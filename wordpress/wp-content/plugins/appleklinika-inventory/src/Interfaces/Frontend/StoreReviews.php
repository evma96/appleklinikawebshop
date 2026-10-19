<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces\Frontend;
use Appleklinika\Inventory\Application\GoogleReviews\ReviewService;
use Appleklinika\Inventory\Infrastructure\WordPress\GoogleBusinessReviews;

final class StoreReviews
{
    public function register(): void
    {
        add_filter('pre_option_woocommerce_enable_reviews', static fn() => 'no');
        add_filter('pre_option_woocommerce_enable_review_rating', static fn() => 'no');
        add_filter('woocommerce_product_get_reviews_allowed', static fn() => false);
        add_filter('woocommerce_product_tabs', static function(array $tabs): array { unset($tabs['reviews']); return $tabs; }, 99);
        add_filter('comments_open', static fn(bool $open, int $id): bool => get_post_type($id) === 'product' ? false : $open, 99, 2);
        add_filter('comments_array', static fn(array $comments, int $id): array => get_post_type($id) === 'product' && !is_admin() ? [] : $comments, 99, 2);
        add_filter('woocommerce_product_get_rating_html', static fn() => '');
        add_filter('woocommerce_structured_data_product', static function(array $data): array { unset($data['aggregateRating'], $data['review']); return $data; }, 99);
        add_filter('render_block', static function(string $html, array $block): string {
            $name = $block['blockName'] ?? '';
            $reviewBlock = str_starts_with($name, 'woocommerce/product-review')
                || str_starts_with($name, 'woocommerce/product-rating')
                || in_array($name, ['woocommerce/product-average-rating','woocommerce/rating-filter','woocommerce/product-filter-rating','woocommerce/reviews-by-product','woocommerce/reviews-by-category','woocommerce/all-reviews'], true);
            return $reviewBlock ? '' : $html;
        }, 99, 2);
        add_action('ak_google_reviews_refresh', static function(): void {
            (new ReviewService(new GoogleBusinessReviews()))->refresh(time());
        });
        add_action('init', static function(): void {
            $gateway = new GoogleBusinessReviews();
            if (!$gateway->configured()) {
                $gateway->clear();
                if (wp_next_scheduled('ak_google_reviews_refresh')) { wp_clear_scheduled_hook('ak_google_reviews_refresh'); }
                return;
            }
            // A public page only schedules; it never waits for a Google response.
            if (!wp_next_scheduled('ak_google_reviews_refresh')) { wp_schedule_event(time() + 30, 'twicedaily', 'ak_google_reviews_refresh'); }
        });
    }
}
