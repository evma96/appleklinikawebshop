<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\SzamlazzInvoice;
use Appleklinika\BackOffice\Infrastructure\GlsAutomation;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;

/** Maps existing provider/fulfilment events to their integration adapters. */
final class ProviderObservationHooks
{
    public function register(): void
    {
        $reconcile = new \Appleklinika\BackOffice\Infrastructure\WooArtifactReconciliation();
        add_action('init', [$reconcile, 'schedule'], 30);
        add_action(\Appleklinika\BackOffice\Infrastructure\WooArtifactReconciliation::SCAN, [$reconcile, 'scan']);
        add_action(\Appleklinika\BackOffice\Infrastructure\WooArtifactReconciliation::WORK, [$reconcile, 'dispatch']);
        add_action('woocommerce_payment_complete', [$reconcile, 'enqueue'], 100);
        add_action('appleklinika_fulfilment_transitioned', [$reconcile, 'enqueue'], 100);
        add_action('woocommerce_barion_init', [\Appleklinika\BackOffice\Infrastructure\BarionPaymentReview::class, 'capture'], 10, 2);
        add_action('wc_szamlazz_after_invoice_error', [$this, 'invoiceFailed'], 20, 2);
        add_action('appleklinika_fulfilment_transitioned', [$this, 'transitioned'], 20, 2);
    }

    public function invoiceFailed(mixed $order, mixed $response): void
    {
        if ($order instanceof \WC_Order) {
            (new SzamlazzInvoice())->rememberFailure($order, $response);
        }
    }

    public function transitioned(int $id, string $action): void
    {
        if ($action === 'packing_completed') {
            (new GlsAutomation(new WooOrderBackOfficeRepository()))->run($id);
        }
    }
}
