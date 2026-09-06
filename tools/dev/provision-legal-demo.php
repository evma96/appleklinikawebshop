<?php

declare(strict_types=1);

/** Explicit CLI-only demo provisioning. Never loaded by the theme or a plugin. */
function ak_legal_demo_target_allowed(string $url, string $environment, bool $allowLocalLabel): bool
{
    $url = rtrim($url, '/');
    return ($url === 'https://teszt.appleklinika.com' && $environment === 'staging')
        || ($url === 'http://localhost:8080' && (in_array($environment, ['local', 'development'], true) || $allowLocalLabel));
}

/** @return array<string, array{slug: string, topics: list<string>}> */
function ak_legal_demo_documents(): array
{
    return [
        'terms' => ['slug' => 'teszt-jogi-aszf', 'topics' => ['A dokumentum célja', 'A vásárlás lépései', 'Rendelési adatok ellenőrzése', 'Visszaigazolások és kapcsolattartás']],
        'privacy' => ['slug' => 'teszt-jogi-adatkezeles', 'topics' => ['A tájékoztató célja', 'Adatkategóriák bemutatása', 'Adatkezelési folyamatok helye', 'Kapcsolat és kérelmek']],
        'cookies' => ['slug' => 'teszt-jogi-cookie', 'topics' => ['A mintalap célja', 'Sütik csoportosításának helye', 'Beállítások bemutatása', 'Tájékoztató frissítése']],
        'withdrawal' => ['slug' => 'teszt-jogi-elallas', 'topics' => ['A folyamat áttekintése', 'Ügyintézéshez szükséges adatok', 'Visszaküldés bemutatása', 'Ügyfél-tájékoztatás']],
        'warranty' => ['slug' => 'teszt-jogi-jotallas', 'topics' => ['A tájékoztató áttekintése', 'Készülékadatok és dokumentumok', 'Bejelentés és vizsgálat', 'Kapcsolattartás']],
        'shipping_payment' => ['slug' => 'teszt-jogi-szallitas-fizetes', 'topics' => ['Elérhető módok bemutatása', 'Szállítási adatok ellenőrzése', 'Fizetési tájékoztatás helye', 'Rendelés követése']],
        'marketing' => ['slug' => 'teszt-jogi-marketing', 'topics' => ['Az önkéntes jelölőnégyzet tesztje', 'Tájékoztatók típusainak helye', 'Választás és beállítások', 'Kapcsolat és visszajelzés']],
        'buyback_terms' => ['slug' => 'teszt-jogi-felvasarlas', 'topics' => ['A felvásárlási folyamat áttekintése', 'Készülékadatok megadása', 'Ajánlat és személyes vizsgálat', 'Átadás és kapcsolattartás']],
    ];
}

