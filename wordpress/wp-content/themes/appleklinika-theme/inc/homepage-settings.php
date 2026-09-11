<?php

declare(strict_types=1);

/** Content-only defaults; product selection remains the existing WooCommerce flow. */
function appleklinika_home_content_defaults(): array
{
    return [
        'hero_layout' => 'split',
        'hero_items' => [[
            'enabled' => true,
            'eyebrow' => 'Ellenőrzött használt Apple készülékek',
            'title' => "Valós állapot.\nValós adatok.\nGaranciával.",
            'text' => 'Ellenőrzött használt Apple készülékek, átlátható termékadatokkal és személyes segítséggel. Találd meg a hozzád illő készüléket.',
            'image_id' => 0,
            'url' => '',
            'alt' => '',
            'primary_label' => 'Kiemelt ajánlatok',
            'primary_url' => '#ak-home-offers',
            'secondary_label' => 'Így dolgozunk',
            'secondary_url' => '#ak-home-process',
        ]],
        'hero_benefits' => [
            ['icon' => 'check', 'title' => 'Ellenőrzött készülékek', 'text' => 'Állapot és készülékadatok termékenként.'],
            ['icon' => 'shield', 'title' => 'Garanciával', 'text' => 'A részleteket a termékoldalon találod.'],
            ['icon' => 'tools', 'title' => 'Szegedi háttér', 'text' => 'Személyes segítség a választáshoz.'],
        ],
        'category_title' => 'Mit keresel?',
        'category_link_label' => 'Összes termék',
        'categories' => [
            ['title' => 'iPhone', 'text' => 'A mindennapjaidhoz illő iPhone.', 'image_id' => 0, 'url' => appleklinika_shop_type_url('iphone')],
            ['title' => 'MacBook', 'text' => 'Munkához, tanuláshoz, alkotáshoz.', 'image_id' => 0, 'url' => appleklinika_shop_type_url('macbook')],
            ['title' => 'iPad', 'text' => 'Sokoldalú társ, bármerre jársz.', 'image_id' => 0, 'url' => appleklinika_shop_type_url('ipad')],
            ['title' => 'Apple Watch', 'text' => 'A napodhoz igazodó Apple Watch.', 'image_id' => 0, 'url' => appleklinika_shop_type_url('apple_watch')],
        ],
        'featured_title' => 'Kiemelt ajánlatok',
        'featured_text' => 'Nézd meg a jelenleg elérhető készülékeket, és hasonlítsd össze a részleteiket.',
        'featured_link_label' => 'Összes termék',
        'trust_title' => 'Átláthatóság, ami számít',
        'trust_text' => 'A jó döntéshez egyértelmű információ kell. A fontos részleteket a készülék mellett találod.',
        'trust_items' => [
            ['icon' => 'check', 'title' => 'Ellenőrzött állapot', 'text' => 'Az állapotbesorolás segít összehasonlítani a készülékeket.'],
            ['icon' => 'battery', 'title' => 'Akkumulátoradatok', 'text' => 'Az elérhető akkumulátoradatokat a termékoldalon mutatjuk.'],
            ['icon' => 'shield', 'title' => 'Látható garancia', 'text' => 'A készülékhez tartozó garanciaidőt a termékadatok között találod.'],
            ['icon' => 'document', 'title' => 'Átlátható részletek', 'text' => 'Tárhely, szín és készülékleírás egy helyen.'],
            ['icon' => 'tools', 'title' => 'Szaküzleti háttér', 'text' => 'Ha kérdésed van, segítünk eligazodni a készülékek között.'],
        ],
        'process_title' => 'Miért más az Apple Klinika?',
        'process_text' => 'Az átvizsgálástól a termékadatokig a készülék valódi állapotára figyelünk.',
        'process_items' => [
            ['icon' => 'tools', 'title' => 'Bevizsgálás', 'text' => 'Állapotellenőrzés.'],
            ['icon' => 'document', 'title' => 'Adatlap készítés', 'text' => 'Valós adatok, látható állapot.'],
            ['icon' => 'phone', 'title' => 'Tisztítás és felkészítés', 'text' => 'Gondos előkészítés.'],
            ['icon' => 'check', 'title' => 'Minőségellenőrzés', 'text' => 'Utolsó ellenőrzés átadás előtt.'],
            ['icon' => 'truck', 'title' => 'Biztonságos szállítás', 'text' => 'Gondos csomagolás.'],
        ],
        'process_image_id' => 0,
    ];
}

