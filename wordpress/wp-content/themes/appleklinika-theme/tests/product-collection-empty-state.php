<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

final class ProductCollectionEmptyStateTest
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;

        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            exit(1);
        }

        echo "Theme storefront tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$test = new ProductCollectionEmptyStateTest();
$originalQuery = $GLOBALS['wp_query'] ?? null;
$searchQuery = new WP_Query();
$searchQuery->is_search = true;
$GLOBALS['wp_query'] = $searchQuery;

try {
    $test->assert(
        appleklinika_render_product_collection_empty_state('', [], null) === '',
        'A product collection with results keeps WooCommerce\'s empty no-results block empty.'
    );

    $emptyState = appleklinika_render_product_collection_empty_state('<div>Default empty state</div>', [], null);
    $test->assert(
        str_contains($emptyState, 'ak-empty-state--search')
        && str_contains($emptyState, 'Nincs találat erre a keresésre'),
        'A genuine zero-result search receives the single custom Hungarian empty state.'
    );
} finally {
    $GLOBALS['wp_query'] = $originalQuery;
}

$headerQuery = new WP_Query();
$headerQuery->is_home = true;
$GLOBALS['wp_query'] = $headerQuery;

try {
    ob_start();
    appleklinika_render_header();
    $headerHtml = (string) ob_get_clean();

    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $headerHtml . '</body></html>');
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $navigation = $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " ak-category-nav ")]')->item(0);
    $directLinks = $navigation instanceof DOMElement
        ? $xpath->query('./a', $navigation)
        : false;
    $directBreaks = $navigation instanceof DOMElement
        ? $xpath->query('./br', $navigation)
        : false;
    $labels = [];

    if ($directLinks !== false) {
        foreach ($directLinks as $link) {
            $labels[] = trim((string) $link->textContent);
        }
    }

    $sellLink = $xpath->query('./a[contains(concat(" ", normalize-space(@class), " "), " ak-category-nav__sell ")]', $navigation)->item(0);
    $test->assert(
        $labels === ['iPhone', 'MacBook', 'iPad', 'Apple Watch', 'Eladás'],
        'The category navigation renders its five required destinations in logical DOM order.'
    );
    $test->assert(
        $directBreaks !== false && $directBreaks->length === 0,
        'Theme-owned category navigation markup contains no direct structural line breaks.'
    );
    $test->assert(
        $sellLink instanceof DOMElement
        && str_contains(' ' . $sellLink->getAttribute('class') . ' ', ' ak-category-nav__sell '),
        'The Eladás link exposes the stable class used for its full-width mobile row.'
    );
} finally {
    $GLOBALS['wp_query'] = $originalQuery;
}

$test->finish();
