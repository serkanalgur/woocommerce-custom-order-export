<?php
/**
 * Tests for Export Manager
 *
 * @package WExport\Tests
 */

/**
 * Test case for Export Manager
 */
class Test_Export_Manager extends WP_UnitTestCase {

	/**
	 * Test export manager initialization
	 */
	public function test_export_manager_init() {
		$manager = new \WExport\Export_Manager();
		$this->assertIsObject( $manager );
	}

	/**
	 * Test default configuration
	 */
	public function test_default_config() {
		$manager = new \WExport\Export_Manager();
		$config  = $manager->get_config();

		$this->assertEquals( 'csv', $config['format'] );
		$this->assertEquals( ',', $config['delimiter'] );
		$this->assertEquals( 'line_item', $config['export_mode'] );
		$this->assertTrue( $config['include_headers'] );
	}

	/**
	 * Test configuration setting
	 */
	public function test_set_config() {
		$manager = new \WExport\Export_Manager();
		$manager->set_config( 'format', 'xlsx' );

		$config = $manager->get_config();
		$this->assertEquals( 'xlsx', $config['format'] );
	}

	/**
	 * Test custom configuration
	 */
	public function test_custom_config() {
		$custom_config = array(
			'format'      => 'csv',
			'delimiter'   => ';',
			'export_mode' => 'order',
		);

		$manager = new \WExport\Export_Manager( $custom_config );
		$config  = $manager->get_config();

		$this->assertEquals( 'csv', $config['format'] );
		$this->assertEquals( ';', $config['delimiter'] );
		$this->assertEquals( 'order', $config['export_mode'] );
	}

	/**
	 * Test export with sample data
	 */
	public function test_export_with_orders() {
		// This requires WooCommerce orders to be set up in test data
		// Skipping detailed implementation as it requires WooCommerce fixtures

		$manager = new \WExport\Export_Manager(
			array(
				'columns' => array(
					'order_id',
					'customer_name',
					'product_name',
					'quantity',
				),
			)
		);

		// In a real test, we'd create sample orders and verify output
		$this->assertIsObject( $manager );
	}

	/**
	 * Test formatter initialization
	 */
	public function test_formatter_init() {
		$formatter = new \WExport\Export_Formatter( 'csv' );
		$this->assertIsObject( $formatter );
	}

	/**
	 * Test CSV row formatting
	 */
	public function test_csv_row_format() {
		$formatter = new \WExport\Export_Formatter( 'csv', array( 'delimiter' => ',' ) );

		$row    = array( 'order_id', 'customer', 'amount' );
		$result = $formatter->format_csv_row( $row );

		$this->assertStringContainsString( 'order_id', $result );
		$this->assertStringContainsString( 'customer', $result );
		$this->assertStringContainsString( 'amount', $result );
	}

	/**
	 * Test CSV with special characters
	 */
	public function test_csv_escaping() {
		$formatter = new \WExport\Export_Formatter( 'csv', array( 'delimiter' => ',' ) );

		$row    = array( 'test "quoted"', 'comma, separated', 'normal' );
		$result = $formatter->format_csv_row( $row );

		// Quoted fields should be escaped
		$this->assertStringContainsString( '"test ""quoted"""', $result );
	}

	/**
	 * Test BOM addition
	 */
	public function test_bom_addition() {
		$formatter = new \WExport\Export_Formatter( 'csv', array( 'use_bom' => true ) );

		$content = 'test,data';
		$result  = $formatter->add_bom( $content );

		// BOM should be prepended
		$this->assertEquals( "\xEF\xBB\xBF" . $content, $result );
	}

	/**
	 * Test field sanitization
	 */
	public function test_field_sanitization() {
		$formatter = new \WExport\Export_Formatter();

		$this->assertEquals( 'test', $formatter->sanitize_field( 'test', 'text' ) );
		$this->assertEquals( '100.00', $formatter->sanitize_field( 100, 'number' ) );
		$this->assertEquals( 'test@example.com', $formatter->sanitize_field( 'test@example.com', 'email' ) );
	}

