jQuery(document).ready(function($) {

	//Settings page
	var wc_szamlazz_settings = {
		settings_groups: ['accounts', 'coupon', 'vatnumber', 'emails', 'email-notify', 'receipt', 'accounting', 'automation', 'vat-override', 'eusafa', 'advanced'],
		$additional_account_table: $('.wc-szamlazz-settings-inline-table-accounts'),
		$notes_table: $('.wc-szamlazz-settings-notes'),
		$automations_table: $('.wc-szamlazz-settings-automations'),
		$vat_overrides_table: $('.wc-szamlazz-settings-vat-overrides'),
		$eusafa_table: $('.wc-szamlazz-settings-eusafas'),
		$advanced_table: $('.wc-szamlazz-settings-advanced-options'),
		activation_nonce: '',
		init: function() {
			this.init_toggle_groups();

			//Show settings page
			$('body').addClass('wc-szamlazz-settings-loaded');

			//Pro version actions
			this.activation_nonce = wc_szamlazz_params.nonces.settings;
			$('#woocommerce_wc_szamlazz_pro_email').keypress(this.submit_pro_on_enter);
			$('#wc_szamlazz_activate_pro').on('click', this.submit_activate_form);
			$('body').on('click', '#wc_szamlazz_deactivate_pro', this.submit_deactivate_form);
			$('body').on('click', '#wc_szamlazz_validate_pro', this.submit_validate_form);
		
			//PRO version modal
			$( '.wc-szamlazz-settings-submenu' ).on('click', '.wc-szamlazz-settings-submenu-pro', function () {
				$(this).WCBackboneModal({
					template: 'wc-szamlazz-modal-pro-version'
				});
				return false;
			});
			
			//Load additional accounts table
			this.$additional_account_table.find('tfoot a').on('click', this.add_new_account_row);
			this.$additional_account_table.on('click', 'a.delete-row', this.delete_account_row);
			this.$additional_account_table.on('change', 'select', this.change_account_select_class);

			if(this.$additional_account_table.find('tbody tr').length < 1) {
				this.add_new_account_row();
			}

			//Conditional logic controls
			var conditional_fields = [this.$notes_table, this.$vat_overrides_table, this.$eusafa_table, this.$automations_table, this.$advanced_table];
			var conditional_fields_ids = ['notes', 'vat_overrides', 'eusafas', 'automations', 'advanced_options'];

			//Setup conditional fields for notes, vat rates and automations
			conditional_fields.forEach(function(table, index){
				var id = conditional_fields_ids[index];
				var singular = id.slice(0, -1);
				singular = singular.replace('_', '-');
				table.on('change', 'select.condition', {group: id}, wc_szamlazz_settings.change_x_condition);
				table.on('change', 'select.wc-szamlazz-settings-repeat-select', function(){wc_szamlazz_settings.reindex_x_rows(id)});
				table.on('click', '.add-row', {group: id}, wc_szamlazz_settings.add_new_x_condition_row);
				table.on('click', '.delete-row', {group: id}, wc_szamlazz_settings.delete_x_condition_row);
				table.on('change', 'input.condition', {group: id}, wc_szamlazz_settings.toggle_x_condition);
				table.on('click', '.delete-'+singular, {group: id}, wc_szamlazz_settings.delete_x_row);
				$('.wc-szamlazz-settings-'+singular+'-add a:not([data-disabled]').on('click', {group: id, table: table}, wc_szamlazz_settings.add_new_x_row);

				//If we already have some notes, append the conditional logics
				table.find('ul.conditions[data-options]').each(function(){
					var saved_conditions = $(this).data('options');
					var ul = $(this);

					saved_conditions.forEach(function(condition){
						var sample_row = $('#wc_szamlazz_'+id+'_condition_sample_row').html();
						sample_row = $(sample_row);
						sample_row.find('select.condition').val(condition.category);
						sample_row.find('select.comparison').val(condition.comparison);
						sample_row.find('select.value').removeClass('selected');
						sample_row.find('select[data-condition="'+condition.category+'"]').val(condition.value).addClass('selected').attr('disabled', false);
						
						//Show/hide "exclusively" option based on condition
						var exclusively_option = sample_row.find('select.comparison option[value="exclusively"]');
						if (condition.category.startsWith('shipping_') || condition.category.startsWith('product_')) {
							exclusively_option.show();
						} else {
							exclusively_option.hide();
						}
						
						ul.append(sample_row);
					});
				});

				if(table.find('.wc-szamlazz-settings-'+singular).length < 1) {
					$('.wc-szamlazz-settings-'+singular+'-add a:not([data-disabled]').trigger('click');
				}

				if(id == 'advanced_options') {
					table.find('input.condition').trigger('change');
				}

				//Reindex the fields
				wc_szamlazz_settings.reindex_x_rows(id);

			});

		},
		init_toggle_groups: function() {
			$.each(wc_szamlazz_settings.settings_groups, function( index, value ) {
				var checkbox = $('.wc-szamlazz-toggle-group-'+value);
				var group_items = $('.wc-szamlazz-toggle-group-'+value+'-item').parents('tr');
				var group_items_hide = $('.wc-szamlazz-toggle-group-'+value+'-item-hide').parents('tr');
				var single_items_hide = $('.wc-szamlazz-toggle-group-'+value+'-cell-hide');
				var single_items_show = $('.wc-szamlazz-toggle-group-'+value+'-cell-show');
				var checked = checkbox.is(":checked");

				if(checkbox.attr('type') == 'radio') {
					checkbox = checkbox.parents('ul').find('input[type=radio]');
					checked = (checkbox.parents('ul').find('input[type=radio]:checked').val() != 'no');
				}

				if(value == 'emails' && $('.wc-szamlazz-toggle-group-'+value+':checked').length) {
					checked = true;
				}

				if(checked) {
					group_items.show();
					group_items_hide.hide();
					single_items_hide.hide();
					single_items_show.show();
				} else {
					group_items.hide();
					group_items_hide.show();
					single_items_hide.show();
					single_items_show.hide();
				}
				checkbox.change(function(e){
					e.preventDefault();
					var checked = $(this).is(":checked");

					if(checkbox.attr('type') == 'radio') {
						checked = (checkbox.parents('ul').find('input[type=radio]:checked').val() != 'no');
					}

					if(value == 'emails' && $('.wc-szamlazz-toggle-group-'+value+':checked').length) {
						checked = true;
					}

					if(checked) {
						group_items.show();
						group_items_hide.hide();
						single_items_hide.hide();
						single_items_show.show();
					} else {
						group_items.hide();
						group_items_hide.show();
						single_items_hide.show();
						single_items_show.hide();
					}
				});
			});
		},
		submit_pro_on_enter: function(e) {
			if (e.which == 13) {
				$('#wc_szamlazz_activate_pro').click();
				return false;
			}
		},
		submit_activate_form: function() {
			var key = $('#woocommerce_wc_szamlazz_pro_key').val();
			var button = $(this);
			var $form = button.parent();
			var $container = button.parents('.wc-szamlazz-pro-widget');

			var data = {
				action: 'wc_szamlazz_license_activate',
				key: key,
				nonce: wc_szamlazz_settings.activation_nonce
			};

			$form.block({
				message: null,
				overlayCSS: {
					background: '#f0f0f1 url(' + wc_szamlazz_params.loading + ') no-repeat center',
					backgroundSize: '16px 16px',
					opacity: 0.6
				}
			});

			$container.find('.wc-szamlazz-pro-widget-notice').hide();

			$.post(ajaxurl, data, function(response) {
				//Remove old messages
				if(response.success) {
					window.location.reload();
					return;
				} else {
					$container.find('.wc-szamlazz-pro-widget-notice p').html(response.data.message);
					$container.find('.wc-szamlazz-pro-widget-notice').show();

					$form.addClass('fail');
					setTimeout(function() {
						$form.removeClass('fail');
					}, 1000);
				}
				$form.unblock();
			});

			return false;
		},
		submit_deactivate_form: function() {
			var button = $(this);
			var form = $('.wc-szamlazz-modal-pro-version-content');

			var data = {
				action: 'wc_szamlazz_license_deactivate',
				nonce: wc_szamlazz_settings.activation_nonce
			};

			form.block({
				message: null,
				overlayCSS: {
					background: '#ffffff url(' + wc_szamlazz_params.loading + ') no-repeat center',
					backgroundSize: '16px 16px',
					opacity: 0.6
				}
			});

			form.find('.notice').hide();

			$.post(ajaxurl, data, function(response) {
				//Remove old messages
				if(response.success) {
					window.location.reload();
					return;
				} else {
					form.find('.notice p').html(response.data.message);
					form.find('.notice').show();
				}
				form.unblock();
			});
			return false;
		},
		submit_validate_form: function() {
			var button = $(this);
			var form = $('.wc-szamlazz-modal-pro-version-content');

			var data = {
				action: 'wc_szamlazz_license_validate',
				nonce: wc_szamlazz_settings.activation_nonce
			};

			form.block({
				message: null,
				overlayCSS: {
					background: '#ffffff url(' + wc_szamlazz_params.loading + ') no-repeat center',
					backgroundSize: '16px 16px',
					opacity: 0.6
				}
			});

			form.find('.notice').hide();

			$.post(ajaxurl, data, function(response) {
				window.location.reload();
			});
			return false;
		},
		add_new_account_row: function() {
			var sample_row = $('#wc_szamlazz_additional_accounts_sample_row').html();
			wc_szamlazz_settings.$additional_account_table.find('tbody').append(sample_row);
			wc_szamlazz_settings.reindex_account_rows();
			return false;
		},
		delete_account_row: function() {
			var row = $(this).closest('tr').remove();
			wc_szamlazz_settings.reindex_account_rows();

			//Add empty row if no rows left
			if(wc_szamlazz_settings.$additional_account_table.find('tbody tr').length < 1) {
				wc_szamlazz_settings.add_new_account_row();
			}

			return false;
		},
		reindex_account_rows: function() {
			var sample_row = $('#wc_szamlazz_additional_accounts_sample_row').html();
			wc_szamlazz_settings.$additional_account_table.find('tbody tr').each(function(index){
				$(this).find('input, select').each(function(){
					var name = $(this).data('name');
					name = name.replace('X', index);
					$(this).attr('name', name);
				});
			});
			return false;
		},
		change_account_select_class: function() {
			if(this.selectedIndex === 0) {
				$(this).addClass('placeholder');
			} else {
				$(this).removeClass('placeholder');
			}
		},
		change_x_condition: function(event) {
			var condition = $(this).val();

			//Hide all selects and make them disabled(so it won't be in $_POST)
			$(this).parent().find('select.value').removeClass('selected').prop('disabled', true);
			$(this).parent().find('select.value[data-condition="'+condition+'"]').addClass('selected').prop('disabled', false);

			//Show/hide "exclusively" option based on condition
			var comparison_select = $(this).parent().find('select.comparison');
			var exclusively_option = comparison_select.find('option[value="exclusively"]');
			
			if (condition.startsWith('shipping_') || condition.startsWith('product_')) {
				exclusively_option.show();
			} else {
				exclusively_option.hide();
				//If exclusively was selected, reset to equal
				if (comparison_select.val() === 'exclusively') {
					comparison_select.val('equal');
				}
			}
		},
		add_new_x_condition_row: function(event) {
			var sample_row = $('#wc_szamlazz_'+event.data.group+'_condition_sample_row').html();
			$(this).closest('ul').append(sample_row);
			wc_szamlazz_settings.reindex_x_rows(event.data.group);
			return false;
		},
		delete_x_condition_row: function(event) {
			$(this).parent().remove();
			wc_szamlazz_settings.reindex_x_rows(event.data.group);
			return false;
		},
		reindex_x_rows: function(group) {
			var group = group.replace('_', '-');
			$('.wc-szamlazz-settings-'+group).find('.wc-szamlazz-settings-repeat-item').each(function(index){
				$(this).find('textarea, select, input').each(function(){
					var name = $(this).data('name');
					name = name.replace('X', index);
					$(this).attr('name', name);
				});

				//Reindex conditions too
				$(this).find('li').each(function(index_child){
					$(this).find('select').each(function(){
						var name = $(this).data('name');
						name = name.replace('Y', index_child);
						name = name.replace('X', index);
						$(this).attr('name', name);
					});
				});

				$(this).find('.wc-szamlazz-settings-repeat-select').each(function(){
					var val = $(this).val();
					var label = $(this).find('option:selected').text();
					$(this).parent().find('label span').text(label);
					$(this).parent().find('label span').text(label);
					$(this).parent().find('label i').removeClass().addClass(val);
				});

				//For automations, hide a couple of unnecessary fields
				if(group == 'automations') {
					var document_icon = $(this).find('.wc-szamlazz-settings-automation-document').val();
					$(this).find('.wc-szamlazz-settings-automation-option').show();
					if(document_icon == 'paid') {
						$(this).find('.wc-szamlazz-settings-automation-option:not(:first)').hide();
					}
				}

				//For advanced options, hide a couple of unnecessary fields
				if(group == 'advanced-options') {
					$(this).find('inpput.condition').trigger('change');
					var selected_property = $(this).find('.wc-szamlazz-settings-advanced-option-property').val();
					$(this).find('.wc-szamlazz-settings-advanced-option-option').hide();
					$(this).find('.property-value').prop('disabled', true);
					$(this).find('.wc-szamlazz-settings-advanced-option-option.option-'+selected_property).show();
					$(this).find('.wc-szamlazz-settings-advanced-option-option.option-'+selected_property+' .property-value').prop('disabled', false);
				}

			});
			return false;
		},
		add_new_x_row: function(event) {
			var group = event.data.group;
			var table = event.data.table;
			var singular = group.slice(0, -1);
			var sample_row = $('#wc_szamlazz_'+singular+'_sample_row').html();
			var sample_row_conditon = $('#wc_szamlazz_'+group+'_condition_sample_row').html();
			sample_row = $(sample_row);
			sample_row.find('ul').append(sample_row_conditon);
			table.append(sample_row);
			wc_szamlazz_settings.reindex_x_rows(group);
			return false;
		},
		toggle_x_condition: function(event) {
			var group = event.data.group;
			var checked = $(this).is(":checked");
			var note = $(this).closest('.wc-szamlazz-settings-repeat-item').find('ul.conditions');
			if(checked) {
				//Add empty row if no condtions exists
				if(note.find('li').length < 1) {
					var sample_row = $('#wc_szamlazz_'+group+'_condition_sample_row').html();
					note.append(sample_row);
				}
				note.show();
			} else {
				note.hide();
			}

			//Slightly different for notes
			if(group == 'notes') {
				var append = $(this).closest('.wc-szamlazz-settings-note').find('.wc-szamlazz-settings-note-if-append');
				if(checked) {
					append.show();
				} else {
					append.hide();
				}
			}

			//Slightly different for automations
			if(group == 'automations') {
				var automation = $(this).closest('.wc-szamlazz-settings-automation').find('.wc-szamlazz-settings-automation-if');
				if(checked) {
					automation.show();
				} else {
					automation.hide();
				}
			}

			wc_szamlazz_settings.reindex_x_rows(event.data.group);
		},
		delete_x_row: function(event) {
			$(this).closest('.wc-szamlazz-settings-repeat-item').remove();
			wc_szamlazz_settings.reindex_x_rows(event.data.group);
			return false;
		},

	}

	//Metabox functions
	var wc_szamlazz_metabox = {
		prefix: 'wc_szamlazz_',
		prefix_id: '#wc_szamlazz_',
		prefix_class: '.wc-szamlazz-',
		$metaboxContent: $('#wc_szamlazz_metabox .inside'),
		$disabledState: $('.wc-szamlazz-metabox-disabled'),
		$optionsContent: $('.wc-szamlazz-metabox-generate-options'),
		$autoMsg: $('.wc-szamlazz-metabox-auto-msg'),
		$generateContent: $('.wc-szamlazz-metabox-generate'),
		$optionsButton: $('#wc_szamlazz_invoice_options'),
		$generateButtonInvoice: $('#wc_szamlazz_invoice_generate'),
		$previewButton: $('#wc_szamlazz_invoice_preview'),
		$generateButtonReceipt: $('#wc_szamlazz_receipt_generate'),
		$receiptRowVoidNote: $('.wc-szamlazz-metabox-receipt-void-note'),
		$invoiceRow: $('.wc-szamlazz-metabox-invoices-invoice'),
		$receiptRow: $('.wc-szamlazz-metabox-invoices-receipt'),
		$proformRow: $('.wc-szamlazz-metabox-invoices-proform'),
		$deliveryRow: $('.wc-szamlazz-metabox-invoices-delivery'),
		$depositRow: $('.wc-szamlazz-metabox-invoices-deposit'),
		$voidedRow: $('.wc-szamlazz-metabox-invoices-void'),
		$correctedRow: $('.wc-szamlazz-metabox-invoices-corrected'),
		$voidedReceiptRow: $('.wc-szamlazz-metabox-invoices-void_receipt'),
		$completeRow: $('.wc-szamlazz-metabox-rows-data-complete'),
		$voidRow: $('.wc-szamlazz-metabox-rows-data-void'),
		$voidReasonRow: $('.wc-szamlazz-metabox-rows-data-void-reason'),
		$correctRow: $('.wc-szamlazz-metabox-rows-data-correct'),
		$messages: $('.wc-szamlazz-metabox-messages'),
		$reverseReceiptButton: $('#wc_szamlazz_reverse_receipt'),
		nonce: $('.wc-szamlazz-metabox-content').data('nonce'),
		order: $('.wc-szamlazz-metabox-content').data('order'),
		$uploadDocumentButton: $('.wc-szamlazz-invoice-upload'),
		is_receipt: false,
		init: function() {
			this.$optionsButton.on( 'click', this.show_options );
			$(this.prefix_class+'invoice-toggle').on( 'click', this.toggle_invoice );

			this.$previewButton.on( 'click', this.show_preview);
			this.$generateButtonInvoice.on( 'click', this.generate_invoice );
			this.$generateButtonReceipt.on( 'click', this.generate_receipt );

			this.$completeRow.find('a').on( 'click', this.mark_completed );
			this.$voidRow.find('a').on( 'click', this.void_invoice );
			this.$correctRow.find('a').on( 'click', this.correct_invoice );
			this.$voidReasonRow.find('textarea').on( 'focus', this.void_invoice_reason_focus );
			this.$voidReasonRow.find('textarea').on( 'blur', this.void_invoice_reason_blur );

			this.$messages.find('a').on( 'click', this.hide_message );

			this.$reverseReceiptButton.on( 'click', this.reverse_receipt );

			if(this.$generateButtonReceipt.length) {
				this.is_receipt = true;
			}

			this.$uploadDocumentButton.on( 'click', this.show_upload_modal );

			$( 'body' ).on( 'submit', '#wc-szamlazz-modal-upload-form', this.upload_document);
			$( 'body' ).on( 'change', '#wc_szamlazz_document_upload_file', this.on_upload_document_change);

		},
		loading_indicator: function(button, color) {
			wc_szamlazz_metabox.hide_message();
			button.block({
				message: null,
				overlayCSS: {
					background: color+' url(' + wc_szamlazz_params.loading + ') no-repeat center',
					backgroundSize: '16px 16px',
					opacity: 0.6
				}
			});
		},
		show_options: function() {
			wc_szamlazz_metabox.$optionsButton.toggleClass('active');
			wc_szamlazz_metabox.$optionsContent.slideToggle();
			return false;
		},
		toggle_invoice: function() {
			var note = '';

			//Ask for message
			if($(this).hasClass('off')) {
				note = prompt("Számlakészítés kikapcsolása. Mi az indok?", "Ehhez a rendeléshez nem kell számla.");
				if (!note) {
					return false;
				}
			}

			//Create request
			var data = {
				action: wc_szamlazz_metabox.prefix+'toggle_invoice',
				nonce: wc_szamlazz_metabox.nonce,
				order: wc_szamlazz_metabox.order,
				note: note
			};

			//Show loading indicator
			wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$metaboxContent, '#fff');

			//Make request
			$.post(ajaxurl, data, function(response) {

				//Replace text
				wc_szamlazz_metabox.$disabledState.find('span').text(note);

				//Hide loading indicator
				wc_szamlazz_metabox.$metaboxContent.unblock();

				//Show/hide divs based on response
				if (response.data.state == 'off') {
					wc_szamlazz_metabox.$disabledState.slideDown();
					wc_szamlazz_metabox.$optionsContent.slideUp();
					wc_szamlazz_metabox.$autoMsg.slideUp();
					wc_szamlazz_metabox.$generateContent.slideUp();
					wc_szamlazz_metabox.$voidedRow.slideUp();
				} else {
					wc_szamlazz_metabox.$disabledState.slideUp();
					wc_szamlazz_metabox.$autoMsg.slideDown();
					wc_szamlazz_metabox.$generateContent.slideDown();
				}
			});

			return false;
		},
		show_document_response: function(response) {

			if(response.data.type == 'invoice') {
				wc_szamlazz_metabox.$autoMsg.slideUp();
				wc_szamlazz_metabox.$generateContent.slideUp();
				wc_szamlazz_metabox.$voidedRow.slideUp();

				wc_szamlazz_metabox.$invoiceRow.find('strong').text(response.data.name);
				wc_szamlazz_metabox.$invoiceRow.find('a').attr('href', response.data.link);
				wc_szamlazz_metabox.$invoiceRow.slideDown();
				wc_szamlazz_metabox.$completeRow.slideDown();
				wc_szamlazz_metabox.$voidRow.slideDown();

				if(response.data.completed) {
					wc_szamlazz_metabox.$completeRow.find('a').addClass('completed');
					wc_szamlazz_metabox.$completeRow.find('a').text(response.data.completed);
				}

				if(response.data.delivery) {
					wc_szamlazz_metabox.$deliveryRow.find('strong').text(response.data.delivery.name);
					wc_szamlazz_metabox.$deliveryRow.find('a').attr('href', response.data.delivery.link);
					wc_szamlazz_metabox.$deliveryRow.slideDown();
				}
			}

			if(response.data.type == 'proform') {
				$('#wc_szamlazz_invoice_normal').prop('checked', true);
				wc_szamlazz_metabox.$optionsContent.slideUp();
				wc_szamlazz_metabox.$proformRow.find('strong').text(response.data.name);
				wc_szamlazz_metabox.$proformRow.find('a').attr('href', response.data.link);
				wc_szamlazz_metabox.$proformRow.slideDown();
				wc_szamlazz_metabox.$voidedRow.slideUp();
			}

			if(response.data.type == 'delivery') {
				$('#wc_szamlazz_invoice_normal').prop('checked', true);
				wc_szamlazz_metabox.$optionsContent.slideUp();
				wc_szamlazz_metabox.$deliveryRow.find('strong').text(response.data.name);
				wc_szamlazz_metabox.$deliveryRow.find('a').attr('href', response.data.link);
				wc_szamlazz_metabox.$deliveryRow.slideDown();
				wc_szamlazz_metabox.$voidedRow.slideUp();
			}

			if(response.data.type == 'deposit') {
				$('#wc_szamlazz_invoice_normal').prop('checked', true);
				wc_szamlazz_metabox.$optionsContent.slideUp();
				wc_szamlazz_metabox.$depositRow.find('strong').text(response.data.name);
				wc_szamlazz_metabox.$depositRow.find('a').attr('href', response.data.link);
				wc_szamlazz_metabox.$depositRow.slideDown();
				wc_szamlazz_metabox.$voidedRow.slideUp();
			}

		},
		generate_invoice: function() {
			var $this = $(this);
			var r = confirm($this.data('question'));
			var type = 'invoice';
			if (r != true) {
				return false;
			}

			var account = $('#wc_szamlazz_invoice_account').val();
			var lang = $('#wc_szamlazz_invoice_lang').val();
			var doc_type = $('#wc_szamlazz_invoice_doc_type').val();
			var note = $('#wc_szamlazz_invoice_note').val();
			var deadline = $('#wc_szamlazz_invoice_deadline').val();
			var completed = $('#wc_szamlazz_invoice_completed').val();
			var proform = $('#wc_szamlazz_invoice_proform').is(':checked');
			var delivery = $('#wc_szamlazz_invoice_delivery').is(':checked');
			var deposit = $('#wc_szamlazz_invoice_deposit').is(':checked');
			var paid = $('#wc_szamlazz_mark_as_paid').is(':checked');
			if (proform) type = 'proform';
			if (delivery) type = 'delivery';
			if (deposit) type = 'deposit';

			//Create request
			var data = {
				action: wc_szamlazz_metabox.prefix+'generate_invoice',
				nonce: wc_szamlazz_metabox.nonce,
				order: wc_szamlazz_metabox.order,
				account: account,
				lang: lang,
				doc_type: doc_type,
				note: note,
				deadline: deadline,
				completed: completed,
				type: type,
				paid: paid
			};

			//Show loading indicator
			wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$metaboxContent, '#fff');

			//Make request
			$.post(ajaxurl, data, function(response) {

				//Hide loading indicator
				wc_szamlazz_metabox.$metaboxContent.unblock();

				//Show success/error messages
				wc_szamlazz_metabox.show_messages(response);

				//On success and error
				if(response.data.error) {

				} else {
					wc_szamlazz_metabox.show_document_response(response);
				}

			});

			return false;
		},
		generate_receipt: function() {
			var $this = $(this);
			var r = confirm($this.data('question'));
			if (r != true) {
				return false;
			}

			//Create request
			var data = {
				action: wc_szamlazz_metabox.prefix+'generate_receipt',
				nonce: wc_szamlazz_metabox.nonce,
				order: wc_szamlazz_metabox.order
			};

			//Show loading indicator
			wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$metaboxContent, '#fff');

			//Make request
			$.post(ajaxurl, data, function(response) {

				//Hide loading indicator
				wc_szamlazz_metabox.$metaboxContent.unblock();

				//Show success/error messages
				wc_szamlazz_metabox.show_messages(response);

				//On success and error
				if(response.data.error) {

				} else {
					wc_szamlazz_metabox.$autoMsg.slideUp();
					wc_szamlazz_metabox.$generateContent.slideUp();
					wc_szamlazz_metabox.$receiptRow.find('strong').text(response.data.name);
					wc_szamlazz_metabox.$receiptRow.find('a').attr('href', response.data.link);
					wc_szamlazz_metabox.$receiptRow.slideDown();
					wc_szamlazz_metabox.$voidRow.slideDown();
				}

			});

			return false;
		},
		mark_completed_timeout: false,
		mark_completed: function() {
			var $this = $(this);

			//Do nothing if already marked completed
			if($this.hasClass('completed')) return false;

			if($this.hasClass('confirm')) {

				//Reset timeout
				clearTimeout(wc_szamlazz_metabox.mark_completed_timeout);

				//Show loading indicator
				wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$completeRow, '#fff');

				//Create request
				var data = {
					action: wc_szamlazz_metabox.prefix+'mark_completed',
					nonce: wc_szamlazz_metabox.nonce,
					order: wc_szamlazz_metabox.order,
				};

				$.post(ajaxurl, data, function(response) {

					//Hide loading indicator
					wc_szamlazz_metabox.$completeRow.unblock();

					//Show success/error messages
					wc_szamlazz_metabox.show_messages(response);

					if(response.data.error) {
						//On success and error
						$this.fadeOut(function(){
							$this.text($this.data('trigger-value'));
							$this.removeClass('confirm');
							$this.fadeIn();
						});
					} else {
						//On success and error
						$this.fadeOut(function(){
							$this.text(response.data.completed);
							$this.addClass('completed');
							$this.fadeIn();
							$this.removeClass('confirm');
						});
					}

				});

			} else {
				wc_szamlazz_metabox.mark_completed_timeout = setTimeout(function(){
					$this.fadeOut(function(){
						$this.text($this.data('trigger-value'));
						$this.fadeIn();
						$this.removeClass('confirm');
					});
				}, 5000);

				$this.addClass('confirm');
				$this.fadeOut(function(){
					$this.text('Biztos?')
					$this.fadeIn();
				});
			}

			return false;

		},
		void_invoice_timeout: false,
		void_invoice: function() {
			var $this = $(this);

			//Do nothing if already marked completed
			if($this.hasClass('confirm')) {

				//Reset timeout
				clearTimeout(wc_szamlazz_metabox.void_invoice_timeout);
				wc_szamlazz_metabox.$voidReasonRow.slideUp();

				//Show loading indicator
				wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$voidRow, '#fff');

				//Get textarea vale
				var reason = $('#wc_szamlazz_void_note').val();

				//Set request route
				var request_suffix = wc_szamlazz_metabox.is_receipt ? 'void_receipt' : 'void_invoice';

				//Create request
				var data = {
					action: wc_szamlazz_metabox.prefix+request_suffix,
					nonce: wc_szamlazz_metabox.nonce,
					order: wc_szamlazz_metabox.order,
					reason: reason
				};

				$.post(ajaxurl, data, function(response) {

					//Hide loading indicator
					wc_szamlazz_metabox.$voidRow.unblock();

					//Show success/error messages
					wc_szamlazz_metabox.show_messages(response);

					//On success and error
					if(response.data.error) {

					} else {

						wc_szamlazz_metabox.$invoiceRow.slideUp();
						wc_szamlazz_metabox.$completeRow.slideUp();
						wc_szamlazz_metabox.$deliveryRow.slideUp();
						wc_szamlazz_metabox.$depositRow.slideUp();
						wc_szamlazz_metabox.$receiptRow.slideUp();
						wc_szamlazz_metabox.$correctRow.slideUp();
						wc_szamlazz_metabox.$proformRow.slideUp();
						wc_szamlazz_metabox.$voidRow.slideUp(function(){
							$this.text(response.data.completed);
							$this.removeClass('confirm');
						});

						//If we need to delete the proform invoice too, hide that one too
						if(wc_szamlazz_params.delete_proform_too == 'yes') {
							wc_szamlazz_metabox.$proformRow.slideUp();
						}

						wc_szamlazz_metabox.$generateContent.slideDown();
						wc_szamlazz_metabox.$autoMsg.slideDown();

						//Reload page if we voided a receipt
						if(wc_szamlazz_metabox.is_receipt) {
							wc_szamlazz_metabox.$voidedReceiptRow.find('strong').text(response.data.name);
							wc_szamlazz_metabox.$voidedReceiptRow.find('a').attr('href', response.data.link);
							wc_szamlazz_metabox.$voidedReceiptRow.slideDown();

							wc_szamlazz_metabox.$receiptRowVoidNote.slideDown();
							wc_szamlazz_metabox.$generateContent.slideUp();
						} else {
							//If theres no name, it was a proform delete
							if(response.data.name) {
								wc_szamlazz_metabox.$voidedRow.find('strong').text(response.data.name);
								wc_szamlazz_metabox.$voidedRow.find('a').attr('href', response.data.link);
								wc_szamlazz_metabox.$voidedRow.slideDown();
							}
						}

					}

					//On success and error
					$this.fadeOut(function(){
						$this.text($this.data('trigger-value'));
						$this.fadeIn();
						$this.removeClass('confirm');
					});

				});

			} else {
				wc_szamlazz_metabox.void_invoice_timeout = setTimeout(function(){
					$this.fadeOut(function(){
						$this.text($this.data('trigger-value'));
						$this.fadeIn();
						$this.removeClass('confirm');
					});
					wc_szamlazz_metabox.$voidReasonRow.slideUp();
				}, 5000);

				$this.addClass('confirm');
				$this.fadeOut(function(){
					$this.text($this.data('question'))
					$this.fadeIn();
				});

				wc_szamlazz_metabox.$voidReasonRow.slideDown();
			}

			return false;

		},
		void_invoice_reason_focus: function(){
			console.log('void_invoice_reason_focus');
			clearTimeout(wc_szamlazz_metabox.void_invoice_timeout);
		},
		void_invoice_reason_blur: function(){
			clearTimeout(wc_szamlazz_metabox.void_invoice_timeout);
			var $this = wc_szamlazz_metabox.$voidRow.find('a');
			wc_szamlazz_metabox.void_invoice_timeout = setTimeout(function(){
				$this.fadeOut(function(){
					$this.text($this.data('trigger-value'));
					$this.fadeIn();
					$this.removeClass('confirm');
				});
				wc_szamlazz_metabox.$voidReasonRow.slideUp();
			}, 5000);
		},
		correct_invoice_timeout: false,
		correct_invoice: function() {
			var $this = $(this);

			//Do nothing if already marked completed
			if($this.hasClass('confirm')) {

				//Reset timeout
				clearTimeout(wc_szamlazz_metabox.correct_invoice_timeout);

				//Show loading indicator
				wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$correctRow, '#fff');

				//Set request route
				var request_suffix = wc_szamlazz_metabox.is_receipt ? 'void_receipt' : 'void_invoice';

				//Create request
				var account = $('#wc_szamlazz_invoice_account').val();
				var lang = $('#wc_szamlazz_invoice_lang').val();
				var note = $('#wc_szamlazz_invoice_note').val();
				var deadline = $('#wc_szamlazz_invoice_deadline').val();
				var completed = $('#wc_szamlazz_invoice_completed').val();

				//Create request
				var data = {
					action: wc_szamlazz_metabox.prefix+'generate_invoice',
					nonce: wc_szamlazz_metabox.nonce,
					order: wc_szamlazz_metabox.order,
					account: account,
					lang: lang,
					note: note,
					deadline: deadline,
					completed: completed,
					type: 'corrected'
				};

				$.post(ajaxurl, data, function(response) {

					//Hide loading indicator
					wc_szamlazz_metabox.$correctRow.unblock();

					//Show success/error messages
					wc_szamlazz_metabox.show_messages(response);

					//On success and error
					if(response.data.error) {

					} else {
						wc_szamlazz_metabox.$voidRow.slideUp(function(){
							$this.text(response.data.completed);
							$this.removeClass('confirm');
						});

						wc_szamlazz_metabox.$correctRow.slideUp(function(){
							$this.text(response.data.completed);
							$this.removeClass('confirm');
						});

						//Show corrected invoice id and download link
						wc_szamlazz_metabox.$correctedRow.find('strong').text(response.data.name);
						wc_szamlazz_metabox.$correctedRow.find('a').attr('href', response.data.link);
						wc_szamlazz_metabox.$correctedRow.slideDown();
					}

					//On success and error
					$this.fadeOut(function(){
						$this.text($this.data('trigger-value'));
						$this.fadeIn();
						$this.removeClass('confirm');
					});

				});

			} else {
				wc_szamlazz_metabox.void_invoice_timeout = setTimeout(function(){
					$this.fadeOut(function(){
						$this.text($this.data('trigger-value'));
						$this.fadeIn();
						$this.removeClass('confirm');
					});
				}, 5000);

				$this.addClass('confirm');
				$this.fadeOut(function(){
					$this.text($this.data('question'))
					$this.fadeIn();
				});
			}

			return false;

		},
		show_messages: function(response) {
			if(response.data.messages && response.data.messages.length > 0) {
				this.$messages.removeClass('wc-szamlazz-metabox-messages-success');
				this.$messages.removeClass('wc-szamlazz-metabox-messages-error');

				if(response.data.error) {
					this.$messages.addClass('wc-szamlazz-metabox-messages-error');
				} else {
					this.$messages.addClass('wc-szamlazz-metabox-messages-success');
				}

				$ul = this.$messages.find('ul');
				$ul.html('');

				$.each(response.data.messages, function(i, value) {
					var li = $('<li>')
					li.append(value);
					$ul.append(li);
				});
				this.$messages.slideDown();
			}
		},
		hide_message: function() {
			wc_szamlazz_metabox.$messages.slideUp();
			return false;
		},
		reverse_receipt: function() {
			//Create request
			var data = {
				action: wc_szamlazz_metabox.prefix+'reverse_receipt',
				nonce: wc_szamlazz_metabox.nonce,
				order: wc_szamlazz_metabox.order
			};

			//Show loading indicator
			wc_szamlazz_metabox.loading_indicator(wc_szamlazz_metabox.$metaboxContent, '#fff');

			//Make request
			$.post(ajaxurl, data, function(response) {
				window.location.reload();
			});
		},
		show_preview: function() {
			var note = $('#wc_szamlazz_invoice_note').val();
			var deadline = $('#wc_szamlazz_invoice_deadline').val();
			var completed = $('#wc_szamlazz_invoice_completed').val();
			var account = $('#wc_szamlazz_invoice_account').val();
			var url = $(this).data('url');
			var params = {'note': note, 'deadline': deadline, 'completed': completed, 'account': account};
			url += '&' + $.param(params);

			//Change url to include options
			$(this).attr('href', url);

			return true;
		},
		show_upload_modal: function() {
			$(this).WCBackboneModal({
				template: 'wc-szamlazz-modal-upload',
				variable : {order_id: wc_szamlazz_metabox.order}
			});

			$('#wc_szamlazz_mark_paid_date').datepicker({
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showButtonPanel: true,
				maxDate: 0
			});
			return false;
		},
		on_upload_document_change: function(e) {
			var fileInput = e.target;
			if (fileInput.files.length > 0) {
				var filename = fileInput.files[0].name;
				var nameWithoutExtension = filename.split('.').slice(0, -1).join('.');
				$('.wc-szamlazz-modal-upload #wc_szamlazz_document_upload_name').val(nameWithoutExtension);
			}
		},
		upload_document: function(e) {
			e.preventDefault();

			//Collect form data
			var form = $(this);
			var formdata = (window.FormData) ? new FormData(form[0]) : null;
			var data = (formdata !== null) ? formdata : form.serialize();

			//Validate
			var document_name = $('.wc-szamlazz-modal-upload #wc_szamlazz_document_upload_name').val();
			var document_file = $('.wc-szamlazz-modal-upload #wc_szamlazz_document_upload_file').val();
			var valid = true;

			//Append nonce and action
			formdata.append('action', 'wc_szamlazz_upload_document');
			formdata.append('nonce', wc_szamlazz_metabox.nonce);
			formdata.append('order', wc_szamlazz_metabox.order);

			if(!document_name) {
				valid = false;
				$('#wc_szamlazz_document_upload_name').parent().addClass('validate');
				setTimeout(function(){
					$('#wc_szamlazz_document_upload_name').parent().removeClass('validate');
				}, 1000);
			}

			if(!document_file) {
				valid = false;
				$('#wc_szamlazz_document_upload_file').parent().addClass('validate');
				setTimeout(function(){
					$('#wc_szamlazz_document_upload_file').parent().removeClass('validate');
				}, 1000);
			}

			//If valid, submit form
			if(valid) {

				//Show loading indicator
				wc_szamlazz_metabox.loading_indicator($('.wc-szamlazz-modal-upload-form'), '#fff');

				//Make POST request
				$.ajax({
					type: 'POST',
					url: ajaxurl,
					contentType: false,
					processData: false,
					dataType: 'JSON',
					status: 200,
					data: formdata,
					success: function(response){
						console.log(response);

						//Show success/error messages
						wc_szamlazz_bulk_actions.show_messages(response, 'uploader-results');

						//Hide loading indicator
						$('.wc-szamlazz-modal-upload-form').unblock();

						//On success, update metabox
						wc_szamlazz_metabox.show_document_response(response);
						$('.modal-close-link').trigger('click');

					}
				});

			}

			return false;
		}
	}

	// Hide notice
	$( '.wc-szamlazz-notice .wc-szamlazz-hide-notice').on('click', function(e) {
		e.preventDefault();
		var el = $(this).closest('.wc-szamlazz-notice');
		$(el).find('.wc-szamlazz-wait').remove();
		$(el).append('<div class="wc-szamlazz-wait"></div>');
		if ( $('.wc-szamlazz-notice.updating').length > 0 ) {
			var button = $(this);
			setTimeout(function(){
				button.triggerHandler( 'click' );
			}, 100);
			return false;
		}
		$(el).addClass('updating');
		$.post( ajaxurl, {
				action: 	'wc_szamlazz_hide_notice',
				security: 	$(this).data('nonce'),
				notice: 	$(this).data('notice'),
				remind: 	$(this).hasClass( 'remind-later' ) ? 'yes' : 'no'
		}, function(){
			$(el).removeClass('updating');
			$(el).fadeOut(100);
		});
	});

	//Bulk actions
	var wc_szamlazz_bulk_actions = {
		init: function() {
			$( '#wpbody' ).on( 'click', '#doaction', function() {
				if($('#bulk-action-selector-top').val() == 'wc_szamlazz_bulk_grouped_generate') {
					wc_szamlazz_bulk_actions.show_grouped_modal();
					return false;
				}
			});

			$( '#wpbody' ).on( 'click', '#doaction2', function() {
				if($('#bulk-action-selector-bottom').val() == 'wc_szamlazz_bulk_grouped_generate') {
					wc_szamlazz_bulk_actions.show_grouped_modal();
					return false;
				}
			});

			$(document).on( 'click', '#generate_grouped_invoice', this.generate_grouped_invoices );

			//Listen for keyboard shortcuts
			var mPressed = false;
			$(window).keydown(function(evt) {
				if (evt.which == 77) { //m
					mPressed = true;
				}
			}).keyup(function(evt) {
				if (evt.which == 77) { //m
					mPressed = false;
				}
			});

			//Mark order as paid in order manager
			$( '#wpbody' ).on( 'click', 'a.wc-szamlazz-mark-paid-button', function() {
				if($(this).hasClass('paid')) return false;
				var order_id = $(this).data('order');
				var nonce = $(this).data('nonce');
				var today = $.datepicker.formatDate('yy-mm-dd', new Date());

				if(mPressed) {
					$(this).addClass('paid');
					$(this).tipTip({ content: 'Fizetve: '+today });
					$('#tiptip_content').text('Fizetve: '+today);

					//Create request
					var data = {
						action: wc_szamlazz_metabox.prefix+'mark_completed',
						nonce: nonce,
						order: order_id,
					};

					//Make an ajax call in the background. No error handling, since this usually works just fine
					$.post(ajaxurl, data, function(response) { });

				} else {
					$(this).WCBackboneModal({
						template: 'wc-szamlazz-modal-mark-paid',
						variable : {order_id: order_id}
					});

					$('#wc_szamlazz_mark_paid_date').datepicker({
						dateFormat: 'yy-mm-dd',
						numberOfMonths: 1,
						showButtonPanel: true,
						maxDate: 0
					});
				}

				return false;
			});

			//Mark order as paid in order manager
			$( 'body' ).on( 'click', '#wc_szamlazz_mark_paid', function() {
				var order_id = $(this).data('order');
				var nonce = $(this).data('nonce');
				var date = $('#wc_szamlazz_mark_paid_date').val();

				//Create request
				var data = {
					action: wc_szamlazz_metabox.prefix+'mark_completed',
					nonce: nonce,
					order: order_id,
					date: date
				};

				//Change to a green checkmark and update tooltip text
				$('a.wc-szamlazz-mark-paid-button[data-order="'+order_id+'"]').addClass('paid');
				$('a.wc-szamlazz-mark-paid-button[data-order="'+order_id+'"]').tipTip({ content: 'Fizetve: '+date });

				//Make an ajax call in the background. No error handling, since this usually works just fine
				$.post(ajaxurl, data, function(response) { });

				//Close modal
				$('.modal-close-link').trigger('click');

				return false;
			});

		},
		show_grouped_modal: function() {
			var checkedOrders = jQuery("#the-list input[name='id[]']:checked");
			var orderIds = [];
			var ul = $('<ul/>');
			ul.addClass('wc-szamlazz-modal-grouped-generate-list');

			$(checkedOrders).each(function(i) {
				var order_id = $(checkedOrders[i]).val();
				var column_name = $(checkedOrders[i]).parents('.type-shop_order').find('a.order-view').text();
				ul.append('<li><label><input type="radio" name="main_order_id" value="'+order_id+'"> '+column_name+'</label></li>');
				orderIds.push(order_id);
			});

			if(checkedOrders.length === 0) {
				orderIds = false;
			}

			$(this).WCBackboneModal({
				template: 'wc-szamlazz-modal-grouped-generate',
				variable : {orders: ul.prop("outerHTML"), orderIds: orderIds}
			});
			return false;
		},
		generate_grouped_invoices: function() {
			var orderIds = $(this).data('orders');
			var nonce = $(this).data('nonce');
			var mainOrder = $('input[name=main_order_id]:checked', '.wc-szamlazz-modal-grouped-generate-list').val();

			if(!mainOrder) {
				$('.wc-szamlazz-modal-grouped-generate-list').addClass('validate');
				setTimeout(function(){
					$('.wc-szamlazz-modal-grouped-generate-list').removeClass('validate');
				}, 1000);
				return false;
			}

			//Show loading indicator
			wc_szamlazz_metabox.loading_indicator($('.wc-szamlazz-modal-grouped-generate-form'), '#fff');

			//Create request
			var data = {
				action: wc_szamlazz_metabox.prefix+'generate_grouped_invoice',
				nonce: nonce,
				orders: orderIds,
				main_order: mainOrder
			};

			$.post(ajaxurl, data, function(response) {

				//Hide loading indicator
				$('.wc-szamlazz-modal-grouped-generate-form').unblock();

				//Show success/error messages
				wc_szamlazz_bulk_actions.show_messages(response, 'grouped-generate-results');

				if(response.data.error) {

				} else {
					$('.wc-szamlazz-modal-grouped-generate-download').slideDown();
					$('.wc-szamlazz-modal-grouped-generate-download-invoice').find('strong').text(response.data.name);
					$('.wc-szamlazz-modal-grouped-generate-download-invoice').attr('href', response.data.link);
					$('.wc-szamlazz-modal-grouped-generate-download-order').attr('href', response.data.order_link);
					$('.wc-szamlazz-modal-grouped-generate-form, .wc-szamlazz-modal-grouped-generate footer').slideUp();
				}

			});

			return false;
		},
		show_messages: function(response, id) {
			$messages = $('.wc-szamlazz-modal-'+id);
			if(response.data.messages && response.data.messages.length > 0) {
				$messages.removeClass('wc-szamlazz-metabox-messages-success');
				$messages.removeClass('wc-szamlazz-metabox-messages-error');

				if(response.data.error) {
					$messages.addClass('wc-szamlazz-metabox-messages-error');
				} else {
					$messages.addClass('wc-szamlazz-metabox-messages-success');
				}

				$ul = $messages.find('ul');
				$ul.html('');

				$.each(response.data.messages, function(i, value) {
					var li = $('<li>')
					li.append(value);
					$ul.append(li);
				});
				$messages.slideDown();
			}
		},
	}

	//Metabox
	if($('#wc_szamlazz_metabox').length) {
		wc_szamlazz_metabox.init();
	}

	//Init settings page
	if($('body.wc-szamlazz-settings').length) {
		wc_szamlazz_settings.init();
	}

	//Init bulk actions
	if($('.wc-szamlazz-bulk-actions').length || $('#tmpl-wc-szamlazz-modal-grouped-generate').length) {
		wc_szamlazz_bulk_actions.init();
	}

	//Background generate actions
	var wc_szamlazz_background_actions = {
		$menu_bar_item: $('#wp-admin-bar-wc-szamlazz-bg-generate-loading'),
		$link_stop: $('#wc-szamlazz-bg-generate-stop'),
		$link_refresh: $('#wc-szamlazz-bg-generate-refresh'),
		finished: false,
		nonce: '',
		init: function() {
			this.$link_stop.on( 'click', this.stop );
			this.$link_refresh.on( 'click', this.reload_page );

			//Store nonce
			this.nonce = this.$link_stop.data('nonce');

			//Refresh status every 5 second
			var refresh_action = this.refresh;
			setTimeout(refresh_action, 5000);

		},
		reload_page: function() {
			location.reload();
			return false;
		},
		stop: function() {
			var data = {
				action: 'wc_szamlazz_bg_generate_stop',
				nonce: wc_szamlazz_background_actions.nonce,
			}

			$.post(ajaxurl, data, function(response) {
				wc_szamlazz_background_actions.mark_stopped();
			});
			return false;
		},
		refresh: function() {
			var data = {
				action: 'wc_szamlazz_bg_generate_status',
				nonce: wc_szamlazz_background_actions.nonce,
			}

			if(!wc_szamlazz_background_actions.finished) {
				$.post(ajaxurl, data, function(response) {
					if(response.data.finished) {
						wc_szamlazz_background_actions.mark_finished();
					} else {
						//Repeat after 5 seconds
						setTimeout(wc_szamlazz_background_actions.refresh, 5000);
					}

				});
			}
		},
		mark_finished: function() {
			this.finished = true;
			this.$menu_bar_item.addClass('finished');
		},
		mark_stopped: function() {
			this.mark_finished();
			this.$menu_bar_item.addClass('stopped');
		}
	}

	//Init background generate loading indicator
	if($('#wp-admin-bar-wc-szamlazz-bg-generate-loading').length) {
		wc_szamlazz_background_actions.init();
	}

	//Bulk actions
	var wc_szamlazz_bulk_actions_v2 = {
		needs_label: [],
		total_labels: 0,
		labels_generated: 0,
		document_type: ['invoice'],
		init: function() {

			//Handle bulk download action
			$( '#wpbody' ).on( 'click', '#doaction', function() {
				var value = $('#bulk-action-selector-top').val();
				var type = false;
				if(value == 'wc_szamlazz_bulk_download_invoices') {
					type = 'download';
				}

				if(value == 'wc_szamlazz_bulk_generate_documents') {
					type = 'generator';
				}

				if(value == 'wc_szamlazz_bulk_generate_invoices') {
					type = 'generate';
				}

				if(type) {

					//Show only if there are selected items
					if($("#the-list .check-column input:checked").length > 0) {
						wc_szamlazz_bulk_actions_v2.show_bulk_generate_modal({type: type});
					}

					return false;
				}
			});

			//Show modal automatically on select change
			$('body').on('change', '#bulk-action-selector-top', function(){
				var type = false;
				var value = $('#bulk-action-selector-top').val();
				if(value == 'wc_szamlazz_bulk_download_invoices') {
					type = 'download';
				}

				if(value == 'wc_szamlazz_bulk_generate_documents') {
					type = 'generator';
				}
				
				if(type) {

					//Show only if there are selected items
					if($("#the-list .check-column input:checked").length > 0) {
						wc_szamlazz_bulk_actions_v2.show_bulk_generate_modal({type: type});
					}

					return false;
				}
			});

			//Select all checkbox in table header
			$('body').on('change', '.wc-szamlazz-modal-generate-selectall', function(){ 
				var checked = $(this).is(':checked');
				$('.wc-szamlazz-modal-generate table input[type="checkbox"]').attr('checked', checked);
			});

			//When the modal closes, cancel all ajax requests
			$('body').on( 'wc_backbone_modal_removed', this.on_modal_close );

			//Print button
			$('body').on( 'click', '.wc-szamlazz-modal-generate-button-download', this.download_in_bulk );
			$('body').on( 'click', '.wc-szamlazz-modal-generate-button-print', this.print_in_bulk );
			$('body').on( 'click', '.wc-szamlazz-modal-generate-document-print', this.print_single_label );

			//Toggle bulk generator options
			$(document).on( 'change', '.wc-szamlazz-modal-generate-form input[name="bulk_invoice_type"]', this.toggle_bulk_generator_options );

			//COntinue button
			$(document).on( 'click', '.wc-szamlazz-modal-generate-button-next', this.generate_labels );

			//When download type changes
			$(document).on( 'change', '.wc-szamlazz-modal-generate-download-type input[name="bulk_download_type"]', this.toggle_bulk_download_type );

		},
		show_bulk_generate_modal: function(options) {

			//Get selected order details, input named id[] and checked
			var selected_orders = [];
			$("#the-list .check-column input:checked").each(function(){
				var $row = $(this).parents('tr');
				var order_details = $row.find('.wc-szamlazz-order-details').data('order-details');
				if(order_details.order_id) {
					order_details.table_row = $row;
					selected_orders.push(order_details);
				}
			});

			//Show modal
			$(this).WCBackboneModal({
				template: 'wc-szamlazz-modal-generate'
			});

			//Reset content
			var $table = $('.wc-szamlazz-modal-generate table');
			wc_szamlazz_bulk_actions_v2.reset_modal_data();

			//Set total labels
			wc_szamlazz_bulk_actions_v2.total_labels = selected_orders.length;

			//Create an array that needs a label
			wc_szamlazz_bulk_actions_v2.needs_label = [];

			//Add selected orders to table
			selected_orders.forEach(function(order){
				var sample_row = $('#wc_szamlazz_modal_generate_sample_row').html();
				sample_row = $(sample_row);
				sample_row.find('.cell-order-number strong').text('#'+order.order_number);
				sample_row.find('.cell-order-number span').text(order.customer_name);
				sample_row.find('.cell-address span').text(order.billing_address);
				sample_row.data('order-details', order);
				sample_row.find('input').val(order.order_id);

				//Find previously create ddocument
				if(Object.keys(order.documents).length > 0) {
					Object.keys(order.documents).forEach(document => {
						var $link = $('<a>');
						$link.text(order.documents[document].name);
						$link.attr('href', order.documents[document].link);
						$link.addClass('wc-szamlazz-modal-generate-document-'+document);
						$link.attr('download', true);
						sample_row.find('.wc-szamlazz-modal-generate-documents').append($link);
					});
					sample_row.addClass('has-document');
				}

				//Append to the table
				$table.find('tbody').append(sample_row);
			});

			//Change modal title
			var titles = $('.wc-szamlazz-modal-generate h1').data('titles');
			$('.wc-szamlazz-modal-generate h1').html(titles[options.type]);
			$('.wc-szamlazz-modal-generate').attr('data-type', options.type);

			//For invoice type, we can start generating labels immediately
			if(options.type == 'generate') {

				//Start to generate labels
				wc_szamlazz_bulk_actions_v2.check_existing_documents();

				//Start to generate labels
				wc_szamlazz_bulk_actions_v2.generate_label();
				
			}

			//For download type, check for invoices by default
			if(options.type == 'download') {
				wc_szamlazz_bulk_actions_v2.check_existing_documents();
			}

			//Update counter
			wc_szamlazz_bulk_actions_v2.update_counter();
			wc_szamlazz_bulk_actions_v2.type = options.type;			
				
			return false;
		},
		generate_label: function() {

			//If we have no more orders that need a label, stop
			if(wc_szamlazz_bulk_actions_v2.needs_label.length == 0) {
				return false;
			}
				
			//Get order details
			var $row = wc_szamlazz_bulk_actions_v2.needs_label[0];
			var order_details = $row.data('order-details');

			//Make ajax request
			var data = {
				action: 'wc_szamlazz_quick_generate_invoice',
				nonce: wc_szamlazz_params.nonces.generate,
				order: order_details.order_id,
				type: 'invoice'
			};

			if(wc_szamlazz_bulk_actions_v2.options && wc_szamlazz_bulk_actions_v2.options.type) {
				data.type = wc_szamlazz_bulk_actions_v2.options.type;
				data.deadline = wc_szamlazz_bulk_actions_v2.options.deadline;
				data.completed = wc_szamlazz_bulk_actions_v2.options.completed;
				data.account = wc_szamlazz_bulk_actions_v2.options.account;
				data.note = wc_szamlazz_bulk_actions_v2.options.note;
			}

			//Make request
			$.post(ajaxurl, data, function(response) {

				//On success and error
				if(!response.data.error) {
					var $link = $('<a>');
					$link.text(response.data.name);
					$link.attr('href', response.data.link);
					$link.addClass('wc-szamlazz-modal-generate-document-'+response.data.type+' active');
					$link.attr('download', true);
					$row.find('.wc-szamlazz-modal-generate-document-'+response.data.type).remove();
					$row.find('.wc-szamlazz-modal-generate-documents').append($link);
					$row.addClass('has-document');

					//Update order details in table too
					var $table_row = order_details.table_row;
					if(response.data.order_status) {
						//remove wc- prefix
						var status_class = response.data.order_status.status;
						status_class = status_class.replace('wc-', '');
						$table_row.find('td.order_status mark').removeClass(function(index, className) {
							return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
						}).addClass('status-'+status_class);
						$table_row.find('td.order_status span').text(response.data.order_status.name);

						//If we are filtering orders by status, we can hide the row from the table if status changes
						//Check for current status filter, url param status value
						var url = new URL(window.location.href);
						var status = url.searchParams.get("status"); //HPOS
						var post_status = url.searchParams.get("post_status"); //Legacy posts table
						if(status || post_status) {
							if(post_status || status != 'all') {

								//Remove checkbox and hide row
								$table_row.slideUp();
								$table_row.find('.check-column input').prop('checked', false);

								//Get selected status filter
								var $selected_filter = $('ul.subsubsub a.current').parent();
								var $target_filter = $('ul.subsubsub li.'+response.data.order_status.status);

								//And set new counters
								var selected_count = parseInt($selected_filter.find('.count').text().replace(/[^0-9]/g, ''));
								var target_count = parseInt($target_filter.find('.count').text().replace(/[^0-9]/g, ''));
								$selected_filter.find('.count').text('('+(selected_count-1)+')');
								$target_filter.find('.count').text('('+(target_count+1)+')');

							}
						}

					}

					//Create new download link
					var $download_button = $('<a target="_blank">');
					$download_button.attr('href', response.data.link);
					$download_button.addClass('button tips wc-szamlazz-button wc-szamlazz-button-'+response.data.type);
					$table_row.find('td.column-wc_szamlazz').append($download_button);

					//Update data attributes too, so if it triggered again int he same session, it will not generate a new label
					order_details.parcel_number = response.data.number;
					order_details.download_link = response.data.pdf;
					order_details.documents[response.data.type] = {
						name: response.data.name,
						link: response.data.link
					}
					$table_row.find('.wc-szamlazz-order-details').data('order-details', order_details);

				} else {
					$row.addClass('has-error');
					$row.find('input[type="checkbox"]').attr('disabled', true);
				}

				//Remove from needs label array
				wc_szamlazz_bulk_actions_v2.needs_label.shift();

				//Run again to generate next label
				wc_szamlazz_bulk_actions_v2.generate_label();

				//And update progress bar
				wc_szamlazz_bulk_actions_v2.update_counter();

			});

		},
		update_counter: function() {
			wc_szamlazz_bulk_actions_v2.labels_generated = wc_szamlazz_bulk_actions_v2.total_labels - wc_szamlazz_bulk_actions_v2.needs_label.length;
			var labels = $('.wc-szamlazz-modal-generate-progress-bar-text').data('labels');
			
			console.log(wc_szamlazz_bulk_actions_v2.total_labels, wc_szamlazz_bulk_actions_v2.needs_label.length)

			//Stop if cancelled
			if(!labels) return false;

			//Get total label
			var total_label = labels.total.singular;
			if(wc_szamlazz_bulk_actions_v2.total_labels > 1) {
				total_label = labels.total.plural;
				total_label = total_label.replace('%d', wc_szamlazz_bulk_actions_v2.total_labels);
			}

			//Get selected label
			var selected_label = labels.selected.singular;
			if(wc_szamlazz_bulk_actions_v2.total_labels > 1) {
				selected_label = labels.selected.plural;
				selected_label = selected_label.replace('%d', wc_szamlazz_bulk_actions_v2.total_labels);
			}

			//Get progress text
			var progress_label = labels.current.default;
			if(wc_szamlazz_bulk_actions_v2.labels_generated == 1) progress_label = labels.current.singular;
			if(wc_szamlazz_bulk_actions_v2.labels_generated > 1) {
				progress_label = labels.current.plural;
				progress_label = progress_label.replace('%d', wc_szamlazz_bulk_actions_v2.labels_generated);
			}

			//Set width percentage
			var percentage = wc_szamlazz_bulk_actions_v2.labels_generated / wc_szamlazz_bulk_actions_v2.total_labels * 100;
			$('.wc-szamlazz-modal-generate-progress-bar-inner').css('width', percentage+'%');

			//Set text labels
			$('.wc-szamlazz-modal-generate-progress-bar-text-current').text(progress_label);
			$('.wc-szamlazz-modal-generate-progress-bar-text-total').text(total_label);
			$('.wc-szamlazz-modal-generate-progress-bar-text-selected').text(selected_label);

			//If we have no more labels to generate, show print button
			if(wc_szamlazz_bulk_actions_v2.needs_label.length == 0) {
				setTimeout(function(){
					$('.wc-szamlazz-modal-generate').addClass('done');
				}, 500);

				setTimeout(function(){
					$('.wc-szamlazz-modal-generate').removeClass('done');
					$('.wc-szamlazz-modal-generate').addClass('finished');
				}, 500);
			}

		},
		on_modal_close: function(_, modal_id) {
			if(modal_id == 'wc-szamlazz-modal-generate') {
				wc_szamlazz_bulk_actions_v2.reset_modal_data();
			}
		},
		reset_modal_data: function() {
			var $table = $('.wc-szamlazz-modal-generate-table');
			$table.find('tbody').html('');
			$('.wc-szamlazz-modal-generate').removeClass('done');
			$('.wc-szamlazz-modal-generate').removeClass('finished');
			$('.wc-szamlazz-modal-generate').removeClass('generator');
			wc_szamlazz_bulk_actions_v2.needs_label = [];
			wc_szamlazz_bulk_actions_v2.total_labels = 0;
			wc_szamlazz_bulk_actions_v2.labels_generated = 0;
			wc_szamlazz_bulk_actions_v2.type = 'generator';
			wc_szamlazz_bulk_actions_v2.options = {};
			wc_szamlazz_bulk_actions_v2.document_type = ['invoice'];
		},
		print_in_bulk: async function() {

			//Show loading indicator
			var $buttons = $(this).parents('footer');
			$buttons.block({
				message: null,
				overlayCSS: {
					background: '#fcfcfc url(' + wc_szamlazz_params.loading + ') no-repeat center',
					backgroundSize: '16px 16px',
					opacity: 0.6
				}
			});

			//Generate print URL
			var pdf_urls = wc_szamlazz_bulk_actions_v2.generate_pdf_urls();
			var merged_pdf_blob = await wc_szamlazz_bulk_actions_v2.merge_pdf_files(pdf_urls);
			var merged_pdf_blob_url = URL.createObjectURL(merged_pdf_blob);

			//Open print modal
			printJS({printable: merged_pdf_blob_url, type: 'pdf', onLoadingEnd: function(){

				//Hide loading indicator
				$buttons.unblock();

				//Revoke object URL
				URL.revokeObjectURL(merged_pdf_blob_url);

			}});

			return false;
		},
		download_in_bulk: async function() {

			//Generate print URL
			var pdf_urls = wc_szamlazz_bulk_actions_v2.generate_pdf_urls();
			var merged_pdf_blob = await wc_szamlazz_bulk_actions_v2.merge_pdf_files(pdf_urls);

			//Download the file
			saveAs(merged_pdf_blob, 'merged.pdf');

			return false;
		},
		generate_pdf_urls: function() {
			var checkedOrders = $(".wc-szamlazz-modal-generate table tr.has-document input:checked");
			var orderIds = [];

			//Collect selected items
			$(checkedOrders).each(function(i) {
				var $row = $(checkedOrders[i]).parents('tr');
				$row.find('.wc-szamlazz-modal-generate-documents a.active').each(function(){
					var pdf_url = $(this).attr('href');
					orderIds.push(pdf_url);	
				});
			});

			return orderIds;
		},
		print_single_label: function() {
			var pdf_url = $(this).parent().find('a.active').attr('href');
			if(pdf_url) {
				printJS(pdf_url);
			}
			return false;
		},
		merge_pdf_files: async function(urls) {
			console.log(urls);

        	const pdfDoc = await PDFLib.PDFDocument.create();
			const numDocs = urls.length;    
			for(var i = 0; i < numDocs; i++) {
				try {
					const donorPdfBytes = await fetch(urls[i]).then(res => res.arrayBuffer());
					const donorPdfDoc = await PDFLib.PDFDocument.load(donorPdfBytes);
					if(!donorPdfDoc) continue;
					const docLength = donorPdfDoc.getPageCount();
					for(var k = 0; k < docLength; k++) {
						const [donorPage] = await pdfDoc.copyPages(donorPdfDoc, [k]);
						pdfDoc.addPage(donorPage);
					}
				} catch(e) {
					console.log(e);
				}
			}
			const mergedPdfBytes = await pdfDoc.save();
			const blob = new Blob([mergedPdfBytes], { type: 'application/pdf' });
			return blob;
		},
		toggle_bulk_generator_options: function() {
			var value = $(this).val();
			var label = $(this).parent().text();
			$('.wc-szamlazz-modal-generate-table-document-type').text(label);
			wc_szamlazz_bulk_actions_v2.document_type = [value];
			wc_szamlazz_bulk_actions_v2.check_existing_documents();

			if(value == 'void') {
				$('.hidden-if-void').hide();
			} else {
				$('.hidden-if-void').show();
			}
		},
		generate_labels: function() {
			wc_szamlazz_bulk_actions_v2.options = {
				account: $('.wc-szamlazz-modal-generate #wc_szamlazz_bulk_invoice_account').val(),
				type: $('.wc-szamlazz-modal-generate input[name="bulk_invoice_type"]:checked').val(),
				note: $('.wc-szamlazz-modal-generate #wc_szamlazz_bulk_invoice_note').val(),
				deadline: $('.wc-szamlazz-modal-generate #wc_szamlazz_bulk_invoice_deadline').val(),
				completed: $('.wc-szamlazz-modal-generate #wc_szamlazz_bulk_invoice_completed').val(),
			}
			wc_szamlazz_bulk_actions_v2.document_type = [wc_szamlazz_bulk_actions_v2.options.type];
			wc_szamlazz_bulk_actions_v2.check_existing_documents();
			$('.wc-szamlazz-modal-generate').attr('data-type', 'generate');
			$('.wc-szamlazz-modal-generate').removeClass('finished');
			wc_szamlazz_bulk_actions_v2.generate_label();
			wc_szamlazz_bulk_actions_v2.update_counter();

			return false;
		},
		check_existing_documents: function() {
			var document_types = wc_szamlazz_bulk_actions_v2.document_type;
			wc_szamlazz_bulk_actions_v2.needs_label = [];
			$('.wc-szamlazz-modal-generate-table tbody tr').each(function(){
				var $row = $(this);
				var order_details = $row.data('order-details');
				$row.removeClass('has-document');
				$row.find('.wc-szamlazz-modal-generate-documents a').removeClass('active');
				document_types.forEach(function(document_type){
					if(order_details.documents[document_type]) {
						$row.addClass('has-document');
						$row.attr('data-type', document_type);
						$row.find('.wc-szamlazz-modal-generate-document-'+document_type).addClass('active');
						console.log('.wc-szamlazz-modal-generate-document-'+document_type);
					} else {
						wc_szamlazz_bulk_actions_v2.needs_label.push($row);
					}
				});
			});

			//Set already generated labels
			wc_szamlazz_bulk_actions_v2.labels_generated = wc_szamlazz_bulk_actions_v2.total_labels - wc_szamlazz_bulk_actions_v2.needs_label.length;

		},
		toggle_bulk_download_type: function() {
			var $form = $(this).parents('.wc-szamlazz-modal-generate-download-type');
			var values = $form.find('input:checked').map(function(){return $(this).val()}).get();
			wc_szamlazz_bulk_actions_v2.document_type = values;	
			wc_szamlazz_bulk_actions_v2.check_existing_documents();
		}
	}

	//Init bulk actions
	if($('.wc-szamlazz-order-details').length) {
		wc_szamlazz_bulk_actions_v2.init();
	}




});