function appleklinika_home_content_list_schema(): array
{
    $infoFields = ['icon' => 'icon', 'title' => 'text', 'text' => 'textarea'];

    return [
        'hero_items' => ['min' => 1, 'max' => 8, 'fields' => ['enabled' => 'boolean', 'eyebrow' => 'text', 'title' => 'textarea', 'text' => 'textarea', 'image_id' => 'image', 'url' => 'url', 'alt' => 'text', 'primary_label' => 'text', 'primary_url' => 'url', 'secondary_label' => 'text', 'secondary_url' => 'url']],
        'hero_benefits' => ['min' => 0, 'max' => 8, 'fields' => $infoFields],
        'categories' => ['min' => 0, 'max' => 12, 'fields' => ['title' => 'text', 'text' => 'textarea', 'image_id' => 'image', 'url' => 'url']],
        'trust_items' => ['min' => 0, 'max' => 12, 'fields' => $infoFields],
        'process_items' => ['min' => 0, 'max' => 10, 'fields' => $infoFields],
    ];
}

function appleklinika_home_content_icons(): array
{
    return ['check' => 'Pipa', 'shield' => 'Pajzs', 'battery' => 'Akkumulátor', 'truck' => 'Szállítás', 'document' => 'Dokumentum', 'tools' => 'Szerszámok', 'return' => 'Visszaküldés', 'phone' => 'Telefon'];
}

/** @param mixed $value */
function appleklinika_home_content_sanitize_field($value, string $type)
{
    if (! is_scalar($value)) {
        $value = '';
    }

    if ($type === 'layout') {
        return in_array($value, ['split', 'artwork'], true) ? $value : 'split';
    }

    if ($type === 'boolean') {
        return in_array($value, [true, 1, '1'], true);
    }

    if ($type === 'image') {
        $id = absint($value);

        return $id > 0 && wp_attachment_is_image($id) ? $id : 0;
    }

    if ($type === 'icon') {
        return array_key_exists((string) $value, appleklinika_home_content_icons()) ? (string) $value : 'check';
    }

    if ($type === 'url') {
        return esc_url_raw((string) $value, ['http', 'https']);
    }

    return $type === 'textarea' ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
}

/** Retain deliberate empty copy/lists; missing keys receive backward-compatible defaults. */
function appleklinika_sanitize_home_content($value): array
{
    $defaults = appleklinika_home_content_defaults();
    $input = is_array($value) ? $value : [];
    $schema = appleklinika_home_content_list_schema();
    $content = [];

    foreach ($defaults as $key => $default) {
        if (! isset($schema[$key])) {
            $type = $key === 'process_image_id' ? 'image' : (substr($key, -5) === '_text' ? 'textarea' : 'text');
            if ($key === 'hero_layout') {
                $type = 'layout';
            }
            $content[$key] = appleklinika_home_content_sanitize_field(array_key_exists($key, $input) ? $input[$key] : $default, $type);
            continue;
        }

        $rows = array_key_exists($key, $input) ? $input[$key] : $default;
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        $rows = array_slice($rows, 0, $schema[$key]['max']);

        if (count($rows) < $schema[$key]['min']) {
            $rows = array_slice($default, 0, $schema[$key]['min']);
        }

        $content[$key] = [];
        foreach ($rows as $index => $row) {
            $clean = [];
            foreach ($schema[$key]['fields'] as $field => $type) {
                $fallback = $default[$index][$field] ?? ($type === 'boolean' ? true : ($type === 'image' ? 0 : ($type === 'icon' ? 'check' : '')));
                $clean[$field] = appleklinika_home_content_sanitize_field(array_key_exists($field, $row) ? $row[$field] : $fallback, $type);
            }
            $content[$key][] = $clean;
        }
    }

    return $content;
}

function appleklinika_home_content(): array
{
    return appleklinika_sanitize_home_content(get_option('appleklinika_home_content', []));
}

function appleklinika_register_homepage_settings_page(): void
{
    add_options_page('Apple Klinika homepage', 'Apple Klinika homepage', 'manage_options', 'appleklinika-homepage', 'appleklinika_render_homepage_settings_page');
}

function appleklinika_register_homepage_settings(): void
{
    register_setting('appleklinika_homepage_settings', 'appleklinika_home_featured_product_ids', ['sanitize_callback' => 'appleklinika_sanitize_home_featured_product_ids', 'default' => []]);
    register_setting('appleklinika_homepage_settings', 'appleklinika_home_featured_product_limit', ['sanitize_callback' => 'appleklinika_sanitize_home_featured_product_limit', 'default' => 6]);
    register_setting('appleklinika_homepage_settings', 'appleklinika_home_content', ['type' => 'array', 'sanitize_callback' => 'appleklinika_sanitize_home_content', 'default' => []]);
}