	/**
	 * Test that remove_variation_from_product_name option is passed to formatter
	 */
	public function test_remove_variation_option_on_formatter() {
		$formatter = new \WExport\Export_Formatter( 'csv', array( 'remove_variation_from_product_name' => true ) );
		$this->assertTrue( $formatter->get_remove_variation_from_product_name() );
	}

	/**
	 * Test get available field types
	 */
	public function test_get_field_types() {
		$types = \WExport\Export_Formatter::get_field_types();

		$this->assertArrayHasKey( 'order_fields', $types );
		$this->assertArrayHasKey( 'item_fields', $types );
		$this->assertArrayHasKey( 'product_fields', $types );
	}

	/**
	 * Test logger initialization
	 */
	public function test_logger_init() {
		$this->assertTrue( class_exists( '\WExport\Export_Logger' ) );
	}

	/**
	 * Test logger log creation
	 */
	public function test_log_export() {
		// This would require database setup
		// Basic test to ensure method exists
		$this->assertTrue( method_exists( '\WExport\Export_Logger', 'log_export' ) );
	}

	/**
	 * Test logger get logs
	 */
	public function test_get_logs() {
		$this->assertTrue( method_exists( '\WExport\Export_Logger', 'get_recent_logs' ) );
	}

	/**
	 * Test line item metadata configuration
	 */
	public function test_line_item_metadata_config() {
		$mappings = array(
			array(
				'meta_key'    => '_test_meta',
				'json_query'  => '.value',
				'column_name' => 'Test Meta',
			),
		);

		$config = array(
			'line_item_metadata_mappings' => $mappings,
		);

		$manager = new \WExport\Export_Manager( $config );
		$config  = $manager->get_config();

		$this->assertEquals( $mappings, $config['line_item_metadata_mappings'] );
	}

	/**
	 * Test line item metadata with nested JSON path
	 */
	public function test_line_item_metadata_json_path() {
		$mappings = array(
			array(
				'meta_key'    => '_product_input_fields',
				'json_query'  => '.value.0.__value',
				'column_name' => 'Custom Input Value',
			),
		);

		$config = array(
			'line_item_metadata_mappings' => $mappings,
		);

		$manager = new \WExport\Export_Manager( $config );
		$updated_config = $manager->get_config();

		$this->assertEquals( 1, count( $updated_config['line_item_metadata_mappings'] ) );
		$this->assertEquals( '.value.0.__value', $updated_config['line_item_metadata_mappings'][0]['json_query'] );
	}

	/**
	 * Test default JSON query value
	 */
	public function test_line_item_metadata_default_json_query() {
		// In the AJAX handler, the default json_query should be '.value' if empty
		$mapping = array(
			'meta_key'    => '_test_meta',
			'json_query'  => '',
			'column_name' => 'Test Column',
		);

		// Simulate what the AJAX handler does
		$json_query = $mapping['json_query'] ?? '.value';
		$this->assertEquals( '.value', $json_query );
	}

	/**
	 * Test line item metadata with multiple mappings
	 */
	public function test_multiple_line_item_metadata_mappings() {
		$mappings = array(
			array(
				'meta_key'    => '_first_meta',
				'json_query'  => '.value',
				'column_name' => 'First Meta',
			),
			array(
				'meta_key'    => '_second_meta',
				'json_query'  => '.display_value',
				'column_name' => 'Second Meta',
			),
			array(
				'meta_key'    => '_third_meta',
				'json_query'  => '.value.nested',
				'column_name' => 'Third Meta',
			),
		);

		$config = array(
			'line_item_metadata_mappings' => $mappings,
		);

		$manager = new \WExport\Export_Manager( $config );
		$updated_config = $manager->get_config();

		$this->assertEquals( 3, count( $updated_config['line_item_metadata_mappings'] ) );
		$this->assertEquals( '_first_meta', $updated_config['line_item_metadata_mappings'][0]['meta_key'] );
		$this->assertEquals( '_second_meta', $updated_config['line_item_metadata_mappings'][1]['meta_key'] );
		$this->assertEquals( '_third_meta', $updated_config['line_item_metadata_mappings'][2]['meta_key'] );
	}
}
