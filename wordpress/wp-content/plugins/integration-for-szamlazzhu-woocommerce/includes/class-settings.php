<?php
/**
 * WooCommerce advanced settings
 *
 * @package  WooCommerce\Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Szamlazz_Settings', false ) ) {
	return new WC_Szamlazz_Settings();
}

class WC_Szamlazz_Settings extends WC_Settings_Page {

	//Init class
	public function __construct() {
		$this->id    = 'wc-szamlazz';
		$this->label = __( 'Számlázz.hu', 'woocommerce' );

		// Define the actions that use the same method
		$field_types = array(
			'accounts',
			'vat_overrides',
			'accounting_details',
			'eusafa',
			'notes',
			'advanced',
			'payment_methods',
			'radio',
			'automations',
			'auto-status',
			'auto_ipn',
			'title',
			'pro_notice'
		);

		//Loop over the actions and add them
		foreach ($field_types as $field_type) {
			add_action('woocommerce_admin_field_wc_szamlazz_settings_' . $field_type, array(__CLASS__, 'display_custom_field_html'));
		}

		//Add body class
		add_filter('admin_body_class', array(__CLASS__, 'add_body_class'));

		parent::__construct();
	}

	//Add body class to settings pages
	public static function add_body_class($classes) {
		if(isset($_GET['tab']) && $_GET['tab'] == 'wc-szamlazz') {
			$classes .= ' wc-szamlazz-settings';
			if(WC_Szamlazz_Pro::is_pro_enabled()) {
				$classes .= ' wc-szamlazz-pro-enabled';
			}
		}
		return $classes;
	}

	//Setup custom sections
	protected function get_own_sections() {
		return array(
			'' => __( 'General', 'wc-szamlazz' ),
			'taxes' => __( 'Taxes & accounting', 'wc-szamlazz' ),
			'items' => __( 'Line items', 'wc-szamlazz' ),
			'vat-number' => __( 'VAT number', 'wc-szamlazz' ),
			'automations' => __( 'Automatization', 'wc-szamlazz' ),
			'notice' => __( 'Invoice sharing', 'wc-szamlazz' ),
			'receipt' => __( 'E-Receipt', 'wc-szamlazz' ),
		);
	}

	//Get settings for the default section
	protected function get_settings_for_default_section() {
		$settings = $this->get_settings_fields('account');
		return $settings;
	}

	//Get settings for the rest of the sections
	protected function get_settings_for_section_core($section_name) {
		$settings = $this->get_settings_fields($section_name);
		return $settings;
	}

	//Helper function to get settings fields
	public function get_settings_fields($section_name) {
		$settings = include __DIR__ . '/settings/settings-'.$section_name.'.php';
		foreach($settings as $key => $setting) {
			if(isset($setting['id'])) {
				$settings[$key]['id'] = 'wc_szamlazz_'.$setting['id'];
			}
		}
		return apply_filters('wc_szamlazz_settings_'.$section_name, $settings);;
	}

	//Save custom settings
	public function save() {
		global $current_section;

		//Save by default
		$this->save_settings_for_current_section();

		//Save multi account options
		if(!isset($_GET['section']) || empty($_GET['section'])) {
			$extra_accounts = array();
			if ( isset( $_POST['wc_szamlazz_additional_accounts'] ) ) {
				foreach ($_POST['wc_szamlazz_additional_accounts'] as $account_id => $account) {
					$name = wc_clean($account['name']);
					$key = wc_clean($account['key']);
					$condition = wc_clean($account['condition']);

					$extra_accounts[$account_id] = array(
						'name' => $name,
						'key' => $key,
						'condition' => $condition
					);
				}
				update_option( 'wc_szamlazz_extra_accounts', $extra_accounts );
			}

			//Save notes
			$notes = array();
			if ( isset( $_POST['wc_szamlazz_notes'] ) ) {
				foreach ($_POST['wc_szamlazz_notes'] as $note_id => $note) {

					$comment = wp_kses_post( trim( wp_unslash($note['note']) ) );
					$notes[$note_id] = array(
						'comment' => $comment,
						'conditional' => false
					);

					//If theres conditions to setup
					$condition_enabled = isset($note['condition_enabled']) ? true : false;
					$append_enabled = isset($note['append']) ? true : false;
					$conditions = (isset($note['conditions']) && count($note['conditions']) > 0);

					if($condition_enabled && $conditions) {
						$notes[$note_id]['conditional'] = true;
						$notes[$note_id]['conditions'] = array();
						$notes[$note_id]['logic'] = wc_clean($note['logic']);
						$notes[$note_id]['append'] = $append_enabled;

						foreach ($note['conditions'] as $condition) {
							if(isset($condition['category'])) {
								$condition_details = array(
									'category' => wc_clean($condition['category']),
									'comparison' => wc_clean($condition['comparison']),
									'value' => $condition[$condition['category']]
								);

								$notes[$note_id]['conditions'][] = $condition_details;
							}
						}
					}
				}
			}
			update_option( 'wc_szamlazz_notes', $notes );

			//Save advanced options
			$advanced_options = array();
			if ( isset( $_POST['wc_szamlazz_advanced_options'] ) ) {
				foreach ($_POST['wc_szamlazz_advanced_options'] as $advanced_option_id => $advanced_option) {
					$property = sanitize_text_field($advanced_option['property']);
					$value = sanitize_text_field($advanced_option['value']);
					$advanced_options[$advanced_option_id] = array(
						'property' => $property,
						'value' => $value,
						'conditional' => false
					);

					if(!$value) continue;

					//If theres conditions to setup
					$condition_enabled = true;
					$conditions = (isset($advanced_option['conditions']) && count($advanced_option['conditions']) > 0);
					if($conditions && $condition_enabled) {
						$advanced_options[$advanced_option_id]['conditional'] = true;
						$advanced_options[$advanced_option_id]['conditions'] = array();
						$advanced_options[$advanced_option_id]['logic'] = wc_clean($advanced_option['logic']);

						foreach ($advanced_option['conditions'] as $condition) {
							$condition_details = array(
								'category' => wc_clean($condition['category']),
								'comparison' => wc_clean($condition['comparison']),
								'value' => $condition[$condition['category']]
							);

							$advanced_options[$advanced_option_id]['conditions'][] = $condition_details;
						}
					}
				}
			}
			update_option( 'wc_szamlazz_advanced_options', $advanced_options );

			//Save payment options
			$accounts = array();
			if ( isset( $_POST['wc_szamlazz_payment_options'] ) ) {
				foreach ($_POST['wc_szamlazz_payment_options'] as $payment_method_id => $payment_method) {
					$deadline = wc_clean($payment_method['deadline']);
					$complete = isset($payment_method['complete']) ? true : false;
					$proform = isset($payment_method['proform']) ? true : false;
					$deposit = isset($payment_method['deposit']) ? true : false;
					$name = isset($payment_method['name']) ? wc_clean($payment_method['name']) : '';
					$auto_disabled = isset($payment_method['auto_disabled']) ? true : false;

					$accounts[$payment_method_id] = array(
						'deadline' => $deadline,
						'complete' => $complete,
						'proform' => $proform,
						'deposit' => $deposit,
						'name' => $name,
						'auto_disabled' => $auto_disabled
					);
				}
				update_option( 'wc_szamlazz_payment_method_options_v2', $accounts );
			}

		}

		//Save vat overrides
		if(isset($_GET['section']) && $_GET['section'] == 'taxes') {
			$vat_overrides = array();
			if ( isset( $_POST['wc_szamlazz_vat_overrides'] ) ) {
				foreach ($_POST['wc_szamlazz_vat_overrides'] as $vat_override_id => $vat_override) {
					$line_item = sanitize_text_field($vat_override['line_item']);
					$vat_type = sanitize_text_field($vat_override['vat_type']);
					$vat_overrides[$vat_override_id] = array(
						'line_item' => $line_item,
						'vat_type' => $vat_type,
						'conditional' => false
					);

					//If theres conditions to setup
					$condition_enabled = isset($vat_override['condition_enabled']) ? true : false;
					$conditions = (isset($vat_override['conditions']) && count($vat_override['conditions']) > 0);
					if($conditions && $condition_enabled) {
						$vat_overrides[$vat_override_id]['conditional'] = true;
						$vat_overrides[$vat_override_id]['conditions'] = array();
						$vat_overrides[$vat_override_id]['logic'] = wc_clean($vat_override['logic']);

						foreach ($vat_override['conditions'] as $condition) {
							$condition_details = array(
								'category' => wc_clean($condition['category']),
								'comparison' => wc_clean($condition['comparison']),
								'value' => $condition[$condition['category']]
							);

							$vat_overrides[$vat_override_id]['conditions'][] = $condition_details;
						}
					}
				}
			}
			update_option( 'wc_szamlazz_vat_overrides', $vat_overrides );

			//Save vat overrides
			$eusafas = array();
			if ( isset( $_POST['wc_szamlazz_eusafas'] ) ) {
				foreach ($_POST['wc_szamlazz_eusafas'] as $vat_override_id => $vat_override) {
					$eusafas[$vat_override_id] = array(
						'enabled' => true,
						'conditional' => false
					);

					//If theres conditions to setup
					$condition_enabled = isset($vat_override['condition_enabled']) ? true : false;
					$conditions = (isset($vat_override['conditions']) && count($vat_override['conditions']) > 0);
					if($conditions && $condition_enabled) {
						$eusafas[$vat_override_id]['conditional'] = true;
						$eusafas[$vat_override_id]['conditions'] = array();

						foreach ($vat_override['conditions'] as $condition) {
							$condition_details = array(
								'category' => wc_clean($condition['category']),
								'comparison' => wc_clean($condition['comparison']),
								'value' => $condition[$condition['category']]
							);

							$eusafas[$vat_override_id]['conditions'][] = $condition_details;
						}
					}
				}
			}
			update_option( 'wc_szamlazz_eusafa', $eusafas );

			//Save accounting details
			$accounts = array();
			if ( isset( $_POST['wc_szamlazz_accounting_details'] ) ) {
				foreach ($_POST['wc_szamlazz_accounting_details'] as $payment_method_id => $payment_method) {
					$afa_fokonyvi_szam_hu = wc_clean($payment_method['afa_fokonyvi_szam_hu']);
					$fokonyvi_szam_hu = wc_clean($payment_method['fokonyvi_szam_hu']);
					$gazd_esem_hu = wc_clean($payment_method['gazd_esem_hu']);
					$afa_gazd_esem_hu = wc_clean($payment_method['afa_gazd_esem_hu']);
					$afa_fokonyvi_szam_kulfold = wc_clean($payment_method['afa_fokonyvi_szam_kulfold']);
					$fokonyvi_szam_kulfold = wc_clean($payment_method['fokonyvi_szam_kulfold']);
					$gazd_esem_kulfold = wc_clean($payment_method['gazd_esem_kulfold']);
					$afa_gazd_esem_kulfold = wc_clean($payment_method['afa_gazd_esem_kulfold']);

					$accounts[$payment_method_id] = array(
						'afa_fokonyvi_szam_hu' => $afa_fokonyvi_szam_hu,
						'fokonyvi_szam_hu' => $fokonyvi_szam_hu,
						'gazd_esem_hu' => $gazd_esem_hu,
						'afa_gazd_esem_hu' => $afa_gazd_esem_hu,
						'afa_fokonyvi_szam_kulfold' => $afa_fokonyvi_szam_kulfold,
						'fokonyvi_szam_kulfold' => $fokonyvi_szam_kulfold,
						'gazd_esem_kulfold' => $gazd_esem_kulfold,
						'afa_gazd_esem_kulfold' => $afa_gazd_esem_kulfold
					);
				}
			}
			update_option( 'wc_szamlazz_accounting_details', $accounts );

		}

		if(isset($_GET['section']) && $_GET['section'] == 'automations') {

			//Save automations
			$automations = array();
			if ( isset( $_POST['wc_szamlazz_automations'] ) ) {
				foreach ($_POST['wc_szamlazz_automations'] as $automation_id => $automation) {
					$document = sanitize_text_field($automation['document']);
					$trigger = sanitize_text_field($automation['trigger']);
					$complete = sanitize_text_field($automation['complete']);
					$complete_delay = sanitize_text_field($automation['complete_delay']);
					$deadline_start = sanitize_text_field($automation['deadline_start']);
					$deadline = sanitize_text_field($automation['deadline']);
					$paid = isset($automation['paid']) ? true : false;
					$automations[$automation_id] = array(
						'document' => $document,
						'trigger' => $trigger,
						'complete' => $complete,
						'complete_delay' => $complete_delay,
						'deadline_start' => $deadline_start,
						'deadline' => $deadline,
						'paid' => $paid,
						'conditional' => false
					);

					//If theres conditions to setup
					$condition_enabled = isset($automation['condition_enabled']) ? true : false;
					$conditions = (isset($automation['conditions']) && count($automation['conditions']) > 0);

					if($condition_enabled && $conditions) {
						$automations[$automation_id]['conditional'] = true;
						$automations[$automation_id]['conditions'] = array();
						$automations[$automation_id]['logic'] = wc_clean($automation['logic']);

						foreach ($automation['conditions'] as $condition) {
							$condition_details = array(
								'category' => wc_clean($condition['category']),
								'comparison' => wc_clean($condition['comparison']),
								'value' => $condition[$condition['category']]
							);

							$automations[$automation_id]['conditions'][] = $condition_details;
						}
					}
				}
			}
			update_option( 'wc_szamlazz_automations', $automations );

			//Save checkbox groups
			$checkbox_groups = array('auto_invoice_status', 'auto_void_status');
			foreach ($checkbox_groups as $checkbox_group) {
				$checkbox_values = array();
				if ( isset( $_POST['wc_szamlazz_'.$checkbox_group] ) ) {
					foreach ($_POST['wc_szamlazz_'.$checkbox_group] as $checkbox_value) {
						$checkbox_values[] = wc_clean($checkbox_value);
					}
				}
				update_option('wc_szamlazz_'.$checkbox_group, $checkbox_values);
			}

		}

		//Delete cookies
		delete_option('_wc_szamlazz_cookie_name');

		//Save version number
		update_option( '_wc_szamlazz_db_version', '6.0' );
	}

	//Render custom fields
	public static function display_custom_field_html($data) {
		$defaults = array(
			'title' => '',
			'disabled' => false,
			'class' => '',
			'css' => '',
			'placeholder' => '',
			'type' => 'text',
			'desc_tip' => false,
			'desc' => '',
			'custom_attributes' => array(),
			'options' => array()
		);
		$data = wp_parse_args( $data, $defaults );
		$template_name = str_replace('wc_szamlazz_settings_', '', $data['type']);
		ob_start();
		include( dirname( __FILE__ ) . '/views/html-admin-'.str_replace('_', '-', $template_name).'.php' );
		echo ob_get_clean();
	}

	public static function get_payment_methods() {
		$available_gateways = WC()->payment_gateways->payment_gateways();

		$payment_methods = array();
		foreach ($available_gateways as $available_gateway) {
			if($available_gateway->enabled == 'yes') {
				$payment_methods[$available_gateway->id] = $available_gateway->get_title();
			}
		}
		return $payment_methods;
	}

	//Get shipping methods
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

	public static function get_ipn_url() {
		$url = '';
		if(WC_Szamlazz_Pro::is_pro_enabled()) {
			$ipn_id = add_option( '_wc_szamlazz_ipn_url', substr(md5(rand()),5)); //this will only store it if doesn't exists yet
			$url = home_url( '?wc_szamlazz_ipn_url=' ).get_option('_wc_szamlazz_ipn_url');
		}
		return $url;
	}


	//Get order statues
	public static function get_order_statuses($default = false) {
		$statuses = wc_get_order_statuses();
		if(function_exists('wc_order_status_manager_get_order_status_posts')) {
			$filtered_statuses = array();
			$custom_statuses = wc_order_status_manager_get_order_status_posts();
			foreach ($custom_statuses as $status ) {
				$filtered_statuses[ 'wc-' . $status->post_name ] = $status->post_title;
			}
			$statuses = $filtered_statuses;
		}

		if($default) {
			$statuses = $default + $statuses;
		}

		return $statuses;
	}

	//Order statuses
	public static function get_order_statuses_for_void() {
		$built_in_statuses = array("no"=>__("Turned off")) + self::get_order_statuses();
		return $built_in_statuses;
	}


	public static function get_emails() {

		//Get registered emails
		$mailer = WC()->mailer();
		$email_templates = $mailer->get_emails();
		$emails = array();

		//Omit a few one thats not required at all
		$disabled = ['failed_order', 'customer_note', 'customer_reset_password', 'customer_new_account'];

		foreach ($email_templates as $email) {
			if(!in_array($email->id,$disabled)) {
				$emails[$email->id] = $email->get_title();
			}
		}
		
		return $emails;
	}

	public static function get_receipt_billing_fields() {
		if ( ! class_exists( 'WC_Session' ) ) {
		include_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-session.php' );
		}

		WC()->session = new WC_Session_Handler;
		WC()->customer = new WC_Customer;
		$exclude = array('wc_szamlazz_receipt', 'billing_email');
		$fields = array();
		foreach (WC()->checkout->checkout_fields['billing'] as $field_id => $field) {
			if(!in_array($field_id, $exclude) && isset($field['label'])) {
				$fields[$field_id] = wp_strip_all_tags($field['label']);
			}
		}
		return $fields;
	}
}

return new WC_Szamlazz_Settings();