add_action('admin_enqueue_scripts', 'appleklinika_homepage_admin_assets');

function appleklinika_homepage_admin_assets(string $hook): void
{
    if ($hook !== 'settings_page_appleklinika-homepage' || ! current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_media();
    $directory = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_script('appleklinika-homepage-admin', $uri . '/assets/js/homepage-admin.js', ['media-editor'], (string) filemtime($directory . '/assets/js/homepage-admin.js'), true);
    wp_enqueue_style('appleklinika-homepage-admin', $uri . '/assets/css/homepage-admin.css', [], (string) filemtime($directory . '/assets/css/homepage-admin.css'));
}

function appleklinika_homepage_admin_field(string $key, string $type, string $label, $value, string $list = '', string $index = ''): void
{
    $name = 'appleklinika_home_content' . ($list !== '' ? '[' . $list . '][' . $index . ']' : '') . '[' . $key . ']';
    $id = 'ak-home-' . ($list !== '' ? $list . '-' . $index . '-' : '') . $key;
    $onlyLayout = '';
    if ($list === 'hero_items') {
        if (in_array($key, ['eyebrow', 'text', 'primary_label', 'primary_url', 'secondary_label', 'secondary_url'], true)) {
            $onlyLayout = 'split';
        } elseif (in_array($key, ['url', 'alt'], true)) {
            $onlyLayout = 'artwork';
        }
    }
    ?>
    <div class="ak-home-editor__field" <?php echo $onlyLayout !== '' ? 'data-home-only-layout="' . esc_attr($onlyLayout) . '"' : ''; ?>>
        <label for="<?php echo esc_attr($id); ?>">
            <?php if ($list === 'hero_items' && $key === 'title') : ?>
                <span data-home-only-layout="split">Cím</span>
                <span data-home-only-layout="artwork">Dia neve (belső cím)</span>
            <?php else : ?>
                <?php echo esc_html($label); ?>
            <?php endif; ?>
        </label>
        <?php if ($type === 'textarea') : ?>
            <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-home-field="<?php echo esc_attr($key); ?>" rows="3"><?php echo esc_textarea((string) $value); ?></textarea>
        <?php elseif (in_array($type, ['icon', 'layout', 'boolean'], true)) : ?>
            <?php
            $choices = $type === 'icon' ? appleklinika_home_content_icons() : ($type === 'layout' ? ['split' => 'Külön szöveg és kép', 'artwork' => 'Kész képes banner'] : ['1' => 'Bekapcsolva', '0' => 'Kikapcsolva']);
            $selectedValue = $type === 'boolean' ? ($value ? '1' : '0') : (string) $value;
            ?>
            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-home-field="<?php echo esc_attr($key); ?>">
                <?php foreach ($choices as $choice => $choiceLabel) : ?>
                    <option value="<?php echo esc_attr((string) $choice); ?>" <?php selected($selectedValue, (string) $choice); ?>><?php echo esc_html($choiceLabel); ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'image') : ?>
            <?php $preview = $value ? wp_get_attachment_image_url((int) $value, 'thumbnail') : false; ?>
            <div class="ak-home-editor__media" data-home-media>
                <input type="hidden" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-home-field="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>">
                <img data-home-preview alt="Kiválasztott kép előnézete" <?php echo $preview ? 'src="' . esc_url($preview) . '"' : 'hidden'; ?>>
                <p data-home-image-empty <?php echo $preview ? 'hidden' : ''; ?>>
                    <?php if ($list === 'hero_items') : ?>
                        <span data-home-only-layout="split">Nincs egyedi kép. A főoldal az alapértelmezett illusztrációt használja.</span>
                        <span data-home-only-layout="artwork">Nincs kép kiválasztva. Képes banner módban a dia csak képpel és hivatkozással jelenik meg.</span>
                    <?php else : ?>
                        Nincs egyedi kép. A főoldal az alapértelmezett illusztrációt használja.
                    <?php endif; ?>
                </p>
                <div class="ak-home-editor__media-actions">
                    <button type="button" class="button" data-home-action="image-select"><?php echo $preview ? 'Kép cseréje' : 'Kép választása'; ?></button>
                    <button type="button" class="button" data-home-action="image-remove" <?php disabled(! $preview); ?>>Kép eltávolítása</button>
                </div>
            </div>
        <?php else : ?>
            <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-home-field="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo $type === 'url' ? 'inputmode="url"' : ''; ?>>
        <?php endif; ?>
        <?php if ($list === 'hero_items' && $key === 'url') : ?>
            <p class="description">A teljes banner erre a címre vezet. Üres hivatkozás vagy hiányzó kép esetén a dia kimarad a főoldalról.</p>
        <?php elseif ($list === 'hero_items' && $key === 'alt') : ?>
            <p class="description">Foglald össze a képen szereplő üzenetet és a hivatkozás célját azoknak is, akik nem látják a képet.</p>
        <?php endif; ?>
    </div>
    <?php
}

