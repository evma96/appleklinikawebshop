<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 4) . '/wp-load.php';

use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;

// Unsaved Woo objects: no fixtures, prices or stock are persisted.
$display = new ProductFrontendDisplay(new WooProductConditionRepository(), new DeviceCatalogRepository());
$reflection = new ReflectionClass($display);
$call = static fn (string $method, ...$args) => $reflection->getMethod($method)->invoke($display, ...$args);
$html = static function (string $method, ...$args) use ($call): string {
    ob_start();
    try {
        $call($method, ...$args);
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$count = 0;
$check = static function (bool $ok, string $message) use (&$count): void {
    ++$count;
    if (!$ok) throw new RuntimeException($message);
};
$parse = static function (string $markup): DOMXPath {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $markup);
    return new DOMXPath($dom);
};
$previous = $GLOBALS['product'] ?? null;
$sentinel = new WC_Product_Simple();
$GLOBALS['product'] = $sentinel;
try {
    foreach ([
        ['stocked', 5, 'instock', 'no', false, '100000', 1, '5'],
        ['limited', 2, 'instock', 'no', false, '100000', 1, '2'],
        ['out', 0, 'outofstock', 'no', false, '100000', 0, null],
        ['backorder', 0, 'onbackorder', 'notify', false, '100000', 1, ''],
        ['individual', 5, 'instock', 'no', true, '100000', 1, '1'],
        ['not-purchasable', 5, 'instock', 'no', false, '', 0, null],
    ] as $index => [$name, $quantity, $status, $backorders, $individual, $price, $forms, $max]) {
        $fixture = new WC_Product_Simple();
        $fixture->set_id(987650000 + $index);
        $fixture->set_name('Unsaved purchase ' . $name);
        $fixture->set_status('publish');
        $fixture->set_regular_price($price);
        $fixture->set_price($price);
        $fixture->set_manage_stock(true);
        $fixture->set_stock_quantity($quantity);
        $fixture->set_stock_status($status);
        $fixture->set_backorders($backorders);
        $fixture->set_sold_individually($individual);
        $before = serialize($fixture->get_data());
        $rendered = $html('renderPurchaseArea', $fixture);
        $xpath = $parse($rendered);
        $check($xpath->query('//div[@class="appleklinika-cart-area"]')->length === 1, $name . ': one existing purchase owner');
        $check($xpath->query('//form')->length === $forms, $name . ': Woo controls form presence');
        $check($xpath->query('//button[@name="add-to-cart"]')->length === $forms, $name . ': no stale/duplicate button');
        $check($GLOBALS['product'] === $sentinel, $name . ': outer Woo product context restored');
        $check(serialize($fixture->get_data()) === $before, $name . ': no product mutation');
        if ($forms) {
            $check($xpath->query('//button[@name="add-to-cart"]')->item(0)->getAttribute('value') === (string) $fixture->get_id(), $name . ': selected physical ID');
            $qty = $xpath->query('//input[@name="quantity"]')->item(0);
            $check($qty !== null && $qty->getAttribute('max') === $max, $name . ': exact Woo maximum');
            $check($qty->getAttribute('min') === '1' && $qty->getAttribute('value') === '1', $name . ': minimum and default quantity');
        }
        $payload = $call('productSelectorPayload', [$fixture])[0];
        $payloadXpath = $parse($payload['purchaseHtml']);
        $check($payloadXpath->query('//form')->length === $forms, $name . ': payload uses canonical purchase renderer');
        // Quantity IDs are intentionally unique per render; compare semantic form contents.
        $normalize = static fn (string $s): string => preg_replace('/quantity_[a-z0-9]+/', 'quantity_ID', $s);
        $check($normalize($payload['purchaseHtml']) === $normalize($rendered), $name . ': direct renderer and selector fragment identical');
        $panel = $html('renderBuyPanel', $fixture, $fixture->get_id());
        $check(str_contains($normalize($panel), $normalize($rendered)), $name . ': first page uses identical canonical renderer');
        $check($GLOBALS['product'] === $sentinel && serialize($fixture->get_data()) === $before, $name . ': payload/panel restore context and preserve data');
    }
    $throw = static function (): void { throw new RuntimeException('Expected template failure'); };
    add_action('woocommerce_simple_add_to_cart', $throw, 1);
    try {
        $html('renderPurchaseArea', $fixture);
        throw new LogicException('Expected template hook to throw');
    } catch (RuntimeException $e) {
        $check($e->getMessage() === 'Expected template failure', 'Template failure is not swallowed');
        $check($GLOBALS['product'] === $sentinel, 'Global product restored even if template throws');
    } finally {
        remove_action('woocommerce_simple_add_to_cart', $throw, 1);
    }
} finally {
    $GLOBALS['product'] = $previous;
}
echo "Product purchase renderer passed: {$count} assertions; no products saved.\n";
