<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function (string $class): void {
    $prefix = 'Appleklinika\\BackOffice\\';
    if (str_starts_with($class, $prefix)) { require_once dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
});
use Appleklinika\BackOffice\Application\Port\{OrderLifecycleStore, OrderMutex, InvoiceAutomation, CustomerMailer, FulfilmentStore};
use Appleklinika\BackOffice\Application\{OrderLifecycle, ChangeFulfilment};
use Appleklinika\BackOffice\Domain\{LifecycleOrder, CustomerNotification as Event};

final class MemoryOrders implements OrderLifecycleStore
{
    public bool $managed = true, $paid = true, $handoff = false, $submitted = false, $submissionEligible = false;
    public string $status = 'processing', $number = '', $pdf = '';
    public array $tracking = [], $emails = [], $invoice = [], $issues = [];
    public function order(int $id): ?LifecycleOrder { return new LifecycleOrder($id, $this->managed, $this->paid, $this->status, $this->number, $this->pdf, $this->handoff, $this->tracking, $this->submitted, $this->submissionEligible); }
    public function recordSubmission(int $id): void { $this->submitted = true; }
    public function notification(int $id, string $event): array { return $this->emails[$event] ?? []; }
    public function recordNotification(int $id, string $event, array $record): void { $this->emails[$event] = $record; }
    public function invoiceRecord(int $id): array { return $this->invoice; }
    public function invoiceState(int $id, string $state, string $reason = ''): void { $this->invoice = compact('state', 'reason'); }
    public function issue(int $id, string $code): void { $this->issues[] = $code; }
}
final class FixtureMutex implements OrderMutex { public function synchronized(int $id, callable $operation): mixed { return $operation(); } }
final class FixtureInvoices implements InvoiceAutomation
{
    public int $calls = 0;
    public bool $ready = true, $fail = false, $throw = false;
    public function __construct(public MemoryOrders $orders) {}
    public function ready(): bool { return $this->ready; }
    public function run(int $id): void {
        ++$this->calls;
        if ($this->throw) { throw new RuntimeException('sensitive fixture detail must not enter log'); }
        if (!$this->fail) { $this->orders->number = 'TEST-1'; $this->orders->pdf = '/fixture/invoice.pdf'; }
    }
}
final class FixtureMailer implements CustomerMailer
{
    public array $sent = [];
    public bool $accept = true, $throw = false;
    public function send(int $id, string $event): bool {
        $this->sent[] = $event;
        if ($this->throw) { throw new RuntimeException('delivery unknown'); }
        return $this->accept;
    }
}
$n = 0;
$assert = static function (bool $ok, string $why) use (&$n): void { ++$n; if (!$ok) { throw new RuntimeException($why); } };
$new = static function (): array {
    $store = new MemoryOrders(); $invoice = new FixtureInvoices($store); $mailer = new FixtureMailer();
    return [$store, $invoice, $mailer, new OrderLifecycle($store, new FixtureMutex(), $invoice, $mailer)];
};
[$store, $invoice, $mailer, $service] = $new();
$assert($service->notify(1, Event::PAID) === 'ineligible', 'No email before invoice.');
$store->paid = false; $service->paymentConfirmed(1);
$assert($invoice->calls === 0, 'Unpaid orders never invoice.');
$store->paid = true; $service->paymentConfirmed(1); $service->paymentConfirmed(1);
$assert($invoice->calls === 1 && $store->invoice['state'] === 'ready', 'Repeated payment invoices once.');
for ($i = 0; $i < 5; ++$i) { $service->notify(1, Event::PAID); }
$assert($mailer->sent === [Event::PAID], 'Repeated dispatch accepts the primary once.');
$store->tracking = [['code' => '123', 'url' => 'https://gls-group.eu/HU/en/parcel-tracking/?match=123']];
$assert($service->notify(1, Event::SHIPPED) === 'ineligible', 'Tracking alone never announces handoff.');
$store->handoff = true;
for ($i = 0; $i < 5; ++$i) { $service->notify(1, Event::SHIPPED); }
$assert($mailer->sent === [Event::PAID, Event::SHIPPED], 'One message per actual event.');
$store->status = 'refunded';
$assert($service->notify(1, 'unknown') === 'ineligible', 'Exceptional states cannot send positive mail.');

