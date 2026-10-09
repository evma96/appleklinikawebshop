<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Application\ChangeFulfilment;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use Appleklinika\BackOffice\Infrastructure\WooOrderLifecycleStore;

/** TEST-only UI; all mutations also pass the guarded shared application path. */
final class FulfilmentAdmin
{
    public function __construct(private readonly ChangeFulfilment $change, private readonly WooOrderBackOfficeRepository $orders) {}

    public function register(): void
    {
        if (! LifecycleConfiguration::isTestEnvironment()) { return; }
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render']);
        add_action('admin_post_appleklinika_fulfilment', [$this, 'handle']);
        add_action('admin_notices', [$this, 'notice']);
    }

    public function render(\WC_Order $order): void
    {
        if (! LifecycleConfiguration::isTestEnvironment() || ! current_user_can('manage_woocommerce') || ! current_user_can('edit_shop_order', $order->get_id())) {
            return;
        }
        $state = $this->orders->state($order);
        echo '<div class="order_data_column" style="width:100%;clear:both"><h3>Apple Klinika teljesítés – TEST</h3><p>' . esc_html(FulfilmentWorkflow::labels()[$state]) . '</p>';
        $invoice = (array) $order->get_meta(WooOrderLifecycleStore::INVOICE, true);
        if (! empty($invoice['state'])) {
            echo '<p>Számla-folyamat: <strong>' . esc_html((string) $invoice['state']) . '</strong>. Hiba vagy bizonytalan eredmény esetén előbb Számlázz.hu-egyeztetés szükséges; automatikus újraszámlázás nincs.</p>';
        }
        foreach (['order_received' => 'Automatikus átvételi értesítés', 'paid_invoice' => 'Rendelés és számla', 'carrier_handoff' => 'GLS-átadás'] as $event => $label) {
            $record = (array) $order->get_meta(WooOrderLifecycleStore::EMAIL . $event, true);
            echo '<p>' . esc_html($label) . ': ' . esc_html((string) ($record['state'] ?? 'még nem küldve')) . '</p>';
        }
        echo '<p>Az „accepted” csak a levelező átadását igazolja, a postaládába érkezést nem.</p>';
        if ($this->orders->fulfilmentBlockReason($order) === null) {
            // Avoid nested forms inside the Woo order editor.
            $form = 'akbo-fulfilment-' . $order->get_id();
            echo '<p><select form="' . esc_attr($form) . '" name="operation">';
            $next = FulfilmentWorkflow::primaryAction($state, $this->orders->deliveryMode($order), $this->orders->hasGlsLabel($order));
            if ($next !== null) {
                echo '<option value="' . esc_attr($next) . '">' . esc_html(FulfilmentWorkflow::actions()[$next]) . '</option>';
            }
            echo '<option value="correct">Hibás állapot javítása (indoklás szükséges)</option></select></p>';
            echo '<p>Javítás célállapota: <select form="' . esc_attr($form) . '" name="target">';
            $targets = FulfilmentWorkflow::customerProgressLabels($this->orders->deliveryMode($order));
            $targets[FulfilmentWorkflow::PROBLEM] = 'Probléma';
            foreach ($targets as $target => $label) {
                if (in_array($target, ['handed_to_gls', 'delivered', 'picked_up'], true)) { continue; }
                echo '<option value="' . esc_attr($target) . '">' . esc_html($label) . '</option>';
            }
            echo '</select></p><p><label>Javítás indoka <input form="' . esc_attr($form) . '" name="reason" maxlength="500"></label></p>';
            echo '<p><button type="submit" class="button" form="' . esc_attr($form) . '">Művelet rögzítése</button></p><p>GLS-átadást csak a tényleges átadás után rögzíts. A javítás nem von vissza már elküldött levelet vagy kiállított dokumentumot.</p>';
            add_action('admin_footer', static function () use ($form, $order, $state): void {
                echo '<form id="' . esc_attr($form) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                foreach (['action' => 'appleklinika_fulfilment', 'order_id' => $order->get_id(), 'expected_state' => $state] as $name => $value) {
                    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
                }
                wp_nonce_field('appleklinika_fulfilment_' . $order->get_id());
                echo '</form>';
            });
        }
        echo '</div>';
    }

    public function handle(): void
    {
        $id = absint($_POST['order_id'] ?? 0);
        if (! LifecycleConfiguration::isTestEnvironment() || ! current_user_can('manage_woocommerce') || ! current_user_can('edit_shop_order', $id)) {
            wp_die('Ez a TEST művelet nem engedélyezett.', '', ['response' => 403]);
        }
        check_admin_referer('appleklinika_fulfilment_' . $id);
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order) {
            wp_die('A rendelés nem található.', '', ['response' => 404]);
        }
        try {
            $this->change->execute($id, sanitize_key(wp_unslash($_POST['operation'] ?? '')), get_current_user_id(),
                sanitize_key(wp_unslash($_POST['expected_state'] ?? '')), sanitize_key(wp_unslash($_POST['target'] ?? '')),
                sanitize_textarea_field(wp_unslash($_POST['reason'] ?? '')));
            $message = 'A teljesítési műveletet rögzítettük a rendelés történetében.';
        } catch (\InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (\Throwable) {
            $message = 'A művelet nem hajtható végre biztonságosan. Ellenőrizd a rendelés naplóját.';
        }
        set_transient('akbo_admin_notice_' . get_current_user_id(), $message, 120);
        wp_safe_redirect($order->get_edit_order_url());
        exit;
    }

    public function notice(): void
    {
        $key = 'akbo_admin_notice_' . get_current_user_id();
        $message = get_transient($key);
        if (is_string($message) && $message !== '') {
            delete_transient($key);
            echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
