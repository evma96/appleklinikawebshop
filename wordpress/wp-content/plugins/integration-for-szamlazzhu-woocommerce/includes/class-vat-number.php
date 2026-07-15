<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Szamlazz_Vat_Number_Field', false ) ) :
	class WC_Szamlazz_Vat_Number_Field {

		private static $country_codes_patterns = array(
			'AT' => 'U[A-Z\d]{8}',
			'BE' => '[01]\d{9}',
			'BG' => '\d{9,10}',
			'CY' => '\d{8}[A-Z]',
			'CZ' => '\d{8,10}',
			'DE' => '\d{9}',
			'DK' => '(\d{2} ?){3}\d{2}',
			'EE' => '\d{9}',
			'EL' => '\d{9}',
			'ES' => '[A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8}',
			'FI' => '\d{8}',
			'FR' => '([A-Z]{2}|[A-Z0-9]{2})\d{9}',
			'GB' => '(\d{9}|\d{12}|(GD|HA)\d{3})',
			'XI' => '(\d{9}|\d{12}|(GD|HA)\d{3})',
			'HR' => '\d{11}',
			'HU' => '\d{8}',
			'IE' => '[A-Z\d]{8,10}',
			'IT' => '\d{11}',
			'LT' => '(\d{9}|\d{12})',
			'LU' => '\d{8}',
			'LV' => '\d{11}',
			'MT' => '\d{8}',
			'NL' => '\d{9}B\d{2}',
			'PL' => '\d{10}',
			'PT' => '\d{9}',
			'RO' => '\d{2,10}',
			'SE' => '\d{12}',
			'SI' => '\d{8}',
			'SK' => '\d{10}',
		);

		//Validate EU VAT number format
		private static function validate_eu_vat_format($vat_number) {
			//Remove extra characters
			$vat_number = preg_replace('/[^A-Z0-9]/', '', strtoupper($vat_number));
			
			//Extract country code (first 2 characters)
			if(strlen($vat_number) < 3) {
				return false;
			}
			
			$country_code = substr($vat_number, 0, 2);
			$vat_number_part = substr($vat_number, 2);
			
			//Check if we have a pattern for this country
			if(!isset(self::$country_codes_patterns[$country_code])) {
				return false;
			}
			
			//Validate against country-specific pattern
			$pattern = '/^' . self::$country_codes_patterns[$country_code] . '$/';
			return preg_match($pattern, $vat_number_part) === 1;
		}

		//Init notices
		public static function init() {

			//Creates a new field at the checkout page
			add_filter( 'woocommerce_billing_fields' , array( __CLASS__, 'add_vat_number_checkout_field' ) );
			add_filter( 'woocommerce_checkout_fields' , array( __CLASS__, 'align_vat_number_checkout_field' ) );

			//Validate the vat number on checkout
			add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'vat_number_validate' ), 10, 2);

			//Saves the value to order meta(key _billing_wc_szamlazz_adoszam)
			add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_vat_number' ) );

			//Save the VAT number on the user's profile
			add_action( 'woocommerce_checkout_update_user_meta', array( __CLASS__, 'update_customer_meta' ) );

			//Dispaly the VAT number on the user's profile
			add_filter( 'woocommerce_customer_meta_fields', array( __CLASS__, 'customer_meta' ) );

			//On manual order creation, return the vat number too if customer selected
			add_filter( 'woocommerce_ajax_get_customer_details', array( __CLASS__, 'add_vat_to_customer_details'), 10, 3 );

			//Display the VAT number in the admin order page
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'display_vat_number' ) );
			add_action( 'woocommerce_admin_billing_fields', array( __CLASS__, 'display_vat_number_in_admin' ) );

			//Display the VAT number in the addresses(after the company name, (...))
			add_filter( 'woocommerce_my_account_my_address_formatted_address', array( __CLASS__, 'add_vat_number_to_my_formatted_address'), 10, 3);
			add_filter( 'woocommerce_localisation_address_formats', array( __CLASS__, 'add_vat_number_to_address'));
			add_filter( 'woocommerce_formatted_address_replacements', array( __CLASS__, 'replace_vat_number_in_address'), 10, 2);
			add_filter( 'woocommerce_order_formatted_billing_address', function( $address, $order ) {
				if($address) {
					$taxnumber = self::get_order_vat_number($order);
					$address['wc_szamlazz_adoszam'] = $taxnumber != '' ? $taxnumber : '';
				}
				return $address;
			}, 10, 2 );

			//Just a helper to merge old meta key to the new format
			add_action( 'woocommerce_admin_order_data_after_order_details', function($order){
				if(!$order->get_meta('_billing_wc_szamlazz_adoszam') && $order->get_meta('wc_szamlazz_adoszam')) {
					$order->update_meta_data('_billing_wc_szamlazz_adoszam', $order->get_meta('wc_szamlazz_adoszam'));
					$order->save();
				}
			});

			//Ajax functions used on frontend
			add_action( 'wp_ajax_wc_szamlazz_check_vat_number', array( __CLASS__, 'check_vat_number_with_ajax' ) );
			add_action( 'wp_ajax_nopriv_wc_szamlazz_check_vat_number', array( __CLASS__, 'check_vat_number_with_ajax' ) );

			//VAT Exempt option for virtual company orders outside of the EU
			add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'vat_exempt_abroad'), 11 );
			add_action( 'woocommerce_checkout_process', array( __CLASS__, 'vat_exempt_abroad_save' ), 11 );

			//VAT Exempt for EU VAT Number orders
			if(WC_Szamlazz()->get_option('eu_vat_exempt', 'yes') == 'yes') {
				add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'vat_exempt_eu_vat'), 11 );
			}

			//B2B only option
			if(WC_Szamlazz()->get_option('vat_number_required', 'no') == 'yes') {
				add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'require_vat_number') );
			}			

		}

		//Helper function to get vat number(backward compatibility)
		public static function get_order_vat_number($order) {
			$vat_number = $order->get_meta('wc_szamlazz_adoszam');
			if($order->get_meta('_billing_wc_szamlazz_adoszam')) {
				$vat_number = $order->get_meta('_billing_wc_szamlazz_adoszam');
			}
			return $vat_number;
		}

		//Add vat number field to checkout page
		public static function add_vat_number_checkout_field($fields) {

			if(WC_Szamlazz()->get_option('vat_number_type', '') == 'toggle') {
				$fields['wc_szamlazz_company_toggle'] = array(
					 'label' => esc_html__('Company billing', 'wc-szamlazz'),
					 'type' => 'checkbox',
					 'class' => array( 'form-row-wide' ),
					 'required' => false,
					 'priority' => 29
				);
			}

			if(WC_Szamlazz()->get_option('vat_number_type', '') == 'radio') {
				$fields['wc_szamlazz_company_toggle_radio'] = array(
					 'label' => esc_html__('Are you buying as a private individual or a company?', 'wc-szamlazz'),
					 'type' => 'radio',
					 'class' => array( 'form-row-wide' ),
					 'required' => true,
					 'priority' => 29,
					 'default' => 'individual',
					 'options' => array(
						 'individual' => _x('Individual', 'customer type', 'wc-szamlazz'),
						 'company' => _x('Company', 'customer type', 'wc-szamlazz')
					 )
				);
			}

			$fields['wc_szamlazz_adoszam'] = array(
				 'label' => esc_html__('VAT number', 'wc-szamlazz'),
				 'placeholder' => _x('12345678-1-12', 'placeholder', 'wc-szamlazz'),
				 'required' => false,
				 'class' => array('form-row-wide'),
				 'clear' => true,
				 'priority' => WC_Szamlazz()->get_option('vat_number_position', 35)
			);

			//Hide placeholder, if EU VAT number is enabled
			if(WC_Szamlazz()->get_option('vat_number_eu', 'no') == 'yes') {
				$fields['wc_szamlazz_adoszam']['placeholder'] = '';
			}

			return $fields;
		}

		public static function align_vat_number_checkout_field($fields) {
			if(WC_Szamlazz()->get_option('vat_number_alignment', 'no') == 'yes') {
				$fields['billing']['billing_company']['class'] = array( 'form-row-first' );
				$fields['billing']['wc_szamlazz_adoszam']['class'] = array( 'form-row-last' );
				if(isset($fields['billing']['billing_vat_number'])) {
					$fields['billing']['billing_vat_number']['class'] = array( 'form-row-last' );
				}
			}
			return $fields;
		}

		public static function save_vat_number( $order_id ) {
			if ( ! empty( $_POST['wc_szamlazz_adoszam'] ) ) {
				$order = wc_get_order( $order_id );
				$vat_number = sanitize_text_field( $_POST['wc_szamlazz_adoszam'] );

				//Clean hungarian vat number format
				if(preg_match('/^\d{11}$/', $vat_number)) {
					$vat_number = preg_replace('/^(\d{8})(\d{1})(\d{2})$/', '$1-$2-$3', $vat_number);
				}
				$order->update_meta_data( '_billing_wc_szamlazz_adoszam', $vat_number );

				//Get fresh info from the API
				if(preg_match('/^[A-Z]{2}/', $vat_number)) {
					$adoszam_data = self::get_eu_vat_number_data($vat_number);
				} else {
					$adoszam_data = self::get_vat_number_data($vat_number);
				}

				//Save data
				if($adoszam_data) {
					$order->update_meta_data( '_wc_szamlazz_adoszam_data', $adoszam_data );
				}
				$order->save();
			}
		}

		public static function display_vat_number($order){
			if(!$order->get_meta('_billing_wc_szamlazz_adoszam')) {
				if($adoszam = $order->get_meta('wc_szamlazz_adoszam')) {
					echo '<p><strong>'.__('VAT number', 'wc-szamlazz').':</strong> ' . $adoszam . '</p>';
				}
			}
		}

		public static function display_vat_number_in_admin($billing_fields){
			$billing_fields['wc_szamlazz_adoszam'] = array(
				'label' => __( 'VAT number', 'wc-szamlazz' ),
				'show' => true,
			);
			return $billing_fields;
		}

		public static function vat_number_validate($fields, $errors) {

			//Check HU VAT Number
			if($fields['wc_szamlazz_adoszam'] && $fields['billing_country'] == 'HU') {

				//Validate general format
				if(preg_match('/^(\d{7})(\d)\-([1-5])\-(0[2-9]|[13][0-9]|2[02-9]|4[0-4]|51)$/', sanitize_text_field($fields['wc_szamlazz_adoszam'])) || preg_match('/^\d{11}$/', sanitize_text_field($fields['wc_szamlazz_adoszam']))) {

					//Check with the API too, but only if theres no more errors
					$error_codes = $errors->get_error_codes();
					if(empty( $error_codes )) {
						$adoszam_data = self::get_vat_number_data(sanitize_text_field($fields['wc_szamlazz_adoszam']));

						//Get ÁFA type
						$afa_type = $fields['wc_szamlazz_adoszam'][8]; //9th digit
						if(strpos($fields['wc_szamlazz_adoszam'], '-') !== false) {
							$afa_type = explode('-', $fields['wc_szamlazz_adoszam'])[1]; //1st digit after a -
						}
						$afa_type_invalid = false;

						//Check for VAT type in NAV response
						if($adoszam_data && isset($adoszam_data['vat_code']) && $adoszam_data['vat_code'] != intval($afa_type)) {
							$afa_type_invalid = true;
						}

						if($adoszam_data && (!$adoszam_data['valid'] || $afa_type_invalid)) {
							$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_nav_message', esc_html__( 'The VAT number is not valid.', 'wc-szamlazz'), $fields) );
						}
					}

				} else {
					$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_format_message', esc_html__( 'The VAT number format is not valid.', 'wc-szamlazz'), $fields) );
				}

			}

			//Check EU VAT Number
			$eu_countries = WC()->countries->get_european_union_countries();
			$check_eu_vat = (WC_Szamlazz()->get_option('vat_number_eu', 'no') == 'yes');
			if($fields['wc_szamlazz_adoszam'] && $check_eu_vat && in_array($fields['billing_country'], $eu_countries) && $fields['billing_country'] != 'HU') {

				//Validate general format
				if(preg_match('/^[A-Z]{2}/', sanitize_text_field($fields['wc_szamlazz_adoszam']))) {

					//Check if VAT number is EU, in that case we need to validate the billing country too
					$vat_number = sanitize_text_field($fields['wc_szamlazz_adoszam']);
					$country_code = substr($vat_number, 0, 2);
					$billing_country = $fields['billing_country'];
					$billing_country = self::get_vat_number_prefix($billing_country);
					if($country_code != $billing_country) {
						$errors->add( 'validation', apply_filters('wc_szamlazz_eu_vat_number_validation_country_mismatch_message', esc_html__( 'The VAT number is from another country, please select that country in the billing address.', 'wc-szamlazz'), $fields) );
					}

					//Check with the API too, but only if theres no more errors
					$error_codes = $errors->get_error_codes();
					if(empty( $error_codes )) {
						$adoszam_data = self::get_eu_vat_number_data(sanitize_text_field($fields['wc_szamlazz_adoszam']));
						if($adoszam_data && !$adoszam_data['valid'] && apply_filters( 'wc_szamlazz_should_fail_eu_vat_validation', true, $fields, $adoszam_data ) ) {
							$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_nav_message', esc_html__( 'The VAT number is not valid.', 'wc-szamlazz'), $fields) );
						}
					}

				} else {
					$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_format_message', esc_html__( 'The VAT number format is not valid.', 'wc-szamlazz'), $fields) );
				}

			}

			//Check for general errors, like missing vat number
			$ui_type = (WC_Szamlazz()->get_option('vat_number_always_show', 'no') == 'yes') ? 'show' : 'default';
			$ui_type = WC_Szamlazz()->get_option('vat_number_type', $ui_type);
			$global_vat = (WC_Szamlazz()->get_option('vat_number_global', 'no') == 'yes');

			if($fields['billing_country'] == 'HU' || ($check_eu_vat && in_array($fields['billing_country'], $eu_countries))) {
				if(isset($fields['billing_company']) && $fields['billing_company'] && !$fields['wc_szamlazz_adoszam']) {
					$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_required_message', esc_html__( 'If you enter a company name, the VAT number field is required.', 'wc-szamlazz'), $fields) );
				}

				if(isset($fields['wc_szamlazz_adoszam']) && $fields['wc_szamlazz_adoszam'] && !$fields['billing_company'] && $ui_type == 'show') {
					$errors->add( 'validation', apply_filters('wc_szamlazz_company_validation_required_message', esc_html__( 'If you enter a VAT number, the company name field is required.', 'wc-szamlazz'), $fields) );
				}

				if($ui_type == 'toggle' && isset($fields['wc_szamlazz_company_toggle']) && $fields['wc_szamlazz_company_toggle'] && (!$fields['billing_company'] || !$fields['wc_szamlazz_adoszam'])) {
					$errors->add( 'validation', apply_filters('wc_szamlazz_company_billing_validation_required_message', esc_html__( 'If you choose company billing, please enter both your company name and VAT number.', 'wc-szamlazz'), $fields) );
				}
			}

			//Global VAT: require VAT number for non-HU/EU countries when company name is filled (no format validation)
			if($global_vat && $fields['billing_country'] != 'HU' && !($check_eu_vat && in_array($fields['billing_country'], $eu_countries)) && apply_filters( 'wc_szamlazz_should_validate_global_vat', true, $fields )) {
				$has_company = (isset($fields['billing_company']) && $fields['billing_company']);
				$has_toggle = ($ui_type == 'toggle' && isset($fields['wc_szamlazz_company_toggle']) && $fields['wc_szamlazz_company_toggle']);
				$has_radio = ($ui_type == 'radio' && isset($fields['wc_szamlazz_company_toggle_radio']) && $fields['wc_szamlazz_company_toggle_radio'] == 'company');

				if($has_company || $has_toggle || $has_radio) {
					if(!$fields['wc_szamlazz_adoszam']) {
						$errors->add( 'validation', apply_filters('wc_szamlazz_tax_validation_required_message', esc_html__( 'If you enter a company name, the VAT number field is required.', 'wc-szamlazz'), $fields) );
					}
				}
			}
		}

		public static function get_vat_number_data($vat_number) {

			//Allow plugins to hook into the vat number validation
			do_action('wc_szamlazz_before_vat_number_validation', $vat_number);

			//Build Xml
			$szamla = new WCSzamlazzSimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmltaxpayer xmlns="http://www.szamlazz.hu/xmltaxpayer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmltaxpayer http://www.szamlazz.hu/docs/xsds/agent/xmltaxpayer.xsd"></xmltaxpayer>');
			$szamla->appendXML(WC_Szamlazz()->get_authentication_xml_object(false));
			$szamla->addChild('torzsszam', mb_substr($vat_number, 0, 8));
			$xml = $szamla->asXML();

			//Get response from Számlázz.hu
			$xml_response = WC_Szamlazz()->xml_generator->generate($xml, rand(), 'action-szamla_agent_taxpayer');
			$response = array();

			if($xml_response['error']) {
				return false;
			} else {
				$xml_response['agent_body'] = str_replace("ns2:","",$xml_response['agent_body']); //This is so php can convert it to normal arrays
				$xml_response['agent_body'] = str_replace("ns3:","",$xml_response['agent_body']); //This is so php can convert it to normal arrays
				$agent_body_xml = simplexml_load_string($xml_response['agent_body']);
				$json = json_encode($agent_body_xml);
				$array = json_decode($json,TRUE);

				if (array_key_exists('taxpayerValidity', $array)) {
					$is_valid = filter_var($array['taxpayerValidity'], FILTER_VALIDATE_BOOLEAN);

					$response = array(
						'valid' => $is_valid,
						'type' => 'HU'
					);

					$response['name'] = $array['taxpayerData']['taxpayerName'];

					if(isset($array['taxpayerData']) && isset($array['taxpayerData']['taxNumberDetail']) && isset($array['taxpayerData']['taxNumberDetail']['vatCode'])) {
						$response['vat_code'] = $array['taxpayerData']['taxNumberDetail']['vatCode'];
					}

					$address_details = false;
					if(
						isset($array['taxpayerData']) &&
						isset($array['taxpayerData']['taxpayerAddressList']) &&
						isset($array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem'])
					) {
						if(isset($array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem']['taxpayerAddress'])) {
							$address_details = $array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem']['taxpayerAddress'];
						}

						if(isset($array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem'][0]) && isset($array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem'][0]['taxpayerAddress'])) {
							$address_details = $array['taxpayerData']['taxpayerAddressList']['taxpayerAddressItem'][0]['taxpayerAddress'];
						}

					}

					if($address_details) {
						$address = $address_details;
						$available_fields = array('countryCode', 'postalCode', 'city', 'streetName', 'publicPlaceCategory', 'number', 'building', 'staircase', 'floor', 'door');
						$response['address'] = array();
						foreach ($available_fields as $field) {
							if(isset($address[$field])) {
								$response['address'][$field] = $address[$field];
							} else {
								$response['address'][$field] = '';
							}
						}
					}
				} else {
					$response = array(
						"valid" => 'unknown'
					);
				}

				return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number);
			}
		}

		public static function get_eu_vat_number_data($vat_number) {

			//Allow plugins to hook into the vat number validation
			do_action('wc_szamlazz_before_vat_number_validation', $vat_number);

			//Remove extra characters and validate format
			$vat_number = preg_replace('/[^A-Z0-9]/', '', strtoupper($vat_number));

			//Check with country-specific regex patterns
			if(!self::validate_eu_vat_format($vat_number)) {
				return array(
					"valid" => false
				);
			}

			//Setup response
			$response = array(
				'type' => 'EU',
				'valid' => false
			);

			//Get country code
			$country_code = substr($vat_number, 0, 2);
			$response['country_code'] = $country_code;

			//Get vat number
			$vat_number = substr($vat_number, 2);

			//Setup a request for api call
			$request = array(
				'countryCode' => $country_code,
				'vatNumber' => $vat_number
			);

			// Short-lived cache to avoid hammering VIES on repeated checkout AJAX calls
			$cache_key = 'wc_szamlazz_vies_' . md5($country_code . '|' . $vat_number);
			$cached    = get_transient($cache_key);
			if (is_array($cached)) {
				return apply_filters('wc_szamlazz_vat_number_validation_results', $cached, $vat_number, $cached);
			}

			$logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
			$log_ctx = array('source' => 'wc-szamlazz');

			// Helper: when VIES itself is unreachable / broken, don't block the checkout.
			$service_unavailable = function ($reason) use ($response, $cache_key, $vat_number, $logger, $log_ctx) {
				if ($logger) {
					$logger->warning('VIES unavailable, treating VAT as valid: ' . $reason, $log_ctx);
				}
				$response['valid'] = true;
				$response['note']  = 'VIES unavailable (' . $reason . '), treated as valid';
				set_transient('wc_szamlazz_vies_' . md5($vat_number), $response, 5 * MINUTE_IN_SECONDS);
				return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number, array());
			};

			// Send request via WP HTTP API
			$api_response = wp_remote_post('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', array(
				'timeout'     => 10, // VIES is slow; cap it so checkout doesn't stall
				'redirection' => 2,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'        => wp_json_encode($request),
			));

			// Transport-level failure (timeout, TCP reset, DNS, TLS, ...)
			if (is_wp_error($api_response)) {
				return $service_unavailable('transport: ' . $api_response->get_error_message());
			}

			$http_code = (int) wp_remote_retrieve_response_code($api_response);
			$raw_body  = (string) wp_remote_retrieve_body($api_response);

			// Non-2xx -> upstream tax office returned an error page
			if ($http_code < 200 || $http_code >= 300) {
				return $service_unavailable('HTTP ' . $http_code . ' body=' . substr($raw_body, 0, 200));
			}

			// Empty body -> connection dropped or VIES misbehaving
			if ($raw_body === '') {
				return $service_unavailable('empty response body');
			}

			$response_body = json_decode($raw_body, true);

			// Invalid JSON
			if (json_last_error() !== JSON_ERROR_NONE) {
				return $service_unavailable('invalid JSON: ' . json_last_error_msg() . ' body=' . substr($raw_body, 0, 200));
			}

			//Check for valid response
			if (!is_array($response_body) || !isset($response_body['valid'])) {
				if (isset($response_body['errorWrappers']) && is_array($response_body['errorWrappers'])) {
					foreach ($response_body['errorWrappers'] as $error_wrapper) {
						if (isset($error_wrapper['error']) && $error_wrapper['error'] === 'MS_MAX_CONCURRENT_REQ') {
							$response['valid'] = true;
							$response['note'] = 'Could not verify due to service rate limit, treated as valid';
							set_transient($cache_key, $response, 5 * MINUTE_IN_SECONDS);
							return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number, $response_body);
						}
					}
				}
				if ($logger) {
					$logger->warning('VIES returned unexpected payload: ' . substr($raw_body, 0, 500), $log_ctx);
				}
				return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number, $response_body);
			}

			if (!$response_body['valid']) {
				set_transient($cache_key, $response, HOUR_IN_SECONDS);
				return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number, $response_body);
			}
		
			//Setup data
			$response['valid'] = true;
			$response['vies'] = $response_body; 
			$response['name'] = $response_body['name'];

			set_transient($cache_key, $response, DAY_IN_SECONDS);

			//Return response
			return apply_filters('wc_szamlazz_vat_number_validation_results', $response, $vat_number, $response_body);
			
		}

		public static function update_customer_meta($customer_id) {
			$billing_tax_number = !empty( $_POST['wc_szamlazz_adoszam'] ) ? $_POST['wc_szamlazz_adoszam'] : '';
			update_user_meta( $customer_id, 'wc_szamlazz_adoszam', sanitize_text_field( $billing_tax_number ) );
		}

		public static function customer_meta($profileFieldArray) {
			$fieldData = array(
				'label'			=> __('VAT number', 'wc-szamlazz'),
				'description'   => ''
			);
			$profileFieldArray['billing']['fields']['wc_szamlazz_adoszam'] = $fieldData;
			return $profileFieldArray;
		}

		public static function add_vat_to_customer_details($data, $customer, $user_id) {
			$data['billing']['wc_szamlazz_adoszam'] = get_user_meta( $user_id, 'wc_szamlazz_adoszam', true );
			return $data;
		}

		public static function add_vat_number_to_my_formatted_address( $args, $customer_id, $name ) {
			if($name == 'billing') {
				$args['wc_szamlazz_adoszam'] = get_user_meta( $customer_id, 'wc_szamlazz_adoszam', true );
			}
			return $args;
		}

		public static function add_vat_number_to_address( $formats ) {
			if(is_checkout()) return $formats; //Return normal for checkout block address card, because replacement is not solved there

			foreach($formats as $id => $format) {
				$formats[$id] = str_replace("{company}", "{company}{wc_szamlazz_adoszam}", $format);
			}
			return $formats;
		}

		public static function replace_vat_number_in_address( $replacements, $args ){
			$replacements['{wc_szamlazz_adoszam}'] = '';
			if(isset($args['wc_szamlazz_adoszam']) && !empty($args['wc_szamlazz_adoszam'])) {
				$replacements['{wc_szamlazz_adoszam}'] = ' ('.$args['wc_szamlazz_adoszam'].')';
			}
			return $replacements;
		}

		//Create ajax function for vat number check
		public static function check_vat_number_with_ajax() {
			// The browser cancels this AJAX as soon as the user types again or WC
			// fires another update_order_review. If we let PHP get killed mid-VIES
			// the result is never cached and nothing is ever logged. Finish the
			// request server-side regardless.
			ignore_user_abort(true);
			if (function_exists('set_time_limit')) {
				@set_time_limit(30);
			}

			if($_POST['page'] == 'checkout') {
				check_ajax_referer( 'update-order-review', 'security' );
			} else {
				check_ajax_referer( 'woocommerce-edit_address', 'security' );
			}

			//Submitted vat number
			$vat_number = sanitize_text_field($_POST['vat_number']);
			$vat_number_data = false;

			//Try to validate using számlázz.hu api
			if(class_exists( 'WC_Szamlazz_Vat_Number_Field' )) {
				if(WC_Szamlazz()->get_option('vat_number_eu', 'no') == 'yes' && preg_match('/^[A-Z]{2}/', $vat_number)) {
					$vat_number_data = WC_Szamlazz_Vat_Number_Field::get_eu_vat_number_data($vat_number);
				} else {
					$vat_number_data = WC_Szamlazz_Vat_Number_Field::get_vat_number_data($vat_number);
				}
			}

			wp_send_json($vat_number_data);
		}

		public static function vat_exempt_abroad($post_data = false) {
			if(WC_Szamlazz()->get_option('vat_exempt_abroad', 'no') == 'yes') {
				WC()->customer->set_is_vat_exempt(false);

				//Check selected address type
				parse_str( $post_data, $output );
				$eu_countries = WC()->countries->get_european_union_countries('eu_vat');

				//Check if company name specified
				if(
					isset($output['billing_company']) &&
					isset($output['billing_country']) &&
					!empty($output['billing_company']) &&
					!in_array($output['billing_country'], $eu_countries) &&
					!WC()->cart->needs_shipping()
				) {
					WC()->customer->set_is_vat_exempt(true);
				}

				//EU Vat Number compatibility
				do_action('wc_szamlazz_after_set_vat_exempt', $output);
			}
		}

		public static function vat_exempt_abroad_save() {
			if(WC_Szamlazz()->get_option('vat_exempt_abroad', 'no') == 'yes') {
				WC()->customer->set_is_vat_exempt(false);

				//Check selected address type
				$output = $_POST;
				$eu_countries = WC()->countries->get_european_union_countries('eu_vat');

				//Check if company name specified
				if(
					isset($output['billing_company']) &&
					isset($output['billing_country']) &&
					!empty($output['billing_company']) &&
					!in_array($output['billing_country'], $eu_countries) &&
					!WC()->cart->needs_shipping()
				) {
					WC()->customer->set_is_vat_exempt(true);
				}

				//EU Vat Number compatibility
				do_action('wc_szamlazz_after_set_vat_exempt', $output);
			}
		}

		public static function vat_exempt_eu_vat($post_data = false) {
			if(WC_Szamlazz()->get_option('vat_number_eu', 'no') == 'yes') {
				WC()->customer->set_is_vat_exempt(false);

				//Check selected address type
				parse_str( $post_data, $output );
				$eu_countries = WC()->countries->get_european_union_countries('eu_vat');

				//Check if company name specified
				if(
					apply_filters( 'wc_szamlazz_should_set_eu_vat_exempt', (isset($output['billing_company']) &&
					isset($output['billing_country']) &&
					!empty($output['billing_company']) &&
					in_array($output['billing_country'], $eu_countries) &&
					$output['billing_country'] != 'HU' &&
					isset($output['wc_szamlazz_adoszam']) &&
					self::validate_eu_vat_format($output['wc_szamlazz_adoszam'])), $output )
				) {
					WC()->customer->set_is_vat_exempt(true);
				}
			}
		}

		public static function require_vat_number($fields) {
			$fields['company']['required'] = true;
			return $fields;
		}

		public static function get_vat_number_prefix( $country ) {
			switch ( $country ) {
				case 'GR':
					$vat_prefix = 'EL';
					break;
				default:
					$vat_prefix = $country;
					break;
			}
			return $vat_prefix;
		}

	}

	WC_Szamlazz_Vat_Number_Field::init();

endif;