<?php

declare(strict_types=1);

/** Immutable, owner-supplied VirtualJog binaries; never extract or regenerate legal copy. */
function appleklinika_legal_pdf_catalog(): array
{
    return [
        'terms' => ['title' => 'Általános Szerződési Feltételek', 'file' => 'virtualjog-terms-v2-20260929.pdf', 'updated' => '2026.09.29', 'sha256' => '6a86d96c62b29cf3da4c8027fab62295567bb8f811ebf2c8cd20cc5fa17ff71b'],
        'privacy' => ['title' => 'Adatkezelési tájékoztató', 'file' => 'virtualjog-privacy-v2-20261007.pdf', 'updated' => '2026.10.07', 'sha256' => '21efbe66c36ddb9dd90d06f6b57116eeb6c7539ca7943f758ae4224336ca3e2c'],
    ];
}

function appleklinika_legal_pdf_is_installed(string $key, int $pageId): bool
{
    $page = get_post($pageId);
    return $pageId > 0 && $page instanceof WP_Post && $page->post_status === 'publish'
        && str_contains($page->post_content, '[appleklinika_legal_pdf document="' . $key . '"]');
}

function appleklinika_render_legal_pdf(array $attributes): string
{
    $key = (string)($attributes['document'] ?? '');
    $document = appleklinika_legal_pdf_catalog()[$key] ?? null;
    if ($document === null) { return ''; }
    $url = get_template_directory_uri() . '/assets/legal/' . $document['file'];
    return '<section class="ak-legal-pdf" aria-label="' . esc_attr($document['title']) . '">'
        . '<p>VirtualJog · 2. verzió · Elfogadva: 2026.09.20 · Frissítve: ' . esc_html($document['updated']) . '</p>'
        . '<div class="ak-legal-pdf-actions"><a class="ak-legal-pdf-open" href="' . esc_url($url) . '" target="_blank" rel="noopener">PDF megnyitása</a>'
        . '<a href="' . esc_url($url) . '" download="' . esc_attr($document['file']) . '">PDF letöltése</a></div>'
        . '<p>A teljes dokumentum a PDF megnyitása gombbal külön ablakban is olvasható.</p>'
        . '<iframe class="ak-legal-pdf-frame" src="' . esc_url($url) . '#view=FitH" title="' . esc_attr($document['title']) . ' – PDF"></iframe></section>';
}
add_shortcode('appleklinika_legal_pdf', 'appleklinika_render_legal_pdf');

function appleklinika_legal_pdf_styles(): void
{
    if (is_page() && has_shortcode((string)get_post_field('post_content', get_queried_object_id()), 'appleklinika_legal_pdf')) {
        wp_enqueue_style('appleklinika-legal-pdfs', get_template_directory_uri() . '/assets/css/legal-pdfs.css', [], '1');
    }
}
add_action('wp_enqueue_scripts', 'appleklinika_legal_pdf_styles');

/** Keep /aszf available even when WordPress uses plain ?page_id permalinks. */
function appleklinika_legal_pdf_request($wp): void
{
    $path = trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $base = trim((string)wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '') {
        if (!str_starts_with($path, $base . '/')) { return; }
        $path = substr($path, strlen($base) + 1);
    }
    $key = ['aszf' => 'terms', 'adatvedelem' => 'privacy'][$path] ?? null;
    if ($key === null) { return; }
    $option = $key === 'terms' ? 'woocommerce_terms_page_id' : 'wp_page_for_privacy_policy';
    $id = (int)get_option($option, 0);
    if (appleklinika_legal_pdf_is_installed($key, $id)) {
        $wp->query_vars = ['page_id' => $id];
        $wp->matched_rule = 'appleklinika-legal-pdf';
    }
}
add_action('parse_request', 'appleklinika_legal_pdf_request');

function appleklinika_legal_terms_permalink(string $url, int $pageId): string
{
    return $pageId === (int)get_option('woocommerce_terms_page_id', 0)
        && appleklinika_legal_pdf_is_installed('terms', $pageId) ? home_url('/aszf/') : $url;
}
add_filter('page_link', 'appleklinika_legal_terms_permalink', 10, 2);
