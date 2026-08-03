<?php
/**
 * Apple Klinika My Account navigation.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_navigation');

$icons = [
    'dashboard' => '<path d="M4 11.5 12 5l8 6.5v7a1.5 1.5 0 0 1-1.5 1.5h-4.25v-5.25h-4.5V20H5.5A1.5 1.5 0 0 1 4 18.5v-7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    'orders' => '<path d="M6.5 4.5h11v15h-11v-15Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 8h6M9 12h6M9 16h3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'beszamitasaim' => '<path d="M7 7.5h8.5l1.5 2.2V17a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9.7l2-2.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.5 12h6M12 9v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'garanciaim' => '<path d="M12 4.5 18 7v4.6c0 3.8-2.35 6.5-6 8-3.65-1.5-6-4.2-6-8V7l6-2.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9.3 12.2 1.7 1.7 3.7-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'visszakuldesek' => '<path d="M7 8h9a4 4 0 1 1 0 8H8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m8 5-4 3.5L8 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'cimeim' => '<path d="M4.5 10.5 12 4l7.5 6.5V20h-15v-9.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 13h8M8 16.5h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'edit-account' => '<path d="M12 12.2a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5.3 20a6.9 6.9 0 0 1 13.4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'kedvelt-termekek' => '<path d="M12 19.2s-6.8-4.15-6.8-9.05A3.9 3.9 0 0 1 12 7.55a3.9 3.9 0 0 1 6.8 2.6c0 4.9-6.8 9.05-6.8 9.05Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    'customer-logout' => '<path d="M10.5 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19h3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 8l4 4-4 4M9 12h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
];

$currentEndpoint = function_exists('appleklinika_current_account_endpoint_key') ? appleklinika_current_account_endpoint_key() : 'dashboard';
$currentUser = wp_get_current_user();
$displayName = $currentUser instanceof WP_User && $currentUser->display_name !== '' ? $currentUser->display_name : 'Apple Klinika';
$email = $currentUser instanceof WP_User ? $currentUser->user_email : '';
$initials = function_exists('appleklinika_account_initials') ? appleklinika_account_initials($currentUser) : 'AK';
?>

<nav class="woocommerce-MyAccount-navigation ak-account-sidebar" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
    <div class="ak-account-sidebar__profile">
        <span class="ak-account-sidebar__avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
        <div class="ak-account-sidebar__profile-text">
            <strong><?php echo esc_html($displayName); ?></strong>
            <?php if ($email !== '') : ?>
                <small><?php echo esc_html($email); ?></small>
            <?php endif; ?>
        </div>
    </div>
    <p class="ak-account-sidebar__title">Fiókom</p>
    <ul class="ak-account-menu">
        <?php foreach (wc_get_account_menu_items() as $endpoint => $label) : ?>
            <?php
            $isCurrent = $endpoint === $currentEndpoint
                || ($endpoint === 'orders' && $currentEndpoint === 'view-order')
                || ($endpoint === 'beszamitasaim' && $currentEndpoint === 'eladasaim');
            $classes = wc_get_account_menu_item_classes($endpoint);
            if ($isCurrent && strpos($classes, 'is-active') === false) {
                $classes .= ' is-active';
            }
            ?>
            <li class="<?php echo esc_attr($classes); ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>" <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>>
                    <span class="ak-account-menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <?php echo $icons[$endpoint] ?? $icons['dashboard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </svg>
                    </span>
                    <?php echo esc_html($label); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php do_action('woocommerce_after_account_navigation'); ?>
