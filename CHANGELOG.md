# Changelog

All notable changes to the WooCommerce Order Export Pro plugin will be documented in this file.

## [1.5.0] - 2025-11-16

### Fixed
- **Date Range Filtering**: Fixed critical bug where date range filters were not working correctly
  - Previously, when both `date_from` and `date_to` were specified, the second filter would overwrite the first
  - Now uses proper WooCommerce query approach with post-filtering for combined date ranges
  - Single date filters (from OR to) use native WooCommerce date_created parameter
  - Combined date range filters use PHP-level post-filtering to avoid WooCommerce HPOS incompatibility
  - Orders are now correctly filtered by the complete date range
- Export now respects both start and end dates when filtering orders
- Fixed TypeError from incompatible date query format with WooCommerce HPOS

### Changed
- Improved `get_orders_batch()` method with hybrid approach: native query for single conditions, post-filtering for ranges
- Date filtering now uses time boundaries (00:00:00 for start, 23:59:59 for end) for accurate daily filtering
- Added custom parameter handling (_wexport_date_from, _wexport_date_to) for post-filtering logic

### Files Modified
- `includes/class-export-manager.php` - Fixed date range query logic in `get_orders_batch()` method

## [1.4.8] - 2025-11-16

### Changed
- **UX Improvement**: Replaced browser alerts with user-friendly flyout notifications
- Alerts in template loading logic now display as non-intrusive notification popups
- Notifications support multiple types: success, error, warning, and info
- Auto-dismiss notifications after 3-5 seconds with manual close option
- Improved visual feedback with color-coded notification types

### Added
- New notification system with dedicated CSS styling (`admin/css/notifications.css`)
- Notification JavaScript utility (`admin/js/notifications.js`) with `WExportNotifications` API
- Smooth animations for notification appearance and dismissal
- Responsive design for mobile and tablet devices
- Notification types: success (green), error (red), warning (orange), info (blue)
- **Template Management Improvements**:
  - Display currently loaded template in Template section with visual indicator
  - "Edit" button for templates to rename and update their configurations
  - Visual highlighting of currently loaded template in manage modal
  - Track currently loaded template state across sessions

### Files Added
- `admin/css/notifications.css` - Notification styling
- `admin/js/notifications.js` - Notification system implementation

### Files Modified
- `admin/js/template-manager.js` - Replaced all alerts with flyout notifications, added edit functionality
- `admin/js/admin-ui.js` - Replaced validation alerts with notifications
- `admin/admin-page.php` - Added current template display area
- `woocommerce-custom-order-export.php` - Added notification system enqueue, added 'edit' i18n string

## [1.4.7] - 2025-11-16

### Fixed
- **Simplified**: Removed unnecessary `plugin_version` and `operation_type` columns from logging table
- Cleaned up database schema to only store essential export information
- Removed complex migration logic that was causing database issues

### Changed
- Export logger now only tracks: filters, file_path, rows_exported, export_format, user_id, status, error_message
- Removed `log_export()` parameters: `operation_type`, `plugin_version`
- Removed `migrate_table()` method and all related migration logic
- Simplified table schema to reduce complexity

### Files Modified
- `includes/class-export-logger.php` - Simplified table schema and log_export() method
- `includes/class-ajax-handler.php` - Removed migration call before export
- `woocommerce-custom-order-export.php` - Removed all migration-related code and hooks

### Technical Details
- Table now has only essential columns for tracking exports
- No schema changes required on plugin updates
- Reduced maintenance burden and potential compatibility issues

## [1.4.6] - 2025-11-16

### Fixed
- **Critical**: Database migration now properly adds missing `plugin_version` and `operation_type` columns on export
- Simplified migration logic to use direct ALTER TABLE statements instead of dbDelta
- Migration now runs automatically before every export to ensure columns exist
- Added error logging for migration failures

### Changed
- Migration function now checks column existence with `INFORMATION_SCHEMA` before attempting to add
- Export AJAX handler now calls `migrate_table()` before processing exports
- Transient-based migration caching on page load to avoid performance issues

