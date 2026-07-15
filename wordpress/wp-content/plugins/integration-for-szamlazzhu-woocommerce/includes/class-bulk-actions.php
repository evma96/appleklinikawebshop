<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Load PDF API
use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\TcpdiDriver;

if ( ! class_exists( 'WC_Szamlazz_Bulk_Actions', false ) ) :

	class WC_Szamlazz_Bulk_Actions {

		public static function init() {
			add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'add_bulk_options'), 20, 1);
			add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_bulk_actions'), 10, 3 );
			add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'add_bulk_options'), 20, 1 );
			add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk_actions'), 10, 3 );
			add_action( 'admin_footer', array( __CLASS__, 'generator_modal' ) );
			add_action( 'wp_ajax_wc_szamlazz_bulk_generator', array( __CLASS__, 'bulk_generator_ajax' ) );

			add_filter( 'woocommerce_admin_order_preview_get_order_details', array( __CLASS__, 'add_invoices_in_preview_modal'), 20, 2 );
			add_action( 'woocommerce_admin_order_preview_start', array( __CLASS__, 'show_invoices_in_preview_modal') );

			//Add some additional order attributes to the order number column
			add_action('manage_shop_order_posts_custom_column', array( __CLASS__, 'order_details_for_bulk_generate'), 10, 2 );
			add_action('woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'order_details_for_bulk_generate'), 10, 2 );
		
			//Order list button
			add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_listing_column' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'add_listing_actions' ), 10, 2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_listing_column' ) );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'add_listing_actions' ), 10, 2 );
			if(WC_Szamlazz()->get_option('tools', 'no') == 'yes') add_action( 'woocommerce_admin_order_actions_end', array( __CLASS__, 'add_listing_actions_2' ) );


		}

		public static function get_bulk_actions() {
			$actions = array();
			$defaults = WC_Szamlazz_Helpers::get_default_bulk_actions();
			$enabled_actions = WC_Szamlazz()->get_option('bulk_actions_v2', $defaults);
			$action_types = array(
				'generate_invoices' => _x( 'Create invoices', 'bulk action', 'wc-szamlazz' ),
				'generate_documents' => _x( 'Create documents', 'bulk action', 'wc-szamlazz' ),
				'download_invoices' => _x( 'Print & download documents', 'bulk action', 'wc-szamlazz' ),
			);

			foreach ($enabled_actions as $key) {
				if(isset($action_types[$key])) {
					$actions['wc_szamlazz_bulk_'.$key] = $action_types[$key];
				}
			}

			return apply_filters('wc_szamlazz_bulk_actions', $actions);
		}

		public static function add_bulk_options( $actions ) {
			return $actions + self::get_bulk_actions();
		}

		public static function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
			return $redirect_to;
		}

		public static function generator_modal() {
			global $pagenow, $typenow; 
			if ( ( $pagenow === 'edit.php' && $typenow === 'shop_order' ) || ( $pagenow === 'admin.php' && $_GET['page'] === 'wc-orders' ) ) {
				include( dirname( __FILE__ ) . '/views/html-modal-generator.php' );
				include( dirname( __FILE__ ) . '/views/html-modal-generate.php' );
			}
		}

		public static function add_invoices_in_preview_modal( $fields, $order ) {
			$invoice_types = WC_Szamlazz_Helpers::get_document_types();
			$invoices = array();

			foreach ($invoice_types as $invoice_type => $invoice_label) {
				if(WC_Szamlazz()->is_invoice_generated($order->get_id(), $invoice_type) && !$order->get_meta('_wc_szamlazz_own')) {
					$invoices[] = [
						'label' => $invoice_label,
						'name' => $order->get_meta('_wc_szamlazz_'.$invoice_type),
						'link' => WC_Szamlazz()->generate_download_link($order, $invoice_type)
					];
				}
			}

			if($invoices) {
				$fields['wc_szamlazz'] = $invoices;
			}

			return $fields;
		}

		public static function show_invoices_in_preview_modal() {
			?>
			<# if ( data.wc_szamlazz ) { #>
			<div class="wc-order-preview-addresses">
				<div class="wc-order-preview-address">
					<h2><?php esc_html_e( 'Számlázz.hu', 'wc-szamlazz' ); ?></h2>
					<# _.each( data.wc_szamlazz, function(res, index) { #>
						<strong>{{res.label}}</strong>
						<a href="{{ res.link }}" target="_blank">{{ res.name }}</a>
					<# }) #>
				</div>
			</div>
			<# } #>
			<?php
		}

		public static function order_details_for_bulk_generate($column_name, $post_or_order_object) {
			$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
			if ( ! is_object( $order ) && is_numeric( $order ) ) {
				$order = wc_get_order( absint( $order ) );
			}

			$invoice_types = WC_Szamlazz_Helpers::get_document_types();
			if($column_name == 'order_number') {
				$order_details = array();
				$order_details['order_id'] = $order->get_id();
				$order_details['order_number'] = $order->get_order_number();
				$order_details['customer_name'] = $order->get_formatted_billing_full_name();
				$order_details['billing_address'] = $order->get_billing_city().', '.$order->get_billing_address_1();
				$order_details['documents'] = array();

				//Add previously generated documents
				foreach ($invoice_types as $invoice_type => $invoice_label) {
					if(WC_Szamlazz()->is_invoice_generated($order->get_id(), $invoice_type)) {
						$order_details['documents'][$invoice_type] = array(
							'name' => $order->get_meta('_wc_szamlazz_'.$invoice_type),
							'link' => WC_Szamlazz()->generate_download_link($order, $invoice_type)
						);
					}
				}

				//Convert to JSON and add as data attribute
				$json_order_details = json_encode($order_details);
				echo '<span class="wc-szamlazz-order-details" style="display:none;" data-order-details=\'' . htmlspecialchars($json_order_details, ENT_QUOTES, 'UTF-8') . '\'></span>';
			}
		}

		//Column on orders page
		public static function add_listing_column($columns) {
			$new_columns = array();
			foreach ($columns as $column_name => $column_info ) {
				$new_columns[ $column_name ] = $column_info;
				if ( 'order_total' === $column_name ) {
					$new_columns['wc_szamlazz'] = __( 'Számlázz.hu', 'wc-szamlazz' );
				}
			}
			return $new_columns;
		}

		//Add icon to order list to show invoice
		public static function add_listing_actions( $column, $post_or_order_object ) {
			$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
			if ( ! is_object( $order ) && is_numeric( $order ) ) {
				$order = wc_get_order( absint( $order ) );
			}

			if ( $order && 'order_total' === $column && WC_Szamlazz_Pro::is_pro_enabled()) {
				echo '<span class="wc-szamlazz-mark-paid-item">';

				//Replicate the original price content
				if ( $order->get_payment_method_title() ) {
					echo '<span class="tips" data-tip="' . esc_attr( sprintf( __( 'via %s', 'wc-szamlazz' ), $order->get_payment_method_title() ) ) . '">' . wp_kses_post( $order->get_formatted_order_total() ) . '</span>';
				} else {
					echo wp_kses_post( $order->get_formatted_order_total() );
				}

				if(WC_Szamlazz()->is_invoice_generated($order->get_id(), 'invoice')) {

					if($order->get_meta('_wc_szamlazz_completed')) {
						$paid_date = $order->get_meta('_wc_szamlazz_completed');
						if (strpos($paid_date, '-') == false) {
							$paid_date = date('Y-m-d', $paid_date);
						}

						echo '<span class="wc-szamlazz-mark-paid-button paid tips" data-tip="'.sprintf(__('Paid on: %s', 'wc-szamlazz'), $paid_date).'"></span>';
					} else {
						if(!$order->get_meta('_wc_szamlazz_own')) {
							echo '<a href="#" data-nonce="'.wp_create_nonce( 'wc_szamlazz_generate_invoice' ).'" data-order="'.$order->get_id().'" class="wc-szamlazz-mark-paid-button tips" data-tip="'.__('Mark as paid', 'wc-szamlazz').'"></a>';
						}
					}

				} else {
					$tip = __("There's no invoice for this order yet", "wc-szamlazz");
					echo '<span class="wc-szamlazz-mark-paid-button pending tips" data-tip="'.$tip.'"></span>';
				}

				echo '</span>';
			}

			if ( $order && 'wc_szamlazz' === $column ) {
				$invoice_types = WC_Szamlazz_Helpers::get_document_types();

				foreach ($invoice_types as $invoice_type => $invoice_label) {
					if(WC_Szamlazz()->is_invoice_generated($order->get_id(), $invoice_type) && !$order->get_meta('_wc_szamlazz_own')):
					?>
						<a href="<?php echo WC_Szamlazz()->generate_download_link($order, $invoice_type); ?>" class="button tips wc-szamlazz-button wc-szamlazz-button-<?php echo esc_attr($invoice_type); ?>" target="_blank" data-tip="<?php echo $invoice_label; ?>"></a>
					<?php
					endif;
				}
			}
		}

		//Add to tools column
		public static function add_listing_actions_2($order) {
			self::add_listing_actions('wc_szamlazz', $order);
		}

	}

	WC_Szamlazz_Bulk_Actions::init();

endif;
