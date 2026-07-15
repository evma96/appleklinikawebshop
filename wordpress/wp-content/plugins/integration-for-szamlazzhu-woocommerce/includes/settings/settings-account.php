<?php
defined( 'ABSPATH' ) || exit;

$pro_required = !WC_Szamlazz_Pro::is_pro_enabled();

$settings = array(

	array(
		'title' => __( 'Account settings', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'title' => __( 'Számla Agent key', 'wc-szamlazz' ),
		'type' => 'text',
		'id' => 'agent_key',
		'desc' => __('To create an Agent Key, sign in into Számlázz.hu, go to the dashboard and click on the key icon at the bottom. <a target="_blank" href="https://visztpeter.me/dokumentacio/">Where can I find the agent key?</a>', 'wc-szamlazz')
	),
	array(
		'title' => __( 'Multiple accounts', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'multiple_accounts',
		'class' => 'wc-szamlazz-toggle-group-accounts',
		'desc' => __('I have multiple accounts', 'wc-szamlazz'),
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
		'desc_tip' => __('Available in the PRO version', 'wc-szamlazz'),

	),
	array(
		'type' => 'wc_szamlazz_settings_accounts',
		'class' => 'wc-szamlazz-toggle-group-accounts-item',
		'desc' => __('If the condition is not matched, it will use the default Számla Agent Key for automatic invoice generation. You can change the account if you are creating an invoice manually.', 'wc-szamlazz')
	),
    array(
		'type' => 'sectionend',
	),
	array(
		'title' => __( 'Invoicing details', 'wc-szamlazz' ),
		'type' => 'title',
	),
	array(
		'title' => __( 'Invoice type', 'wc-szamlazz' ),
		'type' => 'radio',
		'options' => array(
			'electronic' => __( 'Electronic', 'wc-szamlazz' ),
			'paper' => __( 'Paper', 'wc-szamlazz' )
		),
		'id' => 'invoice_type',
		'default' => 'electronic',
		'row_class' => 'szamlazz-radio-row'
	),
	array(
		'title' => __( 'Payment deadline(days)', 'wc-szamlazz' ),
		'type' => 'number',
		'id' => 'payment_deadline',
	),
	array(
		'title' => __( 'Payment methods', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_payment_methods',
		'desc' => __( 'You can overwrite the default payment deadline based on the payment method. You can mark the invoices generated manually or with bulk actions as paid based on the payment method.', 'wc-szamlazz' ),
	),
	array(
		'title' => __( 'Notes', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_notes',
		'desc' => __("You can use the following shortcodes in the description that appears on the invoice:<br>{customer_email} - The customer's e-mail address<br>{customer_phone} - The customer's phone number<br>{transaction_id} - Payment's transaction ID<br>{shipping_address} - Customer's shipping address<br>{customer_note} - Customer's order note<br>{order_number} - Order number", "wc-szamlazz")
	),
	array(
		'title' => __( 'Invoice prefix', 'wc-szamlazz' ),
		'type' => 'text',
		'desc_tip' => __('Make sure the prefix exists in Számlázz.hu / settings / prefixes.', 'wc-szamlazz'),
		'id' => 'prefix'
	),
	array(
		'type' => 'select',
		'class' => 'chosen_select',
		'css' => 'min-width:300px;',
		'title' => __( 'Invoice language', 'wc-szamlazz' ),
		'options' => WC_Szamlazz_Helpers::get_supported_languages(),
		'default' => 'hu',
		'id' => 'language'
	),
	array(
		'title' => __( 'Bank name', 'wc-szamlazz' ),
		'type' => 'text',
		'desc_tip' => __("The seller's bank name. If empty, it will use the value entered on szamlazz.hu.", 'wc-szamlazz'),
		'id' => 'bank_name'
	),
	 array(
		'title' => __( 'Bank account number', 'wc-szamlazz' ),
		'type' => 'text',
		'desc_tip' => __("The seller's bank account number. If empty, it will use the value entered on szamlazz.hu.", 'wc-szamlazz'),
		'id' => 'bank_number'
	),		
	array(
		'type' => 'select',
		'class' => 'chosen_select',
		'css' => 'min-width:300px;',
		'title' => __( 'Document design', 'wc-szamlazz' ),
		'options' => array(
			'SzlaMost' => __('Számlázz.hu recommended design', 'wc-szamlazz'),
			'SzlaAlap' => __('Traditional design', 'wc-szamlazz'),
			'SzlaNoEnv' => __('Envelope-friendly', 'wc-szamlazz'),
			'Szla8cm' => __('Thermal printer compatible (8 cm wide)', 'wc-szamlazz'),
			'SzlaTomb' => __('Retro design', 'wc-szamlazz'),
		),
		'default' => 'SzlaMost',
		'id' => 'template'
	),
	array(
		'type' => 'text',
		'title' => __('Custom Logo', 'wc-szamlazz' ),
		'desc' => __('Leave empty to use the default logo set in your Számlázz.hu account.', 'wc-szamlazz' ),
		'id' => 'custom_logo',
	),
	array(
		'type' => 'checkbox',
		'title' => __( 'Advanced settings', 'wc-szamlazz' ),
		'desc' => __( 'Overwrite the bank account number, the invoice block and the language based on conditional logic.', 'wc-szamlazz' ),
		'class' => 'wc-szamlazz-toggle-group-advanced',
		'id' => 'advanced_settings',
		'disabled' => $pro_required,
		'row_class' => $pro_required ? 'szamlazz-pro-required' : '',
		'desc_tip' => __('Available in the PRO version', 'wc-szamlazz'),
	),
	array(
		'type' => 'wc_szamlazz_settings_advanced',
		'title' => '',
		'class' => 'wc-szamlazz-toggle-group-advanced-item'
	),		
	array(
		'title' => __( 'Other settings', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'debug',
		'checkboxgroup' => 'start',
		'desc' => __( 'Turn on developer mode to log in WooCommerce / Status / Logs', 'wc-szamlazz' ),
	),
	array(
		'title' => __( 'Company name + name', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'company_name',
		'checkboxgroup' => '',
		'desc' => __('Show customer name after company name on the invoice', 'wc-szamlazz')
	),
	array(
		'title' => __( 'Invoice type on company orders', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( "Paper invoice for company orders", 'wc-szamlazz'),
		'id' => 'invoice_type_company',
		'checkboxgroup' => '',
	),

	array(
		'title' => __( 'Download icons in the tools column', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'id' => 'tools',
		'checkboxgroup' => 'end',
		'desc' => __( 'Download icons in the tools column', 'wc-szamlazz' ),
	),
    array(
		'type' => 'sectionend',
	),
);

return $settings;
