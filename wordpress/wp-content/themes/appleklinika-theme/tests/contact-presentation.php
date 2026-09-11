<?php

declare(strict_types=1);

// In-memory content and mail stubs only. No external HTTP, delivered mail or DB fixtures.
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
require_once dirname(__DIR__, 4) . '/wp-load.php';
if (! defined('DISABLE_WP_CRON')) { define('DISABLE_WP_CRON', true); }
add_filter('pre_http_request', static fn () => new WP_Error('contact_test_blocked', 'No external HTTP in tests.'), PHP_INT_MAX);
add_filter('pre_wp_mail', static fn () => false, PHP_INT_MAX);

$mode = $argv[1] ?? 'render';
if (in_array($mode, ['mail-success', 'mail-failure', 'invalid-nonce'], true)) {
    $calls = 0;
    add_filter('pre_wp_mail', static function ($return, array $attributes) use ($mode, &$calls): bool {
        ++$calls;
        if ($attributes['to'] !== get_option('admin_email')) { throw new RuntimeException('Mail recipient changed.'); }
        return $mode === 'mail-success';
    }, PHP_INT_MAX, 2);
    $_POST = ['appleklinika_contact_nonce' => $mode === 'invalid-nonce' ? 'invalid' : wp_create_nonce('appleklinika_contact_submit'), 'website' => '', 'contact_name' => 'Local fixture', 'contact_email' => 'fixture@example.test', 'contact_phone' => '', 'contact_message' => 'In-memory contact test.'];
    add_filter('wp_redirect', static function (string $url) use ($mode, &$calls): never {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $expected = ['mail-success' => 'sent', 'mail-failure' => 'delivery-error', 'invalid-nonce' => 'error'][$mode];
        if (($query['ak_contact_status'] ?? '') !== $expected || $calls !== ($mode === 'invalid-nonce' ? 0 : 1)) {
            fwrite(STDERR, "FAIL: contact mail status or stub call count.\n");
            exit(1);
        }
        echo "Contact {$mode} passed; no message delivered.\n";
        exit(0);
    });
    appleklinika_handle_contact_submit();
}

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (! $condition) { throw new RuntimeException($message); }
};
$capture = static function (): string {
    ob_start();
    appleklinika_render_contact_page();
    return (string) ob_get_clean();
};
$content = appleklinika_contact_defaults();
$filter = static function () use (&$content): array { return $content; };
add_filter('pre_option_appleklinika_contact_content', $filter);
try {
    $assert($content['phone'] === '+36 30 970 6700' && $content['email'] === '' && $content['hours'] === '', 'Phone comes from the existing owner-supplied photo; unknown business details stay empty.');
    $empty = appleklinika_sanitize_contact_content(['phone' => '', 'email' => '', 'hours' => '']);
    $assert($empty['phone'] === '' && $empty['hours'] === '', 'An editor can intentionally hide optional contact details.');
    $rejectedMap = appleklinika_sanitize_contact_content(['map_embed' => 'https://untrusted.example.test/embed']);
    $assert($rejectedMap['map_embed'] === $content['map_embed'], 'Untrusted map hosts are rejected.');
    foreach (['https://www.google.com/maps/embed', 'https://www.google.com/maps/embed?pb[]=!1m18', 'https://user@www.google.com/maps/embed?pb=!1m18', 'https://www.google.com/maps/embed/v1/place?key=fixture'] as $invalidMap) {
        $assert(appleklinika_sanitize_contact_content(['map_embed' => $invalidMap])['map_embed'] === $content['map_embed'], 'Malformed or credential-bearing map URLs fall back safely.');
    }
    $copiedMap = appleklinika_sanitize_contact_content(['map_embed' => '<iframe src="' . $content['map_embed'] . '" onload="alert(1)"></iframe>']);
    $assert($copiedMap['map_embed'] === $content['map_embed'], 'A pasted iframe becomes a safe URL only.');
    $previousMap = appleklinika_sanitize_contact_content(['title' => 'Edited title', 'phone' => '', 'map_embed' => 'https://www.openstreetmap.org/export/embed.html?marker=46.2544892,20.1426876']);
    $assert($previousMap['map_embed'] === $content['map_embed'] && $previousMap['title'] === 'Edited title' && $previousMap['phone'] === '', 'Old map settings fall back to Google without losing other edited contact fields.');
    $assert(appleklinika_sanitize_contact_content(null) === $content, 'Malformed content receives safe defaults.');
    $safe = appleklinika_sanitize_contact_content(['title' => '<b>Hello</b>', 'intro' => "Line 1\nLine 2", 'store_name' => [], 'email' => 'not-an-email']);
    $assert($safe['title'] === 'Hello' && $safe['intro'] === "Line 1\nLine 2", 'Plain text and intentional line breaks survive sanitization.');
    $assert($safe['store_name'] === 'Apple Klinika' && $safe['email'] === '', 'Invalid data does not create broken store names or mail links.');
    foreach (['91,20', '46,-181', '46,20,0', 'not coordinates'] as $coordinates) {
        $assert(appleklinika_sanitize_contact_content(['navigation_coordinates' => $coordinates])['navigation_coordinates'] === $content['navigation_coordinates'], 'Malformed navigation points fall back to the verified store pin.');
    }
    $html = $capture();
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
    $xpath = new DOMXPath($document);
    $assert($xpath->query('//main')->length === 1 && $xpath->query('//h1')->length === 1, 'Exactly one main region and page heading.');
    $frames = $xpath->query('//iframe');
    $assert($frames->length === 1, 'The initial server-rendered page contains exactly one map iframe without a visitor click or JavaScript insertion.');
    $frame = $frames->item(0);
    $assert($frame->getAttribute('src') === $content['map_embed'] && $frame->getAttribute('loading') === 'eager', 'The existing Google embed URL loads immediately, including below the mobile fold.');
    $assert($frame->getAttribute('title') === 'Apple Klinika – Google Maps' && $frame->getAttribute('referrerpolicy') === 'strict-origin-when-cross-origin' && $frame->hasAttribute('allowfullscreen'), 'Map retains its accessible title, referrer policy and fullscreen support.');
    $assert($xpath->query('//iframe/ancestor-or-self::*[@hidden]')->length === 0 && $xpath->query('//iframe/parent::*[@role="region"]')->length === 1, 'Responsive map region is visible from the initial render.');
    $assert($xpath->query('//*[@data-contact-map or @data-map-src or @data-map-prompt or @data-map-load or @data-map-close or @data-map-status]')->length === 0 && ! str_contains($html, 'Interaktív térkép betöltése') && ! str_contains($html, 'Térkép bezárása') && ! str_contains($html, 'csak kattintás után'), 'Obsolete manual-load controls, placeholder and consent copy are absent.');
    $assert($xpath->query('//details[@data-directions]/summary')->length === 1 && $xpath->query('//details[@data-directions]//a')->length === 3, 'Native mobile chooser has exactly three navigation providers.');
    $assert($xpath->query('//form[@method="post"]')->length === 1, 'Existing native contact form is preserved.');
    $assert($xpath->query('//input[@required]')->length === 2 && $xpath->query('//textarea[@required]')->length === 1, 'Required name, email and message fields remain present.');
    $assert($xpath->query('//input[@name="contact_phone" and not(@required)]')->length === 1, 'Phone stays optional.');
    $assert($xpath->query('//input[@name="appleklinika_contact_nonce"]')->length === 1, 'Form retains its CSRF nonce.');
    $assert(! str_contains($html, 'minta üzletcím') && ! str_contains($html, '+36 30 000 0000') && ! str_contains($html, 'info@appleklinika.hu'), 'Old sample public data is absent.');
    $assert(! str_contains($html, 'Ingyenes szállítás'), 'Contact does not repeat an unverified shipping promise.');
    $map = appleklinika_contact_map_urls($content);
    $assert(str_contains(rawurldecode($map['embed']), '0x474489111d25d185:0xf8dc8aa6ed1595e7') && str_contains(rawurldecode($map['embed']), 'Apple Klinika Szeged') && str_contains(rawurldecode($map['directions']), 'Jósika utca 2-4.'), 'Map and directions target the verified Google store place and supplied address.');
    $assert(str_contains(rawurldecode($map['directions']), 'Apple Klinika, 6720 Szeged, Jósika utca 2-4.'), 'Google receives the full requested destination including postcode.');
    $assert($map['apple_directions'] === 'https://maps.apple.com/directions?destination=46.2544892%2C20.1426876' && $map['waze_directions'] === 'https://www.waze.com/ul?ll=46.2544892%2C20.1426876&navigate=yes', 'Apple Maps and Waze receive the exact store pin through HTTPS universal links with web fallback.');
    $editedNavigation = appleklinika_contact_map_urls(appleklinika_sanitize_contact_content(['navigation_coordinates' => '47.5,19.0']));
    $assert(str_contains($editedNavigation['apple_directions'], '47.5%2C19') && str_contains($editedNavigation['waze_directions'], '47.5%2C19'), 'Both coordinate-based providers follow edited navigation settings.');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/contact.js');
    $assert(! str_contains($script, 'iframe') && ! str_contains($script, 'data-map-') && ! str_contains($script, 'setTimeout') && ! str_contains($script, 'unpkg.com') && ! str_contains($script, 'tile.openstreetmap.org') && ! str_contains($script, 'window.L'), 'Contact JavaScript no longer creates, hides, removes or times out the map and adds no map-library dependency.');
    $assert(! str_contains($script, 'localStorage') && ! str_contains($script, 'sessionStorage') && ! str_contains($script, 'document.cookie'), 'Navigation enhancement does not introduce a separate consent store.');
    $assert(! str_contains($html, 'Leaflet') && ! str_contains($html, 'OpenStreetMap'), 'Public map content has no obsolete provider copy.');
    $content['phone'] = '+36 30 123 4567';
    $content['email'] = 'fixture@example.test';
    $content['hours'] = 'Fixture hours';
    $content['map_embed'] = str_replace('!1shu!2shu', '!1sen!2sen', $content['map_embed']);
    $custom = $capture();
    $assert(str_contains($custom, 'tel:+36301234567') && str_contains($custom, 'mailto:fixture@example.test') && str_contains($custom, 'Fixture hours'), 'Configured contact fields render actionable links without database writes.');
    $assert(str_contains($custom, '<iframe src="' . esc_url($content['map_embed']) . '"'), 'The immediately rendered iframe follows edited map settings without database writes or external requests.');
    foreach (['sent', 'error', 'delivery-error'] as $status) {
        $_GET['ak_contact_status'] = $status;
        $assert(str_contains($capture(), $status === 'sent' ? 'role="status"' : 'role="alert"'), 'Contact result has accessible feedback: ' . $status);
    }
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/page-kapcsolat.html');
    $assert(str_contains($template, 'wp:appleklinika/contact') && ! str_contains($template, 'wp:post-content'), 'Contact has a dynamic template without old sample copy or shortcode paragraph wrapping.');
    require_once ABSPATH . 'wp-admin/includes/template.php';
    $adminCapability = static function (array $caps): array { $caps['manage_options'] = true; return $caps; };
    add_filter('user_has_cap', $adminCapability);
    ob_start();
    appleklinika_render_contact_settings();
    $settings = (string) ob_get_clean();
    remove_filter('user_has_cap', $adminCapability);
    $assert(str_contains($settings, 'appleklinika_contact_content[phone]') && str_contains($settings, 'appleklinika_contact_content[map_embed]'), 'Administrator settings expose editable phone and map source.');
    $assert(str_contains($settings, 'name="_wpnonce"') && str_contains($settings, 'action="options.php"'), 'Editing uses the native WordPress settings save and nonce.');
} finally {
    unset($_GET['ak_contact_status']);
    remove_filter('pre_option_appleklinika_contact_content', $filter);
}
echo "Contact presentation passed: {$checks} assertions. No fixtures persisted.\n";
