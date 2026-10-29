<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Infrastructure/MplCarrier.php';
require_once dirname(__DIR__) . '/src/Infrastructure/ProductionReadiness.php';
require_once dirname(__DIR__) . '/src/Infrastructure/CashPickup.php';
require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/PaymentState.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderPayment.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php';
require_once dirname(__DIR__) . '/src/Application/Port/OrderMutex.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderMutex.php';
require_once dirname(__DIR__) . '/src/Infrastructure/GlsAutomation.php';
require_once dirname(__DIR__) . '/src/Infrastructure/OrderDocuments.php';
require_once dirname(__DIR__) . '/src/Application/Port/ArtifactReconciliationStore.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooArtifactReconciliation.php';
require_once dirname(__DIR__) . '/src/Application/Port/GlsLabelStore.php';
require_once dirname(__DIR__) . '/src/Application/CreateGlsLabel.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooGlsLabelStore.php';
class RepositoryFixtureMutex implements \Appleklinika\BackOffice\Application\Port\OrderMutex { public int $calls = 0; public function synchronized(int $id, callable $operation): mixed { ++$this->calls; return $operation(); } }

$wpdb = new class {
    public string $prefix = 'offline_';
    public function prepare($sql, $name): string { return $sql; }
    public function get_var($sql): string { return '1'; }
};

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;

