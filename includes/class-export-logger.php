<?php
/**
 * Export logging class.
 *
 * @package WExport
 */

namespace WExport;

/**
 * Handles logging of export operations.
 */
class Export_Logger {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private static $table_name = 'wexport_logs';

	/**
	 * Create logs table on activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::$table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			export_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			filters longtext NOT NULL,
			file_path longtext NOT NULL,
			rows_exported int(11) NOT NULL,
			export_format varchar(10) NOT NULL,
			user_id bigint(20) NOT NULL,
			status varchar(20) NOT NULL,
			error_message longtext,
			PRIMARY KEY  (id),
			KEY export_date (export_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an export operation.
	 *
	 * @param array  $filters Export filters applied.
	 * @param string $file_path Path to exported file.
	 * @param int    $rows_exported Number of rows exported.
	 * @param string $format Export format (csv or xlsx).
	 * @param string $status Export status (success, error, etc.).
	 * @param string $error_message Optional error message.
	 */
	public static function log_export( $filters, $file_path, $rows_exported, $format, $status = 'success', $error_message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$wpdb->insert(
			$table_name,
			array(
				'filters'       => wp_json_encode( $filters ),
				'file_path'     => $file_path,
				'rows_exported' => (int) $rows_exported,
				'export_format' => sanitize_text_field( $format ),
				'user_id'       => get_current_user_id(),
				'status'        => sanitize_text_field( $status ),
				'error_message' => sanitize_text_field( $error_message ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get recent export logs.
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array
	 */
	public static function get_recent_logs( $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		// Check if table exists before querying.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			// Table doesn't exist, create it.
			self::create_table();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY export_date DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Cleanup old logs (rotate, keep last N days).
	 *
	 * @param int $days Keep logs for N days.
	 */
	public static function cleanup_logs( $days = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE export_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Get log statistics.
	 *
	 * @return object
	 */
	public static function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		return $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_exports,
				SUM(rows_exported) as total_rows,
				MAX(export_date) as last_export
			FROM $table_name"
		);
	}
}
