<?php

declare(strict_types=1);

// Memory-only orders. No database writes, send(), payment hooks or provider calls.
if (PHP_SAPI !== 'cli' || !in_array('--local-email-preview', $argv ?? [], true)) { http_response_code(404); exit(1); }
if (getenv('AK_LOCAL_VERIFICATION') !== '1') { throw new RuntimeException('LOCAL network/mail guard required.'); }
$_SERVER['HTTP_HOST'] = getenv('AK_LIFECYCLE_FIXTURE') === '1' ? 'localhost:18882' : 'localhost:8082';
require '/var/www/html/wp-load.php';
if (wp_get_environment_type() !== 'local' || wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || ini_get('sendmail_path') !== '/bin/false') {
    throw new RuntimeException('LOCAL guarded runtime required.');
}
WC()->mailer(); // Woo loads its email base lazily.
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
$networkAttempts = $mailAttempts = 0;
add_filter('pre_http_request', static function () use (&$networkAttempts) { ++$networkAttempts; return new WP_Error('preview_network_blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function () use (&$mailAttempts) { ++$mailAttempts; return false; }, PHP_INT_MAX);

use Appleklinika\BackOffice\Interfaces\{OrderReceivedEmail, PaidInvoiceEmail, CarrierHandoffEmail};
use Appleklinika\BackOffice\Infrastructure\LifecycleEmailPresentation;
use Appleklinika\BackOffice\Domain\CustomerNotification;

final class PreviewOrder extends WC_Order
{
    public function get_order_number() { return 'AK-2026-1001'; }
    public function get_view_order_url() { return wc_get_endpoint_url('view-order', '900001', wc_get_page_permalink('myaccount')); }
}
function previewOrder(string $shippingId = 'gls_shipping_method', array $tracking = []): PreviewOrder
{
    $order = new PreviewOrder();
    $order->set_customer_id(900001);
    $order->set_currency('HUF'); $order->set_date_created('2026-10-01 10:30:00');
    $order->set_payment_method('barion'); $order->set_payment_method_title('Barion bankkártyás fizetés');
    $address = ['first_name'=>'Ágnes','last_name'=>'Minta','email'=>'preview@example.invalid','country'=>'HU','city'=>'Budapest','postcode'=>'1111','address_1'=>'Példa utca 12.','address_2'=>'III. emelet 8.'];
    $order->set_address($address, 'billing'); $order->set_address($address, 'shipping');
    foreach ([['Apple iPhone 15 Pro Max 256 GB – természetes titán, ellenőrzött állapot és 12 hónap garancia',1,249990],['Apple USB-C töltőkábel – 2 méter, szövött kivitel',2,15980],['Átlátszó MagSafe tok – ütésálló, megerősített sarkokkal',1,9990]] as [$name,$qty,$total]) {
        $item = new WC_Order_Item_Product(); $item->set_name($name); $item->set_quantity($qty);
        $item->set_subtotal($total); $item->set_total($total);
        if ($qty === 2) { $item->set_meta_data([['id'=>900001,'key'=>'Hossz','value'=>'2 méter'], ['id'=>900002,'key'=>'Szín','value'=>'Fehér']]); }
        $order->add_item($item);
    }
    $shipping = new WC_Order_Item_Shipping(); $shipping->set_method_id($shippingId);
    $shipping->set_method_title($shippingId === 'local_pickup' ? 'Személyes átvétel az Apple Klinikán' : ($shippingId === 'gls_shipping_method_parcel_locker' ? 'GLS csomagautomata' : 'GLS házhozszállítás'));
    $shipping->set_total($shippingId === 'local_pickup' ? '0' : '1990'); $order->add_item($shipping);
    $order->set_shipping_total($shippingId === 'local_pickup' ? '0' : '1990');
    $order->set_total($shippingId === 'local_pickup' ? '275960' : '277950');
    $order->update_meta_data('_wc_szamlazz_invoice', '2026-1001');
    $order->update_meta_data('_gls_tracking_codes', $tracking);
    return $order;
}
$assertions = 0;
$assert = static function ($ok, string $message) use (&$assertions) { ++$assertions; if (!$ok) { throw new RuntimeException($message); } };
$directory = '/tmp/appleklinika-email-preview';
if (!is_dir($directory)) { mkdir($directory, 0700, true); }
$scenarios = [
    'received-delivery' => [new OrderReceivedEmail(), previewOrder()],
    'received-pickup' => [new OrderReceivedEmail(), previewOrder('local_pickup')],
    'paid-delivery' => [new PaidInvoiceEmail(), previewOrder()],
    'paid-pickup' => [new PaidInvoiceEmail(), previewOrder('local_pickup')],
    'paid-locker' => [new PaidInvoiceEmail(), previewOrder('gls_shipping_method_parcel_locker')],
    'shipped-single' => [new CarrierHandoffEmail(), previewOrder('gls_shipping_method', ['12345678901'])],
    'shipped-multiple' => [new CarrierHandoffEmail(), previewOrder('gls_shipping_method', ['12345678901','12345678902','12345678901'])],
];
foreach ($scenarios as $name => [$email, $order]) {
    $email->set_object($order);
    $html = $email->style_inline($email->get_content());
    $plain = $email->get_content_plain();
    $assert($order->get_id() === 0, 'Preview order must not be persisted.');
    $assert(str_contains($html,'Kedves Ágnes!') && str_contains($plain,'Kedves Ágnes!'), 'Hungarian personalized greeting in both formats.');
    $assert(str_contains($html,'AK-2026-1001') && str_contains($plain,'AK-2026-1001'), 'Dynamic order reference in both formats.');
    $assert(str_contains(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'),$order->get_view_order_url()) && str_contains($plain,$order->get_view_order_url()), 'Account URL uses Woo routing.');
    $assert(!str_contains($html,'<script') && !str_contains($html,'<form'), 'No interactive or executable email markup.');
    $assert(strlen($html) < 90000, 'HTML below common clipping threshold.');
    $assert($email->get_email_type() === 'multipart', 'Default includes useful plain alternative.');
    $phpmailer = new PHPMailer\PHPMailer\PHPMailer();
    $email->handle_multipart($phpmailer);
    $assert(str_contains($phpmailer->AltBody, 'AK-2026-1001'), 'Woo multipart hook produces a plain body without sending.');
    if (str_starts_with($name,'paid-')) {
        foreach (['2026-1001','PDF','Barion','Hossz: 2 méter','Szín: Fehér'] as $expected) { $assert(str_contains($plain,$expected), 'Paid details include ' . $expected); }
        $compactPlain = preg_replace('/[\s\x{00a0}\x{202f}]+/u', '', $plain);
        foreach (['249990Ft','15980Ft','9990Ft','Részösszeg:275960Ft'] as $expected) { $assert(str_contains($compactPlain,$expected), 'Exact item/subtotal preserved: ' . $expected); }
        $assert(str_contains($html,'Apple iPhone 15 Pro Max') && str_contains($html,'Apple USB-C') && str_contains($html,'Átlátszó MagSafe'), 'All long/multiple products included.');
        $assert(str_contains($plain,'2 db'), 'Quantity remains correct.');
        $assert(str_contains($html,'Számlázási adatok') && str_contains($plain,'Példa utca'), 'Order billing snapshot retained.');
        $assert(str_contains($compactPlain, 'Végösszeg:' . ($name === 'paid-pickup' ? '275960Ft' : '277950Ft')), 'Exact total includes the correct shipping cost.');
    } elseif (str_starts_with($name,'received-')) {
        $assert(str_contains($html,'Rendelésed beérkezett') && str_contains($plain,'nem jelenti annak elfogadását'), 'Acknowledgement subject and legal distinction preserved.');
        $assert(!str_contains($plain,'Fizetve') && !str_contains($html,'Számlád a mellékletben'), 'Acknowledgement has no payment or PDF claim.');
        $assert(str_contains($plain,'10:30') && str_contains($plain,'2 db') && str_contains($html,'Apple iPhone 15 Pro Max'), 'Acknowledgement includes time, quantities and long product details.');
        $assert(str_contains($plain,'Választott fizetési mód') && str_contains($plain,'Barion'), 'Acknowledgement identifies the chosen payment only.');
    } else {
        $assert(str_contains($plain,'átadtuk a GLS futárszolgálatnak'), 'Announces actual handoff.');
        $assert(!str_contains($html,'Számlád a mellékletben'), 'Shipping does not claim a repeated invoice attachment.');
        $assert(substr_count($html,'match=') === ($name === 'shipped-single' ? 1 : 2), 'Tracking links are current and deduplicated.');
    }
    file_put_contents($directory.'/'.$name.'.html',$html);
    file_put_contents($directory.'/'.$name.'.txt',$plain);
}
$pickup = file_get_contents($directory.'/paid-pickup.html');
$assert(str_contains($pickup,'amikor átvehető') && !str_contains($pickup,'GLS-nek történő'), 'Pickup copy makes no courier/ready-now claim.');
$locker = file_get_contents($directory.'/paid-locker.html');
$assert(str_contains($locker,'kiválasztott átvételi pont') && !str_contains($locker,'<h2 style="margin:28px 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.4;color:#202124;">Szállítási cím'), 'Locker does not present home address as parcel destination.');
$order = previewOrder(); $order->set_billing_first_name('');
$email = new PaidInvoiceEmail(); $email->set_object($order);
$assert(str_contains($email->get_content_html(),'Kedves Vásárlónk!'), 'Missing name has a clean fallback.');
$order->set_billing_first_name('<script>alert(1)</script>');
$html = $email->get_content_html();
$assert(!str_contains($html,'<script>'), 'Customer input cannot introduce HTML.');
$item = new WC_Order_Item_Product(); $item->set_name('<img src=x onerror=alert(1)>'); $item->set_quantity(1); $item->set_total(0); $order->add_item($item);
$assert(!str_contains($email->get_content_html(),'<img src=x'), 'Product names escaped.');
$assert(!str_contains($email->get_content_plain(),'<script>'), 'Plain text strips markup.');
$guest = previewOrder(); $guest->set_customer_id(0);
$receipt = new OrderReceivedEmail(); $receipt->set_object($guest);
$assert(!str_contains($receipt->get_content_html(), 'Rendelés megtekintése'), 'Guest acknowledgement does not link to someone else’s My Account order.');
$customCopy = static fn () => ['expected_fulfilment'=>'Várható teljesítés: egyedi egyeztetés alapján'];
add_filter('pre_option_woocommerce_appleklinika_paid_invoice_settings', $customCopy);
$customEmail = new PaidInvoiceEmail(); $customEmail->set_object(previewOrder());
$assert(str_contains($customEmail->get_content_plain(), 'egyedi egyeztetés alapján'), 'Expected fulfilment copy is configurable through the existing Woo email settings.');
remove_filter('pre_option_woocommerce_appleklinika_paid_invoice_settings', $customCopy);
$savedFormat = static fn () => ['email_type' => 'plain'];
add_filter('pre_option_woocommerce_appleklinika_paid_invoice_settings', $savedFormat);
$assert((new PaidInvoiceEmail())->get_email_type() === 'plain', 'An explicitly saved Woo email format remains honored.');
remove_filter('pre_option_woocommerce_appleklinika_paid_invoice_settings', $savedFormat);
// Model the actual separate Back Office origin without saving settings.
$staffUrls = \Appleklinika\BackOffice\Infrastructure\DedicatedHostUrls::forRequest(
    'https://backoffice.staging.example', 'backoffice.staging.example', [get_option('home'), get_option('siteurl')]
);
$canonicalOrder = previewOrder();
$presentation = new LifecycleEmailPresentation();
$canonicalView = $presentation->forOrder($canonicalOrder, CustomerNotification::PAID);
foreach (['home_url', 'site_url', 'network_site_url'] as $hook) { add_filter($hook, [$staffUrls, 'rewrite']); }
$assert(str_contains($canonicalOrder->get_view_order_url(), 'backoffice.staging.example'), 'Fixture reproduces native staff-origin order links.');
$staffView = $presentation->forOrder($canonicalOrder, CustomerNotification::PAID);
$assert($staffView === $canonicalView, 'Every customer email field, account URL and logo remains canonical in staff context.');
$assert(\Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration::isTestEnvironment(), 'Staff routing retains LOCAL lifecycle hooks.');
foreach (['home_url', 'site_url', 'network_site_url'] as $hook) { remove_filter($hook, [$staffUrls, 'rewrite']); }
$assert($mailAttempts === 0 && $networkAttempts === 0, 'Rendering did not attempt mail or network traffic.');
$links = '';
foreach (array_keys($scenarios) as $name) {
    $links .= '<li><a href="' . esc_attr($name) . '.html">' . esc_html($name) . '</a> · <a href="' . esc_attr($name) . '.txt">plain text</a></li>';
}
file_put_contents($directory.'/index.html', '<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Apple Klinika email previews</title><body style="font:17px/1.7 Arial;background:#f3f4f6;color:#202124;margin:32px"><main style="max-width:800px;margin:auto"><h1>Apple Klinika · levélelőnézetek</h1><p>LOCAL előnézet, kitalált rendelési adatokkal. Nem történt mentés, levélküldés vagy szolgáltatói kérés. A mintarendelések fiókhivatkozásai nem nyitnak létező rendelést.</p><ul>'.$links.'</ul><p>A levél megjelenése asztali és keskeny böngészőablakban is ellenőrizhető. Gmail / Outlook / Apple Mail klienspróba és valódi kézbesítés külön szükséges.</p></main></body></html>');
file_put_contents($directory.'/results.json',json_encode(['assertions'=>$assertions,'scenarios'=>array_keys($scenarios),'persisted_orders'=>0,'mail_attempts'=>$mailAttempts,'network_attempts'=>$networkAttempts,'mode'=>'LOCAL memory-only Woo render; not delivery or provider proof'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Email presentation: $assertions assertions passed; seven memory-only scenarios; zero orders saved, mails or provider requests.\n";
