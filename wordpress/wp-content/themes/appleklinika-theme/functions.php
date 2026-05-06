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
        '0.1.65'
    );

    if (function_exists('is_checkout') && is_checkout()) {
        wp_enqueue_style(
            'appleklinika-checkout-sidebar',
            get_stylesheet_directory_uri() . '/assets/css/checkout-sidebar.css',
            ['appleklinika-theme'],
            '0.1.0'
        );
    }

    wp_enqueue_script(
        'appleklinika-theme',
        get_stylesheet_directory_uri() . '/assets/js/frontend.js',
        [],
        '0.1.65',
        true
    );
});

add_action('init', 'appleklinika_ensure_info_pages');
add_action('admin_post_nopriv_appleklinika_contact_submit', 'appleklinika_handle_contact_submit');
add_action('admin_post_appleklinika_contact_submit', 'appleklinika_handle_contact_submit');
add_filter('wc_get_price_decimals', '__return_zero');
add_filter('wc_get_price_thousand_separator', static fn (): string => ' ');
add_filter('wc_get_price_decimal_separator', static fn (): string => ',');
add_filter('woocommerce_price_format', static fn (): string => '%2$s %1$s');
add_filter('body_class', 'appleklinika_body_classes');
add_filter('the_content', 'appleklinika_replace_cart_page_content', 9);

add_shortcode('appleklinika_homepage', static function (): string {
    ob_start();
    appleklinika_render_homepage();

    return (string) ob_get_clean();
});

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
add_action('woocommerce_after_shop_loop_item_title', 'appleklinika_render_loop_product_meta', 6);
add_filter('woocommerce_loop_add_to_cart_link', 'appleklinika_loop_view_product_link', 10, 2);
add_action('pre_get_posts', 'appleklinika_apply_shop_filters');

function appleklinika_format_plain_price(float $amount): string
{
    return number_format($amount, 0, '', ' ') . ' Ft';
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
            <div class="ak-cart-card">
                <h1>Kosár</h1>
                <p>A kosarad jelenleg üres.</p>
                <a class="ak-button ak-button--primary" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Vásárlás folytatása</a>
            </div>
        </section>
        <?php
        return;
    }
    ?>
    <section class="ak-cart-layout" aria-label="Kosár tartalma">
        <form class="ak-cart-card ak-cart-items-card" action="<?php echo esc_url(appleklinika_cart_url()); ?>" method="post">
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
    $discountTotal = (float) $cart->get_discount_total();
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
            <div class="ak-cart-summary__row">
                <span>Kupon</span>
                <strong>-<?php echo wp_kses_post(wc_price($discountTotal)); ?></strong>
            </div>
        </div>

        <div class="ak-cart-summary__total">
            <span>Végösszeg</span>
            <strong><?php echo wp_kses_post($cart->get_total()); ?></strong>
        </div>
    </div>

    <a class="ak-cart-checkout" href="<?php echo esc_url(wc_get_checkout_url()); ?>">Tovább a pénztárhoz</a>
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
        <a class="ak-header-pill" href="<?php echo esc_url(appleklinika_account_url()); ?>">Fiókom</a>
        <?php appleklinika_render_cart_link(); ?>
    </div>
    <?php
}

function appleklinika_render_header(): void
{
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
        <nav class="ak-category-nav" aria-label="Apple termékkategóriák">
            <a href="<?php echo esc_url(add_query_arg('ak_type', 'iphone', appleklinika_shop_url())); ?>">iPhone</a>
            <a href="<?php echo esc_url(add_query_arg('ak_type', 'macbook', appleklinika_shop_url())); ?>">MacBook</a>
            <a href="<?php echo esc_url(add_query_arg('ak_type', 'ipad', appleklinika_shop_url())); ?>">iPad</a>
            <a href="<?php echo esc_url(add_query_arg('ak_type', 'apple_watch', appleklinika_shop_url())); ?>">Apple Watch</a>
        </nav>
    </div>
    <?php
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
        <span class="ak-cart-label">Kosár</span>
        <span class="ak-cart-count"><?php echo esc_html((string) $count); ?></span>
    </a>
    <?php
}

