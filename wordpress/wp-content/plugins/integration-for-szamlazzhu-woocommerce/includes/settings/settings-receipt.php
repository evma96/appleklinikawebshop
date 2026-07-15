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

$settings = array(
	array(
		'title' => __( 'E-Receipt', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'title' => __( 'Receipt', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Enable receipts', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt',
		'id' => 'receipt'
	),
	array(
		'title' => __( 'Receipt prefix', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'text',
		'id' => 'receipt_prefix',
	),
	array(
		'title' => __( 'Receipt note', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'text',
		'description' => __( 'General notice that is visible on the receipt', 'wc-szamlazz' ),
		'id' => 'receipt_note'
	),
	array(
		'title' => __( 'Receipt emailing', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'checkbox',
		'desc' => __( 'Send e-mail notification to customer', 'wc-szamlazz' ),
		'id' => 'receipt_email'
	),
	array(
		'title' => __( 'Receipt e-mail subject', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'text',
		'default' => __( 'Receipt', 'wc-szamlazz' ),
		'id' => 'receipt_email_subject'
	),
	array(
		'title' => __( 'Receipt e-mail text', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'textarea',
		'id' => 'receipt_email_text'
	),
	array(
		'title' => __( 'Receipt e-mail address', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-receipt-item',
		'type' => 'text',
		'desc' => __( 'If someone replies to the email, this will be the reply address(reply-to)', 'wc-szamlazz' ),
		'id' => 'receipt_email_replyto'
	),
	array(
		'title' => __( 'Receipt template', 'wc-szamlazz' ),
		'class' => 'chosen_select wc-szamlazz-toggle-group-receipt-item',
		'css' => 'min-width:300px;',
		'type' => 'select',
		'options' => array(
			'' => __( 'A4 format', 'wc-szamlazz' ),
			'J' => __( 'J - Thermal paper without logo', 'wc-szamlazz' ),
			'L' => __( 'L - Thermal paper with logo', 'wc-szamlazz' )
		),
		'id' => 'receipt_template'
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Hidden checkout fields', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-receipt-item',
		'default' => array('billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_phone', 'billing_address_2', 'wc_szamlazz_adoszam', 'order_comments'),
		'desc' => __('These fields will be hidden on the checkout field if the customer only needs a receipt. The e-mail address field is required.', 'wc-szamlazz'),
		'options' => self::get_receipt_billing_fields(),
		'id' => 'receipt_hidden_fields'
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Disabled payment methods', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-receipt-item',
		'default' => array('billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_phone', 'billing_address_2', 'wc_szamlazz_adoszam', 'order_comments'),
		'desc' => __('Select the payment methods that are not allowed to be used with e-receipts, like cash on delivery or bank transfer.', 'wc-szamlazz'),
		'options' => self::get_payment_methods(),
		'id' => 'receipts_invalid_payment_methods'
	),
	array(
		'type' => 'sectionend',
	)
);

return $settings;
