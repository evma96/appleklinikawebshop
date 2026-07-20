<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

final class WordPressLocalDemoPageGateway
{
    public const SLUG = 'eladas';
    public const SHORTCODE = '[appleklinika_buyback_demo]';
    private const MARKER = '_ak_buyback_local_demo_page';

    public function ensure(int $actorId): int
    {
        $existing = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($existing instanceof \WP_Post) {
            if (has_shortcode($existing->post_content, 'appleklinika_buyback_demo') || get_post_meta($existing->ID, self::MARKER, true) === '1') {
                return (int) $existing->ID;
            }
            throw new \RuntimeException('The /eladas/ slug is already used by a non-demo page.');
        }

        $id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Add el vagy számíttasd be iPhone-odat',
            'post_name' => self::SLUG,
            'post_content' => self::SHORTCODE,
            'post_author' => $actorId,
            'comment_status' => 'closed',
        ], true);
        if (is_wp_error($id)) {
            throw new \RuntimeException('Could not create the local demo page: ' . $id->get_error_message());
        }
        update_post_meta((int) $id, self::MARKER, '1');
        return (int) $id;
    }
}
