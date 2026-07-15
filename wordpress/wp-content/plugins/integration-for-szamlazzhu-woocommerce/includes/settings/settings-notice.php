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
		'title' => __( 'Invoice sharing', 'wc-szamlazz' ),
		'type' => 'wc_szamlazz_settings_title',
	),
	array(
		'title' => __( 'Invoice notification', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'class' => 'wc-szamlazz-toggle-group-email-notify',
		'desc' => __( 'Send notification with Számlázz.hu', 'wc-szamlazz' ),
		'desc_tip' => __( 'If turned on, Szamlazz.hu will email the customer about the invoice automatically.', 'wc-szamlazz' ),
		'default' => 'yes',
		'id' => 'auto_email'
	),
	array(
		'title' => __( 'Invoice notification e-mail address', 'wc-szamlazz' ),
		'type' => 'text',
		'class' => 'wc-szamlazz-toggle-group-email-notify-item',
		'desc_tip' => __('If someone responds to the notification e-mail, this will be the reply-to address.', 'wc-szamlazz'),
		'id' => 'auto_email_replyto',
	),
	array(
		'title' => __( 'Invoice notification e-mail subject', 'wc-szamlazz' ),
		'type' => 'text',
		'class' => 'wc-szamlazz-toggle-group-email-notify-item',
		'desc_tip' => __('The subject of the email notification sent by Számlázz.hu', 'wc-szamlazz'),
		'id' => 'auto_email_subject'
	),
	array(
		'title' => __( 'Invoice notification e-mail content', 'wc-szamlazz' ),
		'type' => 'textarea',
		'class' => 'wc-szamlazz-toggle-group-email-notify-item',
		'desc_tip' => __('The body of the email notification sent by Számlázz.hu.', 'wc-szamlazz'),
		'id' => 'auto_email_message'
	),
	array(
		'title' => __( 'Attach invoices to e-mails', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'class' => 'wc-szamlazz-toggle-group-emails',
		'desc' => __( 'Attach PDF invoices to WooCommerce e-mails', 'wc-szamlazz' ),
		'id' => 'email_attachment_file',
		'checkboxgroup' => 'start',
	),
	array(
		'title' => __( 'Attach invoices to e-mail', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'class' => 'wc-szamlazz-toggle-group-emails',
		'id' => 'email_attachment',
		'desc' => __( 'Insert download links in WooCommerce e-mails', 'wc-szamlazz' ),
		'checkboxgroup' => 'end',
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Invoice and receipt pairing', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'default' => array('customer_completed_order', 'customer_invoice'),
		'options' => self::get_emails(),
		'id' => 'email_attachment_invoice',
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Proforma pairing', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'default' => array('customer_processing_order', 'customer_on_hold_order'),
		'options' => self::get_emails(),
		'id' => 'email_attachment_proform'
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Deposit invoice pairing', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'default' => array('customer_processing_order', 'customer_on_hold_order'),
		'options' => self::get_emails(),
		'id' => 'email_attachment_deposit'
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Reverse invoice pairing', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'default' => array('customer_refunded_order', 'cancelled_order'),
		'options' => self::get_emails(),
		'id' => 'email_attachment_void'
	),
	array(
		'type' => 'multiselect',
		'title' => __( 'Delivery note pairing', 'wc-szamlazz' ),
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'options' => self::get_emails(),
		'id' => 'email_attachment_delivery'
	),
	array(
		'type' => 'select',
		'class' => 'wc-enhanced-select wc-szamlazz-toggle-group-emails-item',
		'title' => __( 'E-mail link position', 'wc-szamlazz' ),
		'desc_tip' => __( 'Where should the download links be included in the emails?', 'wc-szamlazz' ),
		'default' => 'beginning',
		'options' => array(
			'beginning' => __( 'At the beginning', 'wc-szamlazz' ),
			'end' => __( 'At the end', 'wc-szamlazz' ),
		),
		'id' => 'email_attachment_position'
	),
	array(
		'title' => __( 'Invoices in My Orders', 'wc-szamlazz' ),
		'type' => 'checkbox',
		'desc' => __( 'Display download link on the My Orders page', 'wc-szamlazz' ),
		'default' => 'no',
		'id' => 'customer_download'
	),
	array(
		'title' => __( 'Invoice forwarding', 'wc-szamlazz' ),
		'type' => 'text',
		'id' => 'invoice_forward',
		'desc' => __('You can enter multiple email addresses separated with a comma and every document created will be forwarded to these addresses. You can use this to setup automation with Zapirt or emailitin.com for example.', 'wc-szamlazz')
	),
	array(
		'title' => __( 'E-mail address for error notifications', 'wc-szamlazz' ),
		'type' => 'text',
		'id' => 'error_email',
		'desc' => __( "If you enter an email address, you will receive a notification if there was an error generating an invoice. Leave it empty if you don't need this(számlázz.hu also sends an email usually)", 'wc-szamlazz' ),
	),
);

$settings[] = array(
	'type' => 'sectionend',
);

return $settings;
