=== WooCommerce Custom Order Export ===
Contributors: kaisercrazy
Author: Serkan Algur
Author URI: https://serkanalgur.com.tr
Plugin URI: https://serkanalgur.com.tr
Tags: woocommerce, export, csv, xlsx, orders, custom codes
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.4
Requires Plugins: woocommerce
Stable tag: 1.5.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export WooCommerce orders to CSV/XLSX with product-level custom codes and metadata.

== Description ==

WooCommerce Order Export Pro is a powerful, production-ready plugin that allows you to export WooCommerce orders to CSV or XLSX format with complete support for product-level custom codes and metadata.

= Features =

- **Flexible Export Formats**: CSV (default) and XLSX output
- **Custom Column Selection**: Choose which order, item, and product fields to include
- **Product Custom Codes**: Map product meta keys or taxonomy terms to custom columns
- **Export Modes**: Export as one row per line item or one row per order
- **Advanced Filtering**: Filter orders by date range and status
- **Performance Optimized**: Stream output for large datasets (3,000+ orders)
- **Export Logging**: Track all export operations with filters applied
- **Extensible**: Hooks and filters for custom development
- **Security**: Proper nonces, capability checks, and sanitization

= Requirements =

- WordPress 6.0+
- WooCommerce 6.0+
- PHP 7.4+

== Installation ==

1. Download the plugin zip file
2. Go to **Plugins** > **Add New** > **Upload Plugin**
3. Upload the woocommerce-custom-order-export.zip file and click **Install Now**
4. Click **Activate Plugin** once installation is complete
5. Go to **WooCommerce** > **Custom Order Export** to start using the plugin

Or manually:
1. Extract the zip file to `/wp-content/plugins/wexport/`
2. Go to **Plugins** and activate **WooCommerce Order Export Pro**
3. Go to **WooCommerce** > **Custom Order Export**

== Quick Start ==

1. Navigate to **WooCommerce** > **Custom Order Export**
2. Select columns you want to export
3. (Optional) Set date range and order status filters
4. (Optional) Add product custom code mappings
5. Click **Export** to download your CSV file

== Configuration ==

= Basic Setup =

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

= Product Custom Codes =

Map custom product information to your export:

**From Product Meta Fields:**
- Column Name: "Product Code"
- Source Type: "Product Meta"
- Meta Key: "_product_code"

**From Product Taxonomy:**
- Column Name: "Material"
- Source Type: "Taxonomy"
- Taxonomy Name: "product_material"

== Available Columns ==

= Order Fields =
- order_id
- order_date
- order_status
- order_total
- customer_name
- customer_email
- billing_phone
- shipping_address
- shipping_method
- payment_method

= Line Item Fields =
- product_id
- sku
- product_name
- quantity
- line_total
- line_tax
- line_subtotal

= Product Fields =
- product_categories
- Custom codes (via mapping)

== Frequently Asked Questions ==

= How do I export specific orders? =
Use the date range and order status filters to select which orders to include in your export.

= Can I export product custom fields? =
Yes! Use the "Product Custom Codes Mapping" section to map your custom fields or taxonomy terms to export columns.

= What export formats are supported? =
CSV (default) and XLSX are both supported. You can also customize the CSV delimiter.

= Is there a limit to how many orders I can export? =
No limit! The plugin uses streaming output to handle large exports efficiently.

= Can I schedule automatic exports? =
Not yet, but you can use the programmatic API or hooks to schedule exports via WordPress cron.

== Changelog ==

= 1.5.0 =
* **Critical Fix**: Fixed date range filtering not working correctly when both start and end dates were specified
* Previously, when both `date_from` and `date_to` were specified, the second filter would overwrite the first
* Now uses proper WooCommerce query approach with post-filtering for combined date ranges
* Single date filters (from OR to) use native WooCommerce date_created parameter
* Combined date range filters use PHP-level post-filtering to avoid WooCommerce HPOS incompatibility
* Export now respects both start and end dates when filtering orders
* Time boundaries (00:00:00 for start, 23:59:59 for end) ensure accurate daily filtering

= 1.4.8 =
* **UX Improvement**: Replaced browser alerts with user-friendly flyout notifications
* Alerts in template loading logic now display as non-intrusive notification popups
* Notifications support multiple types: success, error, warning, and info
* Auto-dismiss notifications after 3-5 seconds with manual close option
* Improved visual feedback with color-coded notification types
* New notification system with dedicated CSS styling and JavaScript utility
* Display currently loaded template in Template section with visual indicator
* "Edit" button for templates to rename and update their configurations
* Visual highlighting of currently loaded template in manage modal
* Track currently loaded template state across sessions

= 1.4.7 =
* **Fixed**: Removed unnecessary `plugin_version` and `operation_type` columns from logging table
* Cleaned up database schema to only store essential export information
* Removed complex migration logic that was causing database issues
* Export logger now only tracks: filters, file_path, rows_exported, export_format, user_id, status, error_message
* Reduced maintenance burden and potential compatibility issues

= 1.4.6 =
* **Critical**: Database migration now properly adds missing `plugin_version` and `operation_type` columns on export
* Simplified migration logic to use direct ALTER TABLE statements instead of dbDelta
* Migration now runs automatically before every export to ensure columns exist
* Added error logging for migration failures

= 1.4.5 =
* **Added**: Auto-load default template - When a user sets a template as default, it now automatically loads on page visit
* Default template ID is passed to frontend and loaded on admin page initialization
* Template initialization now checks for and loads the default template if one is set

