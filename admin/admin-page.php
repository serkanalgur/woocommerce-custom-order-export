<?php
/**
 * Admin page template.
 *
 * @package WExport\Admin
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use WExport\Admin\Admin_Page;

// Initialize form data variables.
$default_date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$default_date_to   = gmdate( 'Y-m-d' );

$form_data = array(
	'date_from'              => $default_date_from,
	'date_to'                => $default_date_to,
	'order_status'           => array(),
	'export_format'          => 'csv',
	'delimiter'              => ',',
	'export_mode'            => 'line_item',
	'multi_term_separator'   => ', ',
	'include_headers'        => 1,
	'columns'                => array(),
		'custom_code_mappings'   => array(),
		// Whether to remove variation details from product names in export.
		'remove_variation_from_product_name' => false,
);

// Verify nonce and populate form data from POST if available.
if ( isset( $_POST['wexport_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wexport_nonce'] ) ), 'wexport_export_nonce' ) ) {
		wp_die( esc_html__( 'Security check failed', 'wexport' ) );
	}

	// Safely extract form data.
	if ( isset( $_POST['date_from'] ) ) {
		$form_data['date_from'] = sanitize_text_field( wp_unslash( $_POST['date_from'] ) );
	}
	if ( isset( $_POST['date_to'] ) ) {
		$form_data['date_to'] = sanitize_text_field( wp_unslash( $_POST['date_to'] ) );
	}
	if ( isset( $_POST['order_status'] ) ) {
		$form_data['order_status'] = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['order_status'] ) );
	}
	if ( isset( $_POST['export_format'] ) ) {
		$form_data['export_format'] = sanitize_text_field( wp_unslash( $_POST['export_format'] ) );
	}
	if ( isset( $_POST['delimiter'] ) ) {
		$form_data['delimiter'] = sanitize_text_field( wp_unslash( $_POST['delimiter'] ) );
	}
	if ( isset( $_POST['export_mode'] ) ) {
		$form_data['export_mode'] = sanitize_text_field( wp_unslash( $_POST['export_mode'] ) );
	}
	if ( isset( $_POST['multi_term_separator'] ) ) {
		$form_data['multi_term_separator'] = sanitize_text_field( wp_unslash( $_POST['multi_term_separator'] ) );
	}
	if ( isset( $_POST['include_headers'] ) ) {
		$form_data['include_headers'] = 1;
	}
	if ( isset( $_POST['columns'] ) ) {
		$form_data['columns'] = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['columns'] ) );
	}
	if ( isset( $_POST['custom_code_mappings'] ) ) {
		$custom_mappings = wp_unslash( (array) $_POST['custom_code_mappings'] );
		foreach ( $custom_mappings as $mapping ) {
			if ( is_array( $mapping ) ) {
				$form_data['custom_code_mappings'][] = array(
					'column_name'   => isset( $mapping['column_name'] ) ? sanitize_text_field( $mapping['column_name'] ) : '',
					'source_type'   => isset( $mapping['source_type'] ) ? sanitize_text_field( $mapping['source_type'] ) : '',
					'source_name'   => isset( $mapping['source_name'] ) ? sanitize_text_field( $mapping['source_name'] ) : '',
				);
			}
		}

		if ( isset( $_POST['remove_variation_from_product_name'] ) ) {
			$form_data['remove_variation_from_product_name'] = 1;
		}
	}
}

$available_columns = Admin_Page::get_available_columns();
$order_statuses    = Admin_Page::get_order_statuses();
$available_taxonomies = Admin_Page::get_available_product_taxonomies();
$default_columns   = get_option( 'wexport_default_columns', array() );
$custom_codes      = get_option( 'wexport_custom_codes', array() );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'WooCommerce Custom Order Export', 'wexport' ); ?></h1>

	<?php settings_errors(); ?>

	<form class="wexport-form" id="wexport-form">
		<?php wp_nonce_field( 'wexport_nonce', 'nonce' ); ?>

		<div class="wexport-container">
			<!-- Templates Section -->
			<div class="wexport-section wexport-templates-section">
				<h2><?php esc_html_e( 'Templates', 'wexport' ); ?></h2>

				<!-- Current Template Display -->
				<div id="wexport-current-template"></div>

				<div class="wexport-template-controls">
					<select id="wexport-templates-dropdown" class="wexport-templates-dropdown">
						<option value=""><?php esc_html_e( 'Select a template...', 'wexport' ); ?></option>
					</select>
					
					<button 
						type="button" 
						id="wexport-load-template-btn"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Load', 'wexport' ); ?>
					</button>

					<button 
						type="button" 
						id="wexport-save-template-btn"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Save as Template', 'wexport' ); ?>
					</button>

					<button 
						type="button" 
						id="wexport-manage-templates-btn"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Manage Templates', 'wexport' ); ?>
					</button>
				</div>

				<p class="description">
					<?php esc_html_e( 'Save your current export settings as a template to quickly load them later.', 'wexport' ); ?>
				</p>
			</div>

			<!-- Filter Section -->
			<div class="wexport-section">
				<h2><?php esc_html_e( 'Filters', 'wexport' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="remove_variation_from_product_name"
									value="1"
									<?php checked( (bool) $form_data['remove_variation_from_product_name'], true ); ?>
								/>
								<?php esc_html_e( 'Remove variation details from product names', 'wexport' ); ?>
							</label>
						</td>
					</tr>

						<th scope="row">
							<label for="date_from"><?php esc_html_e( 'Date From', 'wexport' ); ?></label>
						</th>
						<td>
							<input 
								type="date" 
								id="date_from" 
								name="date_from"
								value="<?php echo esc_attr( $form_data['date_from'] ); ?>"
							/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="date_to"><?php esc_html_e( 'Date To', 'wexport' ); ?></label>
						</th>
						<td>
							<input 
								type="date" 
								id="date_to" 
								name="date_to"
								value="<?php echo esc_attr( $form_data['date_to'] ); ?>"
							/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="order_status"><?php esc_html_e( 'Order Status', 'wexport' ); ?></label>
						</th>
						<td>
							<select id="order_status" name="order_status[]" class="wexport-order-status-select" multiple style="width: 100%;">
								<?php foreach ( $order_statuses as $key => $label ) : ?>
									<option 
										value="<?php echo esc_attr( $key ); ?>"
										<?php selected( in_array( $key, $form_data['order_status'], true ) ); ?>
									>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Search and select order statuses', 'wexport' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Format Section -->
			<div class="wexport-section">
				<h2><?php esc_html_e( 'Export Format', 'wexport' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="export_format"><?php esc_html_e( 'Output Format', 'wexport' ); ?></label>
						</th>
						<td>
							<select id="export_format" name="export_format">
								<option value="csv" <?php selected( $form_data['export_format'], 'csv' ); ?>>
									<?php esc_html_e( 'CSV', 'wexport' ); ?>
								</option>
								<option value="xlsx" <?php selected( $form_data['export_format'], 'xlsx' ); ?>>
									<?php esc_html_e( 'XLSX', 'wexport' ); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="delimiter"><?php esc_html_e( 'CSV Delimiter', 'wexport' ); ?></label>
						</th>
						<td>
							<select id="delimiter" name="delimiter">
								<option value="," <?php selected( $form_data['delimiter'], ',' ); ?>>
									<?php esc_html_e( 'Comma (,)', 'wexport' ); ?>
								</option>
								<option value=";" <?php selected( $form_data['delimiter'], ';' ); ?>>
									<?php esc_html_e( 'Semicolon (;)', 'wexport' ); ?>
								</option>
								<option value="	" <?php selected( $form_data['delimiter'], '	' ); ?>>
									<?php esc_html_e( 'Tab', 'wexport' ); ?>
								</option>
								<option value="|" <?php selected( $form_data['delimiter'], '|' ); ?>>
									<?php esc_html_e( 'Pipe (|)', 'wexport' ); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="export_mode"><?php esc_html_e( 'Export Mode', 'wexport' ); ?></label>
						</th>
						<td>
							<select id="export_mode" name="export_mode">
								<option value="line_item" <?php selected( $form_data['export_mode'], 'line_item' ); ?>>
									<?php esc_html_e( 'One row per line item', 'wexport' ); ?>
								</option>
								<option value="order" <?php selected( $form_data['export_mode'], 'order' ); ?>>
									<?php esc_html_e( 'One row per order (products joined)', 'wexport' ); ?>
								</option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how to handle orders with multiple items', 'wexport' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"></th>
						<td>
							<label>
								<input 
									type="checkbox" 
									name="include_headers" 
									value="1"
									<?php checked( $form_data['include_headers'], 1 ); ?>
								/>
								<?php esc_html_e( 'Include column headers', 'wexport' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="multi_term_separator"><?php esc_html_e( 'Multi-term Separator', 'wexport' ); ?></label>
						</th>
						<td>
							<input 
								type="text" 
								id="multi_term_separator" 
								name="multi_term_separator"
								value="<?php echo esc_attr( $form_data['multi_term_separator'] ); ?>"
								size="5"
							/>
							<p class="description"><?php esc_html_e( 'Separator for multiple taxonomy terms in a column', 'wexport' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Columns Section -->
			<div class="wexport-section">
				<h2><?php esc_html_e( 'Select Columns', 'wexport' ); ?></h2>

				<div class="wexport-column-controls">
					<button type="button" id="wexport-select-all-columns" class="button">
						✓ <?php esc_html_e( 'Select All', 'wexport' ); ?>
					</button>
					<button type="button" id="wexport-deselect-all-columns" class="button">
						✕ <?php esc_html_e( 'Deselect All', 'wexport' ); ?>
					</button>
				</div>

				<div class="wexport-columns">
					<?php foreach ( $available_columns as $group_name => $columns ) : ?>
						<fieldset class="wexport-column-group">
							<legend>
								<span><?php echo esc_html( $group_name ); ?></span>
								<label class="wexport-group-select-label">
									<input 
										type="checkbox" 
										class="wexport-group-select-all" 
										data-group="<?php echo esc_attr( $group_name ); ?>"
									/>
									<small><?php esc_html_e( 'Select all', 'wexport' ); ?></small>
								</label>
							</legend>

							<?php foreach ( $columns as $column_key => $column_label ) : ?>
								<label class="wexport-column-item">
									<input 
										type="checkbox" 
										name="columns[]" 
										class="wexport-column-checkbox"
										data-group="<?php echo esc_attr( $group_name ); ?>"
										value="<?php echo esc_attr( $column_key ); ?>"
										<?php checked( in_array( $column_key, $form_data['columns'], true ) || in_array( $column_key, $default_columns, true ) ); ?>
									/>
									<?php echo esc_html( $column_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Custom Codes Section -->
			<div class="wexport-section">
				<h2><?php esc_html_e( 'Product Custom Codes Mapping', 'wexport' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Map product custom fields or taxonomy terms to export columns. Selected terms will be exported as comma-separated values.', 'wexport' ); ?>
				</p>

				<table class="wexport-custom-codes-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Column Name', 'wexport' ); ?></th>
							<th><?php esc_html_e( 'Source Type', 'wexport' ); ?></th>
							<th><?php esc_html_e( 'Meta Key / Taxonomy Name', 'wexport' ); ?></th>
							<th><?php esc_html_e( 'Action', 'wexport' ); ?></th>
						</tr>
					</thead>
					<tbody id="custom-codes-tbody">
						<?php if ( ! empty( $custom_codes ) ) : ?>
							<?php foreach ( $custom_codes as $code ) : ?>
								<?php $code_type = $code['type'] ?? 'meta'; ?>
								<tr class="custom-code-row">
									<td>
										<input 
											type="text" 
											name="custom_codes[][column_name]" 
											value="<?php echo esc_attr( $code['column_name'] ?? '' ); ?>"
											class="custom-code-name"
										/>
									</td>
									<td>
										<select name="custom_codes[][type]" class="custom-code-type">
											<option value="meta" <?php selected( $code_type, 'meta' ); ?>>
												<?php esc_html_e( 'Product Meta', 'wexport' ); ?>
											</option>
											<option value="taxonomy" <?php selected( $code_type, 'taxonomy' ); ?>>
												<?php esc_html_e( 'Taxonomy', 'wexport' ); ?>
											</option>
										</select>
									</td>
									<td>
										<?php if ( 'taxonomy' === $code_type ) : ?>
											<select 
												name="custom_codes[][source]" 
												class="custom-code-source custom-code-source-select"
												style="display: block;"
											>
												<option value=""><?php esc_html_e( '-- Select Taxonomy --', 'wexport' ); ?></option>
												<?php foreach ( $available_taxonomies as $tax_name => $tax_label ) : ?>
													<option 
														value="<?php echo esc_attr( $tax_name ); ?>"
														<?php selected( $code['source'] ?? '', $tax_name ); ?>
													>
														<?php echo esc_html( $tax_label ); ?> (<?php echo esc_html( $tax_name ); ?>)
													</option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<input 
												type="text" 
												name="custom_codes[][source]" 
												value="<?php echo esc_attr( $code['source'] ?? '' ); ?>"
												class="custom-code-source custom-code-source-text"
												placeholder="e.g., _metal_type or _product_code"
												style="display: block;"
											/>
										<?php endif; ?>
									</td>
									<td>
										<button type="button" class="button button-small remove-code">
											<?php esc_html_e( 'Remove', 'wexport' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<button 
					type="button" 
					id="add-custom-code" 
					class="button button-secondary"
					style="margin-top: 10px;"
				>
					<?php esc_html_e( 'Add Custom Code Mapping', 'wexport' ); ?>
				</button>
			</div>

			<!-- Action Buttons -->
			<div class="wexport-actions">
				<button 
					type="button" 
					id="wexport-preview-btn"
					class="button button-secondary"
				>
					<?php esc_html_e( 'Preview (5 rows)', 'wexport' ); ?>
				</button>

				<button 
					type="button" 
					id="wexport-export-btn"
					class="button button-primary"
				>
					<?php esc_html_e( 'Export', 'wexport' ); ?>
				</button>

				<span id="wexport-loading" style="display:none; margin-left: 15px; line-height: 32px;">
					<span class="spinner" style="float: none; margin: 0 5px 0 0;"></span>
					<?php esc_html_e( 'Processing...', 'wexport' ); ?>
				</span>
			</div>

			<!-- Preview Modal -->
			<div id="wexport-preview-modal" class="wexport-preview-modal" style="display:none;">
				<div class="wexport-preview-overlay"></div>
				<div class="wexport-preview-box">
					<div class="wexport-preview-header">
						<h3><?php esc_html_e( 'Export Preview', 'wexport' ); ?></h3>
						<button type="button" id="wexport-preview-close" class="button-close">&times;</button>
					</div>
					<div class="wexport-preview-content" id="wexport-preview-content"></div>
				</div>
			</div>

			<!-- Templates Modal -->
			<div id="wexport-templates-modal" class="wexport-templates-modal" style="display:none;">
				<div class="wexport-templates-overlay"></div>
				<div class="wexport-templates-box">
					<div class="wexport-templates-header">
						<h3><?php esc_html_e( 'Manage Templates', 'wexport' ); ?></h3>
						<button type="button" id="wexport-templates-close" class="button-close">&times;</button>
					</div>
					<div class="wexport-templates-content" id="wexport-templates-list"></div>
				</div>
			</div>
		</div>
	</form>

	<!-- Recent Exports -->
	<div class="wexport-section" style="margin-top: 40px;">
		<h2><?php esc_html_e( 'Recent Exports', 'wexport' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Last 10 export operations', 'wexport' ); ?></p>
		<?php
		$logs = \WExport\Export_Logger::get_recent_logs( 10 );
		if ( ! empty( $logs ) ) :
			?>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wexport' ); ?></th>
						<th><?php esc_html_e( 'Format', 'wexport' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'wexport' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wexport' ); ?></th>
						<th><?php esc_html_e( 'User', 'wexport' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log->export_date ); ?></td>
							<td><?php echo esc_html( strtoupper( $log->export_format ) ); ?></td>
							<td><?php echo esc_html( $log->rows_exported ); ?></td>
							<td>
								<?php
								$status_class = 'success' === $log->status ? 'success' : 'error';
								echo '<span class="wexport-status-' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $log->status ) ) . '</span>';
								?>
							</td>
							<td><?php echo esc_html( get_user_by( 'id', $log->user_id )->display_name ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No exports yet.', 'wexport' ); ?></p>
		<?php endif; ?>
	</div>
</div>
