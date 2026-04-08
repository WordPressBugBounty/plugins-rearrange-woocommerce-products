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

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

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
			Helpers::log( 'Failed to create product order table: ' . $wpdb->last_error, 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Migrate data from menu_order and postmeta to custom table
	 *
	 * @throws \Exception If migration encounters an error.
	 *
	 * @return array Migration result.
	 */
	public static function migrate_data() {
		global $wpdb;

		$table_name = self::get_table_name();
		$result     = [
			'success'           => false,
			'global_migrated'   => 0,
			'category_migrated' => 0,
			'errors'            => [],
		];

		try {
			// Ensure table exists.
			$table_created = self::create_table();
			if ( false === $table_created ) {
				$result['errors'][] = 'Failed to create product order table';
				Helpers::log( 'Migration aborted: could not create product order table', 'error' );
				return $result;
			}
			Helpers::log( 'Product order table created/verified', 'info' );

			$errors = 0;

			// Phase 1: Migrate global sorting from menu_order.
			$global_count = self::migrate_global_sorting();
			if ( false === $global_count ) {
				$result['errors'][] = 'Global sorting migration failed';
				++$errors;
			} else {
				$result['global_migrated'] = $global_count;
			}
			Helpers::log( 'Global sorting migration: ' . $result['global_migrated'] . ' records migrated', 'info' );

			// Phase 2: Migrate category-specific sorting from postmeta.
			$category_count = self::migrate_category_sorting();
			if ( false === $category_count ) {
				$result['errors'][] = 'Category sorting migration failed';
				++$errors;
			} else {
				$result['category_migrated'] = $category_count;
			}
			Helpers::log( 'Category sorting migration: ' . $result['category_migrated'] . ' records migrated', 'info' );

			if ( 0 === $errors ) {
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
	 * @return int Number of records migrated.
	 */
	private static function migrate_global_sorting() {
		global $wpdb;

		$table_name  = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Insert global sorting (category_id = 0) from menu_order.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$query_result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (product_id, category_id, sort_order, created_at, updated_at)
				SELECT ID, 0, menu_order, NOW(), NOW()
				FROM %i
				WHERE post_type = %s AND menu_order > 0
				ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()',
				$table_name,
				$posts_table,
				'product'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $query_result ) {
			Helpers::log( 'Global sorting migration query failed: ' . $wpdb->last_error, 'error' );
			return false;
		}

		return $wpdb->rows_affected;
	}

	/**
	 * Migrate category-specific sorting from postmeta
	 *
	 * @return int Number of records migrated.
	 */
	private static function migrate_category_sorting() {
		global $wpdb;

		$table_name     = self::get_table_name();
		$postmeta_table = $wpdb->postmeta;

		// Insert category-specific sorting from postmeta.
		// Extract category_id from meta_key (rwpp_sortorder_{category_id}).
		// NOTE: Include sort_order >= 0 to capture all positions including 0 (first position).
		$like_pattern = $wpdb->esc_like( 'rwpp_sortorder_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$query_result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (product_id, category_id, sort_order, created_at, updated_at)
				SELECT
					post_id,
					CAST(SUBSTRING(meta_key, 16) AS UNSIGNED),
					CAST(meta_value AS UNSIGNED),
					NOW(),
					NOW()
				FROM %i
				WHERE meta_key LIKE %s
					AND meta_key REGEXP '^rwpp_sortorder_[0-9]+$'
					AND CAST(meta_value AS UNSIGNED) >= 0
				ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()",
				$table_name,
				$postmeta_table,
				$like_pattern
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $query_result ) {
			Helpers::log( 'Category sorting migration query failed: ' . $wpdb->last_error, 'error' );
			return false;
		}

		return $wpdb->rows_affected;
	}

	/**
	 * Populate initial global sort order based on WooCommerce's default catalog ordering.
	 *
	 * On fresh installs, the custom table is empty because migration only copies
	 * products with menu_order > 0. This method queries all products using
	 * WooCommerce's default ordering and saves sequential positions so the admin
	 * rearrange page matches the frontend store page from the start.
	 *
	 * @return int Number of products populated, or 0 if skipped.
	 */
	public static function populate_initial_global_order() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// Skip if there are already global entries (user has arranged products or migration populated them).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE category_id = 0', $table_name )
		);

		if ( $existing_count > 0 ) {
			return 0;
		}

		// Get WooCommerce's default catalog ordering.
		$default_orderby = get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

		$query_args = [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'private' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		switch ( $default_orderby ) {
			case 'date':
				$query_args['orderby'] = 'date ID';
				$query_args['order']   = 'DESC';
				break;

			case 'popularity':
				$query_args['orderby']  = 'meta_value_num ID';
				$query_args['order']    = 'DESC';
				$query_args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'rating':
				$query_args['orderby']  = 'meta_value_num ID';
				$query_args['order']    = 'DESC';
				$query_args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'price':
				$query_args['orderby']  = 'meta_value_num ID';
				$query_args['order']    = 'ASC';
				$query_args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'price-desc':
				$query_args['orderby']  = 'meta_value_num ID';
				$query_args['order']    = 'DESC';
				$query_args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'menu_order':
			default:
				$query_args['orderby'] = 'menu_order title';
				$query_args['order']   = 'ASC';
				break;
		}

		$product_ids = get_posts( $query_args );

		if ( empty( $product_ids ) ) {
			return 0;
		}

		// Build batch INSERT query with sequential sort positions.
		$values = [];
		foreach ( $product_ids as $position => $product_id ) {
			$product_id = absint( $product_id );
			$sort_order = absint( $position );
			$values[]   = "({$product_id}, 0, {$sort_order}, NOW(), NOW())";
		}

		$values_sql = implode( ',', $values );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (product_id, category_id, sort_order, created_at, updated_at)
				VALUES {$values_sql}
				ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()",
				$table_name
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( false === $result ) {
			Helpers::log( 'Failed to populate initial global sort order: ' . $wpdb->last_error, 'error' );
			return 0;
		}

		$count = count( $product_ids );
		Helpers::log( "Populated initial global sort order for {$count} products (WooCommerce default: {$default_orderby})", 'info' );

		return $count;
	}

	/**
	 * Re-run the full migration from legacy storage
	 *
	 * Deletes the db version flag, truncates the custom table, re-runs migration,
	 * and sets the version on success.
	 *
	 * @return array Migration result with success flag and counts.
	 */
	public static function run_remigration() {
		global $wpdb;

		// Delete the version flag so migration will run.
		delete_option( 'rwpp_db_version' );

		// Truncate the custom table to start fresh.
		$table_name = self::get_table_name();
		if ( self::table_exists() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );
		}

		// Re-run migration.
		$result = self::migrate_data();

		// Set version on success.
		if ( $result['success'] ) {
			update_option( 'rwpp_db_version', '1.0.0' );
		}

		return $result;
	}

	/**
	 * Get sort order for a product and category
	 *
	 * @param int $product_id  Product ID.
	 * @param int $category_id Category ID (0 for global).
	 *
	 * @return int Sort order or 0 if not found.
	 */
	public static function get_sort_order( $product_id, $category_id = 0 ) {
		global $wpdb;

		$table_name  = self::get_table_name();
		$product_id  = absint( $product_id );
		$category_id = absint( $category_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT sort_order FROM %i WHERE product_id = %d AND category_id = %d',
				$table_name,
				$product_id,
				$category_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return null !== $result ? (int) $result : null;
	}

	/**
	 * Set sort order for a product and category
	 *
	 * @param int $product_id  Product ID.
	 * @param int $category_id Category ID (0 for global).
	 * @param int $sort_order  Sort order value.
	 *
	 * @return bool True on success.
	 */
	public static function set_sort_order( $product_id, $category_id, $sort_order ) {
		global $wpdb;

		$table_name  = self::get_table_name();
		$product_id  = absint( $product_id );
		$category_id = absint( $category_id );
		$sort_order  = absint( $sort_order );

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (product_id, category_id, sort_order, created_at, updated_at)
				VALUES (%d, %d, %d, NOW(), NOW())
				ON DUPLICATE KEY UPDATE sort_order = %d, updated_at = NOW()',
				$table_name,
				$product_id,
				$category_id,
				$sort_order,
				$sort_order
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $result;
	}

	/**
	 * Bulk update sort orders for multiple products in a category
	 *
	 * @param array $sort_orders Array of sort_order => product_id mappings.
	 * @param int   $category_id Category ID (0 for global).
	 *
	 * @throws \Exception If database error occurs during bulk update.
	 *
	 * @return bool True on success.
	 */
	public static function bulk_update_orders( $sort_orders, $category_id = 0 ) {
		global $wpdb;

		if ( ! is_array( $sort_orders ) || empty( $sort_orders ) ) {
			Helpers::log( 'bulk_update_orders called with invalid sort_orders parameter', 'warning' );
			return false;
		}

		$table_name  = self::get_table_name();
		$category_id = absint( $category_id );

		// Validate and sanitize input.
		$sort_orders = Helpers::sanitize_sort_orders( $sort_orders );

		if ( empty( $sort_orders ) ) {
			Helpers::log( 'bulk_update_orders: sort_orders empty after sanitization for category_id: ' . $category_id, 'warning' );
			return false;
		}

		try {
			// Build batch INSERT ... ON DUPLICATE KEY UPDATE query.
			$values      = [];
			$product_ids = [];

			foreach ( $sort_orders as $sort_order => $product_id ) {
				$product_id = absint( $product_id );
				$sort_order = absint( $sort_order );

				if ( $product_id > 0 ) {
					$values[]      = "({$product_id}, {$category_id}, {$sort_order}, NOW(), NOW())";
					$product_ids[] = $product_id;
				}
			}

			if ( empty( $values ) ) {
				Helpers::log( 'bulk_update_orders: no valid product_ids found for category_id: ' . $category_id, 'warning' );
				return false;
			}

			$values_sql = implode( ',', $values );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$update_result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO %i (product_id, category_id, sort_order, created_at, updated_at)
					VALUES {$values_sql}
					ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = NOW()",
					$table_name
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			if ( false === $update_result ) {
				Helpers::log( 'Database error during bulk update for category_id: ' . $category_id . ', WPDB Error: ' . $wpdb->last_error, 'error' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			// Log error and return false.
			Helpers::log( 'Exception in bulk_update_orders for category_id: ' . $category_id . ', Error: ' . $e->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Get all products in a category with their sort orders
	 *
	 * @param int $category_id Category ID (0 for global).
	 *
	 * @return array Array of product objects with sort_order.
	 */
	public static function get_products_by_category( $category_id = 0 ) {
		global $wpdb;

		$table_name  = self::get_table_name();
		$category_id = absint( $category_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE category_id = %d ORDER BY sort_order ASC',
				$table_name,
				$category_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results ? $results : [];
	}

	/**
	 * Delete all sort orders for a product
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return bool True on success.
	 */
	public static function delete_product_orders( $product_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$product_id = absint( $product_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE product_id = %d',
				$table_name,
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $result;
	}

	/**
	 * Delete sort orders for a product in a specific category
	 *
	 * @param int $product_id  Product ID.
	 * @param int $category_id Category ID.
	 *
	 * @return bool True on success.
	 */
	public static function delete_product_category_order( $product_id, $category_id ) {
		global $wpdb;

		$table_name  = self::get_table_name();
		$product_id  = absint( $product_id );
		$category_id = absint( $category_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE product_id = %d AND category_id = %d',
				$table_name,
				$product_id,
				$category_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $result;
	}

	/**
	 * Check if custom table exists
	 *
	 * @return bool True if table exists.
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_check = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $table_check === $table_name;
	}

	/**
	 * Get migration status
	 *
	 * @return array Migration status information.
	 */
	public static function get_migration_status() {
		global $wpdb;

		return [
			'table_exists'          => self::table_exists(),
			'total_global_orders'   => self::count_global_orders(),
			'total_category_orders' => self::count_category_orders(),
			'db_version'            => Helpers::get_db_version(),
		];
	}

	/**
	 * Count total global sort orders
	 *
	 * @return int Count of global orders.
	 */
	private static function count_global_orders() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE category_id = 0', $table_name )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $count;
	}

	/**
	 * Count total category-specific sort orders
	 *
	 * @return int Count of category orders.
	 */
	private static function count_category_orders() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE category_id > 0', $table_name )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $count;
	}

	/**
	 * Get the most recent update timestamp for a category's sort order
	 *
	 * Used for cache-busting WooCommerce shortcode queries.
	 *
	 * @param int $category_id Category ID (0 for global).
	 *
	 * @return string Unix timestamp of last update or current time if not found.
	 */
	public static function get_last_update_time( $category_id = 0 ) {
		global $wpdb;

		$table_name  = self::get_table_name();
		$category_id = absint( $category_id );

		if ( ! self::table_exists() ) {
			return time();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM %i WHERE category_id = %d',
				$table_name,
				$category_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result ? $result : time();
	}

	/**
	 * Create or update the presets table using dbDelta.
	 *
	 * This method uses dbDelta which intelligently handles:
	 * - Creating the table if it doesn't exist
	 * - Updating the table structure if schema changes
	 * - Preserving existing data during updates
	 * - Not dropping the table if structure is already correct
	 *
	 * @return void
	 */
	public static function create_presets_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'rwpp_sort_presets';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			preset_name varchar(100) NOT NULL,
			description text DEFAULT NULL,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_count int(11) NOT NULL DEFAULT 0,
			preset_data longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category_id (category_id),
			KEY created_at (created_at),
			KEY preset_name (preset_name(50))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// dbDelta will:
		// - Create table if it doesn't exist.
		// - Modify table structure if schema changed.
		// - Do nothing if table exists with correct structure.
		// - Preserve all existing data.
		dbDelta( $sql );
	}

	/**
	 * Drop the presets table.
	 *
	 * WARNING: This will permanently delete all preset data.
	 * Only call this during plugin uninstallation.
	 *
	 * @return void
	 */
	public static function drop_presets_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rwpp_sort_presets';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	/**
	 * Check if the presets table exists.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public static function presets_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rwpp_sort_presets';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $result === $table_name;
	}
}
