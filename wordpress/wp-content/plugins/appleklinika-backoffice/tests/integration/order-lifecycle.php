<?php
declare(strict_types=1);
// This mutates an EMPTY, disposable LOCAL fixture only. Never run on TEST/prod.
if (PHP_SAPI !== 'cli' || getenv('AK_LIFECYCLE_FIXTURE') !== '1') { exit(1); }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$_SERVER['HTTP_HOST'] = 'localhost:18882';
require '/var/www/html/wp-load.php';
if (home_url() !== 'http://localhost:18882' || wp_get_environment_type() !== 'local' || DB_NAME !== 'lifecycle_fixture'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) { throw new RuntimeException('Wrong fixture environment.'); }

use Appleklinika\BackOffice\Application\ChangeFulfilment;
use Appleklinika\BackOffice\Infrastructure\{WooOrderMutex, WooFulfilmentStore, WooOrderBackOfficeRepository, WooOrderLifecycleStore, OrderDocuments};
use Appleklinika\BackOffice\Interfaces\{BackOfficeRouter, FulfilmentAdmin};

$mail = [];
add_filter('pre_wp_mail', static function ($return, $args) use (&$mail) { $mail[] = $args; return true; }, 999, 2);
add_filter('pre_http_request', static fn () => new WP_Error('fixture_network_blocked'), 999);
$assertions = 0;
$assert = static function ($ok, $why) use (&$assertions) { ++$assertions; if (!$ok) { throw new RuntimeException($why); } };
$product = new WC_Product_Simple();
$product->set_name('Lifecycle fixture device'); $product->set_regular_price('1000');
$product->set_manage_stock(true); $product->set_stock_quantity(5); $product->save();
$existingBuyer = get_user_by('email', 'buyer@example.invalid');
$buyer = $existingBuyer ? $existingBuyer->ID : wp_insert_user(['user_login'=>'lifecycle_fixture_' . wp_generate_password(8, false), 'user_pass'=>wp_generate_password(40), 'user_email'=>'buyer@example.invalid', 'role'=>'customer']);
$order = wc_create_order(['customer_id'=>$buyer]);
$order->set_payment_method('barion'); $order->set_payment_method_title('Barion');
$order->set_address(['first_name'=>'Fixture', 'last_name'=>'Customer', 'email'=>'buyer@example.invalid', 'country'=>'HU', 'city'=>'Budapest', 'postcode'=>'1111', 'address_1'=>'Fixture utca 1.'], 'billing');
$order->set_address(['first_name'=>'Fixture', 'last_name'=>'Customer', 'country'=>'HU', 'city'=>'Budapest', 'postcode'=>'1111', 'address_1'=>'Fixture utca 1.'], 'shipping');
$order->add_product($product, 1);
$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_id('gls_shipping_method'); $shipping->set_method_title('GLS házhozszállítás'); $shipping->set_total(100);
$order->add_item($shipping); $order->calculate_totals(); $order->save(); $id = $order->get_id();
$order->payment_complete('LOCAL-FIXTURE-NO-PROVIDER');
$fresh = wc_get_order($id);
$assert($fresh->is_paid() && $fresh->has_status('processing'), 'Woo payment state remains correct.');
$invoiceState = $fresh->get_meta(WooOrderLifecycleStore::INVOICE, true);
$assert(($invoiceState['state'] ?? '') === 'blocked', 'Unlicensed automation is blocked, not bypassed.');
$customerMail = static function () use (&$mail): array { return array_values(array_filter($mail, static fn ($item) => $item['to'] === 'buyer@example.invalid')); };
$assert($customerMail() === [], 'Native processing email is suppressed before invoice readiness.');

