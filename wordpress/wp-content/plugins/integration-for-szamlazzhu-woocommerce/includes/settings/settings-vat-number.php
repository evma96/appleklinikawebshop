<?php
defined( 'ABSPATH' ) || exit;
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

$pro_required = !WC_Szamlazz_Pro::is_pro_enabled();
$checkout_block_used = class_exists( 'Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && CartCheckoutUtils::is_checkout_block_default();

$settings_vat = array(
	array(
		'title' => __( 'VAT number', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
);

$settings_vat_block = array(
	array(
		'title' => __('VAT number field', 'wc-szamlazz'),
		'type' => 'radio',
		'class' => 'wc-szamlazz-toggle-group-vatnumber',
		'options' => array(
			'no' => __('Do not show the VAT number field', 'wc-szamlazz'),
			'yes' => __('Show the VAT number field on checkout', 'wc-szamlazz'),
		),
		'default' => 'no',
		'id' => 'vat_number_type',
		'desc' => '<div class="wc-szamlazz-toggle-group-vatnumber-cell-show">'.__('Since you are using the Checkout Block, you can find the rest of the settings related to the VAT number field after you add the Vat Number Field block inside the Contact Information step.', 'wc-szamlazz').'<img src="'.WC_Szamlazz()::$plugin_url.'assets/images/vat-number-block-info.png" class="wc-szamlazz-settings-vat-number-block-preview"></div></div>',
		'desc_at_end' => true
	),
);

$settings_vat_shortcode = array(
	array(
		'title' => __('VAT number field', 'wc-szamlazz'),
		'type' => 'radio',
		'class' => 'wc-szamlazz-toggle-group-vatnumber',
		'options' => array(
			'no' => __('Do not show the VAT number field', 'wc-szamlazz'),
			'default' => __('Show the field if the Company Name field is filled', 'wc-szamlazz'),
			'show' => __('Show the VAT number field always(optional by default, required if company name filled)',  'wc-szamlazz'),
			'toggle' => __('Display a company billing checkbox to toggle both the company name and VAT number fields',  'wc-szamlazz'),
			'radio' => __('Radio buttons',  'wc-szamlazz')
		),
		'default' => 'no',
		'id' => 'vat_number_type'
	),
	array(
		'title' => __( 'VAT number field position', 'wc-szamlazz' ),
		'type' => 'number',
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'default' => 35,
		'desc_tip' => __( 'The default priority is 35, which will place the field just after the company name field. Change this number if you want to place it somewhere else.', 'wc-szamlazz' ),
		'id' => 'vat_number_position'
	),
	array(
		'title' => __( 'Alignment', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Align company and VAT number field side by side', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'desc_tip' => __( 'Show the company name and VAT number fields side by side, like the First name and Last name fields.', 'wc-szamlazz' ),
		'id' => 'vat_number_alignment'
	),
	array(
		'title' => __( 'B2B orders only', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Require VAT number', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'desc_tip' => __( 'Only allow orders with a valid VAT number.', 'wc-szamlazz' ),
		'id' => 'vat_number_required'
	),
	array(
		'title' => __( 'Autofill', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Fill in the address automatically', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'desc_tip' => __( 'If the customer enters a VAT number, it will validate it and also prefill the address and company name fields automatically.', 'wc-szamlazz' ),
		'id' => 'vat_number_autofill',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
	)
);

$setting_vat_eu = array(
	array(
		'title' => __( 'EU VAT numbers', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Accept EU VAT numbers', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'desc_tip' => __( 'Customer can enter an EU VAT Number too(with country prefix) and it will be validated with VIES and also removes VAT from the order.', 'wc-szamlazz' ),
		'id' => 'vat_number_eu',
		'checkboxgroup' => 'start',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
	),
	array(
		'title'    => __( 'EU VAT exempt', 'wc-szamlazz' ),
		'desc' => __('VAT exempt for orders inside the EU with valid VAT number', 'wc-szamlazz' ),
		'type'     => 'checkbox',
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'default' => 'no',
		'id' => 'eu_vat_exempt',
		'checkboxgroup' => 'end',
		'disabled' => $pro_required,
	),
	array(
		'title' => __( 'Global VAT number', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Show VAT number field for all countries', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vatnumber-item',
		'desc_tip' => __( 'When enabled, the VAT number field will be visible for non-Hungarian and non-EU countries too, whenever the company name is filled or company billing is selected. The field will be required but no format validation is applied.', 'wc-szamlazz' ),
		'id' => 'vat_number_global',
	)
);

//Show different info based on checkout block and shortcode page
if($checkout_block_used) {
	$settings = array_merge($settings_vat, $settings_vat_block, $setting_vat_eu);
} else {
	$settings = array_merge($settings_vat, $settings_vat_shortcode, $setting_vat_eu);
}

$settings[] = array(
	'type' => 'sectionend',
);

return $settings;
