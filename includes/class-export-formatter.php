<?php
/**
 * Export formatter class.
 *
 * @package WExport
 */

namespace WExport;

/**
 * Handles CSV/XLSX formatting for export data.
 */
class Export_Formatter {

	/**
	 * Export format (csv or xlsx).
	 *
	 * @var string
	 */
	private $format = 'csv';

	/**
	 * CSV delimiter.
	 *
	 * @var string
	 */
	private $delimiter = ',';

	/**
	 * Character set.
	 *
	 * @var string
	 */
	private $charset = 'UTF-8';

	/**
	 * Include BOM for Excel compatibility.
	 *
	 * @var bool
	 */
	private $use_bom = true;

	/**
	 * Constructor.
	 *
	 * @param string $format Export format.
	 * @param array  $options Additional options.
	 */
	public function __construct( $format = 'csv', $options = array() ) {
		$this->format    = sanitize_text_field( $format );
		$this->delimiter = $options['delimiter'] ?? ',';
		$this->charset   = $options['charset'] ?? 'UTF-8';
		$this->use_bom   = $options['use_bom'] ?? true;
	}

	/**
	 * Set export format.
	 *
	 * @param string $format Format type.
	 */
	public function set_format( $format ) {
		$this->format = sanitize_text_field( $format );
	}

	/**
	 * Set CSV delimiter.
	 *
	 * @param string $delimiter Delimiter character.
	 */
	public function set_delimiter( $delimiter ) {
		$this->delimiter = sanitize_text_field( $delimiter );
	}

	/**
	 * Format row for CSV output.
	 *
	 * @param array $row Data row.
	 * @return string CSV formatted row.
	 */
	public function format_csv_row( $row ) {
		// Handle CSV field escaping.
		$fields = array();
		foreach ( $row as $field ) {
			$field = (string) $field;
			// Escape quotes and wrap fields with quotes if they contain delimiter or newlines.
			if ( strpos( $field, $this->delimiter ) !== false ||
				strpos( $field, '"' ) !== false ||
				strpos( $field, "\n" ) !== false ) {
				$field = '"' . str_replace( '"', '""', $field ) . '"';
			}
			$fields[] = $field;
		}
		return implode( $this->delimiter, $fields ) . "\n";
	}

	/**
	 * Format headers for CSV.
	 *
	 * @param array $headers Header row.
	 * @return string CSV formatted header.
	 */
	public function format_headers( $headers ) {
		return $this->format_csv_row( $headers );
	}

	/**
	 * Add UTF-8 BOM for Excel compatibility.
	 *
	 * @param string $content CSV content.
	 * @return string Content with BOM.
	 */
	public function add_bom( $content ) {
		if ( $this->use_bom && 'UTF-8' === $this->charset ) {
			return "\xEF\xBB\xBF" . $content;
		}
		return $content;
	}

