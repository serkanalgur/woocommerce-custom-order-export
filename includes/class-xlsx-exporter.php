<?php
/**
 * XLSX exporter class.
 *
 * @package WExport
 */

namespace WExport;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Handles XLSX export using PhpSpreadsheet.
 */
class Xlsx_Exporter {

	/**
	 * Export data to XLSX file.
	 *
	 * @param string $file_path File path to save XLSX.
	 * @param array  $headers Column headers.
	 * @param array  $rows Data rows.
	 * @return bool True on success, false on failure.
	 */
	public static function export( $file_path, $headers, $rows ) {
		try {
			// Create spreadsheet
			$spreadsheet = new Spreadsheet();
			$sheet       = $spreadsheet->getActiveSheet();

			// Write headers
			if ( ! empty( $headers ) ) {
				$col = 1;
				foreach ( $headers as $header ) {
					$cell = $sheet->getCellByColumnAndRow( $col, 1 );
					$cell->setValue( $header );

					// Style header: bold, light gray background
					$cell->getStyle()->getFont()->setBold( true );
					$cell->getStyle()->getFill()->setFillType( Fill::FILL_SOLID );
					$cell->getStyle()->getFill()->getStartColor()->setRGB( 'E8E8E8' );

					$col++;
				}
			}

			// Write data rows
			$row_num = 2;
			foreach ( $rows as $row_data ) {
				$col = 1;
				foreach ( $headers as $header ) {
					$value = isset( $row_data[ $header ] ) ? $row_data[ $header ] : '';
					$cell  = $sheet->getCellByColumnAndRow( $col, $row_num );
					$cell->setValue( $value );

					// Auto-detect and format numbers
					if ( is_numeric( $value ) && strpos( $value, '.' ) !== false ) {
						$cell->getStyle()->getNumberFormat()->setFormatCode( '0.00' );
					}

					$col++;
				}
				$row_num++;
			}

			// Auto-fit columns
			foreach ( $sheet->getColumnIterator() as $column ) {
				$sheet->getColumnDimension( $column->getColumnIndex() )->setAutoSize( true );
			}

			// Set freeze panes (freeze header row)
			$sheet->freezePane( 'A2' );

			// Write file
			$writer = new Xlsx( $spreadsheet );
			$writer->save( $file_path );

			return true;

		} catch ( \Exception $e ) {
			error_log( 'WExport XLSX Export Error: ' . $e->getMessage() );
			return false;
		}
	}
}
