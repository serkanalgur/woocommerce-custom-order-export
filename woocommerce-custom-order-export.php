<?php
/**
 * Plugin Name: WooCommerce Custom Order Export
 * Plugin URI: https://serkanalgur.com.tr/
 * Description: Export WooCommerce orders to CSV/XLSX with product-level custom codes and metadata.
 * Version: 1.7.2
 * Author: Serkan Algur
 * Author URI: https://serkanalgur.com.tr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wexport
 * Domain Path: /languages
 * Requires: WordPress 6.0+
 * Requires WooCommerce: 6.0+
 * Requires PHP: 7.4
 *
 * @package WExport
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WEXPORT_VERSION', '1.7.2' );
define( 'WEXPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEXPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WEXPORT_INCLUDES_DIR', WEXPORT_PLUGIN_DIR . 'includes/' );
define( 'WEXPORT_ADMIN_DIR', WEXPORT_PLUGIN_DIR . 'admin/' );
define( 'WEXPORT_PLUGIN_FILE', __FILE__ );

/**
 * WExport class autoloader.
 *
 * @param string $class_name Class name to load.
 */
function wexport_class_autoloader( $class_name ) {
	// Only autoload WExport classes.
	if ( 0 !== strpos( $class_name, 'WExport' ) ) {
		return;
	}

	// Remove WExport namespace prefix.
	$class_name = str_replace( 'WExport\\', '', $class_name );

	// Convert class name to file name format (underscores to hyphens, lowercase).
	$file_name = strtolower( str_replace( '_', '-', $class_name ) );

	// Handle nested namespaces (e.g., Admin\Admin_Page -> admin/class-admin-page.php).
	$file_name = str_replace( '\\', DIRECTORY_SEPARATOR, $file_name );

	// Build the file path.
	if ( strpos( $file_name, 'admin' ) === 0 ) {
		$dir_file = WEXPORT_ADMIN_DIR . 'class-' . substr( $file_name, strlen( 'admin' . DIRECTORY_SEPARATOR ) ) . '.php';
	} else {
		$dir_file = WEXPORT_INCLUDES_DIR . 'class-' . $file_name . '.php';
	}

	// Load the file if it exists.
	if ( file_exists( $dir_file ) ) {
		require_once $dir_file;
	}
}

/**
 * Handle preview AJAX request.
 */
function wexport_handle_preview_ajax() {
	if ( class_exists( 'WExport\Ajax_Handler' ) ) {
		$handler = new \WExport\Ajax_Handler();
		$handler->handle_preview_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Ajax handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_preview', 'wexport_handle_preview_ajax' );

/**
 * Handle export AJAX request.
 */
function wexport_handle_export_ajax() {
	if ( class_exists( 'WExport\Ajax_Handler' ) ) {
		$handler = new \WExport\Ajax_Handler();
		$handler->handle_export_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Ajax handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_export', 'wexport_handle_export_ajax' );

/**
 * Handle download AJAX request.
 */
function wexport_handle_download_ajax() {
	if ( class_exists( 'WExport\Ajax_Handler' ) ) {
		$handler = new \WExport\Ajax_Handler();
		$handler->handle_download_ajax();
	} else {
		wp_die( esc_html__( 'Ajax handler class not found.', 'wexport' ) );
	}
}
add_action( 'wp_ajax_nopriv_wexport_download', 'wexport_handle_download_ajax' );
add_action( 'wp_ajax_wexport_download', 'wexport_handle_download_ajax' );

/**
 * Handle save template AJAX request.
 */
function wexport_handle_save_template_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_save_template_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_save_template', 'wexport_handle_save_template_ajax' );

/**
 * Handle load template AJAX request.
 */
function wexport_handle_load_template_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_load_template_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_load_template', 'wexport_handle_load_template_ajax' );

/**
 * Handle get templates AJAX request.
 */
function wexport_handle_get_templates_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_get_templates_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_get_templates', 'wexport_handle_get_templates_ajax' );

/**
 * Handle delete template AJAX request.
 */
function wexport_handle_delete_template_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_delete_template_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_delete_template', 'wexport_handle_delete_template_ajax' );

/**
 * Handle duplicate template AJAX request.
 */
function wexport_handle_duplicate_template_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_duplicate_template_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_duplicate_template', 'wexport_handle_duplicate_template_ajax' );

/**
 * Handle set default template AJAX request.
 */
function wexport_handle_set_default_template_ajax() {
	if ( class_exists( 'WExport\Template_Ajax_Handler' ) ) {
		$handler = new \WExport\Template_Ajax_Handler();
		$handler->handle_set_default_template_ajax();
	} else {
		wp_send_json_error( array( 'message' => 'Template handler class not found.' ) );
	}
}
add_action( 'wp_ajax_wexport_set_default_template', 'wexport_handle_set_default_template_ajax' );

/**
 * Bootstrap the plugin.
 */
function wexport_init() {
	if ( class_exists( 'WExport\WExport' ) ) {
		\WExport\WExport::get_instance();
	}
}
add_action( 'plugins_loaded', 'wexport_init' );

/**
 * Setup class aliases after classes are loaded.
 */
function wexport_setup_aliases() {
	// Export classes.
	if ( class_exists( 'WExport\Export_Logger' ) ) {
		class_alias( 'WExport\Export_Logger', 'WExport_Logger' );
	}
	if ( class_exists( 'WExport\Export_Formatter' ) ) {
		class_alias( 'WExport\Export_Formatter', 'WExport_Formatter' );
	}
	if ( class_exists( 'WExport\Export_Manager' ) ) {
		class_alias( 'WExport\Export_Manager', 'WExport_Manager' );
	}

	// Admin classes.
	if ( class_exists( 'WExport\Admin\Admin_Page' ) ) {
		class_alias( 'WExport\Admin\Admin_Page', 'WExport_Admin_Page' );
	}
}
add_action( 'plugins_loaded', 'wexport_setup_aliases', 20 );