### Files Modified
- `includes/class-export-logger.php` - Simplified and fixed migration logic
- `includes/class-ajax-handler.php` - Added migration call before export
- `woocommerce-custom-order-export.php` - Added transient-based migration caching

### Technical Details
- Uses `$wpdb->get_var()` instead of `get_results()` for column checking
- Direct SQL `ALTER TABLE` statements work better for adding columns to existing tables
- Migration runs on page load once per hour (cached with transient)
- Migration also runs before each export as a safety check
- Error logging in wp-content/debug.log if columns cannot be added

## [1.4.5] - 2025-11-16

### Added
- **Auto-load default template** - When a user sets a template as default, it now automatically loads on page visit
- Default template ID is passed to frontend and loaded on admin page initialization

### Changed
- Template initialization now checks for and loads the default template if one is set
- Updated frontend data localization to include `defaultTemplateId`
- `loadDefaultTemplateIfSet()` method added to template manager for automatic loading

### Files Modified
- `admin/js/template-manager.js` - Added auto-loading of default template on page load
- `woocommerce-custom-order-export.php` - Added default template ID to frontend data and version bump

### Technical Details
- Default template loads via AJAX after a 500ms delay to ensure DOM is ready
- Uses existing `loadTemplate()` method for consistency
- No alert shown on auto-load to provide seamless user experience

## [1.4.4] - 2025-11-16

### Fixed
- **Critical**: Database table migration not running on existing installations
- Added automatic database migration on every plugin load to ensure missing columns are created
- Fixed `plugin_version` and `operation_type` columns missing on tables created with older versions

### Changed
- Plugin now runs `migrate_table()` on `plugins_loaded` hook in addition to activation
- Ensures backward compatibility with all existing installations without manual SQL intervention

### Files Modified
- `woocommerce-custom-order-export.php` - Added `ensure_db_migration()` method and hooked it to `plugins_loaded` action
- `includes/class-export-logger.php` - Migration logic now automatically detects and adds missing columns

### Technical Details
- Migration checks for `plugin_version` and `operation_type` columns before attempting to add them
- Uses `INFORMATION_SCHEMA.COLUMNS` to safely detect existing columns
- Prevents "Unknown column" database errors on first export after upgrade

## [1.4.3] - 2025-11-16

### Fixed
- **Critical**: Custom code mappings source field was always empty when saving templates
- Fixed form data collection to properly read from the correct input element (text vs select)
- For taxonomy types, now correctly reads from `.custom-code-source-select` instead of generic `.custom-code-source`
- For meta types, correctly reads from `.custom-code-source-text`

### Technical Details
- Updated `getFormData()` in template-manager.js to check source input type and read from appropriate element
- Fixed issue where jQuery `.val()` was returning value from first matching element instead of the visible one
- Removed debug logging from both frontend and backend

### Files Modified
- `admin/js/template-manager.js` - Fixed source field collection logic
- `includes/class-template-ajax-handler.php` - Cleaned up debug logging
- `includes/class-template-manager.php` - Cleaned up debug logging

## [1.4.2] - 2025-11-16

### Fixed
- Fixed custom code mappings not being properly serialized when saving templates
- Improved template save AJAX request to use FormData API for proper nested array handling
- Added comprehensive debug logging to track data flow during template save operations

### Changed
- Template save now uses FormData API instead of plain object spread
- Enhanced error reporting in browser console and server logs
- Added detailed logging for custom codes array processing

### Technical Details
- Updated `saveTemplate()` in template-manager.js to properly serialize nested arrays
- Added error_log statements in Template_Ajax_Handler for debugging
- Added console.log statements for browser-side debugging

### Files Modified
- `admin/js/template-manager.js` - Fixed FormData serialization
- `includes/class-template-ajax-handler.php` - Added debug logging

## [1.4.1] - 2025-11-16