// A fixture PDF/reference stands in for an already-created provider document.
// This is deliberately NOT proof of Számlázz.hu generation or a TEST invoice.
$uploads = wp_upload_dir(); $directory = $uploads['basedir'] . '/wc_szamlazz'; wp_mkdir_p($directory);
$filename = '/fixture-' . $id . '.pdf'; file_put_contents($directory . $filename, "%PDF-1.4\n% LOCAL FIXTURE ONLY - NOT AN INVOICE\n%%EOF\n");
$fresh->update_meta_data('_wc_szamlazz_invoice', 'LOCAL-FIXTURE-' . $id);
$fresh->update_meta_data('_wc_szamlazz_invoice_pdf', $filename);
$fresh->save_meta_data();
$assert((new OrderDocuments())->filePath(wc_get_order($id), 'invoice') !== null, 'Provider path resolver accepts the attached local PDF.');
do_action('wc_szamlazz_document_created', ['order_id'=>$id, 'document_type'=>'invoice']);
for ($i=0; $i<3; ++$i) { do_action('woocommerce_payment_complete', $id); do_action('appleklinika_lifecycle_dispatch', $id, 'paid_invoice'); }
$customer = $customerMail();
$assert(count($customer) === 1, 'Exactly one primary email accepted across duplicate hooks.');
$assert(str_contains($customer[0]['subject'], 'Köszönjük, megkaptuk a rendelésed!'), 'Primary subject is correct.');
foreach (['Lifecycle fixture device', 'Barion', 'GLS', 'Fixture utca', 'Rendelés megtekintése'] as $text) {
    $assert(str_contains($customer[0]['message'], $text), 'Primary content includes ' . $text);
}
$assert(count($customer[0]['attachments']) === 1 && is_readable($customer[0]['attachments'][0]), 'Primary attaches the stored PDF.');
$stock = wc_get_product($product->get_id())->get_stock_quantity();
$assert($stock === 4, 'Repeated payment hooks reduce stock once.');

$repository = new WooOrderBackOfficeRepository();
$change = new ChangeFulfilment(new WooFulfilmentStore($repository), new WooOrderMutex());
foreach (['new'=>'start', 'preparation'=>'start_packing', 'packing'=>'packing_completed'] as $state=>$action) { $change->execute($id, $action, 1, $state); }
$assert(count($customerMail()) === 1, 'Intermediate operational states send no mail.');
$fresh = wc_get_order($id); $fresh->update_meta_data('_gls_print_label', 'fixture-label.pdf'); $fresh->update_meta_data('_gls_tracking_codes', ['11111111','22222222']); $fresh->save_meta_data();
do_action('appleklinika_lifecycle_dispatch', $id, 'carrier_handoff');
$assert(count($customerMail()) === 1, 'Label/tracking metadata alone never sends shipping.');
$change->execute($id, 'handed_to_gls', 1, 'ready_for_shipping');
for ($i=0; $i<3; ++$i) { do_action('appleklinika_lifecycle_dispatch', $id, 'carrier_handoff'); }
$customer = $customerMail();
$assert(count($customer) === 2, 'Exactly one handoff email accepted across duplicate workers.');
$assert(str_contains($customer[1]['subject'], 'Úton van a rendelésed!'), 'Shipping subject is correct.');
$assert(str_contains($customer[1]['message'], 'match=11111111') && str_contains($customer[1]['message'], 'match=22222222'), 'All current tracking links appear in shipping mail.');
$assert($customer[1]['attachments'] === [], 'Shipping does not attach a second invoice.');
$change->execute($id, 'delivered', 1, 'handed_to_gls');
$assert(count($customerMail()) === 2, 'Delivery completion remains silent.');
$change->execute($id, 'correct', 1, 'delivered', 'ready_for_shipping', 'Fixture correction');
$change->execute($id, 'handed_to_gls', 1, 'ready_for_shipping');
do_action('appleklinika_lifecycle_dispatch', $id, 'carrier_handoff');
$assert(count($customerMail()) === 2, 'Correcting then repeating handoff cannot duplicate shipping mail.');

