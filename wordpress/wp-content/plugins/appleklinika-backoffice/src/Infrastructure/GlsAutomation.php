<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\DeliveryMode;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use WC_Order;

/** Packing-complete adapter to the installed GLS operation, not another GLS client. */
final class GlsAutomation
{
    public function __construct(private readonly WooOrderBackOfficeRepository $orders)
    {
    }

    public static function enabled(): bool
    {
        // Production still requires the existing explicit activation gate.
        $host = strtolower((string) parse_url((string) get_option('home', ''), PHP_URL_HOST));
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') { return ProductionReadiness::allowed(); }
        return function_exists('wp_get_environment_type')
            && in_array($host, ['teszt.appleklinika.com', 'localhost', '127.0.0.1'], true);
    }

    /** Read-only. Never queues or starts work from an order-page GET. */
    public function status(WC_Order $order): string
    {
        if (!self::enabled()) { return 'Az automatikus GLS-folyamat ebben a környezetben nincs engedélyezve.'; }
        if (($reason = $this->orders->glsReadinessMessage()) !== null) { return $reason; }
        if (($message = WooArtifactReconciliation::message($order, 'gls')) !== null) {
            $last = $order->get_meta('_appleklinika_gls_attempt', true);
            return $message . (is_array($last) && !empty($last['message']) ? ' ' . $last['message'] : '');
        }
        $attempt = $order->get_meta('_appleklinika_gls_attempt', true);
        if (is_array($attempt) && ($attempt['state'] ?? '') === 'retryable') { return 'A GLS kérés elutasítva. Az adatok/beállítások javítása után a helyreállítási művelettel biztonságosan újrapróbálható.'; }
        if (is_array($attempt) && !empty($attempt['state']) && $attempt['state'] !== 'ready') {
            return 'A GLS feldolgozás ellenőrzést igényel. ' . ($attempt['message'] ?? 'A korábbi kérés eredményét egyeztetni kell; új címke nem indul automatikusan.');
        }
        if (($block = $this->orders->fulfilmentBlockReason($order)) !== null) { return $block; }
        if ($this->orders->state($order) !== FulfilmentWorkflow::READY_FOR_SHIPPING) {
            return 'Automatikus GLS feldolgozásra vár — a „Csomagolás kész” művelet indítja. Nem szükséges külön címkekészítés.';
        }
        return 'A csomagolás kész, de a GLS címke még hiányzik. Az ütemezett egyeztetés az aktuális jogosultság és szolgáltatói eredmény alapján pótolja az elmaradt műveletet; még nem adható futárnak.';
    }

    /** Invoked after the authorized packing transition has been persisted. */
    public function run(int $id): void
    {
        if (!self::enabled() || get_current_user_id() < 1
            || (!current_user_can('manage_appleklinika_backoffice') && !current_user_can('manage_options') && !(current_user_can('manage_woocommerce') && current_user_can('edit_shop_order', $id)))) { return; }
        $this->process($id, false);
    }

    /** Only the registered server-side worker may run without an employee session. */
    public function runScheduled(int $id): void
    {
        if (!WooArtifactReconciliation::enabled() || !doing_action(WooArtifactReconciliation::WORK)) { return; }
        $this->process($id, true);
    }

    private function process(int $id, bool $scheduled): void
    {
        try {
            (new WooOrderMutex())->synchronized($id, function () use ($id, $scheduled): void {
                $order = wc_get_order($id);
                if (!$order instanceof WC_Order || $this->orders->deliveryMode($order) !== DeliveryMode::GLS
                    || $this->orders->state($order) !== FulfilmentWorkflow::READY_FOR_SHIPPING) { return; }
                // Any provider evidence (including a missing PDF with parcel IDs)
                // forbids another generation request, regardless of local state.
                foreach (['_gls_print_label', '_gls_parcel_ids', '_gls_tracking_codes', '_gls_tracking_code'] as $key) {
                    if ($order->get_meta($key, true)) { return; }
                }
                $attempt = $order->get_meta('_appleklinika_gls_attempt', true);
                if (is_array($attempt) && in_array($attempt['state'] ?? '', ['generating', 'attempting', 'failed', 'uncertain', 'ready'], true)) { return; }
                try {
                    if (($block = $this->orders->fulfilmentBlockReason($order)) !== null) {
                        throw new \InvalidArgumentException($block);
                    }
                    $this->orders->createGlsLabel($order);
                    if (!$scheduled) { $this->orders->recordDocumentAccess(wc_get_order($id), 'gls_label_generated'); }
                } catch (\Throwable $error) {
                    $fresh = wc_get_order($id);
                    if (!$fresh instanceof WC_Order) { return; }
                    if ($fresh->get_meta('_gls_print_label', true)) { return; } // Document wins over an audit failure.
                    $record = $fresh->get_meta('_appleklinika_gls_attempt', true);
                    $record = is_array($record) ? $record : [];
                    $record['state'] = empty($record['state']) || $record['state'] === 'blocked' ? 'blocked' : ($record['state'] === 'retryable' ? 'retryable' : 'uncertain');
                    $record['message'] = !empty($record['message']) ? $record['message'] : $this->safeError($error);
                    $record['at'] = current_time('mysql');
                    $fresh->update_meta_data('_appleklinika_gls_attempt', $record);
                    $fresh->save_meta_data();
                    if (!$scheduled) { $this->orders->recordDocumentAccess($fresh, 'gls_automation_failed'); }
                }
            });
        } catch (\Throwable) {
            // Do not undo an already recorded packing transition or leak a response.
            wc_get_logger()->error('gls_automation_interrupted', ['source' => 'appleklinika-backoffice', 'order_id' => $id]);
        }
    }

    private function safeError(\Throwable $error): string
    {
        if (!$error instanceof \InvalidArgumentException) { return 'A szolgáltatói művelet megszakadt; az eredmény egyeztetése szükséges.'; }
        $message = $error->getMessage();
        $account = class_exists('GLS_Shipping_Account_Helper') ? \GLS_Shipping_Account_Helper::get_active_account() : [];
        foreach (['username', 'password', 'client_id'] as $key) {
            $secret = (string) ($account[$key] ?? '');
            if ($secret !== '') { $message = str_replace([$secret, rawurlencode($secret), htmlspecialchars($secret, ENT_QUOTES)], '[védett adat]', $message); }
        }
        return mb_substr(trim(strip_tags($message)), 0, 600);
    }
}
