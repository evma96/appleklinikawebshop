<?php
defined( 'ABSPATH' ) || exit;

$pro_required = false;
$pro_icon = false;
if(!WC_Szamlazz_Pro::is_pro_enabled()) {
	$pro_required = true;
	$pro_icon = '<i class="wc_szamlazz_pro_label">PRO</i>';
}

$settings = array(
	array(
		'title' => __( 'Line items', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'title' => __( 'Quantity unit', 'wc-szamlazz' ),
		'type' => 'text',
		'id' => 'unit_type',
		'default' => 'db',
		'desc' => __('This will be the default quantity unit on the invoice. You can change this for each product one-by-one on the Advanced tab too.', 'wc-szamlazz')
	),
	array(
		'title' => __( 'Discount display', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'class' => 'wc-szamlazz-toggle-group-coupon',
		'desc' => __('Show discount as a separate line item', 'wc-szamlazz'),
		'id' => 'separate_coupon'
	),
	array(
		'title' => __( 'Discount line item name', 'wc-szamlazz' ),
		'type' => 'text',
		'placeholder' => __('Discount', 'wc-szamlazz'),
		'class' => 'wc-szamlazz-toggle-group-coupon-item',
		'desc_tip' => __('This is the line item name if a coupon is applied to the order. The default value is "Discount"', 'wc-szamlazz'),
		'id' => 'separate_coupon_name'
	),
	array(
		'title' => __( 'Discount line item description', 'wc-szamlazz' ),
		'type' => 'textarea',
		'placeholder' => __('{kedvezmeny_merteke} discount with the following coupon code: {kupon}', 'wc-szamlazz'),
		'class' => 'wc-szamlazz-toggle-group-coupon-item',
		'desc_tip' => __("If turned on, the discount will be a new separate negative order item and you can change it's description here. Default value: {kedvezmeny_merteke} discount with the following coupon code: {kupon}", 'wc-szamlazz'),
		'id' => 'separate_coupon_desc',
		'css'       => 'height: 65px;',
	),
	array(
		'title' => __( 'Discounted product note', 'wc-szamlazz' ),
		'type' => 'textarea',
		'desc_tip' => __('You can display the original price and the amount of the discount in the comment of the line item. Use these shortcodes: {eredeti_ar}, {kedvezmeny_merteke}, {kedvezmenyes_ar}', 'wc-szamlazz'),
		'id' => 'discount_note',
		'css'       => 'height: 65px;',
	),
	array(
		'title' => __( 'Free line items', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Hide free line items', 'wc-szamlazz' ),
		'id' => 'hide_free_items',
		'checkboxgroup' => 'start',
	),
	array(
		'title' => __( 'Hide free shipping', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __('Hide free shipping', 'wc-szamlazz'),
		'id' => 'hide_free_shipping',
		'checkboxgroup' => ''
	),
	array(
		'title' => __( 'Do not create an invoice for free orders', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __("Do not create an invoice for free orders", 'wc-szamlazz'),
		'default' => 'yes',
		'id' => 'disable_free_order',
		'checkboxgroup' => 'end'
	),
	array(
		'title' => __( 'Line item notes', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __("Hide line item notes", 'wc-szamlazz'),
		'id' => 'hide_item_notes'
	),	
	array(
		'type' => 'sectionend',
	)
);

return $settings;
