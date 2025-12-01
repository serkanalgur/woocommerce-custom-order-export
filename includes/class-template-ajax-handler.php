<?php
/**
 * Template AJAX handler class.
 *
 * @package WExport
 */

namespace WExport;

/**
 * Handles AJAX requests for template management.
 */
class Template_Ajax_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// AJAX handlers are registered in woocommerce-custom-order-export.php
	}

	/**
	 * Handle save template AJAX request.
	 */
	public function handle_save_template_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		// Get template name
		$template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : null;

		if ( empty( $template_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Template name is required.', 'wexport' ) ) );
		}

		// Build config from AJAX data
		$config = $this->build_config_from_ajax();

		// Save template
		$result = Template_Manager::save_template( $template_name, $config, $template_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Template saved successfully.', 'wexport' ),
			'template'   => $result,
			'is_update'  => (bool) $template_id,
		) );
	}

	/**
	 * Handle load template AJAX request.
	 */
	public function handle_load_template_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Template ID is required.', 'wexport' ) ) );
		}

		$template = Template_Manager::get_template( $template_id );

		if ( null === $template ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'wexport' ) ) );
		}

		wp_send_json_success( array(
			'template' => $template,
		) );
	}

	/**
	 * Handle get templates list AJAX request.
	 */
	public function handle_get_templates_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		$templates = Template_Manager::get_all_templates();
		$default_template_id = Template_Manager::get_default_template_id();

		// Format templates for display
		$formatted = array_map(
			function ( $template ) use ( $default_template_id ) {
				return array(
					'id'         => $template['id'],
					'name'       => $template['name'],
					'created_at' => $template['created_at'],
					'updated_at' => $template['updated_at'],
					'is_default' => $template['id'] === $default_template_id,
				);
			},
			$templates
		);

		wp_send_json_success( array(
			'templates' => $formatted,
		) );
	}

	/**
	 * Handle delete template AJAX request.
	 */
	public function handle_delete_template_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Template ID is required.', 'wexport' ) ) );
		}

		$result = Template_Manager::delete_template( $template_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Template deleted successfully.', 'wexport' ),
		) );
	}

	/**
	 * Handle duplicate template AJAX request.
	 */
	public function handle_duplicate_template_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Template ID is required.', 'wexport' ) ) );
		}

		$result = Template_Manager::duplicate_template( $template_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Template duplicated successfully.', 'wexport' ),
			'template' => $result,
		) );
	}

	/**
	 * Handle set default template AJAX request.
	 */
	public function handle_set_default_template_ajax() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wexport_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wexport' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wexport' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Template ID is required.', 'wexport' ) ) );
		}

		$result = Template_Manager::set_default_template( $template_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Default template updated.', 'wexport' ),
		) );
	}

	/**
	 * Build config from AJAX request data.
	 *
	 * @return array
	 */
	private function build_config_from_ajax() {
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$statuses = isset( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['order_status'] ) ) : array( 'wc-completed' );

		// Ensure status keys have wc- prefix.
		$statuses = array_map(
			function ( $s ) {
				return strpos( $s, 'wc-' ) === 0 ? $s : 'wc-' . $s;
			},
			$statuses
		);

		$columns = isset( $_POST['columns'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['columns'] ) ) : array();

		// Build custom code mappings.
		$code_mappings = array();
		if ( isset( $_POST['custom_codes'] ) && is_array( $_POST['custom_codes'] ) ) {
			$custom_codes = wp_unslash( (array) $_POST['custom_codes'] );

			foreach ( $custom_codes as $mapping ) {
				if ( is_array( $mapping ) ) {
					// Sanitize and trim values
					$mapping = array_map(
						function( $val ) {
							return trim( sanitize_text_field( $val ) );
						},
						$mapping
					);

					$column_name = $mapping['column_name'] ?? '';
					$type = $mapping['type'] ?? '';
					$source = $mapping['source'] ?? '';

					if ( ! empty( $column_name ) && ! empty( $type ) && ! empty( $source ) ) {
						$code_mappings[ $column_name ] = array(
							'type'   => $type,
							'source' => $source,
						);
					}
				}
			}
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
		);
	}
}
