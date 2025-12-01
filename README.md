# WooCommerce Custom Order Export

![WordPress](https://img.shields.io/badge/WordPress-21759b?style=for-the-badge&logo=WordPress&logoColor=%23FFFFFF&color=%2321759b) ![WooCommerce](https://img.shields.io/badge/WooCommerce-96588a?style=for-the-badge&logo=Woo&logoColor=%23FFFFFF&color=%2396588a&logoSize=30)

A production-ready WordPress plugin for exporting WooCommerce orders to CSV/XLSX with support for product-level custom codes and metadata.

## Features

- **Flexible Export Formats**: CSV (default) and XLSX output
- **Custom Column Selection**: Choose which order, item, and product fields to include
- **Product Custom Codes**: Map product meta keys or taxonomy terms to custom columns
- **Export Modes**: Export as one row per line item or one row per order
- **Advanced Filtering**: Filter orders by date range and status
- **Performance Optimized**: Stream output for large datasets (3,000+ orders)
- **Export Logging**: Track all export operations with filters applied
- **Extensible**: Hooks and filters for custom development
- **Security**: Proper nonces, capability checks, and sanitization

## Requirements

- WordPress 6.0+
- WooCommerce 6.0+
- PHP 7.4+

## Installation

1. Download the plugin or clone into your `wp-content/plugins/` directory
2. Navigate to the plugin in WordPress admin and activate
3. Go to **WooCommerce → Custom Order Export**

## Configuration

### Basic Setup

1. **Select Filters**
   - Choose date range (optional)
   - Select order statuses to include

2. **Choose Export Format**
   - CSV or XLSX
   - Set CSV delimiter (comma, semicolon, tab, pipe)
   - Configure column headers inclusion

3. **Select Columns**
   - Order fields (ID, date, customer info, totals)
   - Line item fields (product name, SKU, quantity, price)
   - Product fields (categories)

4. **Add Custom Code Mappings**
   - Map product meta keys or taxonomies to export columns
   - Supports multiple terms per product

## Custom Code Mapping Examples

### Example 1: Product Meta Key

Export a product custom field stored as post meta:

```
Column Name: product_code
Source Type: Product Meta
Meta Key: _product_code
```

This exports the value of `get_post_meta( $product_id, '_product_code', true )`

### Example 2: Product Taxonomy

Export a product taxonomy (like a custom "Collection" taxonomy):

```
Column Name: product_collection
Source Type: Taxonomy
Taxonomy Name: product_collection
```

This exports `wp_get_post_terms( $product_id, 'product_collection', array('fields' => 'names') )`

### Example 3: Product Attribute

For WooCommerce attributes (stored as taxonomy `pa_*`):

```
Column Name: metal_type
Source Type: Taxonomy
Taxonomy Name: pa_metal
```

## Default Export Columns

By default, the export includes:

```
order_id, order_date, customer_name, customer_email, billing_phone, 
shipping_address, payment_method, order_total, product_id, sku, 
product_name, quantity, line_total, product_code, product_categories
```

## Database Schema

The plugin creates a logging table (`wp_wexport_logs`) with the following columns:

- `id`: Auto-increment ID
- `export_date`: Timestamp of export
- `filters`: JSON of filters applied
- `file_path`: Path to exported file
- `rows_exported`: Number of rows in export
- `export_format`: csv or xlsx
- `user_id`: ID of user who triggered export
- `status`: success or error
- `error_message`: Error details if applicable

## Available Hooks

### Filters

```php
// Add or modify order query arguments
apply_filters( 'wexport_order_query_args', $args );

// Modify custom code mappings
apply_filters( 'wexport_custom_code_mappings', $mappings );
```

### Actions

```php
// Before export starts
do_action( 'wexport_before_export', $manager );

// After export completes
do_action( 'wexport_after_export', $manager, $file_path, $rows_exported );
```

## Example: Custom Code Implementation

### Adding a Product Meta Code

1. In your product edit screen or via code, save product meta:

```php
update_post_meta( $product_id, '_product_code', 'PROD-12345' );
```

2. In WExport, add a custom code mapping:
   - Column Name: `product_code`
   - Source Type: `Product Meta`
   - Meta Key: `_product_code`

### Adding a Product Taxonomy

1. Register a custom product taxonomy:

```php
register_taxonomy(
    'product_code',
    'product',
    array(
        'label' => 'Product Code',
        'rewrite' => array( 'slug' => 'product-code' ),
    )
);
```

2. Assign terms to products via admin or code:

```php
wp_set_post_terms( $product_id, array( 'PROD-12345' ), 'product_code' );
```

3. In WExport, add a custom code mapping:
   - Column Name: `product_code`
   - Source Type: `Taxonomy`
   - Taxonomy Name: `product_code`

## File Structure

```
woocommerce-custom-order-export/
├── woocommerce-custom-order-export.php   # Main plugin file
├── composer.json                         # Composer dependencies
├── composer.lock                         # Locked dependency versions
├── phpunit.xml                           # PHPUnit configuration
├── README.md                             # This file
├── CHANGELOG.md                          # Version history
├── LICENSE                               # GPL v2 license
├── .gitignore                            # Git ignore rules
│
├── includes/                             # Core plugin classes
│   ├── class-export-manager.php          # Main export orchestration
│   ├── class-export-formatter.php        # CSV/XLSX data formatting
│   ├── class-export-logger.php           # Export logging and tracking
│   ├── class-xlsx-exporter.php           # XLSX file generation
│   ├── class-ajax-handler.php            # AJAX request handling
│   ├── class-template-manager.php        # Template CRUD operations
│   ├── class-template-ajax-handler.php   # Template AJAX operations
│   └── class-import-manager.php          # Template import/export
│
├── admin/                                # WordPress admin interface
│   ├── class-admin-page.php              # Admin page controller
│   ├── admin-page.php                    # Admin UI HTML template
│   ├── css/
│   │   ├── admin-styles.css              # Main admin styling
│   │   ├── notifications.css             # Notification system styling
│   │   └── template-styles.css           # Template UI styling
│   └── js/
│       ├── admin-ui.js                   # Main admin interface logic
│       ├── notifications.js              # Notification system
│       └── template-manager.js           # Template frontend logic
│
├── tests/                                # PHPUnit tests
│   ├── bootstrap.php                     # Test bootstrap configuration
│   └── test-export-manager.php           # Export manager tests
│
└── vendor/                               # Composer dependencies
    ├── phpoffice/phpspreadsheet/         # XLSX generation library
    ├── phpunit/phpunit/                  # PHPUnit testing framework
    ├── wp-coding-standards/wpcs/         # WordPress coding standards
    └── [other dependencies...]
```

## Testing

### Unit Tests

Run PHPUnit tests using Composer:

```bash
# Run all tests
composer test

# Run with coverage report
composer test -- --coverage-html coverage/
```

Tests are located in the `tests/` directory and configured via `phpunit.xml`.

### Manual QA Steps

1. **Verify Product Meta Export**
   - Create a product with custom meta key `_test_code` = "TEST123"
   - Add custom code mapping in WExport (Meta type)
   - Export and verify column contains "TEST123"

2. **Verify Taxonomy Export**
   - Assign a product to a custom taxonomy term
   - Add custom code mapping in WExport (Taxonomy type)
   - Export and verify column contains term name

3. **Large Dataset Test**
   - With 3,000+ orders, verify export completes without memory errors
   - Check that file is created and downloaded correctly

4. **Multi-Item Order Test**
   - Create order with 3 line items
   - Export in "line_item" mode: should see 3 rows
   - Export in "order" mode: should see 1 row with products joined

5. **Special Characters Test**
   - Create product with quotes, commas, newlines in name
   - Verify CSV escaping works correctly in exported file

6. **Date Range Filtering Test**
   - Export with both from and to dates: verify complete range is applied
   - Export with only from date: verify all orders after that date
   - Export with only to date: verify all orders before that date

7. **Template System Test**
   - Save a template with custom columns
   - Load the template and verify all settings are restored
   - Set as default and reload page to verify auto-loading
   - Duplicate and edit templates

## Performance Considerations

### Memory Usage

- Orders are fetched in batches (default 100 per batch)
- CSV is streamed to disk via `fputcsv()`
- No order or item data stored in memory simultaneously

### Large Exports (3,000+ orders)

- Processing time depends on server speed and data size
- Recommend increasing PHP timeout for very large exports
- Consider exporting by date ranges for large organizations

## Troubleshooting

### "Export directory is not writable"

Ensure `/wp-content/uploads/wexport/` directory exists and is writable:

```bash
chmod 755 wp-content/uploads/wexport/
```

### Missing custom code columns in export

1. Verify the meta key exists on products: 
   ```php
   get_post_meta( $product_id, '_your_meta_key', true )
   ```

2. Verify the taxonomy exists and is assigned to product:
   ```php
   wp_get_post_terms( $product_id, 'your_taxonomy' )
   ```

3. Check that custom code mapping is configured in WExport settings

### CSV not opening correctly in Excel

Ensure "Include column headers" and "Use UTF-8 BOM" are both checked for Excel compatibility.

## For Developers

### Project Structure

- **includes/** - Core plugin classes (Export_Manager, Export_Logger, Exporters, Template_Manager)
- **admin/** - WordPress admin interface (class-admin-page.php, templates, styles, JavaScript)
- **tests/** - PHPUnit unit and integration tests
- **vendor/** - Composer dependencies (PhpSpreadsheet, WordPress Coding Standards)

### Code Standards

The project follows WordPress Coding Standards. Run checks with:

```bash
# Check code style
composer phpcs

# Auto-fix style issues
composer phpcbf
```

### Key Classes

- **Export_Manager** (`includes/class-export-manager.php`)
  - Orchestrates the export process
  - Queries orders in batches
  - Applies filters and custom code mappings
  - Delegates formatting to exporter classes

- **CSV_Exporter** / **XLSX_Exporter** (`includes/class-*-exporter.php`)
  - Format-specific export logic
  - Stream data to files
  - Handle styling and encoding

- **Export_Logger** (`includes/class-export-logger.php`)
  - Tracks export operations in database
  - Logs filters applied, file paths, status

- **Template_Manager** (`includes/class-template-manager.php`)
  - User-scoped template persistence
  - CRUD operations for templates
  - Default template handling

- **Admin_Page** (`admin/class-admin-page.php`)
  - Generates admin UI
  - Handles form data validation
  - Provides preview functionality

### Adding Features

#### Add a new export column

1. Update column selection UI in `admin/admin-page.php`
2. Add column to available columns list in `Export_Manager::get_available_columns()`
3. Implement column data retrieval in `Export_Formatter::format_item()`
4. Update default export columns if needed

#### Add a new filter type

1. Create filter UI in `admin/admin-page.php`
2. Modify `Export_Manager::get_orders_batch()` to apply new filter
3. Update `Export_Logger` to track new filter type
4. Add validation in `Admin_Page::validate_filters()`

#### Add a new export format

1. Create new exporter class extending base logic
2. Implement file writing in `export()` method
3. Register in `Export_Manager::get_exporter()`
4. Add format option to admin UI

### Hooks and Filters

**Filters:**
```php
// Modify order query arguments
apply_filters( 'wexport_order_query_args', $args );

// Modify custom code mappings
apply_filters( 'wexport_custom_code_mappings', $mappings );

// Modify export columns
apply_filters( 'wexport_export_columns', $columns );
```

**Actions:**
```php
// Before export starts
do_action( 'wexport_before_export', $manager );

// After export completes
do_action( 'wexport_after_export', $manager, $file_path, $rows_exported );
```

### Debugging

Enable debug logging by adding to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check logs in `wp-content/debug.log`. The plugin uses `error_log()` for server-side debugging.

For frontend debugging, check browser console (`F12`) for detailed logging during export operations.

### Running Tests Locally

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run with verbose output
composer test -- --verbose
```

Test files use WordPress test utilities if bootstrapped properly. See `tests/bootstrap.php` for configuration.

## Development Notes

### Adding Custom Columns

Extend export columns via filter:

```php
add_filter( 'wexport_order_query_args', function( $args ) {
    $args['meta_query'] = array(
        array(
            'key'   => '_custom_order_field',
            'value' => 'value',
        )
    );
    return $args;
});
```

### Creating a Scheduled Export

Use WP-Cron:

```php
wp_schedule_event( time(), 'daily', 'wexport_scheduled_export' );

add_action( 'wexport_scheduled_export', function() {
    $manager = new WExport\Export_Manager( array(
        'format'       => 'csv',
        'columns'      => get_option( 'wexport_default_columns' ),
        'order_status' => array( 'wc-completed' ),
    ));
    
    $file = $manager->export();
    
    // Email result
    wp_mail( 
        get_option( 'admin_email' ),
        'Daily Order Export',
        'Export completed: ' . $file
    );
});
```

## Performance Benchmark

Typical performance on standard hosting:

- 100 orders: < 1 second
- 1,000 orders: 5-10 seconds
- 10,000 orders: 1-2 minutes
- 100,000 orders: 15-30 minutes

## Security

- All admin actions require `manage_woocommerce` capability
- Nonces on all forms
- User ID and timestamp logged for all exports
- Meta keys and taxonomy names sanitized before database queries
- CSV output escaped for safe Excel import

## Support & Issues

For issues or feature requests, please contact support or submit via the plugin support channel.

## License

GPL v2 or later - See LICENSE file

## Changelog

### 1.7.1 - 2025-12-01

**Fixed**
- **Template System**: `remove_variation_from_product_name` checkbox value is now correctly saved in template config and loaded when templates are restored. Previously, this setting was only saved globally but not included in individual template configurations.

### 1.7.0 - 2025-11-29

**Fixed**
- **Critical**: Taxonomy custom columns now correctly show only the variation-specific attribute value for variation products, instead of showing all taxonomy terms from the parent product. When exporting a variation with a selected attribute (e.g., "50g" for `pa_gramaj`), only that specific value is now exported, not all available values.
- Product variation taxonomy extraction now prioritizes `get_attribute()` to retrieve the variation-specific value before falling back to `wp_get_post_terms()`.
- Preview and export now have consistent behavior for variation taxonomy values.
- Persist `remove_variation_from_product_name` admin setting: Store the checkbox state persistently using `update_option` from both AJAX and non-AJAX flows (preview and export), so the checkbox is remembered for subsequent sessions.

**Changed**
- Taxonomy handling: Reordered variation attribute extraction logic to check `product->get_attribute()` FIRST for variations, ensuring only the selected attribute value is returned, then falling back to taxonomy term queries only if needed.
- Simplified taxonomy extraction logic by removing redundant attribute fallback from the else block.

### 1.6.0 - 2025-11-29

**Added**
- Checkbox in admin UI to remove variation details from product names on export and preview. The exporter now uses parent product name for variations if this option is enabled.

**Changed**
- Taxonomy-based custom code mappings now prefer terms assigned to the variation (if the exported item is a variation) and fall back to the parent product if none are present. This makes per-variation attributes (e.g., `pa_gramaj`) export the correct variation value (like "50g").

### 1.5.0 - 2025-11-16

**Fixed**
- Date Range Filtering: Fixed critical bug where date range filters were not working correctly
  - Previously, when both `date_from` and `date_to` were specified, the second filter would overwrite the first
  - Now uses proper WooCommerce query approach with post-filtering for combined date ranges
  - Single date filters (from OR to) use native WooCommerce date_created parameter
  - Combined date range filters use PHP-level post-filtering to avoid WooCommerce HPOS incompatibility
  - Orders are now correctly filtered by the complete date range
- Export now respects both start and end dates when filtering orders
- Fixed TypeError from incompatible date query format with WooCommerce HPOS

**Changed**
- Improved `get_orders_batch()` method with hybrid approach: native query for single conditions, post-filtering for ranges
- Date filtering now uses time boundaries (00:00:00 for start, 23:59:59 for end) for accurate daily filtering
- Added custom parameter handling (_wexport_date_from, _wexport_date_to) for post-filtering logic

### 1.4.8 - 2025-11-16

**Changed**
- UX Improvement: Replaced browser alerts with user-friendly flyout notifications
- Alerts in template loading logic now display as non-intrusive notification popups
- Notifications support multiple types: success, error, warning, and info
- Auto-dismiss notifications after 3-5 seconds with manual close option
- Improved visual feedback with color-coded notification types

**Added**
- New notification system with dedicated CSS styling (`admin/css/notifications.css`)
- Notification JavaScript utility (`admin/js/notifications.js`) with `WExportNotifications` API
- Smooth animations for notification appearance and dismissal
- Responsive design for mobile and tablet devices
- Notification types: success (green), error (red), warning (orange), info (blue)
- Template Management Improvements:
  - Display currently loaded template in Template section with visual indicator
  - "Edit" button for templates to rename and update their configurations
  - Visual highlighting of currently loaded template in manage modal
  - Track currently loaded template state across sessions

### 1.4.7 - 2025-11-16

**Fixed**
- Simplified: Removed unnecessary `plugin_version` and `operation_type` columns from logging table
- Cleaned up database schema to only store essential export information
- Removed complex migration logic that was causing database issues

**Changed**
- Export logger now only tracks: filters, file_path, rows_exported, export_format, user_id, status, error_message
- Removed `log_export()` parameters: `operation_type`, `plugin_version`
- Removed `migrate_table()` method and all related migration logic
- Simplified table schema to reduce complexity

### 1.4.6 - 2025-11-16

**Fixed**
- Critical: Database migration now properly adds missing `plugin_version` and `operation_type` columns on export
- Simplified migration logic to use direct ALTER TABLE statements instead of dbDelta
- Migration now runs automatically before every export to ensure columns exist
- Added error logging for migration failures

**Changed**
- Migration function now checks column existence with `INFORMATION_SCHEMA` before attempting to add
- Export AJAX handler now calls `migrate_table()` before processing exports
- Transient-based migration caching on page load to avoid performance issues

### 1.4.5 - 2025-11-16

**Added**
- Auto-load default template - When a user sets a template as default, it now automatically loads on page visit
- Default template ID is passed to frontend and loaded on admin page initialization

**Changed**
- Template initialization now checks for and loads the default template if one is set
- Updated frontend data localization to include `defaultTemplateId`
- `loadDefaultTemplateIfSet()` method added to template manager for automatic loading

### 1.4.4 - 2025-11-16

**Fixed**
- Critical: Database table migration not running on existing installations
- Added automatic database migration on every plugin load to ensure missing columns are created
- Fixed `plugin_version` and `operation_type` columns missing on tables created with older versions

**Changed**
- Plugin now runs `migrate_table()` on `plugins_loaded` hook in addition to activation
- Ensures backward compatibility with all existing installations without manual SQL intervention

### 1.4.3 - 2025-11-16

**Fixed**
- Critical: Custom code mappings source field was always empty when saving templates
- Fixed form data collection to properly read from the correct input element (text vs select)
- For taxonomy types, now correctly reads from `.custom-code-source-select` instead of generic `.custom-code-source`
- For meta types, correctly reads from `.custom-code-source-text`

### 1.4.2 - 2025-11-16

**Fixed**
- Fixed custom code mappings not being properly serialized when saving templates
- Improved template save AJAX request to use FormData API for proper nested array handling
- Added comprehensive debug logging to track data flow during template save operations

**Changed**
- Template save now uses FormData API instead of plain object spread
- Enhanced error reporting in browser console and server logs
- Added detailed logging for custom codes array processing

### 1.4.1 - 2025-11-16

**Fixed**
- Critical: Template system now correctly loads custom column mappings when loading saved templates
- Custom code mappings were not being restored when loading templates due to form data mismatch
- Form now properly clears existing custom code rows before loading template rows
- Added null/undefined checks for safely handling template data

### 1.4.0 - 2025-11-16

**Added**
- Template Management System - Save, load, duplicate, and manage export templates
- New `Template_Manager` class for handling template CRUD operations
- New `Template_Ajax_Handler` class for AJAX template operations
- Template dropdown selector for quick template loading
- "Manage Templates" modal with table view of all saved templates
- Set default template functionality for automatic loading on page load
- Template duplication and deletion capabilities
- Template import/export as JSON for backup and sharing

**Changed**
- Updated admin page to include template management interface
- Added template JavaScript handler for frontend operations
- Plugin version bumped from 1.3.8 to 1.4.0

### 1.3.8 - 2025-11-16

**Fixed**
- Critical: XLSX export styling methods now compatible with PhpSpreadsheet 1.29+
- Fixed `getFont()` method - now uses `getStyle()->getFont()`
- Fixed `getFill()` method - now uses `getStyle()->getFill()`
- Fixed `getDataType()` method - now uses `getStyle()->getNumberFormat()`
- XLSX header styling (bold, gray background) now works correctly
- Number formatting for decimal values in XLSX files now works

### 1.3.7 - 2025-11-16

**Fixed**
- Important: Product variations now correctly export parent product taxonomy terms
- Variation products now inherit category and taxonomy assignments from their parent product
- Product categories now properly resolve for all product variation types

**Added**
- Automatic parent product detection for variation products
- Proper handling of WooCommerce product variation inheritance in taxonomy extraction

### 1.3.6 - 2025-11-16

**Fixed**
- Critical: Taxonomy form data not being properly captured during AJAX form submission
- Fixed FormData construction to correctly extract values from taxonomy select dropdowns
- Improved input visibility toggle to use CSS display properties instead of jQuery show/hide
- Taxonomy terms now properly export when selected from the custom code mapping dropdown

### 1.3.5 - 2025-11-16

**Fixed**
- Critical: Custom taxonomy term mappings showing no values in export
- Improved form validation to properly capture taxonomy select values
- Fixed visibility toggle detection in form data collection
- Added taxonomy validation to prevent silent failures on invalid taxonomy names

### 1.3.4 - 2025-11-16

**Fixed**
- Critical: Product taxonomy term data not being exported to CSV despite appearing in preview
- Custom taxonomy term extraction now retrieves full term objects instead of only term names
- Both export and preview now access complete term data with proper separators

### 1.3.3 - 2025-11-16

**Fixed**
- Critical: Custom code mappings not being included in export/preview
- Form data submission now uses FormData API instead of jQuery serialize()
- Properly handles nested array structure for custom codes (`custom_codes[index][field]`)
- Custom code columns and product data now correctly appear in exports

### 1.3.2 - 2025-11-16

**Added**
- Debug logging for custom code mapping issues
- Browser console logging to show custom codes before AJAX submission
- Server-side error logging for custom code processing (wp-content/debug.log)

### 1.3.1 - 2025-11-16

**Fixed**
- Bug: Validation error when using taxonomy dropdown selector
- Form validation now checks only the visible input field (text for meta, dropdown for taxonomy)
- Fixed "Please fill in both Column Name and Source" error during preview/export

### 1.3.0 - 2025-11-16

**Added**
- New Feature: Dynamic taxonomy dropdown selector for custom product code mappings
- `get_available_product_taxonomies()` method to fetch all public product taxonomies
- Smart input type switching in custom codes UI - text input for meta, select dropdown for taxonomies
- Taxonomy labels displayed with technical names for better user clarity (e.g., "Color (pa_color)")

### 1.2.9 - 2025-11-16

**Fixed**
- Bug: Product custom metas not included in preview generation
- Custom code mappings now correctly show in preview data
- Preview now matches final export output

### 1.2.8 - 2025-11-16

**Features**
- CSV and XLSX export support
- Product custom codes mapping (meta and taxonomy)
- Order filtering by date range and status
- Export logging and recent exports display
- Flexible column selection
- Template system foundation

### 1.0.0 - 2025-11-16

**Initial Release**
- Basic CSV and XLSX export functionality
- WooCommerce order export with customizable columns
- Support for product metadata and custom fields
- Date range and order status filtering
- Export logging to track operations
- Full WordPress and WooCommerce integration
- Security features: nonce verification, capability checks, input sanitization
