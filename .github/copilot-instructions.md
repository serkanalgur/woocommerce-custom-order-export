## Repo Snapshot
- **What**: A WordPress plugin to export WooCommerce orders to CSV/XLSX with template management and custom product-code mapping.
- **Where to look**: `woocommerce-custom-order-export.php` (bootstrap & hooks), `includes/` (core logic), `admin/` (UI), `vendor/` (composer dependencies), `tests/` (phpunit tests).

## High-level Architecture
- Main bootstrap: `woocommerce-custom-order-export.php` — registers WordPress hooks, autoloader, AJAX routes and aliases.
- Export orchestration: `includes/class-export-manager.php` — queries orders in batches, applies filters, merges rows and calls formatters/exporters.
- Formatting & sanitization: `includes/class-export-formatter.php` — converts fields to CSV/XLSX-ready values (BOM, escaping, numeric formats).
- XLSX output: `includes/class-xlsx-exporter.php` — uses PhpSpreadsheet for XLSX creation and styling.
- Template management: `includes/class-template-manager.php` & `includes/class-template-ajax-handler.php` — CRUD and import/export of export templates stored in WP options.
- Logging: `includes/class-export-logger.php` — creates a small tracking table for export metadata.

## Key Patterns & Conventions
- Namespacing: Code uses PHP namespaces (`WExport` and `WExport\Admin`). Prefer new code under the `WExport\` namespace.
- Class filenames: Use the pattern `class-<kebab-case-name>.php` under `includes/` or `admin/` to match the plugin autoloader.
- Class aliases: The plugin creates legacy aliases via `wexport_setup_aliases()` (e.g., `class_alias( 'WExport\Export_Manager', 'WExport_Manager' )`). Use core namespaced classes and avoid creating global aliases unless needed for compatibility.
- Autoloader: The plugin has a custom lite autoloader in `woocommerce-custom-order-export.php` (maps underscores to hyphens and adds `class-` prefix). Composer PSR-4 is also configured in `composer.json`.
- Hooks & Filters: Expose extensibility points rather than changing core logic.
  - Filters: `wexport_order_query_args`, `wexport_custom_code_mappings`.
  - Actions: `wexport_before_export`, `wexport_after_export`.
- Security: AJAX endpoints verify nonces and capability checks (`manage_woocommerce`), sanitize inputs and escape outputs. Follow existing patterns (`sanitize_text_field`, `esc_attr`, `wp_kses_post`).
- Memory/perf: Exports use batching (`batch_size`), streaming for CSV (file streaming), and buffer rows for XLSX. Keep heavy operations outside loops when possible.

## Developer Workflows
- Install: `composer install` (installs PhpSpreadsheet, phpunit, code sniffer).
- Tests: `composer test` runs `phpunit` (uses `tests/bootstrap.php`). The bootstrap expects Composer autoloader and (for integration) a WP testing environment.
- Linting / Style: `composer phpcs`, `composer phpcbf` with WordPress standards.
- Debugging: Turn on `WP_DEBUG` and `WP_DEBUG_LOG`, check `wp-content/debug.log`. The plugin writes to `error_log()` for server-side traces.

## Adding Features — Guiding Examples
- Adding a new export column:
  1. Update UI in `admin/admin-page.php` and `admin/class-admin-page.php` (available columns and default columns).
  2. Add data mapping in `includes/class-export-formatter.php` (for orders/items or meta fallback).
  3. Ensure column keys appear in `Export_Manager::get_order_columns()` or `get_item_columns()` and are included in header formation.
  4. Add unit tests to `tests/test-export-manager.php` that verify formatting and CSV/XLSX output.

- Adding new export filter hook:
  1. Add a filter call in `Export_Manager::get_orders_batch()` or before running the export.
  2. Document the filter in README and add a test case for expected behavior.

## Performance/Edge Cases & Testing Notes
- Large datasets: default `batch_size` is 100 and CSV uses streaming (`fopen/fwrite`). For XLSX, rows are buffered then written via PhpSpreadsheet — consider memory usage.
- Date range filtering: plugin uses hybrid approach: single date queries in WooCommerce, and PHP-level post-filtering when both `date_from` and `date_to` provided; prefer calling `wexport_order_query_args` when altering date behavior.
- Products & Variations: Variation products use parent product IDs for taxonomy lookups — check `get_product_custom_codes()` and `get_product_custom_codes_for_preview()` for logic.

## File/Function Reference Cheat Sheet
- Bootstrap & hooks: `woocommerce-custom-order-export.php`
- Export orchestration: `includes/class-export-manager.php`
- CSV/XLSX formats & sanitization: `includes/class-export-formatter.php`
- XLSX writer: `includes/class-xlsx-exporter.php` (uses PhpSpreadsheet)
- AJAX: `includes/class-ajax-handler.php` & `woocommerce-custom-order-export.php` (action registration)
- Template manager: `includes/class-template-manager.php` & `includes/class-template-ajax-handler.php`
- Admin UI: `admin/class-admin-page.php`, `admin/admin-page.php`, `admin/js` and `admin/css`
- Tests: `tests/` with PHPUnit. `tests/bootstrap.php` expects Composer and optional WordPress test libs.

## Practical Tips for AI Agents
- Make small, atomic PRs: prefer adding a test and the corresponding implementation change in the same PR.
- Use existing hooks/filters before modifying export core logic; follow the patterns used in `Export_Manager` & `Admin_Page`.
- When changing formatting logic, update `Export_Formatter` and verify CSV/XLSX parity (escape logic and BOM).
- When in doubt about product taxonomy or variation behavior, look up `get_product_custom_codes()` and `get_product_custom_codes_for_preview()` for consistent handling.

## Release Workflow
- When preparing a release or PR that changes functionality, follow these steps:
  1. Bump plugin version: update the plugin header `Version:` and the `WEXPORT_VERSION` constant in `woocommerce-custom-order-export.php`.
  2. Update `composer.json` `version` field to match the plugin version.
  3. Add a short entry to `CHANGELOG.md` and `README.md` describing the change (summary + files modified).
  4. Update `readme.txt` `Stable tag:` and add a short changelog entry under the changelog sections.
  5. Run `composer test`, `composer phpcs`, and `composer phpcbf` if needed.
  6. Ensure any new admin options are included in `set_default_options()` and added to `admin/admin-page.php` and appropriate config flows.
  7. Add or update unit tests in `tests/` to cover the change.
  8. Commit, push, and open a PR with a detailed description pointing to the changelog and tests.

---
If you'd like, I can add a short CONTRIBUTING section to the README with developer setup steps (WordPress test scaffolding, how to run a local WP install for full integration tests). Any specific preferences? 
