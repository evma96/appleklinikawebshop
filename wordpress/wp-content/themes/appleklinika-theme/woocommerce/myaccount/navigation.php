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
    'edit-account' => '<path d="M12 12.2a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5.3 20a6.9 6.9 0 0 1 13.4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'kedvelt-termekek' => '<path d="M12 19.2s-6.8-4.15-6.8-9.05A3.9 3.9 0 0 1 12 7.55a3.9 3.9 0 0 1 6.8 2.6c0 4.9-6.8 9.05-6.8 9.05Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    'customer-logout' => '<path d="M10.5 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19h3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 8l4 4-4 4M9 12h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
];

$currentEndpoint = function_exists('appleklinika_current_account_endpoint_key') ? appleklinika_current_account_endpoint_key() : 'dashboard';
?>

<nav class="woocommerce-MyAccount-navigation ak-account-sidebar" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
    <p class="ak-account-sidebar__title">Fiókom</p>
    <ul class="ak-account-menu">
        <?php foreach (wc_get_account_menu_items() as $endpoint => $label) : ?>
            <?php
            $isCurrent = $endpoint === $currentEndpoint || ($endpoint === 'orders' && $currentEndpoint === 'view-order');
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
