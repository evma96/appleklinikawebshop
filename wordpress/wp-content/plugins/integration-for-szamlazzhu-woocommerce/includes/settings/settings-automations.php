<?php
defined( 'ABSPATH' ) || exit;

$pro_required = false;
$pro_icon = false;
if(!WC_Szamlazz_Pro::is_pro_enabled()) {
	$pro_required = true;
	$pro_icon = '<i class="wc_szamlazz_pro_label">PRO</i>';

	return array(
		array(
			'type' => 'wc_szamlazz_settings_pro_notice',
		)
	);
}

//Check for automation settings
$auto_invoice_statuses = get_option('wc_szamlazz_auto_invoice_status');
$auto_void_statuses = get_option('wc_szamlazz_auto_void_status');
$custom_automations_only = false;
if(!$auto_invoice_statuses && !$auto_void_statuses) {
	$custom_automations_only = true;
}

//Automatic settings
$settings_main = array(
	array(
		'title' => __( 'Automatization', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'type' => 'checkbox',
		'title' => __( 'Automations', 'wc-szamlazz' ),
		'desc' => __( 'Create invoices and other Számlázz.hu documents automatically', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-automation',
		'id' => 'auto_invoice_custom'
	),
	array(
		'type' => 'wc_szamlazz_settings_automations',
		'title' => '',
		'class' => 'wc-szamlazz-toggle-group-automation-item',
		'id' => 'automations'
	),
);

$settings_auto_old = [];
if(!$custom_automations_only) {
	$settings_auto_old = array(
		array(
			'type' => 'multiselect',
			'title' => __( 'Automatic billing', 'wc-szamlazz' ),
			'options' => self::get_order_statuses(array('no' => __('Do nothing', 'wc-szamlazz'))),
			'desc' => __( 'The invoice will be generated automatically if the order is in this status.', 'wc-szamlazz' ),
			'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-automation-item-hide',
			'id' => 'auto_invoice_status'
		),
		array(
			'type' => 'multiselect',
			'title' => __( 'Automatic reverse invoice', 'wc-szamlazz' ),
			'options' => self::get_order_statuses(array('no' => __('Do nothing', 'wc-szamlazz'))),
			'desc' => __( 'A reverse invoice will be generated automatically if the order is in this status.', 'wc-szamlazz' ),
			'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-automation-item-hide',
			'id' => 'auto_void_status'
		),	
	);
}

$settings_rest = array(
	array(
		'title' => __( 'Szamlazz.hu IPN Url', 'wc-szamlazz' ),
		'type' => 'text',
		'default' => self::get_ipn_url(),
		'value' => self::get_ipn_url(),
		'custom_attributes' => array(
			'readonly' => 'readonly'
		),
		'id' => 'ipn_url',
		'desc' => __( 'Your webshop can get notified when an invoice has been paid. This message is sent by Számlázz.hu to a specific web address. You can define this address on <a href="https://www.szamlazz.hu/szamla/?page=beallitasok-szamlaalapertelmezett#cegpaynotifurl" target="_blank">this</a> page.', 'wc-szamlazz' )
	),
	array(
		'title' => __( 'Order status based on IPN', 'wc-szamlazz' ),
		'type' => 'select',
		'options' => self::get_order_statuses(array('no' => __('Do not change status', 'wc-szamlazz'))),
		'default' => 'no',
		'id' => 'ipn_close_order',
		'desc' => __( 'If a deposit or proforma invoice was marked as payment completed in Számlázz.hu and if it notifies your website via IPN about it, the order will be marked with this status.', 'wc-szamlazz' ),
	),
	array(
		'title' => __( 'Order status after invoice generated', 'wc-szamlazz' ),
		'type' => 'select',
		'options' => self::get_order_statuses(array('no' => __('Do not change status', 'wc-szamlazz'))),
		'default' => 'no',
		'id' => 'order_status_callback',
		'desc' => __( 'If you are generating invoices using the bulk actions, the order will be set to this status after the invoice was generated.', 'wc-szamlazz' ),
	),
	array(
		'type' => 'select',
		'title' => __( 'Combined invoice order status', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select',
		'default' => 'no',
		'options' => self::get_order_statuses(array('no' => __('Do not change status', 'wc-szamlazz'))),
		'id' => 'grouped_invoice_status',
		'desc' => __( 'If you create a combined invoice, the related order statuses will change to this.', 'wc-szamlazz' ),
	),
	array(
		'title' => __( 'Delivery note alongside with invoice', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'delivery_note',
		'desc' => __( 'Create delivery note', 'wc-szamlazz' ),
		'desc_tip' => __('If turned on, a delivery note will be generated alongside with the invoice.', 'wc-szamlazz')
	),
	array(
		'title' => __( 'Custom order statuses', 'wc-szamlazz' ),
		'type' => 'text',
		'id' => 'custom_order_statues',
		'desc' => __( "If you are using a custom order status extension and the automation you setup for that status won't trigger, try to add the slug of your custom status. You can add multiple, separated with a comma.", 'wc-szamlazz' ),
	),
);

$settings = array_merge($settings_main, $settings_auto_old, $settings_rest);

$settings[] = array(
	'type' => 'sectionend',
);

return $settings;
