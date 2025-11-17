<?php
/**
 * Template Manager class.
 *
 * @package WExport
 */

namespace WExport;

/**
 * Handles template management for export configurations.
 */
class Template_Manager {

	/**
	 * Option key for storing templates.
	 */
	const TEMPLATES_OPTION_KEY = 'wexport_templates';

	/**
	 * Get all templates for current user.
	 *
	 * @return array
	 */
	public static function get_all_templates() {
		$all_templates = get_option( self::TEMPLATES_OPTION_KEY, array() );

		// Filter templates for current user if multi-user
		$current_user_id = get_current_user_id();
		$user_templates  = array();

		foreach ( $all_templates as $template ) {
			if ( isset( $template['user_id'] ) && $template['user_id'] === $current_user_id ) {
				$user_templates[] = $template;
			}
		}

		return $user_templates;
	}

	/**
	 * Get template by ID.
	 *
	 * @param string $template_id Template ID.
	 * @return array|null
	 */
	public static function get_template( $template_id ) {
		$all_templates = get_option( self::TEMPLATES_OPTION_KEY, array() );
		$current_user_id = get_current_user_id();

		foreach ( $all_templates as $template ) {
			if ( isset( $template['id'] ) && $template['id'] === $template_id && 
				 isset( $template['user_id'] ) && $template['user_id'] === $current_user_id ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Save or update a template.
	 *
	 * @param string $template_name Template name.
	 * @param array  $config Export configuration.
	 * @param string $template_id Optional. Template ID for updating.
	 * @return array|WP_Error Template data or error.
	 */
	public static function save_template( $template_name, $config, $template_id = null ) {
		// Validate inputs
		if ( empty( $template_name ) ) {
			return new \WP_Error( 'empty_name', __( 'Template name is required.', 'wexport' ) );
		}

		$template_name = sanitize_text_field( $template_name );

		if ( strlen( $template_name ) > 100 ) {
			return new \WP_Error( 'name_too_long', __( 'Template name must be 100 characters or less.', 'wexport' ) );
		}

		// Get all templates
		$all_templates = get_option( self::TEMPLATES_OPTION_KEY, array() );
		if ( ! is_array( $all_templates ) ) {
			$all_templates = array();
		}

		$current_user_id = get_current_user_id();
		$now = gmdate( 'Y-m-d H:i:s' );

		// If updating existing template
		if ( $template_id ) {
			$found = false;
			foreach ( $all_templates as &$template ) {
				if ( isset( $template['id'] ) && $template['id'] === $template_id && 
					 isset( $template['user_id'] ) && $template['user_id'] === $current_user_id ) {
					$template['name']       = $template_name;
					$template['config']     = $config;
					$template['updated_at'] = $now;
					$found = true;
					break;
				}
			}
			unset( $template );

			if ( ! $found ) {
				return new \WP_Error( 'template_not_found', __( 'Template not found.', 'wexport' ) );
			}
		} else {
			// Create new template
			$template_id = 'wexport_' . uniqid() . '_' . wp_generate_password( 8, false );

			$all_templates[] = array(
				'id'         => $template_id,
				'name'       => $template_name,
				'config'     => $config,
				'user_id'    => $current_user_id,
				'created_at' => $now,
				'updated_at' => $now,
			);
		}

		// Save updated templates
		if ( update_option( self::TEMPLATES_OPTION_KEY, $all_templates ) ) {
			return array(
				'id'   => $template_id,
				'name' => $template_name,
			);
		}

		return new \WP_Error( 'save_failed', __( 'Failed to save template.', 'wexport' ) );
	}

	/**
	 * Delete a template.
	 *
	 * @param string $template_id Template ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_template( $template_id ) {
		$all_templates = get_option( self::TEMPLATES_OPTION_KEY, array() );
		$current_user_id = get_current_user_id();

		$found = false;
		foreach ( $all_templates as $key => $template ) {
			if ( isset( $template['id'] ) && $template['id'] === $template_id && 
				 isset( $template['user_id'] ) && $template['user_id'] === $current_user_id ) {
				unset( $all_templates[ $key ] );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'wexport' ) );
		}

		// Reindex array
		$all_templates = array_values( $all_templates );

		if ( update_option( self::TEMPLATES_OPTION_KEY, $all_templates ) ) {
			return true;
		}

		return new \WP_Error( 'delete_failed', __( 'Failed to delete template.', 'wexport' ) );
	}

	/**
	 * Duplicate a template.
	 *
	 * @param string $template_id Template ID to duplicate.
	 * @param string $new_name Optional. New template name.
	 * @return array|WP_Error New template data or error.
	 */
	public static function duplicate_template( $template_id, $new_name = null ) {
		$template = self::get_template( $template_id );

		if ( null === $template ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'wexport' ) );
		}

		$name = $new_name ? $new_name : $template['name'] . ' ' . __( '(Copy)', 'wexport' );

		return self::save_template( $name, $template['config'] );
	}

	/**
	 * Rename a template.
	 *
	 * @param string $template_id Template ID.
	 * @param string $new_name New name.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function rename_template( $template_id, $new_name ) {
		$template = self::get_template( $template_id );

		if ( null === $template ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'wexport' ) );
		}

		return self::save_template( $new_name, $template['config'], $template_id );
	}

	/**
	 * Set a template as default.
	 *
	 * @param string $template_id Template ID.
	 * @return bool|WP_Error
	 */
	public static function set_default_template( $template_id ) {
		$template = self::get_template( $template_id );

		if ( null === $template ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'wexport' ) );
		}

