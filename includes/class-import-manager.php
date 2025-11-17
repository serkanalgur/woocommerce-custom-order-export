<?php
/**
 * Import manager class for handling CSV/XLSX file imports.
 *
 * @package WExport
 */

namespace WExport;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Handles importing data from CSV/XLSX files.
 */
class Import_Manager {

	/**
	 * Import logger instance.
	 *
	 * @var Export_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Export_Logger();
	}

	/**
	 * Process uploaded file and extract data.
	 *
	 * @param array $file $_FILES array element.
	 * @return array|WP_Error Array with headers and rows, or error.
	 */
	public function import_file( $file ) {
		// Verify file upload.
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'wexport' ) );
		}

		// Check file type.
		$file_type = $this->get_file_type( $file['name'] );
		if ( ! in_array( $file_type, array( 'csv', 'xlsx' ), true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Only CSV and XLSX files are supported.', 'wexport' ) );
		}

		// Check file size (limit to 10MB).
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			return new \WP_Error( 'file_too_large', __( 'File size exceeds 10MB limit.', 'wexport' ) );
		}

		// Import based on file type.
		if ( 'csv' === $file_type ) {
			return $this->import_csv( $file['tmp_name'] );
		} elseif ( 'xlsx' === $file_type ) {
			return $this->import_xlsx( $file['tmp_name'] );
		}

		return new \WP_Error( 'unknown_error', __( 'Unknown error occurred.', 'wexport' ) );
	}

	/**
	 * Import data from CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array|WP_Error Array with headers and rows, or error.
	 */
	private function import_csv( $file_path ) {
		try {
			$data = array(
				'headers' => array(),
				'rows'    => array(),
			);

			$file = fopen( $file_path, 'r' );
			if ( ! $file ) {
				return new \WP_Error( 'file_read_error', __( 'Could not open CSV file.', 'wexport' ) );
			}

			$row_num = 0;

			while ( ( $row = fgetcsv( $file ) ) !== false ) {
				if ( 0 === $row_num ) {
					// First row is headers.
					$data['headers'] = array_map( 'trim', $row );
				} else {
					// Data rows.
					$row_data = array();
					foreach ( $data['headers'] as $index => $header ) {
						$row_data[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
					}
					$data['rows'][] = $row_data;
				}
				$row_num++;
			}

			fclose( $file );

			return $data;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'csv_import_error', __( 'Error importing CSV file: ', 'wexport' ) . $e->getMessage() );
		}
	}

	/**
	 * Import data from XLSX file.
	 *
	 * @param string $file_path Path to XLSX file.
	 * @return array|WP_Error Array with headers and rows, or error.
	 */
	private function import_xlsx( $file_path ) {
		try {
			$data = array(
				'headers' => array(),
				'rows'    => array(),
			);

			$spreadsheet = IOFactory::load( $file_path );
			$sheet       = $spreadsheet->getActiveSheet();
			$highest_row = $sheet->getHighestRow();
			$highest_col = $sheet->getHighestColumn();

			// Get headers from first row.
			for ( $col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnLetterToColumnIndex( $highest_col ); $col++ ) {
				$cell_value = $sheet->getCellByColumnAndRow( $col, 1 )->getValue();
				$data['headers'][] = $cell_value ? trim( (string) $cell_value ) : '';
			}

			// Get data rows.
			for ( $row = 2; $row <= $highest_row; $row++ ) {
				$row_data = array();
				for ( $col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnLetterToColumnIndex( $highest_col ); $col++ ) {
					$cell_value = $sheet->getCellByColumnAndRow( $col, $row )->getValue();
					$header     = isset( $data['headers'][ $col - 1 ] ) ? $data['headers'][ $col - 1 ] : '';
					$row_data[ $header ] = $cell_value ? trim( (string) $cell_value ) : '';
				}
				$data['rows'][] = $row_data;
			}

			return $data;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'xlsx_import_error', __( 'Error importing XLSX file: ', 'wexport' ) . $e->getMessage() );
		}
	}

	/**
	 * Determine file type from filename.
	 *
	 * @param string $filename File name.
	 * @return string File type (csv, xlsx, or empty).
	 */
	private function get_file_type( $filename ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $extension, array( 'csv', 'xlsx' ), true ) ? $extension : '';
	}

	/**
	 * Get sample data from imported file.
	 *
	 * @param array $data Imported data with headers and rows.
	 * @param int   $limit Number of rows to return.
	 * @return array Sample data.
	 */
	public static function get_sample( $data, $limit = 5 ) {
		return array(
			'headers' => $data['headers'] ?? array(),
			'rows'    => array_slice( $data['rows'] ?? array(), 0, $limit ),
		);
	}
}