### Fixed
- **Critical**: Template system now correctly loads custom column mappings when loading saved templates
- Custom code mappings were not being restored when loading templates due to form data mismatch
- Form now properly clears existing custom code rows before loading template rows
- Added null/undefined checks for safely handling template data

### Technical Details
- Fixed `populateFormFromConfig()` in template-manager.js to properly clear only row elements instead of entire tbody
- Enhanced `getFormData()` to only include custom code rows with non-empty column names
- Added proper undefined checking for `wexportTaxonomies` global variable in `createCustomCodeRow()`
- Ensured all template data fields use proper defaults to prevent errors on load

### Files Modified
- `admin/js/template-manager.js` - Fixed template loading for custom codes

## [1.4.0] - 2025-11-16

### Added
- **Template Management System** - Save, load, duplicate, and manage export templates
- New `Template_Manager` class for handling template CRUD operations
- New `Template_Ajax_Handler` class for AJAX template operations
- Template dropdown selector for quick template loading
- "Manage Templates" modal with table view of all saved templates
- Set default template functionality for automatic loading on page load
- Template duplication and deletion capabilities
- Template import/export as JSON for backup and sharing
- Comprehensive template documentation and guides

### Changed
- Updated admin page to include template management interface
- Added template JavaScript handler for frontend operations
- Plugin version bumped from 1.3.8 to 1.4.0

### Technical Details
**New Files:**
- `includes/class-template-manager.php` - Template persistence and management
- `includes/class-template-ajax-handler.php` - AJAX template operations
- `admin/js/template-manager.js` - Frontend template UI
- `TEMPLATE_FEATURE_GUIDE.md` - User guide for templates
- `TEMPLATE_ARCHITECTURE.md` - Technical architecture documentation

**Modified Files:**
- `woocommerce-custom-order-export.php` - Registered template AJAX hooks
- `admin/admin-page.php` - Added template UI section
- Various documentation files

**Features:**
- Save current form configuration as reusable template
- Load template to auto-fill all form fields
- Duplicate templates for quick variations
- Set any template as default for next session
- Browse all templates in modal with timestamps

### Security
- Nonce verification on all template AJAX requests
- User capability check (requires `manage_woocommerce`)
- User-scoped template storage (each user has separate templates)
- Input sanitization and validation

## [1.3.8] - 2025-11-16

### Fixed
- **Critical**: XLSX export styling methods now compatible with PhpSpreadsheet 1.29+
- Fixed `getFont()` method - now uses `getStyle()->getFont()`
- Fixed `getFill()` method - now uses `getStyle()->getFill()`
- Fixed `getDataType()` method - now uses `getStyle()->getNumberFormat()`
- XLSX header styling (bold, gray background) now works correctly
- Number formatting for decimal values in XLSX files now works

### Files Modified
- `includes/class-xlsx-exporter.php` - Updated PhpSpreadsheet API calls

### Technical Details
- PhpSpreadsheet 1.29+ requires accessing styling through `getStyle()` method
- Header formatting: bold text and light gray background (E8E8E8)
- Decimal numbers formatted with `0.00` format code
- Auto-fit columns and freeze panes working correctly

## [1.3.7] - 2025-11-16

### Fixed
- **Important**: Product variations now correctly export parent product taxonomy terms
- Variation products now inherit category and taxonomy assignments from their parent product
- Product categories now properly resolve for all product variation types

### Added
- Automatic parent product detection for variation products
- Proper handling of WooCommerce product variation inheritance in taxonomy extraction
- Parent product ID fallback for all taxonomy and category exports

### Changed
- Modified product custom code extraction to use parent product for taxonomy terms
- Updated product categories export to retrieve parent product categories for variations
- Enhanced product object checking to prevent errors with invalid products

### Files Modified
- `includes/class-export-manager.php` - Added variation parent detection for custom codes
- `includes/class-export-formatter.php` - Updated product categories export for variations
- `admin/class-admin-page.php` - Added variation support in preview and custom codes methods
- `woocommerce-custom-order-export.php` - Version bump to 1.3.7
- `VERSION` - Updated to 1.3.7