		$current_user_id = get_current_user_id();

		if ( update_option( 'wexport_default_template_' . $current_user_id, $template_id ) ) {
			return true;
		}

		return new \WP_Error( 'save_failed', __( 'Failed to set default template.', 'wexport' ) );
	}

	/**
	 * Get default template ID for current user.
	 *
	 * @return string|null
	 */
	public static function get_default_template_id() {
		$current_user_id = get_current_user_id();
		return get_option( 'wexport_default_template_' . $current_user_id, null );
	}

	/**
	 * Get default template config.
	 *
	 * @return array|null
	 */
	public static function get_default_template_config() {
		$template_id = self::get_default_template_id();

		if ( null === $template_id ) {
			return null;
		}

		$template = self::get_template( $template_id );

		return $template ? $template['config'] : null;
	}

	/**
	 * Export templates as JSON.
	 *
	 * @param array $template_ids Optional. Specific template IDs to export.
	 * @return string|WP_Error JSON string or error.
	 */
	public static function export_templates( $template_ids = null ) {
		$templates = self::get_all_templates();

		if ( $template_ids ) {
			$templates = array_filter(
				$templates,
				function ( $template ) use ( $template_ids ) {
					return in_array( $template['id'], $template_ids, true );
				}
			);
		}

		// Remove user_id from export for portability
		$export_data = array_map(
			function ( $template ) {
				$template_copy = $template;
				unset( $template_copy['user_id'] );
				return $template_copy;
			},
			$templates
		);

		$json = wp_json_encode( $export_data );

		if ( false === $json ) {
			return new \WP_Error( 'json_encode_failed', __( 'Failed to encode templates.', 'wexport' ) );
		}

		return $json;
	}

	/**
	 * Import templates from JSON.
	 *
	 * @param string $json JSON string.
	 * @return array|WP_Error Array of imported template IDs or error.
	 */
	public static function import_templates( $json ) {
		$data = json_decode( $json, true );

		if ( null === $data || ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'wexport' ) );
		}

		$imported_ids = array();

		foreach ( $data as $template_data ) {
			if ( isset( $template_data['name'] ) && isset( $template_data['config'] ) ) {
				$result = self::save_template( $template_data['name'], $template_data['config'] );

				if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
					$imported_ids[] = $result['id'];
				}
			}
		}

		return $imported_ids;
	}
}
