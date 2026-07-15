<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wc-szamlazz-settings-submenu">
    <?php if(WC_Szamlazz_Pro::is_pro_enabled() || (!WC_Szamlazz_Pro::is_pro_enabled() && WC_Szamlazz_Pro::get_license_key())): ?>
        <?php if(WC_Szamlazz_Pro::is_pro_enabled()): ?>
            <a href="#" class="wc-szamlazz-settings-submenu-pro button pro-active">
                <span class="dashicons dashicons-yes-alt"></span> <?php _e('The PRO version is active', 'wc-szamlazz'); ?>
            </a>
        <?php else: ?>
            <a href="#" class="wc-szamlazz-settings-submenu-pro button expired">
                <span class="dashicons dashicons-warning"></span> <?php _e('The PRO version is expired', 'wc-szamlazz'); ?>
            </a>
        <?php endif; ?>
    <?php else: ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc-szamlazz&section=automations' ) ); ?>" class="wc-szamlazz-settings-submenu-pro-setup button">
            <span class="dashicons dashicons-cart"></span> <?php _e('Setup PRO version', 'wc-szamlazz'); ?>
        </a>
    <?php endif; ?>
    <a class="button" href="https://visztpeter.me/dokumentacio/" target="_blank"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Documentation', 'wc-szamlazz' ); ?></a>
    <a class="button" href="https://wordpress.org/support/plugin/integration-for-szamlazzhu-woocommerce/" target="_blank"><span class="dashicons dashicons-admin-comments"></span> <?php esc_html_e( 'Forum', 'wc-szamlazz' ); ?></a>
    <a class="button" href="https://wordpress.org/support/plugin/integration-for-szamlazzhu-woocommerce/reviews/#new-post" target="_blank"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Review', 'wc-szamlazz' ); ?></a>


    

    <?php include( dirname( __FILE__ ) . '/html-modal-pro-version.php' ); ?>

</div>