function appleklinika_homepage_admin_row(string $list, string $index, array $row, array $fields): void
{
    $labels = ['enabled' => 'Megjelenítés', 'eyebrow' => 'Felső címke', 'title' => 'Cím', 'text' => 'Leírás', 'image_id' => 'Kép', 'alt' => 'Alternatív szöveg', 'primary_label' => 'Első gomb felirata', 'primary_url' => 'Első gomb hivatkozása', 'secondary_label' => 'Második gomb felirata', 'secondary_url' => 'Második gomb hivatkozása', 'url' => 'Hivatkozás', 'icon' => 'Ikon'];
    ?>
    <details class="ak-home-editor__row" data-home-row>
        <summary><span data-home-row-title><?php echo esc_html(((int) $index + 1) . '. ' . ($row['title'] ?? 'Új elem')); ?></span></summary>
        <div class="ak-home-editor__row-body">
            <div class="ak-home-editor__row-actions">
                <button type="button" class="button" data-home-action="up">Feljebb</button>
                <button type="button" class="button" data-home-action="down">Lejjebb</button>
                <button type="button" class="button" data-home-action="remove">Elem eltávolítása</button>
            </div>
            <?php foreach ($fields as $key => $type) : ?>
                <?php appleklinika_homepage_admin_field($key, $type, $labels[$key], $row[$key] ?? ($type === 'boolean' ? true : ($type === 'image' ? 0 : '')), $list, $index); ?>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
}

function appleklinika_homepage_admin_list(string $key, string $label, array $rows): void
{
    $schema = appleklinika_home_content_list_schema()[$key];
    ?>
    <div class="ak-home-editor__list" data-home-list="<?php echo esc_attr($key); ?>" data-min="<?php echo esc_attr((string) $schema['min']); ?>" data-max="<?php echo esc_attr((string) $schema['max']); ?>" aria-label="<?php echo esc_attr($label); ?>">
        <h3><?php echo esc_html($label); ?></h3>
        <p class="description">Nyisd le az elemet a szerkesztéshez. A Feljebb és Lejjebb gombokkal változtathatod a sorrendet. Legfeljebb <?php echo esc_html((string) $schema['max']); ?> elem.</p>
        <input type="hidden" name="appleklinika_home_content[<?php echo esc_attr($key); ?>]" value="">
        <div data-home-rows>
            <?php foreach ($rows as $index => $row) : ?>
                <?php appleklinika_homepage_admin_row($key, (string) $index, $row, $schema['fields']); ?>
            <?php endforeach; ?>
        </div>
        <template data-home-template><?php appleklinika_homepage_admin_row($key, 'new', [], $schema['fields']); ?></template>
        <button type="button" class="button" data-home-action="add">Új elem hozzáadása</button>
    </div>
    <?php
}

