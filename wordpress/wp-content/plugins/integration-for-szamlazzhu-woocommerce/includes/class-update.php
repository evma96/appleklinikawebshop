<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Szamlazz_Update_Database', false ) ) :

	class WC_Szamlazz_Update_Database {

		public static function init() {

			//Store and check version number and schedule update actions if needed
			add_action( 'admin_init', array( __CLASS__, 'check_version' ) );

		}

		public static function check_version() {
			$existing_version = get_option('wc_szamlazz_version_number');
			$new_version = WC_Szamlazz()::$version;

			//If plugin is updated, schedule imports(maybe a new provider was added for example)
			if(!$existing_version || ($existing_version != $new_version)) {
				update_option('wc_szamlazz_version_number', $new_version);

				//Run db updates if needed
				if(version_compare('6.0', $existing_version, '>')) {
					self::wc_szamlazz_update_600();
				}

			}
		}

		public static function wc_szamlazz_update_600() {

			//Get existing settings
			$settings = get_option( 'woocommerce_wc_szamlazz_settings', null );

			//Settings to update for sure
			$settings_to_update = array('agent_key', 'multiple_accounts', 'vat_number_position', 'vat_number_alignment', 'vat_number_autofill', 'vat_number_eu', 'eu_vat_exempt', 'auto_email', 'auto_email_replyto', 'auto_email_subject', 'auto_email_message', 'email_attachment', 'email_attachment_file', 'email_attachment_invoice', 'email_attachment_proform', 'email_attachment_deposit', 'email_attachment_void', 'email_attachment_delivery', 'email_attachment_position', 'customer_download', 'invoice_forward', 'receipt', 'receipt_prefix', 'receipt_note', 'receipt_email', 'receipt_email_subject', 'receipt_email_text', 'receipt_email_replyto', 'receipt_template', 'receipt_hidden_fields', 'receipts_invalid_payment_methods', 'debug', 'error_email', 'corrected', 'tools', 'grouped_invoice_status', 'custom_order_statues', 'eusafa_custom', 'accounting_details_enabled', 'kata_compatibility', 'vat_exempt_abroad', 'invoice_type', 'payment_deadline', 'afakulcs', 'vat_overrides_custom', 'afakulcs_eu', 'afakulcs_euk', 'prefix', 'language', 'unit_type', 'company_name', 'hide_shipping_details', 'hide_item_notes', 'bank_name', 'bank_number', 'template', 'advanced_settings', 'auto_invoice_custom', 'payment_methods', 'ipn_close_order', 'delivery_note', 'separate_coupon', 'separate_coupon_name', 'separate_coupon_desc', 'discount_note', 'hide_free_shipping', 'disable_free_order', 'hide_free_items', 'accounting_details_vevo_azonosito', 'compat_bookings_comment', 'compat_prodcut_bundles_hide_free_items', 'compat_subscriptions_proform', 'compat_subscriptions_invoice');

			//Move settings to new options
			foreach ($settings_to_update as $setting) {
				if(isset($settings[$setting])) {
					update_option('wc_szamlazz_'.$setting, $settings[$setting]);
				}
			}

			//Check for VAT number separately, since settings id has changed
			if(isset($settings['vat_number_form']) && $settings['vat_number_form'] == 'yes') {
				if(isset($setting['vat_number_type'])) {
					update_option('wc_szamlazz_vat_number_type', $settings['vat_number_type']);
				} else {
					update_option('wc_szamlazz_vat_number_type', 'yes');
				}
			}

			//Invoice type for company
			if(isset($settings['invoice_type_company']) && $settings['invoice_type_company'] == 'paper') {
				update_option('wc_szamlazz_invoice_type_company', 'yes');
			}

			//Just in case save the old one as backup
			update_option('woocommerce_wc_szamlazz_settings_old', $settings);

			//And delete old settings
			delete_option('woocommerce_wc_szamlazz_settings');

			return true;
		}

	}

	WC_Szamlazz_Update_Database::init();

endif;
