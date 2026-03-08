<?php
/**
 * Export manager class.
 *
 * @package WExport
 */

namespace WExport;

/**
 * Main export processing class.
 */
class Export_Manager {

	/**
	 * Export formatter instance.
	 *
	 * @var Export_Formatter
	 */
	private $formatter;

	/**
	 * Export logger instance.
	 *
	 * @var Export_Logger
	 */
	private $logger;

	/**
	 * Export configuration.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * File handle for streaming.
	 *
	 * @var resource
	 */
	private $file_handle;

	/**
	 * Rows buffer for XLSX export.
	 *
	 * @var array
	 */
	private $rows_buffer = array();

	/**
	 * Headers for export.
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Metadata headers can be variable based on the meta value, and can contain duplicates.
	 *
	 * @var string[]
	 */
	private $metadata_headers = array();

	/**
	 * Constructor.
	 *
	 * @param array $config Export configuration.
	 */
	public function __construct( $config = array() ) {
		$this->config    = wp_parse_args( $config, $this->get_default_config() );
		$this->formatter = new Export_Formatter( $this->config['format'], $this->config );
		$this->logger    = new Export_Logger();
	}

	/**
	 * Get default configuration.
	 *
	 * @return array
	 */
	private function get_default_config() {
		return array(
			'format'                             => 'csv',
			'delimiter'                          => ',',
			'charset'                            => 'UTF-8',
			'use_bom'                            => true,
			'export_mode'                        => 'line_item',
			'date_from'                          => '',
			'date_to'                            => '',
			'order_status'                       => array( 'wc-completed' ),
			'columns'                            => array(),
			'custom_code_mappings'               => array(),
			'line_item_metadata_mappings'        => array(),
			'multi_term_separator'               => '|',
			'include_headers'                    => true,
			'remove_variation_from_product_name' => false,
			'batch_size'                         => 100,
		);
	}

	/**
	 * Execute export and return file path.
	 *
	 * @return string|WP_Error File path or error.
	 */
	public function export() {
		// Verify user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'unauthorized', __( 'Insufficient permissions.', 'wexport' ) );
		}

