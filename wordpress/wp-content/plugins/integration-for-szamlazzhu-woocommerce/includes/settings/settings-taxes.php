<?php
defined( 'ABSPATH' ) || exit;

$pro_required = !WC_Szamlazz_Pro::is_pro_enabled();

$settings = array(
	array(
		'title' => __( 'Taxes & accounting', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'type' => 'select',
		'class' => 'chosen_select',
		'css' => 'min-width:300px;',
		'title' => __( 'VAT rates', 'wc-szamlazz' ),
		'options' => WC_Szamlazz_Helpers::get_vat_types(),
		'desc' => __( "The VAT rate that is visible on the invoice. By default, it will use the values set in WooCommerce / Tax menu, if the values match the following tax types: TAM, AAM, EU, EUK, MAA, F.AFA, ÁKK. If there's no match, it will calculate the percentage based on the net and gross prices.", 'wc-szamlazz' ),
		'default' => '',
		'id' => 'afakulcs'
	),
	array(
		'type'		 => 'checkbox',
		'title'    => __( 'Special VAT rates', 'wc-szamlazz' ),
		'desc' => __( "Use the EUT vat rate instead of 0% for EU companies", 'wc-szamlazz' ),
		'default'  => '',
		'id' => 'afakulcs_eu',
		'checkboxgroup' => 'start',
	),
	array(
		'type'		 => 'checkbox',
		'title'    => __( 'EUKT vat rate', 'wc-szamlazz' ),
		'desc' => __( "Use the EUKT vat rate outside of the EU", 'wc-szamlazz' ),
		'default'  => '',
		'id' => 'afakulcs_euk',
		'checkboxgroup' => '',
	),
	array(
		'title'    => __( 'VAT exempt for virtual company orders outside of the EU', 'wc-szamlazz' ),
		'type'     => 'checkbox',
		'default' => 'no',
		'id' => 'vat_exempt_abroad',
		'checkboxgroup' => '',
		'desc' => __( "VAT exempt for virtual company orders outside of the EU", 'wc-szamlazz' ),
	),
	array(
		'type' => 'checkbox',
		'title' => __( 'Custom VAT rate overrrides', 'wc-szamlazz' ),
		'desc' => __( 'Advanced settings to setup VAT rates.', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-vat-override',
		'id' => 'vat_overrides_custom',
		'checkboxgroup' => 'end',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
		'desc_tip' => __('Available in the PRO version', 'wc-szamlazz'),
	),
	array(
		'type' => 'wc_szamlazz_settings_vat_overrides',
		'title' => '',
		'class' => 'wc-szamlazz-toggle-group-vat-override-item'
	),


	array(
		'type' => 'checkbox',
		'title' => __( 'One Stop Shop', 'wc-szamlazz' ),
		'desc' => __( 'The company is registered under the One Stop Shop (OSS).', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-eusafa',
		'id' => 'eusafa_custom',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
		'desc_tip' => __('Available in the PRO version', 'wc-szamlazz'),
	),
	array(
		'type' => 'wc_szamlazz_settings_eusafa',
		'title' => '',
		'class' => 'wc-szamlazz-toggle-group-eusafa-item'
	),
	array(
		'title' => __( 'Correction invoice', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'corrected',
		'desc' => __( "Use correction invoices", 'wc-szamlazz' ),
		'desc_tip' => __( "If turned on, you can create a correction invoice instead of a reverse invoice manually for each order. It will create a correction invoice with negative prices. The automatic reverse invoice is still enabled with this option, so it's a good idea to turn that off if you plan to use this feature.", 'wc-szamlazz' ),
	),
	array(
		'title'    => __( 'KATA compatibility', 'wc-szamlazz' ),
		'type'     => 'checkbox',
		'default' => 'no',
		'desc' => __( "Enable KATA compatibility", 'wc-szamlazz' ),
		'desc_tip' => __( 'If turned on and the invoice would include a VAT number, it will show an error message instead of creating an invoice.', 'wc-szamlazz' ),
		'id' => 'kata_compatibility'
	),
	array(
		'title' => __( 'Accounting details', 'wc-szamlazz' ),
		'desc' => __( 'Accounting details', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-accounting',
		'type' => 'checkbox',
		'id' => 'accounting_details_enabled',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
		'desc_tip' => __('Available in the PRO version', 'wc-szamlazz'),
	),
);

if(WC_Szamlazz()->get_option('accounting_details_enabled', 'no') == 'yes') {
	$settings[] = array(
		'class' => 'wc-szamlazz-toggle-group-accounting-item',
		'title' => __( 'Customer ledger number', 'wc-szamlazz' ),
		'desc' => __('Save ledger number', 'wc-szamlazz'),
		'type' => 'checkbox',
		'id' => 'accounting_details_vevo_azonosito',
		'desc_tip' => __( "If the customer is a registered user, the user id will be stored as the buyer's ledger number.", 'wc-szamlazz' ),
	);
	$settings[] = array(
		'class' => 'wc-szamlazz-toggle-group-accounting-item',
		'title' => __( 'Accounting details', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_accounting_details'
	);
}

$settings[] = array(
	'type' => 'sectionend',
);

return $settings;