	/**
	 * Sanitize field value for output.
	 *
	 * @param mixed  $value Field value.
	 * @param string $type Field type.
	 * @return string Sanitized value.
	 */
	public function sanitize_field( $value, $type = 'text' ) {
		if ( is_null( $value ) ) {
			return '';
		}

		switch ( $type ) {
			case 'number':
				return number_format( (float) $value, 2, '.', '' );

			case 'date':
				return is_string( $value ) ? $value : '';

			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url( $value );

			case 'array':
				return is_array( $value ) ? implode( ', ', array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( $value );

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Format order data for output.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $columns Columns to export.
	 * @return array Formatted order data.
	 */
	public function format_order_data( $order, $columns ) {
		$row = array();

		foreach ( $columns as $column ) {
			switch ( $column ) {
				case 'order_id':
					$row[ $column ] = $this->sanitize_field( $order->get_id(), 'number' );
					break;

				case 'order_date':
					$row[ $column ] = $this->sanitize_field(
						$order->get_date_created()->format( 'Y-m-d H:i:s' ),
						'date'
					);
					break;

				case 'customer_name':
					$row[ $column ] = $this->sanitize_field(
						$order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
					);
					break;

				case 'customer_email':
					$row[ $column ] = $this->sanitize_field( $order->get_billing_email(), 'email' );
					break;

				case 'billing_phone':
					$row[ $column ] = $this->sanitize_field( $order->get_billing_phone() );
					break;

				case 'shipping_address':
					$address = $order->get_shipping_address_1();
					if ( $order->get_shipping_address_2() ) {
						$address .= ' ' . $order->get_shipping_address_2();
					}
					$address       .= ', ' . $order->get_shipping_city();
					$address       .= ', ' . $order->get_shipping_state();
					$address       .= ' ' . $order->get_shipping_postcode();
					$row[ $column ] = $this->sanitize_field( $address );
					break;

				case 'shipping_method':
					$methods  = $order->get_shipping_methods();
					$shipping = array();
					foreach ( $methods as $method ) {
						$shipping[] = $method->get_method_title();
					}
					$row[ $column ] = $this->sanitize_field(
						implode( ', ', $shipping ),
						'array'
					);
					break;

				case 'payment_method':
					$row[ $column ] = $this->sanitize_field( $order->get_payment_method_title() );
					break;

				case 'order_status':
					$row[ $column ] = $this->sanitize_field( $order->get_status() );
					break;

				case 'order_total':
					$row[ $column ] = $this->sanitize_field( $order->get_total(), 'number' );
					break;

				default:
					// Check for order meta.
					$meta_value     = $order->get_meta( $column );
					$row[ $column ] = $this->sanitize_field( $meta_value );
					break;
			}
		}

		return $row;
	}

	/**
	 * Format line item data for output.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param \WC_Order              $order Order object.
	 * @param array                  $columns Columns to export.
	 * @return array Formatted item data.
	 */
	public function format_item_data( $item, $order, $columns ) {
		$row = array();

		foreach ( $columns as $column ) {
			switch ( $column ) {
				case 'product_id':
					$row[ $column ] = $this->sanitize_field( $item->get_product_id(), 'number' );
					break;

				case 'sku':
					$product        = $item->get_product();
					$row[ $column ] = $this->sanitize_field( $product ? $product->get_sku() : '' );
					break;

				case 'product_name':
					$row[ $column ] = $this->sanitize_field( $item->get_name() );
					break;

				case 'quantity':
					$row[ $column ] = $this->sanitize_field( $item->get_quantity(), 'number' );
					break;

				case 'line_total':
					$row[ $column ] = $this->sanitize_field( $item->get_total(), 'number' );
					break;

				case 'line_tax':
					$row[ $column ] = $this->sanitize_field( $item->get_total_tax(), 'number' );
					break;

				case 'line_subtotal':
					$row[ $column ] = $this->sanitize_field( $item->get_subtotal(), 'number' );
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

						$terms          = wp_get_post_terms(
							$product_id,
							'product_cat',
							array( 'fields' => 'names' )
						);
						$row[ $column ] = $this->sanitize_field( $terms, 'array' );
					} else {
						$row[ $column ] = '';
					}
					break;

				default:
					// Check for item meta.
					$meta_value     = $item->get_meta( $column );
					$row[ $column ] = $this->sanitize_field( $meta_value );
					break;
			}
		}

		return $row;
	}

	/**
	 * Get available field types.
	 *
	 * @return array
	 */
	public static function get_field_types() {
		return array(
			'order_fields'   => array(
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
			'item_fields'    => array(
				'product_id'    => __( 'Product ID', 'wexport' ),
				'sku'           => __( 'SKU', 'wexport' ),
				'product_name'  => __( 'Product Name', 'wexport' ),
				'quantity'      => __( 'Quantity', 'wexport' ),
				'line_total'    => __( 'Line Total', 'wexport' ),
				'line_tax'      => __( 'Line Tax', 'wexport' ),
				'line_subtotal' => __( 'Line Subtotal', 'wexport' ),
			),
			'product_fields' => array(
				'product_categories' => __( 'Product Categories', 'wexport' ),
			),
		);
	}
}