[$store, $invoice, $mailer, $service] = $new();
$invoice->fail = true; $service->paymentConfirmed(1); $service->paymentConfirmed(1);
$assert($invoice->calls === 1 && $store->invoice['state'] === 'failed', 'Provider failure stays visible and never blindly retries.');
$assert($store->paid && $service->notify(1, Event::PAID) === 'ineligible', 'Invoice failure keeps payment but suppresses positive mail.');
$assert(in_array('invoice_reconciliation_required', $store->issues, true), 'Reconciliation is explicit.');
[$store, $invoice, $mailer, $service] = $new();
$invoice->throw = true; $service->paymentConfirmed(1); $service->paymentConfirmed(1);
$assert($invoice->calls === 1 && $store->invoice['state'] === 'uncertain', 'Timeout/exception never creates an automatic duplicate.');
$invoice->ready = false; $service->paymentConfirmed(1); $invoice->ready = true; $service->paymentConfirmed(1);
$assert($invoice->calls === 1 && $store->invoice['state'] === 'uncertain', 'Configuration changes cannot erase an uncertain invoice attempt.');
$assert(!str_contains(json_encode($store->issues), 'sensitive'), 'Provider exception details are not logged.');
[$store, $invoice, $mailer, $service] = $new();
$invoice->ready = false; $service->paymentConfirmed(1);
$assert($invoice->calls === 0 && $store->invoice['state'] === 'blocked', 'No provider call without licensed TEST setup.');
$invoice->ready = true; $service->paymentConfirmed(1);
$assert($invoice->calls === 1, 'Configuration repair may safely start a first attempt.');
$store->pdf = ''; $service->paymentConfirmed(1);
$assert($invoice->calls === 1 && $store->invoice['state'] === 'document_missing', 'Invoice number without PDF is not regenerated.');
[$store, $invoice, $mailer, $service] = $new();
$store->invoice = ['state' => 'generating']; $service->paymentConfirmed(1);
$assert($invoice->calls === 0, 'Crash during invoice generation requires reconciliation.');
[$store, $invoice, $mailer, $service] = $new();
$service->paymentConfirmed(1); $mailer->throw = true;
$assert($service->notify(1, Event::PAID) === 'uncertain', 'Interrupted mail becomes uncertain.');
$assert($service->notify(1, Event::PAID) === 'skipped' && count($mailer->sent) === 1, 'Uncertain send is never resent.');
[$store, $invoice, $mailer, $service] = $new();
$service->paymentConfirmed(1); $mailer->accept = false;
for ($i = 0; $i < 5; ++$i) { $service->notify(1, Event::PAID); }
$assert(count($mailer->sent) === 3 && $store->emails[Event::PAID]['state'] === 'failed', 'Known rejection has bounded retries.');
$store->managed = false; $service->paymentConfirmed(1);
$assert($service->notify(1, Event::PAID) === 'ineligible', 'Legacy orders remain out of scope.');