### Technical Details
- Uses `$product->is_type( 'variation' )` to detect variation products
- Retrieves parent product ID via `$product->get_parent_id()`
- Applies to: product_categories, custom taxonomy mappings, and custom meta export
- Backward compatible - handles non-variation products as before

## [1.3.6] - 2025-11-16

### Fixed
- **Critical**: Taxonomy form data not being properly captured during AJAX form submission
- Fixed FormData construction to correctly extract values from taxonomy select dropdowns
- Improved input visibility toggle to use CSS display properties instead of jQuery show/hide
- Taxonomy terms now properly export when selected from the custom code mapping dropdown

### Added
- Enhanced debug logging in AJAX handler to show detailed mapping processing steps
- Better error diagnostics showing which mappings are accepted vs. rejected
- Logging of input value extraction for both taxonomy and meta types

### Changed
- Refactored custom code FormData building to explicitly read from the correct input based on type
- Improved UI state initialization to ensure proper display of inputs on page load
- More robust console logging for troubleshooting form data issues
- Enhanced validation messages in debug output

### Files Modified
- `admin/js/admin-ui.js` - Improved FormData building and UI toggle logic
- `includes/class-ajax-handler.php` - Enhanced debug logging for mapping processing
- `woocommerce-custom-order-export.php` - Version bump to 1.3.6
- `VERSION` - Updated to 1.3.6

## [1.3.5] - 2025-11-16

### Fixed
- **Critical**: Custom taxonomy term mappings showing no values in export
- Improved form validation to properly capture taxonomy select values
- Fixed visibility toggle detection in form data collection
- Added taxonomy validation to prevent silent failures on invalid taxonomy names

### Added
- Enhanced debug logging in JavaScript console showing row-by-row form data capture
- Taxonomy existence validation before attempting term extraction
- Comprehensive troubleshooting guides and debugging documentation:
  - `DEBUGGING_GUIDE.md` - General debugging methodology
  - `TERM_EXPORT_TROUBLESHOOTING.md` - Specific term export issues
  - `CHANGES_MADE.md` - Detailed documentation of code improvements

### Changed
- Improved form data collection to use proper type-based input selection instead of CSS visibility selectors
- Enhanced JavaScript logging to show when rows are added vs. skipped
- Form validation now properly handles both meta and taxonomy inputs
- Better error handling for invalid taxonomy names with graceful fallback

### Files Modified
- `includes/class-export-manager.php` - Added taxonomy validation
- `admin/class-admin-page.php` - Added taxonomy validation to preview
- `admin/js/admin-ui.js` - Improved form validation and data collection with detailed logging
- New documentation files for debugging and troubleshooting

### Technical Details
- Added `taxonomy_exists()` validation before `wp_get_post_terms()` calls
- Removed reliance on CSS `:visible` selector in form data collection
- Improved console logging with individual row tracking
- Form values trimmed and validated before submission

## [1.3.4] - 2025-11-16

### Fixed
- **Critical**: Product taxonomy term data not being exported to CSV despite appearing in preview
- Custom taxonomy term extraction now retrieves full term objects instead of only term names
- Both export and preview now access complete term data with proper separators

### Added
- Enhanced term extraction to fetch full term objects with metadata support
- Consistent behavior between preview and actual export for taxonomy terms
- Term Meta Export Guide documentation (`TERM_META_EXPORT_GUIDE.md`)

### Changed
- `get_product_custom_codes()` in Export_Manager now uses `'fields' => 'all'` for term queries
- `get_product_custom_codes_for_preview()` in Admin_Page updated with same logic
- Multiple taxonomy terms now properly joined with configured separator
- Updated help text to clarify term export behavior

### Files Modified
- `includes/class-export-manager.php` - Enhanced term data extraction
- `admin/class-admin-page.php` - Updated preview generation logic
- `admin/admin-page.php` - Improved UI documentation
- `TERM_META_EXPORT_GUIDE.md` - New comprehensive guide for term exports