// Standalone in-memory WooCommerce and provider doubles: no WordPress bootstrap or network.
define('HOUR_IN_SECONDS', 3600);
define('GLS_SHIPPING_ABSPATH', '/provider-files-must-not-be-loaded/');
$GLOBALS['products'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['transient_writes'] = 0;
$GLOBALS['environment'] = 'local';
$GLOBALS['site_home'] = 'http://localhost:18080/';
$GLOBALS['orders'] = [];
$GLOBALS['filters'] = [];
$GLOBALS['backoffice_capability'] = true;

function get_post_meta(int $id, string $key, bool $single): mixed { return $GLOBALS['products'][$id][$key] ?? ''; }
function doing_action(string $hook): bool { return ($GLOBALS['worker_hook'] ?? '') === $hook; }
function get_current_user_id(): int { return 81; }
function get_option(string $key, mixed $default = false): mixed { return $key === 'home' ? $GLOBALS['site_home'] : ($GLOBALS['test_options'][$key] ?? $default); }
function wc_get_logger(): object { return new class { public function error(string $code, array $context): void { $GLOBALS['automation_errors'][] = $code; } }; }
function get_userdata(int $id): object { return (object) ['display_name' => 'QA Employee', 'user_email' => 'qa@example.invalid']; }
function current_user_can(string $capability, mixed ...$arguments): bool { return $capability === 'manage_appleklinika_backoffice' && $GLOBALS['backoffice_capability']; }
function add_filter(string $hook, callable $callback, int $priority, int $acceptedArguments): void { $GLOBALS['filters'][$hook][] = $callback; }
function remove_filter(string $hook, callable $callback, int $priority): void { $GLOBALS['filters'][$hook] = array_values(array_filter($GLOBALS['filters'][$hook] ?? [], static fn ($registered): bool => $registered !== $callback)); }
function apply_filters(string $hook, array $data, array $context): array { foreach ($GLOBALS['filters'][$hook] ?? [] as $callback) { $data = $callback($data, $context); } return $data; }
function current_time(string $type): string|int { return match ($type) { 'mysql' => '2026-09-10 10:15:00', 'Y-m-d' => '2026-09-10', 'Ymd' => '20260910', 'timestamp' => strtotime('2026-09-10 10:15:00'), default => throw new RuntimeException('Unexpected date format.') }; }
function get_transient(string $key): mixed { return $GLOBALS['transients'][$key] ?? false; }
function set_transient(string $key, mixed $value, int $expiry): bool { ++$GLOBALS['transient_writes']; if (!empty($GLOBALS['fail_activity'])) { return false; } $GLOBALS['transients'][$key] = $value; return true; }
function wc_get_order_notes(array $args): array { if ($args['type'] !== 'internal' || $args['limit'] !== 1 || $args['cache_results'] !== false) { throw new RuntimeException('Unbounded or public correction-note lookup.'); } return array_values(array_filter(($GLOBALS['orders'][$args['order_id']]->notes ?? []), static fn(array $note): bool => str_contains($note['content'], $args['search']))); }
function wp_get_environment_type(): string { return $GLOBALS['environment']; }
function home_url(string $path): string { return $GLOBALS['site_home']; }
function wp_parse_url(string $url, int $component): mixed { return parse_url($url, $component); }
function wc_get_order(int $id): WC_Order|false { return $GLOBALS['orders'][$id] ?? false; }

class RepositoryMetaDouble
{
    public array $metadata = [];
    public function get_meta(string $key, bool $single = true, string $context = 'view'): mixed
    {
        $values = $this->metadata[$key] ?? [];
        return $single ? ($values[0] ?? '') : array_map(static fn ($value): object => (object) ['value' => $value], $values);
    }
    public function update_meta_data(string $key, mixed $value): void { $this->metadata[$key] = [$value]; }
}

class WC_Order_Item_Product extends RepositoryMetaDouble
{
    public function __construct(private string $name, private int $productId = 7) {}
    public function get_name(): string { return $this->name; }
    public function get_product_id(): int { return $this->productId; }
    public function get_variation_id(): int { return 0; }
    public function get_quantity(): int { return 1; }
    public function get_product(): never { throw new RuntimeException('Device display must not load the current product name.'); }
}

class WC_Order extends RepositoryMetaDouble
{
    public array $items = [];
    public array $notes = [];
    public bool $paid = true;
    public bool $noteSucceeds = true;
    public bool $noteThrows = false;
    public bool $probeForeignNotes = false;
    public array $noteAuthors = [];
    public array $foreignNoteProbes = [];
    public string $status = 'processing';
    public string $payment = 'barion';
    public function get_payment_method(): string { return $this->payment; }
    public string $shippingMethod = 'gls_shipping_method';
    public int $saves = 0;
    public function __construct(private int $id = 1376) {}
    public function get_id(): int { return $this->id; }
    public function get_items(string $type): array { return $this->items; }
    public function get_formatted_line_subtotal(WC_Order_Item_Product $item): string { return "4 000 Ft"; }
    public function get_status(): string { return $this->status; }
    public function has_status(string $status): bool { return $this->status === $status; }
    public function is_paid(): bool { return $this->paid; }
    public function get_date_paid(): ?string { return $this->paid ? "2026-10-16" : null; }
    public function get_shipping_methods(): array
    {
        return [new class($this->shippingMethod) {
            public function __construct(private string $id) {}
            public function get_method_id(): string { return $this->id; }
            public function get_instance_id(): int { return 0; }
        }];
    }
    public function add_order_note(string $content, bool $customer = false, bool $employee = false): int
    {
        if ($this->noteThrows) { throw new RuntimeException('Simulated note persistence failure.'); }
        if (! $this->noteSucceeds) { return 0; }
        $comment = ['comment_post_ID' => $this->id, 'comment_type' => 'order_note', 'comment_content' => $content, 'comment_author' => 'WooCommerce', 'comment_author_email' => 'woocommerce@example.invalid', 'user_id' => 0];
        $context = ['order_id' => $this->id, 'is_customer_note' => $customer];
        if ($this->probeForeignNotes) {
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', array_replace($comment, ['comment_post_ID' => 999]), array_replace($context, ['order_id' => 999]));
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', array_replace($comment, ['comment_content' => 'Unrelated note']), $context);
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', $comment, array_replace($context, ['is_customer_note' => true]));
        }
        $this->noteAuthors[] = apply_filters('woocommerce_new_order_note_data', $comment, $context);
        $this->notes[] = compact('content', 'customer', 'employee');
        return count($this->notes);
    }
    public function save(): int { ++$this->saves; return $this->id; }
    public function save_meta_data(): void { ++$this->saves; }
    public function read_meta_data(bool $force = false): void {}
}

class GLS_Shipping_For_Woo {}
class GLS_Shipping_Account_Helper
{
    public static array|false $account = ['client_id' => 'fixture-client', 'username' => 'fixture-user', 'password' => 'fixture-secret', 'mode' => 'sandbox'];
    public static function get_active_account(): array|false { return self::$account; }
    public static function get_account_setting(string $key): string { return 'fixture-phone'; }
}
class GLS_Shipping_Sender_Address_Helper
{
    public static array $sender = ['Name' => 'Fixture Store', 'Street' => 'Fixture Street 1', 'City' => 'Fixture City', 'ZipCode' => '1000', 'CountryIsoCode' => 'HU', 'ContactPhone' => 'fixture-phone'];
    public static function get_default_sender_address(): array { return self::$sender; }
    public static function format_for_api_pickup(array $address, string $phone): array { return $address; }
}

function load_repository_provider_doubles(): void
{
    class GLS_Shipping_API_Data {}
    class GLS_Shipping_API_Service {}
    class GLS_Shipping_Order
    {
        public static int $calls = 0;
        public static bool $fail = false;
        public function generate_single_order_label(int $id): array
        {
            return (new \Appleklinika\BackOffice\Application\CreateGlsLabel(new \Appleklinika\BackOffice\Infrastructure\WooGlsLabelStore(), new RepositoryFixtureMutex()))->execute($id, static function () use ($id) {
            ++self::$calls;
            if (self::$fail) { return ['success' => false, 'error' => 'Offline provider failure']; }
            wc_get_order($id)->update_meta_data('_gls_print_label', 'recorded-fixture-label.pdf');
            return ['success' => true];
            });
        }
    }
}

final class RepositoryOperationsTest
{
    private int $assertions = 0;
    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) { throw new RuntimeException($message); }
    }
    private function rejects(callable $operation, string $message): void
    {
        try { $operation(); } catch (InvalidArgumentException) { $this->assert(true, $message); return; }
        $this->assert(false, $message);
    }
    public function run(): void
    {
        $mutex = new RepositoryFixtureMutex();
        $repository = new WooOrderBackOfficeRepository(null, $mutex);
        $order = new WC_Order();
        $item = new WC_Order_Item_Product('Ordered device name');
        $order->items = [$item];
        $GLOBALS['products'][7] = ['_appleklinika_internal_identifier' => 'CURRENT_OTHER_DEVICE', '_appleklinika_battery_health' => '88'];
        $item->metadata[OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = ['ORDERED_IMEI', 'ORDERED_SERIAL', 'ORDERED_IMEI'];
        $order->metadata[OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = ['ORDER_SNAPSHOT'];
        $device = $repository->deviceItems($order)[0];
        $identifier = 'Belső azonosító / IMEI';
        $this->assert($device['name'] === 'Ordered device name', 'Purchased line name remains immutable even if the live product changes.');
        $this->assert($device['details'][$identifier] === 'ORDERED_IMEI / ORDERED_SERIAL', 'Line snapshots override order/live identifiers and preserve exact underscores without duplicate values.');
        $this->assert($device['detail_sources'][$identifier] === 'order', 'Immutable device identifiers declare their order-time source.');
        $this->assert($device['details']['Akkumulátor állapota'] === '88%' && $device['detail_sources']['Akkumulátor állapota'] === 'product', 'Remaining live metadata is explicitly identified as current product data.');
        $item->metadata = ['_appleklinika_battery_health' => ['97'], '_appleklinika_internal_identifier' => ['LEGACY_ITEM_ID']];
        $device = $repository->deviceItems($order)[0];
        $this->assert($device['details'][$identifier] === 'LEGACY_ITEM_ID' && $device['details']['Akkumulátor állapota'] === '97%' && $device['detail_sources']['Akkumulátor állapota'] === 'order', 'Existing item metadata takes precedence over live product data.');
        $item->metadata = ['_appleklinika_serial_number' => ['ITEM_SERIAL']];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ITEM_SERIAL', 'Legacy item serial numbers remain useful after product changes.');
        $item->metadata = [];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ORDER_SNAPSHOT', 'A single-line order can use its order-level device snapshot.');
        $order->items[] = new WC_Order_Item_Product('Second line', 8);
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'CURRENT_OTHER_DEVICE', 'An unassigned order snapshot is not incorrectly attributed to a line in a multi-item order.');
        $this->assert(! isset($repository->deviceItems($order)[1]['details'][$identifier]), 'Missing multi-item identifier data stays missing rather than showing another device.');
        $order->items = [new WC_Order_Item_Product('Deleted product snapshot', 0)];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ORDER_SNAPSHOT', 'Order snapshots remain available after the original product is deleted.');

        $GLOBALS['orders'][1376] = $order;
        $repository->recordTransition($order, 'start', FulfilmentWorkflow::PREPARATION, 81);
        $order->probeForeignNotes = true;
        $repository->addInternalNote($order, ' Confidential note text ');
        $history = $repository->history($order);
        $noteEvent = $history[1];
        $this->assert(count($history) === 2 && $history[0]['action'] === 'start', 'Adding a note preserves the existing workflow history.');
        $this->assert($noteEvent === ['order_id' => 1376, 'action' => 'note', 'from' => 'preparation', 'to' => 'preparation', 'user_id' => 81, 'user' => 'QA Employee', 'at' => '2026-09-10 10:15:00'], 'Note activity records employee identity, order, timestamp and unchanged workflow state.');
        $this->assert(! str_contains(json_encode($history), 'Confidential note text'), 'Activity history never duplicates the confidential note body.');
        $this->assert($order->notes[1] === ['content' => '[appleklinika-backoffice-manual] Confidential note text', 'customer' => false, 'employee' => true], 'The note itself remains an internal employee-authored WooCommerce note.');
        $this->assert(! current_user_can('edit_shop_orders') && $order->noteAuthors[1]['comment_author'] === 'QA Employee' && $order->noteAuthors[1]['comment_author_email'] === 'qa@example.invalid' && $order->noteAuthors[1]['user_id'] === 81, 'A user with only the Back Office capability remains the actual WooCommerce comment author and user ID.');
        $this->assert(count($order->foreignNoteProbes) === 3 && array_column($order->foreignNoteProbes, 'comment_author') === ['WooCommerce', 'WooCommerce', 'WooCommerce'], 'The author filter ignores other orders, unrelated note content and customer notes.');
        $this->assert(($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'The temporary author filter is removed immediately after a successful note save.');
        $this->assert(count($repository->todayActivity()) === 2 && $GLOBALS['transient_writes'] === 2, 'Each successful workflow/note action appends once to the existing daily activity storage.');
        $this->assert(FulfilmentWorkflow::employeeDailyCounts($repository->todayActivity())[0]['count'] === 2, 'Daily employee counts include both state changes and internal notes.');
        $this->assert($repository->state($order) === FulfilmentWorkflow::PREPARATION, 'An internal note does not change fulfilment progress.');
        $this->rejects(fn () => $repository->addInternalNote($order, ' '), 'Blank notes are rejected.');
        $order->noteSucceeds = false;
        $this->rejects(fn () => $repository->addInternalNote($order, 'Not saved'), 'A failed WooCommerce note save is not reported as successful.');
        $this->assert(($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'A failed note insert still removes the temporary author filter.');
        $order->noteThrows = true;
        try {
            $repository->addInternalNote($order, 'Throwing save');
            $this->assert(false, 'A throwing note insert must propagate its failure.');
        } catch (RuntimeException $error) {
            $this->assert($error->getMessage() === 'Simulated note persistence failure.' && ($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'A throwing insert removes the temporary author filter through finally.');
        }
        $order->noteThrows = false;
        $GLOBALS['backoffice_capability'] = false;
        $this->rejects(fn () => $repository->addInternalNote($order, 'Unauthorized note'), 'The actor override never grants access to a user without the Back Office capability.');
        $GLOBALS['backoffice_capability'] = true;
        $this->assert(count($repository->history($order)) === 2 && $GLOBALS['transient_writes'] === 2, 'Rejected/failed notes do not create activity.');
        $GLOBALS['transients']['appleklinika_backoffice_activity_20260910'][] = ['at' => '2026-09-09 23:59:59'];
        $this->assert(count($repository->todayActivity()) === 2, 'Today activity excludes entries from another day.');

        $reviewOrder = new WC_Order(529);
        $before = ['shipping' => [], 'payment' => 'bacs', 'paid' => false];
        $after = ['shipping' => [['method'=>'local_pickup','instance'=>27]], 'payment'=>'bacs','paid'=>false];
        $repository->recordReview($reviewOrder, 'resolve_shipping', $before, $after, 'Verified customer pickup request', 'Shipping repaired');
        $event = $repository->history($reviewOrder)[0];
        $this->assert($event['user_id'] === 81 && $event['user'] === 'QA Employee' && $event['order_id'] === 529 && $event['at'] === '2026-09-10 10:15:00', 'Recovery audit persists actual employee/order/time.');
        $this->assert($event['review'] === ['before'=>$before,'after'=>$after,'reason'=>'Verified customer pickup request','result'=>'Shipping repaired','source'=>'backoffice_staff_review'], 'Recovery audit preserves authoritative old/new data and evidence source.');
        $this->assert($event['from'] === $event['to'] && count($repository->todayActivity()) === 3, 'Review adds daily activity without changing fulfilment.');
        $this->assert($reviewOrder->notes[0]['customer'] === false, 'Recovery reason and employee never become a customer note.');
        $GLOBALS['backoffice_capability'] = false;
        $this->rejects(fn () => $repository->recordReview($reviewOrder, 'resolve_shipping', [], [], 'Denied', 'Denied'), 'Unauthorized recovery audit rejected.');
        $GLOBALS['backoffice_capability'] = true;
        $this->assert(count($repository->history($reviewOrder)) === 1, 'Rejected audit does not append history.');

        // Exercise the actual audit adapter, not just the command/store double.
        $correction = new WC_Order(9001); $GLOBALS['orders'][9001] = $correction;
        $journal = ['id'=>str_repeat('a',64),'before'=>['state'=>'new','shipping'=>[]],'user_id'=>81,'user'=>'QA Employee','at'=>'2026-09-10 10:10:00','reason'=>'Verified original customer instruction'];
        $afterCorrection = ['state'=>'new','shipping'=>[['method'=>'local_pickup','instance'=>2]]];
        $activityBefore = count($repository->todayActivity());
        $correction->noteSucceeds = false;
        try { $repository->recordShippingReview($correction, $journal, $afterCorrection); $this->assert(false, 'Failed correction note must not report completion.'); }
        catch (RuntimeException) { $this->assert(count($repository->history($correction)) === 1 && count($correction->notes) === 0, 'A note failure leaves the durable history intact for reconciliation.'); }
        $this->assert(count($repository->todayActivity()) === $activityBefore, 'Failed projection is not falsely published as completed daily activity.');
        $correction->noteSucceeds = true;
        $GLOBALS['fail_activity'] = true;
        try { $repository->recordShippingReview($correction, $journal, $afterCorrection); $this->assert(false, 'Failed activity storage must be explicit.'); }
        catch (RuntimeException) { $this->assert(count($repository->history($correction)) === 1 && count($correction->notes) === 1, 'Activity failure preserves the single history event and single internal note.'); }
        $GLOBALS['fail_activity'] = false;
        $repository->recordShippingReview($correction, $journal, $afterCorrection);
        $repository->recordShippingReview($correction, $journal, $afterCorrection);
        $this->assert(count($repository->history($correction)) === 1 && count($correction->notes) === 1 && count($repository->todayActivity()) === $activityBefore + 1, 'Repeated reconciliation creates exactly one history, one note and one daily activity.');
        $entry = $repository->history($correction)[0];
        $this->assert($entry['review_operation'] === $journal['id'] && $entry['user_id'] === 81 && $entry['review']['requested_at'] === $journal['at'] && $entry['review']['finalized_by'] === 81, 'Stable operation identity preserves the original actor and decision time separately from finalization.');
        $this->assert($correction->notes[0]['customer'] === false && str_contains($correction->notes[0]['content'], '[akbo-review:'.$journal['id'].']'), 'Correction notes are private and carry the durable duplicate-detection marker.');
        $correction->update_meta_data('_appleklinika_shipping_review_operation', ['id'=>$journal['id'],'stage'=>'prepared']);
        $this->assert(str_contains((string)$repository->fulfilmentBlockReason($correction), 'napló'), 'Unfinished shipping audit blocks ordinary fulfilment.');
        $GLOBALS['backoffice_capability'] = false;
        $this->rejects(fn () => $repository->recordShippingReview($correction, $journal, $afterCorrection), 'Correction projections require Back Office capability even when the operation is known.');
        $GLOBALS['backoffice_capability'] = true;

        $this->assert(! class_exists('GLS_Shipping_Order') && $repository->glsReadinessMessage() === null, 'Configured frontend GLS readiness does not depend on the admin-only operation class.');
        GLS_Shipping_Account_Helper::$account['password'] = '';
        $this->assert($repository->glsReadinessMessage() !== null, 'Missing real provider credentials disable GLS generation.');
        GLS_Shipping_Account_Helper::$account['password'] = 'fixture-secret';
        GLS_Shipping_Account_Helper::$account['mode'] = 'production';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'Sandbox'), 'Local operation rejects production GLS mode.');
        $GLOBALS['environment'] = 'production';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'production-readiness'), 'A localhost clone reporting production must first pass the production guard.');
        $GLOBALS['environment'] = 'staging';
        $GLOBALS['site_home'] = 'https://staging.example.invalid/';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'Sandbox'), 'Non-production environments require sandbox independently of hostname.');
        GLS_Shipping_Account_Helper::$account['mode'] = 'sandbox';
        GLS_Shipping_Sender_Address_Helper::$sender['Street'] = '';
        $this->assert($repository->glsReadinessMessage() !== null, 'An incomplete provider-resolved sender address disables generation.');
        GLS_Shipping_Sender_Address_Helper::$sender['Street'] = 'Fixture Street 1';

        // Declared only now: earlier frontend-readiness checks cannot see the admin class.
        load_repository_provider_doubles();
        $order = new WC_Order();
        $GLOBALS['orders'][1376] = $order;
        $this->rejects(fn () => $repository->createGlsLabel($order), 'An invalid fulfilment transition is rejected before calling GLS.');
        $order->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        $order->paid = false;
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Unpaid orders cannot trigger the provider.');
        $order->paid = true;
        $order->shippingMethod = 'local_pickup';
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Pickup orders cannot trigger GLS generation.');
        $order->shippingMethod = 'gls_shipping_method';
        $order->status = 'cancelled';
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Cancelled orders cannot trigger the provider.');
        $order->status = 'processing';
        $this->assert(GLS_Shipping_Order::$calls === 0, 'All ineligible actions were rejected before any provider call.');
        $repository->createGlsLabel($order);
        $this->assert(GLS_Shipping_Order::$calls === 1 && $repository->hasGlsLabel($order), 'An eligible action reuses the installed provider entry point and verifies recorded label metadata.');
        $this->rejects(fn () => $repository->createGlsLabel($order), 'A recorded label prevents duplicate generation even if its file is later unavailable.');
        $this->assert(GLS_Shipping_Order::$calls === 1, 'Duplicate prevention takes effect before the provider call.');

        $recovery = new WC_Order(1377);
        $GLOBALS['orders'][1377] = $recovery;
        $recovery->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        $recovery->update_meta_data('_gls_parcel_ids', [12345]);
        $this->rejects(fn () => $repository->createGlsLabel($recovery), 'Partial parcel metadata blocks a duplicate label even without a PDF.');
        $recovery->update_meta_data('_gls_parcel_ids', []);
        GLS_Shipping_Order::$fail = true;
        $this->rejects(fn () => $repository->createGlsLabel($recovery), 'An actual provider error is not successful label creation.');
        $this->assert($recovery->get_meta('_appleklinika_gls_attempt')['state'] === 'uncertain', 'Provider failure leaves a durable reconciliation marker.');
        $beforeRetry = GLS_Shipping_Order::$calls;
        $this->rejects(fn () => $repository->createGlsLabel($recovery), 'A repeated recovery never blindly repeats an uncertain provider operation.');
        $this->assert(GLS_Shipping_Order::$calls === $beforeRetry, 'Uncertain recovery rejection occurs before the provider call.');
        GLS_Shipping_Order::$fail = false;

        $order->update_meta_data('_appleklinika_backoffice_delivery_mode', 'gls');
        $order->shippingMethod = 'local_pickup';
        $this->assert($repository->deliveryMode($order) === 'pickup', 'A stale GLS snapshot never overrides actual pickup shipping.');
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Stale carrier snapshots cannot authorize generation for pickup.');
        $order->shippingMethod = '';
        $this->assert($repository->deliveryMode($order) === 'unknown', 'Missing WooCommerce method data stays unknown regardless of old metadata.');
        $order->shippingMethod = 'gls_shipping_method';
        $beforeState = $order->get_meta(FulfilmentWorkflow::META_KEY);
        $repository->recordDocumentAccess($order, 'invoice');
        $entry = array_slice($repository->history($order), -1)[0];
        $this->assert($entry['action'] === 'open_invoice' && $entry['order_id'] === 1376 && $entry['user_id'] === 81 && $entry['user'] === 'QA Employee' && $entry['at'] === '2026-09-10 10:15:00', 'Document access records the real actor, order, action and time in existing history.');
        $this->assert($entry['from'] === $entry['to'] && $order->get_meta(FulfilmentWorkflow::META_KEY) === $beforeState, 'Document access never changes fulfilment state.');
        $this->assert(array_slice($repository->todayActivity(), -1)[0]['action'] === 'open_invoice', 'Document access also appears in today’s bounded activity index.');
        foreach (['invoice_generation', 'invoice_generated', 'invoice_generation_failed'] as $action) {
            $repository->recordDocumentAccess($order, $action);
            $entry = array_slice($repository->history($order), -1)[0];
            $this->assert($entry['action'] === $action && $entry['user_id'] === 81 && $entry['from'] === $entry['to'], 'Invoice operation audit preserves employee identity without changing fulfilment.');
        }
        $repository->recordDocumentAccess($order, 'gls_label');
        $this->assert(array_slice($repository->history($order), -1)[0]['action'] === 'open_gls_label', 'Label access has its own activity action.');
        $this->rejects(fn () => $repository->recordDocumentAccess($order, 'arbitrary'), 'Unrecognized document operations cannot enter history.');
        $GLOBALS['backoffice_capability'] = false;
        $this->rejects(fn () => $repository->recordDocumentAccess($order, 'invoice'), 'Document access audit cannot be written by a user without Back Office access.');
        $GLOBALS['backoffice_capability'] = true;

        $this->assert($mutex->calls >= 8, 'Notes and document history use the same order lock contract.');
        $stale = new WC_Order();
        $stale->update_meta_data(FulfilmentWorkflow::META_KEY, 'new');
        $beforeHistory = count($repository->history($order));
        $repository->recordDocumentAccess($stale, 'invoice');
        $this->assert(count($repository->history($order)) === $beforeHistory + 1 && $repository->state($order) === 'ready_for_shipping', 'Document audit reloads under lock; a stale request cannot overwrite state or history.');

        $GLOBALS['site_home'] = 'https://teszt.appleklinika.com'; $GLOBALS['environment'] = 'staging';
        $auditOrder = new WC_Order(1382); $GLOBALS['orders'][1382] = $auditOrder;
        $auditOrder->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        $this->assert(str_contains($repository->operationalStateLabel($auditOrder), 'dokumentumokra vár'), 'Packed order without provider files is not operationally ready.');
        if (method_exists($repository, 'transition')) {
            $this->rejects(fn()=>$repository->transition($auditOrder,'handed_to_gls',81),'Forged handover POST cannot bypass missing provider documents.');
        }
        $GLOBALS['worker_hook'] = \Appleklinika\BackOffice\Infrastructure\WooArtifactReconciliation::WORK;
        $repository->recordAutomation(1382,'gls','done','gls:done');
        $repository->recordAutomation(1382,'gls','done','gls:done');
        $systemHistory=$repository->history($auditOrder);
        $this->assert(count($systemHistory)===1 && $systemHistory[0]['user_id']===0 && $systemHistory[0]['user']==='Automatika', 'Background work is audited once as the system, never as an invented employee.');
        $systemDaily=array_filter($repository->todayActivity(),static fn($e)=>($e['automation_operation']??'')==='artifact:1382:gls:done');
        $this->assert(count($systemDaily)===1,'Repeated automation audit repairs the same daily projection without duplicate activity.');
        $manualHistory=$systemHistory[0]; unset($manualHistory['automation_operation']); $manualHistory['user_id']=81; $manualHistory['user']='QA Employee';
        $auditOrder->update_meta_data('_appleklinika_backoffice_history',[$manualHistory]);
        $repository->recordAutomation(1382,'gls','done','gls:done');
        $this->assert(count($repository->history($auditOrder))===1 && $repository->history($auditOrder)[0]['user_id']===81,'Observing an already audited manual recovery does not invent a second automatic generation.');
        $GLOBALS['worker_hook']='';
        $this->rejects(fn()=>$repository->recordAutomation(1382,'gls','done','forged'),'Ordinary requests cannot impersonate automation audit.');
        $automatic = new \Appleklinika\BackOffice\Infrastructure\GlsAutomation($repository);
        $GLOBALS['site_home'] = 'https://teszt.appleklinika.com';
        $GLOBALS['environment'] = 'staging';
        $autoOrder = new WC_Order(1378); $GLOBALS['orders'][1378] = $autoOrder;
        $autoOrder->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::PACKING);
        $beforeAutomatic = GLS_Shipping_Order::$calls;
        $this->assert(str_contains($automatic->status($autoOrder), 'Csomagolás kész'), 'Read-only GLS state names the actual packing trigger.');
        $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic, 'No automatic label before packing is complete.');
        $autoOrder->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        foreach (['local_pickup', '', 'free_shipping'] as $method) {
            $autoOrder->shippingMethod = $method; $automatic->run(1378);
            $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic, 'Pickup/unknown shipping never enters automatic GLS.');
        }
        $autoOrder->shippingMethod = 'gls_shipping_method'; $autoOrder->paid = false; $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic && $autoOrder->get_meta('_appleklinika_gls_attempt')['state'] === 'blocked', 'Unpaid packing cannot call GLS; the actual block is durable.');
        $autoOrder->paid = true; $autoOrder->payment = 'cod'; $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic, 'COD processing status is not proof of payment for automatic labels.');
        $autoOrder->payment = 'barion'; $GLOBALS['backoffice_capability'] = false; $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic, 'Unauthorized events cannot initiate automatic GLS.');
        $GLOBALS['backoffice_capability'] = true; $GLOBALS['environment'] = 'production'; $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic, 'TEST rollout cannot start production labels.');
        $GLOBALS['environment'] = 'local';
        $automatic->run(1378); $automatic->run(1378);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic + 1 && $repository->hasGlsLabel($autoOrder), 'Packing automatically reuses the provider once, including after a resolved preflight block.');
        $this->assert(array_slice($repository->history($autoOrder), -1)[0]['action'] === 'gls_label_generated' && $autoOrder->get_meta(FulfilmentWorkflow::META_KEY) === FulfilmentWorkflow::READY_FOR_SHIPPING, 'Automatic label success is audited and never marks physical handoff.');
        $failedAuto = new WC_Order(1379); $GLOBALS['orders'][1379] = $failedAuto;
        $failedAuto->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        GLS_Shipping_Order::$fail = true; $automatic->run(1379); $automatic->run(1379);
        $this->assert(GLS_Shipping_Order::$calls === $beforeAutomatic + 2 && $failedAuto->get_meta('_appleklinika_gls_attempt')['state'] === 'uncertain', 'Failed automatic GLS is durable and never blindly retried.');
        $this->assert(str_contains($automatic->status($failedAuto), 'Offline provider failure') && array_slice($repository->history($failedAuto), -1)[0]['action'] === 'gls_automation_failed', 'Actual automatic provider failure is visible and audited.');
        $this->assert(empty($GLOBALS['automation_errors']), 'No unexpected integration exception was swallowed in these cases.');
        GLS_Shipping_Order::$fail = false;

        $cod = new WC_Order(1380); $GLOBALS['orders'][1380] = $cod;
        $cod->payment = 'cod'; $cod->paid = false; $cod->status = 'on-hold';
        $cod->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        $GLOBALS['test_options']['woocommerce_cod_settings'] = ['enabled'=>'yes','enable_for_methods'=>['gls_shipping_method']];
        $calls = GLS_Shipping_Order::$calls;
        $this->assert($repository->fulfilmentBlockReason($cod) !== null, 'Courier COD remains outside the approved Barion-only launch.');
        $automatic->run(1380); $automatic->run(1380);
        $this->assert(GLS_Shipping_Order::$calls === $calls && !$repository->hasGlsLabel($cod), 'Courier COD cannot create a label even if an old setting allows the method.');
        $this->assert(!$cod->paid && $cod->payment === 'cod' && $cod->status === 'on-hold', 'GLS generation preserves COD and does not record payment.');
        echo "Back Office repository operations passed: {$this->assertions} assertions.\n";
    }
}

(new RepositoryOperationsTest())->run();
