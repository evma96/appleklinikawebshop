<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Szamlazz_Helpers', false ) ) :

	class WC_Szamlazz_Helpers {

		//Get supported languages
		public static function get_supported_languages() {
			return apply_filters('wc_szamlazz_supported_languages', array(
				'hu' => __( 'Hungarian', 'wc-szamlazz' ),
				'de' => __( 'German', 'wc-szamlazz' ),
				'en' => __( 'English', 'wc-szamlazz' ),
				'it' => __( 'Italian', 'wc-szamlazz' ),
				'fr' => __( 'French', 'wc-szamlazz' ),
				'hr' => __( 'Croatian', 'wc-szamlazz' ),
				'ro' => __( 'Romanian', 'wc-szamlazz' ),
				'sk' => __( 'Slovak', 'wc-szamlazz' ),
				'si' => __( 'Slovenian', 'wc-szamlazz' ),
				'es' => __( 'Spanish', 'wc-szamlazz' ),
				'pl' => __( 'Polish', 'wc-szamlazz' ),
				'cz' => __( 'Czech', 'wc-szamlazz' ),
			));
		}

		//Get document type ids and labels
		public static function get_document_types() {
			return apply_filters('wc_szamlazz_document_types', array(
				'deposit' => esc_html__('Deposit invoice','wc-szamlazz'),
				'proform' => esc_html__('Proforma invoice','wc-szamlazz'),
				'invoice' => esc_html__('Invoice','wc-szamlazz'),
				'receipt' => esc_html__('Receipt','wc-szamlazz'),
				'delivery' => esc_html__('Delivery note','wc-szamlazz'),
				'void' => esc_html__('Reverse invoice','wc-szamlazz'),
				'void_receipt' => esc_html__('Reverse receipt','wc-szamlazz'),
				'corrected' => esc_html__('Correction invoice','wc-szamlazz')
			));
		}

		//Get available valid VAT types
		public static function get_vat_types($valid_tax_labels = false) {
			$default = array(
				'' => __( 'Default', 'wc-szamlazz' ),
			);

			$text_types = array(
				'TAM' => __( 'TAM', 'wc-szamlazz' ),
				'AAM' => __( 'AAM', 'wc-szamlazz' ),
				'EU' => __( 'EU', 'wc-szamlazz' ),
				'EUT' => __( 'EUT', 'wc-szamlazz' ),
				'EUK' => __( 'EUK', 'wc-szamlazz' ),
				'EUKT' => __( 'EUKT', 'wc-szamlazz' ),
				'MAA' => __( 'MAA', 'wc-szamlazz' ),
				'F.AFA' => __( 'F.AFA', 'wc-szamlazz' ),
				'K.AFA' => __( 'K.AFA', 'wc-szamlazz' ),
				'ÁKK' => __( 'ÁKK', 'wc-szamlazz' ),
				'TAHK' => __( 'TAHK', 'wc-szamlazz' ),
				'TEHK' => __( 'TEHK', 'wc-szamlazz' ),
				'EUE' => __( 'EUE', 'wc-szamlazz' ),
				'EUFADE' => __( 'EUFADE', 'wc-szamlazz' ),
				'EUFAD37' => __( 'EUFAD37', 'wc-szamlazz' ),
				'ATK' => __( 'ATK', 'wc-szamlazz' ),
				'NAM' => __( 'NAM', 'wc-szamlazz' ),
				'EAM' => __( 'EAM', 'wc-szamlazz' ),
				'KBAUK' => __( 'KBAUK', 'wc-szamlazz' ),
				'KBAET' => __( 'KBAET', 'wc-szamlazz' ),
				'HO' => __( 'HO', 'wc-szamlazz' )
			);

			$number_types = array(
				'0' => '0%',
				'1' => '1%',
				'2' => '2%',
				'2.1' => '2,1%',
				'3' => '3%',
				'4' => '4%',
				'4.8' => '4,8%',
				'5' => '5%',
				'5.5' => '5,5%',
				'6' => '6%',
				'7' => '7%',
				'7.7' => '7,7%',
				'8' => '8%',
				'8.1' => '8,1%',
				'9' => '9%',
				'9.5' => '9,5%',
				'10' => '10%',
				'11' => '11%',
				'12' => '12%',
				'13' => '13%',
				'13.5' => '13,5%',
				'14' => '14%',
				'15' => '15%',
				'16' => '16%',
				'17' => '17%',
				'18' => '18%',
				'19' => '19%',
				'20' => '20%',
				'21' => '21%',
				'22' => '22%',
				'23' => '23%',
				'24' => '24%',
				'25' => '25%',
				'25.5' => '25,5%',
				'26' => '26%',
				'27' => '27%',
			);

			if($valid_tax_labels) {
				return $text_types;
			}

			return apply_filters('wc_szamlazz_vat_types',$default+$text_types+$number_types);
		}

		//Duplicate wc_display_item_meta for small customizations(mainly to hide the backordered meta info)
		public static function get_item_meta($item, $args) {
			$strings = array();
			$html = '';
			$args    = wp_parse_args(
				$args,
				array(
					'before'       => '<ul class="wc-item-meta"><li>',
					'after'        => '</li></ul>',
					'separator'    => '</li><li>',
					'echo'         => true,
					'autop'        => false,
					'label_before' => '<strong class="wc-item-meta-label">',
					'label_after'  => ':</strong> ',
				)
			);

			foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
				if(__( 'Backordered', 'woocommerce' ) == $meta->key) continue;
				$value     = wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
				$strings[] = wp_kses_post( $meta->display_key ) . $args['label_after'] . $value;
			}

			if ( $strings ) {
				$html = $args['before'] . implode( $args['separator'], $strings );
			}

			return apply_filters( 'woocommerce_display_item_meta', $html, $item, $args );
		}

		//Get the language of the invoice
		public static function get_order_language($order) {
			$orderId = $order->get_id();
			$lang_code = WC_Szamlazz()->get_option('language', 'hu');

			if(true) {
				$wpml_lang_code = $order->get_meta('wpml_language');
				if(!$wpml_lang_code && function_exists('pll_get_post_language')){
					$wpml_lang_code = pll_get_post_language($orderId, 'slug');
				}
				if($wpml_lang_code && in_array($wpml_lang_code, array('hu', 'de', 'en', 'it', 'fr', 'hr', 'ro', 'sk', 'es', 'pl', 'cz'))) {
					$lang_code = $wpml_lang_code;
				}
			}

			return apply_filters('wc_szamlazz_get_order_language', $lang_code, $order);
		}

		//Helper to get order currency
		public static function get_currency($order) {
			$currency = $order->get_currency() ?: 'HUF';
			$invoice_lang = WC_Szamlazz()->get_option('language', 'hu');
			if($currency == 'HUF' && $invoice_lang == 'hu') $currency = 'Ft';
			return $currency;
		}

		public static function is_order_hungarian($order) {
			return $order->get_billing_country() == 'HU';
		}

		public static function get_invoice_type($order) {
			$type = WC_Szamlazz()->get_option('invoice_type', 'paper');
			if($order->get_billing_company() && WC_Szamlazz()->get_option('invoice_type_company') && WC_Szamlazz()->get_option('invoice_type_company', 'no') == 'yes') {
				$type = 'paper';
			}
			return ($type == 'electronic') ? 'true' : 'false';
		}

		//Get available checkout methods and ayment gateways
		public static function get_available_payment_gateways() {
			$available_gateways = WC()->payment_gateways->payment_gateways();
			$available = array();
			$available['none'] = __('Select a payment method','wc-szamlazz');
			foreach ($available_gateways as $available_gateway) {
				$available[$available_gateway->id] = $available_gateway->title;
			}
			return $available;
		}

		//Replace placeholders in invoice note
		public static function replace_note_placeholders($note, $order) {

			//Setup replacements
			$note_replacements = apply_filters('wc_szamlazz_get_order_note_placeholders', array(
				'{customer_email}' => $order->get_billing_email(),
				'{customer_phone}' => $order->get_billing_phone(),
				'{order_number}' => $order->get_order_number(),
				'{transaction_id}' => $order->get_transaction_id(),
				'{shipping_address}' => preg_replace('/\<br(\s*)?\/?\>/i', "\n", $order->get_formatted_shipping_address()),
				'{customer_note}' => $order->get_customer_note(),
				'{customer_notes}' => $order->get_customer_note(),
				'{current_date}' => date_i18n('Y-m-d')
			), $order);

			//Replace stuff:
			$note = str_replace( array_keys( $note_replacements ), array_values( $note_replacements ), $note);

			//Replace shortcodes
			$note = do_shortcode($note);

			//Return fixed note
			return esc_html($note);
		}

		//Helper function to check vat override settings
		public static function check_vat_override($line_item_type, $auto_vat, $order, $order_item = false) {

			//Set new vat
			$vat = $auto_vat;

			//Check for overrides
			$vat_overrides = get_option('wc_szamlazz_vat_overrides');

			//Skip if theres no override setup
			if((WC_Szamlazz()->get_option('vat_overrides_custom', 'no') == 'no') || !$vat_overrides) {
				return $vat;
			}

			//Get order type
			$order_details = WC_Szamlazz_Conditions::get_order_details($order, 'vat_overrides');

			//When manually creating invoices and the selected account is different
			if(isset( $_POST['action']) && $_POST['action'] == 'wc_szamlazz_generate_invoice' && isset($_POST['account'])) {
				$order_details['account'] = sanitize_text_field($_POST['account']);
			}

			//If its a product item, check for category and attribute
			$product_categories = array();
			if($line_item_type == 'product') {
				if($order_item->get_product()) {
					$order_details['product_categories'] = $product_categories + wp_get_post_terms( $order_item->get_product_id(), 'product_cat', array('fields' => 'ids') );
					$order_details['product_attribute'] = array();
					$product = $order_item->get_product();
					if($product && $product->get_attributes()) {
						foreach ($product->get_attributes() as $attribute) {
							if(is_object($attribute)) {
								$terms = $attribute->get_terms();
								if($terms) {
									foreach ($terms as $term) {
										$order_details['product_attribute'][] = $term->term_id;
									}
								}
							}
						}
					}
				}
			}

			//We will return the matched automations at the end
			$final_automations = array();

			//Loop through each automation
			foreach ($vat_overrides as $automation_id => $automation) {

				//Check if trigger is a match. If not, just skip
				$is_match = apply_filters('wc_szamlazz_check_vat_override_line_item', ($automation['line_item'] == $line_item_type), $automation, $line_item_type, $order, $order_item);
				if(!$is_match) {
					continue;
				}

				if($automation['conditional']) {

					//Compare conditions with order details and see if we have a match
					$automation_is_a_match = WC_Szamlazz_Conditions::match_conditions($vat_overrides, $automation_id, $order_details);

					//If its not a match, continue to next not
					if(!$automation_is_a_match) continue;

					//If its a match, add to found automations
					$final_automations[] = $automation;

				} else {

					$final_automations[] = $automation;

				}

			}

			//If we found some automations, try to generate documents
			if(count($final_automations) > 0) {
				foreach ($final_automations as $final_automation) {
					$vat = $final_automation['vat_type'];
				}
			}

			//Convert percentage values to float
			$float_value = (float) $vat;
			if ( strval($float_value) == $vat ) {
				$vat = $float_value;
			}

			return $vat;
		}

		//Helper function to check vat override settings
		public static function check_eusafa($order) {

			//Check for overrides
			$eusafas = get_option('wc_szamlazz_eusafa');

			//Skip if theres no override setup
			if((WC_Szamlazz()->get_option('eusafa_custom', 'no') == 'no') || !$eusafas) {
				return 'false';
			}

			//Get order type
			$order_details = WC_Szamlazz_Conditions::get_order_details($order, 'eusafas');

			//We will return the matched automations at the end
			$is_eusafa = array();

			//Loop through each automation
			foreach ($eusafas as $automation_id => $automation) {

				if($automation['conditional']) {

					//Compare conditions with order details and see if we have a match
					$automation_is_a_match = WC_Szamlazz_Conditions::match_conditions($eusafas, $automation_id, $order_details);

					//If its not a match, continue to next not
					if(!$automation_is_a_match) continue;

					//If its a match, add to found automations
					$is_eusafa[] = $automation;

				} else {

					$is_eusafa[] = $automation;

				}

			}

			//If we found some automations, try to generate documents
			if(count($is_eusafa) > 0) {
				return 'true';
			}

			return 'false';
		}

		public static function get_default_bulk_actions() {
			$defaults = array('generate_invoices', 'generate_documents', 'download_invoices');
			return $defaults;
		}

		public static function get_payment_methods() {
			$available_gateways = WC()->payment_gateways->payment_gateways();
			$payment_methods = array();
			foreach ($available_gateways as $available_gateway) {
				if($available_gateway->enabled == 'yes') {
					$payment_methods[$available_gateway->id] = $available_gateway->title;
				}
			}
			return $payment_methods;
		}

		public static function get_shipping_methods() {
			$active_methods = array();
			$custom_zones = WC_Shipping_Zones::get_zones();
			$worldwide_zone = new WC_Shipping_Zone( 0 );
			$worldwide_methods = $worldwide_zone->get_shipping_methods();

			foreach ( $custom_zones as $zone ) {
				$shipping_methods = $zone['shipping_methods'];
				foreach ($shipping_methods as $shipping_method) {
					if ( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled ) {
						$method_title = $shipping_method->method_title;
						$active_methods[$shipping_method->id.':'.$shipping_method->instance_id] = $method_title.' ('.$zone['zone_name'].')';
					}
				}
			}

			foreach ($worldwide_methods as $shipping_method_id => $shipping_method) {
				if ( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled ) {
					$method_title = $shipping_method->method_title;
					$active_methods[$shipping_method->id.':'.$shipping_method->instance_id] = $method_title.' (Worldwide)';
				}
			}

			return $active_methods;
		}

		public static function get_shipping_classes() {
			$shipping_classes = WC()->shipping()->get_shipping_classes();
			$available_classes = array();
			foreach ($shipping_classes as $shipping_class) {
				$available_classes[$shipping_class->slug] = $shipping_class->name;
			}
			return $available_classes;
		}

		public static function validate_kata($szamla) {
			$compatible = true;

			//Check if VAT number submitted for some reason
			if($szamla->vevo->adoszam != '') $compatible = false;
			if($szamla->vevo->adoszamEU != '') $compatible = false;

			//Check customer name field for company related data
			$customer_name = $szamla->vevo->nev;
			$customer_name = strtolower($customer_name);
			$invalid_strings = array(' bt', ' kft', ' rt', ' zrt', 'bt.', 'kft.', 'rt.', 'zrt.');
			$has_invalid_data = ($customer_name != str_ireplace($invalid_strings,"XX",$customer_name))? true: false;
			if($has_invalid_data) $compatible = false;

			return $compatible;
		}

		public static function get_user_roles() {
			$roles = get_editable_roles();
			$available_roles = array();
			foreach ($roles as $role_id => $role) {
				$available_roles[$role_id] = $role['name'];
			}
			return $available_roles;
		}
	}

endif;
