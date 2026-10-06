<?php
// LOCAL runtime guard only; this file is never loaded by the application plugin.
if (getenv('AK_LOCAL_VERIFICATION') !== '1') {
    return;
}
$GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][] = [
    'function' => static fn () => false, 'accepted_args' => 2,
];
$GLOBALS['wp_filter']['pre_http_request'][PHP_INT_MAX][] = [
    'function' => static fn () => new WP_Error('local_verification_network_blocked'), 'accepted_args' => 3,
];

// Vendor clients may use cURL directly instead of the WordPress HTTP API.
// Only this opt-in LOCAL PHP configuration disables/replaces these transports.
if (!function_exists('curl_exec')) {
    function curl_exec($handle) { return false; }
}
if (!function_exists('curl_multi_exec')) {
    function curl_multi_exec($handle, &$running) { $running = 0; return CURLM_INTERNAL_ERROR; }
}

// The LOCAL login language picker must not call WordPress.org while offline.
$GLOBALS['wp_filter']['translations_api'][PHP_INT_MAX][] = [
    'function' => static fn () => ['translations' => []], 'accepted_args' => 3,
];
