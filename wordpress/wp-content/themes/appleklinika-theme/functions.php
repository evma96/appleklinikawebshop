<?php

declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'appleklinika-theme',
        get_stylesheet_directory_uri() . '/assets/css/frontend.css',
        [],
        '0.1.206'
    );

    if (function_exists('is_checkout') && is_checkout()) {
        wp_enqueue_style(
            'appleklinika-checkout-sidebar',
            get_stylesheet_directory_uri() . '/assets/css/checkout-sidebar.css',
            ['appleklinika-theme'],
            '0.1.18'
        );
    }

    wp_enqueue_script(
        'appleklinika-theme',
        get_stylesheet_directory_uri() . '/assets/js/frontend.js',
        [],
        '0.1.83',
        true
    );

    wp_localize_script('appleklinika-theme', 'appleklinikaWishlist', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('appleklinika_wishlist'),
        'isLoggedIn' => is_user_logged_in(),
        'loginUrl' => appleklinika_account_url(),
        'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/'),
        'productIds' => is_user_logged_in() ? appleklinika_get_wishlist_product_ids(get_current_user_id()) : [],
    ]);

    if (function_exists('is_checkout') && is_checkout()) {
        wp_localize_script('appleklinika-theme', 'appleklinikaCheckoutSummary', [
            'html' => appleklinika_checkout_summary_markup(),
            'isStaticSnapshot' => true,
        ]);
    }
});

add_action('init', 'appleklinika_ensure_info_pages');
add_action('init', 'appleklinika_register_homepage_block');
add_action('init', 'appleklinika_register_account_endpoints');
add_action('admin_post_nopriv_appleklinika_contact_submit', 'appleklinika_handle_contact_submit');
add_action('admin_post_appleklinika_contact_submit', 'appleklinika_handle_contact_submit');
add_action('wp_ajax_appleklinika_toggle_wishlist', 'appleklinika_handle_wishlist_toggle');
add_action('admin_menu', 'appleklinika_register_homepage_settings_page');
add_action('admin_init', 'appleklinika_register_homepage_settings');
add_filter('wc_get_price_decimals', '__return_zero');
add_filter('wc_get_price_thousand_separator', static fn (): string => ' ');
add_filter('wc_get_price_decimal_separator', static fn (): string => ',');
add_filter('woocommerce_price_format', static fn (): string => '%2$s %1$s');
add_filter('gettext', 'appleklinika_checkout_text_translations', 10, 3);
add_filter('gettext_woocommerce', 'appleklinika_frontend_woocommerce_text_translations', 10, 3);
add_filter('woocommerce_shipping_rate_label', 'appleklinika_frontend_shipping_rate_label', 20, 2);
add_filter('body_class', 'appleklinika_body_classes');
add_filter('render_block_core/site-title', 'appleklinika_render_checkout_site_title_logo', 10, 2);
add_filter('the_content', 'appleklinika_replace_cart_page_content', 9);
add_filter('woocommerce_account_menu_items', 'appleklinika_add_wishlist_account_menu_item');
add_action('woocommerce_account_beszamitasaim_endpoint', 'appleklinika_render_sell_account_endpoint');
add_action('woocommerce_account_garanciaim_endpoint', 'appleklinika_render_warranty_account_endpoint');
add_action('woocommerce_account_visszakuldesek_endpoint', 'appleklinika_render_returns_account_endpoint');
add_action('woocommerce_account_kedvelt-termekek_endpoint', 'appleklinika_render_wishlist_account_endpoint');
add_action('template_redirect', 'appleklinika_redirect_account_downloads_endpoint');
add_action('woocommerce_save_account_details_errors', 'appleklinika_validate_account_details_fields', 10, 2);
add_action('woocommerce_save_account_details', 'appleklinika_save_account_details_fields', 10, 1);
add_action('init', 'appleklinika_register_company_checkout_fields', 20);
add_action('woocommerce_blocks_validate_location_order_fields', 'appleklinika_validate_company_checkout_fields', 10, 3);
add_action('woocommerce_store_api_checkout_update_customer_from_request', 'appleklinika_reset_checkout_profile_save_state', 20, 2);
add_action('woocommerce_store_api_checkout_update_order_from_request', 'appleklinika_persist_company_checkout_fields', 10, 2);
add_action('woocommerce_admin_order_data_after_billing_address', 'appleklinika_render_company_order_admin_meta');
add_filter('woocommerce_get_country_locale_default', 'appleklinika_checkout_default_locale_overrides');
add_filter('woocommerce_get_country_locale', 'appleklinika_checkout_country_locale_overrides');
add_filter('woocommerce_get_default_value_for_appleklinika/company_purchase', 'appleklinika_company_checkout_default_value', 10, 3);
add_filter('woocommerce_get_default_value_for_appleklinika/company_name', 'appleklinika_company_checkout_default_value', 10, 3);
add_filter('woocommerce_get_default_value_for_appleklinika/tax_number', 'appleklinika_company_checkout_default_value', 10, 3);
add_filter('woocommerce_get_default_value_for_appleklinika/save_to_profile', 'appleklinika_checkout_profile_save_default_value', 10, 3);
add_filter('woocommerce_order_formatted_billing_address', 'appleklinika_append_checkout_address_details_to_formatted_address', 10, 2);
add_filter('woocommerce_order_formatted_shipping_address', 'appleklinika_append_checkout_address_details_to_formatted_address', 10, 2);
add_action('after_switch_theme', static function (): void {
    appleklinika_register_account_endpoints();
    flush_rewrite_rules();
});

add_shortcode('appleklinika_homepage', static function (): string {
    ob_start();
    appleklinika_render_homepage();

    return (string) ob_get_clean();
});

function appleklinika_register_homepage_block(): void
{
    register_block_type('appleklinika/homepage', [
        'render_callback' => static function (): string {
            ob_start();
            appleklinika_render_homepage();

            return (string) ob_get_clean();
        },
    ]);
}

add_shortcode('appleklinika_header_actions', static function (): string {
    ob_start();
    appleklinika_render_header_actions();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_header', static function (): string {
    ob_start();
    appleklinika_render_header();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_footer', static function (): string {
    ob_start();
    appleklinika_render_footer();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_info_trust_block', static function (): string {
    if (! appleklinika_is_info_page()) {
        return '';
    }

    ob_start();
    appleklinika_render_info_trust_block();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_contact_panel', static function (): string {
    if (! appleklinika_is_contact_page()) {
        return '';
    }

    ob_start();
    appleklinika_render_contact_panel();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_404_empty_state', static function (): string {
    ob_start();
    appleklinika_render_404_empty_state();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_search_heading', static function (): string {
    ob_start();
    appleklinika_render_search_heading();

    return (string) ob_get_clean();
});

add_shortcode('appleklinika_search_empty_state', static function (): string {
    ob_start();
    appleklinika_render_search_empty_state();

    return (string) ob_get_clean();
});

/**
 * @return array<int, array{label: string, url: string}>
 */
function appleklinika_empty_state_category_links(): array
{
    return [
        ['label' => 'iPhone', 'url' => appleklinika_shop_type_url('iphone')],
        ['label' => 'MacBook', 'url' => appleklinika_shop_type_url('macbook')],
        ['label' => 'iPad', 'url' => appleklinika_shop_type_url('ipad')],
        ['label' => 'Apple Watch', 'url' => appleklinika_shop_type_url('apple_watch')],
    ];
}

/**
 * @param array{
 *     modifier?: string,
 *     title: string,
 *     text: string,
 *     note?: string,
 *     primary_label?: string,
 *     primary_url?: string,
 *     secondary_label?: string,
 *     secondary_url?: string,
 *     show_search?: bool,
 *     search_value?: string,
 *     show_categories?: bool
 * } $args
 */
function appleklinika_render_empty_state(array $args): void
{
    $modifier = isset($args['modifier']) ? sanitize_html_class((string) $args['modifier']) : 'default';
    $classes = trim('ak-empty-state ak-empty-state--' . $modifier);
    ?>
    <section class="<?php echo esc_attr($classes); ?>" aria-labelledby="<?php echo esc_attr('ak-empty-state-title-' . $modifier); ?>">
        <div class="ak-empty-state__card">
            <span class="ak-empty-state__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M5.5 7.5h13l-1.05 9.1a2.2 2.2 0 0 1-2.18 1.9H8.73a2.2 2.2 0 0 1-2.18-1.9L5.5 7.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9 9.2V6.8a3 3 0 0 1 6 0v2.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <h1 id="<?php echo esc_attr('ak-empty-state-title-' . $modifier); ?>" class="ak-empty-state__title"><?php echo esc_html($args['title']); ?></h1>
            <p class="ak-empty-state__text"><?php echo esc_html($args['text']); ?></p>
            <?php if (! empty($args['note'])) : ?>
                <p class="ak-empty-state__note"><?php echo esc_html((string) $args['note']); ?></p>
            <?php endif; ?>

            <?php if (! empty($args['show_search'])) : ?>
                <?php appleklinika_render_empty_state_search((string) ($args['search_value'] ?? '')); ?>
            <?php endif; ?>

            <?php if (! empty($args['primary_label']) || ! empty($args['secondary_label'])) : ?>
                <div class="ak-empty-state__actions">
                    <?php if (! empty($args['primary_label']) && ! empty($args['primary_url'])) : ?>
                        <a class="ak-empty-state__button ak-empty-state__button--primary" href="<?php echo esc_url((string) $args['primary_url']); ?>">
                            <?php echo esc_html((string) $args['primary_label']); ?>
                        </a>
                    <?php endif; ?>
                    <?php if (! empty($args['secondary_label']) && ! empty($args['secondary_url'])) : ?>
                        <a class="ak-empty-state__button ak-empty-state__button--secondary" href="<?php echo esc_url((string) $args['secondary_url']); ?>">
                            <?php echo esc_html((string) $args['secondary_label']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (! array_key_exists('show_categories', $args) || (bool) $args['show_categories']) : ?>
                <?php appleklinika_render_empty_state_categories(); ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function appleklinika_render_empty_state_search(string $value = ''): void
{
    $inputId = wp_unique_id('ak-empty-search-');
    ?>
    <form class="ak-empty-state__search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <label class="screen-reader-text" for="<?php echo esc_attr($inputId); ?>">Keresés a webshopban</label>
        <input id="<?php echo esc_attr($inputId); ?>" type="search" name="s" value="<?php echo esc_attr($value); ?>" placeholder="Keresés Apple készülékre">
        <input type="hidden" name="post_type" value="product">
        <button type="submit">Keresés</button>
    </form>
    <?php
}

function appleklinika_render_empty_state_categories(): void
{
    ?>
    <nav class="ak-empty-state__categories" aria-label="Készülékkategóriák">
        <?php foreach (appleklinika_empty_state_category_links() as $link) : ?>
            <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function appleklinika_render_404_empty_state(): void
{
    appleklinika_render_empty_state([
        'modifier' => '404',
        'title' => 'Ezt az oldalt nem találjuk',
        'text' => 'Lehet, hogy a hivatkozás megváltozott, vagy a keresett oldal már nem elérhető.',
        'primary_label' => 'Vissza a webshopba',
        'primary_url' => appleklinika_shop_url(),
        'show_search' => true,
    ]);
}

function appleklinika_render_search_heading(): void
{
    $query = trim((string) get_search_query());
    ?>
    <div class="ak-search-page__heading">
        <p class="ak-search-page__eyebrow">Keresés</p>
        <h1><?php echo $query !== '' ? esc_html('Keresés: ' . $query) : 'Keresés a webshopban'; ?></h1>
    </div>
    <?php
}

function appleklinika_render_search_empty_state(): void
{
    appleklinika_render_empty_state([
        'modifier' => 'search',
        'title' => 'Nincs találat erre a keresésre',
        'text' => 'Próbálj meg más kulcsszót, vagy nézz körül a készülékkategóriák között.',
        'primary_label' => 'Összes termék',
        'primary_url' => appleklinika_shop_url(),
        'show_search' => true,
        'search_value' => get_search_query(),
    ]);
}

function appleklinika_register_homepage_settings_page(): void
{
    add_options_page(
        'Apple Klinika homepage',
        'Apple Klinika homepage',
        'manage_options',
        'appleklinika-homepage',
        'appleklinika_render_homepage_settings_page'
    );
}

function appleklinika_register_homepage_settings(): void
{
    register_setting('appleklinika_homepage_settings', 'appleklinika_home_featured_product_ids', [
        'sanitize_callback' => 'appleklinika_sanitize_home_featured_product_ids',
        'default' => [],
    ]);

    register_setting('appleklinika_homepage_settings', 'appleklinika_home_featured_product_limit', [
        'sanitize_callback' => 'appleklinika_sanitize_home_featured_product_limit',
        'default' => 6,
    ]);
}

function appleklinika_render_homepage_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $selectedIds = appleklinika_home_featured_product_ids();
    $limit = appleklinika_home_featured_product_limit();
    ?>
    <div class="wrap">
        <h1>Apple Klinika homepage</h1>
        <form method="post" action="options.php">
            <?php settings_fields('appleklinika_homepage_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="appleklinika_home_featured_product_ids">Kiemelt Apple ajánlatok termékek</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="regular-text"
                            id="appleklinika_home_featured_product_ids"
                            name="appleklinika_home_featured_product_ids"
                            value="<?php echo esc_attr(implode(', ', $selectedIds)); ?>"
                            placeholder="Pl. 123, 456, 789"
                        >
                        <p class="description">WooCommerce termék ID-k vesszővel elválasztva. A sorrend megmarad, és csak publikus termékek jelennek meg.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="appleklinika_home_featured_product_limit">Megjelenített termékek száma</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="appleklinika_home_featured_product_limit"
                            name="appleklinika_home_featured_product_limit"
                            value="<?php echo esc_attr((string) $limit); ?>"
                            min="1"
                            max="12"
                            step="1"
                        >
                        <p class="description">Engedélyezett tartomány: 1-12. Alapértelmezett: 6.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Beállítások mentése'); ?>
        </form>
    </div>
    <?php
}

/**
 * @return array<int, int>
 */
function appleklinika_home_featured_product_ids(): array
{
    return appleklinika_sanitize_home_featured_product_ids(get_option('appleklinika_home_featured_product_ids', []));
}

/**
 * @param mixed $value
 * @return array<int, int>
 */
function appleklinika_sanitize_home_featured_product_ids($value): array
{
    if (is_string($value)) {
        $rawIds = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    } elseif (is_array($value)) {
        $rawIds = $value;
    } else {
        $rawIds = [];
    }

    $ids = [];

    foreach ($rawIds as $rawId) {
        $productId = absint($rawId);

        if ($productId <= 0 || in_array($productId, $ids, true)) {
            continue;
        }

        if (! appleklinika_is_public_product_id($productId)) {
            continue;
        }

        $ids[] = $productId;
    }

    return $ids;
}

function appleklinika_is_public_product_id(int $productId): bool
{
    if (function_exists('wc_get_product')) {
        $product = wc_get_product($productId);

        return $product instanceof WC_Product && $product->get_status() === 'publish';
    }

    return get_post_type($productId) === 'product' && get_post_status($productId) === 'publish';
}

function appleklinika_home_featured_product_limit(): int
{
    return appleklinika_sanitize_home_featured_product_limit(get_option('appleklinika_home_featured_product_limit', 6));
}

/**
 * @param mixed $value
 */
function appleklinika_sanitize_home_featured_product_limit($value): int
{
    return max(1, min(12, absint($value ?: 6)));
}

add_shortcode('appleklinika_cart_page', static function (): string {
    if (! appleklinika_is_cart_page()) {
        return '';
    }

    ob_start();
    appleklinika_render_cart_page();

    return (string) ob_get_clean();
});

add_filter('woocommerce_add_to_cart_fragments', static function (array $fragments): array {
    ob_start();
    appleklinika_render_cart_link();
    $fragments['a.ak-cart-link'] = (string) ob_get_clean();

    return $fragments;
});

add_action('woocommerce_before_shop_loop', 'appleklinika_render_shop_filters', 5);
add_action('woocommerce_before_shop_loop', 'appleklinika_render_active_filter_chips', 6);
add_action('pre_get_posts', 'appleklinika_apply_shop_filters');
add_action('wp', 'appleklinika_customize_shop_loop_cards');
add_action('wp', 'appleklinika_replace_shop_no_products_empty_state');
add_filter('woocommerce_catalog_orderby', 'appleklinika_catalog_orderby_options');
add_filter('woocommerce_default_catalog_orderby_options', 'appleklinika_catalog_orderby_options');
add_filter('woocommerce_get_catalog_ordering_args', 'appleklinika_catalog_ordering_args', 10, 3);
add_filter('posts_clauses', 'appleklinika_sale_first_ordering_clauses', 20, 2);
add_filter('render_block', 'appleklinika_remove_duplicate_shop_product_blocks', 20, 3);
add_filter('render_block_woocommerce/product-collection-no-results', 'appleklinika_render_product_collection_empty_state', 20, 3);
add_filter('render_block_woocommerce/product-image', 'appleklinika_remove_duplicate_shop_product_block', 100, 3);
add_filter('render_block_woocommerce/product-price', 'appleklinika_remove_duplicate_shop_product_block', 100, 3);
add_filter('render_block_woocommerce/product-button', 'appleklinika_remove_duplicate_shop_product_block', 100, 3);
add_filter('render_block_core/post-title', 'appleklinika_remove_duplicate_shop_product_title_block', 100, 3);

function appleklinika_format_plain_price(float $amount): string
{
    return number_format($amount, 0, '', ' ') . ' Ft';
}

function appleklinika_remove_duplicate_shop_product_blocks(string $blockContent, array $block, ?WP_Block $instance = null): string
{
    if (! appleklinika_is_shop_archive_context()) {
        return $blockContent;
    }

    $blockName = (string) ($block['blockName'] ?? '');
    $duplicateProductBlocks = [
        'woocommerce/product-image',
        'woocommerce/product-price',
        'woocommerce/product-button',
    ];

    if (in_array($blockName, $duplicateProductBlocks, true) && appleklinika_is_shop_product_block_context($block, $instance)) {
        return '';
    }

    if ($blockName === 'core/post-title' && appleklinika_is_shop_product_title_block($block, $instance)) {
        return '';
    }

    return $blockContent;
}

function appleklinika_remove_duplicate_shop_product_block(string $blockContent, array $block, ?WP_Block $instance = null): string
{
    if (! appleklinika_is_shop_archive_context()) {
        return $blockContent;
    }

    return appleklinika_is_shop_product_block_context($block, $instance) ? '' : $blockContent;
}

function appleklinika_remove_duplicate_shop_product_title_block(string $blockContent, array $block, ?WP_Block $instance = null): string
{
    if (! appleklinika_is_shop_archive_context()) {
        return $blockContent;
    }

    return appleklinika_is_shop_product_title_block($block, $instance) ? '' : $blockContent;
}

function appleklinika_is_shop_archive_context(): bool
{
    if (function_exists('is_shop') && is_shop()) {
        return true;
    }

    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        return true;
    }

    if (is_post_type_archive('product')) {
        return true;
    }

    return get_query_var('post_type') === 'product' || ($_GET['post_type'] ?? '') === 'product';
}

function appleklinika_is_shop_product_title_block(array $block, ?WP_Block $instance): bool
{
    if (appleklinika_is_shop_product_block_context($block, $instance)) {
        return true;
    }

    return (bool) ($block['attrs']['isLink'] ?? false);
}

function appleklinika_is_shop_product_block_context(array $block, ?WP_Block $instance): bool
{
    $context = $instance instanceof WP_Block ? $instance->context : [];

    if (($context['postType'] ?? '') === 'product') {
        return true;
    }

    if (isset($context['postId']) && get_post_type((int) $context['postId']) === 'product') {
        return true;
    }

    if (($context['query']['postType'] ?? '') === 'product') {
        return true;
    }

    return (bool) ($block['attrs']['isDescendentOfQueryLoop'] ?? false);
}

function appleklinika_checkout_text_translations(string $translation, string $text, string $domain): string
{
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return $translation;
    }

    if ($domain === 'woocommerce' && $text === 'Additional order information') {
        return 'Céges adatok';
    }

    return $translation;
}

function appleklinika_frontend_woocommerce_text_translations(string $translation, string $text, string $domain): string
{
    if ($domain !== 'woocommerce' || (is_admin() && ! wp_doing_ajax())) {
        return $translation;
    }

    return match ($text) {
        'Original price was: %s.' => 'Eredeti ár: %s.',
        'Current price is: %s.' => 'Jelenlegi ár: %s.',
        default => $translation,
    };
}

function appleklinika_frontend_shipping_rate_label(string $label, WC_Shipping_Rate $rate): string
{
    if ($rate->get_method_id() === 'free_shipping' && $label === 'Free shipping') {
        return 'Ingyenes szállítás';
    }

    return $label;
}

function appleklinika_body_classes(array $classes): array
{
    if (appleklinika_is_info_page()) {
        $classes[] = 'ak-info-page';
    }

    if (appleklinika_is_contact_page()) {
        $classes[] = 'ak-contact-page';
    }

    return $classes;
}

function appleklinika_is_info_page(): bool
{
    if (! function_exists('is_page') || ! is_page()) {
        return false;
    }

    return is_page(array_keys(appleklinika_info_pages()));
}

function appleklinika_is_contact_page(): bool
{
    return function_exists('is_page') && is_page('kapcsolat');
}

function appleklinika_is_cart_page(): bool
{
    return function_exists('is_cart') && is_cart();
}

function appleklinika_render_cart_page(): void
{
    if (! function_exists('WC') || ! WC()->cart) {
        echo '<p class="ak-empty">A kosár jelenleg nem érhető el.</p>';
        return;
    }

    if (WC()->cart->is_empty()) {
        ?>
        <section class="ak-cart-layout ak-cart-layout--empty">
            <?php
            appleklinika_render_empty_state([
                'modifier' => 'cart',
                'title' => 'A kosarad jelenleg üres',
                'text' => 'Nézz körül az ellenőrzött Apple készülékek között, és válaszd ki a következő kedvenced.',
                'note' => 'A pénztár használatához előbb tegyél terméket a kosárba.',
                'primary_label' => 'Termékek böngészése',
                'primary_url' => appleklinika_shop_url(),
            ]);
            ?>
        </section>
        <?php
        return;
    }
    ?>
    <section class="ak-cart-layout" aria-label="Kosár tartalma">
        <form id="ak-cart-form" class="ak-cart-card ak-cart-items-card" action="<?php echo esc_url(appleklinika_cart_url()); ?>" method="post">
            <div class="ak-cart-card__head">
                <h1>Kosár</h1>
                <p>Rendelésed áttekintése</p>
            </div>

            <div class="ak-cart-items">
                <?php foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) : ?>
                    <?php appleklinika_render_cart_item((string) $cartItemKey, $cartItem); ?>
                <?php endforeach; ?>
            </div>

            <div class="ak-cart-actions">
                <button type="submit" class="ak-button ak-button--secondary" name="update_cart" value="Kosár frissítése">Kosár frissítése</button>
                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
            </div>
        </form>

        <aside class="ak-cart-card ak-cart-summary" aria-label="Rendelés összesítő">
            <h2>Rendelés összesítő</h2>
            <?php appleklinika_render_cart_summary(); ?>
        </aside>
    </section>
    <?php
}

