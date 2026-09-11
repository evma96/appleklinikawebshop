<?php

declare(strict_types=1);

/** Public contact content only. Do not expose the technical WordPress mail recipient. */
function appleklinika_contact_defaults(): array
{
    return [
        'title' => 'Keress minket',
        'intro' => 'Kérdésed van egy készülékről, rendelésről vagy javításról? Írj nekünk, vagy keress fel személyesen Szegeden.',
        'store_name' => 'Apple Klinika',
        'city' => 'Szeged',
        'postcode' => '6720',
        'address' => 'Jósika utca 2–4.',
        // Verified store pin; avoids navigation providers reading the house-number range as 24.
        'navigation_coordinates' => '46.2544892,20.1426876',
        // Existing owner-supplied assets/images/home-service.jpg; confirmed on the store map.
        'phone' => '+36 30 970 6700',
        'email' => '',
        'hours' => '',
        // Copied from the verified Apple Klinika place: Google Maps > Share > Embed a map.
        'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2758.7856352844606!2d20.140112676103616!3d46.25448917109782!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x474489111d25d185%3A0xf8dc8aa6ed1595e7!2sApple%20Klinika%20Szeged%20-%20iPhone%20Szerviz%20%C3%A9s%20Keresked%C3%A9s!5e0!3m2!1shu!2shu!4v1789048358880!5m2!1shu!2shu',
    ];
}

function appleklinika_sanitize_contact_content($input): array
{
    $input = is_array($input) ? $input : [];
    $content = [];
    foreach (appleklinika_contact_defaults() as $key => $default) {
        $value = $input[$key] ?? $default;
        $value = is_scalar($value) ? (string) $value : '';
        if ($key === 'navigation_coordinates') {
            $coordinates = array_map('trim', explode(',', $value));
            $valid = count($coordinates) === 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1]) && abs((float) $coordinates[0]) <= 90 && abs((float) $coordinates[1]) <= 180;
            $content[$key] = $valid ? (string) (float) $coordinates[0] . ',' . (string) (float) $coordinates[1] : $default;
            continue;
        }
        if ($key === 'map_embed') {
            // Accept the copied iframe snippet, but retain only an allowlisted URL.
            if (preg_match('/\bsrc=["\x27]([^"\x27]+)["\x27]/i', $value, $matches)) { $value = $matches[1]; }
            $url = esc_url_raw(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ['https']);
            $parts = wp_parse_url($url);
            parse_str($parts['query'] ?? '', $query);
            $validEmbed = is_string($query['pb'] ?? null) && str_starts_with($query['pb'], '!1m');
            $content[$key] = is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'www.google.com' && ($parts['path'] ?? '') === '/maps/embed' && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']) && $validEmbed ? $url : $default;
            continue;
        }
        $content[$key] = $key === 'email' ? sanitize_email($value) : (in_array($key, ['intro', 'hours'], true) ? sanitize_textarea_field($value) : sanitize_text_field($value));
        if (in_array($key, ['title', 'store_name', 'city', 'address'], true) && $content[$key] === '') {
            $content[$key] = $default;
        }
    }
    return $content;
}

function appleklinika_contact_content(): array
{
    return appleklinika_sanitize_contact_content(get_option('appleklinika_contact_content', []));
}

/** A verified store pin, plus address-driven directions; no raw iframe HTML is stored. */
function appleklinika_contact_map_urls(array $content): array
{
    $query = rawurlencode(str_replace('–', '-', implode(', ', [$content['store_name'], trim($content['postcode'] . ' ' . $content['city']), $content['address']])));
    $coordinates = rawurlencode($content['navigation_coordinates']);
    return [
        'embed' => $content['map_embed'],
        'directions' => 'https://www.google.com/maps/dir/?api=1&destination=' . $query,
        'apple_directions' => 'https://maps.apple.com/directions?destination=' . $coordinates,
        'waze_directions' => 'https://www.waze.com/ul?ll=' . $coordinates . '&navigate=yes',
    ];
}

