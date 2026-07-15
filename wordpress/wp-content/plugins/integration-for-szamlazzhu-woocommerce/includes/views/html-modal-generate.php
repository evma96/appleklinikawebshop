<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prograss_bar_text = array(
	'total' => array(
		'singular' => __( 'One in total', 'wc-szamlazz' ),
		'plural' => __( '%d in total', 'wc-szamlazz' ),    
	),
	'current' => array(
		'default' => __( '1 invoice generating', 'wc-szamlazz' ),
		'singular' => __( '1 invoice generated', 'wc-szamlazz' ),
		'plural' => __( '%d invoices generated', 'wc-szamlazz' ),    
	),
	'selected' => array(
		'singular' => __( 'One order selected', 'wc-szamlazz' ),
		'plural' => __( '%d orders selected', 'wc-szamlazz' ),    
	)
);

$prograss_bar_text_json = wp_json_encode( $prograss_bar_text );
$prograss_bar_text_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $prograss_bar_text_json ) : _wp_specialchars( $prograss_bar_text_json, ENT_QUOTES, 'UTF-8', true );

$document_types = array(
	'invoice' => esc_html__('Invoice','wc-szamlazz'),
	'proform' => esc_html__('Proforma invoice','wc-szamlazz'),
	'deposit' => esc_html__('Deposit invoice','wc-szamlazz'),
	'delivery' => esc_html__('Delivery note','wc-szamlazz'),
	'void' => esc_html__('Reverse invoice','wc-szamlazz'),
);

$titles = array(
	'generator' => esc_html__('Generate documents','wc-szamlazz'),
	'invoice' => esc_html__('Generate invoices','wc-szamlazz'),
	'download' => esc_html__('Print & Download documents','wc-szamlazz'),
);
$titles_json = wp_json_encode( $titles );
$titles_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $titles_json ) : _wp_specialchars( $titles_json, ENT_QUOTES, 'UTF-8', true );

?>

