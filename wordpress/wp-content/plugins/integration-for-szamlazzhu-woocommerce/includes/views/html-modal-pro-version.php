<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script type="text/template" id="tmpl-wc-szamlazz-modal-pro-version">
	<div class="wc-backbone-modal wc-szamlazz-modal-pro-version">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1>
						<?php if(WC_Szamlazz_Pro::is_pro_enabled()): ?>
							<span class="dashicons dashicons-yes-alt"></span> <?php _e('The PRO version is active', 'wc-szamlazz'); ?>
						<?php else: ?>
							<span class="dashicons dashicons-warning"></span> <?php _e('The PRO version is expired', 'wc-szamlazz'); ?>
						<?php endif; ?>
					</h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'wc-szamlazz' ); ?></span>
					</button>
				</header>
				<?php if(WC_Szamlazz_Pro::is_pro_enabled() || (!WC_Szamlazz_Pro::is_pro_enabled() && WC_Szamlazz_Pro::get_license_key())): ?>
					<article class="wc-szamlazz-modal-pro-version-content wc-szamlazz-settings-widget-pro-<?php if(WC_Szamlazz_Pro::is_pro_enabled()): ?>state-active<?php else: ?>state-expired<?php endif; ?>">
						<p>
							<span class="wc-szamlazz-settings-widget-pro-label"><?php _e('License key', 'wc-szamlazz'); ?></span><br>
							<?php echo esc_html(WC_Szamlazz_Pro::get_license_key()); ?>
						</p>
						<?php $license = WC_Szamlazz_Pro::get_license_key_meta(); ?>
						<?php if(isset($license['type'])): ?>
						<p class="single-license-info">
							<span class="wc-szamlazz-settings-widget-pro-label"><?php _e('License type', 'wc-szamlazz'); ?></span><br>
							<?php if ( $license['type'] == 'unlimited' ): ?>
								<?php _e( 'Unlimited', 'wc-szamlazz' ); ?>
							<?php else: ?>
								<?php _e( 'Subscription', 'wc-szamlazz' ); ?>
							<?php endif; ?>
						</p>
						<?php endif; ?>
						<?php if(isset($license['next_payment'])): ?>
						<p class="single-license-info">
							<span class="wc-szamlazz-settings-widget-pro-label"><?php _e('Next payment', 'wc-szamlazz'); ?></span><br>
							<?php echo esc_html($license['next_payment']); ?>
						</p>
						<?php endif; ?>
						<p><?php esc_html_e( 'If you want to activate the license on another website, you must first deactivate it on this website.', 'wc-szamlazz' ); ?></p>
					</article>
				<?php endif; ?>
				<footer>
					<div class="inner">
						<a class="button-secondary" id="wc_szamlazz_deactivate_pro"><?php esc_html_e( 'Deactivate license', 'wc-szamlazz' ); ?></a>
						<a class="button-secondary" id="wc_szamlazz_validate_pro"><?php esc_html_e( 'Reload license', 'wc-szamlazz' ); ?></a>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
