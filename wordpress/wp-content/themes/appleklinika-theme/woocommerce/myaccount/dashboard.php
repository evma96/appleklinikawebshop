<?php
/**
 * Apple Klinika account dashboard.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

defined('ABSPATH') || exit;

$displayName = $current_user instanceof WP_User && $current_user->display_name !== ''
    ? $current_user->display_name
    : 'Apple Klinika vásárló';
$email = $current_user instanceof WP_User ? $current_user->user_email : '';
$userId = $current_user instanceof WP_User ? (int) $current_user->ID : 0;
$initials = function_exists('appleklinika_account_initials') ? appleklinika_account_initials($current_user) : 'AK';
$orderCount = function_exists('appleklinika_account_order_count') ? appleklinika_account_order_count($userId) : 0;
$warrantyCount = function_exists('appleklinika_account_warranty_records') ? count(appleklinika_account_warranty_records($userId)) : 0;
$favoriteCount = function_exists('appleklinika_account_wishlist_count') ? appleklinika_account_wishlist_count($userId) : 0;
$summaryCards = [
    [
        'title' => 'Vásárlásaim',
        'count' => $orderCount,
        'text' => $orderCount > 0 ? 'Rendeléseid és állapotuk egy helyen.' : 'Jelenleg nincs leadott rendelésed.',
        'url' => wc_get_account_endpoint_url('orders'),
    ],
    [
        'title' => 'Garanciáim',
        'count' => $warrantyCount,
        'text' => $warrantyCount > 0 ? 'Valós rendelésből származó garanciaadatok.' : 'Még nincs aktív garanciád.',
        'url' => wc_get_account_endpoint_url('garanciaim'),
    ],
    [
        'title' => 'Kedvelt termékek',
        'count' => $favoriteCount,
        'text' => $favoriteCount > 0 ? 'A későbbre elmentett készülékeid.' : 'Még nincsenek kedvelt termékeid.',
        'url' => wc_get_account_endpoint_url('kedvelt-termekek'),
    ],
];
?>

<section class="ak-account-home">
    <header class="ak-account-profile-hero">
        <span class="ak-account-profile-hero__avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
        <div>
            <p class="ak-account-section-kicker">Vezérlőpult</p>
            <h2><?php echo esc_html($displayName); ?></h2>
            <?php if ($email !== '') : ?>
                <p><?php echo esc_html($email); ?></p>
            <?php endif; ?>
        </div>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">Fiók beállítások</a>
    </header>

    <div class="ak-account-summary-grid">
        <?php foreach ($summaryCards as $card) : ?>
            <a class="ak-account-summary-card" href="<?php echo esc_url($card['url']); ?>">
                <span class="ak-account-summary-card__count"><?php echo esc_html((string) $card['count']); ?></span>
                <strong><?php echo esc_html($card['title']); ?></strong>
                <small><?php echo esc_html($card['text']); ?></small>
                <span class="ak-account-summary-card__arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($orderCount === 0 && $warrantyCount === 0 && $favoriteCount === 0) : ?>
        <?php appleklinika_render_account_category_recommendations(); ?>
        <?php appleklinika_render_account_trust_strip(); ?>
    <?php endif; ?>
</section>

<?php
do_action('woocommerce_account_dashboard');
do_action('woocommerce_before_my_account');
do_action('woocommerce_after_my_account');