function appleklinika_replace_cart_page_content(string $content): string
{
    if (is_admin() || ! in_the_loop() || ! is_main_query() || ! appleklinika_is_cart_page()) {
        return $content;
    }

    ob_start();
    appleklinika_render_cart_page();

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $cartItem
 */
function appleklinika_render_cart_item(string $cartItemKey, array $cartItem): void
{
    $product = $cartItem['data'] ?? null;

    if (! $product instanceof WC_Product || ! $product->exists() || (int) ($cartItem['quantity'] ?? 0) <= 0) {
        return;
    }

    $productId = (int) ($cartItem['product_id'] ?? $product->get_id());
    $quantity = (int) $cartItem['quantity'];
    $permalink = $product->is_visible() ? get_permalink($productId) : '';
    $meta = appleklinika_cart_item_meta($productId, $cartItem);
    $regularPrice = (float) $product->get_regular_price();
    $currentPrice = (float) $product->get_price();
    $savings = $regularPrice > $currentPrice ? ($regularPrice - $currentPrice) * $quantity : 0;
    $removeUrl = wc_get_cart_remove_url($cartItemKey);
    ?>
    <article class="ak-cart-item">
        <div class="ak-cart-item__row">
            <a class="ak-cart-item__image" href="<?php echo esc_url($permalink ?: appleklinika_shop_url()); ?>">
                <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail')); ?>
            </a>

            <div class="ak-cart-item__content">
                <h2>
                    <?php if ($permalink !== '') : ?>
                        <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($product->get_name()); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($product->get_name()); ?>
                    <?php endif; ?>
                </h2>

                <?php if ($meta !== []) : ?>
                    <p class="ak-cart-item__meta"><?php echo esc_html(implode(' · ', $meta)); ?></p>
                <?php endif; ?>

                <div class="ak-cart-item__controls">
                    <label class="screen-reader-text" for="ak-cart-qty-<?php echo esc_attr($cartItemKey); ?>">
                        <?php echo esc_html($product->get_name()); ?> mennyisége
                    </label>
                    <div class="ak-cart-qty-control" data-cart-qty-control>
                        <button type="button" aria-label="Mennyiség csökkentése" data-cart-qty-decrease>−</button>
                        <input
                            id="ak-cart-qty-<?php echo esc_attr($cartItemKey); ?>"
                            class="ak-cart-item__qty"
                            type="number"
                            min="0"
                            step="1"
                            name="cart[<?php echo esc_attr($cartItemKey); ?>][qty]"
                            value="<?php echo esc_attr((string) $quantity); ?>"
                        >
                        <button type="button" aria-label="Mennyiség növelése" data-cart-qty-increase>+</button>
                    </div>
                    <a
                        class="ak-cart-item__remove"
                        href="<?php echo esc_url($removeUrl); ?>"
                        aria-label="<?php echo esc_attr(sprintf('%s eltávolítása a kosárból', $product->get_name())); ?>"
                        data-product_id="<?php echo esc_attr((string) $productId); ?>"
                        data-product_sku="<?php echo esc_attr($product->get_sku()); ?>"
                    >Eltávolítás</a>
                </div>
            </div>

            <div class="ak-cart-item__price">
                <?php if ($regularPrice > $currentPrice) : ?>
                    <del><?php echo wp_kses_post(wc_price($regularPrice * $quantity)); ?></del>
                <?php endif; ?>
                <strong><?php echo wp_kses_post(WC()->cart->get_product_subtotal($product, $quantity)); ?></strong>
                <?php if ($savings > 0) : ?>
                    <span><?php echo esc_html(appleklinika_format_plain_price($savings)); ?> megtakarítás</span>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

/**
 * @param array<string, mixed> $cartItem
 * @return array<int, string>
 */
function appleklinika_cart_item_meta(int $productId, array $cartItem): array
{
    $meta = array_filter([
        appleklinika_storage_label((string) get_post_meta($productId, '_appleklinika_storage_capacity', true)),
        appleklinika_color_label((string) get_post_meta($productId, '_appleklinika_color', true)),
        appleklinika_grade_label((string) get_post_meta($productId, '_appleklinika_overall_grade', true)),
        appleklinika_battery_label((string) get_post_meta($productId, '_appleklinika_battery_health', true)),
    ]);

    if (! empty($cartItem['appleklinika_battery_extra_label'])) {
        $meta[] = 'Akkumulátor extra: ' . (string) $cartItem['appleklinika_battery_extra_label'];
    }

    return array_values($meta);
}

function appleklinika_render_cart_summary(): void
{
    $cart = WC()->cart;
    ?>
    <div class="ak-cart-summary__body">
        <div class="ak-cart-summary__rows">
            <div class="ak-cart-summary__row">
                <span>Részösszeg</span>
                <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
            </div>
            <div class="ak-cart-summary__row">
                <span>Szállítás</span>
                <strong><?php echo wp_kses_post($cart->get_cart_shipping_total()); ?></strong>
            </div>
        </div>

        <?php if (wc_coupons_enabled()) : ?>
            <div class="ak-cart-coupon">
                <label for="ak-cart-coupon-code">Kuponkód</label>
                <div>
                    <input
                        id="ak-cart-coupon-code"
                        type="text"
                        name="coupon_code"
                        form="ak-cart-form"
                        placeholder="Kuponkód"
                    >
                    <button type="submit" name="apply_coupon" value="Kupon alkalmazása" form="ak-cart-form">Kupon alkalmazása</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="ak-cart-summary__total">
            <span>Végösszeg</span>
            <strong><?php echo wp_kses_post($cart->get_total()); ?></strong>
        </div>
    </div>

    <a class="ak-cart-checkout" href="<?php echo esc_url(wc_get_checkout_url()); ?>">Tovább a pénztárhoz</a>
    <a class="ak-cart-continue" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Vásárlás folytatása</a>
    <?php
}

function appleklinika_checkout_summary_markup(): string
{
    if (! function_exists('WC') || ! (WC()->cart instanceof WC_Cart)) {
        return '';
    }

    ob_start();
    appleklinika_render_checkout_summary(WC()->cart);

    return (string) ob_get_clean();
}

function appleklinika_render_checkout_summary(WC_Cart $cart): void
{
    ?>
    <aside class="ak-checkout-summary" aria-label="Rendelés összesítő">
        <h2 class="ak-checkout-summary__title">Rendelés összesítő</h2>

        <?php if ($cart->is_empty()) : ?>
            <p class="ak-checkout-summary__empty">A kosarad jelenleg üres.</p>
        <?php else : ?>
            <div class="ak-checkout-summary__items">
                <?php foreach ($cart->get_cart() as $cartItem) : ?>
                    <?php appleklinika_render_checkout_summary_item($cartItem, $cart); ?>
                <?php endforeach; ?>
            </div>

            <?php appleklinika_render_checkout_summary_coupons($cart); ?>
            <?php appleklinika_render_checkout_summary_totals($cart); ?>
        <?php endif; ?>
    </aside>
    <?php
}

/**
 * @param array<string, mixed> $cartItem
 */
function appleklinika_render_checkout_summary_item(array $cartItem, WC_Cart $cart): void
{
    $product = $cartItem['data'] ?? null;
    $quantity = (int) ($cartItem['quantity'] ?? 0);

    if (! $product instanceof WC_Product || ! $product->exists() || $quantity <= 0) {
        return;
    }

    $productId = (int) ($cartItem['product_id'] ?? $product->get_id());
    $permalink = $product->is_visible() ? get_permalink($productId) : '';
    $linePrice = $cart->get_product_subtotal($product, $quantity);
    ?>
    <article class="ak-checkout-summary__item">
        <div class="ak-checkout-summary__thumb">
            <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', ['class' => 'ak-checkout-summary__image'])); ?>
            <span class="ak-checkout-summary__qty"><?php echo esc_html((string) $quantity); ?></span>
        </div>

        <div class="ak-checkout-summary__item-body">
            <h3 class="ak-checkout-summary__item-title">
                <?php if ($permalink !== '') : ?>
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($product->get_name()); ?></a>
                <?php else : ?>
                    <?php echo esc_html($product->get_name()); ?>
                <?php endif; ?>
            </h3>
        </div>

        <div class="ak-checkout-summary__item-aside">
            <div class="ak-checkout-summary__item-price"><?php echo wp_kses_post($linePrice); ?></div>
        </div>
    </article>
    <?php
}

function appleklinika_render_checkout_summary_coupons(WC_Cart $cart): void
{
    $coupons = $cart->get_coupons();

    if ($coupons === []) {
        return;
    }
    ?>
    <div class="ak-checkout-summary__coupons" aria-label="Kuponok">
        <?php foreach ($coupons as $code => $coupon) : ?>
            <?php
            $discount = (float) $cart->get_coupon_discount_amount((string) $code, $cart->display_cart_ex_tax);
            ?>
            <div class="ak-checkout-summary__row">
                <span><?php echo esc_html(sprintf('Kupon: %s', wc_format_coupon_code((string) $code))); ?></span>
                <strong><?php echo wp_kses_post('-' . wc_price($discount)); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function appleklinika_render_checkout_summary_totals(WC_Cart $cart): void
{
    $discountTotal = (float) $cart->get_discount_total();
    ?>
    <div class="ak-checkout-summary__totals">
        <div class="ak-checkout-summary__row">
            <span>Részösszeg</span>
            <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
        </div>

        <?php if ($discountTotal > 0) : ?>
            <div class="ak-checkout-summary__row">
                <span>Kedvezmény</span>
                <strong><?php echo wp_kses_post('-' . wc_price($discountTotal)); ?></strong>
            </div>
        <?php endif; ?>

        <div class="ak-checkout-summary__row">
            <span>Szállítás</span>
            <strong><?php echo wp_kses_post($cart->get_cart_shipping_total()); ?></strong>
        </div>

        <div class="ak-checkout-summary__row ak-checkout-summary__row--total">
            <span>Összeg</span>
            <strong><?php echo wp_kses_post($cart->get_total()); ?></strong>
        </div>
    </div>
    <?php
}

function appleklinika_handle_contact_submit(): void
{
    $redirectUrl = appleklinika_info_page_url('kapcsolat');

    if (! isset($_POST['appleklinika_contact_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['appleklinika_contact_nonce'])), 'appleklinika_contact_submit')) {
        wp_safe_redirect(add_query_arg('ak_contact_status', 'error', $redirectUrl));
        exit;
    }

    $honeypot = isset($_POST['website']) ? trim((string) wp_unslash($_POST['website'])) : '';

    if ($honeypot !== '') {
        wp_safe_redirect(add_query_arg('ak_contact_status', 'sent', $redirectUrl));
        exit;
    }

    $name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';
    $email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '';
    $phone = isset($_POST['contact_phone']) ? sanitize_text_field(wp_unslash($_POST['contact_phone'])) : '';
    $message = isset($_POST['contact_message']) ? sanitize_textarea_field(wp_unslash($_POST['contact_message'])) : '';

    if ($name === '' || $email === '' || ! is_email($email) || $message === '') {
        wp_safe_redirect(add_query_arg('ak_contact_status', 'error', $redirectUrl));
        exit;
    }

    $body = implode("\n", [
        'Új Appleklinika kapcsolatfelvétel',
        '',
        'Név: ' . $name,
        'E-mail: ' . $email,
        'Telefon: ' . ($phone !== '' ? $phone : 'nincs megadva'),
        '',
        'Üzenet:',
        $message,
    ]);

    wp_mail(
        get_option('admin_email'),
        'Új Appleklinika kapcsolatfelvétel',
        $body,
        ['Reply-To: ' . $name . ' <' . $email . '>']
    );

    wp_safe_redirect(add_query_arg('ak_contact_status', 'sent', $redirectUrl));
    exit;
}

function appleklinika_render_contact_panel(): void
{
    $status = isset($_GET['ak_contact_status']) ? sanitize_key(wp_unslash($_GET['ak_contact_status'])) : '';
    ?>
    <section class="ak-contact-panel" aria-label="Kapcsolati adatok">
        <div class="ak-contact-grid">
            <article class="ak-contact-card">
                <span>Tel</span>
                <h3>Telefon</h3>
                <p><a href="tel:+36300000000">+36 30 000 0000</a></p>
            </article>
            <article class="ak-contact-card">
                <span>@</span>
                <h3>E-mail</h3>
                <p><a href="mailto:info@appleklinika.hu">info@appleklinika.hu</a></p>
            </article>
            <article class="ak-contact-card">
                <span>Pin</span>
                <h3>Üzlet</h3>
                <p>6720 Szeged, minta üzletcím</p>
            </article>
        </div>

        <div class="ak-contact-body">
            <form class="ak-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <h3>Írj nekünk</h3>
                <p>Válaszolunk készülék, rendelés, garancia vagy személyes átvétel kérdésben.</p>

                <?php if ($status === 'sent') : ?>
                    <div class="ak-contact-notice ak-contact-notice--success">Köszönjük, az üzenetet megkaptuk.</div>
                <?php elseif ($status === 'error') : ?>
                    <div class="ak-contact-notice ak-contact-notice--error">Kérlek ellenőrizd a kötelező mezőket.</div>
                <?php endif; ?>

                <input type="hidden" name="action" value="appleklinika_contact_submit">
                <?php wp_nonce_field('appleklinika_contact_submit', 'appleklinika_contact_nonce'); ?>
                <label class="ak-contact-hp" aria-hidden="true">
                    Weboldal
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>

                <div class="ak-contact-form-grid">
                    <label>
                        Név
                        <input type="text" name="contact_name" autocomplete="name" required>
                    </label>
                    <label>
                        E-mail
                        <input type="email" name="contact_email" autocomplete="email" required>
                    </label>
                    <label class="ak-contact-form-wide">
                        Telefon
                        <input type="tel" name="contact_phone" autocomplete="tel">
                    </label>
                    <label class="ak-contact-form-wide">
                        Üzenet
                        <textarea name="contact_message" rows="5" required></textarea>
                    </label>
                </div>

                <button type="submit" class="ak-button ak-button--primary">Üzenet küldése</button>
            </form>

            <aside class="ak-contact-map" aria-label="Térkép helye">
                <strong>Appleklinika Szeged</strong>
                <span>Térkép helye</span>
                <p>A pontos üzletcím és beágyazott térkép az indulás előtt frissíthető.</p>
            </aside>
        </div>
    </section>
    <?php
}

function appleklinika_render_info_trust_block(): void
{
    $items = [
        [
            'icon' => '✓',
            'title' => '12 hónap garancia',
            'text' => 'Átlátható feltételek minden készüléknél.',
        ],
        [
            'icon' => '✓',
            'title' => 'Ingyenes szállítás',
            'text' => 'Egyszerű kézbesítés a rendelés után.',
        ],
        [
            'icon' => '✓',
            'title' => '14 napos visszaküldés',
            'text' => 'Biztonságos döntés, ha mégsem megfelelő.',
        ],
        [
            'icon' => '✓',
            'title' => 'Gyors ügyintézés',
            'text' => 'Segítünk garancia, rendelés és kérdés esetén.',
        ],
    ];
    ?>
    <section class="ak-info-trust" aria-label="Appleklinika bizalmi információk">
        <?php foreach ($items as $item) : ?>
            <article class="ak-info-trust-card">
                <span class="ak-info-trust-icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span>
                <div>
                    <h3><?php echo esc_html($item['title']); ?></h3>
                    <p><?php echo esc_html($item['text']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
}

function appleklinika_render_header_actions(): void
{
    ?>
    <div class="ak-header-actions">
        <a class="ak-header-pill ak-account-link" href="<?php echo esc_url(appleklinika_account_url()); ?>">
            <?php appleklinika_render_header_icon('account'); ?>
            <span class="ak-header-pill__label">Fiókom</span>
        </a>
        <?php appleklinika_render_cart_link(); ?>
    </div>
    <?php
}

function appleklinika_render_header_icon(string $icon): void
{
    if ($icon === 'account') {
        ?>
        <span class="ak-header-pill__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M12 12.25c2.2 0 4-1.8 4-4.02 0-2.2-1.8-3.98-4-3.98S8 6.03 8 8.23c0 2.22 1.8 4.02 4 4.02Zm0 2.05c-3.22 0-6.05 1.65-7.6 4.13-.42.68.06 1.57.86 1.57h13.48c.8 0 1.28-.89.86-1.57-1.55-2.48-4.38-4.13-7.6-4.13Z" fill="currentColor"/>
            </svg>
        </span>
        <?php
        return;
    }

    if ($icon === 'cart') {
        ?>
        <span class="ak-header-pill__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M7.2 8.1h9.6l-.78 8.38a2 2 0 0 1-1.99 1.82H9.97a2 2 0 0 1-1.99-1.82L7.2 8.1Zm2.3-1.6a2.5 2.5 0 0 1 5 0v1.1h-1.6V6.5a.9.9 0 0 0-1.8 0v1.1H9.5V6.5ZM6.02 6.9a.8.8 0 0 0-.79.87l.84 8.86a3.92 3.92 0 0 0 3.9 3.57h4.06a3.92 3.92 0 0 0 3.9-3.57l.84-8.86a.8.8 0 0 0-.79-.87H6.02Z" fill="currentColor"/>
            </svg>
        </span>
        <?php
    }
}

function appleklinika_should_show_category_nav(): bool
{
    return is_front_page() || appleklinika_is_shop_archive_context() || is_page('eladas');
}

function appleklinika_render_header(): void
{
    $categoryLinks = [
        'iphone' => 'iPhone',
        'macbook' => 'MacBook',
        'ipad' => 'iPad',
        'apple_watch' => 'Apple Watch',
    ];
    $activeCategory = appleklinika_current_shop_device_type();
    ?>
    <div class="ak-header-shell">
        <div class="ak-header-top">
            <a class="ak-header-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Appleklinika kezdőlap">
                <img src="<?php echo esc_url(plugins_url('appleklinika-inventory/assets/brand/appleklinika-logo.jpg')); ?>" alt="Appleklinika">
            </a>
            <form class="ak-product-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <label class="screen-reader-text" for="ak-product-search-field">Termék keresése</label>
                <input id="ak-product-search-field" type="search" name="s" placeholder="Keresés..." value="<?php echo esc_attr(get_search_query()); ?>">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" aria-label="Keresés">⌕</button>
            </form>
            <?php appleklinika_render_header_actions(); ?>
        </div>
        <?php if (appleklinika_should_show_category_nav()) : ?>
            <nav class="ak-category-nav" aria-label="Apple termékkategóriák">
                <?php foreach ($categoryLinks as $categoryType => $categoryLabel) : ?>
                    <?php $isActive = appleklinika_is_shop_archive_context() && $activeCategory === $categoryType; ?>
                    <a
                        class="<?php echo $isActive ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(appleklinika_shop_type_url($categoryType)); ?>"
                        <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                    ><?php echo esc_html($categoryLabel); ?></a>
                <?php endforeach; ?>
                <span class="ak-category-nav__spacer" aria-hidden="true"></span>
                <a class="ak-category-nav__sell<?php echo is_page('eladas') ? ' is-active' : ''; ?>" href="<?php echo esc_url(home_url('/eladas/')); ?>"<?php echo is_page('eladas') ? ' aria-current="page"' : ''; ?>>Eladás</a>
            </nav>
        <?php endif; ?>
    </div>
    <?php
}

function appleklinika_render_checkout_site_title_logo(string $blockContent, array $block): string
{
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return $blockContent;
    }

    $logoUrl = plugins_url('appleklinika-inventory/assets/brand/appleklinika-logo.jpg');

    ob_start();
    ?>
    <div class="ak-checkout-header-inner">
        <a class="ak-header-logo ak-checkout-header__logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Appleklinika kezdőlap">
            <img src="<?php echo esc_url($logoUrl); ?>" alt="Appleklinika">
        </a>
        <span class="ak-checkout-header__label">Biztonságos pénztár</span>
        <a class="ak-checkout-header__back" href="<?php echo esc_url(appleklinika_cart_url()); ?>">Vissza a kosárhoz</a>
    </div>
    <?php

    return (string) ob_get_clean();
}

function appleklinika_ensure_info_pages(): void
{
    foreach (appleklinika_info_pages() as $slug => $page) {
        $existingPage = get_page_by_path($slug);

        if ($existingPage instanceof WP_Post) {
            if (
                $slug === 'kapcsolat'
                && (
                    strpos($existingPage->post_content, 'Ez egy előkészített demo kapcsolat oldal') !== false
                    || strpos($existingPage->post_content, 'Telefon: hamarosan pontosítjuk') !== false
                )
            ) {
                wp_update_post([
                    'ID' => $existingPage->ID,
                    'post_content' => $page['content'],
                ]);
            }

            continue;
        }

        $legacySlug = (string) ($page['legacy_slug'] ?? '');

        if ($legacySlug !== '') {
            $legacyPage = get_page_by_path($legacySlug);

            if ($legacyPage instanceof WP_Post) {
                wp_update_post([
                    'ID' => $legacyPage->ID,
                    'post_name' => $slug,
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                ]);
                continue;
            }
        }

        wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_name' => $slug,
            'post_title' => $page['title'],
            'post_content' => $page['content'],
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
    }
}

/**
 * @return array<string, array{title: string, content: string, legacy_slug?: string}>
 */
function appleklinika_info_pages(): array
{
    return [
        'kapcsolat' => [
            'title' => 'Kapcsolat',
            'content' => '<!-- wp:heading --><h2>Kapcsolat</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Keress minket készülék, rendelés, garancia vagy személyes átvétel ügyében. Az alábbi elérhetőségek és az űrlap jelenleg indulás előtti mintaadatokkal működnek, később WordPress adminból pontosíthatók.</p><!-- /wp:paragraph -->',
        ],
        'szallitas' => [
            'title' => 'Szállítás',
            'legacy_slug' => 'szallitasi-informaciok',
            'content' => '<!-- wp:heading --><h2>Szállítás</h2><!-- /wp:heading --><!-- wp:paragraph --><p>A megrendelt készülékeket a rendelés feldolgozása és ellenőrzése után adjuk át szállításra. Minden csomagot úgy készítünk elő, hogy a készülék biztonságosan érkezzen meg.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>Várható kézbesítés: 1-3 munkanap</li><li>Személyes átvétel: előzetes egyeztetés alapján</li><li>Csomagátvételkor kérjük, ellenőrizd a külső sértetlenséget.</li><li>A pontos szállítási díjak a végleges futárszolgálati beállítás után frissülnek.</li></ul><!-- /wp:list -->',
        ],
        'aszf' => [
            'title' => 'ÁSZF',
            'legacy_slug' => 'altalanos-szerzodesi-feltetelek',
            'content' => '<!-- wp:heading --><h2>Általános Szerződési Feltételek</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Ez az oldal a webshop indulásához előkészített, szerkeszthető ÁSZF tartalom. A végleges jogi szöveget indulás előtt szakmai és jogi ellenőrzés után kell rögzíteni.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>A webshop használt Apple készülékeket értékesít.</li><li>Minden készülék egyedi termék, saját állapot-, tárhely-, szín-, akkumulátor- és garanciaadatokkal.</li><li>A rendelés végleges adatai a kosárban és a pénztár oldalon kerülnek rögzítésre.</li><li>Az árak forintban értendők, a részletes fizetési és szállítási feltételek később pontosíthatók.</li></ul><!-- /wp:list -->',
        ],
        'adatvedelem' => [
            'title' => 'Adatvédelem',
            'legacy_slug' => 'adatvedelmi-tajekoztato',
            'content' => '<!-- wp:heading --><h2>Adatvédelem</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Ez egy indulás előtti, szerkeszthető adatvédelmi tájékoztató minta. A végleges dokumentumhoz pontosítani kell az adatkezelőt, a használt szolgáltatókat és az adatkezelési célokat.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>Kapcsolattartási adatok kezelése</li><li>Megrendelési és számlázási adatok kezelése</li><li>Szállítási adatok továbbítása a kézbesítéshez</li><li>Webshop működéséhez szükséges technikai adatok kezelése</li></ul><!-- /wp:list -->',
        ],
        'garancia' => [
            'title' => 'Garancia',
            'content' => '<!-- wp:heading --><h2>Garancia</h2><!-- /wp:heading --><!-- wp:paragraph --><p>A garancia időtartama termékenként eltérhet, ezért minden készülék adatlapján külön jelenik meg. A pontos garanciális feltételeket az indulás előtt véglegesíteni kell.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>A garancia hossza a termékoldalon látható.</li><li>Garanciális ügyintézéshez a rendelési azonosító szükséges.</li><li>A belső azonosító kizárólag admin célra szolgál, nem jelenik meg a vásárlói felületen.</li><li>A garancia nem helyettesíti a készülék állapotának terméklapon szereplő leírását.</li></ul><!-- /wp:list -->',
        ],
        'visszakuldes' => [
            'title' => 'Visszaküldés',
            'legacy_slug' => 'visszakuldes-es-elallas',
            'content' => '<!-- wp:heading --><h2>Visszaküldés</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Ez az oldal a visszaküldési és elállási folyamat indulás előtti, szerkeszthető mintája. A végleges határidőket és feltételeket jogi ellenőrzés után kell feltölteni.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>Visszaküldés előtt kérjük, vedd fel velünk a kapcsolatot.</li><li>A készüléket biztonságosan, lehetőség szerint eredeti vagy megfelelő védőcsomagolásban kell visszaküldeni.</li><li>A visszaküldött termék állapotát beérkezéskor ellenőrizzük.</li><li>A pontos visszatérítési folyamat és határidő később véglegesítendő.</li></ul><!-- /wp:list -->',
        ],
    ];
}

function appleklinika_info_page_url(string $slug): string
{
    $page = get_page_by_path($slug);

    if ($page instanceof WP_Post) {
        return home_url('/?pagename=' . $page->post_name);
    }

    return home_url('/?pagename=' . trim($slug, '/'));
}

function appleklinika_render_footer(): void
{
    $infoLinks = [
        'ÁSZF' => 'aszf',
        'Adatvédelem' => 'adatvedelem',
        'Szállítás' => 'szallitas',
        'Kapcsolat' => 'kapcsolat',
        'Garancia' => 'garancia',
        'Visszaküldés' => 'visszakuldes',
    ];
    ?>
    <div class="ak-footer-shell">
        <div class="ak-footer-brand">
            <h2>Appleklinika</h2>
            <p>Ellenőrzött használt Apple készülékek, átlátható termékadatokkal.</p>
        </div>
        <nav class="ak-footer-nav" aria-label="Webshop">
            <h3>Webshop</h3>
            <a href="<?php echo esc_url(appleklinika_shop_url()); ?>">Termékek</a>
            <a href="<?php echo esc_url(appleklinika_cart_url()); ?>">Kosár</a>
            <a href="<?php echo esc_url(appleklinika_account_url()); ?>">Fiókom</a>
        </nav>
        <nav class="ak-footer-nav" aria-label="Információk">
            <h3>Információk</h3>
            <?php foreach ($infoLinks as $label => $slug) : ?>
                <a href="<?php echo esc_url(appleklinika_info_page_url($slug)); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php
}

function appleklinika_render_cart_link(): void
{
    $count = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    ?>
    <a class="ak-header-pill ak-cart-link" href="<?php echo esc_url(appleklinika_cart_url()); ?>">
        <?php appleklinika_render_header_icon('cart'); ?>
        <span class="ak-cart-label">Kosár</span>
        <span class="ak-cart-count"><?php echo esc_html((string) $count); ?></span>
    </a>
    <?php
}

function appleklinika_render_homepage(): void
{
    $trustTiles = appleklinika_homepage_trust_tiles();
    ?>
    <main class="ak-home" id="wp--skip-link--target">
        <section class="ak-hero" aria-label="Appleklinika webshop">
            <div class="ak-hero__content">
                <span class="ak-kicker">Ellenőrzött használt Apple készülékek</span>
                <h1>Ellenőrzött használt Apple készülékek, garanciával.</h1>
                <p>Átlátható állapot, valós termékadatok és szegedi háttér. Válogass iPhone, MacBook, iPad és Apple Watch készülékeink között.</p>
                <div class="ak-hero__actions">
                    <a class="ak-button ak-button--primary" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Termékek megtekintése <span aria-hidden="true">→</span></a>
                    <a class="ak-button ak-button--secondary" href="<?php echo esc_url(appleklinika_info_page_url('kapcsolat')); ?>">Kapcsolat <span aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="ak-hero__tiles" aria-label="Vásárlási előnyök">
                <?php foreach ($trustTiles as $tile) : ?>
                    <article class="ak-home-trust-tile">
                        <span class="ak-home-trust-tile__icon" aria-hidden="true"><?php echo esc_html($tile['icon']); ?></span>
                        <h2><?php echo esc_html($tile['title']); ?></h2>
                        <p><?php echo esc_html($tile['text']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ak-home-showcase" aria-labelledby="ak-home-showcase-title">
            <div class="ak-home-section-head">
                <span class="ak-kicker">Akciós és friss készülékek</span>
                <div>
                    <h2 id="ak-home-showcase-title">Kiemelt Apple ajánlatok</h2>
                    <p>Válogatott ajánlatok élő WooCommerce termékekből: először az akciós készülékek, ha pedig nincs elég, a legfrissebb termékek jelennek meg.</p>
                </div>
                <a href="<?php echo esc_url(appleklinika_shop_url()); ?>">Összes termék</a>
            </div>
            <?php appleklinika_render_homepage_product_section('home_featured', appleklinika_home_featured_product_limit(), 'ak-home-products--showcase'); ?>
        </section>

        <section class="ak-home-trust" aria-labelledby="ak-home-trust-title">
            <div class="ak-home-section-head">
                <span class="ak-kicker">Miért Apple Klinika?</span>
                <div>
                    <h2 id="ak-home-trust-title">Használt Apple készülék, felesleges bizonytalanság nélkül.</h2>
                    <p>A vásárlás alapja nálunk a látható állapot, a valós termékadat és az egyértelmű garancia.</p>
                </div>
            </div>
            <div class="ak-home-trust-list">
                <?php foreach ($trustTiles as $tile) : ?>
                    <article class="ak-home-trust-item">
                        <span aria-hidden="true"><?php echo esc_html($tile['icon']); ?></span>
                        <strong><?php echo esc_html($tile['title']); ?></strong>
                        <p><?php echo esc_html($tile['text']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <?php
}

/**
 * @return array<int, array{icon: string, title: string, text: string}>
 */
function appleklinika_homepage_trust_tiles(): array
{
    return [
        [
            'icon' => '✓',
            'title' => 'Ellenőrzött adatok',
            'text' => 'Állapot, tárhely, szín és garancia termékszinten kezelve.',
        ],
        [
            'icon' => '◆',
            'title' => 'Garancia',
            'text' => 'A garanciaidő nem eldugott apróbetű, hanem látható termékadat.',
        ],
        [
            'icon' => '◎',
            'title' => 'Átlátható állapot',
            'text' => 'Grade, akkumulátoradat és készülékleírás egy helyen.',
        ],
        [
            'icon' => '⌂',
            'title' => 'Szegedi háttér',
            'text' => 'Lokális szaküzlet logika, nem névtelen piactér hangulat.',
        ],
    ];
}

function appleklinika_render_featured_products(): void
{
    appleklinika_render_homepage_product_section('home_featured', appleklinika_home_featured_product_limit());
}

function appleklinika_render_homepage_product_section(string $source, int $limit, string $className = ''): void
{
    if (! function_exists('wc_get_products')) {
        echo '<p class="ak-empty">A WooCommerce még nem érhető el.</p>';
        return;
    }

    $products = appleklinika_homepage_products($source, $limit);

    if ($products === []) {
        echo '<p class="ak-empty">Még nincs megjeleníthető termék.</p>';
        return;
    }

    $classes = trim('ak-home-products wc-block-product-template__responsive columns-3 wc-block-product-template wp-block-woocommerce-product-template is-layout-flow wp-block-woocommerce-product-template-is-layout-flow ' . $className);
    echo '<div class="ak-home-products-shell woocommerce">';
    echo '<ul class="' . esc_attr($classes) . '">';

    foreach ($products as $product) {
        if (! $product instanceof WC_Product) {
            continue;
        }

        echo '<li class="' . esc_attr(implode(' ', appleklinika_product_card_container_classes($product))) . '">';
        appleklinika_render_product_card($product, 'home');
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

/**
 * @return array<int, string>
 */
function appleklinika_product_card_container_classes(WC_Product $product): array
{
    if (function_exists('wc_get_product_class')) {
        $classes = wc_get_product_class(['wc-block-product'], $product);
    } else {
        $classes = ['product', 'wc-block-product', 'post-' . $product->get_id()];
    }

    $classes[] = 'wc-block-product';

    return array_values(array_unique(array_filter($classes)));
}

/**
 * @return array<int, WC_Product>
 */
function appleklinika_homepage_products(string $source, int $limit): array
{
    if ($source === 'home_featured') {
        $selectedProducts = appleklinika_homepage_selected_products($limit);

        if ($selectedProducts !== []) {
            return $selectedProducts;
        }

        $featuredProducts = appleklinika_homepage_featured_products($limit);
        $usedIds = appleklinika_product_ids_from_products($featuredProducts);

        if (count($featuredProducts) >= $limit) {
            return $featuredProducts;
        }

        $saleProducts = appleklinika_homepage_sale_products($limit - count($featuredProducts), $usedIds);
        $fallbackProducts = array_merge($featuredProducts, $saleProducts);
        $usedIds = appleklinika_product_ids_from_products($fallbackProducts);

        if (count($fallbackProducts) >= $limit) {
            return array_slice($fallbackProducts, 0, $limit);
        }

        return array_slice(array_merge($fallbackProducts, appleklinika_homepage_latest_products($limit - count($fallbackProducts), $usedIds)), 0, $limit);
    }

    if ($source === 'sale') {
        $saleProducts = appleklinika_homepage_sale_products($limit);

        if ($saleProducts !== []) {
            return $saleProducts;
        }

        return appleklinika_homepage_latest_products($limit);
    }

    if ($source === 'featured') {
        $featuredProducts = appleklinika_homepage_featured_products($limit);

        if ($featuredProducts !== []) {
            return $featuredProducts;
        }
    }

    return appleklinika_homepage_latest_products($limit);
}

/**
 * @return array<int, WC_Product>
 */
function appleklinika_homepage_selected_products(int $limit): array
{
    $selectedIds = array_slice(appleklinika_home_featured_product_ids(), 0, max(0, $limit));

    if ($selectedIds === []) {
        return [];
    }

    return wc_get_products([
        'status' => 'publish',
        'limit' => $limit,
        'include' => $selectedIds,
        'orderby' => 'include',
    ]);
}

/**
 * @return array<int, WC_Product>
 */
function appleklinika_homepage_featured_products(int $limit): array
{
    if ($limit <= 0) {
        return [];
    }

    return wc_get_products([
        'status' => 'publish',
        'limit' => $limit,
        'featured' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

/**
 * @return array<int, WC_Product>
 */
function appleklinika_homepage_sale_products(int $limit, array $excludeIds = []): array
{
    if ($limit <= 0 || ! function_exists('wc_get_product_ids_on_sale')) {
        return [];
    }

    $saleIds = array_values(array_diff(array_filter(array_map('absint', wc_get_product_ids_on_sale())), $excludeIds));

    if ($saleIds === []) {
        return [];
    }

    $saleProducts = wc_get_products([
        'status' => 'publish',
        'limit' => $limit,
        'include' => array_slice($saleIds, 0, $limit),
        'orderby' => 'include',
    ]);

    if (count($saleProducts) >= $limit) {
        return $saleProducts;
    }

    $usedIds = array_values(array_unique(array_merge($excludeIds, appleklinika_product_ids_from_products($saleProducts))));

    return array_slice(array_merge($saleProducts, appleklinika_homepage_latest_products($limit - count($saleProducts), $usedIds)), 0, $limit);
}

/**
 * @param array<int, mixed> $products
 * @return array<int, int>
 */
function appleklinika_product_ids_from_products(array $products): array
{
    return array_values(array_map(static fn (WC_Product $product): int => $product->get_id(), array_filter($products, static fn ($product): bool => $product instanceof WC_Product)));
}

/**
 * @param array<int, int> $excludeIds
 * @return array<int, WC_Product>
 */
function appleklinika_homepage_latest_products(int $limit, array $excludeIds = []): array
{
    if ($limit <= 0) {
        return [];
    }

    return wc_get_products([
        'status' => 'publish',
        'limit' => $limit,
        'exclude' => $excludeIds,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

function appleklinika_render_shop_filters(): void
{
    if (! appleklinika_is_shop_archive_context()) {
        return;
    }

    $action = is_product_taxonomy() ? get_term_link(get_queried_object()) : appleklinika_shop_url();

    if (! is_string($action) || $action === '') {
        $action = appleklinika_shop_url();
    }

    $deviceType = appleklinika_current_shop_device_type();
    $priceRange = appleklinika_price_bounds($deviceType);
    $minPrice = appleklinika_query_value('ak_min_price') ?: (string) $priceRange['min'];
    $maxPrice = appleklinika_query_value('ak_max_price') ?: (string) $priceRange['max'];
    $filters = appleklinika_shop_filter_definitions($deviceType);
    $renderedModelFilter = false;
    ?>
    <form class="ak-shop-filters" method="get" action="<?php echo esc_url($action); ?>" aria-label="Termékszűrők">
        <div class="ak-filter-heading">
            <strong>Szűrők</strong>
            <a class="ak-shop-filters__reset" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Szűrők törlése</a>
        </div>
        <?php if (isset($filters['ak_model'])) : ?>
            <?php appleklinika_render_filter_details('ak_model', $filters['ak_model']); ?>
            <?php $renderedModelFilter = true; ?>
        <?php endif; ?>
        <details class="ak-filter-group ak-filter-group--price" open>
            <summary>Ár</summary>
            <div class="ak-price-filter" data-price-filter>
                <div class="ak-price-filter__values">
                    <span data-price-min-label><?php echo esc_html(appleklinika_format_plain_price((float) $minPrice)); ?></span>
                    <span data-price-max-label><?php echo esc_html(appleklinika_format_plain_price((float) $maxPrice)); ?></span>
                </div>
                <label>
                    <span>Minimum</span>
                    <input type="range" min="<?php echo esc_attr((string) $priceRange['min']); ?>" max="<?php echo esc_attr((string) $priceRange['max']); ?>" step="1000" name="ak_min_price" value="<?php echo esc_attr($minPrice); ?>" data-price-min>
                </label>
                <label>
                    <span>Maximum</span>
                    <input type="range" min="<?php echo esc_attr((string) $priceRange['min']); ?>" max="<?php echo esc_attr((string) $priceRange['max']); ?>" step="1000" name="ak_max_price" value="<?php echo esc_attr($maxPrice); ?>" data-price-max>
                </label>
            </div>
        </details>
        <?php foreach ($filters as $filterKey => $filter) : ?>
            <?php if ($filterKey === 'ak_model' && $renderedModelFilter) { continue; } ?>
            <?php appleklinika_render_filter_details($filterKey, $filter); ?>
        <?php endforeach; ?>
        <div class="ak-filter-actions">
            <button type="submit">Szűrés alkalmazása</button>
        </div>
        <input type="hidden" name="ak_type" value="<?php echo esc_attr($deviceType); ?>">
        <?php foreach ($_GET as $key => $value) : ?>
            <?php if (in_array($key, array_merge(['ak_type', 'ak_min_price', 'ak_max_price', 'paged'], appleklinika_shop_filter_query_keys()), true) || is_array($value)) { continue; } ?>
            <input type="hidden" name="<?php echo esc_attr((string) $key); ?>" value="<?php echo esc_attr((string) wp_unslash($value)); ?>">
        <?php endforeach; ?>
    </form>
    <?php
}

function appleklinika_current_shop_device_type(): string
{
    return appleklinika_normalize_shop_device_type(appleklinika_query_value('ak_type') ?: 'iphone');
}

function appleklinika_normalize_shop_device_type(string $type): string
{
    return [
        'iphone' => 'iphone',
        'ipad' => 'ipad',
        'mac' => 'macbook',
        'macbook' => 'macbook',
        'watch' => 'apple_watch',
        'apple_watch' => 'apple_watch',
    ][sanitize_key($type)] ?? 'iphone';
}

function appleklinika_catalog_type_to_shop_type(string $type): string
{
    return [
        'iphone' => 'iphone',
        'ipad' => 'ipad',
        'mac' => 'macbook',
        'watch' => 'apple_watch',
    ][sanitize_key($type)] ?? sanitize_key($type);
}

/**
 * @return array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}>
 */
function appleklinika_device_catalog_entries(): array
{
    if (class_exists('\Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository')) {
        $repository = new \Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository();

        return $repository->all();
    }

    $catalog = get_option('appleklinika_device_catalog');

    return is_array($catalog) ? $catalog : [];
}

/**
 * @return array<int, string>
 */
function appleklinika_device_model_keys_for_type(string $deviceType): array
{
    $deviceType = appleklinika_normalize_shop_device_type($deviceType);
    $keys = [];

    foreach (appleklinika_device_catalog_entries() as $device) {
        if (! is_array($device) || ! isset($device['key'], $device['type'])) {
            continue;
        }

        if (appleklinika_catalog_type_to_shop_type((string) $device['type']) !== $deviceType) {
            continue;
        }

        $keys[] = (string) $device['key'];
    }

    return array_values(array_unique($keys));
}

/**
 * @return array<string, array{label: string, options: array<string, array{label: string, count: int}>, open: bool}>
 */
function appleklinika_shop_filter_definitions(string $deviceType): array
{
    $deviceType = appleklinika_normalize_shop_device_type($deviceType);
    $definitions = [];
    $addFilter = static function (string $key, array $filter) use (&$definitions): void {
        if ($filter['options'] === []) {
            return;
        }

        $definitions[$key] = $filter;
    };

    $addFilter('ak_model', [
        'label' => $deviceType === 'iphone' ? 'Típus' : 'Modell / széria',
        'options' => appleklinika_meta_options_with_counts('_appleklinika_device_model', 'Modell', $deviceType),
        'open' => true,
    ]);

    if ($deviceType === 'iphone') {
        $addFilter('ak_storage', [
            'label' => 'Tárhely',
            'options' => appleklinika_known_options_with_counts('_appleklinika_storage_capacity', appleklinika_storage_filter_labels(), true, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_condition', [
            'label' => 'Állapot',
            'options' => appleklinika_known_options_with_counts('_appleklinika_overall_grade', appleklinika_condition_filter_labels(), false, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_color', [
            'label' => 'Szín',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_color', 'Szín', $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_sim', [
            'label' => 'SIM',
            'options' => appleklinika_known_options_with_counts('_appleklinika_sim_config', appleklinika_sim_filter_labels(), false, $deviceType),
            'open' => false,
        ]);

        return $definitions;
    }

    if ($deviceType === 'ipad') {
        $addFilter('ak_storage', [
            'label' => 'Tárhely',
            'options' => appleklinika_known_options_with_counts('_appleklinika_storage_capacity', appleklinika_storage_filter_labels(), true, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_color', [
            'label' => 'Szín',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_color', 'Szín', $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_connectivity', [
            'label' => 'Kapcsolat',
            'options' => appleklinika_known_options_with_counts('_appleklinika_connectivity', appleklinika_ipad_connectivity_labels(), false, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_condition', [
            'label' => 'Állapot',
            'options' => appleklinika_known_options_with_counts('_appleklinika_overall_grade', appleklinika_condition_filter_labels(), false, $deviceType),
            'open' => false,
        ]);

        return $definitions;
    }

    if ($deviceType === 'macbook') {
        foreach ([
            'ak_screen_size' => ['Kijelzőméret', '_appleklinika_screen_size', appleklinika_screen_size_labels()],
            'ak_chip' => ['Chip', '_appleklinika_processor_chip', appleklinika_processor_chip_labels()],
            'ak_ram' => ['RAM', '_appleklinika_ram_size', appleklinika_ram_size_labels()],
            'ak_storage' => ['Tárhely', '_appleklinika_storage_capacity', appleklinika_storage_filter_labels()],
        ] as $key => $config) {
            $addFilter($key, [
                'label' => $config[0],
                'options' => appleklinika_known_options_with_counts($config[1], $config[2], false, $deviceType),
                'open' => false,
            ]);
        }
        $addFilter('ak_color', [
            'label' => 'Szín',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_color', 'Szín', $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_condition', [
            'label' => 'Állapot',
            'options' => appleklinika_known_options_with_counts('_appleklinika_overall_grade', appleklinika_condition_filter_labels(), false, $deviceType),
            'open' => false,
        ]);

        return $definitions;
    }

    if ($deviceType === 'apple_watch') {
        $addFilter('ak_case_size', [
            'label' => 'Tokméret',
            'options' => appleklinika_known_options_with_counts('_appleklinika_case_size', appleklinika_watch_case_size_labels(), false, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_case_material', [
            'label' => 'Tok anyaga / színe',
            'options' => appleklinika_known_options_with_counts('_appleklinika_case_material', appleklinika_watch_case_material_labels(), false, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_connectivity', [
            'label' => 'Kapcsolat',
            'options' => appleklinika_known_options_with_counts('_appleklinika_connectivity', appleklinika_watch_connectivity_labels(), false, $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_strap', [
            'label' => 'Szíj',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_strap', 'Szíj', $deviceType),
            'open' => false,
        ]);
        $addFilter('ak_condition', [
            'label' => 'Állapot',
            'options' => appleklinika_known_options_with_counts('_appleklinika_overall_grade', appleklinika_condition_filter_labels(), false, $deviceType),
            'open' => false,
        ]);
    }

    return $definitions;
}

/**
 * @return array<string, string>
 */
function appleklinika_shop_filter_query_meta_map(string $deviceType): array
{
    $definitions = [
        'ak_model' => '_appleklinika_device_model',
        'ak_storage' => '_appleklinika_storage_capacity',
        'ak_condition' => '_appleklinika_overall_grade',
        'ak_color' => '_appleklinika_color',
        'ak_sim' => '_appleklinika_sim_config',
        'ak_connectivity' => '_appleklinika_connectivity',
        'ak_screen_size' => '_appleklinika_screen_size',
        'ak_chip' => '_appleklinika_processor_chip',
        'ak_ram' => '_appleklinika_ram_size',
        'ak_case_size' => '_appleklinika_case_size',
        'ak_case_material' => '_appleklinika_case_material',
        'ak_strap' => '_appleklinika_strap',
        'ak_battery' => '_appleklinika_battery_health',
    ];

    return array_intersect_key($definitions, appleklinika_shop_filter_definitions($deviceType));
}

/**
 * @return array<int, string>
 */
function appleklinika_shop_filter_query_keys(): array
{
    return [
        'ak_model',
        'ak_storage',
        'ak_condition',
        'ak_color',
        'ak_sim',
        'ak_connectivity',
        'ak_screen_size',
        'ak_chip',
        'ak_ram',
        'ak_case_size',
        'ak_case_material',
        'ak_strap',
        'ak_battery',
    ];
}

function appleklinika_render_active_filter_chips(): void
{
    if (! appleklinika_is_shop_archive_context()) {
        return;
    }

    $chips = [];
    $filterLabels = appleklinika_shop_filter_query_meta_map(appleklinika_current_shop_device_type());

    foreach ($filterLabels as $queryKey => $metaKey) {
        foreach (appleklinika_query_values($queryKey) as $value) {
            $chips[] = [
                'label' => appleklinika_filter_label($metaKey, $value),
                'url' => appleklinika_filter_chip_remove_url($queryKey, $value),
            ];
        }
    }

    $minPrice = appleklinika_query_value('ak_min_price');
    $maxPrice = appleklinika_query_value('ak_max_price');

    if ($minPrice !== '' || $maxPrice !== '') {
        $chips[] = [
            'label' => trim(
                ($minPrice !== '' ? appleklinika_format_plain_price((float) $minPrice) : '') .
                ' - ' .
                ($maxPrice !== '' ? appleklinika_format_plain_price((float) $maxPrice) : '')
            ),
            'url' => appleklinika_filter_chip_remove_url('ak_price'),
        ];
    }

    if ($chips === []) {
        return;
    }
    ?>
    <nav class="ak-active-filter-chips" aria-label="Aktív szűrők">
        <?php foreach ($chips as $chip) : ?>
            <a href="<?php echo esc_url($chip['url']); ?>" aria-label="<?php echo esc_attr($chip['label'] . ' szűrő eltávolítása'); ?>">
                <span><?php echo esc_html($chip['label']); ?></span>
                <span class="ak-filter-chip__remove" aria-hidden="true">×</span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function appleklinika_filter_chip_remove_url(string $queryKey, string $value = ''): string
{
    $url = remove_query_arg('paged');

    if ($queryKey === 'ak_price') {
        return remove_query_arg(['ak_min_price', 'ak_max_price'], $url);
    }

    $values = appleklinika_query_values($queryKey);
    $remainingValues = array_values(array_filter(
        $values,
        static fn (string $currentValue): bool => $currentValue !== $value
    ));

    $url = remove_query_arg($queryKey, $url);

    if ($remainingValues === []) {
        return $url;
    }

    return add_query_arg([$queryKey => $remainingValues], $url);
}

/**
 * @param array{label: string, options: array<string, array{label: string, count: int}>, open: bool} $filter
 */
function appleklinika_render_filter_details(string $name, array $filter): void
{
    $selected = appleklinika_query_values($name);
    $isOpen = $filter['open'] || $selected !== [];
    ?>
    <details class="ak-filter-group" <?php echo $isOpen ? 'open' : ''; ?>>
        <summary><?php echo esc_html($filter['label']); ?></summary>
        <div class="ak-filter-options">
            <?php if ($filter['options'] === []) : ?>
                <p class="ak-filter-empty">Még nincs elérhető adat.</p>
            <?php else : ?>
                <?php foreach ($filter['options'] as $value => $option) : ?>
                    <?php $count = (int) $option['count']; ?>
                    <label class="ak-filter-check <?php echo $count === 0 ? 'is-disabled' : ''; ?>">
                        <input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr((string) $value); ?>" <?php checked(in_array((string) $value, $selected, true)); ?> <?php disabled($count === 0); ?>>
                        <span class="ak-filter-check__box" aria-hidden="true"></span>
                        <span class="ak-filter-check__label">
                            <?php if ($name === 'ak_color') : ?>
                                <span class="ak-filter-swatch" style="--ak-swatch: <?php echo esc_attr(appleklinika_color_swatch((string) $value)); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html((string) $option['label']); ?>
                        </span>
                        <span class="ak-filter-check__count"><?php echo esc_html((string) $count); ?></span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>
    <?php
}

function appleklinika_price_bounds(string $deviceType = ''): array
{
    global $wpdb;

    $modelKeys = appleklinika_device_model_keys_for_type($deviceType);
    $join = '';
    $where = "WHERE pm.meta_key = '_price'
        AND pm.meta_value != ''
        AND p.post_type = 'product'
        AND p.post_status = 'publish'";
    $params = [];

    if ($deviceType !== '' && $modelKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($modelKeys), '%s'));
        $join = " INNER JOIN {$wpdb->postmeta} ak_device_model ON ak_device_model.post_id = p.ID AND ak_device_model.meta_key = '_appleklinika_device_model'";
        $where .= " AND ak_device_model.meta_value IN ({$placeholders})";
        $params = $modelKeys;
    }

    $sql = "SELECT MIN(CAST(pm.meta_value AS DECIMAL(12,2))) AS min_price, MAX(CAST(pm.meta_value AS DECIMAL(12,2))) AS max_price
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        {$join}
        {$where}";
    $row = $params === []
        ? $wpdb->get_row($sql, ARRAY_A)
        : $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

    $min = isset($row['min_price']) ? (int) floor((float) $row['min_price'] / 1000) * 1000 : 0;
    $max = isset($row['max_price']) ? (int) ceil((float) $row['max_price'] / 1000) * 1000 : 500000;

    if ($max <= $min) {
        $max = $min + 100000;
    }

    return ['min' => max(0, $min), 'max' => max(1000, $max)];
}

/**
 * @return array<string, array{label: string, count: int}>
 */
function appleklinika_meta_options_with_counts(string $metaKey, string $emptyLabel, string $deviceType = ''): array
{
    $counts = appleklinika_meta_counts($metaKey, $deviceType);
    $options = [];

    foreach ($counts as $value => $count) {
        $value = (string) $value;
        $options[$value] = [
            'label' => appleklinika_filter_label($metaKey, $value),
            'count' => $count,
        ];
    }

    return $options ?: [];
}

/**
 * @param array<string, string> $labels
 * @return array<string, array{label: string, count: int}>
 */
function appleklinika_known_options_with_counts(string $metaKey, array $labels, bool $includeEmpty = true, string $deviceType = ''): array
{
    $counts = appleklinika_meta_counts($metaKey, $deviceType);
    $options = [];

    foreach ($labels as $value => $label) {
        if (! $includeEmpty && ! isset($counts[$value])) {
            continue;
        }

        $options[$value] = [
            'label' => $label,
            'count' => $counts[$value] ?? 0,
        ];
    }

    return $options;
}

/**
 * @return array<string, int>
 */
function appleklinika_meta_counts(string $metaKey, string $deviceType = ''): array
{
    global $wpdb;

    $modelKeys = appleklinika_device_model_keys_for_type($deviceType);
    $join = '';
    $where = "WHERE pm.meta_key = %s
        AND pm.meta_value != ''
        AND p.post_type = 'product'
        AND p.post_status = 'publish'";
    $params = [$metaKey];

    if ($deviceType !== '' && $modelKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($modelKeys), '%s'));
        $join = " INNER JOIN {$wpdb->postmeta} ak_device_model ON ak_device_model.post_id = p.ID AND ak_device_model.meta_key = '_appleklinika_device_model'";
        $where .= " AND ak_device_model.meta_value IN ({$placeholders})";
        $params = array_merge($params, $modelKeys);
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.meta_value, COUNT(DISTINCT p.ID) AS product_count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        {$join}
        {$where}
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value ASC",
        $params
    ), ARRAY_A);

    $counts = [];

    foreach ($rows as $row) {
        $counts[(string) $row['meta_value']] = (int) $row['product_count'];
    }

    return $counts;
}

function appleklinika_filter_label(string $metaKey, string $value): string
{
    if ($metaKey === '_appleklinika_device_model') {
        return appleklinika_model_label($value);
    }

    if ($metaKey === '_appleklinika_color') {
        return appleklinika_color_label($value);
    }

    if ($metaKey === '_appleklinika_storage_capacity') {
        return appleklinika_storage_label($value);
    }

    if ($metaKey === '_appleklinika_overall_grade') {
        return appleklinika_grade_label($value);
    }

    if ($metaKey === '_appleklinika_sim_config') {
        return appleklinika_sim_label($value);
    }

    if ($metaKey === '_appleklinika_connectivity') {
        return appleklinika_connectivity_label($value);
    }

    if ($metaKey === '_appleklinika_screen_size') {
        return appleklinika_screen_size_label($value);
    }

    if ($metaKey === '_appleklinika_processor_chip') {
        return appleklinika_processor_chip_label($value);
    }

    if ($metaKey === '_appleklinika_ram_size') {
        return appleklinika_ram_size_label($value);
    }

    if ($metaKey === '_appleklinika_case_size') {
        return appleklinika_watch_case_size_label($value);
    }

    if ($metaKey === '_appleklinika_case_material') {
        return appleklinika_watch_case_material_label($value);
    }

    if ($metaKey === '_appleklinika_battery_health') {
        return $value !== '' ? $value . '%' : '';
    }

    return ucwords(str_replace('_', ' ', $value));
}

/**
 * @return array<string, string>
 */
function appleklinika_storage_filter_labels(): array
{
    return [
        '64_gb' => '64 GB',
        '128_gb' => '128 GB',
        '256_gb' => '256 GB',
        '512_gb' => '512 GB',
        '1_tb' => '1 TB',
        '2_tb' => '2 TB',
        '4_tb' => '4 TB',
        '8_tb' => '8 TB',
    ];
}

/**
 * @return array<string, string>
 */
function appleklinika_sim_filter_labels(): array
{
    return [
        'dual_esim' => 'Dual eSIM',
        'physical_esim' => 'Fizikai + eSIM',
        'dual_physical' => 'Dual fizikai',
    ];
}

/**
 * @return array<string, string>
 */
function appleklinika_condition_filter_labels(): array
{
    return [
        'a_plus' => 'A+',
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
    ];
}

function appleklinika_sim_label(string $value): string
{
    return appleklinika_sim_filter_labels()[$value] ?? ucwords(str_replace('_', ' ', $value));
}

/**
 * @return array<string, string>
 */
function appleklinika_ipad_connectivity_labels(): array
{
    return [
        'wifi' => 'Wi-Fi',
        'wifi_cellular' => 'Wi-Fi + Cellular',
    ];
}

/**
 * @return array<string, string>
 */
function appleklinika_watch_connectivity_labels(): array
{
    return [
        'gps' => 'GPS',
        'gps_cellular' => 'GPS + Cellular',
    ];
}

function appleklinika_connectivity_label(string $value): string
{
    return (appleklinika_ipad_connectivity_labels() + appleklinika_watch_connectivity_labels())[$value] ?? ucwords(str_replace('_', ' ', $value));
}

/**
 * @return array<string, string>
 */
function appleklinika_screen_size_labels(): array
{
    return [
        '13_inch' => '13"',
        '14_inch' => '14"',
        '15_inch' => '15"',
        '16_inch' => '16"',
    ];
}

function appleklinika_screen_size_label(string $value): string
{
    return appleklinika_screen_size_labels()[$value] ?? $value;
}

/**
 * @return array<string, string>
 */
function appleklinika_processor_chip_labels(): array
{
    return [
        'm1' => 'M1',
        'm1_pro' => 'M1 Pro',
        'm1_max' => 'M1 Max',
        'm2' => 'M2',
        'm2_pro' => 'M2 Pro',
        'm2_max' => 'M2 Max',
        'm3' => 'M3',
        'm3_pro' => 'M3 Pro',
        'm3_max' => 'M3 Max',
        'm4' => 'M4',
        'm4_pro' => 'M4 Pro',
        'm4_max' => 'M4 Max',
        'm5' => 'M5',
        'm5_pro' => 'M5 Pro',
        'm5_max' => 'M5 Max',
    ];
}

function appleklinika_processor_chip_label(string $value): string
{
    return appleklinika_processor_chip_labels()[$value] ?? strtoupper($value);
}

/**
 * @return array<string, string>
 */
function appleklinika_ram_size_labels(): array
{
    return [
        '8_gb' => '8 GB',
        '16_gb' => '16 GB',
        '18_gb' => '18 GB',
        '24_gb' => '24 GB',
        '32_gb' => '32 GB',
        '36_gb' => '36 GB',
        '48_gb' => '48 GB',
        '64_gb' => '64 GB',
        '96_gb' => '96 GB',
        '128_gb' => '128 GB',
    ];
}

function appleklinika_ram_size_label(string $value): string
{
    return appleklinika_ram_size_labels()[$value] ?? appleklinika_storage_label($value);
}

/**
 * @return array<string, string>
 */
function appleklinika_watch_case_size_labels(): array
{
    return [
        '40_mm' => '40 mm',
        '41_mm' => '41 mm',
        '42_mm' => '42 mm',
        '44_mm' => '44 mm',
        '45_mm' => '45 mm',
        '46_mm' => '46 mm',
        '49_mm' => '49 mm',
    ];
}

function appleklinika_watch_case_size_label(string $value): string
{
    return appleklinika_watch_case_size_labels()[$value] ?? str_replace('_', ' ', $value);
}

/**
 * @return array<string, string>
 */
function appleklinika_watch_case_material_labels(): array
{
    return [
        'aluminium' => 'Alumínium',
        'stainless_steel' => 'Rozsdamentes acél',
        'titanium' => 'Titán',
    ];
}

function appleklinika_watch_case_material_label(string $value): string
{
    return appleklinika_watch_case_material_labels()[$value] ?? ucwords(str_replace('_', ' ', $value));
}

function appleklinika_product_card_sim_label(string $value): string
{
    if ($value === '' || $value === 'physical_esim') {
        return '';
    }

    return appleklinika_sim_label($value);
}

function appleklinika_product_card_battery_option_label(string $value): string
{
    return [
        'aftermarket_new' => 'Új utángy. akku',
        'factory_new' => 'Új gyári akku',
    ][$value] ?? '';
}

function appleklinika_color_label(string $value): string
{
    foreach (appleklinika_catalog_color_labels() as $key => $label) {
        if ($key === $value) {
            return $label;
        }
    }

    return ucwords(str_replace('_', ' ', $value));
}

/**
 * @return array<string, string>
 */
function appleklinika_catalog_color_labels(): array
{
    $catalog = get_option('appleklinika_device_catalog');
    $labels = [];

    if (! is_array($catalog)) {
        return $labels;
    }

    foreach ($catalog as $device) {
        if (! is_array($device) || ! isset($device['colors']) || ! is_array($device['colors'])) {
            continue;
        }

        foreach ($device['colors'] as $key => $label) {
            $labels[(string) $key] = (string) $label;
        }
    }

    return $labels;
}

function appleklinika_color_swatch(string $value): string
{
    return [
        'black' => '#1f2329',
        'space_black' => '#1b1b1f',
        'graphite' => '#54524f',
        'space_gray' => '#65666a',
        'silver' => '#d9dde2',
        'white' => '#f5f5f3',
        'starlight' => '#f3eadf',
        'gold' => '#ead6b7',
        'light_gold' => '#f0d69a',
        'blue' => '#7aa7d9',
        'sierra_blue' => '#9bb5c9',
        'alpine_green' => '#52695f',
        'green' => '#9fb8a5',
        'midnight_green' => '#46584d',
        'midnight' => '#222832',
        'product_red' => '#bf1d2d',
        'red' => '#bf1d2d',
        'purple' => '#b8a6d9',
        'deep_purple' => '#594f63',
        'pink' => '#f3b7c7',
        'yellow' => '#f5d35f',
        'black_titanium' => '#353638',
        'white_titanium' => '#e7e3dc',
        'blue_titanium' => '#4d5f73',
        'natural_titanium' => '#b8b2a8',
        'desert_titanium' => '#c9a27f',
    ][$value] ?? '#d0d5dd';
}

/**
 * @return array<int, string>
 */
function appleklinika_query_values(string $key): array
{
    if (! isset($_GET[$key])) {
        return [];
    }

    $raw = wp_unslash($_GET[$key]);

    if (! is_array($raw)) {
        $raw = [$raw];
    }

    return array_values(array_filter(array_map(static function ($value): string {
        return sanitize_key((string) $value);
    }, $raw)));
}

function appleklinika_apply_shop_filters(WP_Query $query): void
{
    if (is_admin() || ! $query->is_main_query() || (! $query->is_post_type_archive('product') && ! $query->is_tax(get_object_taxonomies('product')))) {
        return;
    }

    $metaQuery = (array) $query->get('meta_query');
    $deviceType = appleklinika_current_shop_device_type();
    $deviceModelKeys = appleklinika_device_model_keys_for_type($deviceType);

    if ($deviceModelKeys !== []) {
        $metaQuery[] = [
            'key' => '_appleklinika_device_model',
            'value' => $deviceModelKeys,
            'compare' => 'IN',
        ];
    }

    $map = appleklinika_shop_filter_query_meta_map($deviceType);

    foreach ($map as $requestKey => $metaKey) {
        $values = appleklinika_query_values($requestKey);

        if ($values === []) {
            continue;
        }

        $metaQuery[] = [
            'key' => $metaKey,
            'value' => $values,
            'compare' => 'IN',
        ];
    }

    $min = appleklinika_query_value('ak_min_price');
    $max = appleklinika_query_value('ak_max_price');

    if ($min !== '' || $max !== '') {
        $priceFilter = [
            'key' => '_price',
            'type' => 'NUMERIC',
        ];

        if ($min !== '' && $max !== '') {
            $priceFilter['value'] = [(float) $min, (float) $max];
            $priceFilter['compare'] = 'BETWEEN';
        } elseif ($min !== '') {
            $priceFilter['value'] = (float) $min;
            $priceFilter['compare'] = '>=';
        } else {
            $priceFilter['value'] = (float) $max;
            $priceFilter['compare'] = '<=';
        }

        $metaQuery[] = $priceFilter;
    }

    if ($metaQuery !== []) {
        $query->set('meta_query', $metaQuery);
    }
}

/**
 * @param array<string, string> $options
 * @return array<string, string>
 */
function appleklinika_catalog_orderby_options(array $options): array
{
    return [
        'menu_order' => 'Alapértelmezett rendezés',
        'price' => 'Ár szerint növekvő',
        'price-desc' => 'Ár szerint csökkenő',
        'ak_sale_first' => 'Akciós termékek elöl',
    ];
}

/**
 * @param array<string, string> $args
 * @return array<string, string>
 */
function appleklinika_catalog_ordering_args(array $args, string $orderby, string $order): array
{
    if ($orderby !== 'ak_sale_first') {
        return $args;
    }

    return [
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ];
}

/**
 * @param array<string, string> $clauses
 * @return array<string, string>
 */
function appleklinika_sale_first_ordering_clauses(array $clauses, WP_Query $query): array
{
    if (is_admin() || ! $query->is_main_query() || ! appleklinika_is_shop_archive_context()) {
        return $clauses;
    }

    if (sanitize_key((string) ($_GET['orderby'] ?? '')) !== 'ak_sale_first') {
        return $clauses;
    }

    global $wpdb;

    $lookupTable = $wpdb->prefix . 'wc_product_meta_lookup';
    $join = " LEFT JOIN {$lookupTable} ak_price_lookup ON {$wpdb->posts}.ID = ak_price_lookup.product_id ";

    if (! str_contains($clauses['join'] ?? '', 'ak_price_lookup')) {
        $clauses['join'] = ($clauses['join'] ?? '') . $join;
    }

    $existingOrderBy = trim((string) ($clauses['orderby'] ?? ''));
    $clauses['orderby'] = 'ak_price_lookup.onsale DESC' . ($existingOrderBy !== '' ? ', ' . $existingOrderBy : '');

    return $clauses;
}

function appleklinika_render_loop_product_meta(): void
{
    global $product;

    if (! $product instanceof WC_Product) {
        return;
    }

    $bits = array_filter([
        appleklinika_storage_label((string) get_post_meta($product->get_id(), '_appleklinika_storage_capacity', true)),
        appleklinika_grade_label((string) get_post_meta($product->get_id(), '_appleklinika_overall_grade', true)),
        appleklinika_product_card_sim_label((string) get_post_meta($product->get_id(), '_appleklinika_sim_config', true)),
        appleklinika_battery_label((string) get_post_meta($product->get_id(), '_appleklinika_battery_health', true)),
    ]);

    if ($bits === []) {
        return;
    }

    echo '<div class="ak-loop-meta">' . esc_html(implode(' · ', $bits)) . '</div>';
}

function appleklinika_customize_shop_loop_cards(): void
{
    if (! (is_shop() || is_product_taxonomy())) {
        return;
    }

    remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

    add_filter('woocommerce_post_class', 'appleklinika_shop_product_card_class', 10, 2);
    add_action('woocommerce_before_shop_loop_item', 'appleklinika_render_shop_product_card', 10);
}

function appleklinika_replace_shop_no_products_empty_state(): void
{
    if (! function_exists('is_shop') || ! (is_shop() || is_product_taxonomy())) {
        return;
    }

    remove_action('woocommerce_no_products_found', 'wc_no_products_found', 10);
    add_action('woocommerce_no_products_found', 'appleklinika_render_shop_no_products_empty_state', 10);
}

function appleklinika_render_shop_no_products_empty_state(): void
{
    $deviceType = appleklinika_current_shop_device_type();

    appleklinika_render_empty_state([
        'modifier' => 'shop',
        'title' => 'Nincs ilyen szűrésnek megfelelő készülék',
        'text' => 'Módosítsd a szűrőket, vagy nézd meg az összes elérhető készüléket.',
        'primary_label' => 'Szűrők törlése',
        'primary_url' => appleklinika_shop_type_url($deviceType),
        'secondary_label' => 'Összes termék',
        'secondary_url' => appleklinika_shop_url(),
    ]);
}

/**
 * @param array<string, mixed> $block
 * @param WP_Block|null $instance
 */
function appleklinika_render_product_collection_empty_state(string $blockContent, array $block, ?WP_Block $instance = null): string
{
    if (is_admin()) {
        return $blockContent;
    }

    if (function_exists('is_search') && is_search()) {
        ob_start();
        appleklinika_render_search_empty_state();

        return (string) ob_get_clean();
    }

    if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
        ob_start();
        appleklinika_render_shop_no_products_empty_state();

        return (string) ob_get_clean();
    }

    return $blockContent;
}

/**
 * @param array<int, string> $classes
 * @param mixed $product
 * @return array<int, string>
 */
function appleklinika_shop_product_card_class(array $classes, $product): array
{
    if ($product instanceof WC_Product && (is_shop() || is_product_taxonomy())) {
        $classes[] = 'ak-product-card';
    }

    return array_values(array_unique($classes));
}

function appleklinika_render_shop_product_card(): void
{
    global $product;

    if (! $product instanceof WC_Product) {
        return;
    }

    appleklinika_render_product_card($product, 'shop');
}

function appleklinika_render_product_card(WC_Product $product, string $context = 'shop'): void
{
    $productId = $product->get_id();
    $productUrl = get_permalink($productId);
    $metaChips = appleklinika_product_card_meta_chips($productId);
    $savings = appleklinika_product_savings_amount($product);

    $html = '<a class="ak-product-card__inner" href="' . esc_url($productUrl) . '" aria-label="' . esc_attr($product->get_name()) . '">';

    if ($savings > 0) {
        $html .= '<span class="ak-product-card__badge">- ' . esc_html(appleklinika_format_plain_price($savings)) . '</span>';
    }

    $html .= '<div class="ak-product-card__image">' . wp_kses_post($product->get_image('woocommerce_thumbnail')) . '</div>';
    $html .= '<div class="ak-product-card__content">';
    $html .= '<h3 class="ak-product-card__title">' . esc_html($product->get_name()) . '</h3>';

    if ($metaChips !== []) {
        $html .= '<div class="ak-product-card__meta" aria-label="Termékadatok">';

        foreach ($metaChips as $chip) {
            $html .= '<span class="ak-product-card__meta-chip ak-product-card__meta-chip--' . esc_attr($chip['type']) . '">';

            if ($chip['type'] === 'battery') {
                $html .= appleklinika_battery_status_icon();
            }

            $html .= '<span>' . esc_html($chip['label']) . '</span></span>';
        }

        $html .= '</div>';
    }

    $html .= '<div class="ak-product-card__price">';

    if ($product->is_on_sale() && $product->get_regular_price() !== '') {
        $html .= '<span class="ak-product-card__old-price">' . wp_kses_post(wc_price((float) $product->get_regular_price())) . '</span>';
    }

    $html .= '<span class="ak-product-card__current-price">' . wp_kses_post(wc_price((float) $product->get_price())) . '</span>';
    $html .= '</div><span class="ak-product-card__cta">Megnézem</span></div></a>';

    echo $html;
    appleklinika_render_wishlist_button($productId);
}

function appleklinika_render_wishlist_button(int $productId, string $className = ''): void
{
    $isFavorite = is_user_logged_in() && in_array($productId, appleklinika_get_wishlist_product_ids(get_current_user_id()), true);
    $classes = trim('ak-wishlist-button ' . $className . ($isFavorite ? ' is-active' : ''));
    $label = $isFavorite ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez';

    echo '<button type="button" class="' . esc_attr($classes) . '" data-product-id="' . esc_attr((string) $productId) . '" aria-pressed="' . ($isFavorite ? 'true' : 'false') . '" aria-label="' . esc_attr($label) . '">'
        . '<svg class="ak-wishlist-button__icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20.4s-7.2-4.5-9.4-9A5.2 5.2 0 0 1 12 6.2a5.2 5.2 0 0 1 9.4 5.2c-2.2 4.5-9.4 9-9.4 9Z" /></svg>'
        . '</button>';
}

function appleklinika_wishlist_meta_key(): string
{
    return '_appleklinika_wishlist_product_ids';
}

/**
 * @return array<int, int>
 */
function appleklinika_get_wishlist_product_ids(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $rawIds = get_user_meta($userId, appleklinika_wishlist_meta_key(), true);
    if (! is_array($rawIds)) {
        return [];
    }

    $productIds = array_map('absint', $rawIds);
    $productIds = array_filter($productIds, 'appleklinika_is_valid_wishlist_product');

    return array_values(array_unique($productIds));
}

function appleklinika_is_valid_wishlist_product(int $productId): bool
{
    return $productId > 0 && get_post_type($productId) === 'product' && get_post_status($productId) !== false;
}

function appleklinika_handle_wishlist_toggle(): void
{
    check_ajax_referer('appleklinika_wishlist', 'nonce');

    $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (! appleklinika_is_valid_wishlist_product($productId)) {
        wp_send_json_error(['message' => 'Érvénytelen termék.'], 400);
    }

    if (! is_user_logged_in()) {
        wp_send_json_error([
            'message' => 'A kedvencek használatához be kell jelentkezni.',
            'loginUrl' => appleklinika_account_url(),
        ], 401);
    }

    $userId = get_current_user_id();
    $productIds = appleklinika_get_wishlist_product_ids($userId);
    $isFavorite = in_array($productId, $productIds, true);

    if ($isFavorite) {
        $productIds = array_values(array_diff($productIds, [$productId]));
        $isFavorite = false;
    } else {
        $productIds[] = $productId;
        $productIds = array_values(array_unique($productIds));
        $isFavorite = true;
    }

    update_user_meta($userId, appleklinika_wishlist_meta_key(), $productIds);

    wp_send_json_success([
        'productId' => $productId,
        'isFavorite' => $isFavorite,
        'productIds' => $productIds,
    ]);
}

/**
 * @return array<int, array{type: string, label: string}>
 */
function appleklinika_product_card_meta_chips(int $productId): array
{
    $deviceType = appleklinika_product_device_type($productId);

    if ($deviceType === 'ipad') {
        return appleklinika_ipad_product_card_meta_chips($productId);
    }

    if ($deviceType === 'macbook') {
        return appleklinika_macbook_product_card_meta_chips($productId);
    }

    if ($deviceType === 'apple_watch') {
        return appleklinika_watch_product_card_meta_chips($productId);
    }

    $chips = [];

    $storage = appleklinika_storage_label((string) get_post_meta($productId, '_appleklinika_storage_capacity', true));
    if ($storage !== '') {
        $chips[] = ['type' => 'storage', 'label' => $storage];
    }

    $grade = appleklinika_grade_label((string) get_post_meta($productId, '_appleklinika_overall_grade', true));
    if ($grade !== '') {
        $chips[] = ['type' => 'grade', 'label' => 'Grade ' . $grade];
    }

    $batteryOption = appleklinika_product_card_battery_option_label((string) get_post_meta($productId, '_appleklinika_battery_option', true));
    if ($batteryOption !== '') {
        $chips[] = ['type' => 'battery-option', 'label' => $batteryOption];
    }

    $sim = appleklinika_product_card_sim_label((string) get_post_meta($productId, '_appleklinika_sim_config', true));
    if ($sim !== '') {
        $chips[] = ['type' => 'sim', 'label' => $sim];
    }

    $battery = appleklinika_battery_label((string) get_post_meta($productId, '_appleklinika_battery_health', true));
    if ($battery !== '') {
        $chips[] = ['type' => 'battery', 'label' => $battery];
    }

    return $chips;
}

function appleklinika_product_device_type(int $productId): string
{
    $savedType = appleklinika_normalize_shop_device_type((string) get_post_meta($productId, '_appleklinika_device_type', true));
    if ($savedType !== 'iphone' || get_post_meta($productId, '_appleklinika_device_type', true) !== '') {
        return $savedType;
    }

    $model = (string) get_post_meta($productId, '_appleklinika_device_model', true);
    foreach (appleklinika_device_catalog_entries() as $device) {
        if (! is_array($device) || ($device['key'] ?? '') !== $model) {
            continue;
        }

        return appleklinika_catalog_type_to_shop_type((string) ($device['type'] ?? 'iphone'));
    }

    return 'iphone';
}

/**
 * @return array<int, array{type: string, label: string}>
 */
function appleklinika_ipad_product_card_meta_chips(int $productId): array
{
    $chips = [];
    appleklinika_add_product_card_chip($chips, 'storage', appleklinika_storage_label((string) get_post_meta($productId, '_appleklinika_storage_capacity', true)));
    appleklinika_add_product_card_chip($chips, 'grade', appleklinika_grade_label((string) get_post_meta($productId, '_appleklinika_overall_grade', true)), 'Grade ');
    if ((string) get_post_meta($productId, '_appleklinika_connectivity', true) === 'wifi_cellular') {
        appleklinika_add_product_card_chip($chips, 'connectivity', 'Cellular');
    }
    appleklinika_add_product_card_chip($chips, 'battery', appleklinika_battery_label((string) get_post_meta($productId, '_appleklinika_battery_health', true)));

    return $chips;
}

/**
 * @return array<int, array{type: string, label: string}>
 */
function appleklinika_macbook_product_card_meta_chips(int $productId): array
{
    $chips = [];
    appleklinika_add_product_card_chip($chips, 'storage', appleklinika_storage_label((string) get_post_meta($productId, '_appleklinika_storage_capacity', true)));
    appleklinika_add_product_card_chip($chips, 'grade', appleklinika_grade_label((string) get_post_meta($productId, '_appleklinika_overall_grade', true)), 'Grade ');
    appleklinika_add_product_card_chip($chips, 'battery', appleklinika_battery_label((string) get_post_meta($productId, '_appleklinika_battery_health', true)));

    return $chips;
}

/**
 * @return array<int, array{type: string, label: string}>
 */
function appleklinika_watch_product_card_meta_chips(int $productId): array
{
    $chips = [];
    appleklinika_add_product_card_chip($chips, 'storage', appleklinika_storage_label((string) get_post_meta($productId, '_appleklinika_storage_capacity', true)));
    appleklinika_add_product_card_chip($chips, 'grade', appleklinika_grade_label((string) get_post_meta($productId, '_appleklinika_overall_grade', true)), 'Grade ');
    if ((string) get_post_meta($productId, '_appleklinika_connectivity', true) === 'gps_cellular') {
        appleklinika_add_product_card_chip($chips, 'connectivity', 'Cellular');
    }
    appleklinika_add_product_card_chip($chips, 'battery', appleklinika_battery_label((string) get_post_meta($productId, '_appleklinika_battery_health', true)));

    return $chips;
}

/**
 * @param array<int, array{type: string, label: string}> $chips
 */
function appleklinika_add_product_card_chip(array &$chips, string $type, string $label, string $prefix = ''): void
{
    if ($label === '') {
        return;
    }

    $chips[] = [
        'type' => $type,
        'label' => $prefix . $label,
    ];
}

function appleklinika_battery_status_icon(): string
{
    return '<svg class="ak-battery-icon" width="20" height="11" viewBox="0 0 20 11" aria-hidden="true" focusable="false">'
        . '<rect x="1" y="1.5" width="15.5" height="8" rx="2.2" fill="#ffffff" stroke="#1f2937" stroke-width="1.8"/>'
        . '<rect x="3.2" y="3.4" width="10.8" height="4.2" rx="1.2" fill="#1f2937"/>'
        . '<rect x="17.2" y="4" width="1.8" height="3" rx="0.8" fill="#1f2937"/>'
        . '</svg>';
}

function appleklinika_render_loop_product_savings(): void
{
    global $product;

    if (! $product instanceof WC_Product) {
        return;
    }

    appleklinika_render_loop_product_savings_for_product($product);
}

function appleklinika_render_loop_product_savings_for_product(WC_Product $product, string $className = 'ak-loop-savings'): void
{
    $savings = appleklinika_product_savings_amount($product);
    if ($savings <= 0) {
        return;
    }

    echo '<div class="' . esc_attr($className) . '">' . esc_html(appleklinika_format_plain_price($savings)) . ' megtakarítás</div>';
}

function appleklinika_product_savings_amount(WC_Product $product): float
{
    if (! $product->is_on_sale()) {
        return 0.0;
    }

    $regularPrice = (float) $product->get_regular_price();
    $currentPrice = (float) $product->get_price();

    if ($regularPrice <= 0 || $currentPrice <= 0 || $regularPrice <= $currentPrice) {
        return 0.0;
    }

    return $regularPrice - $currentPrice;
}

function appleklinika_loop_view_product_link(string $html, WC_Product $product): string
{
    return '<a class="button ak-loop-view-button" href="' . esc_url(get_permalink($product->get_id())) . '">Megnézem</a>';
}

function appleklinika_render_product_categories(): void
{
    if (! taxonomy_exists('product_cat')) {
        echo '<p class="ak-empty">A termékkategóriák még nem érhetők el.</p>';
        return;
    }

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'number' => 6,
    ]);

    if (is_wp_error($categories) || $categories === []) {
        echo '<p class="ak-empty">Még nincs megjeleníthető termékkategória.</p>';
        return;
    }

    echo '<div class="ak-category-grid">';

    foreach ($categories as $category) {
        if (! $category instanceof WP_Term) {
            continue;
        }

        echo '<a class="ak-category-card" href="' . esc_url(get_term_link($category)) . '">';
        echo '<strong>' . esc_html($category->name) . '</strong>';
        echo '<span>' . esc_html(sprintf('%d termék', (int) $category->count)) . '</span>';
        echo '</a>';
    }

    echo '</div>';
}

function appleklinika_stock_label(WC_Product $product): string
{
    $availability = $product->get_availability();

    if (isset($availability['availability']) && $availability['availability'] !== '') {
        return (string) $availability['availability'];
    }

    return $product->is_in_stock() ? __('In stock', 'woocommerce') : __('Out of stock', 'woocommerce');
}

/**
 * @return array<string, string>
 */
function appleklinika_meta_options(string $metaKey, string $emptyLabel): array
{
    global $wpdb;

    $values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
        $metaKey
    ));

    $options = [];

    foreach ($values as $value) {
        $value = (string) $value;
        $options[$value] = $metaKey === '_appleklinika_device_model'
            ? appleklinika_model_label($value)
            : $value;
    }

    return $options ?: ['' => $emptyLabel];
}

function appleklinika_query_value(string $key): string
{
    if (! isset($_GET[$key]) || is_array($_GET[$key])) {
        return '';
    }

    return sanitize_text_field((string) wp_unslash($_GET[$key]));
}

function appleklinika_model_label(string $model): string
{
    foreach (appleklinika_device_catalog_entries() as $device) {
        if (! is_array($device) || ($device['key'] ?? '') !== $model) {
            continue;
        }

        return (string) ($device['name'] ?? $model);
    }

    return [
        'iphone_13_pro' => 'iPhone 13 Pro',
    ][$model] ?? ucwords(str_replace('_', ' ', $model));
}

function appleklinika_storage_label(string $storage): string
{
    return [
        '64_gb' => '64 GB',
        '128_gb' => '128 GB',
        '256_gb' => '256 GB',
        '512_gb' => '512 GB',
        '1_tb' => '1 TB',
        '2_tb' => '2 TB',
        '4_tb' => '4 TB',
        '8_tb' => '8 TB',
    ][$storage] ?? $storage;
}

function appleklinika_grade_label(string $grade): string
{
    return [
        'a_plus' => 'A+',
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
    ][$grade] ?? $grade;
}

function appleklinika_battery_label(string $health): string
{
    return $health !== '' ? 'Akku ' . $health . '%' : '';
}

function appleklinika_shop_url(): string
{
    if (function_exists('wc_get_page_permalink')) {
        $shopUrl = wc_get_page_permalink('shop');

        if (is_string($shopUrl) && $shopUrl !== '') {
            return $shopUrl;
        }
    }

    return home_url('/?post_type=product');
}

function appleklinika_shop_type_url(string $type): string
{
    return add_query_arg(
        [
            'post_type' => 'product',
            'ak_type' => appleklinika_normalize_shop_device_type($type),
        ],
        home_url('/')
    );
}

function appleklinika_cart_url(): string
{
    if (function_exists('wc_get_cart_url')) {
        return wc_get_cart_url();
    }

    return home_url('/kosar/');
}

function appleklinika_account_url(): string
{
    if (function_exists('wc_get_page_permalink')) {
        $accountUrl = wc_get_page_permalink('myaccount');

        if (is_string($accountUrl) && $accountUrl !== '') {
            return $accountUrl;
        }
    }

    return home_url('/fiokom/');
}

function appleklinika_register_account_endpoints(): void
{
    if (function_exists('add_rewrite_endpoint') && defined('EP_ROOT') && defined('EP_PAGES')) {
        add_rewrite_endpoint('beszamitasaim', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('eladasaim', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('garanciaim', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('visszakuldesek', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('kedvelt-termekek', EP_ROOT | EP_PAGES);
    }

    if (! get_option('appleklinika_account_endpoints_flushed_v2')) {
        flush_rewrite_rules(false);
        update_option('appleklinika_account_endpoints_flushed_v2', '1', false);
    }
}

/**
 * @param array<string, string> $items
 * @return array<string, string>
 */
function appleklinika_add_wishlist_account_menu_item(array $items): array
{
    unset($items['downloads'], $items['edit-address'], $items['payment-methods']);

    $logoutLabel = $items['customer-logout'] ?? 'Kijelentkezés';
    $finalItems = [
        'dashboard' => 'Vezérlőpult',
        'orders' => 'Vásárlásaim',
        'beszamitasaim' => 'Beszámítás',
        'garanciaim' => 'Garanciáim',
        'visszakuldesek' => 'Visszaküldéseim',
        'edit-account' => 'Fiók beállítások',
        'kedvelt-termekek' => 'Kedvelt termékek',
        'customer-logout' => $logoutLabel,
    ];

    return $finalItems;
}

function appleklinika_redirect_account_downloads_endpoint(): void
{
    if (
        ! function_exists('is_account_page')
        || ! function_exists('is_wc_endpoint_url')
        || ! function_exists('wc_get_account_endpoint_url')
        || ! is_account_page()
    ) {
        return;
    }

    if (is_wc_endpoint_url('downloads')) {
        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }

    if (is_wc_endpoint_url('edit-address')) {
        wp_safe_redirect(wc_get_account_endpoint_url('edit-account'));
        exit;
    }

    if (is_wc_endpoint_url('eladasaim') || isset($_GET['eladasaim'])) {
        wp_safe_redirect(wc_get_account_endpoint_url('beszamitasaim'));
        exit;
    }
}

function appleklinika_account_order_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Fizetésre vár',
        'processing' => 'Feldolgozás alatt',
        'completed' => 'Teljesítve',
        'cancelled' => 'Törölve',
        'failed' => 'Sikertelen',
        'refunded' => 'Visszatérítve',
        'on-hold' => 'Várakozik',
        default => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : $status,
    };
}

function appleklinika_account_order_status_class(string $status): string
{
    if ($status === 'completed') {
        return 'is-complete';
    }

    if (in_array($status, ['processing', 'pending', 'on-hold'], true)) {
        return 'is-active';
    }

    if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
        return 'is-muted';
    }

    return 'is-neutral';
}

function appleklinika_account_user_meta(int $userId, string $key, string $default = ''): string
{
    $value = get_user_meta($userId, $key, true);

    return $value === '' ? $default : (string) $value;
}

function appleklinika_account_posted_text(string $key): string
{
    if (! isset($_POST[$key])) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string) $_POST[$key]));
}

function appleklinika_account_posted_email(string $key): string
{
    if (! isset($_POST[$key])) {
        return '';
    }

    return sanitize_email(wp_unslash((string) $_POST[$key]));
}

function appleklinika_account_posted_checkbox(string $key): bool
{
    return isset($_POST[$key]) && appleklinika_checkout_company_enabled(wp_unslash((string) $_POST[$key]));
}

/**
 * @return array<string, string>
 */
function appleklinika_account_country_options(): array
{
    if (function_exists('WC') && WC()->countries) {
        $countries = WC()->countries->get_allowed_countries();

        if (is_array($countries) && $countries !== []) {
            return array_map('strval', $countries);
        }
    }

    return ['HU' => 'Magyarország'];
}

function appleklinika_account_selected_country(int $userId, string $metaKey): string
{
    $country = appleklinika_account_user_meta($userId, $metaKey, 'HU');

    return $country === '' ? 'HU' : $country;
}

/**
 * @return array<string, string>
 */
function appleklinika_account_profile_summary(int $userId): array
{
    $phone = appleklinika_account_user_meta($userId, 'billing_phone');
    if ($phone === '') {
        $phone = appleklinika_account_user_meta($userId, 'shipping_phone');
    }

    $shippingParts = array_filter([
        appleklinika_account_user_meta($userId, 'shipping_postcode'),
        appleklinika_account_user_meta($userId, 'shipping_city'),
        appleklinika_account_user_meta($userId, 'shipping_address_1'),
    ]);

    return [
        'email' => appleklinika_account_user_meta($userId, 'billing_email', wp_get_current_user()->user_email),
        'phone' => $phone,
        'shipping' => implode(' ', $shippingParts),
    ];
}

/**
 * @param WP_Error $errors
 * @param WP_User  $user
 */
function appleklinika_validate_account_details_fields($errors, $user): void
{
    if (! $errors instanceof WP_Error) {
        return;
    }

    $requiredFields = [
        'account_phone' => 'Telefonszám megadása kötelező.',
        'shipping_country' => 'Szállítási ország megadása kötelező.',
        'shipping_postcode' => 'Szállítási irányítószám megadása kötelező.',
        'shipping_city' => 'Szállítási város megadása kötelező.',
        'shipping_address_1' => 'Szállítási cím megadása kötelező.',
        'shipping_phone' => 'Szállítási telefonszám megadása kötelező.',
        'billing_country' => 'Számlázási ország megadása kötelező.',
        'billing_postcode' => 'Számlázási irányítószám megadása kötelező.',
        'billing_city' => 'Számlázási város megadása kötelező.',
        'billing_address_1' => 'Számlázási cím megadása kötelező.',
        'billing_phone' => 'Számlázási telefonszám megadása kötelező.',
        'billing_email' => 'Számlázási e-mail cím megadása kötelező.',
    ];

    foreach ($requiredFields as $fieldKey => $message) {
        if (appleklinika_account_posted_text($fieldKey) === '') {
            $errors->add('appleklinika_' . $fieldKey . '_required', $message);
        }
    }

    $billingEmail = appleklinika_account_posted_email('billing_email');
    if ($billingEmail !== '' && ! is_email($billingEmail)) {
        $errors->add('appleklinika_billing_email_invalid', 'A számlázási e-mail cím formátuma hibás.');
    }

    $isCompany = appleklinika_account_posted_checkbox('ak_billing_is_company');

    if (! $isCompany) {
        if (appleklinika_account_posted_text('billing_first_name') === '') {
            $errors->add('appleklinika_billing_first_name_required', 'Számlázási keresztnév megadása kötelező.');
        }

        if (appleklinika_account_posted_text('billing_last_name') === '') {
            $errors->add('appleklinika_billing_last_name_required', 'Számlázási vezetéknév megadása kötelező.');
        }

        return;
    }

    $companyName = appleklinika_account_posted_text('billing_company');
    $taxNumber = appleklinika_sanitize_checkout_tax_number(appleklinika_account_posted_text('ak_billing_tax_number'));

    if ($companyName === '') {
        $errors->add('appleklinika_billing_company_required', 'Cégnév megadása kötelező, ha cégként vásárolsz.');
    }

    if ($taxNumber === '') {
        $errors->add('appleklinika_billing_tax_number_required', 'Adószám megadása kötelező, ha cégként vásárolsz.');
    } elseif (! appleklinika_valid_hungarian_tax_number($taxNumber)) {
        $errors->add('appleklinika_billing_tax_number_invalid', 'Az adószám formátuma hibás. Példa: 12345678-1-23');
    }
}

function appleklinika_save_account_details_fields(int $userId): void
{
    if ($userId <= 0 || ! current_user_can('edit_user', $userId)) {
        return;
    }

    $accountPhone = appleklinika_account_posted_text('account_phone');
    $billingEmail = appleklinika_account_posted_email('billing_email');
    $isCompany = appleklinika_account_posted_checkbox('ak_billing_is_company');
    $companyName = appleklinika_account_posted_text('billing_company');
    $taxNumber = appleklinika_sanitize_checkout_tax_number(appleklinika_account_posted_text('ak_billing_tax_number'));
    $accountFirstName = appleklinika_account_posted_text('account_first_name');
    $accountLastName = appleklinika_account_posted_text('account_last_name');

    update_user_meta($userId, 'ak_account_phone', $accountPhone);
    update_user_meta($userId, 'billing_phone', appleklinika_account_posted_text('billing_phone') ?: $accountPhone);

    foreach ([
        'shipping_country',
        'shipping_postcode',
        'shipping_city',
        'shipping_address_1',
        'shipping_phone',
        'billing_country',
        'billing_postcode',
        'billing_city',
        'billing_address_1',
        'billing_phone',
    ] as $metaKey) {
        update_user_meta($userId, $metaKey, appleklinika_account_posted_text($metaKey));
    }

    if ($billingEmail !== '') {
        update_user_meta($userId, 'billing_email', $billingEmail);
    }

    foreach ([
        'ak_shipping_house_number',
        'ak_shipping_floor',
        'ak_shipping_staircase',
        'ak_shipping_door',
        'ak_billing_house_number',
        'ak_billing_floor',
        'ak_billing_staircase',
        'ak_billing_door',
    ] as $metaKey) {
        update_user_meta($userId, $metaKey, appleklinika_account_posted_text($metaKey));
    }

    if ($isCompany) {
        update_user_meta($userId, 'appleklinika_company_purchase', '1');
        update_user_meta($userId, 'appleklinika_company_name', $companyName);
        update_user_meta($userId, 'appleklinika_tax_number', $taxNumber);
        update_user_meta($userId, 'ak_billing_is_company', '1');
        update_user_meta($userId, 'ak_billing_tax_number', $taxNumber);
        update_user_meta($userId, 'billing_company', $companyName);
        update_user_meta($userId, 'billing_first_name', $accountFirstName);
        update_user_meta($userId, 'billing_last_name', $accountLastName !== '' ? $accountLastName : $companyName);

        return;
    }

    delete_user_meta($userId, 'appleklinika_company_purchase');
    delete_user_meta($userId, 'appleklinika_company_name');
    delete_user_meta($userId, 'appleklinika_tax_number');
    delete_user_meta($userId, 'ak_billing_is_company');
    delete_user_meta($userId, 'ak_billing_tax_number');
    update_user_meta($userId, 'billing_company', '');
    update_user_meta($userId, 'billing_first_name', appleklinika_account_posted_text('billing_first_name'));
    update_user_meta($userId, 'billing_last_name', appleklinika_account_posted_text('billing_last_name'));
}

function appleklinika_current_account_endpoint_key(): string
{
    if (! function_exists('is_account_page') || ! is_account_page() || ! function_exists('is_wc_endpoint_url')) {
        return 'dashboard';
    }

    $endpoints = [
        'orders',
        'view-order',
        'beszamitasaim',
        'eladasaim',
        'garanciaim',
        'visszakuldesek',
        'edit-account',
        'kedvelt-termekek',
        'edit-address',
        'customer-logout',
    ];

    foreach ($endpoints as $endpoint) {
        if (is_wc_endpoint_url($endpoint) || isset($_GET[$endpoint])) {
            return $endpoint;
        }
    }

    return 'dashboard';
}

function appleklinika_account_page_title(): string
{
    return match (appleklinika_current_account_endpoint_key()) {
        'orders' => 'Vásárlásaim',
        'view-order' => 'Rendelés részletei',
        'beszamitasaim', 'eladasaim' => 'Készülék beszámítás',
        'garanciaim' => 'Garanciáim',
        'visszakuldesek' => 'Visszaküldéseim',
        'edit-account' => 'Fiók beállítások',
        'kedvelt-termekek' => 'Kedvelt termékek',
        'edit-address' => 'Fiók beállítások',
        default => 'Vezérlőpult',
    };
}

function appleklinika_account_breadcrumb_label(): string
{
    return match (appleklinika_current_account_endpoint_key()) {
        'orders' => 'Vásárlásaim',
        'view-order' => 'Rendelés részletei',
        'beszamitasaim', 'eladasaim' => 'Beszámítás',
        'garanciaim' => 'Garanciáim',
        'visszakuldesek' => 'Visszaküldéseim',
        'edit-account' => 'Fiók beállítások',
        'kedvelt-termekek' => 'Kedvelt termékek',
        'edit-address' => 'Fiók beállítások',
        default => 'Vezérlőpult',
    };
}

function appleklinika_account_shop_url(string $type = ''): string
{
    $url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('product') : false;
    if (! is_string($url) || $url === '') {
        $url = home_url('/?post_type=product');
    }

    if ($type === '') {
        return $url;
    }

    return add_query_arg('ak_type', sanitize_key($type), $url);
}

function appleklinika_account_initials(?WP_User $user = null): string
{
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    $name = trim($user->display_name ?: trim($user->first_name . ' ' . $user->last_name));

    if ($name === '') {
        return 'AK';
    }

    $parts = preg_split('/\s+/', $name);
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $toupper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $first = $substr((string) ($parts[0] ?? 'A'), 0, 1);
    $second = count($parts) > 1 ? $substr((string) end($parts), 0, 1) : '';

    return $toupper($first . $second);
}

function appleklinika_account_order_count(int $userId): int
{
    if ($userId <= 0 || ! function_exists('wc_get_orders')) {
        return 0;
    }

    $statuses = function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : [];
    $statuses = array_values(array_diff($statuses, ['wc-checkout-draft']));

    $orders = wc_get_orders([
        'customer_id' => $userId,
        'limit' => -1,
        'return' => 'ids',
        'status' => $statuses,
    ]);

    return is_array($orders) ? count($orders) : 0;
}

function appleklinika_account_wishlist_count(int $userId): int
{
    return $userId > 0 ? count(appleklinika_get_wishlist_product_ids($userId)) : 0;
}

function appleklinika_account_warranty_label(string $warranty): string
{
    return [
        '3_months' => '3 hónap',
        '6_months' => '6 hónap',
        '12_months' => '12 hónap',
        '24_months' => '24 hónap',
        '36_months' => '36 hónap',
    ][$warranty] ?? $warranty;
}

function appleklinika_account_warranty_months(string $warranty): int
{
    if (preg_match('/^(\d+)_months$/', $warranty, $matches) !== 1) {
        return 0;
    }

    return absint($matches[1]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function appleklinika_account_warranty_records(int $userId): array
{
    if ($userId <= 0 || ! function_exists('wc_get_orders')) {
        return [];
    }

    $orders = wc_get_orders([
        'customer_id' => $userId,
        'status' => ['processing', 'completed', 'on-hold'],
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $records = [];
    $now = current_time('timestamp');

    foreach ($orders as $order) {
        if (! $order instanceof WC_Order) {
            continue;
        }

        foreach ($order->get_items() as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            $productId = $product instanceof WC_Product ? $product->get_id() : 0;
            $warranty = $productId > 0 ? (string) get_post_meta($productId, '_appleklinika_warranty_duration', true) : '';

            if ($warranty === '') {
                continue;
            }

            $months = appleklinika_account_warranty_months($warranty);
            $created = $order->get_date_created();
            $endsAt = '';
            $statusLabel = $order->has_status('completed') ? 'Aktív' : 'Feldolgozás alatt';
            $statusClass = $order->has_status('completed') ? 'is-complete' : 'is-active';

            if ($months > 0 && $created instanceof WC_DateTime) {
                $endDate = clone $created;
                $endDate->modify('+' . $months . ' months');
                $endsAt = wc_format_datetime($endDate);

                if ($endDate->getTimestamp() < $now) {
                    $statusLabel = 'Lejárt';
                    $statusClass = 'is-muted';
                }
            }

            $records[] = [
                'order' => $order,
                'product' => $product,
                'name' => $item->get_name(),
                'warranty' => appleklinika_account_warranty_label($warranty),
                'ends_at' => $endsAt,
                'status' => $statusLabel,
                'status_class' => $statusClass,
            ];
        }
    }

    return $records;
}

/**
 * @return array<int, WC_Order>
 */
function appleklinika_account_return_records(int $userId): array
{
    if ($userId <= 0 || ! function_exists('wc_get_orders')) {
        return [];
    }

    return wc_get_orders([
        'customer_id' => $userId,
        'status' => ['refunded'],
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

/**
 * @return array<int, array<string, string>>
 */
function appleklinika_account_sell_records(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $records = get_user_meta($userId, 'appleklinika_buyback_records', true);

    if (! is_array($records)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($record): array {
        if (! is_array($record)) {
            return [];
        }

        $id = sanitize_key((string) ($record['id'] ?? ''));
        $device = sanitize_text_field((string) ($record['device'] ?? ''));

        if ($id === '' || $device === '') {
            return [];
        }

        return [
            'id' => $id,
            'device' => $device,
            'condition' => sanitize_text_field((string) ($record['condition'] ?? '')),
            'battery' => sanitize_text_field((string) ($record['battery'] ?? '')),
            'estimated_offer' => sanitize_text_field((string) ($record['estimated_offer'] ?? '')),
            'final_offer' => sanitize_text_field((string) ($record['final_offer'] ?? '')),
            'status' => sanitize_text_field((string) ($record['status'] ?? '')),
            'created_date' => sanitize_text_field((string) ($record['created_date'] ?? '')),
            'marker' => sanitize_text_field((string) ($record['marker'] ?? '')),
        ];
    }, $records)));
}

/**
 * @return array<int, array{title: string, text: string, url: string, icon: string}>
 */
function appleklinika_account_category_cards(): array
{
    return [
        ['title' => 'iPhone', 'text' => 'Ellenőrzött használt iPhone készülékek.', 'url' => appleklinika_account_shop_url('iphone'), 'icon' => 'iP'],
        ['title' => 'MacBook', 'text' => 'Átlátható állapotú MacBook ajánlatok.', 'url' => appleklinika_account_shop_url('macbook'), 'icon' => 'MB'],
        ['title' => 'iPad', 'text' => 'iPad modellek valós termékadatokkal.', 'url' => appleklinika_account_shop_url('ipad'), 'icon' => 'iP'],
        ['title' => 'Apple Watch', 'text' => 'Apple Watch ajánlatok garanciával.', 'url' => appleklinika_account_shop_url('apple_watch'), 'icon' => 'AW'],
    ];
}

/**
 * @return array<int, array{title: string, text: string}>
 */
function appleklinika_account_trust_items(): array
{
    return [
        ['title' => 'Ellenőrzött készülékek', 'text' => 'Minden terméknél látható állapotadatok.'],
        ['title' => 'Garancia', 'text' => 'A garanciaadat termékszinten jelenik meg.'],
        ['title' => 'Átlátható állapot', 'text' => 'Grade, tárhely, szín és akkumulátor egy helyen.'],
        ['title' => 'Szegedi háttér', 'text' => 'Személyes szakértelem és tiszta kommunikáció.'],
    ];
}

function appleklinika_render_account_category_recommendations(string $title = 'Vásárolj okosan!'): void
{
    ?>
    <section class="ak-account-recommendations" aria-labelledby="ak-account-recommendations-title">
        <h3 id="ak-account-recommendations-title"><?php echo esc_html($title); ?></h3>
        <div class="ak-account-category-grid">
            <?php foreach (appleklinika_account_category_cards() as $card) : ?>
                <a class="ak-account-category-card" href="<?php echo esc_url($card['url']); ?>">
                    <span aria-hidden="true"><?php echo esc_html($card['icon']); ?></span>
                    <strong><?php echo esc_html($card['title']); ?></strong>
                    <small><?php echo esc_html($card['text']); ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function appleklinika_render_account_trust_strip(): void
{
    ?>
    <div class="ak-account-trust-strip" aria-label="Apple Klinika előnyök">
        <?php foreach (appleklinika_account_trust_items() as $item) : ?>
            <div>
                <strong><?php echo esc_html($item['title']); ?></strong>
                <span><?php echo esc_html($item['text']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @param array{title: string, text: string, primary_label?: string, primary_url?: string, secondary_label?: string, secondary_url?: string, recommendations?: bool, trust?: bool} $args
 */
function appleklinika_render_account_empty_state(array $args): void
{
    ?>
    <div class="ak-account-empty">
        <div class="ak-account-empty__copy">
            <h3><?php echo esc_html($args['title']); ?></h3>
            <p><?php echo esc_html($args['text']); ?></p>
        </div>
        <?php if (! empty($args['primary_label']) && ! empty($args['primary_url'])) : ?>
            <div class="ak-account-empty__actions">
                <a class="ak-account-empty__button" href="<?php echo esc_url((string) $args['primary_url']); ?>"><?php echo esc_html((string) $args['primary_label']); ?></a>
                <?php if (! empty($args['secondary_label']) && ! empty($args['secondary_url'])) : ?>
                    <a class="ak-account-empty__link" href="<?php echo esc_url((string) $args['secondary_url']); ?>"><?php echo esc_html((string) $args['secondary_label']); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    if (! empty($args['recommendations'])) {
        appleklinika_render_account_category_recommendations();
    }

    if (! empty($args['trust'])) {
        appleklinika_render_account_trust_strip();
    }
}

function appleklinika_render_sell_account_endpoint(): void
{
    $contactUrl = appleklinika_info_page_url('kapcsolat');
    $records = appleklinika_account_sell_records(get_current_user_id());
    ?>
    <section class="ak-account-page ak-account-sell">
        <header class="ak-account-page__header">
            <p class="ak-account-section-kicker">Beszámítás</p>
            <h2>Készülék beszámítás</h2>
            <?php if ($records === []) : ?>
                <p>Ha később beszámításra vagy eladásra küldesz be készüléket, itt fogod látni az állapotát. Jelenleg nincs beküldött készülék a fiókodban.</p>
            <?php else : ?>
                <p>Itt követheted a beküldött vagy beszámításra rögzített készülékeid állapotát és ajánlati adatait.</p>
            <?php endif; ?>
        </header>
        <?php if ($records === []) : ?>
            <div class="ak-account-steps">
                <article><span>1</span><strong>Add meg a készülék adatait</strong><p>Írd meg, milyen készüléket szeretnél beszámíttatni.</p></article>
                <article><span>2</span><strong>Egyeztetünk az állapotról</strong><p>Átbeszéljük a készülék állapotát és a lehetőségeket.</p></article>
                <article><span>3</span><strong>Jóváírás vagy beszámítás</strong><p>A végleges ajánlat valós állapot alapján készül.</p></article>
            </div>
            <a class="ak-account-primary-action" href="<?php echo esc_url($contactUrl); ?>">Értékbecslés kérése</a>
        <?php else : ?>
            <div class="ak-account-record-list">
                <?php foreach ($records as $record) : ?>
                    <article class="ak-account-record-card" data-buyback-record="<?php echo esc_attr((string) $record['id']); ?>">
                        <div class="ak-account-record-card__thumb" aria-hidden="true">
                            <span><?php echo esc_html(mb_substr((string) $record['device'], 0, 2)); ?></span>
                        </div>
                        <div class="ak-account-record-card__body">
                            <span class="ak-account-order-card__status is-active"><?php echo esc_html((string) $record['status']); ?></span>
                            <h3><?php echo esc_html((string) $record['device']); ?></h3>
                            <p><?php echo esc_html((string) $record['condition']); ?><?php echo $record['battery'] !== '' ? ' · Akku: ' . esc_html((string) $record['battery']) : ''; ?></p>
                            <p>Becsült ajánlat: <?php echo esc_html((string) $record['estimated_offer']); ?> · Végleges ajánlat: <?php echo esc_html((string) $record['final_offer']); ?></p>
                            <p>Létrehozva: <?php echo esc_html((string) $record['created_date']); ?></p>
                        </div>
                        <a class="ak-account-secondary-action" href="<?php echo esc_url($contactUrl); ?>">Egyeztetés</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function appleklinika_render_warranty_account_endpoint(): void
{
    $records = appleklinika_account_warranty_records(get_current_user_id());
    ?>
    <section class="ak-account-page ak-account-warranty">
        <header class="ak-account-page__header">
            <p class="ak-account-section-kicker">Garancia</p>
            <h2>Garanciáim</h2>
            <p>Az Apple Klinikán vásárolt készülékek garanciaadatai itt jelennek meg valós rendelési és termékadatok alapján.</p>
        </header>

        <?php if ($records === []) : ?>
            <?php
            appleklinika_render_account_empty_state([
                'title' => 'Még nincs aktív garanciád.',
                'text' => 'Ha vásárolsz nálunk, a termékhez rögzített garanciaadatok itt jelennek meg.',
                'primary_label' => 'Termékek böngészése',
                'primary_url' => appleklinika_account_shop_url(),
                'recommendations' => true,
                'trust' => true,
            ]);
            ?>
        <?php else : ?>
            <div class="ak-account-record-list">
                <?php foreach ($records as $record) : ?>
                    <?php
                    $order = $record['order'];
                    $product = $record['product'];
                    ?>
                    <article class="ak-account-record-card">
                        <div class="ak-account-record-card__thumb">
                            <?php
                            if ($product instanceof WC_Product) {
                                echo wp_kses_post($product->get_image('woocommerce_thumbnail'));
                            } else {
                                echo wp_kses_post(wc_placeholder_img('woocommerce_thumbnail'));
                            }
                            ?>
                        </div>
                        <div class="ak-account-record-card__body">
                            <span class="ak-account-order-card__status <?php echo esc_attr((string) $record['status_class']); ?>"><?php echo esc_html((string) $record['status']); ?></span>
                            <h3><?php echo esc_html((string) $record['name']); ?></h3>
                            <p>Rendelés #<?php echo esc_html($order instanceof WC_Order ? $order->get_order_number() : ''); ?></p>
                            <p>Garancia: <?php echo esc_html((string) $record['warranty']); ?><?php echo $record['ends_at'] !== '' ? ' · Lejárat: ' . esc_html((string) $record['ends_at']) : ''; ?></p>
                        </div>
                        <?php if ($order instanceof WC_Order) : ?>
                            <a class="ak-account-secondary-action" href="<?php echo esc_url($order->get_view_order_url()); ?>">Részletek</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function appleklinika_render_returns_account_endpoint(): void
{
    $records = appleklinika_account_return_records(get_current_user_id());
    ?>
    <section class="ak-account-page ak-account-returns">
        <header class="ak-account-page__header">
            <p class="ak-account-section-kicker">Visszaküldés</p>
            <h2>Visszaküldéseim</h2>
            <p>Ha egy rendelésnél visszaküldést vagy visszatérítést kezelsz, annak állapota itt jelenik meg valós rendelési adat alapján.</p>
        </header>

        <?php if ($records === []) : ?>
            <?php
            appleklinika_render_account_empty_state([
                'title' => 'Nincs aktív visszaküldésed.',
                'text' => 'Ha visszaküldésre lesz szükség, innen tudsz továbbindulni a feltételekhez és a rendeléseidhez.',
                'primary_label' => 'Visszaküldési feltételek',
                'primary_url' => appleklinika_info_page_url('visszakuldes'),
                'secondary_label' => 'Vásárlásaim megtekintése',
                'secondary_url' => wc_get_account_endpoint_url('orders'),
                'recommendations' => true,
                'trust' => true,
            ]);
            ?>
        <?php else : ?>
            <div class="ak-account-record-list">
                <?php foreach ($records as $order) : ?>
                    <?php if (! $order instanceof WC_Order) { continue; } ?>
                    <?php $firstItem = array_values($order->get_items())[0] ?? null; ?>
                    <article class="ak-account-record-card">
                        <div class="ak-account-record-card__body">
                            <span class="ak-account-order-card__status is-muted">Visszatérítve</span>
                            <h3>Rendelés #<?php echo esc_html($order->get_order_number()); ?></h3>
                            <p><?php echo esc_html($firstItem instanceof WC_Order_Item_Product ? $firstItem->get_name() : 'Rendelés'); ?></p>
                            <?php if ($order->get_date_created()) : ?>
                                <p><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></p>
                            <?php endif; ?>
                        </div>
                        <a class="ak-account-secondary-action" href="<?php echo esc_url($order->get_view_order_url()); ?>">Részletek</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function appleklinika_render_wishlist_account_endpoint(): void
{
    if (! is_user_logged_in()) {
        echo '<div class="ak-account-wishlist"><div class="ak-account-wishlist__empty"><h3>Jelentkezz be</h3><p>A kedvelt termékek megtekintéséhez be kell jelentkezned.</p></div></div>';
        return;
    }

    $productIds = appleklinika_get_wishlist_product_ids(get_current_user_id());
    $products = [];

    foreach ($productIds as $productId) {
        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product || $product->get_status() !== 'publish') {
            continue;
        }

        $products[$productId] = $product;
    }

    $shopUrl = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('product') : false;
    if (! is_string($shopUrl) || $shopUrl === '') {
        $shopUrl = home_url('/?post_type=product');
    }
    ?>
    <section class="ak-account-wishlist ak-account-favorites">
        <header class="ak-account-wishlist__header">
            <p class="ak-account-section-kicker">Kedvencek</p>
            <h2>Kedvelt termékek</h2>
            <p>Itt találod azokat a készülékeket, amelyeket a webshopban kedvencnek jelöltél.</p>
        </header>

        <?php if ($products === []) : ?>
            <?php
            appleklinika_render_account_empty_state([
                'title' => 'Még nincsenek kedvelt termékeid.',
                'text' => 'A szív ikonra kattintva elmentheted a későbbre félretett készülékeket.',
                'primary_label' => 'Termékek böngészése',
                'primary_url' => $shopUrl,
                'recommendations' => true,
                'trust' => true,
            ]);
            ?>
        <?php else : ?>
            <div class="ak-account-wishlist__grid">
                <?php foreach ($products as $productId => $product) : ?>
                    <?php
                    $productUrl = get_permalink($productId);
                    $metaChips = appleklinika_product_card_meta_chips($productId);
                    ?>
                    <article class="ak-account-wishlist__item" data-wishlist-item="<?php echo esc_attr((string) $productId); ?>">
                        <a class="ak-account-wishlist__image" href="<?php echo esc_url($productUrl); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>">
                            <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail')); ?>
                        </a>
                        <div class="ak-account-wishlist__body">
                            <h3 class="ak-account-wishlist__title">
                                <a href="<?php echo esc_url($productUrl); ?>"><?php echo esc_html($product->get_name()); ?></a>
                            </h3>
                            <?php if ($metaChips !== []) : ?>
                                <div class="ak-account-wishlist__meta" aria-label="Termékadatok">
                                    <?php foreach ($metaChips as $chip) : ?>
                                        <span class="ak-account-wishlist__chip ak-account-wishlist__chip--<?php echo esc_attr($chip['type']); ?>">
                                            <?php echo esc_html($chip['label']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="ak-account-wishlist__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
                        </div>
                        <div class="ak-account-wishlist__actions">
                            <a class="ak-account-wishlist__link" href="<?php echo esc_url($productUrl); ?>">Megnézem</a>
                            <?php appleklinika_render_wishlist_button($productId, 'ak-wishlist-button--account is-active'); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function appleklinika_register_company_checkout_fields(): void
{
    static $registered = false;

    if ($registered) {
        return;
    }

    if (! function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    $profileFields = [];

    if (is_user_logged_in()) {
        $profileFields[] = [
            'id' => 'appleklinika/save_to_profile',
            'label' => 'Kiegészítő adatok mentése a fiókomba',
            'optionalLabel' => 'Kiegészítő adatok mentése a fiókomba',
            'location' => 'contact',
            'type' => 'checkbox',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_checkbox',
            'show_in_order_confirmation' => false,
        ];
    }

    $fields = array_merge(appleklinika_checkout_address_detail_fields(), $profileFields, [
        [
            'id' => 'appleklinika/company_purchase',
            'label' => 'Cégként vásárolok',
            'optionalLabel' => 'Cégként vásárolok',
            'location' => 'order',
            'type' => 'checkbox',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_checkbox',
            'show_in_order_confirmation' => false,
        ],
        [
            'id' => 'appleklinika/company_name',
            'label' => 'Cégnév',
            'optionalLabel' => 'Cégnév',
            'location' => 'order',
            'type' => 'text',
            'required' => false,
            'attributes' => [
                'autocomplete' => 'organization',
            ],
            'sanitize_callback' => 'appleklinika_sanitize_checkout_text_field',
            'show_in_order_confirmation' => true,
        ],
        [
            'id' => 'appleklinika/tax_number',
            'label' => 'Adószám',
            'optionalLabel' => 'Adószám',
            'location' => 'order',
            'type' => 'text',
            'required' => false,
            'attributes' => [
                'autocomplete' => 'off',
                'pattern' => '\d{8}-\d-\d{2}',
                'title' => 'Példa: 12345678-1-23',
            ],
            'sanitize_callback' => 'appleklinika_sanitize_checkout_tax_number',
            'show_in_order_confirmation' => true,
        ],
    ]);

    foreach ($fields as $field) {
        woocommerce_register_additional_checkout_field($field);
    }

    $registered = true;
}

/**
 * @return array<int, array<string, mixed>>
 */
function appleklinika_checkout_address_detail_fields(): array
{
    return [
        [
            'id' => 'appleklinika/house_number',
            'label' => 'Házszám',
            'optionalLabel' => 'Házszám',
            'location' => 'address',
            'type' => 'text',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_text_field',
            'show_in_order_confirmation' => true,
        ],
        [
            'id' => 'appleklinika/floor',
            'label' => 'Emelet',
            'optionalLabel' => 'Emelet',
            'location' => 'address',
            'type' => 'text',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_text_field',
            'show_in_order_confirmation' => true,
        ],
        [
            'id' => 'appleklinika/staircase',
            'label' => 'Lépcsőház',
            'optionalLabel' => 'Lépcsőház',
            'location' => 'address',
            'type' => 'text',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_text_field',
            'show_in_order_confirmation' => true,
        ],
        [
            'id' => 'appleklinika/door',
            'label' => 'Ajtó',
            'optionalLabel' => 'Ajtó',
            'location' => 'address',
            'type' => 'text',
            'required' => false,
            'sanitize_callback' => 'appleklinika_sanitize_checkout_text_field',
            'show_in_order_confirmation' => true,
        ],
    ];
}

/**
 * @param mixed $value
 * @param array<string, mixed> $field
 */
function appleklinika_sanitize_checkout_checkbox($value, array $field = []): bool
{
    return appleklinika_checkout_company_enabled($value);
}

/**
 * @param mixed $value
 * @param array<string, mixed> $field
 */
function appleklinika_sanitize_checkout_text_field($value, array $field = []): string
{
    return sanitize_text_field((string) $value);
}

/**
 * @param mixed $value
 * @param array<string, mixed> $field
 */
function appleklinika_sanitize_checkout_tax_number($value, array $field = []): string
{
    $taxNumber = preg_replace('/[^\d-]/', '', sanitize_text_field((string) $value));

    return substr((string) $taxNumber, 0, 13);
}

function appleklinika_valid_hungarian_tax_number(string $taxNumber): bool
{
    return preg_match('/^\d{8}-\d-\d{2}$/', $taxNumber) === 1;
}

/**
 * @param array<string, array<string, mixed>> $locale
 * @return array<string, array<string, mixed>>
 */
function appleklinika_checkout_default_locale_overrides(array $locale): array
{
    if (isset($locale['phone'])) {
        $locale['phone']['required'] = true;
        $locale['phone']['hidden'] = false;
        $locale['phone']['label'] = 'Telefonszám';
        $locale['phone']['optionalLabel'] = 'Telefonszám';
    }

    return $locale;
}

/**
 * @param array<string, array<string, array<string, mixed>>> $locale
 * @return array<string, array<string, array<string, mixed>>>
 */
function appleklinika_checkout_country_locale_overrides(array $locale): array
{
    $locale['HU']['state'] = array_merge(
        $locale['HU']['state'] ?? [],
        [
            'required' => false,
            'hidden' => true,
        ]
    );

    $locale['HU']['phone'] = array_merge(
        $locale['HU']['phone'] ?? [],
        [
            'required' => true,
            'hidden' => false,
            'label' => 'Telefonszám',
            'optionalLabel' => 'Telefonszám',
        ]
    );

    return $locale;
}

/**
 * @param mixed $value
 */
function appleklinika_checkout_company_enabled($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

/**
 * @param array<string, mixed> $fields
 */
function appleklinika_validate_company_checkout_fields(WP_Error $errors, array $fields, string $group): void
{
    if ($group !== 'other') {
        return;
    }

    if (! appleklinika_checkout_company_enabled($fields['appleklinika/company_purchase'] ?? false)) {
        return;
    }

    $companyName = trim(appleklinika_sanitize_checkout_text_field($fields['appleklinika/company_name'] ?? ''));
    $taxNumber = trim(appleklinika_sanitize_checkout_tax_number($fields['appleklinika/tax_number'] ?? ''));

    if ($companyName === '') {
        $errors->add(
            'appleklinika_company_name_required',
            'Cégnév megadása kötelező, ha cégként vásárolsz.',
            ['key' => 'appleklinika/company_name']
        );
    }

    if ($taxNumber === '') {
        $errors->add(
            'appleklinika_tax_number_required',
            'Adószám megadása kötelező, ha cégként vásárolsz.',
            ['key' => 'appleklinika/tax_number']
        );
    } elseif (! appleklinika_valid_hungarian_tax_number($taxNumber)) {
        $errors->add(
            'appleklinika_tax_number_invalid',
            'Az adószám formátuma hibás. Példa: 12345678-1-23',
            ['key' => 'appleklinika/tax_number']
        );
    }
}

function appleklinika_company_checkout_default_value($value, string $group, WC_Data $wcObject)
{
    if ($value !== null || $group !== 'other' || ! $wcObject instanceof WC_Customer) {
        return $value;
    }

    $currentFilter = current_filter();
    $metaKey = str_replace('woocommerce_get_default_value_for_appleklinika/', 'appleklinika_', $currentFilter);

    if ($metaKey === 'appleklinika/company_purchase') {
        $metaKey = 'appleklinika_company_purchase';
    }

    $savedValue = $wcObject->get_meta($metaKey, true);

    return $savedValue === '' ? null : $savedValue;
}

function appleklinika_checkout_profile_save_default_value($value, string $group, WC_Data $wcObject): bool
{
    return false;
}

function appleklinika_reset_checkout_profile_save_state(WC_Customer $customer, WP_REST_Request $request): void
{
    if (get_current_user_id() <= 0) {
        return;
    }

    $customer->delete_meta_data('_wc_other/appleklinika/save_to_profile');
}

function appleklinika_checkout_profile_user_id(WC_Order $order): int
{
    $userId = get_current_user_id();

    if ($userId <= 0) {
        return 0;
    }

    $orderUserId = $order->get_user_id();

    return $orderUserId > 0 && $orderUserId !== $userId ? 0 : $userId;
}

function appleklinika_persist_company_checkout_fields(WC_Order $order, WP_REST_Request $request): void
{
    $additionalFields = (array) $request->get_param('additional_fields');
    $userId = appleklinika_checkout_profile_user_id($order);
    $saveToProfile = $userId > 0
        && appleklinika_checkout_company_enabled($additionalFields['appleklinika/save_to_profile'] ?? false);
    $companyPurchase = appleklinika_checkout_company_enabled($additionalFields['appleklinika/company_purchase'] ?? false);
    $companyName = sanitize_text_field((string) ($additionalFields['appleklinika/company_name'] ?? ''));
    $taxNumber = appleklinika_sanitize_checkout_tax_number($additionalFields['appleklinika/tax_number'] ?? '');

    if (! $companyPurchase) {
        appleklinika_clear_company_checkout_meta($order);
        if ($saveToProfile) {
            appleklinika_clear_company_checkout_user_meta($order);
            appleklinika_save_checkout_profile_from_request($order, $request, false, '', '');
        }
        return;
    }

    $order->update_meta_data('appleklinika_company_purchase', '1');
    $order->update_meta_data('appleklinika_company_name', $companyName);
    $order->update_meta_data('appleklinika_tax_number', $taxNumber);
    $order->update_meta_data('_wc_other/appleklinika/company_purchase', '1');
    $order->update_meta_data('_wc_other/appleklinika/company_name', $companyName);
    $order->update_meta_data('_wc_other/appleklinika/tax_number', $taxNumber);

    if ($saveToProfile && $userId > 0) {
        appleklinika_save_checkout_profile_from_request($order, $request, true, $companyName, $taxNumber);
    }
}

function appleklinika_save_checkout_profile_from_request(
    WC_Order $order,
    WP_REST_Request $request,
    bool $companyPurchase,
    string $companyName,
    string $taxNumber
): void {
    $userId = appleklinika_checkout_profile_user_id($order);

    if ($userId <= 0) {
        return;
    }

    $billingAddress = (array) $request->get_param('billing_address');
    $shippingAddress = (array) $request->get_param('shipping_address');

    $addressDetailValues = [
        'ak_shipping_house_number' => $shippingAddress['appleklinika/house_number'] ?? '',
        'ak_shipping_floor' => $shippingAddress['appleklinika/floor'] ?? '',
        'ak_shipping_staircase' => $shippingAddress['appleklinika/staircase'] ?? '',
        'ak_shipping_door' => $shippingAddress['appleklinika/door'] ?? '',
        'ak_billing_house_number' => $billingAddress['appleklinika/house_number'] ?? '',
        'ak_billing_floor' => $billingAddress['appleklinika/floor'] ?? '',
        'ak_billing_staircase' => $billingAddress['appleklinika/staircase'] ?? '',
        'ak_billing_door' => $billingAddress['appleklinika/door'] ?? '',
    ];

    foreach ($addressDetailValues as $metaKey => $value) {
        update_user_meta($userId, $metaKey, sanitize_text_field((string) $value));
    }

    if ($companyPurchase) {
        update_user_meta($userId, 'billing_company', $companyName);
        update_user_meta($userId, 'appleklinika_company_purchase', '1');
        update_user_meta($userId, 'appleklinika_company_name', $companyName);
        update_user_meta($userId, 'appleklinika_tax_number', $taxNumber);
        update_user_meta($userId, 'ak_billing_is_company', '1');
        update_user_meta($userId, 'ak_billing_tax_number', $taxNumber);
    }
}

function appleklinika_clear_company_checkout_meta(WC_Order $order): void
{
    foreach ([
        'appleklinika_company_purchase',
        'appleklinika_company_name',
        'appleklinika_tax_number',
        '_wc_other/appleklinika/company_purchase',
        '_wc_other/appleklinika/company_name',
        '_wc_other/appleklinika/tax_number',
    ] as $metaKey) {
        $order->delete_meta_data($metaKey);
    }
}

function appleklinika_clear_company_checkout_user_meta(WC_Order $order): void
{
    $userId = appleklinika_checkout_profile_user_id($order);

    if ($userId <= 0) {
        return;
    }

    delete_user_meta($userId, 'appleklinika_company_purchase');
    delete_user_meta($userId, 'appleklinika_company_name');
    delete_user_meta($userId, 'appleklinika_tax_number');
    delete_user_meta($userId, 'ak_billing_is_company');
    delete_user_meta($userId, 'ak_billing_tax_number');
    delete_user_meta($userId, 'billing_company');
}

function appleklinika_render_company_order_admin_meta(WC_Order $order): void
{
    if (! appleklinika_checkout_company_enabled($order->get_meta('appleklinika_company_purchase', true))) {
        return;
    }

    $companyName = (string) $order->get_meta('appleklinika_company_name', true);
    $taxNumber = (string) $order->get_meta('appleklinika_tax_number', true);

    if ($companyName === '' && $taxNumber === '') {
        return;
    }
    ?>
    <div class="appleklinika-order-company-meta">
        <h3>Céges vásárlás</h3>
        <?php if ($companyName !== '') : ?>
            <p><strong>Cégnév:</strong> <?php echo esc_html($companyName); ?></p>
        <?php endif; ?>
        <?php if ($taxNumber !== '') : ?>
            <p><strong>Adószám:</strong> <?php echo esc_html($taxNumber); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * @param array<string, string> $address
 * @return array<string, string>
 */
function appleklinika_append_checkout_address_details_to_formatted_address(array $address, WC_Order $order): array
{
    $group = current_filter() === 'woocommerce_order_formatted_billing_address' ? 'billing' : 'shipping';
    $details = appleklinika_checkout_address_detail_display_value($order, $group);

    if ($details === '') {
        return $address;
    }

    $address['address_2'] = trim(implode(', ', array_filter([
        $address['address_2'] ?? '',
        $details,
    ])));

    return $address;
}

function appleklinika_checkout_address_detail_display_value(WC_Order $order, string $group): string
{
    $prefix = $group === 'billing' ? '_wc_billing/' : '_wc_shipping/';
    $parts = [];

    foreach (appleklinika_checkout_address_detail_fields() as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $label = (string) ($field['label'] ?? '');
        $value = trim((string) $order->get_meta($prefix . $fieldId, true));

        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    return implode(', ', $parts);
}