/** @param list<string> $topics @param array<string, string> $links */
function ak_legal_demo_content(array $topics, array $links): string
{
    $e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $html = '<div style="max-width:760px;margin:0 auto;line-height:1.75;overflow-wrap:anywhere">';
    $html .= '<p style="padding:20px;border-left:4px solid #df0024;background:#fff3f5;border-radius:8px"><strong>TESZT / MINTASZÖVEG – NEM VÉGLEGES JOGI DOKUMENTUM</strong></p>';
    $html .= '<p>Ez az oldal kizárólag az elrendezés, a hivatkozások és a hozzájárulási felületek kipróbálására készült. Nem jogi tanács, nem végleges szerződési feltétel és nem használható éles tájékoztatóként. Az éles indulás előtt a jóváhagyott, valódi dokumentummal kell helyettesíteni.</p>';
    $html .= '<h2 style="margin-top:32px;font-size:1.5rem">A tesztoldal használata</h2><p>Görgess végig az oldalon asztali és mobilnézetben is. Ellenőrizd, hogy a címek, a hosszabb bekezdések és a felsorolások jól olvashatók-e. A lap végén található kapcsolódó dokumentumok az ugyanebben a tesztkörnyezetben létrehozott mintalapokra vezetnek.</p>';
    $html .= '<ul><li>Ellenőrizhető a címsorok sorrendje és a szövegoszlop szélessége.</li><li>Kipróbálható a hivatkozások megjelenése és billentyűzetes elérése.</li><li>A hozzájárulás kiválasztása nem jelent külső hírlevél-feliratkozást.</li></ul>';
    foreach ($topics as $index => $topic) {
        $html .= '<h2 style="margin-top:32px;font-size:1.5rem">' . ($index + 1) . '. ' . $e($topic) . '</h2>';
        $html .= '<p><strong>Minta fejezet.</strong> Itt a végleges, szakmailag ellenőrzött dokumentum megfelelő része kap majd helyet. Ez a bekezdés azt szemlélteti, hogyan jelenik meg egy többmondatos tájékoztatás az Apple Klinika oldalán. Nem állapít meg határidőt, díjat, jogosultságot vagy kötelezettséget.</p>';
        $html .= '<h3 style="margin-top:24px;font-size:1.15rem">Mit ellenőrzünk ezen a részen?</h3><p>A fejezetcím és a törzsszöveg közötti távolságot, a sorok olvashatóságát és a hosszabb tartalom görgetését. A próba során a felhasználó bármikor visszatérhet a korábbi oldalra; az itt látható magyarázat nem helyettesíti a vásárlás előtt szükséges valódi jogi tájékoztatást.</p>';
        $html .= '<ul><li>A tartalom kis képernyőn sem lóghat ki oldalirányban.</li><li>A felsorolás különüljön el a környező bekezdésektől.</li><li>A végleges szöveget a megfelelő üzleti és jogi jóváhagyás után kell beilleszteni.</li></ul>';
    }
    $html .= '<h2 style="margin-top:32px;font-size:1.5rem">Kapcsolódó mintadokumentumok</h2><p>Az alábbi hivatkozásokkal a linkstílus és a dokumentumok közötti navigáció tesztelhető.</p><ul>';
    foreach ($links as $title => $url) {
        $html .= '<li><a style="text-decoration:underline" href="' . $e($url) . '">' . $e($title) . '</a></li>';
    }
    return $html . '</ul><p><strong>Továbbra is teszttartalom:</strong> éles indulás előtt minden mintaszöveget cserélni kell. A szükséges végleges adatok és szabályok nem ebből az oldalból következnek.</p></div>';
}

/** Change only the native Terms block setting, not the checkout architecture. */
function ak_legal_demo_terms_checkbox(array &$blocks): int
{
    $count = 0;
    foreach ($blocks as &$block) {
        if (($block['blockName'] ?? '') === 'woocommerce/checkout-terms-block') {
            $block['attrs']['checkbox'] = true;
            ++$count;
        }
        if (! empty($block['innerBlocks'])) {
            $count += ak_legal_demo_terms_checkbox($block['innerBlocks']);
        }
    }
    return $count;
}

/** Demo-only visual correction kept in WordPress Additional CSS, not theme code. */
function ak_legal_demo_css(): string
{
    return <<<'CSS'
/* ak-legal-demo:start */
body.woocommerce-checkout #contact-fields input[id$="-appleklinika-marketing_consent"] {
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    padding: 0 !important;
    margin: 2px 10px 0 0;
    border-radius: 4px;
    flex: 0 0 20px;
}
body.woocommerce-checkout #contact-fields label:has(input[id$="-appleklinika-marketing_consent"]) {
    align-items: flex-start;
    min-height: 44px;
}
.ak-buyback-demo__privacy-check:has(input[name="marketing_consent"]) {
    display: block;
    position: relative;
    padding-left: 30px;
    margin-top: 12px;
    min-height: 24px;
    text-align: left;
}
.ak-buyback-demo__privacy-check input[name="marketing_consent"] {
    position: absolute;
    left: 0;
    top: 3px;
    width: 18px;
    height: 18px;
    margin: 0;
}
/* ak-legal-demo:end */
CSS;
}