function appleklinika_render_homepage(): void
{
    ?>
    <main class="ak-home" id="wp--skip-link--target">
        <section class="ak-hero" aria-label="Appleklinika webshop">
            <div class="ak-hero__content">
                <span class="ak-kicker">Ellenőrzött használt Apple készülékek</span>
                <h1>Használt iPhone-ok tiszta adatokkal, garanciával.</h1>
                <p>Válogass olyan készülékek között, ahol az állapot, tárhely, szín, akkumulátor és garancia egyértelműen látható.</p>
                <div class="ak-hero__actions">
                    <a class="ak-button ak-button--primary" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Termékek megtekintése</a>
                    <a class="ak-button ak-button--secondary" href="<?php echo esc_url(appleklinika_info_page_url('kapcsolat')); ?>">Kapcsolat</a>
                </div>
            </div>
            <div class="ak-hero__panel" aria-label="Vásárlási előnyök">
                <div><strong>Garancia</strong><span>Termékenként feltüntetve</span></div>
                <div><strong>Állapot</strong><span>Kézzel ellenőrzött grade</span></div>
                <div><strong>Átvétel</strong><span>Szegeden vagy szállítással</span></div>
            </div>
        </section>

        <section class="ak-section" aria-labelledby="ak-featured-products">
            <div class="ak-section__head">
                <h2 id="ak-featured-products">Kiemelt termékek</h2>
                <a href="<?php echo esc_url(appleklinika_shop_url()); ?>">Összes termék</a>
            </div>
            <?php appleklinika_render_featured_products(); ?>
        </section>

        <section class="ak-section" aria-labelledby="ak-categories">
            <div class="ak-section__head">
                <h2 id="ak-categories">Kategóriák</h2>
            </div>
            <?php appleklinika_render_product_categories(); ?>
        </section>

        <section class="ak-trust" aria-labelledby="ak-trust-title">
            <div>
                <span class="ak-kicker">Miért Appleklinika?</span>
                <h2 id="ak-trust-title">Használt készülék vásárlás, felesleges bizonytalanság nélkül.</h2>
            </div>
            <div class="ak-trust__grid">
                <article><strong>Ellenőrzött adatok</strong><span>A fontos készülékadatok termékszinten kezelhetők.</span></article>
                <article><strong>Átlátható állapot</strong><span>A grade és az akkumulátoradat nem elrejtett technikai részlet.</span></article>
                <article><strong>Garancia</strong><span>A garanciaidő minden készüléknél külön megadható.</span></article>
                <article><strong>Szegedi háttér</strong><span>Nem névtelen piactér, hanem lokális szaküzlet logika.</span></article>
            </div>
        </section>
    </main>
    <?php
}

