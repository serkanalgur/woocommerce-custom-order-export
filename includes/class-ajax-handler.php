<?php
/**
 * AJAX handler class.
 *
 * @package WExport
 */

namespace WExport;

use WExport\Admin\Admin_Page;

/**
 * Handles AJAX requests for export and preview.
 */
class Ajax_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// AJAX handlers are now registered directly in woocommerce-custom-order-export.php
		// This prevents issues with autoloader and class instantiation timing.
	}

	/**
	 * Handle preview AJAX request.
	 */
	public function handle_preview_ajax() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'wexport' ),
			) );
			die;
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wexport' ),
			) );
			die;
		}

		try {
			// Build config from request.
			$config = $this->build_config_from_ajax();

			// Limit preview to 5 rows.
			$config['batch_size'] = 5;

			// Generate preview.
			$preview_data = Admin_Page::generate_preview_data_static( $config );

			if ( is_wp_error( $preview_data ) ) {
				wp_send_json_error( array(
					'message' => $preview_data->get_error_message(),
				) );
			}

			wp_send_json_success( array(
				'preview' => $preview_data,
				'message' => __( 'Preview generated successfully.', 'wexport' ),
			) );

		} catch ( \Exception $e ) {
			error_log( 'WExport Preview Error: ' . $e->getMessage() );
			wp_send_json_error( array(
				'message' => __( 'An error occurred while generating preview.', 'wexport' ),
			) );
		}
		die;
	}

	/**
	 * Handle export AJAX request.
	 */
	public function handle_export_ajax() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'wexport' ),
			) );
			die;
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wexport' ),
			) );
			die;
		}

		try {
			// Build config from request.
			$config = $this->build_config_from_ajax();

			// Create export manager.
			$manager = new Export_Manager( $config );

			// Execute export.
			$result = $manager->export();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array(
					'message' => $result->get_error_message(),
				) );
			}

			// Generate download URL.
			$download_url = $this->get_download_url( $result );

			wp_send_json_success( array(
				'download_url' => $download_url,
				'message' => __( 'Export completed. Starting download...', 'wexport' ),
			) );

		} catch ( \Exception $e ) {
			error_log( 'WExport Export Error: ' . $e->getMessage() );
			wp_send_json_error( array(
				'message' => __( 'An error occurred while exporting.', 'wexport' ),
			) );
		}
		die;
	}

	/**
	 * Build config from AJAX request data.
	 *
	 * @return array
	 */
	private function build_config_from_ajax() {
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$statuses = isset( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['order_status'] ) ) : array( 'wc-completed' );

		// Ensure status keys have wc- prefix.
		$statuses = array_map(
			function ( $s ) {
				return strpos( $s, 'wc-' ) === 0 ? $s : 'wc-' . $s;
			},
			$statuses
		);

		$columns = isset( $_POST['columns'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['columns'] ) ) : array();

		// If no columns selected, use default columns.
		if ( empty( $columns ) ) {
			$columns = Admin_Page::get_default_export_columns_static();
		}

		// Build custom code mappings.
		$code_mappings = array();
		if ( isset( $_POST['custom_codes'] ) && is_array( $_POST['custom_codes'] ) ) {
			$custom_codes = wp_unslash( (array) $_POST['custom_codes'] );
			
			// Debug logging
			error_log( 'WExport: Received custom_codes count: ' . count( $custom_codes ) );
			error_log( 'WExport: Received custom_codes: ' . wp_json_encode( $custom_codes ) );
			
			foreach ( $custom_codes as $idx => $mapping ) {
				error_log( "WExport: Processing mapping index $idx, is_array: " . ( is_array( $mapping ) ? 'yes' : 'no' ) );
				
				if ( is_array( $mapping ) ) {
					$mapping = array_map( 'sanitize_text_field', $mapping );
					
					// Debug each mapping
					error_log( 'WExport: After sanitize - ' . wp_json_encode( $mapping ) );
					
					if ( ! empty( $mapping['column_name'] ) && ! empty( $mapping['type'] ) && ! empty( $mapping['source'] ) ) {
						$code_mappings[ $mapping['column_name'] ] = array(
							'type'   => $mapping['type'],
							'source' => $mapping['source'],
						);
						error_log( 'WExport: ✓ Added mapping - Column: ' . $mapping['column_name'] . ', Type: ' . $mapping['type'] . ', Source: ' . $mapping['source'] );
					} else {
						error_log( 'WExport: ✗ Mapping rejected - Missing required fields. Column: "' . ( $mapping['column_name'] ?? '' ) . '", Type: "' . ( $mapping['type'] ?? '' ) . '", Source: "' . ( $mapping['source'] ?? '' ) . '"' );
					}
				}
			}
			
			// Debug final mappings
			error_log( 'WExport: Final code_mappings count: ' . count( $code_mappings ) );
			error_log( 'WExport: Final code_mappings: ' . wp_json_encode( $code_mappings ) );
		}

		return array(
			'format'               => isset( $_POST['export_format'] ) ? sanitize_text_field( wp_unslash( $_POST['export_format'] ) ) : 'csv',
			'delimiter'            => isset( $_POST['delimiter'] ) ? sanitize_text_field( wp_unslash( $_POST['delimiter'] ) ) : ',',
			'export_mode'          => isset( $_POST['export_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['export_mode'] ) ) : 'line_item',
			'date_from'            => $date_from,
			'date_to'              => $date_to,
			'order_status'         => $statuses,
			'columns'              => $columns,
			'custom_code_mappings' => $code_mappings,
			'multi_term_separator' => isset( $_POST['multi_term_separator'] ) ? sanitize_text_field( wp_unslash( $_POST['multi_term_separator'] ) ) : '|',
			'include_headers'      => isset( $_POST['include_headers'] ) ? true : false,
			'remove_variation_from_product_name' => isset( $_POST['remove_variation_from_product_name'] ) ? true : false,
			'batch_size'           => 100,
		);
		// Persist setting for AJAX flows
		if ( isset( $config['remove_variation_from_product_name'] ) ) {
			update_option( 'wexport_remove_variation_from_product_name', (bool) $config['remove_variation_from_product_name'] );
		}
	}

	/**
	 * Get download URL for exported file.
	 *
	 * @param string $file_path File path.
	 * @return string Download URL.
	 */
	private function get_download_url( $file_path ) {
		// Store file path in transient for download handler.
		$download_key = 'wexport_download_' . wp_generate_password( 12, false );
		set_transient( $download_key, $file_path, HOUR_IN_SECONDS );

		return add_query_arg(
			array(
				'action' => 'wexport_download',
				'key'    => $download_key,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Handle download request.
	 */
	public function handle_download_ajax() {
		// Get download key from query parameter.
		$download_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( empty( $download_key ) ) {
			wp_die( esc_html__( 'Invalid download link.', 'wexport' ) );
		}

		// Verify user capability if logged in.
		if ( is_user_logged_in() && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wexport' ) );
		}

		// Get file path from transient.
		$file_path = get_transient( $download_key );

		if ( false === $file_path || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found or download link has expired.', 'wexport' ) );
		}

		// Delete transient after retrieval.
		delete_transient( $download_key );

		// Trigger download.
		Export_Manager::download_file( $file_path );
	}
}