= 1.4.4 =
* **Critical**: Database table migration not running on existing installations
* Added automatic database migration on every plugin load to ensure missing columns are created
* Fixed `plugin_version` and `operation_type` columns missing on tables created with older versions
* Plugin now runs `migrate_table()` on `plugins_loaded` hook in addition to activation

= 1.4.3 =
* **Fixed**: Custom code mappings source field was always empty when saving templates
* Fixed form data collection to properly read from the correct input element (text vs select)
* For taxonomy types, now correctly reads from dropdown selector instead of generic input
* For meta types, correctly reads from text input field

= 1.4.2 =
* **Fixed**: Custom code mappings not being properly serialized when saving templates
* Improved template save AJAX request to use FormData API for proper nested array handling
* Added comprehensive debug logging to track data flow during template save operations
* Template save now uses FormData API instead of plain object spread

= 1.4.1 =
* **Critical**: Template system now correctly loads custom column mappings when loading saved templates
* Custom code mappings were not being restored when loading templates due to form data mismatch
* Form now properly clears existing custom code rows before loading template rows
* Added null/undefined checks for safely handling template data

= 1.4.0 =
* **New Feature**: Template Management System - Save, load, duplicate, and manage export templates
* New `Template_Manager` class for handling template CRUD operations
* New `Template_Ajax_Handler` class for AJAX template operations
* Template dropdown selector for quick template loading
* "Manage Templates" modal with table view of all saved templates
* Set default template functionality for automatic loading on page load
* Template duplication and deletion capabilities
* Template import/export as JSON for backup and sharing

= 1.3.8 =
* **Critical**: XLSX export styling methods now compatible with PhpSpreadsheet 1.29+
* Fixed `getFont()` method - now uses `getStyle()->getFont()`
* Fixed `getFill()` method - now uses `getStyle()->getFill()`
* Fixed `getDataType()` method - now uses `getStyle()->getNumberFormat()`
* XLSX header styling (bold, gray background) now works correctly
* Number formatting for decimal values in XLSX files now works

= 1.3.7 =
* **Important**: Product variations now correctly export parent product taxonomy terms
* Variation products now inherit category and taxonomy assignments from their parent product
* Product categories now properly resolve for all product variation types
* Automatic parent product detection for variation products

= 1.3.6 =
* **Critical**: Taxonomy form data not being properly captured during AJAX form submission
* Fixed FormData construction to correctly extract values from taxonomy select dropdowns
* Improved input visibility toggle to use CSS display properties instead of jQuery show/hide
* Taxonomy terms now properly export when selected from the custom code mapping dropdown
* Enhanced debug logging in AJAX handler to show detailed mapping processing steps

= 1.3.5 =
* **Critical**: Custom taxonomy term mappings showing no values in export
* Improved form validation to properly capture taxonomy select values
* Fixed visibility toggle detection in form data collection
* Added taxonomy validation to prevent silent failures on invalid taxonomy names
* Enhanced debug logging in JavaScript console showing row-by-row form data capture

= 1.3.4 =
* **Critical**: Product taxonomy term data not being exported to CSV despite appearing in preview
* Custom taxonomy term extraction now retrieves full term objects instead of only term names
* Both export and preview now access complete term data with proper separators
* Multiple taxonomy terms now properly joined with configured separator

= 1.3.3 =
* **Critical Fix**: Fixed custom code mappings not being included in export
* Form data submission now uses FormData API instead of jQuery serialize()
* Properly handles nested array structure for custom codes
* Custom code columns and product data now correctly appear in exports
* Improved form data submission for complex nested field structures

= 1.3.2 =
* Added debug logging to diagnose custom code mapping issues
* Browser console logs custom codes data before AJAX submission
* Server-side error logging for custom code processing in AJAX handler
* Helps identify where custom mappings are lost in the pipeline

= 1.3.1 =
* **Bug Fix**: Fixed validation error when using taxonomy dropdown selector
* Validation now checks only the visible input (text for meta, dropdown for taxonomy)
* Improved form validation logic to handle dynamic input type switching

= 1.3.0 =
* **New Feature**: Dynamic taxonomy dropdown selector for custom product code mappings
* Added `get_available_product_taxonomies()` method to fetch all public product taxonomies
* Enhanced custom codes UI with smart input type switching (text for meta, select for taxonomies)
* UI now displays taxonomy labels with their technical names for better clarity
* Improved JavaScript to handle dynamic switching between text input and select dropdown
* Passed available taxonomies to frontend via `wexportTaxonomies` global object

= 1.2.9 =
* **Bug Fix**: Product custom metas now included in preview generation
* Fixed issue where custom code mappings weren't showing in preview data
* Added `get_product_custom_codes_for_preview()` method to fetch custom product data during preview
* Preview now shows the same custom codes that appear in final export

= 1.2.8 =
* CSV and XLSX export support
* Product custom codes mapping (meta and taxonomy)
* Order filtering by date range and status
* Export logging and recent exports display
* Flexible column selection

= 1.0.0 =
* Initial release
* CSV and XLSX export support
* Product custom codes mapping
* Export filtering and logging
* Full WordPress and WooCommerce integration
* Security features: nonce verification, capability checks, input sanitization

== Support ==

For support, questions, or feedback, please visit:
https://serkanalgur.com.tr

== License ==

This plugin is licensed under the GPL v2 or later. See the included LICENSE file for details.

== Credits ==

Developed by Serkan Algur
https://serkanalgur.com.tr

== Code Quality ==

This plugin follows WordPress coding standards and includes:
- Comprehensive security measures (nonce verification, input sanitization)
- Performance optimizations for large datasets
- Extensive documentation and code examples
- Unit tests and QA checklist