function appleklinika_render_featured_products(): void
{
    if (! function_exists('wc_get_products')) {
        echo '<p class="ak-empty">A WooCommerce még nem érhető el.</p>';
        return;
    }

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 4,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    if ($products === []) {
        echo '<p class="ak-empty">Még nincs megjeleníthető termék.</p>';
        return;
    }

    echo '<div class="ak-product-grid">';

    foreach ($products as $product) {
        if (! $product instanceof WC_Product) {
            continue;
        }

        echo '<article class="ak-product-card">';
        echo '<a class="ak-product-card__image" href="' . esc_url(get_permalink($product->get_id())) . '">' . wp_kses_post($product->get_image('woocommerce_thumbnail')) . '</a>';
        echo '<div class="ak-product-card__body">';
        echo '<h3><a href="' . esc_url(get_permalink($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></h3>';
        echo '<div class="ak-loop-meta">' . esc_html(implode(' · ', array_filter([
            appleklinika_storage_label((string) get_post_meta($product->get_id(), '_appleklinika_storage_capacity', true)),
            appleklinika_grade_label((string) get_post_meta($product->get_id(), '_appleklinika_overall_grade', true)),
        ]))) . '</div>';
        echo '<div class="ak-product-card__price">' . wp_kses_post($product->get_price_html()) . '</div>';
        echo '<span class="ak-product-card__stock">' . esc_html(appleklinika_stock_label($product)) . '</span>';
        echo '<a class="ak-product-card__button" href="' . esc_url(get_permalink($product->get_id())) . '">Megnézem</a>';
        echo '</div>';
        echo '</article>';
    }

    echo '</div>';
}

function appleklinika_render_shop_filters(): void
{
    if (! is_shop() && ! is_product_taxonomy()) {
        return;
    }

    $action = is_product_taxonomy() ? get_term_link(get_queried_object()) : appleklinika_shop_url();

    if (! is_string($action) || $action === '') {
        $action = appleklinika_shop_url();
    }

    $priceRange = appleklinika_price_bounds();
    $minPrice = appleklinika_query_value('ak_min_price') ?: (string) $priceRange['min'];
    $maxPrice = appleklinika_query_value('ak_max_price') ?: (string) $priceRange['max'];
    $filters = [
        'ak_model' => [
            'label' => 'Típus',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_device_model', 'Modell'),
            'open' => true,
        ],
        'ak_storage' => [
            'label' => 'Tárhely',
            'options' => appleklinika_known_options_with_counts('_appleklinika_storage_capacity', appleklinika_storage_filter_labels()),
            'open' => false,
        ],
        'ak_color' => [
            'label' => 'Szín',
            'options' => appleklinika_meta_options_with_counts('_appleklinika_color', 'Szín'),
            'open' => false,
        ],
        'ak_sim' => [
            'label' => 'SIM',
            'options' => appleklinika_known_options_with_counts('_appleklinika_sim_config', appleklinika_sim_filter_labels(), false),
            'open' => false,
        ],
    ];
    ?>
    <form class="ak-shop-filters" method="get" action="<?php echo esc_url($action); ?>" aria-label="Termékszűrők">
        <div class="ak-filter-heading">
            <strong>Szűrők</strong>
            <a class="ak-shop-filters__reset" href="<?php echo esc_url(appleklinika_shop_url()); ?>">Szűrők törlése</a>
        </div>
        <?php appleklinika_render_filter_details('ak_model', $filters['ak_model']); ?>
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
        <?php appleklinika_render_filter_details('ak_storage', $filters['ak_storage']); ?>
        <?php appleklinika_render_filter_details('ak_color', $filters['ak_color']); ?>
        <?php appleklinika_render_filter_details('ak_sim', $filters['ak_sim']); ?>
        <div class="ak-filter-actions">
            <button type="submit">Szűrés alkalmazása</button>
        </div>
        <?php foreach ($_GET as $key => $value) : ?>
            <?php if (in_array($key, ['ak_type', 'ak_model', 'ak_storage', 'ak_color', 'ak_sim', 'ak_min_price', 'ak_max_price', 'paged'], true) || is_array($value)) { continue; } ?>
            <input type="hidden" name="<?php echo esc_attr((string) $key); ?>" value="<?php echo esc_attr((string) wp_unslash($value)); ?>">
        <?php endforeach; ?>
    </form>
    <?php
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

function appleklinika_price_bounds(): array
{
    global $wpdb;

    $row = $wpdb->get_row(
        "SELECT MIN(CAST(pm.meta_value AS DECIMAL(12,2))) AS min_price, MAX(CAST(pm.meta_value AS DECIMAL(12,2))) AS max_price
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_price'
        AND pm.meta_value != ''
        AND p.post_type = 'product'
        AND p.post_status = 'publish'",
        ARRAY_A
    );

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
function appleklinika_meta_options_with_counts(string $metaKey, string $emptyLabel): array
{
    $counts = appleklinika_meta_counts($metaKey);
    $options = [];

    foreach ($counts as $value => $count) {
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
function appleklinika_known_options_with_counts(string $metaKey, array $labels, bool $includeEmpty = true): array
{
    $counts = appleklinika_meta_counts($metaKey);
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
function appleklinika_meta_counts(string $metaKey): array
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.meta_value, COUNT(DISTINCT p.ID) AS product_count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
        AND pm.meta_value != ''
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value ASC",
        $metaKey
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

function appleklinika_sim_label(string $value): string
{
    return appleklinika_sim_filter_labels()[$value] ?? ucwords(str_replace('_', ' ', $value));
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
    $map = [
        'ak_model' => '_appleklinika_device_model',
        'ak_storage' => '_appleklinika_storage_capacity',
        'ak_color' => '_appleklinika_color',
        'ak_sim' => '_appleklinika_sim_config',
    ];

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

function appleklinika_render_loop_product_meta(): void
{
    global $product;

    if (! $product instanceof WC_Product) {
        return;
    }

    $bits = array_filter([
        appleklinika_storage_label((string) get_post_meta($product->get_id(), '_appleklinika_storage_capacity', true)),
        appleklinika_grade_label((string) get_post_meta($product->get_id(), '_appleklinika_overall_grade', true)),
        appleklinika_sim_label((string) get_post_meta($product->get_id(), '_appleklinika_sim_config', true)),
        appleklinika_battery_label((string) get_post_meta($product->get_id(), '_appleklinika_battery_health', true)),
    ]);

    if ($bits === []) {
        return;
    }

    echo '<div class="ak-loop-meta">' . esc_html(implode(' · ', $bits)) . '</div>';
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