		// Prepare file path.
		$file_path = $this->create_export_file();
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		try {
			// Apply pre-export hook.
			do_action( 'wexport_before_export', $this );

			// Determine export format.
			$format = $this->config['format'] ?? 'csv';

			if ( 'xlsx' === $format ) {
				// XLSX export - collect all rows in memory.
				$rows_exported = $this->export_xlsx( $file_path );
			} else {
				// CSV export - streaming.
				$rows_exported = $this->export_csv( $file_path );
			}

			if ( $rows_exported < 0 ) {
				return new \WP_Error( 'export_error', __( 'An error occurred during export.', 'wexport' ) );
			}

			// Apply post-export hook.
			do_action( 'wexport_after_export', $this, $file_path, $rows_exported );

			// Log export.
			Export_Logger::log_export(
				$this->get_filters_summary(),
				$file_path,
				$rows_exported,
				$format,
				'success'
			);

			return $file_path;

		} catch ( \Exception $e ) {
			// Log error.
			Export_Logger::log_export(
				$this->get_filters_summary(),
				$file_path,
				0,
				$this->config['format'] ?? 'csv',
				'error',
				$e->getMessage()
			);

			return new \WP_Error( 'export_error', $e->getMessage() );
		}
	}

	/**
	 * Export to CSV format (streaming).
	 *
	 * @param string $file_path File path.
	 * @return int Number of rows exported.
	 * @throws \Exception If the file cannot be opened.
	 */
	private function export_csv( $file_path ) {
		// Open file for writing.
		$this->file_handle = fopen( $file_path, 'w' );
		if ( ! $this->file_handle ) {
			throw new \Exception( esc_html__( 'Could not open export file.', 'wexport' ) );
		}

		// Write headers if needed.
		if ( $this->config['include_headers'] ) {
			$this->write_headers();
		}

		// Fetch and process orders.
		$rows_exported = $this->process_orders();

		// Close file.
		fclose( $this->file_handle );

		return $rows_exported;
	}

	/**
	 * Export to XLSX format.
	 *
	 * @param string $file_path File path.
	 * @return int Number of rows exported.
	 * @throws \Exception If the file cannot be written.
	 */
	private function export_xlsx( $file_path ) {
		// Collect headers (initial set - metadata headers will be added during processing).
		$this->headers = array_merge(
			$this->get_order_columns(),
			$this->get_item_columns(),
			array_keys( $this->config['custom_code_mappings'] ?? array() ),
			array_map(
				fn( $mapping ) => $mapping['column_name'],
				$this->config['line_item_metadata_mappings']
			)
		);

		// Initialize rows buffer.
		$this->rows_buffer = array();

		// Fetch and process orders.
		$rows_exported = $this->process_orders();

		// Map rows with final header list (including dynamic metadata headers).
		$final_headers = array_merge(
			$this->get_order_columns(),
			$this->get_item_columns(),
			array_keys( $this->config['custom_code_mappings'] ?? array() ),
			$this->metadata_headers
		);

		// Rebuild rows with final headers.
		$final_rows = array();
		foreach ( $this->rows_buffer as $row ) {
			$final_row = array();
			foreach ( $final_headers as $header ) {
				$final_row[] = $row[ $header ] ?? '';
			}
			$final_rows[] = $final_row;
		}

		// Write XLSX file.
		if ( $rows_exported > 0 ) {
			if ( ! Xlsx_Exporter::export( $file_path, $final_headers, $final_rows ) ) {
				throw new \Exception( esc_html__( 'Could not write XLSX file.', 'wexport' ) );
			}
		} elseif ( ! Xlsx_Exporter::export( $file_path, $final_headers, array() ) ) {
			// Create empty XLSX with just headers.
			throw new \Exception( esc_html__( 'Could not write XLSX file.', 'wexport' ) );
		}

		return $rows_exported;
	}

	/**
	 * Process orders in batches.
	 *
	 * @return int Total rows exported.
	 */
	private function process_orders() {
		$rows_exported = 0;
		$offset        = 0;
		$batch_size    = $this->config['batch_size'];

		while ( true ) {
			$orders = $this->get_orders_batch( $offset, $batch_size );

			if ( empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				$rows_exported += $this->process_order( $order );
			}

			$offset += $batch_size;
		}

		return $rows_exported;
	}

	/**
	 * Get a batch of orders.
	 *
	 * @param int $offset Query offset.
	 * @param int $limit Query limit.
	 * @return array Array of WC_Order objects.
	 */
	private function get_orders_batch( $offset, $limit ) {
		$args = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'status'  => $this->config['order_status'],
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		// Add date filters if provided using separate comparison operators.
		// WooCommerce requires individual date_created parameters, not arrays.
		if ( ! empty( $this->config['date_from'] ) ) {
			// Start of the day for date_from.
			$args['date_created'] = '>=' . $this->config['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $this->config['date_to'] ) ) {
			// End of the day for date_to. If date_from also exists, append as AND condition.
			$date_to_condition = '<=' . $this->config['date_to'] . ' 23:59:59';

			if ( ! empty( $this->config['date_from'] ) ) {
				// Both dates provided - use custom filtering after retrieval.
				// Store both conditions for post-filtering.
				$args['_wexport_date_from'] = $this->config['date_from'] . ' 00:00:00';
				$args['_wexport_date_to']   = $this->config['date_to'] . ' 23:59:59';
			} else {
				// Only date_to provided.
				$args['date_created'] = $date_to_condition;
			}
		}

		// Apply filter for extensibility.
		$args = apply_filters( 'wexport_order_query_args', $args );

		// Extract our custom date filters before passing to wc_get_orders.
		$date_from = isset( $args['_wexport_date_from'] ) ? $args['_wexport_date_from'] : null;
		$date_to   = isset( $args['_wexport_date_to'] ) ? $args['_wexport_date_to'] : null;
		unset( $args['_wexport_date_from'], $args['_wexport_date_to'] );

		$orders = wc_get_orders( $args );

		// Post-filter orders if both date_from and date_to are provided.
		if ( $date_from && $date_to ) {
			$date_from_ts = strtotime( $date_from );
			$date_to_ts   = strtotime( $date_to );

			$orders = array_filter(
				$orders,
				function ( $order ) use ( $date_from_ts, $date_to_ts ) {
					$order_ts = $order->get_date_created()->getTimestamp();
					return $order_ts >= $date_from_ts && $order_ts <= $date_to_ts;
				}
			);
		}

		return $orders;
	}

	/**
	 * Process a single order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return int Number of rows written.
	 */
	private function process_order( $order ) {
		$rows_written = 0;
		$order_data   = $this->formatter->format_order_data( $order, $this->get_order_columns() );

		// Get custom code mappings.
		$code_mappings               = apply_filters( 'wexport_custom_code_mappings', $this->config['custom_code_mappings'] );
		$line_item_metadata_mappings = apply_filters( 'wexport_line_item_metadata_mappings', $this->config['line_item_metadata_mappings'] );

		if ( 'line_item' === $this->config['export_mode'] ) {
			// One row per line item.
			$items = $order->get_items();

			if ( ! empty( $items ) ) {
				foreach ( $items as $item ) {
					$row       = $order_data;
					$item_data = $this->formatter->format_item_data(
						$item,
						$order,
						$this->get_item_columns()
					);
					$row       = array_merge( $row, $item_data );

					// Add custom codes.
					$product = $item->get_product();
					if ( $product ) {
						$row = array_merge(
							$row,
							$this->get_product_custom_codes(
								$product->get_id(),
								$code_mappings
							)
						);
					}

					if ( $line_item_metadata_mappings ) {
						$row = array_merge(
							$row,
							$this->get_line_item_metadata(
								$item,
								$line_item_metadata_mappings
							)
						);
					}

					$this->write_row( $row );
					++$rows_written;
				}
			} else {
				// Order with no items - write order data only.
				$this->write_row( $order_data );
				++$rows_written;
			}
		} else {
			// One row per order (line items joined).
			// Get all items data.
			$items     = $order->get_items();
			$all_items = array();

			foreach ( $items as $item ) {
				$item_data = $this->formatter->format_item_data(
					$item,
					$order,
					$this->get_item_columns()
				);
				$product   = $item->get_product();
				if ( $product ) {
					$item_data = array_merge(
						$item_data,
						$this->get_product_custom_codes(
							$product->get_id(),
							$code_mappings
						)
					);
				}
				$all_items[] = $item_data;
			}

			// Merge items with separator.
			$separator = $this->config['multi_term_separator'];
			$merged    = $this->merge_items( $all_items, $separator );

			$row = array_merge( $order_data, $merged );
			$this->write_row( $row );
			++$rows_written;
		}

		return $rows_written;
	}

	/**
	 * Get product custom codes from meta or taxonomy.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $mappings Code mappings configuration.
	 * @return array Custom code columns.
	 */
	private function get_product_custom_codes( $product_id, $mappings ) {
		$codes = array();

		// Get the product object to check if it's a variation.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			// If product doesn't exist, return empty codes.
			foreach ( $mappings as $column_name => $mapping ) {
				$codes[ $column_name ] = '';
			}
			return $codes;
		}

		// For variations, use the parent product ID for taxonomy terms.
		// Variations inherit taxonomy assignments from the parent.
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

				// For variation products, prioritize the specific attribute value first.
				// This returns only the selected variation value (e.g., "50g" for pa_gramaj).
				if ( $product->is_type( 'variation' ) ) {
					$attr_value = $product->get_attribute( $taxonomy );
					if ( ! empty( $attr_value ) ) {
						$codes[ $column_name ] = sanitize_text_field( $attr_value );
						continue;
					}
				}

				// Fallback: Try to get taxonomy terms assigned to the product.
				$terms = wp_get_post_terms(
					$product_id,
					$taxonomy,
					array( 'fields' => 'all' )
				);

				// If no terms on the product itself and it's a variation, check parent.
				if ( ( empty( $terms ) || is_wp_error( $terms ) ) && $product->is_type( 'variation' ) ) {
					$terms = wp_get_post_terms(
						$taxonomy_product_id,
						$taxonomy,
						array( 'fields' => 'all' )
					);
				}

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$separator   = $this->config['multi_term_separator'];
					$term_values = array();

					foreach ( $terms as $term ) {
						// Extract term names and meta when available.
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
	 * Get line item metadata from order item.
	 *
	 * @param \WC_Order_Item $line_item Line item.
	 * @param array          $mappings Metadata mappings configuration.
	 * @return array Line item metadata columns.
	 */
	private function get_line_item_metadata( $line_item, $mappings ) {
		$csv_meta = array();

		/**
		 * For this individual line-item, ensure unique column names.
		 *
		 * @var array<string, int>
		 */
		$duplicate_column_names = array();

		foreach ( $mappings as $mapping ) {

			/** @var \WC_Meta_Data[] $wc_meta_values */
			$wc_meta_values = $line_item->get_meta( $mapping['meta_key'], false );

			foreach ( $wc_meta_values as $wc_meta_value ) {

				$meta_data = $wc_meta_value->get_data();

				$meta_data_value = is_array( $meta_data['value'] ) ? $meta_data['value'] : json_decode( $meta_data['value'], true );

				if ( empty( $meta_data_value ) ) {

					$column_name = empty( $mapping['column_name'] )
						? $meta_data['key']
						: $mapping['column_name'];

					$csv_meta[ $column_name ] = $meta_data['value'];

					// Store the column name globally for later.
					if ( ! in_array( $column_name, $this->metadata_headers, true ) ) {
						$this->metadata_headers[] = $column_name;
					}

					continue;
				}

				// I.e. if it is a string etc.
				if ( ! is_array( $meta_data_value ) ) {

					$column_name = empty( $mapping['column_name'] )
						? $meta_data['key']
						: $mapping['column_name'];

					$csv_meta[ $column_name ] = $meta_data_value;

					// Store the column name globally for later.
					if ( ! in_array( $column_name, $this->metadata_headers, true ) ) {
						$this->metadata_headers[] = $column_name;
					}

					continue;
				}

				// Split the string by `.` to an array, remove empty entries.
				$path_parts = array_values(
					array_filter(
						explode( '.', $mapping['json_query'] ),
						function ( $value ) {
							return $value !== ''; }
					)
				);
				if ( 'value' === $path_parts[0] && ! isset( $meta_data_value['value'] ) ) {
					unset( $path_parts[0] );
				}

				$value_part = $meta_data_value;
				foreach ( $path_parts as $part ) {
					$value_part = $value_part[ $part ] ?? null;
				}

				$column_name = $mapping['column_name'];
				// If the column name begins with `.`, use it as a json query.
				if ( 0 === strpos( $column_name, '.' ) ) {
					$column_name_path_parts = array_values(
						array_filter(
							explode( '.', $column_name ),
							function ( $value ) {
								return $value !== ''; }
						)
					);
					if ( 'value' === $column_name_path_parts[0] && ! isset( $meta_data_value['value'] ) ) {
						unset( $column_name_path_parts[0] );
					}
					$name_part = $meta_data_value;
					foreach ( $column_name_path_parts as $column_name_path_part ) {
						$name_part = $name_part[ $column_name_path_part ] ?? null;
					}
					$column_name = $name_part;
				}

				if ( is_null( $column_name ) ) {
					$column_name = $mapping['meta_key'] . ' : ' . $mapping['column_name'];
				}

				if ( isset( $csv_meta[ $column_name ] ) ) {
					if ( ! isset( $duplicate_column_names[ $column_name ] ) ) {
						$duplicate_column_names[ $column_name ] = 2;
					} else {
						++$duplicate_column_names[ $column_name ];
					}
					$column_name = $column_name . '.' . $duplicate_column_names[ $column_name ];
				}

				$csv_meta[ $column_name ] = is_array( $value_part ) ? json_encode( $value_part ) : $value_part;

				// Store the column name globally for later.
				if ( ! in_array( $column_name, $this->metadata_headers, true ) ) {
					$this->metadata_headers[] = $column_name;
				}
			}
		}

		return $csv_meta;
	}

	/**
	 * Merge multiple items into single row.
	 *
	 * @param array  $items Array of item data arrays.
	 * @param string $separator Separator for multi-item columns.
	 * @return array Merged row.
	 */
	private function merge_items( $items, $separator = '|' ) {
		if ( empty( $items ) ) {
			return array();
		}

		if ( count( $items ) === 1 ) {
			return $items[0];
		}

		$merged = array();
		$first  = $items[0];

		foreach ( array_keys( $first ) as $column ) {
			$values = array_column( $items, $column );
			// Filter empty values.
			$values            = array_filter(
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
	 * Write headers to file.
	 */
	private function write_headers() {
		$columns = array_merge(
			$this->get_order_columns(),
			$this->get_item_columns(),
			array_keys( $this->config['custom_code_mappings'] ?? array() ),
			array_map(
				fn( $mapping ) => $mapping['column_name'],
				$this->config['line_item_metadata_mappings']
			)
		);

		$header_row = $this->formatter->format_headers( $columns );

		// Add BOM if needed.
		if ( 0 === ftell( $this->file_handle ) ) {
			$header_row = $this->formatter->add_bom( $header_row );
		}

		fwrite( $this->file_handle, $header_row );
	}

	/**
	 * Write data row to file.
	 *
	 * @param array $row Data row.
	 */
	private function write_row( $row ) {
		$columns = array_merge(
			$this->get_order_columns(),
			$this->get_item_columns(),
			array_keys( $this->config['custom_code_mappings'] ?? array() ),
			$this->metadata_headers,
		);

		// Ensure all columns exist in row.
		foreach ( $columns as $column ) {
			if ( ! isset( $row[ $column ] ) ) {
				$row[ $column ] = '';
			}
		}

		// Reorder row according to columns.
		$ordered = array();
		foreach ( $columns as $column ) {
			$ordered[ $column ] = $row[ $column ] ?? '';
		}

		// Determine export format.
		$format = $this->config['format'] ?? 'csv';

		if ( 'xlsx' === $format ) {
			// Buffer row for XLSX export.
			$this->rows_buffer[] = $ordered;
		} else {
			// Write to CSV file.
			$csv_row = $this->formatter->format_csv_row( $ordered );
			fwrite( $this->file_handle, $csv_row );
		}
	}

	/**
	 * Get configured order columns.
	 *
	 * @return array
	 */
	private function get_order_columns() {
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

		// Filter to only configured columns.
		$columns = array_filter(
			$this->config['columns'],
			function ( $col ) use ( $all_columns ) {
				return in_array( $col, $all_columns, true );
			}
		);

		return array_values( $columns );
	}

	/**
	 * Get configured item columns.
	 *
	 * @return array
	 */
	private function get_item_columns() {
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

		// Filter to only configured columns.
		$columns = array_filter(
			$this->config['columns'],
			function ( $col ) use ( $all_columns ) {
				return in_array( $col, $all_columns, true );
			}
		);

		return array_values( $columns );
	}

	/**
	 * Create temporary export file.
	 *
	 * @return string|WP_Error File path or error.
	 */
	private function create_export_file() {
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/wexport/';

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create directory if it doesn't exist.
		if ( ! $wp_filesystem->is_dir( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		// Check if writable.
		if ( ! $wp_filesystem->is_writable( $export_dir ) ) {
			return new \WP_Error( 'not_writable', __( 'Export directory is not writable.', 'wexport' ) );
		}

		$filename  = 'wexport_' . gmdate( 'YmdHis' ) . '.' . $this->config['format'];
		$file_path = $export_dir . $filename;

		return $file_path;
	}

	/**
	 * Get summary of filters applied.
	 *
	 * @return array
	 */
	private function get_filters_summary() {
		return array(
			'format'       => $this->config['format'],
			'export_mode'  => $this->config['export_mode'],
			'date_from'    => $this->config['date_from'],
			'date_to'      => $this->config['date_to'],
			'order_status' => $this->config['order_status'],
			'columns'      => count( $this->config['columns'] ),
		);
	}

	/**
	 * Get current config.
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Set config value.
	 *
	 * @param string $key Config key.
	 * @param mixed  $value Config value.
	 */
	public function set_config( $key, $value ) {
		$this->config[ $key ] = $value;
	}

	/**
	 * Download exported file.
	 *
	 * @param string $file_path File path to download.
	 */
	public static function download_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'wexport' ) );
		}

		$filename = basename( $file_path );

		// Set headers for download.
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $file_path );
		exit;
	}
}
