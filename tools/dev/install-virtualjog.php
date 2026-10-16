<?php
/** Explicit CLI-only LOCAL/TEST content install; never included by WordPress. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require getenv('AK_WORDPRESS_LOAD') ?: '/var/www/html/wp-load.php';
$home = rtrim((string)get_option('home'), '/');
$environment = wp_get_environment_type();
if (!(($home === 'http://localhost:8082' && in_array($environment, ['local','development'], true))
    || ($home === 'https://teszt.appleklinika.com' && $environment === 'staging'))) {
    throw new RuntimeException('Only the explicitly approved LOCAL or TEST environment is supported.');
}
$apply = in_array('--apply', $argv, true);
if (!function_exists('appleklinika_legal_pdf_catalog')) { throw new RuntimeException('Deploy the PDF presentation source first.'); }
$changes = [];
foreach (appleklinika_legal_pdf_catalog() as $key => $document) {
    $file = get_template_directory() . '/assets/legal/' . $document['file'];
    if (!is_file($file) || !hash_equals($document['sha256'], hash_file('sha256', $file))) {
        throw new RuntimeException('Original PDF binary is missing or changed: ' . $key);
    }
    $option = $key === 'terms' ? 'woocommerce_terms_page_id' : 'wp_page_for_privacy_policy';
    $pageId = (int)get_option($option);
    $legacy = get_page_by_path($key === 'terms' ? 'aszf' : 'adatvedelem');
    $ids = array_unique(array_filter([$pageId, $legacy instanceof WP_Post ? $legacy->ID : 0]));
    if ($pageId < 1) { throw new RuntimeException('Missing native legal page mapping.'); }
    $content = '[appleklinika_legal_pdf document="' . $key . '"]';
    foreach ($ids as $id) {
        $page = get_post($id);
        if (!$page instanceof WP_Post || $page->post_type !== 'page' || $page->post_status !== 'publish') { throw new RuntimeException('Legal page is not a published page.'); }
        if ($page->post_content === $content && $page->post_title === $document['title']) { continue; }
        if (!str_contains($page->post_content, 'TESZT / MINTASZÖVEG') && !str_contains($page->post_content, 'indulás előtti, szerkeszthető') && !str_contains($page->post_content, 'indulásához előkészített, szerkeszthető ÁSZF tartalom')) {
            throw new RuntimeException('Unexpected existing legal content; do not overwrite page ' . $id);
        }
        $changes[] = ['ID' => $id, 'post_title' => $document['title'], 'post_content' => $content];
    }
}
$checkout = get_post((int)get_option('woocommerce_checkout_page_id'));
if ($checkout instanceof WP_Post && str_contains($checkout->post_content, '<!-- ak-legal-demo-reference:start -->')) {
    $clean = preg_replace('~<!-- wp:html -->\s*<!-- ak-legal-demo-reference:start -->.*?<!-- ak-legal-demo-reference:end -->\s*<!-- /wp:html -->\s*~s', '', $checkout->post_content, -1, $removed);
    if ($removed !== 1 || !is_string($clean)) { throw new RuntimeException('Unexpected checkout demo notice structure.'); }
    $changes[] = ['ID' => $checkout->ID, 'post_content' => $clean];
}
// Validate all intended changes before writing anything. Existing mappings/slugs stay intact.
if ($apply && $changes !== []) {
    $backupKey = '_appleklinika_virtualjog_v2_previous';
    if (get_option($backupKey, null) === null) {
        $backup = ['captured_at' => gmdate('c'), 'home' => $home, 'pages' => []];
        foreach ($changes as $change) { $backup['pages'][] = get_post($change['ID'], ARRAY_A); }
        add_option($backupKey, $backup, '', false);
    }
    foreach ($changes as $change) {
        wp_save_post_revision($change['ID']);
        $result = wp_update_post(wp_slash($change), true);
        if (is_wp_error($result)) { throw new RuntimeException('Page update failed; use preserved page backup.'); }
    }
}
echo json_encode(['environment'=>$environment,'home'=>$home,'mode'=>$apply?'apply':'plan','changed_page_ids'=>array_column($changes,'ID'),'documents'=>appleklinika_legal_pdf_catalog(),'native_mappings'=>['terms'=>(int)get_option('woocommerce_terms_page_id'),'privacy'=>(int)get_option('wp_page_for_privacy_policy')]], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
