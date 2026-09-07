<?php

declare(strict_types=1);

require_once getenv('AK_WORDPRESS_LOAD') ?: '/var/www/html/wp-load.php';
require_once __DIR__ . '/provision-legal-demo.php';
$count = 0;
$check = static function (bool $condition, string $message) use (&$count): void {
    ++$count;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$documents = appleklinika_legal_public_documents();
$links = [];
foreach (['terms', 'privacy', 'marketing'] as $key) {
    $document = appleklinika_legal_document($key);
    $links[$document['title']] = $document['url'];
}
$check(count($documents) === 8, 'All documents publicly available.');
foreach ($documents as $document) {
    $page = get_post($document['page_id']);
    $check($page->post_status === 'publish', 'Published page.');
    $check(get_post_meta($page->ID, '_ak_legal_demo_key', true) === $document['key'], 'Exact demo ownership.');
    $check(hash_equals((string) get_post_meta($page->ID, '_ak_legal_demo_content_hash', true), hash('sha256', $page->post_content)), 'No unexpected content changes.');
    $check(str_contains($page->post_content, 'TESZT / MINTASZÖVEG – NEM VÉGLEGES JOGI DOKUMENTUM'), 'Dummy warning is visible.');
    $expected = wp_kses_post(ak_legal_demo_content(ak_legal_demo_documents()[$document['key']]['topics'], $links));
    $check($page->post_content === $expected, 'Generated content equals persisted sanitized content; no needless rewrite.');
}
$check(str_contains(wp_get_custom_css(), ak_legal_demo_css()), 'Scoped demo consent presentation configured.');
$check((int) get_option('woocommerce_terms_page_id') === appleklinika_legal_document('terms')['page_id'], 'Native Woo Terms mapping.');
$check((int) get_option('wp_page_for_privacy_policy') === appleklinika_legal_document('privacy')['page_id'], 'Native WP Privacy mapping.');
$check(str_contains(get_post(wc_get_page_id('checkout'))->post_content, '"checkbox":true'), 'Native Woo Terms checkbox configured.');
$check(get_option('woocommerce_enable_myaccount_registration') === 'yes', 'Registration visible.');
// Exercise the existing order-metadata adapter with an UNSAVED in-memory order.
// Never create an order, customer, payment or external subscription.
$order = new WC_Order();
$request = new WP_REST_Request('POST');
$request->set_param('additional_fields', ['appleklinika/marketing_consent' => true]);
appleklinika_persist_company_checkout_fields($order, $request);
$check($order->get_meta('appleklinika_marketing_consent') === '1', 'Accepted marketing has intended order metadata.');
$request->set_param('additional_fields', ['appleklinika/marketing_consent' => false]);
appleklinika_persist_company_checkout_fields($order, $request);
$check($order->get_meta('appleklinika_marketing_consent') === '', 'Refused marketing does not leave positive metadata.');
$check($order->get_id() === 0, 'No order was persisted.');
ob_start();
appleklinika_render_registration_marketing_consent();
$registration = (string) ob_get_clean();
$check(str_contains($registration, 'type="checkbox"') && ! str_contains($registration, 'required') && ! str_contains($registration, 'checked'), 'Registration consent is optional and unchecked.');
$check(str_contains($registration, esc_url(appleklinika_legal_document('marketing')['url'])), 'Registration marketing link uses central resolver.');
echo "Legal demo configured-state tests passed: {$count} assertions. No order saved.\n";
