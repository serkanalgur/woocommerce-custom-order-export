/**
 * WExport Template Manager
 *
 * Handles saving, loading, and managing export templates via AJAX.
 */

(function($) {
	'use strict';

	const WExportTemplates = {
		// Track currently loaded template
		currentTemplateId: null,
		currentTemplateName: null,

		/**
		 * Initialize template manager
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.loadTemplatesList();
			this.loadDefaultTemplateIfSet();
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$saveTemplateBtn = $('#wexport-save-template-btn');
			this.$templateNameInput = $('#wexport-template-name');
			this.$templatesList = $('#wexport-templates-list');
			this.$templatesDropdown = $('#wexport-templates-dropdown');
			this.$loadTemplateBtn = $('#wexport-load-template-btn');
			this.$templatesModal = $('#wexport-templates-modal');
			this.$templatesClose = $('#wexport-templates-close');
			this.$manageTemplatesBtn = $('#wexport-manage-templates-btn');
			this.$currentTemplateDisplay = $('#wexport-current-template');
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			const self = this;

			// Save template button
			if (this.$saveTemplateBtn.length) {
				this.$saveTemplateBtn.on('click', function(e) {
					e.preventDefault();
					self.showSaveTemplateDialog();
				});
			}

			// Load template from dropdown
			if (this.$loadTemplateBtn.length) {
				this.$loadTemplateBtn.on('click', function(e) {
					e.preventDefault();
					self.loadTemplateFromDropdown();
				});
			}

			// Manage templates button
			if (this.$manageTemplatesBtn.length) {
				this.$manageTemplatesBtn.on('click', function(e) {
					e.preventDefault();
					self.showManageTemplatesModal();
				});
			}

			// Close modal
			if (this.$templatesClose.length) {
				this.$templatesClose.on('click', function(e) {
					e.preventDefault();
					self.closeTemplatesModal();
				});
			}

			// Close modal on overlay click
			$(document).on('click', '.wexport-templates-overlay', function() {
				self.closeTemplatesModal();
			});

			// Template actions (delete, duplicate, set default)
			$(document).on('click', '.wexport-template-delete', function(e) {
				e.preventDefault();
				const templateId = $(this).data('template-id');
				self.deleteTemplate(templateId);
			});

			$(document).on('click', '.wexport-template-duplicate', function(e) {
				e.preventDefault();
				const templateId = $(this).data('template-id');
				self.duplicateTemplate(templateId);
			});

			$(document).on('click', '.wexport-template-set-default', function(e) {
				e.preventDefault();
				const templateId = $(this).data('template-id');
				self.setDefaultTemplate(templateId);
			});

			$(document).on('click', '.wexport-template-load', function(e) {
				e.preventDefault();
				const templateId = $(this).data('template-id');
				self.loadTemplate(templateId);
			});

			$(document).on('click', '.wexport-template-edit', function(e) {
				e.preventDefault();
				const templateId = $(this).data('template-id');
				self.editTemplate(templateId);
			});
		},

		/**
		 * Show save template dialog
		 */
		showSaveTemplateDialog: function() {
			const templateName = prompt(wexportData.i18n.enterTemplateName || 'Enter template name:');
			
			if (templateName === null) {
				return;
			}

			if (templateName.trim() === '') {
				WExportNotifications.warning(wexportData.i18n.templateNameRequired || 'Template name is required.');
				return;
			}

			this.saveTemplate(templateName);
		},

		/**
		 * Save template via AJAX
		 */
		saveTemplate: function(templateName, templateId) {
			const self = this;
			const formData = this.getFormData();

			// Use FormData API to properly handle nested arrays
			const ajaxFormData = new FormData();
			ajaxFormData.append('action', 'wexport_save_template');
			ajaxFormData.append('nonce', wexportData.nonce);
			ajaxFormData.append('template_name', templateName);
			ajaxFormData.append('template_id', templateId || '');
			
			// Add simple fields
			ajaxFormData.append('date_from', formData.date_from);
			ajaxFormData.append('date_to', formData.date_to);
			ajaxFormData.append('export_format', formData.export_format);
			ajaxFormData.append('delimiter', formData.delimiter);
			ajaxFormData.append('export_mode', formData.export_mode);
			ajaxFormData.append('multi_term_separator', formData.multi_term_separator);
			ajaxFormData.append('include_headers', formData.include_headers);

			// Add arrays
			formData.order_status.forEach((status) => {
				ajaxFormData.append('order_status[]', status);
			});

			formData.columns.forEach((column) => {
				ajaxFormData.append('columns[]', column);
			});

			// Add custom codes array properly
			formData.custom_codes.forEach((code, index) => {
				ajaxFormData.append('custom_codes[' + index + '][column_name]', code.column_name);
				ajaxFormData.append('custom_codes[' + index + '][type]', code.type);
				ajaxFormData.append('custom_codes[' + index + '][source]', code.source);
			});

			$.ajax({
				url: wexportData.ajaxUrl,
				type: 'POST',
				data: ajaxFormData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						WExportNotifications.success(response.data.message);
						self.loadTemplatesList();
					} else {
						WExportNotifications.error(response.data.message || 'Error saving template');
					}
				},
				error: function() {
					WExportNotifications.error('Error communicating with server');
				}
			});
		},

			/**
		 * Load templates list
		 */
		loadTemplatesList: function() {
			const self = this;

			const data = {
				action: 'wexport_get_templates',
				nonce: wexportData.nonce
			};

			$.post(wexportData.ajaxUrl, data, function(response) {
				if (response.success) {
					self.renderTemplatesList(response.data.templates);
					self.renderTemplatesDropdown(response.data.templates);
					self.updateCurrentTemplateDisplay();
				}
			}).fail(function() {
				console.error('Error loading templates');
			});
		},

		/**
		 * Render templates list for manage modal
		 */
		renderTemplatesList: function(templates) {
			if (!this.$templatesList.length) {
				return;
			}

			if (templates.length === 0) {
				this.$templatesList.html(
					'<p>' + (wexportData.i18n.noTemplates || 'No templates yet.') + '</p>'
				);
				return;
			}

			let html = '<table class="widefat"><thead><tr>';
			html += '<th>' + (wexportData.i18n.templateName || 'Name') + '</th>';
			html += '<th>' + (wexportData.i18n.created || 'Created') + '</th>';
			html += '<th>' + (wexportData.i18n.updated || 'Updated') + '</th>';
			html += '<th>' + (wexportData.i18n.actions || 'Actions') + '</th>';
			html += '</tr></thead><tbody>';

			templates.forEach(function(template) {
				const createdDate = new Date(template.created_at).toLocaleDateString();
				const updatedDate = new Date(template.updated_at).toLocaleDateString();
				const defaultBadge = template.is_default ? 
					' <span class="wexport-default-badge">' + (wexportData.i18n.default || 'Default') + '</span>' : '';
				const isCurrentTemplate = WExportTemplates.currentTemplateId === template.id;
				const currentBadge = isCurrentTemplate ?
					' <span class="wexport-current-badge" style="background-color: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Loaded</span>' : '';
				const rowClass = isCurrentTemplate ? ' style="background-color: #f0f6fb;"' : '';

				html += '<tr' + rowClass + '>';
				html += '<td><strong>' + WExportTemplates.escapeHtml(template.name) + '</strong>' + defaultBadge + currentBadge + '</td>';
				html += '<td>' + createdDate + '</td>';
				html += '<td>' + updatedDate + '</td>';
				html += '<td>';
				html += '<button class="button button-small wexport-template-load" data-template-id="' + template.id + '">';
				html += wexportData.i18n.load || 'Load';
				html += '</button> ';
				html += '<button class="button button-small wexport-template-edit" data-template-id="' + template.id + '">';
				html += wexportData.i18n.edit || 'Edit';
				html += '</button> ';
				html += '<button class="button button-small wexport-template-duplicate" data-template-id="' + template.id + '">';
				html += wexportData.i18n.duplicate || 'Duplicate';
				html += '</button> ';
				html += '<button class="button button-small wexport-template-set-default" data-template-id="' + template.id + '">';
				html += wexportData.i18n.setDefault || 'Set Default';
				html += '</button> ';
				html += '<button class="button button-small button-link-delete wexport-template-delete" data-template-id="' + template.id + '">';
				html += wexportData.i18n.delete || 'Delete';
				html += '</button>';
				html += '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			this.$templatesList.html(html);
		},

		/**
		 * Render templates dropdown
		 */
		renderTemplatesDropdown: function(templates) {
			if (!this.$templatesDropdown.length) {
				return;
			}

			this.$templatesDropdown.empty();
			this.$templatesDropdown.append($('<option></option>').val('').text(
				wexportData.i18n.selectTemplate || 'Select a template...'
			));

			templates.forEach(function(template) {
				const $option = $('<option></option>')
					.val(template.id)
					.text(WExportTemplates.escapeHtml(template.name));
				
				if (template.is_default) {
					$option.text(template.name + ' (' + (wexportData.i18n.default || 'Default') + ')');
				}

				this.$templatesDropdown.append($option);
			}.bind(this));
		},

		/**
		 * Load template from dropdown
		 */
		loadTemplateFromDropdown: function() {
			const templateId = this.$templatesDropdown.val();

			if (!templateId) {
				WExportNotifications.warning(wexportData.i18n.selectTemplate || 'Please select a template');
				return;
			}

			this.loadTemplate(templateId);
		},

		/**
		 * Load template via AJAX
		 */
		loadTemplate: function(templateId) {
			const self = this;

			const data = {
				action: 'wexport_load_template',
				nonce: wexportData.nonce,
				template_id: templateId
			};

			$.post(wexportData.ajaxUrl, data, function(response) {
				if (response.success) {
					// Track current template
					self.currentTemplateId = templateId;
					self.currentTemplateName = response.data.template.name;
					
					self.populateFormFromConfig(response.data.template.config);
					self.updateCurrentTemplateDisplay();
					WExportNotifications.success(wexportData.i18n.templateLoaded || 'Template loaded successfully');
					self.closeTemplatesModal();
				} else {
					WExportNotifications.error(response.data.message || 'Error loading template');
				}
			}).fail(function() {
				WExportNotifications.error('Error communicating with server');
			});
		},

		/**
		 * Edit template - rename and update the currently loaded template
		 */
		editTemplate: function(templateId) {
			const self = this;

			// Find the template to get its current name
			const currentName = this.currentTemplateName;

			const newName = prompt(
				wexportData.i18n.enterTemplateName || 'Enter new template name:',
				currentName || ''
			);

			if (newName === null) {
				return; // User cancelled
			}

			if (newName.trim() === '') {
				WExportNotifications.warning(wexportData.i18n.templateNameRequired || 'Template name is required.');
				return;
			}

			// Save the current form configuration with the new template name
			this.saveTemplate(newName, templateId);
		},

		/**
		 * Update current template display area
		 */
		updateCurrentTemplateDisplay: function() {
			if (!this.$currentTemplateDisplay || !this.$currentTemplateDisplay.length) {
				return;
			}

			if (this.currentTemplateId) {
				const displayText = 'Currently Loaded: ' + this.escapeHtml(this.currentTemplateName);
				this.$currentTemplateDisplay.html(
					'<p class="wexport-current-template-info" style="padding: 10px; background-color: #f0f6fb; border-left: 4px solid #0073aa; margin-bottom: 15px;">' +
					'<strong style="color: #0073aa;">✓ ' + displayText + '</strong>' +
					'</p>'
				);
			} else {
				this.$currentTemplateDisplay.html('');
			}
		},

		/**
		 * Delete template via AJAX
		 */
		deleteTemplate: function(templateId) {
			if (!confirm(wexportData.i18n.confirmDelete || 'Are you sure you want to delete this template?')) {
				return;
			}

			const self = this;

			const data = {
				action: 'wexport_delete_template',
				nonce: wexportData.nonce,
				template_id: templateId
			};

			$.post(wexportData.ajaxUrl, data, function(response) {
				if (response.success) {
					WExportNotifications.success(response.data.message);
					self.loadTemplatesList();
				} else {
					WExportNotifications.error(response.data.message || 'Error deleting template');
				}
			}).fail(function() {
				WExportNotifications.error('Error communicating with server');
			});
		},

		/**
		 * Duplicate template via AJAX
		 */
		duplicateTemplate: function(templateId) {
			const self = this;

			const data = {
				action: 'wexport_duplicate_template',
				nonce: wexportData.nonce,
				template_id: templateId
			};

			$.post(wexportData.ajaxUrl, data, function(response) {
				if (response.success) {
					WExportNotifications.success(response.data.message);
					self.loadTemplatesList();
				} else {
					WExportNotifications.error(response.data.message || 'Error duplicating template');
				}
			}).fail(function() {
				WExportNotifications.error('Error communicating with server');
			});
		},

		/**
		 * Set default template via AJAX
		 */
		setDefaultTemplate: function(templateId) {
			const self = this;

			const data = {
				action: 'wexport_set_default_template',
				nonce: wexportData.nonce,
				template_id: templateId
			};

			$.post(wexportData.ajaxUrl, data, function(response) {
				if (response.success) {
					WExportNotifications.success(response.data.message);
					self.loadTemplatesList();
				} else {
					WExportNotifications.error(response.data.message || 'Error setting default template');
				}
			}).fail(function() {
				WExportNotifications.error('Error communicating with server');
			});
		},

		/**
		 * Get current form data
		 */
		getFormData: function() {
			const formData = {
				date_from: $('#date_from').val(),
				date_to: $('#date_to').val(),
				order_status: $('#order_status').val() || [],
				export_format: $('#export_format').val(),
				delimiter: $('#delimiter').val(),
				export_mode: $('#export_mode').val(),
				multi_term_separator: $('#multi_term_separator').val(),
				include_headers: $('input[name="include_headers"]').is(':checked') ? 1 : 0,
				columns: [],
				custom_codes: []
			};

			// Get selected columns
			$('input[name="columns[]"]:checked').each(function() {
				formData.columns.push($(this).val());
			});

			// Get custom codes - only include rows with column_name filled
			$('.custom-code-row').each(function() {
				const $row = $(this);
				const columnName = $row.find('.custom-code-name').val();
				const type = $row.find('.custom-code-type').val();
				
				// Only include rows that have a column name
				if (columnName && columnName.trim()) {
					// Get source value from the visible input/select based on type
					let sourceValue = '';
					if (type === 'taxonomy') {
						sourceValue = $row.find('.custom-code-source-select').val();
					} else {
						sourceValue = $row.find('.custom-code-source-text').val();
					}
					
					formData.custom_codes.push({
						column_name: columnName,
						type: type,
						source: sourceValue || ''
					});
				}
			});

			return formData;
		},

		/**
		 * Populate form from template config
		 */
		populateFormFromConfig: function(config) {
			// Set date range
			if (config.date_from) {
				$('#date_from').val(config.date_from);
			}
			if (config.date_to) {
				$('#date_to').val(config.date_to);
			}

			// Set order status
			if (config.order_status) {
				$('#order_status').val(config.order_status).change();
			}

			// Set format and export options
			if (config.format) {
				$('#export_format').val(config.format).change();
			}
			if (config.delimiter) {
				$('#delimiter').val(config.delimiter).change();
			}
			if (config.export_mode) {
				$('#export_mode').val(config.export_mode).change();
			}
			if (config.multi_term_separator) {
				$('#multi_term_separator').val(config.multi_term_separator);
			}

			// Set include headers
			if (config.include_headers) {
				$('input[name="include_headers"]').prop('checked', true);
			} else {
				$('input[name="include_headers"]').prop('checked', false);
			}

			// Set columns
			if (config.columns && Array.isArray(config.columns)) {
				$('input[name="columns[]"]').prop('checked', false);
				config.columns.forEach(function(column) {
					$('input[name="columns[]"][value="' + column + '"]').prop('checked', true);
				});
			}

			// Set custom codes
			if (config.custom_code_mappings && Object.keys(config.custom_code_mappings).length > 0) {
				const $tbody = $('#custom-codes-tbody');
				
				// Clear existing custom code rows (but keep the template structure)
				$tbody.find('.custom-code-row').remove();

				Object.keys(config.custom_code_mappings).forEach(function(columnName) {
					const mapping = config.custom_code_mappings[columnName];
					const $row = WExportTemplates.createCustomCodeRow(columnName, mapping.type, mapping.source);
					$tbody.append($row);
				});
			}
		},

		/**
		 * Create custom code row HTML
		 */
		createCustomCodeRow: function(columnName, type, source) {
			let sourceHtml = '';

			if (type === 'taxonomy') {
				sourceHtml = '<select name="custom_codes[][source]" class="custom-code-source custom-code-source-select" style="display: block;">';
				sourceHtml += '<option value="">' + (wexportData.i18n.selectTaxonomy || '-- Select Taxonomy --') + '</option>';
				
				if (typeof wexportTaxonomies !== 'undefined' && wexportTaxonomies) {
					Object.keys(wexportTaxonomies).forEach(function(taxName) {
						const selected = taxName === source ? ' selected' : '';
						sourceHtml += '<option value="' + taxName + '"' + selected + '>' + wexportTaxonomies[taxName] + ' (' + taxName + ')</option>';
					});
				}
				
				sourceHtml += '</select>';
			} else {
				sourceHtml = '<input type="text" name="custom_codes[][source]" value="' + WExportTemplates.escapeHtml(source || '') + '" class="custom-code-source custom-code-source-text" placeholder="e.g., _metal_type or _product_code" style="display: block;" />';
			}

			const html = '<tr class="custom-code-row">' +
				'<td><input type="text" name="custom_codes[][column_name]" value="' + WExportTemplates.escapeHtml(columnName || '') + '" class="custom-code-name" /></td>' +
				'<td><select name="custom_codes[][type]" class="custom-code-type"><option value="meta"' + (type === 'meta' ? ' selected' : '') + '>' + (wexportData.i18n.productMeta || 'Product Meta') + '</option><option value="taxonomy"' + (type === 'taxonomy' ? ' selected' : '') + '>' + (wexportData.i18n.taxonomy || 'Taxonomy') + '</option></select></td>' +
				'<td>' + sourceHtml + '</td>' +
				'<td><button type="button" class="button button-small remove-code">' + (wexportData.i18n.remove || 'Remove') + '</button></td>' +
				'</tr>';

			return $(html);
		},

		/**
		 * Show manage templates modal
		 */
		showManageTemplatesModal: function() {
			if (this.$templatesModal.length) {
				this.$templatesModal.show();
			}
		},

		/**
		 * Close templates modal
		 */
		closeTemplatesModal: function() {
			if (this.$templatesModal.length) {
				this.$templatesModal.hide();
			}
		},

		/**
		 * Load default template if one is set
		 */
		loadDefaultTemplateIfSet: function() {
			const self = this;

			// Get the default template ID from page data
			const defaultTemplateId = wexportData.defaultTemplateId || null;

			if (!defaultTemplateId) {
				return; // No default template set
			}

			// Load the default template after a short delay to ensure DOM is ready
			setTimeout(function() {
				self.loadTemplate(defaultTemplateId);
			}, 500);
		},

		/**
		 * Escape HTML
		 */
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) {
				return map[m];
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WExportTemplates.init();
	});

	// Expose globally for use in other scripts
	window.WExportTemplates = WExportTemplates;

})(jQuery);
