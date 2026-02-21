<?php
/**
 * Database operations for product sort orders
 *
 * @package ReWooProducts
 */

namespace ReWooProducts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class for managing product sort orders in custom table
 */
class Database {

	/**
	 * Table name for product orders
	 *
	 * @var string
	 */
	private static $table_name = 'rwpp_product_order';

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	/**
	 * Create custom table for storing product sort orders
	 *
	 * @return bool
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Note: dbDelta() requires CREATE TABLE without IF NOT EXISTS for proper parsing.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			category_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_product_category (product_id, category_id),
			KEY idx_product_id (product_id),
			KEY idx_category_id (category_id),
			KEY idx_sort_order (sort_order),
			KEY idx_category_sort (category_id, sort_order)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was actually created.
		if ( ! self::table_exists() ) {
			Helpers::log( 'Failed to create custom table: ' . $table_name, 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Migrate data from menu_order and postmeta to custom table
	 *
	 * @return array Migration result
	 */
	public static function migrate_data() {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = [
			'success'          => false,
			'global_migrated'  => 0,
			'category_migrated' => 0,
			'errors'           => [],
		];

		try {
			// Ensure table exists.
			$table_created = self::create_table();
			if ( ! $table_created ) {
				$result['errors'][] = 'Failed to create custom table';
				Helpers::log( 'Migration aborted: table creation failed', 'error' );
				return $result;
			}
			Helpers::log( 'Product order table created/verified', 'info' );

			// Phase 1: Migrate global sorting from menu_order.
			$global_count = self::migrate_global_sorting();
			if ( false === $global_count ) {
				$result['errors'][] = 'Global sorting migration query failed: ' . $wpdb->last_error;
				Helpers::log( 'Global sorting migration failed: ' . $wpdb->last_error, 'error' );
			} else {
				$result['global_migrated'] = $global_count;
				Helpers::log( 'Global sorting migration: ' . $global_count . ' records migrated', 'info' );
			}

			// Phase 2: Migrate category-specific sorting from postmeta.
			$category_count = self::migrate_category_sorting();
			if ( false === $category_count ) {
				$result['errors'][] = 'Category sorting migration query failed: ' . $wpdb->last_error;
				Helpers::log( 'Category sorting migration failed: ' . $wpdb->last_error, 'error' );
			} else {
				$result['category_migrated'] = $category_count;
				Helpers::log( 'Category sorting migration: ' . $category_count . ' records migrated', 'info' );
			}

			// Only mark success when there are zero errors.
			if ( empty( $result['errors'] ) ) {
				$result['success'] = true;
				Helpers::log( 'Data migration completed successfully', 'info' );
			}
		} catch ( \Exception $e ) {
			$result['errors'][] = $e->getMessage();
			Helpers::log( 'Migration error: ' . $e->getMessage(), 'error' );
		}

		return $result;
	}

	/**
	 * Migrate global sorting from wp_posts.menu_order
	 *
	 * @return int Number of records migrated
	 */
	private static function migrate_global_sorting() {
		global $wpdb;

		$table_name = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Insert global sorting (category_id = 0) from menu_order.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_result = $wpdb->query(
			"INSERT INTO {$table_name} (product_id, category_id, sort_order, created_at, updated_at)
			SELECT ID, 0, menu_order, NOW(), NOW()
			FROM {$posts_table}
			WHERE post_type = 'product' AND menu_order > 0
			ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()"
		);

		if ( false === $query_result ) {
			return false;
		}

		return $wpdb->rows_affected;
	}

	/**
	 * Migrate category-specific sorting from postmeta
	 *
	 * @return int Number of records migrated
	 */
	private static function migrate_category_sorting() {
		global $wpdb;

		$table_name = self::get_table_name();
		$postmeta_table = $wpdb->postmeta;

		// Insert category-specific sorting from postmeta.
		// Extract category_id from meta_key (rwpp_sortorder_{category_id})
		// NOTE: Include sort_order >= 0 to capture all positions including 0 (first position).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_result = $wpdb->query(
			"INSERT INTO {$table_name} (product_id, category_id, sort_order, created_at, updated_at)
			SELECT
				post_id,
				CAST(SUBSTRING(meta_key, 16) AS UNSIGNED),
				CAST(meta_value AS UNSIGNED),
				NOW(),
				NOW()
			FROM {$postmeta_table}
			WHERE meta_key LIKE 'rwpp_sortorder_%'
				AND meta_key REGEXP '^rwpp_sortorder_[0-9]+$'
				AND CAST(meta_value AS UNSIGNED) >= 0
			ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()"
		);

		if ( false === $query_result ) {
			return false;
		}

