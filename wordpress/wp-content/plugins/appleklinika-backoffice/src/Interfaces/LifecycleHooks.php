<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Application\OrderLifecycle;
use Appleklinika\BackOffice\Domain\CustomerNotification;
use Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration;
use Appleklinika\BackOffice\Infrastructure\SzamlazzAutomation;
use Appleklinika\BackOffice\Infrastructure\WooOrderLifecycleStore;

final class LifecycleHooks
{
    public function __construct(private readonly OrderLifecycle $lifecycle, private readonly WooOrderLifecycleStore $store)
    {
    }

    public function register(): void
    {
        add_filter('woocommerce_email_classes', [$this, 'emailClasses']);
        foreach (['customer_processing_order', 'customer_completed_order'] as $email) {
            add_filter('woocommerce_email_enabled_' . $email, [$this, 'legacyEmailEnabled'], 20, 2);
        }
        add_action('woocommerce_payment_complete', [$this, 'paymentComplete'], 20);
        add_action('woocommerce_pre_payment_complete', [$this, 'enrol']);
        add_action('wc_szamlazz_document_created', [$this, 'documentCreated']);
        add_filter('wc_szamlazz_before_generate_invoice_check', [$this, 'invoiceGuard'], 20, 4);
        add_filter('wc_szamlazz_should_generate_auto_invoice', [$this, 'automaticInvoiceAllowed'], 20, 2);
        add_filter('wc_szamlazz_xml', [$this, 'invoiceXml'], 20, 3);
        add_filter('wc_szamlazz_get_option', [$this, 'providerOption'], 20, 2);
        add_action('appleklinika_lifecycle_dispatch', [$this, 'dispatch'], 10, 3);
        add_action('appleklinika_fulfilment_transitioned', [$this, 'transitioned'], 10, 2);
    }

    public function emailClasses(array $emails): array
    {
        $emails['appleklinika_' . CustomerNotification::PAID] = new PaidInvoiceEmail();
        $emails['appleklinika_' . CustomerNotification::SHIPPED] = new CarrierHandoffEmail();
        return $emails;
    }

    public function legacyEmailEnabled(bool $enabled, mixed $order): bool
    {
        return $order instanceof \WC_Order && LifecycleConfiguration::manages($order) ? false : $enabled;
    }

    public function paymentComplete(int $id): void
    {
        try {
            $this->lifecycle->paymentConfirmed($id);
            $this->queue($id, CustomerNotification::PAID);
        } catch (\Throwable) {
            $this->store->issue($id, 'invoice_lifecycle_interrupted');
        }
    }

    public function enrol(int $id): void
    {
        $order = wc_get_order($id);
        if ($order instanceof \WC_Order && LifecycleConfiguration::manages($order)) {
            $order->update_meta_data('_appleklinika_lifecycle_version', 1);
            $order->save_meta_data();
        }
    }

    public function documentCreated(array $document): void
    {
        if (($document['document_type'] ?? '') === 'invoice') {
            $this->queue((int) ($document['order_id'] ?? 0), CustomerNotification::PAID);
        }
    }

    public function transitioned(int $id, string $action): void
    {
        if ($action === 'handed_to_gls') {
            $this->queue($id, CustomerNotification::SHIPPED);
        }
    }

    public function dispatch(int $id, string $event, int $deferrals = 0): void
    {
        try {
            $result = $this->lifecycle->notify($id, $event);
            if ($result === 'failed' && ($this->store->notification($id, $event)['attempts'] ?? 0) < 3) {
                // A known transport rejection is retryable. An uncertain send is not.
                $this->queue($id, $event, 300);
            }
        } catch (\Throwable) {
            $this->store->issue($id, 'notification_dispatch_interrupted');
            if ($deferrals < 3) {
                // A lock timeout can safely retry. If interruption followed the
                // durable sending marker, the service skips it for reconciliation.
                $this->queue($id, $event, 300, $deferrals + 1);
            }
        }
    }

    public function invoiceGuard(mixed $previous, mixed $ids, string $type, array $options): mixed
    {
        foreach ((array) $ids as $id) {
            $order = wc_get_order((int) $id);
            if ($order instanceof \WC_Order && LifecycleConfiguration::manages($order) && $type === 'invoice'
                && (! SzamlazzAutomation::ownsAttempt((int) $id) || count((array) $ids) !== 1)) {
                return ['error' => true, 'messages' => ['Ezt a rendelést a fizetés utáni számla-folyamat kezeli. Hiba esetén a korábbi kérést előbb egyeztesd a Számlázz.hu-val.']];
            }
        }
        return $previous;
    }

    /** The vendor sends booleans from automations and status lists from its metabox. */
    public function automaticInvoiceAllowed(mixed $allowed, int $id): mixed
    {
        $order = wc_get_order($id);
        return $order instanceof \WC_Order && LifecycleConfiguration::manages($order)
            && ! SzamlazzAutomation::ownsAttempt($id) ? false : $allowed;
    }

    public function invoiceXml(mixed $xml, \WC_Order $order, string $type): mixed
    {
        if ($type === 'invoice' && LifecycleConfiguration::manages($order)) {
            // Inspect the resolved account too, including provider account-routing
            // rules, before the XML reaches the network. Never log this key.
            if (! SzamlazzAutomation::verifiedAgentKey((string) $xml->beallitasok->szamlaagentkulcs)) {
                throw new \RuntimeException('The resolved Agent account has not been verified as TEST.');
            }
            $xml->vevo->sendEmail = 'false';
        }
        return $xml;
    }

    public function providerOption(mixed $value, string $key): mixed
    {
        // Provider debug XML contains Agent keys and addresses. Never enable that
        // logger during this TEST rollout; our own diagnostics contain only IDs/codes.
        return LifecycleConfiguration::enabled() && $key === 'debug' ? 'no' : $value;
    }

    private function queue(int $id, string $event, int $delay = 10, int $deferrals = 0): void
    {
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order || ! LifecycleConfiguration::manages($order)) {
            return;
        }
        $args = [$id, $event, $deferrals];
        if (function_exists('as_schedule_single_action')) {
            // A running failed attempt can schedule its successor. Durable order
            // records, not queue uniqueness, provide the duplicate-send protection.
            as_schedule_single_action(time() + $delay, 'appleklinika_lifecycle_dispatch', $args, 'appleklinika-lifecycle');
        } elseif (! wp_next_scheduled('appleklinika_lifecycle_dispatch', $args)) {
            wp_schedule_single_event(time() + $delay, 'appleklinika_lifecycle_dispatch', $args);
        }
    }
}
