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

    $actions = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " ak-header-actions ")]')->item(0);
    $sellLinks = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " ak-header-sell-link ")]');
    $sellLink = $sellLinks->item(0);
    $test->assert(
        $labels === ['iPhone', 'MacBook', 'iPad', 'Apple Watch'],
        'The category row contains only the four original product destinations in order.'
    );
    $test->assert(
        $directBreaks !== false && $directBreaks->length === 0,
        'Theme-owned category navigation markup contains no direct structural line breaks.'
    );
    $test->assert(
        $sellLink instanceof DOMElement
        && $sellLinks->length === 1
        && $sellLink->parentNode->isSameNode($actions)
        && $sellLink->getAttribute('href') === home_url('/eladas/'),
        'Exactly one Eladás action belongs to the action cluster and keeps its destination.'
    );
    $actionLinks = $xpath->query('./a', $actions);
    $test->assert(
        $actionLinks->length === 3
        && $actionLinks->item(0)->getAttribute('href') === appleklinika_account_url()
        && $actionLinks->item(1)->getAttribute('href') === appleklinika_cart_url(),
        'Account and cart keep their original destinations and precede Eladás.'
    );
    $search = $xpath->query('//form[@role="search"]')->item(0);
    $test->assert(
        $search instanceof DOMElement
        && $search->getAttribute('method') === 'get'
        && $search->getAttribute('action') === home_url('/')
        && $xpath->query('.//input[@name="s"]', $search)->length === 1
        && $xpath->query('.//input[@name="post_type" and @value="product"]', $search)->length === 1,
        'The single native product search retains its GET parameters and target.'
    );
} finally {
    $GLOBALS['wp_query'] = $originalQuery;
}

$originalCart = WC()->cart;
try {
    WC()->cart = new class {
        public function get_cart_contents_count(): int { return 7; }
    };
    ob_start();
    appleklinika_render_cart_link();
    $cartHtml = (string) ob_get_clean();
    $test->assert(str_contains($cartHtml, '<span class="ak-cart-count">7</span>'), 'The badge uses the current Woo cart count, not a fixed value.');
    $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
    $test->assert(($fragments['a.ak-cart-link'] ?? '') === $cartHtml, 'The existing dynamic cart fragment keeps the same single-link replacement contract.');
} finally {
    WC()->cart = $originalCart;
}

$stylesheet = (string) file_get_contents(dirname(__DIR__) . '/assets/css/frontend.css');
$test->assert(
    str_contains($stylesheet, 'grid-template-areas: "logo account cart" "search search sell";')
    && str_contains($stylesheet, '.ak-header-shell .ak-header-actions { display: contents; }')
    && str_contains($stylesheet, '.ak-header-shell p:empty'),
    'Mobile lays out the existing controls without duplicates or formatting-only empty rows.'
);
$test->assert(
    str_contains($stylesheet, 'white-space: nowrap;')
    && str_contains($stylesheet, 'body.tax-product_cat .woocommerce .woocommerce-ordering'),
    'The narrow catalogue toolbar keeps the result count and sort control readable without wrapping them together.'
);
$test->assert(
    str_contains($stylesheet, 'mix-blend-mode: multiply;')
    && str_contains($stylesheet, 'linear-gradient(145deg, #fbfcfe 0%, #f2f5f8 100%)')
    && str_contains($stylesheet, '.ak-shop-filters ~ .ak-shop-filters')
    && str_contains($stylesheet, 'align-self: start;'),
    'Product cards render their source images on one consistent neutral media surface.'
);

$test->finish();
