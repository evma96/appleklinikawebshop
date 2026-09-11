<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Infrastructure/DedicatedHostUrls.php';

use Appleklinika\BackOffice\Infrastructure\DedicatedHostUrls;

function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['host_test_options'][$key] ?? $default; }
function add_filter(string $hook, callable $callback): void { $GLOBALS['host_test_filters'][$hook] = $callback; }

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) { throw new RuntimeException($message); }
};
$origin = 'https://backoffice.staging.example';
$store = 'https://store.staging.example';
$sources = [$store, $store . '/wordpress'];
$urls = DedicatedHostUrls::forRequest($origin, 'backoffice.staging.example', $sources);
$assert($urls !== null, 'Explicitly configured host is accepted.');
foreach (['/', '/backoffice/?queue=packing&s=%231376&search_type=auto&queue_page=3', '/wp-login.php?redirect_to=' . rawurlencode($origin . '/?appleklinika_backoffice=1'), '/wp-login.php', '/wp-admin/admin-post.php?action=appleklinika_backoffice_action', '/wp-login.php?action=logout&_wpnonce=test', '/wordpress/wp-login.php', '/wp-includes/css/dashicons.min.css?ver=1#test'] as $path) {
    $assert($urls->rewrite($store . $path) === $origin . $path, 'Native URL retains its path and encoded context: ' . $path);
}
foreach (['https://external.example/document.pdf', 'https://store.staging.example.evil.example/', 'https://store.staging.example@evil.example/', 'https://evil@store.staging.example/', '//store.staging.example/', '/backoffice/', 'javascript:alert(1)', $store . ':444/'] as $external) {
    $assert($urls->rewrite($external) === $external, 'Unrelated/untrusted URLs are never rewritten: ' . $external);
}
foreach (['store.staging.example', 'backoffice.staging.example.evil.example', 'evil@backoffice.staging.example', 'backoffice.staging.example:444', "backoffice.staging.example\n", 'backoffice.staging.example/', '', ['backoffice.staging.example']] as $host) {
    $assert(DedicatedHostUrls::forRequest($origin, $host, $sources) === null, 'Only exact host authority activates the adapter.');
}
foreach (['', false, [], 'http://backoffice.staging.example', '//backoffice.staging.example', $origin . '/backoffice/', $origin . '?return_url=https://evil.example', $origin . '#fragment', 'https://user@backoffice.staging.example', 'https://user:pass@backoffice.staging.example', "https://backoffice.staging.example\n", 'https://backoffice.staging.example\\evil', 'https://backoffice.staging.example:0'] as $invalid) {
    $assert(DedicatedHostUrls::forRequest($invalid, 'backoffice.staging.example', $sources) === null, 'Invalid or insecure origin configuration fails closed.');
}
$assert(DedicatedHostUrls::forRequest(strtoupper($origin) . '/', 'BACKOFFICE.STAGING.EXAMPLE', $sources)?->rewrite($store . '/') === $origin . '/', 'Host casing and optional root slash are normalized.');
$local = DedicatedHostUrls::forRequest('http://127.0.0.1:18080', '127.0.0.1:18080', ['http://localhost:18080']);
$assert($local?->rewrite('http://localhost:18080/wp-login.php') === 'http://127.0.0.1:18080/wp-login.php', 'Loopback-only HTTP supports isolated local QA with an exact port.');
$assert(DedicatedHostUrls::forRequest('http://127.0.0.1:18080', '127.0.0.1', $sources) === null, 'Ports are not silently widened.');

$GLOBALS['host_test_options'] = [DedicatedHostUrls::OPTION => $origin, 'home' => $store, 'siteurl' => $store];
$GLOBALS['host_test_filters'] = [];
$_SERVER['HTTP_HOST'] = 'store.staging.example';
$_SERVER['HTTP_X_FORWARDED_HOST'] = 'backoffice.staging.example';
DedicatedHostUrls::register();
$assert($GLOBALS['host_test_filters'] === [], 'Storefront requests stay untouched even with a forged forwarded host.');
$_SERVER['HTTP_HOST'] = 'backoffice.staging.example';
DedicatedHostUrls::register();
$assert(array_keys($GLOBALS['host_test_filters']) === ['home_url', 'site_url', 'network_site_url'], 'Only core URL builders are filtered; no authentication/capability/redirect bypass.');
foreach ($GLOBALS['host_test_filters'] as $hook => $callback) {
    $assert($callback($store . '/wp-login.php') === $origin . '/wp-login.php', $hook . ' uses the configured dedicated origin.');
}
$assert($GLOBALS['host_test_options']['home'] === $store && $GLOBALS['host_test_options']['siteurl'] === $store, 'Canonical WordPress configuration is not changed.');

echo "Back Office dedicated host URLs: {$assertions} assertions passed.\n";
