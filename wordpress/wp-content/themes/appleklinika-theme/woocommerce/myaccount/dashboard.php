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
    : 'vásárló';

$quickLinks = [
    [
        'label' => 'Rendeléseim',
        'text' => 'Korábbi és folyamatban lévő rendeléseid áttekintése.',
        'url' => wc_get_account_endpoint_url('orders'),
    ],
    [
        'label' => 'Fiókadatok',
        'text' => 'Név, e-mail cím és jelszó módosítása.',
        'url' => wc_get_account_endpoint_url('edit-account'),
    ],
    [
        'label' => 'Kedvelt termékek',
        'text' => 'A későbbre félretett Apple készülékeid.',
        'url' => wc_get_account_endpoint_url('kedvelt-termekek'),
    ],
];
?>

<section class="ak-account-dashboard">
    <div class="ak-account-dashboard__intro">
        <p class="ak-account-section-kicker">Vezérlőpult</p>
        <h2>Szia, <?php echo esc_html($displayName); ?>!</h2>
        <p>Itt tudod áttekinteni a rendeléseidet, frissíteni a fiókadataidat, és visszanézni a kedvelt termékeidet.</p>
    </div>

    <div class="ak-account-dashboard__links">
        <?php foreach ($quickLinks as $link) : ?>
            <a class="ak-account-dashboard__link" href="<?php echo esc_url($link['url']); ?>">
                <span><?php echo esc_html($link['label']); ?></span>
                <small><?php echo esc_html($link['text']); ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php
do_action('woocommerce_account_dashboard');
do_action('woocommerce_before_my_account');
do_action('woocommerce_after_my_account');