function appleklinika_render_homepage_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $content = appleklinika_home_content();
    ?>
    <div class="wrap ak-home-editor">
        <h1>Apple Klinika homepage</h1>
        <p>A főoldal tartalmait itt szerkesztheted. A fejléc és a termékkártyák változatlanok maradnak; a termékadatokat továbbra is a WooCommerce adja.</p>
        <p>A hivatkozás lehet teljes webcím, belső útvonal vagy oldalon belüli horgony (például <code>#ak-home-offers</code>). Üres gombfelirat esetén a gomb nem jelenik meg.</p>
        <?php settings_errors(); ?>
        <form method="post" action="options.php" data-home-editor data-home-hero-layout="<?php echo esc_attr($content['hero_layout']); ?>">
            <?php settings_fields('appleklinika_homepage_settings'); ?>
            <p class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-home-status></p>
            <details class="ak-home-editor__section" open>
                <summary>1. Nyitó szakasz</summary>
                <div class="ak-home-editor__section-body">
                    <p>A nyitó szakasz legalább egy, legfeljebb nyolc tárolt diából állhat. A kikapcsolt diák nem jelennek meg. Egy megjeleníthető dia esetén nincs lapozás.</p>
                    <?php appleklinika_homepage_admin_field('hero_layout', 'layout', 'Nyitó szakasz megjelenése', $content['hero_layout']); ?>
                    <p data-home-only-layout="split">A cím, a leírás és a gombok a kép mellett jelennek meg. A cím sortörései a főoldalon is megmaradnak.</p>
                    <p data-home-only-layout="artwork">A szöveg és a gomb kinézete már a képfájl része. A teljes kép kattintható: add meg a célhivatkozását és az alternatív szöveget. A dia neve belső cím marad. A korábbi külön szöveg- és gombmezőket megőrizzük, de ebben a módban nem kerülnek a képre.</p>
                    <?php appleklinika_homepage_admin_list('hero_items', 'Nyitó diák', $content['hero_items']); ?>
                    <?php appleklinika_homepage_admin_list('hero_benefits', 'Rövid előnyök', $content['hero_benefits']); ?>
                </div>
            </details>
            <details class="ak-home-editor__section">
                <summary>2. Termékkategóriák</summary>
                <div class="ak-home-editor__section-body">
                    <?php appleklinika_homepage_admin_field('category_title', 'text', 'Szakaszcím', $content['category_title']); ?>
                    <?php appleklinika_homepage_admin_field('category_link_label', 'text', 'Összes termék hivatkozás felirata', $content['category_link_label']); ?>
                    <?php appleklinika_homepage_admin_list('categories', 'Kategóriák', $content['categories']); ?>
                </div>
            </details>
            <details class="ak-home-editor__section">
                <summary>3. Kiemelt ajánlatok</summary>
                <div class="ak-home-editor__section-body">
                    <?php appleklinika_homepage_admin_field('featured_title', 'text', 'Szakaszcím', $content['featured_title']); ?>
                    <?php appleklinika_homepage_admin_field('featured_text', 'textarea', 'Leírás', $content['featured_text']); ?>
                    <?php appleklinika_homepage_admin_field('featured_link_label', 'text', 'Összes termék hivatkozás felirata', $content['featured_link_label']); ?>
                    <div class="ak-home-editor__field">
                        <label for="appleklinika_home_featured_product_ids">Kiemelt Apple ajánlatok termékek</label>
                        <input type="text" id="appleklinika_home_featured_product_ids" name="appleklinika_home_featured_product_ids" value="<?php echo esc_attr(implode(', ', appleklinika_home_featured_product_ids())); ?>" placeholder="Pl. 123, 456, 789">
                        <p class="description">WooCommerce termékazonosítók vesszővel elválasztva. A sorrend megmarad, csak publikált termékek jelennek meg. Üres lista esetén a WooCommerce kiemelt, majd akciós és friss termékei töltik fel a szakaszt.</p>
                    </div>
                    <div class="ak-home-editor__field">
                        <label for="appleklinika_home_featured_product_limit">Megjelenített termékek száma</label>
                        <input type="number" id="appleklinika_home_featured_product_limit" name="appleklinika_home_featured_product_limit" value="<?php echo esc_attr((string) appleklinika_home_featured_product_limit()); ?>" min="1" max="12" step="1">
                        <p class="description">Engedélyezett tartomány: 1–12. Alapértelmezett: 6.</p>
                    </div>
                </div>
            </details>
            <details class="ak-home-editor__section">
                <summary>4. Bizalmi szakasz</summary>
                <div class="ak-home-editor__section-body">
                    <?php appleklinika_homepage_admin_field('trust_title', 'text', 'Szakaszcím', $content['trust_title']); ?>
                    <?php appleklinika_homepage_admin_field('trust_text', 'textarea', 'Leírás', $content['trust_text']); ?>
                    <?php appleklinika_homepage_admin_list('trust_items', 'Bizalmi elemek', $content['trust_items']); ?>
                </div>
            </details>
            <details class="ak-home-editor__section">
                <summary>5. Így dolgozunk</summary>
                <div class="ak-home-editor__section-body">
                    <?php appleklinika_homepage_admin_field('process_title', 'text', 'Szakaszcím', $content['process_title']); ?>
                    <?php appleklinika_homepage_admin_field('process_text', 'textarea', 'Leírás', $content['process_text']); ?>
                    <?php appleklinika_homepage_admin_field('process_image_id', 'image', 'A folyamat képe', $content['process_image_id']); ?>
                    <?php appleklinika_homepage_admin_list('process_items', 'A folyamat lépései', $content['process_items']); ?>
                </div>
            </details>
            <?php submit_button('Főoldal mentése'); ?>
        </form>
    </div>
    <?php
}
