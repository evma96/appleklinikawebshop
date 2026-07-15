<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Szamlazz_Automations', false ) ) :

	class WC_Szamlazz_Automations {

		//Setup triggers
		public static function init() {
			$is_pro = WC_Szamlazz_Pro::is_pro_enabled();

			if($is_pro && WC_Szamlazz()->get_option('auto_invoice_custom', 'no') == 'yes') {

				//When order created
				add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 10, 1 );
				add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_order_created_block' ), 10, 1 );

				//On successful payment
				add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ) );

				//On order status change
				add_action( 'init', array( __CLASS__, 'init_order_status_hooks' ) );

			}

			//Create a hook based on the status setup in settings to auto-generate invoice, only if the advanced options are not used
			if($is_pro && WC_Szamlazz()->get_option('auto_invoice_custom', 'no') != 'yes') {

				//Auto generate proform or deposit invoice
				add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_processing' ) );
				
				$auto_invoice_statuses = get_option('wc_szamlazz_auto_invoice_status');
				$auto_void_statuses = get_option('wc_szamlazz_auto_void_status');

				if($auto_invoice_statuses) {
					if(empty($auto_invoice_statuses)) $auto_invoice_statuses = array();
					if(empty($auto_void_statuses)) $auto_void_statuses = array();
				} else {
					$auto_invoice_statuses = array(WC_Szamlazz()->get_option('auto_invoice_status', 'no'));
					$auto_void_statuses = array(WC_Szamlazz()->get_option('auto_void_status', 'no'));
				}

				//Auto generate invoices
				foreach ($auto_invoice_statuses as $auto_invoice_status) {
					$order_auto_invoice_status = str_replace( 'wc-', '', $auto_invoice_status );
					if($order_auto_invoice_status != 'no') {
						add_action( 'woocommerce_order_status_'.$order_auto_invoice_status, array( __CLASS__, 'on_order_complete' ), 1 );
					}
				}

				//Auto generate void invoices
				foreach ($auto_void_statuses as $auto_void_status) {
					$order_auto_void_status = str_replace( 'wc-', '', $auto_void_status );
					if($order_auto_void_status != 'no') {
						add_action( 'woocommerce_order_status_'.$order_auto_void_status, array( __CLASS__, 'on_order_deleted' ), 1 );
					}
				}
			}

			//Delete PDF when order is deleted
			add_action('woocommerce_before_delete_order', array(__CLASS__, 'on_order_post_deleted'), 10, 2);

		}

		//Initialize order status hooks
		public static function init_order_status_hooks() {
			$statuses = self::get_order_statuses();
			foreach ($statuses as $status => $label) {
				$status = str_replace( 'wc-', '', $status );
				add_action( 'woocommerce_order_status_'.$status, function($order_id) use ($status) {
					self::on_status_change($order_id, $status);
				}, 9);
			}
		}

		//Get order statues
		public static function get_order_statuses() {
			if(function_exists('wc_order_status_manager_get_order_status_posts')) {
				$filtered_statuses = array();
				$custom_statuses = wc_order_status_manager_get_order_status_posts();
				foreach ($custom_statuses as $status ) {
					$filtered_statuses[ 'wc-' . $status->post_name ] = $status->post_title;
				}
				return $filtered_statuses;
			} else {
				$statues = wc_get_order_statuses();
				if(WC_Szamlazz()->get_option('custom_order_statues', '') != '') {
					$custom_statuses = WC_Szamlazz()->get_option('custom_order_statues', '');
					$custom_statuses = explode(',', $custom_statuses); //Split at commas
					$custom_statuses = array_map('trim', $custom_statuses); //Remove whitespace

					foreach ($custom_statuses as $custom_status) {
						if(!isset($statues[$custom_status])) {
							$statues[$custom_status] = $custom_status;
						}
					}
				}
				return apply_filters('wc_szamlazz_get_order_statuses', $statues);
			}
		}

		public static function on_order_created($order_id) {
			$automations = self::find_automations($order_id, 'order_created');
		}

		public static function on_order_created_block($order) {
			$automations = self::find_automations($order->get_id(), 'order_created');
		}

		public static function on_payment_complete( $order_id ) {
			$automations = self::find_automations($order_id, 'payment_complete');

		}

		public static function on_status_change( $order_id, $new_status ) {
			$automations = self::find_automations($order_id, $new_status);
		}

		public static function find_automations($order_id, $trigger) {

			//Get main data
			$order = wc_get_order($order_id);
			$automations = get_option('wc_szamlazz_automations');
			$order_details = WC_Szamlazz_Conditions::get_order_details($order, 'automations');

			//We will return the matched automations at the end
			$final_automations = array();

			//Loop through each automation
			foreach ($automations as $automation_id => $automation) {

				//Check if trigger is a match. If not, just skip
				if(str_replace( 'wc-', '', $automation['trigger'] ) != str_replace( 'wc-', '', $trigger )) {
					continue;
				}

				//If this is based on a condition
				if($automation['conditional']) {

					//Compare conditions with order details and see if we have a match
					$automation_is_a_match = WC_Szamlazz_Conditions::match_conditions($automations, $automation_id, $order_details);

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

				//First sort by document types, so proform and deposit runs before invoice
				$ordered_automations = array();
				$document_order = array('proform', 'deposit', 'invoice', 'void', 'delivery', 'paid');
				foreach($document_order as $value) {
					foreach ($final_automations as $final_automation) {
						if($final_automation['document'] == $value) {
							$ordered_automations[] = $final_automation;
						}
					}
				}

				//Loop through documents(usually it will be only one, but who knows)
				self::run_automations($order_id, $ordered_automations);

			}

			return $final_automations;
		}

		public static function run_automations($order_id, $automations) {

			//Get data
			$order = wc_get_order($order_id);
			$deferred = false;
			$need_delivery_note = (WC_Szamlazz()->get_option('delivery_note', 'no') == 'yes');
			$need_delivery_note = apply_filters('wc_szamlazz_need_delivery_note', $need_delivery_note, $order);
			$order_total = $order->get_total();

			//Don't defer if we are just changing one or two order status using bulk actions
			if(is_admin() && isset($_GET['_wp_http_referer']) && isset($_GET['post']) && count($_GET['post']) > 5) {
				$deferred = apply_filters('wc_szamlazz_defer_invoice_in_bulk_action', true);
			}

			//Don't create for free orders
			$is_order_free = false;
			if($order_total == 0 && (WC_Szamlazz()->get_option('disable_free_order', 'yes') == 'yes')) {
				$is_order_free = true;
			}

			//Check payment method settings
			$should_generate_auto_invoice = true;
			$payment_method = $order->get_payment_method();
			if(WC_Szamlazz()->check_payment_method_options($order->get_payment_method(), 'auto_disabled')) {
				$should_generate_auto_invoice = false;
			}

			//Check for product option
			$order_items = $order->get_items();
			foreach( $order_items as $order_item ) {
				if($order_item->get_product() && $order_item->get_product()->get_meta('wc_szamlazz_disable_auto_invoice') && $order_item->get_product()->get_meta('wc_szamlazz_disable_auto_invoice') == 'yes') {
					$should_generate_auto_invoice = false;
				}
			}

			//Allow customization with filters
			$should_generate_auto_invoice = apply_filters('wc_szamlazz_should_generate_auto_invoice', $should_generate_auto_invoice, $order_id);

			//Loop through automations
			foreach ($automations as $automation) {

				//Check if it was already generated or already marked as paid
				$generated = false;
				if($automation['document'] == 'paid') {
					$generated = ($order->get_meta('_wc_szamlazz_completed'));
				} else {
					$generated = WC_Szamlazz()->is_invoice_generated($order_id, $automation['document']);
				}

				//Skip, if already generated or not needed for free orders
				if($generated || $is_order_free) continue;

				//If its an invoice, check for the should auto generate option
				if($automation['document'] == 'invoice' && !$should_generate_auto_invoice) continue;

				//If we are still here, we can generate the actual document
				//First, get custom options, like complete date and deadline
				$complete_date = $automation['complete'];
				$complete_date_delay = intval($automation['complete_delay']);
				$deadline = intval($automation['deadline']);
				$paid = $automation['paid'];
				$automation_id = isset($automation['id']) ? $automation['id'] : '';
				$timestamp = current_time('timestamp');

				//Get dates related to the order
				if($complete_date == 'order_created') {
					$timestamp = $order->get_date_created()->getTimestamp();
				}

				if($complete_date == 'payment_complete' && $order->get_date_paid()) {
					$timestamp = $order->get_date_paid()->getTimestamp();
				}

				//Calculate document dates
				$deadline_delay = $complete_date_delay+$deadline;
				$document_complete_date = date_i18n('Y-m-d', strtotime('+'.$complete_date_delay.' days', $timestamp));
				$document_deadline_date = date_i18n('Y-m-d', strtotime('+'.$deadline_delay.' days', $timestamp));

				//Custom deadline
				if(isset($automation['deadline_start']) && $automation['deadline_start'] != 'completion') {
					$deadline_start_type = $automation['deadline_start'];
					$deadline_start_timestamp = current_time('timestamp');
					if($deadline_start_type == 'order_created') $deadline_start_timestamp = $order->get_date_created()->getTimestamp();
					if($deadline_start_type == 'payment_complete' && $order->get_date_paid()) $deadline_start_timestamp = $order->get_date_paid()->getTimestamp();
					$document_deadline_date = date_i18n('Y-m-d', strtotime('+'.$deadline.' days', $deadline_start_timestamp));
				}

				//Setup options
				$options = array(
					'deadline_date' => $document_deadline_date,
					'completed_date' => $document_complete_date,
					'paid' => $paid,
					'automation_id' => $automation_id
				);

				//Two type of automations to run, one is to actually generate the documents, the other is to just mark an invoice as paid
				if($automation['document'] == 'paid') {

					if($deferred) {
						WC()->queue()->add( 'wc_szamlazz_mark_as_paid_async', array( 'order_id' => $order_id, 'date' => $document_complete_date), 'wc-szamlazz' );
					} else {

						//Now we can finally create the document
						$return_info = WC_Szamlazz()->generate_invoice_complete($order_id, $document_complete_date);

					}

				} else {

					if($deferred) {
						WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => $automation['document'], 'order_id' => $order_id, 'options' => $options), 'wc-szamlazz' );
						if($need_delivery_note && $automation['document'] == 'invoice') {
							WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => 'delivery', 'order_id' => $order_id ), 'wc-szamlazz' );
						}
					} else {

						//Now we can finally create the document
						$return_info = WC_Szamlazz()->generate_invoice($order_id, $automation['document'], $options);

						//Generate delivery not alongside with invoice if needed
						if($need_delivery_note && $automation['document'] == 'invoice') {
							$return_info = WC_Szamlazz()->generate_invoice($order_id, 'delivery');
						}

					}
				}

			}

		}

		//Runs when an order is deleted
		public static function on_order_post_deleted($order_id, $order) {

			//Check if order has some számlázz.hu documents generated
			$document_types = WC_Szamlazz_Helpers::get_document_types();
			foreach ($document_types as $document_type => $document_label) {

				//Check if document exists and delete it
				if($order->get_meta('_wc_szamlazz_'.$document_type)) {
					$path = WC_Szamlazz()->generate_download_link($order, $document_type, true);
					if($path) {
						unlink($path);
					}
				}
			}
		}

		//Autogenerate proform or deposit invoice
		public static function on_order_processing( $order_id ) {

			//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
			$order = wc_get_order($order_id);
			$payment_method = $order->get_payment_method();
			$is_receipt = ($order->get_meta('_wc_szamlazz_type_receipt'));

			if(!WC_Szamlazz()->is_invoice_generated($order_id) && !$is_receipt) {
				$invoice_types = array('proform', 'deposit');
				foreach ($invoice_types as $invoice_type) {

					if(WC_Szamlazz()->check_payment_method_options($payment_method, $invoice_type) && !WC_Szamlazz()->is_invoice_generated($order_id, $invoice_type)) {
						if(WC_Szamlazz()->get_option('defer') == 'yes') {
							WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => $invoice_type, 'order_id' => $order_id ), 'wc-szamlazz' );
						} else {
							$return_info = WC_Szamlazz()->generate_invoice($order_id, $invoice_type);
						}

					}
				}
			}
		}

		//Autogenerate invoice
		public static function on_order_deleted( $order_id ) {

			//Only generate sztornó, if regular invoice already generated & only if automatic invoice is enabled
			if(WC_Szamlazz()->is_invoice_generated($order_id) || WC_Szamlazz()->is_invoice_generated($order_id, 'receipt') || WC_Szamlazz()->is_invoice_generated($order_id, 'proform')) {
				$return_info = false;

				//Check if we need to generate an invoice or a receipt
				$order = wc_get_order($order_id);
				if($order->get_meta('_wc_szamlazz_type_receipt')) {
					$return_info = WC_Szamlazz()->generate_void_receipt($order_id);
				} else {

					$deferred = false;

					//Don't defer if we are just changing one or two order status using bulk actions
					if(is_admin() && isset($_GET['_wp_http_referer']) && isset($_GET['post']) && count($_GET['post']) > 5) {
						$deferred = apply_filters('wc_szamlazz_defer_invoice_in_bulk_action', true);
					}

					if($deferred) {
						WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => 'void', 'order_id' => $order_id ), 'wc-szamlazz' );
					} else {
						$return_info = WC_Szamlazz()->generate_void_invoice($order_id);
					}
				}
			}

		}

		//Autogenerate invoice
		public static function on_order_complete( $order_id ) {

			//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled

			//What are we creating?
			$order = wc_get_order($order_id);
			$document_type = ($order->get_meta('_wc_szamlazz_type_receipt')) ? 'receipt' : 'invoice';
			$is_already_generated = WC_Szamlazz()->is_invoice_generated($order_id, $document_type);
			$return_info = false;
			$deferred = false;
			$need_delivery_note = (WC_Szamlazz()->get_option('delivery_note', 'no') == 'yes');
			$need_delivery_note = apply_filters('wc_szamlazz_need_delivery_note', $need_delivery_note, $order);
			$order_total = $order->get_total();

			if($document_type == 'receipt' && !$is_already_generated) {
				$return_info = WC_Szamlazz()->generate_receipt($order_id);
			}

			//Don't defer if we are just changing one or two order status using bulk actions
			if(is_admin() && isset($_GET['_wp_http_referer']) && isset($_GET['post']) && count($_GET['post']) > 5) {
				$deferred = apply_filters('wc_szamlazz_defer_invoice_in_bulk_action', true);
			}

			//Don't create for free orders
			if($order_total == 0 && (WC_Szamlazz()->get_option('disable_free_order', 'yes') == 'yes')) {
				$is_already_generated = true;
			}

			//Check payment method settings
			$should_generate_auto_invoice = true;
			$payment_method = $order->get_payment_method();
			if(WC_Szamlazz()->check_payment_method_options($order->get_payment_method(), 'auto_disabled')) {
				$should_generate_auto_invoice = false;
			}

			//Check for product option
			$order_items = $order->get_items();
			foreach( $order_items as $order_item ) {
				if($order_item->get_product() && $order_item->get_product()->get_meta('wc_szamlazz_disable_auto_invoice') && $order_item->get_product()->get_meta('wc_szamlazz_disable_auto_invoice') == 'yes') {
					$should_generate_auto_invoice = false;
				}
			}

			//Allow customization with filters
			$should_generate_auto_invoice = apply_filters('wc_szamlazz_should_generate_auto_invoice', $should_generate_auto_invoice, $order_id);

			if($document_type == 'invoice' && !$is_already_generated && $should_generate_auto_invoice) {

				//Check if we generate this invoice deferred
				if($deferred) {
					WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => 'invoice', 'order_id' => $order_id ), 'wc-szamlazz' );
					if($need_delivery_note) {
						WC()->queue()->add( 'wc_szamlazz_generate_document_async', array( 'invoice_type' => 'delivery', 'order_id' => $order_id ), 'wc-szamlazz' );
					}
				} else {
					if($need_delivery_note) {
						$return_info = WC_Szamlazz()->generate_invoice($order_id, 'delivery');
					}
					$return_info = WC_Szamlazz()->generate_invoice($order_id);
				}

			}

			if($return_info && $return_info['error']) {
				WC_Szamlazz()->on_auto_invoice_error($order_id);
			}

		}

	}

	WC_Szamlazz_Automations::init();

endif;
