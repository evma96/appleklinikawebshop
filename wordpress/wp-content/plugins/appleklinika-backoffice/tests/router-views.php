<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Isolated rendering stubs; use the installed WordPress HTML sanitizer, but do
// not bootstrap WordPress, its database, plugins, credentials, or network.
function apply_filters(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function wp_allowed_protocols(): array { return ['http', 'https']; }
function is_account_page(): bool { return $GLOBALS['view_test_account']; }
function is_user_logged_in(): bool { return $GLOBALS['view_test_user'] > 0; }
function get_current_user_id(): int { return $GLOBALS['view_test_user']; }
function wp_get_environment_type(): string { return 'staging'; }
function esc_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
function esc_attr(mixed $value): string { return esc_html($value); }
function esc_url(string $value): string { return esc_html($value); }
function admin_url(string $path): string { return 'http://localhost/wp-admin/' . $path; }
function wp_nonce_field(string $action): void { echo '<input name="_wpnonce" value="local-test">'; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['view_test_options'][$key] ?? $default; }
function wp_nonce_url(string $url, string $action): string { return esc_html($url . '&_wpnonce=test'); }
function add_query_arg(array|string $args, string $url, ?string $base = null): string {
    if (is_string($args)) { $args = [$args => $url]; $url = (string) $base; }
    $parts = explode('?', $url, 2); parse_str($parts[1] ?? '', $query);
    return $parts[0] . '?' . http_build_query(array_merge($query, $args));
}
function home_url(string $path): string { return 'http://localhost' . $path; }
function plugins_url(string $path, string $plugin): string { return 'http://localhost/plugins/appleklinika-backoffice/' . $path; }
function language_attributes(): void { echo 'lang="hu"'; }
function bloginfo(string $key): void { echo 'UTF-8'; }
function wp_head(): void {}
function wp_footer(): void {}
function nocache_headers(): void {}
function wc_format_datetime(mixed $date): string { return (string) $date; }
function wp_upload_dir(mixed $time = null, bool $create = true): array { return ['basedir' => $GLOBALS['view_test_documents']]; }
function WC_Szamlazz(): object { return new class {
    public function get_szamlazz_agent_key($order): string { return 'offline-view-key'; }
    public function generate_invoice(): never { throw new RuntimeException('Rendering must not generate.'); }
    public function generate_download_link(WC_Order $order, string $type, bool $file): string { return $GLOBALS['view_test_documents'] . '/wc_szamlazz/' . $order->get_meta('_wc_szamlazz_invoice_pdf'); }
}; }
final class GLS_Shipping_For_Woo {}
function absint(mixed $value): int { return abs((int) $value); }
function wp_unslash(mixed $value): mixed { return $value; }
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_]/', '', strtolower($value)); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function current_user_can(string $capability): bool { return $GLOBALS['view_test_capability'] ?? false; }
function status_header(int $status): void {}
function auth_redirect(): never { throw new RuntimeException('auth', 302); }
function wp_die(string $message, string $title, array $args): never { throw new RuntimeException($message, $args['response']); }
function wc_get_order(int $id): mixed { return $GLOBALS['view_test_order'] ?? false; }
function check_admin_referer(string $action): void { if (!($GLOBALS['view_test_nonce'] ?? false)) { throw new RuntimeException('nonce', 403); } }

require_once dirname(__DIR__) . '/src/Infrastructure/MplCarrier.php';
require_once dirname(__DIR__, 4) . '/wp-includes/kses.php';
require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php';
require_once dirname(__DIR__) . '/src/Infrastructure/CashPickup.php';
require_once dirname(__DIR__) . '/src/Infrastructure/OrderDocuments.php';
require_once dirname(__DIR__) . '/src/Infrastructure/GlsAutomation.php';
require_once dirname(__DIR__) . '/src/Application/Port/InvoiceIssuer.php';
require_once dirname(__DIR__) . '/src/Infrastructure/SzamlazzInvoice.php';
require_once dirname(__DIR__) . '/src/Interfaces/BackOfficeRouter.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use Appleklinika\BackOffice\Interfaces\BackOfficeRouter;