<script type="text/template" id="tmpl-wc-szamlazz-modal-generate">
	<div class="wc-backbone-modal wc-szamlazz-modal-generate">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1 data-titles="<?php echo $titles_attr; ?>"><?php echo esc_html_e('Generate invoices', 'wc-szamlazz'); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'wc-szamlazz' ); ?></span>
					</button>
				</header>
				<article>

					<div class="wc-szamlazz-modal-generate-form">
						<div class="wc-szamlazz-modal-generate-form-options">
							<label><?php esc_html_e('Document type','wc-szamlazz'); ?></label>
							<div class="wc-szamlazz-modal-generate-form-options-type">
							<?php foreach($document_types as $type => $label): ?>
								<label for="wc_szamlazz_bulk_invoice_<?php echo esc_attr($type); ?>">
									<input type="radio" name="bulk_invoice_type" id="wc_szamlazz_bulk_invoice_<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($type); ?>" <?php checked( 'invoice', $type); ?> />
									<span><?php echo esc_html($label); ?></span>
								</label>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="wc-szamlazz-modal-generate-form-options wc-szamlazz-modal-generate-form-options-group">
							<div class="hidden-if-void">
								<label for="wc_szamlazz_bulk_invoice_deadline"><?php esc_html_e('Payment deadline','wc-szamlazz'); ?>(<?php esc_html_e('day', 'wc-szamlazz'); ?>)</label>
								<input type="number" id="wc_szamlazz_bulk_invoice_deadline" value="<?php echo absint(WC_Szamlazz()->get_option('payment_deadline', '0')); ?>" />
							</div>
							<div class="hidden-if-void">
								<label for="wc_szamlazz_bulk_invoice_completed"><?php esc_html_e('Completion date','wc-szamlazz'); ?></label>
								<input type="text" class="date-picker" id="wc_szamlazz_bulk_invoice_completed" maxlength="10" value="<?php echo date('Y-m-d'); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
							</div>
							<?php if(count(WC_Szamlazz()->get_szamlazz_accounts()) > 1): ?>
							<div>
								<label for="wc_szamlazz_bulk_invoice_account"><?php esc_html_e('Számlázz.hu account','wc-szamlazz'); ?></label>
								<select id="wc_szamlazz_bulk_invoice_account">
									<?php foreach (WC_Szamlazz()->get_szamlazz_accounts() as $account_key => $account_name): ?>
										<option value="<?php echo esc_attr($account_key); ?>" <?php selected( WC_Szamlazz()->get_option('agent_key', ''), $account_key); ?>><?php echo esc_html($account_name); ?> - <?php echo substr(esc_html($account_key), 0, 16); ?>...</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php endif; ?>
						</div>
						<div class="wc-szamlazz-modal-generate-form-options hidden-if-void">
							<label for="wc_szamlazz_bulk_invoice_note"><?php esc_html_e('Note','wc-szamlazz'); ?></label>
							<textarea id="wc_szamlazz_bulk_invoice_note" placeholder="<?php esc_html_e('Here you can override the note specified in settings','wc-szamlazz'); ?>"></textarea>
						</div>
					</div>

					<div class="wc-szamlazz-modal-generate-download-type">
						<?php foreach($document_types as $type => $label): ?>
							<label for="wc_szamlazz_generate_download_type_<?php echo esc_attr($type); ?>">
								<input type="checkbox" name="bulk_download_type" id="wc_szamlazz_generate_download_type_<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($type); ?>" <?php checked( 'invoice', $type); ?> />
								<span><?php echo esc_html($label); ?></span>
							</label>
						<?php endforeach; ?>		
					</div>

					<table class="wc-szamlazz-modal-generate-table">
						<thead>
							<tr>
								<th class="cell-checkbox"><input class="wc-szamlazz-modal-generate-selectall" type="checkbox" checked></th>
								<th><?php esc_html_e('Order', 'wc-szamlazz'); ?></th>
								<th><?php esc_html_e('Billing address', 'wc-szamlazz'); ?></th>
								<th><?php esc_html_e('Document', 'wc-szamlazz'); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</article>
				<footer class="wc-szamlazz-modal-generate-footer-form">
					<div class="inner">
						<p><span class="wc-szamlazz-modal-generate-progress-bar-text-selected"></span></p>
						<a href="#" class="button button-primary wc-szamlazz-modal-generate-button-next"><?php esc_html_e('Generate documents', 'wc-szamlazz'); ?></a>
					</div>
				</footer>
				<footer class="wc-szamlazz-modal-generate-footer-progress">
					<div class="inner">
						<div class="wc-szamlazz-modal-generate-progress-pending">
							<p><?php esc_html_e('Invoices are currently generating, just a sec...', 'wc-szamlazz'); ?></p>
							<div class="wc-szamlazz-modal-generate-progress">
								<div class="wc-szamlazz-modal-generate-progress-bar">
									<div class="wc-szamlazz-modal-generate-progress-bar-inner"></div>
								</div>
								<div class="wc-szamlazz-modal-generate-progress-bar-text" data-labels="<?php echo $prograss_bar_text_attr; ?>">
									<span class="wc-szamlazz-modal-generate-progress-bar-text-current"></span>
									<span class="wc-szamlazz-modal-generate-progress-bar-text-total"></span>
								</div>
							</div>
						</div>
						<div class="wc-szamlazz-modal-generate-progress-buttons">
							<a href="#" class="button wc-szamlazz-modal-generate-button-download"><?php esc_html_e('Download', 'wc-szamlazz'); ?></a>
							<a href="#" class="button button-primary wc-szamlazz-modal-generate-button-print"><?php esc_html_e('Print', 'wc-szamlazz'); ?></a>
						</div>
					</div>
				</footer>
				<footer class="wc-szamlazz-modal-generate-footer-download">
					<div class="inner">
						<a href="#" class="button wc-szamlazz-modal-generate-button-download"><?php esc_html_e('Download', 'wc-szamlazz'); ?></a>
						<a href="#" class="button button-primary wc-szamlazz-modal-generate-button-print"><?php esc_html_e('Print', 'wc-szamlazz'); ?></a>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<script type="text/html" id="wc_szamlazz_modal_generate_sample_row">
	<tr>
		<td class="cell-checkbox"><input type="checkbox" name="order_ids[]" checked></td>
		<td class="cell-order-number">
			<strong></strong> <span></span>
		</td>
		<td class="cell-address">
			<div class="cell-address-inside">
				<i class="wc-szamlazz-provider-icon"></i>
				<span></span>
			</div>
		</td>
		<td class="cell-documents">
			<div class="cell-documents-content">
				<span class="wc-szamlazz-modal-generate-document-error"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Unable to generate invoice', 'wc-szamlazz'); ?></span>
				<span class="wc-szamlazz-modal-generate-loading-indicator"><?php esc_html_e('Generating...', 'wc-szamlazz'); ?></span>
				<div class="wc-szamlazz-modal-generate-documents"></div>
				<a href="#" class="wc-szamlazz-modal-generate-document-print">
					<span class="dashicons dashicons-printer"></span>
				</a>
			</div>
		</td>
	</tr>
</script>