$fresh = wc_get_order($id); wp_set_current_user((int)$buyer);
add_filter('woocommerce_is_account_page', '__return_true');
$router = new BackOfficeRouter($repository, $change);
ob_start(); do_action('woocommerce_order_details_after_order_table', $fresh); $account = ob_get_clean();
$assert(str_contains($account, 'Teljesítve') && str_contains($account, '11111111') && str_contains($account, '22222222'), 'Account renders progression and multi-tracking.');
$assert(!str_contains($account, 'Fixture correction'), 'Internal correction reason stays private.');
wp_set_current_user(1); ob_start(); (new FulfilmentAdmin($change, $repository))->render($fresh); $admin = ob_get_clean();
$assert(str_contains($admin, 'expected_state') === false && str_contains($admin, 'form="akbo-fulfilment-'), 'Admin uses separately associated controls, not nested forms.');
$assert(str_contains($admin, 'accepted'), 'Admin exposes persisted send acceptance.');
$fresh->update_status('completed');
$assert(count($customerMail()) === 2, 'Native completed email remains suppressed for managed order.');
$guard = apply_filters('wc_szamlazz_before_generate_invoice_check', null, $id, 'invoice', []);
$assert(!empty($guard['error']), 'Manual generation cannot bypass the serialized managed invoice path.');
$fixtureKey = 'LOCAL-FAKE-KEY-NO-PROVIDER';
update_option('appleklinika_szamlazz_test_agent_sha256', hash('sha256', $fixtureKey));
$xml = new SimpleXMLElement('<szamla><beallitasok><szamlaagentkulcs>LOCAL-FAKE-KEY-NO-PROVIDER</szamlaagentkulcs></beallitasok><vevo><sendEmail>true</sendEmail></vevo></szamla>');
$xml = apply_filters('wc_szamlazz_xml', $xml, $fresh, 'invoice', []);
$assert((string)$xml->vevo->sendEmail === 'false', 'Managed invoice disables redundant provider mail.');
$xml->beallitasok->szamlaagentkulcs = 'UNVERIFIED-ACCOUNT';
try { apply_filters('wc_szamlazz_xml', $xml, $fresh, 'invoice', []); $assert(false, 'Different account must reject.'); }
catch (RuntimeException) { $assert(true, 'Unverified resolved provider account is rejected before network.'); }
delete_option('appleklinika_szamlazz_test_agent_sha256');

$legacy = wc_create_order(); $legacy->set_payment_method('barion'); $legacy->set_date_created(time()-86400); $legacy->save();
$assert(apply_filters('woocommerce_email_enabled_customer_processing_order', true, $legacy) === true, 'Historical orders keep their native emails.');
$legacy->delete(true);
file_put_contents('/fixtures/primary-email.html', $customer[0]['message']);
file_put_contents('/fixtures/shipping-email.html', $customer[1]['message']);
file_put_contents('/fixtures/customer-progress.html', '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/wp-content/plugins/appleklinika-backoffice/assets/customer-progress.css"><style>body{font-family:Arial;padding:20px;margin:auto;max-width:960px}</style>' . $account);
file_put_contents('/fixtures/evidence.json', json_encode(['assertions'=>$assertions,'order_id'=>$id,'mode'=>'isolated LOCAL fixture; no provider calls or delivered email','customer_email_count'=>count($customerMail()),'stock'=>$stock,'invoice_state'=>wc_get_order($id)->get_meta(WooOrderLifecycleStore::INVOICE,true),'email_1'=>wc_get_order($id)->get_meta(WooOrderLifecycleStore::EMAIL.'paid_invoice',true),'email_2'=>wc_get_order($id)->get_meta(WooOrderLifecycleStore::EMAIL.'carrier_handoff',true)], JSON_PRETTY_PRINT));
echo "Real Woo local fixture: $assertions assertions passed. No Barion/Számlázz.hu/GLS/SMTP traffic.\n";
