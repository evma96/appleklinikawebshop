<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/wp-load.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
};
$originalQuery = $GLOBALS['wp_query'];
$originalGet = $_GET;
try {
    foreach (['iphone', 'macbook', 'ipad', 'apple_watch'] as $type) {
        $ids = wc_get_products(['limit' => 1, 'status' => 'publish', 'return' => 'ids', 'meta_key' => '_appleklinika_device_type', 'meta_value' => $type]);
        $check(count($ids) === 1, 'Representative '.$type.' fixture exists');
        $_GET = ['ak_type' => 'iphone'];
        $GLOBALS['wp_query'] = new WP_Query(['post_type' => 'product', 'p' => $ids[0]]);
        $check(appleklinika_header_device_type() === $type, 'PDP metadata wins over stale archive query: '.$type);
        ob_start();
        appleklinika_render_header();
        $html = (string) ob_get_clean();
        $check(substr_count($html, 'aria-current="page"') === 1, 'Exactly one current category: '.$type);
    }
    $_GET = [];
    $GLOBALS['wp_query'] = new WP_Query(['pagename' => 'unmatched-qa-page']);
    $check(appleklinika_header_device_type() === null, 'Unrelated page has no default iPhone active state');
    $check(get_locale() === 'hu_HU', 'Hungarian storefront locale configured');
    foreach (['Out of stock', 'In stock', 'Place order', 'Payment method:'] as $text) {
        $check(__($text, 'woocommerce') !== $text, 'Woo PHP language catalogue translates '.$text);
    }
} finally {
    $GLOBALS['wp_query'] = $originalQuery;
    $_GET = $originalGet;
}
echo "Prelaunch presentation/localization passed: {$checks} assertions.\n";