## [1.3.3] - 2025-11-16

### Fixed
- **Critical**: Custom code mappings not being included in export/preview
- Form data submission now uses FormData API instead of jQuery serialize()
- Properly handles nested array structure for custom codes (`custom_codes[index][field]`)
- Custom code columns and product data now correctly appear in exports

### Changed
- Refactored AJAX form data collection to use FormData API
- Added `contentType: false` and `processData: false` to AJAX request
- Manually rebuild custom codes array in FormData with proper indexing
- Enhanced console logging to show complete FormData contents

### Files Modified
- `admin/js/admin-ui.js` - Rewrote form data collection using FormData API

## [1.3.2] - 2025-11-16

### Added
- Debug logging for custom code mapping issues
- Browser console logging to show custom codes before AJAX submission
- Server-side error logging for custom code processing (wp-content/debug.log)
- Detailed logging of mapping validation and processing steps

### Files Modified
- `admin/js/admin-ui.js` - Added console logging for debugging
- `includes/class-ajax-handler.php` - Added error_log statements for custom code processing

## [1.3.1] - 2025-11-16

### Fixed
- **Bug**: Validation error when using taxonomy dropdown selector
- Form validation now checks only the visible input field (text for meta, dropdown for taxonomy)
- Fixed "Please fill in both Column Name and Source" error during preview/export

### Changed
- Enhanced `validateForm()` function to be aware of input type switching
- Validation logic now respects which input is actually visible and should be validated

### Files Modified
- `admin/js/admin-ui.js` - Updated validation logic

## [1.3.0] - 2025-11-16

### Added
- **New Feature**: Dynamic taxonomy dropdown selector for custom product code mappings
- `get_available_product_taxonomies()` method to fetch all public product taxonomies (product_cat, pa_color, pa_size, etc.)
- Smart input type switching in custom codes UI - text input for meta, select dropdown for taxonomies
- Taxonomy labels displayed with technical names for better user clarity (e.g., "Color (pa_color)")
- Frontend JavaScript support for toggling between text input and select dropdown
- Available taxonomies passed to frontend via `wexportTaxonomies` global object

### Changed
- Enhanced custom codes table to support both text input and select dropdown
- Updated JavaScript to dynamically handle type changes
- Improved admin-page.php to conditionally render appropriate input types

### Files Modified
- `admin/class-admin-page.php` - Added `get_available_product_taxonomies()` method
- `admin/admin-page.php` - Enhanced custom codes table with conditional rendering
- `admin/js/admin-ui.js` - Added taxonomy selector logic and dynamic input switching
- `woocommerce-custom-order-export.php` - Added taxonomy data localization for frontend
- `readme.txt` - Updated stable tag and changelog

## [1.2.9] - 2025-11-16

### Fixed
- **Bug**: Product custom metas not included in preview generation
- Custom code mappings now correctly show in preview data
- Preview now matches final export output

### Added
- `get_product_custom_codes_for_preview()` method to fetch custom product data during preview
- Custom product codes now included when generating preview for both line_item and order export modes

### Changed
- Enhanced `format_item_for_preview()` to fetch and merge custom codes
- Preview generation now replicates export manager behavior

### Files Modified
- `admin/class-admin-page.php` - Added custom codes support to preview
- `woocommerce-custom-order-export.php` - Updated version constant

## [1.2.8] - 2025-11-16

### Features
- CSV and XLSX export support
- Product custom codes mapping (meta and taxonomy)
- Order filtering by date range and status
- Export logging and recent exports display
- Flexible column selection
- Template system foundation

## [1.0.0] - 2025-11-16

### Initial Release
- Basic CSV and XLSX export functionality
- WooCommerce order export with customizable columns
- Support for product metadata and custom fields
- Date range and order status filtering
- Export logging to track operations
- Full WordPress and WooCommerce integration
- Security features: nonce verification, capability checks, input sanitization
