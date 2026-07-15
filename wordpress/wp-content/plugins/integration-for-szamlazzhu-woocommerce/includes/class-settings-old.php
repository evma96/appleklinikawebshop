<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Szamlazz_Settings_Old' ) ) :
	class WC_Szamlazz_Settings_Old extends WC_Integration {
		public function __construct() {
			$this->id = 'wc_szamlazz';
			$this->method_title = __( 'Számlázz.hu', 'wc-szamlazz' );

			add_action('woocommerce_settings_page_init', function(){
				if(isset($_GET['tab']) && $_GET['tab'] == 'integration' && isset($_GET['section']) && $_GET['section'] == 'wc_szamlazz') {
					wp_redirect(admin_url('admin.php?page=wc-settings&tab=wc-szamlazz'));
				}
			});
		}
	}
endif;