add_action('admin_menu', static function (): void {
    add_options_page('Apple Klinika kapcsolat', 'Apple Klinika kapcsolat', 'manage_options', 'appleklinika-contact', 'appleklinika_render_contact_settings');
});

add_action('admin_init', static function (): void {
    register_setting('appleklinika_contact_settings', 'appleklinika_contact_content', [
        'type' => 'array',
        'sanitize_callback' => 'appleklinika_sanitize_contact_content',
        'default' => appleklinika_contact_defaults(),
    ]);
});

add_action('add_meta_boxes_page', static function (WP_Post $post): void {
    if ($post->post_name !== 'kapcsolat') { return; }
    add_meta_box('appleklinika-contact-editor', 'Kapcsolat oldal tartalma', static function (): void {
        echo '<p>A Kapcsolat oldal saját sablont használ. A megjelenő szöveget, címet és elérhetőségeket a <a href="' . esc_url(admin_url('options-general.php?page=appleklinika-contact')) . '">Beállítások → Apple Klinika kapcsolat</a> oldalon szerkesztheted. Az alábbi régi oldaltörzs nem jelenik meg.</p>';
    }, 'page', 'normal', 'high');
});

function appleklinika_render_contact_settings(): void
{
    if (! current_user_can('manage_options')) { return; }
    $content = appleklinika_contact_content();
    $labels = ['title' => 'Oldalcím', 'intro' => 'Bevezető szöveg', 'store_name' => 'Üzlet neve', 'city' => 'Település', 'postcode' => 'Irányítószám', 'address' => 'Utca, házszám', 'navigation_coordinates' => 'Navigációs célpont koordinátái', 'phone' => 'Publikus telefonszám', 'email' => 'Publikus e-mail-cím', 'hours' => 'Nyitvatartás', 'map_embed' => 'Google Maps beágyazás'];
    ?>
    <div class="wrap">
        <h1>Apple Klinika kapcsolat</h1>
        <p>A Kapcsolat oldal szövegét és elérhetőségeit itt szerkesztheted. Költözéskor a cím mellett a navigációs koordinátákat és a térkép beágyazását is cseréld a pontos új helyre.</p>
        <p>Csak valós, ellenőrzött adatot adj meg. Az üres telefon-, e-mail- és nyitvatartásmező nem jelenik meg a vásárlóknak. Az űrlap címzettje továbbra is a WordPress általános beállításaiban megadott adminisztrátori e-mail-cím.</p>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('appleklinika_contact_settings'); ?>
            <table class="form-table" role="presentation">
                <?php foreach ($labels as $key => $label) : ?>
                    <tr><th scope="row"><label for="ak-contact-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
                        <?php if (in_array($key, ['intro', 'hours', 'map_embed'], true)) : ?>
                            <textarea class="large-text" rows="3" id="ak-contact-<?php echo esc_attr($key); ?>" name="appleklinika_contact_content[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($content[$key]); ?></textarea>
                            <?php if ($key === 'map_embed') : ?><p class="description">Google Maps → az üzlet adatlapja → Megosztás → Térkép beágyazása → HTML másolása. A teljes kódot ide illesztheted; csak a biztonságos Google-térképhivatkozást tároljuk. API-kulcs nem szükséges.</p><?php endif; ?>
                        <?php else : ?>
                            <input class="regular-text" type="<?php echo $key === 'email' ? 'email' : ($key === 'phone' ? 'tel' : 'text'); ?>" id="ak-contact-<?php echo esc_attr($key); ?>" name="appleklinika_contact_content[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($content[$key]); ?>" <?php echo in_array($key, ['title', 'store_name', 'city', 'address'], true) ? 'required' : ''; ?>>
                            <?php if ($key === 'navigation_coordinates') : ?><p class="description">Az Apple Maps és a Waze ezt a pontos célpontot kapja. Google Mapsben jobb kattintás az üzlet helyére → a koordináták másolása. Formátum: szélesség, hosszúság (például 46.2544892,20.1426876).</p><?php endif; ?>
                        <?php endif; ?>
                    </td></tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button('Kapcsolati adatok mentése'); ?>
        </form>
        <p><a href="<?php echo esc_url(appleklinika_info_page_url('kapcsolat')); ?>">Kapcsolat oldal megtekintése</a></p>
    </div>
    <?php
}