		return $wpdb->rows_affected;
	}

	/**
	 * Get sort order for a product and category
	 *
	 * @param int $product_id Product ID
	 * @param int $category_id Category ID (0 for global)
	 *
	 * @return int|null Sort order or null if not found
	 */
	public static function get_sort_order( $product_id, $category_id = 0 ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$product_id = absint( $product_id );
		$category_id = absint( $category_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sort_order FROM {$table_name} WHERE product_id = %d AND category_id = %d",
				$product_id,
				$category_id
			)
		);

		return null !== $result ? (int) $result : null;
	}

	/**
	 * Set sort order for a product and category
	 *
	 * @param int $product_id Product ID
	 * @param int $category_id Category ID (0 for global)
	 * @param int $sort_order Sort order value
	 *
	 * @return bool True on success
	 */
	public static function set_sort_order( $product_id, $category_id, $sort_order ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$product_id = absint( $product_id );
		$category_id = absint( $category_id );
		$sort_order = absint( $sort_order );

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (product_id, category_id, sort_order, created_at, updated_at)
				VALUES (%d, %d, %d, NOW(), NOW())
				ON DUPLICATE KEY UPDATE sort_order = %d, updated_at = NOW()",
				$product_id,
				$category_id,
				$sort_order,
				$sort_order
			)
		);

		// Sync to WPML translations (one-way: default language -> translations).
		if ( $result !== false ) {
			self::sync_orders_to_wpml_translations( [ $sort_order => $product_id ], $category_id );
		}

		return $result !== false;
	}

	/**
	 * Bulk update sort orders for multiple products in a category
	 *
	 * @param array $sort_orders Array of sort_order => product_id mappings
	 * @param int   $category_id Category ID (0 for global)
	 *
	 * @return bool True on success
	 */
	public static function bulk_update_orders( $sort_orders, $category_id = 0 ) {
		global $wpdb;

		if ( ! is_array( $sort_orders ) || empty( $sort_orders ) ) {
			Helpers::log( 'bulk_update_orders called with invalid sort_orders parameter', 'warning' );
			return false;
		}

		$table_name = self::get_table_name();
		$category_id = absint( $category_id );

		// Validate and sanitize input
		$sort_orders = Helpers::sanitize_sort_orders( $sort_orders );

		if ( empty( $sort_orders ) ) {
			Helpers::log( 'bulk_update_orders: sort_orders empty after sanitization for category_id: ' . $category_id, 'warning' );
			return false;
		}

		try {
			// Build batch INSERT ... ON DUPLICATE KEY UPDATE query
			$values = [];
			$product_ids = [];

			foreach ( $sort_orders as $sort_order => $product_id ) {
				$product_id = absint( $product_id );
				$sort_order = absint( $sort_order );

				if ( $product_id > 0 ) {
					$values[] = "({$product_id}, {$category_id}, {$sort_order}, NOW(), NOW())";
					$product_ids[] = $product_id;
				}
			}

			if ( empty( $values ) ) {
				Helpers::log( 'bulk_update_orders: no valid product_ids found for category_id: ' . $category_id, 'warning' );
				return false;
			}

			$values_sql = implode( ',', $values );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$update_result = $wpdb->query(
				"INSERT INTO {$table_name} (product_id, category_id, sort_order, created_at, updated_at)
				VALUES {$values_sql}
				ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()"
			);

			if ( false === $update_result ) {
				Helpers::log( 'Database error during bulk update for category_id: ' . $category_id . ', WPDB Error: ' . $wpdb->last_error, 'error' );
				return false;
			}

			// Sync to WPML translations (one-way: default language -> translations).
			self::sync_orders_to_wpml_translations( $sort_orders, $category_id );

			return true;

		} catch ( \Exception $e ) {
			// Log error and return false
			Helpers::log( 'Exception in bulk_update_orders for category_id: ' . $category_id . ', Error: ' . $e->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Get all products in a category with their sort orders
	 *
	 * @param int $category_id Category ID (0 for global)
	 *
	 * @return array Array of product objects with sort_order
	 */
	public static function get_products_by_category( $category_id = 0 ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$category_id = absint( $category_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE category_id = %d ORDER BY sort_order ASC",
				$category_id
			),
			ARRAY_A
		);

		return $results ? $results : [];
	}

	/**
	 * Delete all sort orders for a product
	 *
	 * @param int $product_id Product ID
	 *
	 * @return bool True on success
	 */
	public static function delete_product_orders( $product_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$product_id = absint( $product_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE product_id = %d",
				$product_id
			)
		);

		return $result !== false;
	}

	/**
	 * Delete sort orders for a product in a specific category
	 *
	 * @param int $product_id Product ID
	 * @param int $category_id Category ID
	 *
	 * @return bool True on success
	 */
	public static function delete_product_category_order( $product_id, $category_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$product_id = absint( $product_id );
		$category_id = absint( $category_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE product_id = %d AND category_id = %d",
				$product_id,
				$category_id
			)
		);

		return $result !== false;
	}

	/**
	 * Check if custom table exists
	 *
	 * @return bool True if table exists
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var(
			"SHOW TABLES LIKE '{$table_name}'"
		) === $table_name;
	}

	/**
	 * Get migration status
	 *
	 * @return array Migration status information
	 */
	public static function get_migration_status() {
		global $wpdb;

		return [
			'table_exists' => self::table_exists(),
			'total_global_orders' => self::count_global_orders(),
			'total_category_orders' => self::count_category_orders(),
			'db_version' => Helpers::get_db_version(),
		];
	}

	/**
	 * Count total global sort orders
	 *
	 * @return int Count of global orders
	 */
	private static function count_global_orders() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE category_id = 0"
		);

		return (int) $count;
	}

	/**
	 * Count total category-specific sort orders
	 *
	 * @return int Count of category orders
	 */
	private static function count_category_orders() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE category_id > 0"
		);

		return (int) $count;
	}

	/**
	 * Re-run migration from scratch
	 *
	 * Deletes the version flag, truncates the custom table, and runs full migration.
	 *
	 * @return array Migration result
	 */
	public static function run_remigration() {
		global $wpdb;

		// Delete the version flag so migration can run.
		delete_option( 'rwpp_db_version' );

		// Truncate the custom table.
		$table_name = self::get_table_name();
		if ( self::table_exists() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table_name}" );
		}

		// Run full migration.
		$result = self::migrate_data();

		// Set version flag on success.
		if ( $result['success'] ) {
			Helpers::update_db_version( '1.0.0' );
		}

		return $result;
	}

	/**
	 * WPML sync: copy ordering written in DEFAULT language to translated products/categories.
	 * One-way sync (default -> translations).
	 *
	 * @param array $sort_orders Array of sort_order => product_id mappings.
	 * @param int   $category_id Category ID (0 for global).
	 *
	 * @return void
	 */
	private static function sync_orders_to_wpml_translations( $sort_orders, $category_id = 0 ) {
		// Only if WPML is active.
		if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'wpml_default_language' ) ) {
			return;
		}

		$default_lang = apply_filters( 'wpml_default_language', null );
		$current_lang = apply_filters( 'wpml_current_language', null );

		if ( empty( $default_lang ) ) {
			return;
		}

		// Only sync when saving from default language (one-way).
		if ( ! empty( $current_lang ) && $current_lang !== $default_lang ) {
			return;
		}

		$languages = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 1 ] );
		if ( empty( $languages ) || ! is_array( $languages ) ) {
			return;
		}

		global $wpdb;
		$table_name  = self::get_table_name();
		$category_id = absint( $category_id );

		foreach ( $languages as $lang_code => $lang_info ) {
			if ( $lang_code === $default_lang ) {
				continue;
			}

			// Translate category term id for this language (unless global category_id=0).
			$translated_category_id = 0;
			if ( $category_id > 0 ) {
				$translated_category_id = (int) apply_filters( 'wpml_object_id', $category_id, 'product_cat', false, $lang_code );
				// If category not translated in this language, skip syncing for it.
				if ( $translated_category_id <= 0 ) {
					continue;
				}
			}

			$placeholders = [];
			$args         = [];

			foreach ( $sort_orders as $sort_order => $product_id ) {
				$product_id = absint( $product_id );
				$sort_order = absint( $sort_order );

				if ( $product_id <= 0 ) {
					continue;
				}

				// Translate product ID.
				$translated_product_id = (int) apply_filters( 'wpml_object_id', $product_id, 'product', false, $lang_code );
				if ( $translated_product_id <= 0 ) {
					// Product not translated in this language.
					continue;
				}

				// Only sync WooCommerce native menu_order for GLOBAL sorting (category_id = 0).
				if ( 0 === $category_id ) {
					$wpdb->update(
						$wpdb->posts,
						[ 'menu_order' => $sort_order ],
						[ 'ID' => $translated_product_id ],
						[ '%d' ],
						[ '%d' ]
					);
				}

				$placeholders[] = '(%d, %d, %d, NOW(), NOW())';
				$args[]         = $translated_product_id;
				$args[]         = $translated_category_id;
				$args[]         = $sort_order;
			}

			if ( empty( $placeholders ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$sql = "
				INSERT INTO {$table_name} (product_id, category_id, sort_order, created_at, updated_at)
				VALUES " . implode( ',', $placeholders ) . '
				ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()
			';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $sql, $args ) );
		}
	}
}
