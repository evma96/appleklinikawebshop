<?php

declare(strict_types=1);

add_action('wp_enqueue_scripts', static function (): void {
    if (! is_front_page()) { return; }
    $base = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('appleklinika-homepage', $uri . '/assets/css/homepage.css', ['appleklinika-theme'], (string) filemtime($base . '/assets/css/homepage.css'));
    wp_enqueue_script('appleklinika-homepage', $uri . '/assets/js/homepage.js', [], (string) filemtime($base . '/assets/js/homepage.js'), true);
});

/** A real catalog image is only a replaceable fallback, never a mock product. */
function appleklinika_home_catalog_image(string $type): int
{
    static $images = [];
    if (isset($images[$type])) { return $images[$type]; }
    $types = match ($type) { 'macbook' => ['mac', 'macbook'], 'apple_watch' => ['watch', 'apple_watch'], default => [$type] };
    $ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 12, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => [['key' => '_appleklinika_device_type', 'value' => $types, 'compare' => 'IN']]]);
    foreach ($ids as $id) {
        $image = get_post_thumbnail_id($id);
        if ($image && wp_attachment_is_image($image)) { return $images[$type] = $image; }
    }
    return $images[$type] = 0;
}

function appleklinika_home_icon(string $icon): string
{
    $paths = [
        'check' => '<path d="m6 12 4 4 8-8"/><circle cx="12" cy="12" r="9"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
        'battery' => '<rect x="3" y="6" width="16" height="12" rx="2"/><path d="M22 10v4M7 10v4m4-4v4m4-4v4"/>',
        'truck' => '<path d="M3 6h11v11H3V6Zm11 4h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'document' => '<path d="M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6m-6 4h6"/>',
        'tools' => '<path d="m14 6 4-3a6 6 0 0 1-7 8l-6 8-3-3 8-6a6 6 0 0 1 8-7l-4 3v3h3"/>',
        'return' => '<path d="M8 5 3 10l5 5M3 10h12a5 5 0 0 1 0 10h-3"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 5h4m-3 14h2"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$icon] ?? $paths['check']) . '</svg>';
}

function appleklinika_home_benefits(array $items, bool $numbered = false): void
{
    foreach ($items as $index => $item) : ?>
        <li class="ak-home-benefit">
            <span class="ak-home-benefit__icon"><?php echo appleklinika_home_icon($item['icon']); ?></span>
            <div><h3><?php if ($numbered) : ?><span class="ak-home-benefit__number"><?php echo esc_html(sprintf('%02d.', $index + 1)); ?></span> <?php endif; ?><?php echo esc_html($item['title']); ?></h3><p><?php echo esc_html($item['text']); ?></p></div>
        </li>
    <?php endforeach;
}

function appleklinika_render_homepage(): void
{
    $content = appleklinika_home_content();
    ?>
    <main class="ak-home" id="wp--skip-link--target">
        <section class="ak-home-hero" aria-label="Apple Klinika ajánlatai" data-home-hero>
            <?php foreach ($content['hero_items'] as $index => $item) : $imageId = $item['image_id'] ?: appleklinika_home_catalog_image('iphone'); ?>
                <div class="ak-home-hero__slide" id="ak-home-slide-<?php echo esc_attr((string) $index); ?>" data-home-slide <?php if ($index > 0) { echo 'hidden'; } ?>>
                    <div class="ak-home-hero__copy">
                        <?php if ($item['eyebrow'] !== '') : ?><p class="ak-home-eyebrow"><?php echo esc_html($item['eyebrow']); ?></p><?php endif; ?>
                        <?php if ($index === 0) : ?><h1><?php echo nl2br(esc_html($item['title'])); ?></h1><?php else : ?><h2 class="ak-home-hero__title"><?php echo nl2br(esc_html($item['title'])); ?></h2><?php endif; ?>
                        <p class="ak-home-hero__text"><?php echo esc_html($item['text']); ?></p>
                        <div class="ak-home-hero__actions">
                            <?php foreach (['primary', 'secondary'] as $kind) : if ($item[$kind . '_label'] === '' || $item[$kind . '_url'] === '') { continue; } ?>
                                <a class="ak-home-cta ak-home-cta--<?php echo esc_attr($kind); ?>" href="<?php echo esc_url($item[$kind . '_url']); ?>"><?php echo esc_html($item[$kind . '_label']); ?><span aria-hidden="true">→</span></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($imageId) : ?><figure class="ak-home-hero__media"><?php echo wp_get_attachment_image($imageId, 'large', false, ['loading' => $index === 0 ? 'eager' : 'lazy', 'fetchpriority' => $index === 0 ? 'high' : 'auto', 'sizes' => '(max-width: 700px) 90vw, 48vw']); ?></figure><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($content['hero_items']) > 1) : ?>
                <nav class="ak-home-hero__navigation" aria-label="Nyitó ajánlatok lapozása" data-home-navigation hidden>
                    <button type="button" data-home-direction="-1" aria-label="Előző ajánlat">←</button><span data-home-status aria-live="polite">1 / <?php echo count($content['hero_items']); ?></span><button type="button" data-home-direction="1" aria-label="Következő ajánlat">→</button>
                </nav>
            <?php endif; ?>
            <?php if ($content['hero_benefits'] !== []) : ?><ul class="ak-home-hero__benefits"><?php appleklinika_home_benefits($content['hero_benefits']); ?></ul><?php endif; ?>
        </section>

        <section class="ak-home-categories" aria-labelledby="ak-home-categories-title">
            <div class="ak-home-heading"><h2 id="ak-home-categories-title"><?php echo esc_html($content['category_title']); ?></h2><a href="<?php echo esc_url(appleklinika_shop_url()); ?>"><?php echo esc_html($content['category_link_label']); ?> <span aria-hidden="true">→</span></a></div>
            <div class="ak-home-categories__grid">
                <?php foreach ($content['categories'] as $index => $tile) :
                    $query = []; parse_str((string) wp_parse_url($tile['url'], PHP_URL_QUERY), $query);
                    $type = isset($query['ak_type']) && is_string($query['ak_type']) ? appleklinika_normalize_shop_device_type($query['ak_type']) : '';
                    $imageId = $tile['image_id'] ?: ($type !== '' ? appleklinika_home_catalog_image($type) : 0);
                    $tag = $tile['url'] !== '' ? 'a' : 'div'; ?>
                    <<?php echo $tag; ?> class="ak-home-category" <?php if ($tag === 'a') : ?>href="<?php echo esc_url($tile['url']); ?>"<?php endif; ?>>
                        <div class="ak-home-category__copy"><h3><?php echo esc_html($tile['title']); ?></h3><p><?php echo esc_html($tile['text']); ?></p><?php if ($tag === 'a') : ?><span class="ak-home-category__arrow" aria-hidden="true">→</span><?php endif; ?></div>
                        <?php if ($imageId) : ?><?php echo wp_get_attachment_image($imageId, 'medium_large', false, ['loading' => 'lazy', 'alt' => '', 'sizes' => '(max-width: 700px) 45vw, 22vw']); ?><?php endif; ?>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ak-home-showcase" id="ak-home-offers" aria-labelledby="ak-home-offers-title">
            <div class="ak-home-heading"><div><h2 id="ak-home-offers-title"><?php echo esc_html($content['featured_title']); ?></h2><p><?php echo esc_html($content['featured_text']); ?></p></div><a href="<?php echo esc_url(appleklinika_shop_url()); ?>"><?php echo esc_html($content['featured_link_label']); ?> <span aria-hidden="true">→</span></a></div>
            <?php appleklinika_render_homepage_product_section('home_featured', appleklinika_home_featured_product_limit(), 'ak-home-products--showcase'); ?>
        </section>

        <section class="ak-home-trust" aria-labelledby="ak-home-trust-title">
            <div class="ak-home-intro"><h2 id="ak-home-trust-title"><?php echo esc_html($content['trust_title']); ?></h2><p><?php echo esc_html($content['trust_text']); ?></p></div>
            <ul class="ak-home-trust__items"><?php appleklinika_home_benefits($content['trust_items']); ?></ul>
        </section>

        <section class="ak-home-process" id="ak-home-process" aria-labelledby="ak-home-process-title">
            <div class="ak-home-process__content"><div class="ak-home-intro"><h2 id="ak-home-process-title"><?php echo esc_html($content['process_title']); ?></h2><p><?php echo esc_html($content['process_text']); ?></p></div><ol class="ak-home-process__steps"><?php appleklinika_home_benefits($content['process_items'], true); ?></ol></div>
            <figure class="ak-home-process__media<?php echo $content['process_image_id'] ? '' : ' is-source-crop'; ?>">
                <?php if ($content['process_image_id']) : ?><?php echo wp_get_attachment_image($content['process_image_id'], 'large', false, ['loading' => 'lazy', 'sizes' => '(max-width: 700px) 90vw, 28vw']); ?>
                <?php else : ?><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/home-service.jpg'); ?>" alt="iPhone szervizelés az Apple Klinikánál" width="940" height="788" loading="lazy"><?php endif; ?>
            </figure>
        </section>
    </main>
    <?php
}
