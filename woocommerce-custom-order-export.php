<?php
/**
 * Plugin Name: WooCommerce Custom Order Export
 * Plugin URI: https://serkanalgur.com.tr/
 * Description: Export WooCommerce orders to CSV/XLSX with product-level custom codes and metadata.
 * Version: 1.7.0
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
define( 'WEXPORT_VERSION', '1.7.0' );
define( 'WEXPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEXPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WEXPORT_INCLUDES_DIR', WEXPORT_PLUGIN_DIR . 'includes/' );
define( 'WEXPORT_ADMIN_DIR', WEXPORT_PLUGIN_DIR . 'admin/' );

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
 * Main plugin class.
 */
class WExport {

	/**
	 * Instance holder.
	 *
	 * @var WExport
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WExport
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_autoloader();
		$this->setup_hooks();
	}

	/**
	 * Register the autoloader.
	 */
	private function register_autoloader() {
		spl_autoload_register( 'wexport_class_autoloader' );
	}

	/**
	 * Setup WordPress hooks.
	 */
	private function setup_hooks() {
		register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		add_action( 'plugins_loaded', array( $this, 'init_ajax_handler' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}



	/**
	 * Check plugin dependencies.
	 */
	public function check_dependencies() {
		if ( ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}
	}

	/**
	 * Initialize AJAX handler.
	 */
	public function init_ajax_handler() {
		if ( class_exists( 'WExport\Ajax_Handler' ) ) {
			new \WExport\Ajax_Handler();
		}
	}

	/**
	 * Display WooCommerce notice.
	 */
	public function woocommerce_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'WExport requires WooCommerce 6.0+ to be installed and activated.', 'wexport' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		if ( ! class_exists( 'WExport\Admin\Admin_Page' ) ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Custom Order Export', 'wexport' ),
			__( 'Custom Order Export', 'wexport' ),
			'manage_woocommerce',
			'wexport-export',
			array( WExport\Admin\Admin_Page::class, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_wexport-export' !== $hook_suffix ) {
			return;
		}

		// Enqueue Select2 library
		wp_enqueue_script( 'select2' );
		wp_enqueue_style(
			'select2-css',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);

		// Enqueue notification system styles
		wp_enqueue_style(
			'wexport-notifications',
			WEXPORT_PLUGIN_URL . 'admin/css/notifications.css',
			array(),
			WEXPORT_VERSION
		);

		wp_enqueue_style(
			'wexport-admin',
			WEXPORT_PLUGIN_URL . 'admin/css/admin-styles.css',
			array(),
			WEXPORT_VERSION
		);

		wp_enqueue_style(
			'wexport-templates',
			WEXPORT_PLUGIN_URL . 'admin/css/template-styles.css',
			array(),
			WEXPORT_VERSION
		);

		// Enqueue notification system script
		wp_enqueue_script(
			'wexport-notifications',
			WEXPORT_PLUGIN_URL . 'admin/js/notifications.js',
			array( 'jquery' ),
			WEXPORT_VERSION,
			true
		);

		wp_enqueue_script(
			'wexport-admin',
			WEXPORT_PLUGIN_URL . 'admin/js/admin-ui.js',
			array( 'jquery', 'select2', 'wexport-notifications' ),
			WEXPORT_VERSION,
			true
		);

		wp_enqueue_script(
			'wexport-templates',
			WEXPORT_PLUGIN_URL . 'admin/js/template-manager.js',
			array( 'jquery', 'wexport-notifications' ),
			WEXPORT_VERSION,
			true
		);

		// Get available taxonomies for custom codes UI.
		if ( class_exists( 'WExport\Admin\Admin_Page' ) ) {
			$taxonomies = \WExport\Admin\Admin_Page::get_available_product_taxonomies();
		} else {
			$taxonomies = array();
		}

		// Get default template ID for current user.
		$default_template_id = null;
		if ( class_exists( 'WExport\Template_Manager' ) ) {
			$default_template_id = \WExport\Template_Manager::get_default_template_id();
		}

		wp_localize_script(
			'wexport-admin',
			'wexportData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wexport_nonce' ),
				'defaultTemplateId' => $default_template_id,
				'i18n'    => array(
					'enterTemplateName'  => __( 'Enter template name:', 'wexport' ),
					'templateNameRequired' => __( 'Template name is required.', 'wexport' ),
					'templateLoaded'    => __( 'Template loaded successfully', 'wexport' ),
					'confirmDelete'     => __( 'Are you sure you want to delete this template?', 'wexport' ),
					'noTemplates'       => __( 'No templates yet.', 'wexport' ),
					'selectTemplate'    => __( 'Select a template...', 'wexport' ),
					'default'           => __( 'Default', 'wexport' ),
					'load'              => __( 'Load', 'wexport' ),
					'edit'              => __( 'Edit', 'wexport' ),
					'duplicate'         => __( 'Duplicate', 'wexport' ),
					'setDefault'        => __( 'Set Default', 'wexport' ),
					'delete'            => __( 'Delete', 'wexport' ),
					'remove'            => __( 'Remove', 'wexport' ),
					'created'           => __( 'Created', 'wexport' ),
					'updated'           => __( 'Updated', 'wexport' ),
					'actions'           => __( 'Actions', 'wexport' ),
					'templateName'      => __( 'Name', 'wexport' ),
					'productMeta'       => __( 'Product Meta', 'wexport' ),
					'taxonomy'          => __( 'Taxonomy', 'wexport' ),
					'selectTaxonomy'    => __( '-- Select Taxonomy --', 'wexport' ),
				),
			)
		);

		wp_localize_script(
			'wexport-admin',
			'wexportTaxonomies',
			$taxonomies
		);
	}

	/**
	 * Plugin activation hook.
	 */
	public function on_activate() {
		// Create custom DB table for logs.
		WExport\Export_Logger::create_table();
		// Set default options.
		$this->set_default_options();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public function on_deactivate() {
		// Clear scheduled cron events if needed.
		wp_clear_scheduled_hook( 'wexport_scheduled_export' );
	}

	/**
	 * Set default plugin options.
	 */
	private function set_default_options() {
		$defaults = array(
			'export_mode'          => 'line_item',
			'delimiter'            => ',',
			'multi_term_separator' => '|',
			'include_headers'      => true,
			'remove_variation_from_product_name' => false,
			'charset'              => 'UTF-8',
			'default_columns'      => $this->get_default_columns(),
			'column_mappings'      => array(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( ! get_option( "wexport_{$key}" ) ) {
				update_option( "wexport_{$key}", $value );
			}
		}
	}

	/**
	 * Get default export columns.
	 *
	 * @return array
	 */
	private function get_default_columns() {
		return array(
			'order_id',
			'order_date',
			'customer_name',
			'customer_email',
			'billing_phone',
			'shipping_address',
			'payment_method',
			'order_total',
			'product_id',
			'sku',
			'product_name',
			'quantity',
			'line_total',
			'product_code',
			'product_categories',
		);
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
	WExport::get_instance();
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