function ak_legal_demo_run(array $args): void
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI only.');
    }
    require_once getenv('AK_WORDPRESS_LOAD') ?: '/var/www/html/wp-load.php';
    if (! ak_legal_demo_target_allowed(home_url(), wp_get_environment_type(), in_array('--allow-local-production-label', $args, true))) {
        throw new RuntimeException('Refusing target: only the exact LOCAL URL or staging TEST SERVER is allowed.');
    }
    if (! function_exists('appleklinika_legal_document_definitions')) {
        throw new RuntimeException('The existing central legal resolver is required.');
    }
    $definitions = appleklinika_legal_document_definitions();
    $plan = [];
    $options = [];
    foreach (ak_legal_demo_documents() as $key => $document) {
        $option = $definitions[$key]['native_option'] ?? $definitions[$key]['option'];
        $mappedId = (int) get_option($option, 0);
        $mapped = $mappedId ? get_post($mappedId) : null;
        $owned = get_posts(['post_type' => 'page', 'post_status' => 'any', 'numberposts' => 2, 'meta_key' => '_ak_legal_demo_key', 'meta_value' => $key]);
        if (count($owned) > 1) {
            throw new RuntimeException("Duplicate demo ownership: {$key}");
        }
        $page = $owned[0] ?? null;
        if ($page && (! in_array($page->post_status, ['draft', 'publish'], true) || $page->post_name !== $document['slug'] || $page->post_title !== $definitions[$key]['title'] . ' – TESZT' || ! hash_equals((string) get_post_meta($page->ID, '_ak_legal_demo_content_hash', true), hash('sha256', $page->post_content)))) {
            throw new RuntimeException("Demo content was manually edited; refusing overwrite: {$key}");
        }
        if ($mapped && (! $page || $mappedId !== $page->ID)) {
            // Explicitly reviewed original WP privacy draft is preserved, not overwritten.
            $flag = '--replace-unpublished-privacy=' . $mappedId;
            if ($key !== 'privacy' || $mapped->post_status !== 'draft' || ! in_array($flag, $args, true)) {
                throw new RuntimeException("Existing non-demo mapping: {$key} #{$mappedId}");
            }
        }
        $collision = get_page_by_path($document['slug'], OBJECT, 'page');
        if ($collision && (! $page || $collision->ID !== $page->ID)) {
            throw new RuntimeException("Non-demo slug collision: {$document['slug']}");
        }
        $options[$option] = get_option($option, null);
        $plan[$key] = ['id' => $page ? $page->ID : 0, 'title' => $definitions[$key]['title'] . ' – TESZT', 'slug' => $document['slug'], 'option' => $option];
    }
    $checkout = get_post(wc_get_page_id('checkout'));
    if (! $checkout || $checkout->post_type !== 'page') {
        throw new RuntimeException('Checkout page missing.');
    }
    // A normal page-content notice supplies the demo marketing link; no React
    // controls, labels or containers are injected, moved or reimplemented.
    $cleanCheckoutContent = preg_replace('~<!-- wp:html -->\s*<!-- ak-legal-demo-reference:start -->.*?<!-- ak-legal-demo-reference:end -->\s*<!-- /wp:html -->\s*~s', '', $checkout->post_content);
    $blocks = parse_blocks((string) $cleanCheckoutContent);
    if (ak_legal_demo_terms_checkbox($blocks) !== 1) {
        throw new RuntimeException('Exactly one native Woo Terms block is required.');
    }
    $checkoutContent = serialize_blocks($blocks);
    $oldCss = wp_get_custom_css();
    if (preg_match('~/\* ak-legal-demo:start \*/.*?/\* ak-legal-demo:end \*/~s', $oldCss, $match) && $match[0] !== ak_legal_demo_css()) {
        throw new RuntimeException('Demo CSS was manually edited; refusing overwrite.');
    }
    if (! in_array('--apply', $args, true)) {
        echo wp_json_encode(['mode' => 'PLAN', 'target' => home_url(), 'pages' => $plan, 'checkout' => $checkout->ID, 'terms_checkbox' => true, 'registration' => 'yes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        return;
    }
    // Back up original configuration once. No automatic rollback can overwrite later real content.
    add_option('_ak_legal_demo_baseline_v1', ['options' => $options, 'registration' => get_option('woocommerce_enable_myaccount_registration'), 'checkout_id' => $checkout->ID, 'checkout_content' => $checkout->post_content], '', false);
    add_option('_ak_legal_demo_original_custom_css', $oldCss, '', false);
    foreach ($plan as $key => &$item) {
        if ($item['id'] === 0) {
            $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $item['title'], 'post_name' => $item['slug'], 'post_content' => '', 'meta_input' => ['_ak_legal_demo_key' => $key, '_ak_legal_demo_content_hash' => hash('sha256', '')]], true);
            if (is_wp_error($id)) {
                throw new RuntimeException($id->get_error_message());
            }
            $item['id'] = $id;
        }
    }
    unset($item);
    $links = [];
    foreach (['terms', 'privacy', 'marketing'] as $key) {
        $links[$definitions[$key]['title']] = get_permalink($plan[$key]['id']);
    }
    foreach ($plan as $key => $item) {
        // Compare the same sanitized representation WordPress persists. Otherwise
        // stripped CSS properties would trigger a needless revision on every run.
        $content = wp_kses_post(ak_legal_demo_content(ak_legal_demo_documents()[$key]['topics'], $links));
        $page = get_post($item['id']);
        if ($page->post_content !== $content || $page->post_status !== 'publish') {
            $result = wp_update_post(['ID' => $item['id'], 'post_content' => wp_slash($content), 'post_status' => 'publish'], true);
            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }
            update_post_meta($item['id'], '_ak_legal_demo_content_hash', hash('sha256', get_post($item['id'])->post_content));
        }
        update_option($item['option'], $item['id']);
    }
    $marketingUrl = appleklinika_legal_document('marketing')['url'];
    $reference = '<!-- wp:html -->' . "\n" . '<!-- ak-legal-demo-reference:start -->'
        . '<aside style="max-width:1080px;margin:24px auto;padding:20px;border:1px solid #e1e7ef;border-radius:12px;background:#fff;line-height:1.6">'
        . '<strong>Jogi felületek tesztje</strong><p>A marketing-hozzájárulás önkéntes: a jelölőnégyzet üresen hagyása nem akadályozza a vásárlást. Részletek a '
        . '<a style="text-decoration:underline" href="' . esc_url($marketingUrl) . '">Marketing-hozzájárulási tájékoztatóban</a>. A kapcsolt jogi oldalak teszt-mintaszöveget tartalmaznak.</p></aside>'
        . '<!-- ak-legal-demo-reference:end -->' . "\n" . '<!-- /wp:html -->' . "\n";
    $checkoutContent = $reference . $checkoutContent;
    if ($checkout->post_content !== $checkoutContent) {
        $result = wp_update_post(['ID' => $checkout->ID, 'post_content' => wp_slash($checkoutContent)], true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    if (! str_contains($oldCss, '/* ak-legal-demo:start */')) {
        $result = wp_update_custom_css_post(rtrim($oldCss) . "\n" . ak_legal_demo_css());
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }
    echo wp_json_encode(['mode' => 'APPLIED', 'target' => home_url(), 'pages' => $plan, 'checkout' => $checkout->ID, 'terms_checkbox' => true, 'registration' => 'yes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        ak_legal_demo_run(array_slice($argv, 1));
    } catch (Throwable $error) {
        fwrite(STDERR, 'REFUSED: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
