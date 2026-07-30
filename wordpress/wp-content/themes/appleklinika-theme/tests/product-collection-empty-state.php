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

$test->finish();
