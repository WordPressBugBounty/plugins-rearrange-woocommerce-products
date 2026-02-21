# Fix: Product Sort Order Resets to Last Position on Save

## Issue

When a user edits and saves a product that is in the **first position** (sort_order = 0) of a category, the product's category sort order gets overwritten and the product moves to the last position in the list.

### Steps to Reproduce

1. Set custom product sort orders in a category (e.g., "Decor")
2. Edit the first product in that category list and click "Update"
3. Return to the category product list — the product has moved to the last position

## Root Cause

Two bugs working together:

### 1. `Database::get_sort_order()` could not distinguish "no entry" from "sort_order = 0"

In `includes/Database.php`, the method returned `0` for both cases:

```php
// BEFORE (buggy)
return $result ? (int) $result : 0;
```

In PHP, the string `"0"` returned from the database is falsy, so `$result ? ... : 0` evaluates to `0` even when a valid row with `sort_order = 0` exists. This made it impossible for callers to distinguish between "product has no entry in the table" and "product is at position 0".

### 2. `Plugin::new_product_added()` overwrote sort_order for products at position 0

In `includes/Plugin.php`, the `save_post_product` hook fires on **every** product save (not just new products). The method checked:

```php
// BEFORE (buggy)
$existing_order = Database::get_sort_order( $post_id, $term->term_id );
if ( 0 === $existing_order || null === $existing_order ) {
    Database::set_sort_order( $post_id, $term->term_id, $menu_order );
}
```

Since `get_sort_order()` returned `0` for both "not found" and "position 0", the condition `0 === $existing_order` was always true for the first product. It then overwrote the category sort order with `$post->menu_order` — which is the **global** sort position (set by `legacy_update_menu_order()`), not the category position.

For example, if a product was #1 in "Decor" (sort_order = 0) but #15 globally (menu_order = 15), saving the product would set its category sort_order to 15, pushing it to the end of the category list.

## Fix Applied

### 1. `includes/Database.php` — `get_sort_order()` now returns `null` when no entry exists

```php
// AFTER (fixed)
return null !== $result ? (int) $result : null;
```

This properly returns:
- `null` — no row exists in the custom table
- `0` — product exists at position 0 (first position)
- `1+` — product exists at that sort position

### 2. `includes/Plugin.php` — `new_product_added()` only acts when entry is truly missing

```php
// AFTER (fixed)
$existing_order = Database::get_sort_order( $post_id, $term->term_id );
if ( null === $existing_order ) {
    Database::set_sort_order( $post_id, $term->term_id, $menu_order );
}
```

Now only products with **no entry at all** in the custom table get a sort order assigned. Products at position 0 are left untouched.

Same fix applied to the global sort order check below it.

## Files Modified

1. `includes/Database.php` — `get_sort_order()` return value changed from `0` to `null` for missing entries
2. `includes/Plugin.php` — `new_product_added()` condition changed from `0 === || null ===` to `null ===`

## Verification

1. Set products in a category with custom sort order
2. Edit the first product (position 0) and save
3. Confirm the product remains in its original position
4. Create a brand new product assigned to the category — confirm it gets added to the custom table
