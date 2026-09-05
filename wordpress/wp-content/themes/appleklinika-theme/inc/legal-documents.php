<?php

declare(strict_types=1);

/**
 * Central, page-backed legal-document registry. Legal copy lives in normal
 * WordPress pages; this registry deliberately provides no placeholder copy.
 *
 * @return array<string, array{title: string, option: string, native_option?: string}>
 */
function appleklinika_legal_document_definitions(): array
{
    return [
        'terms' => ['title' => 'Általános Szerződési Feltételek', 'option' => 'appleklinika_legal_page_terms', 'native_option' => 'woocommerce_terms_page_id'],
        'privacy' => ['title' => 'Adatkezelési tájékoztató', 'option' => 'appleklinika_legal_page_privacy', 'native_option' => 'wp_page_for_privacy_policy'],
        'cookies' => ['title' => 'Cookie-tájékoztató', 'option' => 'appleklinika_legal_page_cookies'],
        'withdrawal' => ['title' => 'Elállás és visszaküldés', 'option' => 'appleklinika_legal_page_withdrawal'],
        'warranty' => ['title' => 'Jótállás és szavatosság', 'option' => 'appleklinika_legal_page_warranty'],
        'shipping_payment' => ['title' => 'Szállítás és fizetés', 'option' => 'appleklinika_legal_page_shipping_payment'],
        'marketing' => ['title' => 'Marketing-hozzájárulási tájékoztató', 'option' => 'appleklinika_legal_page_marketing'],
        'buyback_terms' => ['title' => 'Felvásárlási feltételek', 'option' => 'appleklinika_legal_page_buyback_terms'],
    ];
}

/** @return array{key: string, title: string, page_id: int, url: string, available: bool} */
function appleklinika_legal_document(string $key): array
{
    $definitions = appleklinika_legal_document_definitions();
    $definition = $definitions[$key] ?? null;
    if ($definition === null) {
        return ['key' => $key, 'title' => '', 'page_id' => 0, 'url' => '', 'available' => false];
    }

    $pageId = isset($definition['native_option']) ? absint(get_option($definition['native_option'], 0)) : absint(get_option($definition['option'], 0));
    $page = $pageId > 0 ? get_post($pageId) : null;
    $available = $page instanceof WP_Post && $page->post_type === 'page' && $page->post_status === 'publish';

    return [
        'key' => $key,
        'title' => $definition['title'],
        'page_id' => $available ? $pageId : 0,
        'url' => $available ? (string) get_permalink($pageId) : '',
        'available' => $available,
    ];
}

/** @return list<array{key: string, title: string, page_id: int, url: string, available: bool}> */
function appleklinika_legal_public_documents(): array
{
    $documents = [];
    foreach (array_keys(appleklinika_legal_document_definitions()) as $key) {
        $document = appleklinika_legal_document($key);
        if ($document['available']) {
            $documents[] = $document;
        }
    }

    return $documents;
}

function appleklinika_legal_link(string $key, ?string $label = null): string
{
    $document = appleklinika_legal_document($key);
    if (! $document['available']) {
        return '';
    }

    return sprintf('<a href="%s">%s</a>', esc_url($document['url']), esc_html($label ?? $document['title']));
}

function appleklinika_register_legal_document_settings(): void
{
    foreach (appleklinika_legal_document_definitions() as $definition) {
        $option = $definition['native_option'] ?? $definition['option'];
        register_setting('appleklinika_legal_documents', $option, ['type' => 'integer', 'sanitize_callback' => 'absint']);
    }
}
add_action('admin_init', 'appleklinika_register_legal_document_settings');

function appleklinika_render_legal_document_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Jogi dokumentumok</h1>
        <p>Csak a közzétett, itt kiválasztott WordPress-oldalak jelennek meg nyilvános hivatkozásként. A dokumentumok végleges tartalmát külön kell feltölteni.</p>
        <form action="options.php" method="post">
            <?php settings_fields('appleklinika_legal_documents'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach (appleklinika_legal_document_definitions() as $key => $definition) : ?>
                    <?php $native = $definition['native_option'] ?? ''; $option = $native !== '' ? $native : $definition['option']; ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($definition['title']); ?></label></th>
                        <td>
                            <?php wp_dropdown_pages(['name' => $option, 'id' => $option, 'selected' => absint(get_option($option, 0)), 'show_option_none' => '— Nincs kiválasztva —']); ?>
                            <?php if ($native !== '') : ?><p class="description">Ezt a WordPress/WooCommerce saját beállítása is használja.</p><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function appleklinika_register_legal_document_settings_page(): void
{
    add_options_page('Jogi dokumentumok', 'Jogi dokumentumok', 'manage_options', 'appleklinika-legal-documents', 'appleklinika_render_legal_document_settings_page');
}
add_action('admin_menu', 'appleklinika_register_legal_document_settings_page');

function appleklinika_checkout_terms_text(string $text): string
{
    $terms = appleklinika_legal_link('terms', 'Általános Szerződési Feltételeket');
    if ($terms === '') {
        return $text;
    }
    $privacy = appleklinika_legal_link('privacy', 'Adatkezelési tájékoztatót');

    return $privacy === ''
        ? sprintf('Elolvastam és elfogadom az %s.', $terms)
        : sprintf('Elolvastam és elfogadom az %s és az %s.', $terms, $privacy);
}
add_filter('woocommerce_get_terms_and_conditions_checkbox_text', 'appleklinika_checkout_terms_text');

function appleklinika_checkout_privacy_text(string $text, string $type): string
{
    $privacy = appleklinika_legal_link('privacy');
    if ($privacy === '') {
        return $text;
    }

    return $type === 'registration'
        ? sprintf('A fiók kezelésével kapcsolatos adatkezelési információt az %s tartalmazza.', $privacy)
        : sprintf('A rendelés adatkezelési információját az %s tartalmazza.', $privacy);
}
add_filter('woocommerce_get_privacy_policy_text', 'appleklinika_checkout_privacy_text', 10, 2);

function appleklinika_legal_marketing_document_available(): bool
{
    return appleklinika_legal_document('marketing')['available'];
}

function appleklinika_render_registration_marketing_consent(): void
{
    $link = appleklinika_legal_link('marketing');
    if ($link === '') {
        return;
    }
    echo '<p class="form-row form-row-wide"><label><input type="checkbox" name="appleklinika_marketing_consent" value="1"> Kérek marketingcélú tájékoztatást a ' . wp_kses_post($link) . ' szerint.</label></p>';
}
add_action('woocommerce_register_form', 'appleklinika_render_registration_marketing_consent');

function appleklinika_save_registration_marketing_consent(int $customerId): void
{
    update_user_meta($customerId, 'appleklinika_marketing_consent', isset($_POST['appleklinika_marketing_consent']) ? '1' : '0');
}
add_action('woocommerce_created_customer', 'appleklinika_save_registration_marketing_consent');