// Acknowledgement is its own durable checkout event, not a paid-order synonym.
[$store, $invoice, $mailer, $service] = $new();
$store->paid = false; $store->status = 'pending';
$assert(!$service->orderSubmitted(1), 'An admin/imported order cannot be acknowledged by a fabricated checkout event.');
$assert($service->notify(1, Event::RECEIVED) === 'ineligible', 'No acknowledgement on page views, drafts or payment-only enrollment.');
$store->submissionEligible = true;
$assert($service->orderSubmitted(1), 'Validated checkout persists submission before transport.');
for ($i=0; $i<5; ++$i) { $service->orderSubmitted(1); $service->notify(1, Event::RECEIVED); }
$assert($mailer->sent === [Event::RECEIVED] && $invoice->calls === 0, 'Repeated submissions acknowledge once before payment without invoicing.');
$store->paid = true; $store->status = 'processing'; $service->paymentConfirmed(1);
for ($i=0; $i<5; ++$i) { $service->notify(1, Event::RECEIVED); $service->notify(1, Event::PAID); }
$assert($mailer->sent === [Event::RECEIVED, Event::PAID] && $invoice->calls === 1, 'Payment/callback repeats cannot resend either earlier message.');
[$store, $invoice, $mailer, $service] = $new();
$store->submissionEligible = true; $service->orderSubmitted(1); $service->paymentConfirmed(1);
$assert($service->notify(1, Event::PAID) === 'waiting_acknowledgement' && $mailer->sent === [], 'Fast payment cannot overtake acknowledgement.');
$mailer->throw = true; $service->notify(1, Event::RECEIVED); $mailer->throw = false;
$assert($service->notify(1, Event::RECEIVED) === 'skipped', 'Uncertain acknowledgement is not blindly duplicated.');
$assert($service->notify(1, Event::PAID) === 'waiting_acknowledgement', 'Uncertain first message requires reconciliation before later acceptance.');
[$store, $invoice, $mailer, $service] = $new();
$store->submissionEligible = true; $service->orderSubmitted(1); $mailer->accept = false;
for ($i=0; $i<5; ++$i) { $service->notify(1, Event::RECEIVED); }
$assert(count($mailer->sent) === 3, 'Known first-message rejection has existing bounded retry policy.');
foreach (['checkout-draft','cancelled','refunded','trash','auto-draft'] as $status) {
    $store->status = $status;
    $assert($service->notify(1, Event::RECEIVED) === 'ineligible', 'No acknowledgement for ' . $status);
}

final class FulfilmentFixture implements FulfilmentStore
{
    public array $order = ['state' => 'new', 'mode' => 'gls', 'blocked' => null, 'label' => false, 'tracking' => false];
    public array $events = []; public int $labels = 0;
    public function snapshot(int $id): array { return $this->order; }
    public function createLabel(int $id): void { ++$this->labels; $this->order['label'] = $this->order['tracking'] = true; }
    public function record(int $id, string $action, string $to, int $actor, string $reason): void { $this->order['state'] = $to; $this->events[] = compact('action', 'to', 'actor', 'reason'); }
}
$ful = new FulfilmentFixture(); $change = new ChangeFulfilment($ful, new FixtureMutex());
foreach (['start', 'start_packing', 'packing_completed'] as $action) { $change->execute(1, $action, 7, $ful->order['state']); }
try { $change->execute(1, 'handed_to_gls', 7, $ful->order['state']); $assert(false, 'Missing label must reject.'); } catch (InvalidArgumentException) { $assert(true, 'Missing label rejected.'); }
$change->execute(1, 'create_label', 7, 'ready_for_shipping');
$assert($ful->order['state'] === 'ready_for_shipping' && !in_array('handed_to_gls', array_column($ful->events, 'action'), true), 'Label creation stays operationally silent.');
$change->execute(1, 'handed_to_gls', 7, 'ready_for_shipping');
try { $change->execute(1, 'handed_to_gls', 7, 'ready_for_shipping'); $assert(false, 'Stale request must reject.'); } catch (InvalidArgumentException) { $assert(true, 'Stale handoff rejected.'); }
$change->execute(1, 'delivered', 7, 'handed_to_gls');
$assert($ful->order['state'] === 'delivered', 'Delivery is distinct from carrier handoff.');
$change->execute(1, 'correct', 8, 'delivered', 'packing', 'Recorded too early');
$assert(end($ful->events)['reason'] === 'Recorded too early' && end($ful->events)['actor'] === 8, 'Corrections preserve actor and reason.');
try { $change->execute(1, 'correct', 8, 'packing', 'handed_to_gls', 'Skip'); $assert(false, 'Correction cannot fake actual handoff.'); } catch (InvalidArgumentException) { $assert(true, 'Only handoff action records carrier handoff.'); }
$ful->order['blocked'] = 'Unpaid';
try { $change->execute(1, 'packing_completed', 7, 'packing'); $assert(false, 'Unpaid must reject.'); } catch (InvalidArgumentException) { $assert(true, 'Unpaid operation rejected.'); }
echo "Order lifecycle: $n assertions passed; no database, email, or provider calls.\n";
