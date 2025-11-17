/**
 * WExport Admin UI JavaScript
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin UI
	 */
	$(function() {
		initCustomCodes();
		initFormValidation();
		initAjaxHandlers();
		initPreviewModal();
		initColumnSelection();
		initSelect2();
	});

	/**
	 * Initialize custom codes functionality
	 */
	function initCustomCodes() {
		var tableBody = $('#custom-codes-tbody');

		// Add custom code row
		$(document).on('click', '#add-custom-code', function(e) {
			e.preventDefault();
			var newRow = getCustomCodeRow();
			tableBody.append(newRow);
			console.log('New custom code row added');
		});

		// Remove custom code row
		$(document).on('click', '.remove-code', function(e) {
			e.preventDefault();
			$(this).closest('tr').remove();
		});

		// Toggle between text input and select based on type
		$(document).on('change', '.custom-code-type', function() {
			var type = $(this).val();
			var row = $(this).closest('tr');
			var textInput = row.find('.custom-code-source-text');
			var selectInput = row.find('.custom-code-source-select');

			console.log('Custom code type changed to:', type);

			if ('meta' === type) {
				// Show text input, hide select
				textInput.css('display', 'block').focus();
				selectInput.css('display', 'none');
				textInput.attr('placeholder', 'e.g., _metal_type or _product_code');
				console.log('Switched to meta input mode');
			} else if ('taxonomy' === type) {
				// Show select, hide text input
				textInput.css('display', 'none');
				selectInput.css('display', 'block').focus();
				console.log('Switched to taxonomy select mode');
			}
		});

		// Initialize display state for existing rows on page load
		$('.custom-code-row').each(function() {
			var type = $(this).find('.custom-code-type').val();
			var textInput = $(this).find('.custom-code-source-text');
			var selectInput = $(this).find('.custom-code-source-select');

			if ('meta' === type) {
				textInput.css('display', 'block');
				selectInput.css('display', 'none');
			} else if ('taxonomy' === type) {
				textInput.css('display', 'none');
				selectInput.css('display', 'block');
			}
		});
	}

	/**
	 * Get taxonomy select HTML options from page data
	 */
	function getTaxonomySelectHTML() {
		var html = '<option value="">-- Select Taxonomy --</option>';
		
		// Try to get taxonomies from page data
		if ( typeof wexportTaxonomies !== 'undefined' && wexportTaxonomies ) {
			Object.keys( wexportTaxonomies ).forEach( function( taxName ) {
				html += '<option value="' + taxName + '">' + wexportTaxonomies[taxName] + ' (' + taxName + ')</option>';
			});
		} else {
			// Fallback if taxonomies not loaded
			console.warn( 'wexportTaxonomies not available', wexportTaxonomies );
		}
		
		console.log( 'getTaxonomySelectHTML returning:', html );
		
		return html;
	}

	/**
	 * Get HTML for a new custom code row
	 */
	function getCustomCodeRow() {
		var taxonomyOptions = getTaxonomySelectHTML();
		return `
			<tr class="custom-code-row">
				<td>
					<input 
						type="text" 
						name="custom_codes[][column_name]" 
						class="custom-code-name"
						placeholder="e.g., product_code"
					/>
				</td>
				<td>
					<select name="custom_codes[][type]" class="custom-code-type">
						<option value="meta" selected>Product Meta</option>
						<option value="taxonomy">Taxonomy</option>
					</select>
				</td>
				<td>
					<input 
						type="text" 
						name="custom_codes[][source]" 
						class="custom-code-source custom-code-source-text"
						placeholder="e.g., _metal_type or _product_code"
						style="display: block;"
					/>
					<select 
						name="custom_codes[][source]" 
						class="custom-code-source custom-code-source-select"
						style="display: none;"
					>
						${taxonomyOptions}
					</select>
				</td>
				<td>
					<button type="button" class="button button-small remove-code">
						Remove
					</button>
				</td>
			</tr>
		`;
	}

	/**
	 * Initialize form validation
	 */
	function initFormValidation() {
		// Validation only, no form submission
	}

	/**
	 * Initialize AJAX handlers for preview and export
	 */
	function initAjaxHandlers() {
		var previewBtn = $('#wexport-preview-btn');
		var exportBtn = $('#wexport-export-btn');

		previewBtn.on('click', function(e) {
			e.preventDefault();
			if (!validateForm()) {
				return false;
			}
			handleAjaxAction('preview');
		});

		exportBtn.on('click', function(e) {
			e.preventDefault();
			if (!validateForm()) {
				return false;
			}
			handleAjaxAction('export');
		});
	}

	/**
	 * Validate form before submission
	 */
	function validateForm() {
		var selectedColumns = $('input[name="columns[]"]:checked').length;

		if (0 === selectedColumns) {
			WExportNotifications.warning('Please select at least one column to export.');
			return false;
		}

		// Validate custom codes if any rows exist
		var customCodeRows = $('.custom-code-row');
		if (customCodeRows.length > 0) {
			var valid = true;
			customCodeRows.each(function() {
				var columnName = $(this).find('.custom-code-name').val().trim();
				var typeSelect = $(this).find('.custom-code-type');
				var selectedType = typeSelect.val();
				
				// Get source value from the appropriate input (either text or select) based on type
				var sourceInput;
				var source = '';
				if ('taxonomy' === selectedType) {
					sourceInput = $(this).find('.custom-code-source-select');
					source = sourceInput.val().trim();
				} else {
					sourceInput = $(this).find('.custom-code-source-text');
					source = sourceInput.val().trim();
				}

				if ((columnName && !source) || (!columnName && source)) {
					valid = false;
					WExportNotifications.warning('Please fill in both Column Name and Source for all custom code mappings.');
					return false;
				}
			});

			if (!valid) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handle AJAX action (preview or export)
	 */
	function handleAjaxAction(action) {
		var form = $('#wexport-form');
		var loading = $('#wexport-loading');
		var previewBtn = $('#wexport-preview-btn');
		var exportBtn = $('#wexport-export-btn');

		// Disable buttons and show loading
		previewBtn.prop('disabled', true);
		exportBtn.prop('disabled', true);
		loading.show();

		// Get nonce value
		var nonceValue = form.find('input[name="nonce"]').val();

		// If no order statuses selected, select all available statuses
		var statusSelect = $('#order_status');
		var selectedStatuses = statusSelect.val();
		if ( !selectedStatuses || selectedStatuses.length === 0 ) {
			statusSelect.find('option').prop('selected', true);
			statusSelect.trigger('change');
		}

		// Build form data properly handling array structures
		var formDataObj = new FormData( form[0] );
		formDataObj.append( 'action', 'wexport_' + action );
		
		// Remove the improperly serialized custom_codes and rebuild them
		// FormData doesn't handle name="custom_codes[][field]" correctly
		formDataObj.delete( 'custom_codes' );
		
		// Rebuild custom codes in FormData
		var customCodesRows = $('.custom-code-row');
		console.log('Number of custom code rows:', customCodesRows.length);
		
		var customCodeIndex = 0;
		customCodesRows.each(function(rowIndex) {
			var row = $(this);
			var columnName = row.find('.custom-code-name').val();
			var type = row.find('.custom-code-type').val();
			var source = '';
			
			// Trim and clean values
			columnName = columnName ? columnName.trim() : '';
			type = type ? type.trim() : '';
			
			// Get source based on type - only from the visible input
			if ('taxonomy' === type) {
				// For taxonomy type, get value from the select dropdown
				var selectInput = row.find('.custom-code-source-select');
				source = selectInput.val() || '';
				source = source ? source.trim() : '';
				console.log('Row ' + rowIndex + ' - Taxonomy mode. Select value:', source);
			} else {
				// For meta type, get value from the text input
				var textInput = row.find('.custom-code-source-text');
				source = textInput.val() || '';
				source = source ? source.trim() : '';
				console.log('Row ' + rowIndex + ' - Meta mode. Text input value:', source);
			}
			
			console.log('Row ' + rowIndex + ' values:', { columnName: columnName, type: type, source: source });
			
			// Only add to FormData if all fields have values
			if ( columnName && type && source ) {
				formDataObj.append( 'custom_codes[' + customCodeIndex + '][column_name]', columnName );
				formDataObj.append( 'custom_codes[' + customCodeIndex + '][type]', type );
				formDataObj.append( 'custom_codes[' + customCodeIndex + '][source]', source );
				console.log('Row ' + rowIndex + ' added as index ' + customCodeIndex);
				customCodeIndex++;
			} else {
				console.log('Row ' + rowIndex + ' skipped - incomplete mapping. Column:', columnName, 'Type:', type, 'Source:', source);
			}
		});
		
		// Debug: Log what we're sending
		console.log('FormData contents:');
		for ( var pair of formDataObj.entries() ) {
			console.log( pair[0] + ' = ' + pair[1] );
		}

		// Make AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formDataObj,
			contentType: false,
			processData: false,
			success: function(response) {
				if (response.success) {
					if ('preview' === action) {
						showPreviewModal(response.data.preview);
					} else if ('export' === action) {
						// Trigger download
						window.location.href = response.data.download_url;
						showNotice('Export completed successfully!', 'success');
					}
				} else {
					showNotice(response.data.message || 'An error occurred.', 'error');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', xhr, status, error);
				showNotice('Request failed: ' + error, 'error');
			},
			complete: function() {
				previewBtn.prop('disabled', false);
				exportBtn.prop('disabled', false);
				loading.hide();
			}
		});
	}

	/**
	 * Show preview modal
	 */
	function showPreviewModal(previewData) {
		var modal = $('#wexport-preview-modal');
		var content = $('#wexport-preview-content');

		// Parse CSV and display as table
		var table = csvToTable(previewData);
		content.html(table);

		modal.show();
	}

	/**
	 * Convert CSV string to HTML table
	 */
	function csvToTable(csvData) {
		var rows = csvData.trim().split('\n');
		var table = '<table class="wexport-preview-table"><tbody>';

		rows.forEach(function(row, index) {
			var cells = parseCSVRow(row);
			table += '<tr>';
			
			// First row is header
			if (index === 0) {
				cells.forEach(function(cell) {
					table += '<th>' + escapeHtml(cell) + '</th>';
				});
			} else {
				cells.forEach(function(cell) {
					table += '<td>' + escapeHtml(cell) + '</td>';
				});
			}
			
			table += '</tr>';
		});

		table += '</tbody></table>';
		return table;
	}

	/**
	 * Parse a CSV row handling quoted cells
	 */
	function parseCSVRow(row) {
		var cells = [];
		var current = '';
		var insideQuotes = false;

		for (var i = 0; i < row.length; i++) {
			var char = row[i];
			var nextChar = row[i + 1];

			if (char === '"') {
				if (insideQuotes && nextChar === '"') {
					// Escaped quote
					current += '"';
					i++;
				} else {
					// Toggle quote state
					insideQuotes = !insideQuotes;
				}
			} else if (char === ',' && !insideQuotes) {
				// End of cell
				cells.push(current);
				current = '';
			} else {
				current += char;
			}
		}

		// Add last cell
		cells.push(current);

		return cells;
	}

	/**
	 * Initialize preview modal close button
	 */
	function initPreviewModal() {
		var modal = $('#wexport-preview-modal');
		var closeBtn = $('#wexport-preview-close');
		var overlay = $('.wexport-preview-overlay');

		closeBtn.on('click', function() {
			modal.hide();
		});

		overlay.on('click', function() {
			modal.hide();
		});

		// Close on Escape key
		$(document).on('keydown', function(e) {
			if (27 === e.keyCode && modal.is(':visible')) {
				modal.hide();
			}
		});
	}

	/**
	 * Show notification
	 */
	function showNotice(message, type) {
		var noticeClass = 'notice notice-' + type + ' is-dismissible';
		var notice = '<div class="' + noticeClass + '"><p>' + escapeHtml(message) + '</p></div>';
		$('.wrap').prepend(notice);

		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$('.notice').fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

	/**
	 * Show preview of export data
	 */
	function showPreview(data) {
		var modal = $('<div class="wexport-preview-modal"></div>');
		var content = $('<div class="wexport-preview-content"></div>');

		content.html('<h3>Export Preview</h3><pre>' + escapeHtml(data) + '</pre>');
		modal.append(content);

		$('body').append(modal);

		// Close on click outside
		modal.on('click', function() {
			modal.remove();
		});

		content.on('click', function(e) {
			e.stopPropagation();
		});
	}

	/**
	 * Initialize column selection functionality
	 */
	function initColumnSelection() {
		var selectAllBtn = $('#wexport-select-all-columns');
		var deselectAllBtn = $('#wexport-deselect-all-columns');
		var groupSelectAllCheckboxes = $('.wexport-group-select-all');
		var columnCheckboxes = $('.wexport-column-checkbox');

		// Select All button
		selectAllBtn.on('click', function(e) {
			e.preventDefault();
			columnCheckboxes.prop('checked', true);
			groupSelectAllCheckboxes.prop('checked', true);
		});

		// Deselect All button
		deselectAllBtn.on('click', function(e) {
			e.preventDefault();
			columnCheckboxes.prop('checked', false);
			groupSelectAllCheckboxes.prop('checked', false);
		});

		// Group select/deselect
		groupSelectAllCheckboxes.on('change', function() {
			var groupName = $(this).data('group');
			var isChecked = $(this).is(':checked');
			columnCheckboxes.filter('[data-group="' + groupName + '"]').prop('checked', isChecked);
		});

		// Update group checkbox when individual items change
		columnCheckboxes.on('change', function() {
			var groupName = $(this).data('group');
			var groupCheckbox = $('.wexport-group-select-all[data-group="' + groupName + '"]');
			var groupItems = columnCheckboxes.filter('[data-group="' + groupName + '"]');
			var checkedCount = groupItems.filter(':checked').length;

			groupCheckbox.prop('checked', checkedCount === groupItems.length);
		});

		// Initialize group checkboxes on page load
		groupSelectAllCheckboxes.each(function() {
			var groupName = $(this).data('group');
			var groupItems = columnCheckboxes.filter('[data-group="' + groupName + '"]');
			var checkedCount = groupItems.filter(':checked').length;
			$(this).prop('checked', checkedCount === groupItems.length && checkedCount > 0);
		});
	}

	/**
	 * Initialize Select2 for order status dropdown
	 */
	function initSelect2() {
		var select = $('#order_status');
		if (select.length && window.jQuery.fn.select2) {
			select.select2({
				allowClear: true,
				placeholder: 'Select order statuses...',
				width: '100%',
				closeOnSelect: false
			});
		}
	}

	/**
	 * Escape HTML entities
	 */
	function escapeHtml(text) {
		var map = {
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

})(jQuery);