final class WC_Order
{
    public string $status = 'processing';
    public string $payment = 'bacs';
    public ?array $shipping = null;
    public array $meta = [
        '_appleklinika_backoffice_delivery_mode' => 'pickup',
        '_appleklinika_backoffice_state' => 'problem',
        '_appleklinika_backoffice_history' => [[
            'from' => 'preparation', 'to' => 'ready_for_pickup',
            'action' => 'prepare_pickup', 'user' => 'INTERNAL EMPLOYEE',
            'at' => '2026-09-10 10:00:00', 'note' => 'INTERNAL SECRET',
        ]],
    ];
    public function get_meta(string $key, bool $single = true): mixed { return $this->meta[$key] ?? ''; }
    public function get_id(): int { return 123; }
    public function get_order_number(): string { return '123'; }
    public function get_date_created(): string { return '2026-10-16 10:00'; }
    public function get_formatted_billing_address(): string { return 'Test Name<br>Test address'; }
    public function get_formatted_shipping_address(): string { return 'Test Shipping<br>Test address'; }
    public function get_billing_phone(): string { return '1234567'; }
    public function get_items(string $type = 'line_item'): array { return []; }
    public function get_user_id(): int { return 7; }
    public function get_status(): string { return $this->status; }
    public function has_status(string $status): bool { return $this->status === $status; }
    public function is_paid(): bool { return in_array($this->status, ['processing', 'completed'], true); }
    public function get_payment_method(): string { return $this->payment; }
    public function get_shipping_methods(): array
    {
        $ids = $this->shipping ?? [($this->meta['_appleklinika_backoffice_delivery_mode'] ?? '') === 'gls' ? 'gls_shipping_method' : 'local_pickup'];
        return array_map(static fn ($id) => new class($id) {
            public function __construct(private string $id) {}
            public function get_method_id(): string { return $this->id; }
            public function get_instance_id(): int { return 1; }
            public function get_name(): string { return 'Actual WooCommerce shipping'; }
        }, $ids);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) { throw new RuntimeException($message); }
};
$router = new BackOfficeRouter(new WooOrderBackOfficeRepository());
$render = static function (string $method, mixed ...$arguments) use ($router): string {
    ob_start();
    try {
        (new ReflectionMethod($router, $method))->invoke($router, ...$arguments);
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

$address = BackOfficeRouter::addressHtml('Kovács &amp; Társa<br/>Budapest<br>Fő utca 1');
$assert(str_contains($address, '<br') && ! str_contains($address, '&lt;br'), 'WooCommerce address line breaks remain HTML, not literal text.');
$assert(str_contains($address, '&amp;') && ! str_contains($address, '&amp;amp;'), 'Already escaped customer text is not escaped twice.');
$unsafe = BackOfficeRouter::addressHtml('Név<br onclick="alert(1)"><img src=x onerror=alert(1)><a href="https://evil.example">Cím</a>');
$assert(! str_contains($unsafe, 'onclick') && ! str_contains($unsafe, '<img') && ! str_contains($unsafe, '<a'), 'Address output permits only attribute-free line breaks.');
$assert(BackOfficeRouter::addressHtml(' ') === '—', 'Missing addresses have an explicit empty state.');

$GLOBALS['view_test_account'] = true;
$GLOBALS['view_test_user'] = 7;
$order = new WC_Order();
$html = $render('renderCustomerProgress', $order);
$assert(str_contains($html, 'Átvételre előkészítve'), 'Problem progress stays at the last safe pickup stage.');
$assert(! str_contains($html, 'INTERNAL') && ! str_contains($html, 'Problém') && ! str_contains($html, 'GLS'), 'Customer output never exposes employee, note, problem, or the wrong delivery branch.');
$GLOBALS['view_test_user'] = 8;
$assert($render('renderCustomerProgress', $order) === '', 'Another customer cannot see this progress.');
$GLOBALS['view_test_user'] = 0;
$assert($render('renderCustomerProgress', $order) === '', 'Anonymous visitors cannot see this progress.');
$GLOBALS['view_test_user'] = 7;
$GLOBALS['view_test_account'] = false;
$assert($render('renderCustomerProgress', $order) === '', 'Public order hooks outside My Account do not render private progress.');

$context = ['queue' => 'new', 's' => 'Kovács', 'search_type' => 'customer', 'queue_page' => 3];
$order->meta[FulfilmentWorkflow::META_KEY] = 'preparation';
$actions = $render('renderActions', $order, 'preparation', 'pickup', $context);
$assert(str_contains($actions, 'value="prepare_pickup"') && ! str_contains($actions, 'create_label'), 'Pickup exposes only its real next action.');
foreach ($context as $key => $value) {
    $assert(str_contains($actions, 'name="' . $key . '" value="' . $value . '"'), 'Action forms retain ' . $key . '.');
}
$order->status = 'completed';
$actions = $render('renderActions', $order, 'new', 'pickup', []);
$assert(str_contains($actions, 'lezárult') && ! str_contains($actions, '<form'), 'A WooCommerce-completed order is not presented as actionable NEW.');
$order->status = 'pending';
$actions = $render('renderActions', $order, 'new', 'pickup', []);
$assert(str_contains($actions, 'fizetés ellenőrzése') && ! str_contains($actions, '<form'), 'Unpaid orders display the actual block without an enabled mutation.');
$order->status = 'processing';
$order->meta['_appleklinika_backoffice_delivery_mode'] = 'gls';
$order->meta[FulfilmentWorkflow::META_KEY] = 'ready_for_shipping';
$actions = $render('renderActions', $order, 'ready_for_shipping', 'gls', []);
$assert(str_contains($actions, 'nem normál kézi munkalépés') && ! str_contains($actions, 'value="create_label"'), 'Label generation is never a normal primary action.');
$order->payment = 'cod';
$payment = new ReflectionMethod($router, 'paymentLabel');
$assert($payment->invoke($router, $order) === 'Utánvét', 'COD processing never claims that cash was collected.');
$order->payment = 'bacs';
$assert($payment->invoke($router, $order) === 'Fizetés rendben', 'Existing non-COD paid wording remains intact.');
$order->status = 'refunded';
$assert($payment->invoke($router, $order) === 'Visszatérítve', 'Refunded orders are not mislabeled as cancelled.');

$invoiceHtml = $render('renderInvoice', new WC_Order());
$assert(str_contains($invoiceHtml, 'Számlázási kapcsolat ellenőrzése') && str_contains($invoiceHtml, 'nem aktív') && !str_contains($invoiceHtml, 'download_invoice'), 'No invoice/inactive provider exposes precise state without a fake download.');
$order->meta['_wc_szamlazz_invoice'] = '<script>INVOICE</script>';
$order->meta['_wc_szamlazz_own'] = '<script>INTERNAL REASON</script>';
$invoiceHtml = $render('renderInvoice', $order);
$assert(!str_contains($invoiceHtml, '<script>') && str_contains($invoiceHtml, '&lt;script&gt;'), 'Provider metadata and internal disabled reasons are escaped on the employee panel.');
$order->shipping = [];
$shippingHtml = $render('renderShipping', $order);
$assert(str_contains($shippingHtml, 'Nincs WooCommerce szállítási tétel') && !str_contains($shippingHtml, 'GLS címke'), 'An order like #529 remains diagnostic even with a stale GLS snapshot.');
$order->shipping = ['local_pickup'];
$shippingHtml = $render('renderShipping', $order);
$assert(str_contains($shippingHtml, 'Személyes átvétel') && !str_contains($shippingHtml, 'GLS címke'), 'Actual pickup data cannot expose GLS despite a stale carrier snapshot.');
$order->shipping = ['gls_shipping_method_parcel_shop'];
$shippingHtml = $render('renderShipping', $order);
$assert(str_contains($shippingHtml, 'gls_shipping_method_parcel_shop') && str_contains($shippingHtml, 'Még nincs elkészült címke') && !str_contains($shippingHtml, 'download_label'), 'A real GLS method exposes its actual method and no-label state without a fake PDF.');
$order->shipping = ['gls_shipping_method', ''];
$assert(str_contains($render('renderShipping', $order), 'hiányos, nem támogatott'), 'A missing method in a mixed shipment is not silently discarded.');

$_GET['order_id'] = 123;
$GLOBALS['view_test_order'] = $order;
$deniesDocument = static function (string $document, string $nonce, int $status) use ($router): bool {
    try { (new ReflectionMethod($router, 'downloadDocument'))->invoke($router, $document, $nonce); }
    catch (RuntimeException $exception) { return $exception->getCode() === $status; }
    return false;
};
$GLOBALS['view_test_user'] = 0;
$assert($deniesDocument('invoice', 'invoice', 302), 'Anonymous document access requires login.');
$GLOBALS['view_test_user'] = 7;
$GLOBALS['view_test_capability'] = false;
$assert($deniesDocument('invoice', 'invoice', 403), 'Customer-only access cannot download employee documents.');
$GLOBALS['view_test_capability'] = true;
$GLOBALS['view_test_nonce'] = false;
$assert($deniesDocument('invoice', 'invoice', 403), 'A missing or invalid order nonce denies document access.');
$GLOBALS['view_test_nonce'] = true;
$order->shipping = ['local_pickup'];
$assert($deniesDocument('gls_label', 'label', 404), 'Forging the GLS PDF endpoint does not bypass pickup classification.');
$assert($deniesDocument('invoice', 'invoice', 404), 'Unavailable PDFs are rejected before any document-access history write.');

// Print uses the very same endpoint gates, including when forged directly.
$_GET['print'] = '1';
$GLOBALS['view_test_user'] = 0;
$assert($deniesDocument('invoice', 'invoice', 302), 'Anonymous print pages require login.');
$GLOBALS['view_test_user'] = 7;
$GLOBALS['view_test_capability'] = false;
$assert($deniesDocument('invoice', 'invoice', 403), 'A customer cannot enter the employee print wrapper.');
$GLOBALS['view_test_capability'] = true;
$GLOBALS['view_test_nonce'] = false;
$assert($deniesDocument('invoice', 'invoice', 403), 'Print requests retain the order-specific nonce check.');
$GLOBALS['view_test_nonce'] = true;
$assert($deniesDocument('gls_label', 'label', 404), 'Forged GLS print requests are denied for pickup.');
$assert($deniesDocument('invoice', 'invoice', 404), 'Missing PDFs cannot render a seemingly usable print wrapper.');
unset($_GET['print']);

$root = sys_get_temp_dir() . '/akbo-document-view-' . bin2hex(random_bytes(8));
$GLOBALS['view_test_documents'] = $root;
define('GLS_LABELS_DIR', $root . '/gls');
try {
    mkdir($root, 0700); mkdir($root . '/wc_szamlazz', 0700); mkdir($root . '/gls', 0700);
    // Isolated resolver fixtures only; never written to WooCommerce orders.
    file_put_contents($root . '/wc_szamlazz/view.pdf', '%PDF-1.4 isolated unit fixture');
    file_put_contents($root . '/gls/view.pdf', '%PDF-1.4 isolated unit fixture');
    $GLOBALS['view_test_options']['active_plugins'] = ['integration-for-szamlazzhu-woocommerce/index.php', 'gls-shipping-for-woocommerce/gls-shipping-for-woocommerce.php'];
    $order->meta['_wc_szamlazz_invoice'] = 'TEST-INVOICE-123';
    $order->meta['_wc_szamlazz_invoice_pdf'] = 'view.pdf';
    unset($order->meta['_wc_szamlazz_own']);
    $order->meta['_gls_print_label'] = 'view.pdf';
    $order->meta['_gls_tracking_codes'] = ['3244739305'];
    $order->shipping = ['gls_shipping_method'];
    $html = $render('renderDocuments', $order);
    $assert(substr_count($html, '>Dokumentumok</h2>') === 1 && substr_count($html, '>Megnyitás</a>') === 3 && substr_count($html, '>Nyomtatás</a>') === 3, 'One Documents card has separate Open and Print links for all three available documents.');
    $assert(str_contains($html, 'TEST-INVOICE-123') && str_contains($html, '3244739305'), 'The card displays actual provider invoice and parcel identifiers.');
    $assert(!str_contains($html, $root) && !str_contains($html, '/uploads/') && !str_contains($html, 'view.pdf'), 'No storage path or public provider file URL leaks into document links.');
    preg_match_all('/href="([^"]+)"/', $html, $links);
    foreach ($links[1] as $index => $href) {
        parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
        $assert(($query[$index < 2 ? 'order' : 'order_id'] ?? '') === '123', 'Every document link refers to the actual order.');
        if ($index >= 2) {
            $assert(($query['_wpnonce'] ?? '') === 'test' && (($query['print'] ?? '') === '1') === ($index % 2 === 1), 'PDF Open and Print are distinct, nonce-protected endpoint URLs.');
        }
    }
    $assert(str_contains($html, 'autoprint=1') && !str_contains($html, 'generate_invoice'), 'Order sheet Print requests the browser dialog, never provider generation.');
    $order->shipping = ['local_pickup'];
    $pickup = $render('renderDocuments', $order);
    $assert(!str_contains($pickup, 'GLS') && !str_contains($pickup, 'download_label'), 'Pickup has no GLS document actions even with stale real-file metadata.');
    $order->shipping = [];
    $unknown = $render('renderDocuments', $order);
    $assert(!str_contains($unknown, 'download_label'), 'Unknown shipping cannot expose GLS documents.');
    $assert(str_contains($unknown, 'TEST-INVOICE-123') && substr_count($unknown, 'download_invoice') === 2, 'Unknown shipping still shows the invoice number and separate Open/Print actions.');
    $order->shipping = ['gls_shipping_method'];
    unset($order->meta['_gls_print_label']);
    $assert(str_contains($render('renderDocuments', $order), 'Még nincs elkészült GLS címke.'), 'GLS without a label clearly reports no existing document.');
    $order->meta['_wc_szamlazz_invoice_pdf'] = 'missing.pdf';
    $missing = $render('renderDocuments', $order);
    $assert(str_contains($missing, 'TEST-INVOICE-123') && str_contains($missing, 'PDF-fájl nem érhető el') && !str_contains($missing, 'download_invoice'), 'A recorded invoice with a missing file shows its number but no false Open/Print actions.');
    $GLOBALS['view_test_options'] = [];
    unset($order->meta['_wc_szamlazz_invoice'], $order->meta['_wc_szamlazz_invoice_pdf']);
    $absent = $render('renderDocuments', $order);
    $assert(str_contains($absent, 'Számlázási kapcsolat ellenőrzése') && !str_contains($absent, 'download_invoice'), 'An order without an invoice clearly reports the provider problem without links.');
    $GLOBALS['view_test_options'] = ['active_plugins'=>['integration-for-szamlazzhu-woocommerce/index.php'], 'home'=>'https://teszt.appleklinika.com', 'appleklinika_szamlazz_test_agent_sha256'=>hash('sha256', 'offline-view-key')];
    $order->shipping = [];
    $_GET = ['queue'=>'all','s'=>'529','search_type'=>'order','queue_page'=>'3'];
    $unknown = $render('renderDocuments', $order);
    $assert(str_contains($unknown, 'Számlázz.hu számla') && str_contains($unknown, 'Számlázás helyreállítása') && !str_contains($unknown, 'download_label'), 'Unknown shipping retains eligible exceptional recovery but no GLS action.');
    $assert(str_contains($unknown, '<details class="akbo-recovery">') && !str_contains($unknown, '<details open') && str_contains($unknown, 'name="confirm_recovery" value="1" required'), 'Recovery is collapsed by default and requires explicit confirmation.');
    $assert(str_contains($unknown, 'name="queue" value="all"') && str_contains($unknown, 'name="queue_page" value="3"'), 'Invoice form carries validated worklist context.');
    $order->meta['_wc_szamlazz_type_receipt'] = 1;
    $receipt = $render('renderDocuments', $order);
    $assert(str_contains($receipt, 'Nyugta mód') && !str_contains($receipt, 'value="generate_invoice"'), 'Receipt mode has a precise state and never silently issues an invoice.');
    unset($order->meta['_wc_szamlazz_type_receipt']);
    $order->meta['_wc_szamlazz_own'] = 'Externally handled';
    $disabled = $render('renderDocuments', $order);
    $assert(str_contains($disabled, 'Számlázás letiltva') && !str_contains($disabled, 'value="generate_invoice"'), 'Explicit disabled invoicing is shown independently of shipping.');
    unset($order->meta['_wc_szamlazz_own']);
    $_GET = [];
    $_GET['url'] = 'https://evil.example/document.pdf';
    $wrapper = $render('renderDocumentPrint', $order, 'invoice');
    $assert(str_contains($wrapper, 'data-akbo-print="pdf"') && str_contains($wrapper, 'download_invoice') && str_contains($wrapper, '_wpnonce=test'), 'The print wrapper retrieves the same protected document, not a public URL.');
    $assert(!str_contains($wrapper, 'evil.example') && !str_contains($wrapper, $root), 'Request-supplied document URLs and storage paths never enter the wrapper.');
    $assert(str_contains($wrapper, 'disabled>Nyomtatás') && str_contains($wrapper, 'PDF megnyitása') && str_contains($wrapper, '<noscript>'), 'The wrapper waits for the PDF and keeps an explicit native-viewer/no-JavaScript fallback.');
    unset($_GET['url']);
    $sheet = $render('renderPackingSheet', $order);
    $assert(str_contains($sheet, 'Test Shipping<br>Test address') && str_contains($sheet, 'onclick="window.print()"') && !str_contains($sheet, 'data-akbo-print="sheet"'), 'Order sheet Open renders the real sheet with a manual browser Print action.');
    $_GET['autoprint'] = '1';
    $assert(str_contains($render('renderPackingSheet', $order), 'data-akbo-print="sheet"'), 'Order sheet Print enables the load-triggered browser print flow.');
    unset($_GET['autoprint']);
} finally {
    unlink($root . '/wc_szamlazz/view.pdf'); unlink($root . '/gls/view.pdf');
    rmdir($root . '/wc_szamlazz'); rmdir($root . '/gls'); rmdir($root);
}

echo "Back Office rendered views passed: {$assertions} assertions.\n";
