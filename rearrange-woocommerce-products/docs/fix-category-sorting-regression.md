# Fix Category Sorting Regression (v5.0.2+)

## Context

Since v5.0.2, customers who had category-specific sort orders working in v4.x see them broken after updating. The v5.0.2 release switched from postmeta-based sorting (`rwpp_sortorder_{term_id}`) to a custom table (`wp_rwpp_product_order`). The migration that copies old data to the new table has silent failure bugs, and there's no fallback when the custom table is empty.

## Root Cause

### 1. Silent migration failures in `Database.php`

- `create_table()` uses `CREATE TABLE IF NOT EXISTS` with `dbDelta()` (known parsing issue) and always returns `true` regardless of success
- `migrate_data()` sets `$result['success'] = true` even when `$wpdb->query()` returns `false` for migration SQL
- `migrate_global_sorting()` and `migrate_category_sorting()` don't check query return values

### 2. Premature version flag in `Plugin.php`

- `check_database_version()` sets `rwpp_db_version = 1.0.0` based on the faulty success flag, preventing re-migration

### 3. No fallback for empty custom table

- Frontend sorting (`join_product_order_table` / `orderby_product_order`) only reads from the custom table
- If table is empty (migration failed), COALESCE falls back to `menu_order` (global sort) instead of old postmeta data

### v4.x vs v5.x sorting comparison

**v4.x** (working):
```php
$query->set('orderby', 'meta_value_num menu_order title');
// Reads from: wp_postmeta where meta_key = 'rwpp_sortorder_{term_id}'
```

**v5.x** (broken for affected users):
```sql
ORDER BY COALESCE(rwpp_order.sort_order, menu_order, 9999) ASC
-- Reads from: wp_rwpp_product_order (empty if migration failed)
-- Falls back to menu_order (WRONG - this is global sort, not category sort)
```

## Changes Made

### 1. Fix `includes/Database.php` - Migration error handling

**`create_table()`**:
- Removed `IF NOT EXISTS` from SQL (incompatible with `dbDelta()` parsing)
- Added table existence verification after `dbDelta()`
- Returns `false` on failure instead of always `true`

**`migrate_data()`**:
- Checks return value of `create_table()` and aborts early if `false`
- Checks individual migration query return values for `false`
- Only sets `$result['success'] = true` when there are zero errors
- Logs `$wpdb->last_error` on failure

**`migrate_global_sorting()` / `migrate_category_sorting()`**:
- Captures `$wpdb->query()` return value; returns `false` if query failed

**New `run_remigration()` method**:
- Deletes `rwpp_db_version` option
- Truncates the custom table
- Runs full `migrate_data()`
- Sets version flag on success

### 2. Add postmeta fallback to `includes/Plugin.php`

Core fix that makes sorting work immediately, even without re-migration.

**`join_product_order_table()`**:
- When `current_category_id > 0`, adds a second LEFT JOIN on `wp_postmeta` for `rwpp_sortorder_{term_id}`

**`orderby_product_order()`**:
- Inserts `CAST(rwpp_meta.meta_value AS UNSIGNED)` into COALESCE chain

Resulting SQL fallback chain:
```
custom_table.sort_order -> postmeta.rwpp_sortorder_{id} -> menu_order -> 9999
```

**`load_more_products_handler()`**:
- Same postmeta fallback in inline `$join_callback` and `$orderby_callback` closures

**New AJAX handler** `run_remigration_handler()`:
- Registered on `wp_ajax_rwpp_run_remigration`
- Calls `Database::run_remigration()`
- Returns JSON success/failure with migration counts

### 3. Fix `views/template-parts/tab-category-products.php`

- Added postmeta LEFT JOIN in the `$rwpp_join_callback` closure
- Added `CAST(rwpp_meta.meta_value AS UNSIGNED)` to COALESCE in `$rwpp_orderby_callback`

Note: `tab-all-products.php` does NOT need changes (global sorting uses `menu_order` fallback, which is already correct).

### 4. Add re-migration UI to `views/template-parts/tab-troubleshooting.php`

- New accordion panel with "Re-run Migration" button
- Uses standard WordPress button/spinner classes
- Result div for success/error messages

### 5. Add JS handler in `src/js/main.js`

- Click handler for `#rwpp-run-remigration` button
- AJAX POST to `rwpp_run_remigration` action
- Disables button during request, shows spinner
- Displays result with migration counts

## Files Modified

1. `includes/Database.php` - Fix migration, add `run_remigration()`
2. `includes/Plugin.php` - Postmeta fallback in JOIN/ORDER BY, add AJAX handler
3. `views/template-parts/tab-category-products.php` - Postmeta fallback in closures
4. `views/template-parts/tab-troubleshooting.php` - Re-migration button UI
5. `src/js/main.js` - Re-migration AJAX handler

## Verification Steps

1. **Automatic fix**: Visit a category page on the frontend - products should sort correctly using the postmeta fallback even if the custom table is empty
2. **Re-migration**: Go to Rearrange Products > Troubleshooting > "Re-run Migration" button - should report success with record counts
3. **After re-migration**: Category sorting should work from the custom table (verify via SQL: `SELECT * FROM wp_rwpp_product_order WHERE category_id > 0 LIMIT 10`)
4. **Admin consistency**: Admin category sort view should show same order as frontend
5. **No global sort regression**: Shop page (global sort) should continue to work unchanged

## Recovery for Affected Customers

Customers affected by the failed migration have two paths:

1. **Automatic**: Simply updating to v5.0.10 will add the postmeta fallback, restoring their category sort orders immediately without any action needed
2. **Manual**: After updating, they can visit Rearrange Products > Troubleshooting and click "Re-run Migration" to properly populate the custom table from their existing postmeta data
