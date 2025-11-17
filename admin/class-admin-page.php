<?php
/**
 * Admin page class.
 *
 * @package WExport\Admin
 */

namespace WExport\Admin;

use WExport\Export_Manager;

/**
 * Handles the admin UI page.
 */
class Admin_Page {

	/**
	 * Render the admin page.
	 */
	public static function render_page() {
		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wexport' ) );
		}

		// Get current settings.
		$settings = self::get_current_settings();

		include WEXPORT_ADMIN_DIR . 'admin-page.php';
	}

	/**
	 * Handle export request.
	 */
	private static function handle_export() {
		$config  = self::build_export_config();
		$manager = new Export_Manager( $config );

		$result = $manager->export();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'wexport_export',
				'export_error',
				esc_html__( 'Export failed: ', 'wexport' ) . $result->get_error_message(),
				'error'
			);
		} else {
			// Trigger download.
			Export_Manager::download_file( $result );
		}
	}

	/**
	 * Handle preview request.
	 */
	private static function handle_preview() {
		$config = self::build_export_config();

		// Limit preview to 5 rows.
		$config['batch_size'] = 5;

		$manager = new Export_Manager( $config );

		// Generate preview data.
		$preview_data = self::generate_preview_data( $manager );

		if ( is_wp_error( $preview_data ) ) {
			add_settings_error(
				'wexport_preview',
				'preview_error',
				esc_html__( 'Preview failed: ', 'wexport' ) . $preview_data->get_error_message(),
				'error'
			);
		} else {
			// Store preview in session for display.
			// Display preview message with data embedded.
			add_settings_error(
				'wexport_preview',
				'preview_success',
				'<strong>' . esc_html__( 'Preview (First 5 rows):', 'wexport' ) . '</strong><pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">' . wp_kses_post( $preview_data ) . '</pre>',
				'info'
			);
		}
	}

	/**
	 * Generate preview data (first 5 rows).
	 *
	 * @param Export_Manager $manager Export manager instance.
	 * @return string|WP_Error CSV preview or error.
	 */
	private static function generate_preview_data( $manager ) {
		try {
			// Create temporary preview file.
			$upload_dir    = wp_upload_dir();
			$preview_dir   = $upload_dir['basedir'] . '/wexport/';
			$preview_file  = $preview_dir . 'preview_temp_' . gmdate( 'YmdHis' ) . '_' . uniqid() . '.csv';

			// Ensure directory exists.
			if ( ! is_dir( $preview_dir ) ) {
				wp_mkdir_p( $preview_dir );
			}

			// Open file for writing.
			$file_handle = fopen( $preview_file, 'w' );
			if ( ! $file_handle ) {
				return new \WP_Error( 'file_error', __( 'Could not create preview file.', 'wexport' ) );
			}

			// Get config for preview.
			$config = $manager->get_config();

			// Write headers.
			if ( $config['include_headers'] ) {
				$columns = array_merge(
					self::get_order_columns_for_export( $config ),
					self::get_item_columns_for_export( $config ),
					array_keys( $config['custom_code_mappings'] ?? array() )
				);
				$header_row = self::format_csv_row( $columns, $config['delimiter'] );
				fwrite( $file_handle, $header_row );
			}

			// Get a small batch of orders (5 rows).
			$orders = wc_get_orders(
				array(
					'limit'   => 5,
					'offset'  => 0,
					'status'  => $config['order_status'],
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);

			if ( empty( $orders ) ) {
				fclose( $file_handle );
				$preview_content = file_get_contents( $preview_file );
				if ( false === $preview_content ) {
					$preview_content = __( 'No orders found for preview.', 'wexport' );
				}
				unlink( $preview_file );
				return $preview_content ?: __( 'No data to preview.', 'wexport' );
			}

			// Process first 5 orders.
			foreach ( $orders as $order ) {
				$row_data = self::format_order_for_preview( $order, $config );
				if ( $config['export_mode'] === 'line_item' ) {
					// One row per line item.
					$items = $order->get_items();
					if ( ! empty( $items ) ) {
						foreach ( $items as $item ) {
							$item_data = self::format_item_for_preview( $item, $order, $config );
							$final_row = array_merge( $row_data, $item_data );
							fwrite( $file_handle, self::format_csv_row( $final_row, $config['delimiter'] ) );
						}
					} else {
						fwrite( $file_handle, self::format_csv_row( $row_data, $config['delimiter'] ) );
					}
				} else {
					// One row per order.
					$items = $order->get_items();
					$all_items = array();
					foreach ( $items as $item ) {
						$item_data = self::format_item_for_preview( $item, $order, $config );
						$all_items[] = $item_data;
					}
					if ( ! empty( $all_items ) ) {
						$merged = self::merge_items_for_preview( $all_items, $config['multi_term_separator'] );
						$final_row = array_merge( $row_data, $merged );
					} else {
						$final_row = $row_data;
					}
					fwrite( $file_handle, self::format_csv_row( $final_row, $config['delimiter'] ) );
				}
			}

			fclose( $file_handle );

			// Read preview content.
			$preview_content = file_get_contents( $preview_file );
			unlink( $preview_file );

			return $preview_content ?: __( 'Unable to generate preview.', 'wexport' );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'preview_error', $e->getMessage() );
		}
	}

	/**
	 * Get order columns for export.
	 *
	 * @param array $config Export configuration.
	 * @return array
	 */
	private static function get_order_columns_for_export( $config ) {
		$all_columns = array(
			'order_id',
			'order_date',
			'customer_name',
			'customer_email',
			'billing_phone',
			'shipping_address',
			'shipping_method',
			'payment_method',
			'order_status',
			'order_total',
		);

		$columns = array_filter(
			$config['columns'],
			function ( $col ) use ( $all_columns ) {
				return in_array( $col, $all_columns, true );
			}
		);

		return array_values( $columns );
	}

	/**
	 * Get item columns for export.
	 *
	 * @param array $config Export configuration.
	 * @return array
	 */
	private static function get_item_columns_for_export( $config ) {
		$all_columns = array(
			'product_id',
			'sku',
			'product_name',
			'quantity',
			'line_total',
			'line_tax',
			'line_subtotal',
			'product_categories',
		);

		$columns = array_filter(
			$config['columns'],
			function ( $col ) use ( $all_columns ) {
				return in_array( $col, $all_columns, true );
			}
		);

		return array_values( $columns );
	}

	/**
	 * Format order data for preview.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $config Export configuration.
	 * @return array
	 */
	private static function format_order_for_preview( $order, $config ) {
		$data = array();
		$columns = self::get_order_columns_for_export( $config );

		foreach ( $columns as $column ) {
			switch ( $column ) {
				case 'order_id':
					$data[ $column ] = $order->get_id();
					break;
				case 'order_date':
					$data[ $column ] = $order->get_date_created()->format( 'Y-m-d H:i:s' );
					break;
				case 'customer_name':
					$data[ $column ] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
					break;
				case 'customer_email':
					$data[ $column ] = $order->get_billing_email();
					break;
				case 'billing_phone':
					$data[ $column ] = $order->get_billing_phone();
					break;
				case 'shipping_address':
					$addr = $order->get_formatted_shipping_address();
					$data[ $column ] = str_replace( '<br/>', ', ', $addr );
					break;
				case 'shipping_method':
					$methods = $order->get_shipping_methods();
					$data[ $column ] = ! empty( $methods ) ? implode( ', ', array_keys( $methods ) ) : '';
					break;
				case 'payment_method':
					$data[ $column ] = $order->get_payment_method_title();
					break;
				case 'order_status':
					$data[ $column ] = $order->get_status();
					break;
				case 'order_total':
					$data[ $column ] = $order->get_total();
					break;
				default:
					$data[ $column ] = '';
			}
		}

		return $data;
	}

	/**
	 * Format item data for preview.
	 *
	 * @param \WC_Order_Item $item Order item object.
	 * @param \WC_Order      $order Order object.
	 * @param array          $config Export configuration.
	 * @return array
	 */
	private static function format_item_for_preview( $item, $order, $config ) {
		$data = array();
		$columns = self::get_item_columns_for_export( $config );

		foreach ( $columns as $column ) {
			switch ( $column ) {
				case 'product_id':
					$data[ $column ] = $item->get_product_id();
					break;
				case 'sku':
					$product = $item->get_product();
					$data[ $column ] = $product ? $product->get_sku() : '';
					break;
				case 'product_name':
					$data[ $column ] = $item->get_name();
					break;
				case 'quantity':
					$data[ $column ] = $item->get_quantity();
					break;
				case 'line_total':
					$data[ $column ] = $item->get_total();
					break;
				case 'line_tax':
					$data[ $column ] = $item->get_total_tax();
					break;
				case 'line_subtotal':
					$data[ $column ] = $item->get_subtotal();
					break;
				case 'product_categories':
					$product = $item->get_product();
					if ( $product ) {
						// For variations, get categories from parent product
						$product_id = $product->get_id();
						if ( $product->is_type( 'variation' ) ) {
							$parent_id = $product->get_parent_id();
							if ( $parent_id ) {
								$product_id = $parent_id;
							}
						}

						$terms = wp_get_post_terms(
							$product_id,
							'product_cat',
							array( 'fields' => 'names' )
						);
						$data[ $column ] = ! is_wp_error( $terms ) ? implode( $config['multi_term_separator'], $terms ) : '';
					} else {
						$data[ $column ] = '';
					}
					break;
				default:
					$data[ $column ] = '';
			}
		}

		// Add custom codes if mappings exist
		if ( ! empty( $config['custom_code_mappings'] ) ) {
			$product = $item->get_product();
			if ( $product ) {
				$custom_codes = self::get_product_custom_codes_for_preview( $product->get_id(), $config['custom_code_mappings'], $config );
				$data = array_merge( $data, $custom_codes );
			}
		}

		return $data;
	}

	/**
	 * Merge items for preview.
	 *
	 * @param array  $items Array of item arrays.
	 * @param string $separator Item separator.
	 * @return array
	 */
	private static function merge_items_for_preview( $items, $separator = '|' ) {
		if ( empty( $items ) ) {
			return array();
		}

		if ( count( $items ) === 1 ) {
			return $items[0];
		}

		$merged = array();
		$first = $items[0];

		foreach ( array_keys( $first ) as $column ) {
			$values = array_column( $items, $column );
			$values = array_filter(
				$values,
				function ( $v ) {
					return ! empty( $v );
				}
			);
			$merged[ $column ] = implode( $separator, $values );
		}

		return $merged;
	}

	/**
	 * Get product custom codes from meta or taxonomy.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $mappings Code mappings configuration.
	 * @param array $config Export configuration.
	 * @return array Custom code columns.
	 */
	private static function get_product_custom_codes_for_preview( $product_id, $mappings, $config ) {
		$codes = array();

		// Get the product object to check if it's a variation
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			// If product doesn't exist, return empty codes
			foreach ( $mappings as $column_name => $mapping ) {
				$codes[ $column_name ] = '';
			}
			return $codes;
		}

		// For variations, use the parent product ID for taxonomy terms
		// Variations inherit taxonomy assignments from the parent
		$taxonomy_product_id = $product_id;
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$taxonomy_product_id = $parent_id;
			}
		}

		foreach ( $mappings as $column_name => $mapping ) {
			if ( 'meta' === $mapping['type'] ) {
				// Fetch from product meta.
				$meta_key = $mapping['source'];
				// Sanitize meta key to prevent injection.
				$meta_key              = sanitize_text_field( $meta_key );
				$value                 = get_post_meta( $product_id, $meta_key, true );
				$codes[ $column_name ] = ! empty( $value ) ? $value : '';

			} elseif ( 'taxonomy' === $mapping['type'] ) {
				// Fetch from product taxonomy.
				$taxonomy = $mapping['source'];
				// Sanitize taxonomy name.
				$taxonomy = sanitize_text_field( $taxonomy );

				// Validate that the taxonomy exists for products.
				if ( ! taxonomy_exists( $taxonomy ) ) {
					$codes[ $column_name ] = '';
					continue;
				}

				// Get full term objects to access meta data
				// Use parent product ID for variations to get inherited taxonomy terms
				$terms = wp_get_post_terms(
					$taxonomy_product_id,
					$taxonomy,
					array( 'fields' => 'all' )
				);

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$separator = $config['multi_term_separator'];
					$term_values = array();
					
					foreach ( $terms as $term ) {
						// Extract term names and meta when available
						$term_values[] = $term->name;
					}
					
					$codes[ $column_name ] = implode( $separator, $term_values );
				} else {
					$codes[ $column_name ] = '';
				}
			}
		}

		return $codes;
	}

	/**
	 * Format CSV row.
	 *
	 * @param array  $row CSV row data.
	 * @param string $delimiter CSV delimiter.
	 * @return string
	 */
	private static function format_csv_row( $row, $delimiter = ',' ) {
		$output = '';
		foreach ( $row as $value ) {
			// Escape quotes and wrap if contains delimiter or quotes.
			$value = (string) $value;
			if ( strpos( $value, $delimiter ) !== false || strpos( $value, '"' ) !== false || strpos( $value, "\n" ) !== false ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			}
			$output .= $value . $delimiter;
		}
		return rtrim( $output, $delimiter ) . "\n";
	}

	/**
	 * Build export config from POST data.
	 *
	 * @return array
	 */
	private static function build_export_config() {
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		$statuses = isset( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', (array) $_POST['order_status'] ) : array( 'wc-completed' );

		// Ensure status keys have wc- prefix.
		$statuses = array_map(
			function ( $s ) {
				return strpos( $s, 'wc-' ) === 0 ? $s : 'wc-' . $s;
			},
			$statuses
		);

		$columns = isset( $_POST['columns'] ) ? array_map( 'sanitize_text_field', (array) $_POST['columns'] ) : array();

		// If no columns selected, use default columns.
		if ( empty( $columns ) ) {
			$columns = self::get_default_export_columns();
		}

		// Build custom code mappings.
		$code_mappings = array();
		if ( isset( $_POST['custom_codes'] ) && is_array( $_POST['custom_codes'] ) ) {
			foreach ( $_POST['custom_codes'] as $mapping ) {
				$mapping = array_map( 'sanitize_text_field', $mapping );
				if ( ! empty( $mapping['column_name'] ) && ! empty( $mapping['type'] ) && ! empty( $mapping['source'] ) ) {
					$code_mappings[ $mapping['column_name'] ] = array(
						'type'   => $mapping['type'],
						'source' => $mapping['source'],
					);
				}
			}
		}

		return array(
			'format'               => isset( $_POST['export_format'] ) ? sanitize_text_field( $_POST['export_format'] ) : 'csv',
			'delimiter'            => isset( $_POST['delimiter'] ) ? sanitize_text_field( $_POST['delimiter'] ) : ',',
			'export_mode'          => isset( $_POST['export_mode'] ) ? sanitize_text_field( $_POST['export_mode'] ) : 'line_item',
			'date_from'            => $date_from,
			'date_to'              => $date_to,
			'order_status'         => $statuses,
			'columns'              => $columns,
			'custom_code_mappings' => $code_mappings,
			'multi_term_separator' => isset( $_POST['multi_term_separator'] ) ? sanitize_text_field( $_POST['multi_term_separator'] ) : '|',
			'include_headers'      => isset( $_POST['include_headers'] ) ? true : false,
		);
	}

	/**
	 * Get default export columns.
	 *
	 * @return array
	 */
	private static function get_default_export_columns() {
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
		);
	}

	/**
	 * Get default export columns (static).
	 *
	 * @return array
	 */
	public static function get_default_export_columns_static() {
		return self::get_default_export_columns();
	}

	/**
	 * Generate preview data (static for AJAX).
	 *
	 * @param array $config Export configuration.
	 * @return string|WP_Error CSV preview or error.
	 */
	public static function generate_preview_data_static( $config ) {
		return self::generate_preview_data_internal( $config );
	}

	/**
	 * Internal preview generation.
	 *
	 * @param array $config Export configuration.
	 * @return string|WP_Error CSV preview or error.
	 */
	private static function generate_preview_data_internal( $config ) {
		try {
			// Create temporary preview file.
			$upload_dir    = wp_upload_dir();
			$preview_dir   = $upload_dir['basedir'] . '/wexport/';
			$preview_file  = $preview_dir . 'preview_temp_' . gmdate( 'YmdHis' ) . '_' . uniqid() . '.csv';

			// Ensure directory exists.
			if ( ! is_dir( $preview_dir ) ) {
				wp_mkdir_p( $preview_dir );
			}

			// Open file for writing.
			$file_handle = fopen( $preview_file, 'w' );
			if ( ! $file_handle ) {
				return new \WP_Error( 'file_error', __( 'Could not create preview file.', 'wexport' ) );
			}

			// Write headers.
			if ( $config['include_headers'] ) {
				$columns = array_merge(
					self::get_order_columns_for_export( $config ),
					self::get_item_columns_for_export( $config ),
					array_keys( $config['custom_code_mappings'] ?? array() )
				);
				$header_row = self::format_csv_row( $columns, $config['delimiter'] );
				fwrite( $file_handle, $header_row );
			}

			// Get a small batch of orders (5 rows).
			$orders = wc_get_orders(
				array(
					'limit'   => 5,
					'offset'  => 0,
					'status'  => $config['order_status'],
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);

			if ( empty( $orders ) ) {
				fclose( $file_handle );
				$preview_content = file_get_contents( $preview_file );
				if ( false === $preview_content ) {
					$preview_content = __( 'No orders found for preview.', 'wexport' );
				}
				unlink( $preview_file );
				return $preview_content ?: __( 'No data to preview.', 'wexport' );
			}

			// Process first 5 orders.
			foreach ( $orders as $order ) {
				$row_data = self::format_order_for_preview( $order, $config );
				if ( $config['export_mode'] === 'line_item' ) {
					// One row per line item.
					$items = $order->get_items();
					if ( ! empty( $items ) ) {
						foreach ( $items as $item ) {
							$item_data = self::format_item_for_preview( $item, $order, $config );
							$final_row = array_merge( $row_data, $item_data );
							fwrite( $file_handle, self::format_csv_row( $final_row, $config['delimiter'] ) );
						}
					} else {
						fwrite( $file_handle, self::format_csv_row( $row_data, $config['delimiter'] ) );
					}
				} else {
					// One row per order.
					$items = $order->get_items();
					$all_items = array();
					foreach ( $items as $item ) {
						$item_data = self::format_item_for_preview( $item, $order, $config );
						$all_items[] = $item_data;
					}
					if ( ! empty( $all_items ) ) {
						$merged = self::merge_items_for_preview( $all_items, $config['multi_term_separator'] );
						$final_row = array_merge( $row_data, $merged );
					} else {
						$final_row = $row_data;
					}
					fwrite( $file_handle, self::format_csv_row( $final_row, $config['delimiter'] ) );
				}
			}

			fclose( $file_handle );

			// Read preview content.
			$preview_content = file_get_contents( $preview_file );
			unlink( $preview_file );

			return $preview_content ?: __( 'Unable to generate preview.', 'wexport' );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'preview_error', $e->getMessage() );
		}
	}

	/**
	 * Get current page settings.
	 *
	 * @return array
	 */
	private static function get_current_settings() {
		return array(
			'export_mode'     => get_option( 'wexport_export_mode', 'line_item' ),
			'delimiter'       => get_option( 'wexport_delimiter', ',' ),
			'export_format'   => get_option( 'wexport_export_format', 'csv' ),
			'include_headers' => get_option( 'wexport_include_headers', true ),
			'column_mappings' => get_option( 'wexport_column_mappings', array() ),
			'custom_codes'    => get_option( 'wexport_custom_codes', array() ),
		);
	}

	/**
	 * Get all available order statuses.
	 *
	 * @return array
	 */
	public static function get_order_statuses() {
		$statuses = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			// Remove 'wc-' prefix for display.
			$status_key       = str_replace( 'wc-', '', $key );
			$statuses[ $key ] = $label;
		}
		return $statuses;
	}

	/**
	 * Get available export columns.
	 *
	 * @return array
	 */
	public static function get_available_columns() {
		return array(
			'Order Fields'   => array(
				'order_id'         => __( 'Order ID', 'wexport' ),
				'order_date'       => __( 'Order Date', 'wexport' ),
				'customer_name'    => __( 'Customer Name', 'wexport' ),
				'customer_email'   => __( 'Customer Email', 'wexport' ),
				'billing_phone'    => __( 'Billing Phone', 'wexport' ),
				'shipping_address' => __( 'Shipping Address', 'wexport' ),
				'shipping_method'  => __( 'Shipping Method', 'wexport' ),
				'payment_method'   => __( 'Payment Method', 'wexport' ),
				'order_status'     => __( 'Order Status', 'wexport' ),
				'order_total'      => __( 'Order Total', 'wexport' ),
			),
			'Item Fields'    => array(
				'product_id'    => __( 'Product ID', 'wexport' ),
				'sku'           => __( 'SKU', 'wexport' ),
				'product_name'  => __( 'Product Name', 'wexport' ),
				'quantity'      => __( 'Quantity', 'wexport' ),
				'line_total'    => __( 'Line Total', 'wexport' ),
				'line_tax'      => __( 'Line Tax', 'wexport' ),
				'line_subtotal' => __( 'Line Subtotal', 'wexport' ),
			),
			'Product Fields' => array(
				'product_categories' => __( 'Product Categories', 'wexport' ),
			),
		);
	}

	/**
	 * Get available product taxonomies.
	 *
	 * @return array Array of taxonomy names with labels.
	 */
	public static function get_available_product_taxonomies() {
		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		$result     = array();

		foreach ( $taxonomies as $taxonomy ) {
			// Only include public taxonomies
			if ( $taxonomy->public ) {
				$result[ $taxonomy->name ] = $taxonomy->label;
			}
		}

		// Sort alphabetically
		asort( $result );

		return $result;
	}
}
