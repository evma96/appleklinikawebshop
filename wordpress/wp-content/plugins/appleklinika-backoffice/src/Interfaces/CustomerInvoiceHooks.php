<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\CustomerInvoice;

final class CustomerInvoiceHooks
{
    public function register(): void
    {
        add_action('woocommerce_order_details_after_order_table', [$this, 'render'], 25);
        add_action('template_redirect', [$this, 'download'], 5);
    }

    public function render(\WC_Order $order): void
    {
        if (!is_account_page() || (new CustomerInvoice())->path($order, get_current_user_id()) === null) { return; }
        $url = wp_nonce_url(add_query_arg('ak_customer_invoice', $order->get_id(), home_url('/')), 'ak_customer_invoice_'.$order->get_id());
        echo '<section aria-label="Számla"><h2>Számla</h2><p><a class="button" href="'.esc_url($url).'">Számla letöltése (PDF)</a></p></section>';
    }

    public function download(): void
    {
        if (!isset($_GET['ak_customer_invoice'])) { return; }
        $id = absint(wp_unslash($_GET['ak_customer_invoice']));
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!is_user_logged_in() || !$id || !wp_verify_nonce($nonce, 'ak_customer_invoice_'.$id)) {
            wp_die('A dokumentum nem érhető el.', 'Hozzáférés megtagadva', ['response' => 403]);
        }
        $order = wc_get_order($id);
        $path = $order instanceof \WC_Order ? (new CustomerInvoice())->path($order, get_current_user_id()) : null;
        if ($path === null) { wp_die('A dokumentum nem érhető el.', 'Dokumentum nem található', ['response' => 404]); }
        nocache_headers();
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="szamla-'.$id.'.pdf"');
        header('Content-Length: '.(string)filesize($path));
        readfile($path);
        exit;
    }
}
