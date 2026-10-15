<?php
/** Read-only inventory via `wp eval-file`; no apply/delete/import implementation. */
function ak_launch_guard(string $environment, string $home): void
{
    $host = parse_url($home, PHP_URL_HOST);
    if (!in_array($environment, ['local', 'development', 'staging'], true)
        || !in_array($host, ['localhost', '127.0.0.1', 'teszt.appleklinika.com'], true)) {
        throw new RuntimeException('Only known LOCAL/TEST read-only inventory is supported. Production is refused.');
    }
}

function ak_launch_plan(array $data, array $approvedMedia): array
{
    $ids = static function (array $values): array {
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);
        return $values;
    };
    $allMedia = $ids($data['media']);
    $approvedMedia = $ids($approvedMedia);
    if (array_diff($approvedMedia, $allMedia)) {
        throw new RuntimeException('Approved media ID not found in source inventory.');
    }
    $productMedia = $ids($data['product_media']);
    $review = array_values(array_diff($allMedia, $approvedMedia));
    return [
        'mode' => 'dry-run / fresh-production-database / no source deletion',
        'exclude_from_production' => [
            'product_ids_all_statuses' => $ids($data['products']),
            'variation_ids_all_statuses' => $ids($data['variations']),
            'order_ids_all_statuses' => $ids($data['orders']),
            'refund_ids_all_statuses' => $ids($data['refunds']),
            'user_ids_all_roles' => $ids($data['users']),
            'sessions_carts_transients' => 'ALL: start with new empty stores; do not export',
            'provider_transactions_documents_logs' => 'ALL: no TEST provider state export',
        ],
        'media' => [
            'approved_site_media_keep_ids' => $approvedMedia,
            'product_referenced_ids' => $productMedia,
            'shared_product_and_approved_site_ids_keep' => array_values(array_intersect($approvedMedia, $productMedia)),
            'unapproved_ids_do_not_export_until_reviewed' => $review,
            'product_referenced_unapproved_ids' => array_values(array_intersect($review, $productMedia)),
            'rule' => 'Product references do not prove exclusive use. No media deletion; manually approve site/page/logo references.',
        ],
        'configuration_review' => [
            'page_ids_keep_after_review' => $ids($data['pages']),
            'product_taxonomy_term_ids_review_no_product_relationships' => $ids($data['terms']),
            'legal_pages' => 'Separate approved legal-content process; no wording changes.',
            'settings_secrets' => 'Use production-data-separation.md; never export all options.',
        ],
    ];
}

if (defined('AK_CATALOG_PLAN_LIBRARY_ONLY')) {
    return;
}
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI || !function_exists('wc_get_orders')) {
    throw new RuntimeException('Use supported WordPress CLI with WooCommerce loaded.');
}
ak_launch_guard(wp_get_environment_type(), home_url('/'));
$args = $args ?? [];
$approved = [];
if ($args) {
    if (count($args) !== 1 || !is_file($args[0])) {
        throw new RuntimeException('Optional argument must be a reviewed JSON array of site-media attachment IDs.');
    }
    $approved = json_decode(file_get_contents($args[0]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($approved) || array_filter($approved, static fn($id) => !is_int($id) || $id <= 0)) {
        throw new RuntimeException('Invalid site-media allowlist.');
    }
}
$statuses = array_keys(get_post_stati());
$getIds = static fn(string $type): array => get_posts([
    'post_type' => $type, 'post_status' => $statuses, 'posts_per_page' => -1,
    'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true,
]);
$data = [
    'products' => $getIds('product'), 'variations' => $getIds('product_variation'),
    'media' => $getIds('attachment'), 'pages' => $getIds('page'), 'product_media' => [],
    'users' => get_users(['fields' => 'ID']), 'orders' => [], 'refunds' => [], 'terms' => [],
];
foreach (array_merge($data['products'], $data['variations']) as $id) {
    $product = wc_get_product($id);
    if ($product) {
        $data['product_media'] = array_merge($data['product_media'],
            array_filter([$product->get_image_id()]), $product->get_gallery_image_ids());
    }
}
$orderStatuses = array_values(array_unique(array_merge(array_keys(wc_get_order_statuses()),
    $statuses, ['wc-checkout-draft', 'checkout-draft', 'trash', 'auto-draft'])));
foreach (['shop_order' => 'orders', 'shop_order_refund' => 'refunds'] as $type => $key) {
    for ($page = 1; ; $page++) {
        $found = wc_get_orders(['type' => $type, 'status' => $orderStatuses,
            'limit' => 100, 'page' => $page, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
        $data[$key] = array_merge($data[$key], $found);
        if (count($found) < 100) {
            break;
        }
    }
}
$terms = get_terms(['taxonomy' => get_object_taxonomies('product'), 'hide_empty' => false, 'fields' => 'ids']);
if (is_wp_error($terms)) {
    throw new RuntimeException('Taxonomy inventory unavailable.');
}
$data['terms'] = $terms;
echo wp_json_encode(ak_launch_plan($data, $approved), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
