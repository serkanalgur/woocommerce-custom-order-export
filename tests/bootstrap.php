<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the test environment for PHPUnit tests.
 *
 * @package WExport
 */

// Determine the project root directory
$project_root = dirname( dirname( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( $project_root . '/vendor/autoload.php' ) ) {
	require_once $project_root . '/vendor/autoload.php';
} else {
	echo "Error: Composer autoloader not found. Run 'composer install' first.\n";
	exit( 1 );
}

// Load WordPress testing framework
// Note: For full integration tests, WordPress test suite would be needed
// This bootstrap handles unit tests for the plugin classes

// Define test constants if not already defined
if ( ! defined( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_DIR', sys_get_temp_dir() . '/wordpress-tests-lib/' );
}

// Ensure plugin files are loaded
if ( ! file_exists( $project_root . '/woocommerce-custom-order-export.php' ) ) {
	echo "Error: Main plugin file not found.\n";
	exit( 1 );
}