add_action('init', static function (): void {
    register_block_type('appleklinika/contact', ['render_callback' => static function (): string {
        if (! appleklinika_is_contact_page()) { return ''; }
        ob_start();
        appleklinika_render_contact_page();
        return (string) ob_get_clean();
    }]);
});

add_action('wp_enqueue_scripts', static function (): void {
    if (! appleklinika_is_contact_page()) { return; }
    $base = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('appleklinika-contact', $uri . '/assets/css/contact.css', ['appleklinika-theme'], (string) filemtime($base . '/assets/css/contact.css'));
    wp_enqueue_script('appleklinika-contact', $uri . '/assets/js/contact.js', [], (string) filemtime($base . '/assets/js/contact.js'), true);
});

function appleklinika_render_contact_page(): void
{
    $content = appleklinika_contact_content();
    $map = appleklinika_contact_map_urls($content);
    $status = isset($_GET['ak_contact_status']) && is_string($_GET['ak_contact_status']) ? sanitize_key(wp_unslash($_GET['ak_contact_status'])) : '';
    $privacyUrl = get_privacy_policy_url();
    ?>
    <main class="ak-contact" id="wp--skip-link--target">
        <header class="ak-contact__intro">
            <p class="ak-contact__eyebrow"><?php echo esc_html($content['store_name'] . ' · ' . $content['city']); ?></p>
            <h1><?php echo esc_html($content['title']); ?></h1>
            <?php if ($content['intro'] !== '') : ?><p><?php echo nl2br(esc_html($content['intro'])); ?></p><?php endif; ?>
        </header>

        <section class="ak-contact__store" aria-labelledby="ak-contact-store-title">
            <div class="ak-contact__details">
                <span class="ak-contact__pin" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                <p class="ak-contact__eyebrow">Személyesen is várunk</p>
                <h2 id="ak-contact-store-title"><?php echo esc_html($content['store_name']); ?></h2>
                <address><?php echo esc_html(trim($content['postcode'] . ' ' . $content['city'])); ?><br><?php echo esc_html($content['address']); ?></address>
                <?php if ($content['phone'] !== '' || $content['email'] !== '' || $content['hours'] !== '') : ?>
                    <dl class="ak-contact__channels">
                        <?php if ($content['phone'] !== '') : ?><div><dt>Telefon</dt><dd><a href="<?php echo esc_attr('tel:' . preg_replace('/[^+0-9]/', '', $content['phone'])); ?>"><?php echo esc_html($content['phone']); ?></a></dd></div><?php endif; ?>
                        <?php if ($content['email'] !== '') : ?><div><dt>E-mail</dt><dd><a href="<?php echo esc_attr('mailto:' . $content['email']); ?>"><?php echo esc_html($content['email']); ?></a></dd></div><?php endif; ?>
                        <?php if ($content['hours'] !== '') : ?><div><dt>Nyitvatartás</dt><dd><?php echo nl2br(esc_html($content['hours'])); ?></dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
                <div class="ak-contact__actions">
                    <a class="ak-contact__button ak-contact__directions-desktop" href="<?php echo esc_url($map['directions']); ?>" target="_blank" rel="noopener noreferrer">Útvonaltervezés <span aria-hidden="true">↗&#xfe0e;</span><span class="screen-reader-text"> – Google Maps, új lapon</span></a>
                    <details class="ak-contact__directions" data-directions>
                        <summary class="ak-contact__button">Útvonaltervezés <span aria-hidden="true">↗&#xfe0e;</span></summary>
                        <nav class="ak-contact__directions-options" aria-label="Útvonaltervező választása">
                            <p>Megnyitás ezzel</p>
                            <?php foreach (['directions' => 'Google Maps', 'apple_directions' => 'Apple Maps', 'waze_directions' => 'Waze'] as $key => $label) : ?>
                                <a href="<?php echo esc_url($map[$key]); ?>" aria-label="<?php echo esc_attr($label . ' – útvonaltervezés'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?><span aria-hidden="true">↗&#xfe0e;</span><span class="screen-reader-text"> – új lapon vagy az alkalmazásban</span></a>
                            <?php endforeach; ?>
                        </nav>
                    </details>
                    <a class="ak-contact__text-link" href="#ak-contact-form">Üzenetet írok <span aria-hidden="true">↓</span></a>
                </div>
            </div>
            <div class="ak-contact__map">
                <div class="ak-contact__map-canvas" role="region" aria-label="<?php echo esc_attr($content['store_name'] . ' – interaktív térkép'); ?>">
                    <iframe src="<?php echo esc_url($map['embed']); ?>" title="<?php echo esc_attr($content['store_name'] . ' – Google Maps'); ?>" loading="eager" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
            </div>
        </section>

        <section class="ak-contact__message" aria-labelledby="ak-contact-message-title">
            <div class="ak-contact__message-intro">
                <p class="ak-contact__eyebrow">Kezdjük egy üzenettel</p>
                <h2 id="ak-contact-message-title">Írj nekünk</h2>
                <p>Készülékválasztás, rendelés vagy szerviz? Írd meg, miben segíthetünk.</p>
                <div class="ak-contact__hint"><h3>Rendelésről érdeklődsz?</h3><p>Add meg a rendelési számot, hogy könnyebben megtaláljuk a vásárlásodat.</p><a class="ak-contact__text-link" href="<?php echo esc_url(appleklinika_account_url()); ?>">Fiókom megnyitása <span aria-hidden="true">→</span></a></div>
            </div>
            <form id="ak-contact-form" class="ak-contact__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" aria-label="Üzenet küldése">
                <?php if ($status === 'sent') : ?><p class="ak-contact__notice" role="status">Köszönjük, az üzenetküldés sikeres.</p><?php elseif ($status === 'error') : ?><p class="ak-contact__notice ak-contact__notice--error" role="alert">Az üzenetet nem küldtük el. Ellenőrizd a kötelező mezőket, majd próbáld újra.</p><?php elseif ($status === 'delivery-error') : ?><p class="ak-contact__notice ak-contact__notice--error" role="alert">Az üzenetet most nem sikerült elküldeni. Kérjük, próbáld újra később.</p><?php endif; ?>
                <input type="hidden" name="action" value="appleklinika_contact_submit">
                <?php wp_nonce_field('appleklinika_contact_submit', 'appleklinika_contact_nonce'); ?>
                <label class="ak-contact__honeypot" aria-hidden="true">Weboldal<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <p class="ak-contact__required">A csillaggal jelölt mezők kitöltése kötelező.</p>
                <div class="ak-contact__fields">
                    <label>Név *<input type="text" name="contact_name" autocomplete="name" required maxlength="150"></label>
                    <label>E-mail *<input type="email" name="contact_email" autocomplete="email" required maxlength="254"></label>
                    <label class="ak-contact__wide">Telefon <span>(nem kötelező)</span><input type="tel" name="contact_phone" autocomplete="tel" maxlength="40"></label>
                    <label class="ak-contact__wide">Üzenet *<textarea name="contact_message" rows="5" required maxlength="10000"></textarea></label>
                </div>
                <?php if ($privacyUrl !== '') : ?><p class="ak-contact__privacy">Az üzenetben megadott adatok kezeléséről az <a href="<?php echo esc_url($privacyUrl); ?>">Adatkezelési tájékoztatóban</a> olvashatsz.</p><?php endif; ?>
                <button type="submit" class="ak-contact__button">Üzenet küldése <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
    <?php
}
