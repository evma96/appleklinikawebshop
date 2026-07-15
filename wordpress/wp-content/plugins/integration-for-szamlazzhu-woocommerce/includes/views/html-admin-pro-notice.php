<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>


<div class="wc-szamlazz-settings wc-szamlazz-pro-widget">
	<ul>
		<li><?php esc_html_e('Create invoices automatically based on the order status', 'wc-szamlazz'); ?></li>
		<li><?php esc_html_e('Handle bank transfers automatically using Autokassza', 'wc-szamlazz'); ?></li>
		<li><?php esc_html_e('Attach the invoices to the e-mails sent by WooCommerce', 'wc-szamlazz'); ?></li>
		<li><?php esc_html_e('Simplify the purchase process for your private customers with e-receipts', 'wc-szamlazz'); ?></li>
		<li><?php esc_html_e('Advanced features, including multiple accounts, bulk actions and advanced tax rates', 'wc-szamlazz'); ?></li>
	</ul>
    <div class="wc-szamlazz-pro-widget-cta">
		<a class="button button-primary button-hero" href="https://visztpeter.me/woocommerce-szamlazz-hu/">
            <span class="dashicons dashicons-cart"></span> 
            <span><?php esc_html_e( 'Purchase PRO version', 'wc-szamlazz' ); ?></span>
        </a>
		<span>
			<strong><small><?php esc_html_e('from', 'wc-szamlazz'); ?></small> <span><?php esc_html_e( '30 EUR / year', 'wc-szamlazz' ); ?></span></strong>
		</span>
	</div>
	<div class="wc-szamlazz-pro-widget-activate">
		<input class="input-text regular-input" type="text" name="woocommerce_wc_szamlazz_pro_key" id="woocommerce_wc_szamlazz_pro_key" value="" placeholder="<?php esc_html_e( 'License key', 'wc-szamlazz' ); ?>">
		<button class="button" type="button" id="wc_szamlazz_activate_pro"><?php _e('Activate', 'wc-szamlazz'); ?></button>
    </div>
    <div class="wc-szamlazz-pro-widget-notice" style="display:none">
		<span class="dashicons dashicons-warning"></span>
		<p></p>
	</div>
    
</div>
