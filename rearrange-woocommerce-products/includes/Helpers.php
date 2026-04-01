<?php
/**
 * Helper Functions Class
 *
 * @package ReWooProducts
 */

namespace ReWooProducts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for common utility functions
 */
class Helpers {

	/**
	 * Get plugin version
	 *
	 * @return string Plugin version.
	 */
	public static function get_version() {
		$plugin_data = get_file_data(
			RWPP_LOCATION . '/rearrange-woocommerce-products.php',
			[ 'Version' => 'Version' ]
		);
		return $plugin_data['Version'] ?? '1.0.0';
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool True if WooCommerce is active.
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get product categories
	 *
	 * @param array $args Optional. Arguments for get_terms.
	 * @return array Array of product category terms.
	 */
	public static function get_product_categories( $args = [] ) {
		$defaults = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		];

		$args = wp_parse_args( $args, $defaults );

		return get_terms( $args );
	}

	/**
	 * Sanitize and validate sort orders
	 *
	 * @param array $sort_orders Sort orders array.
	 * @return array Sanitized sort orders.
	 */
	public static function sanitize_sort_orders( $sort_orders ) {
		if ( ! is_array( $sort_orders ) ) {
			return [];
		}

		// Ensure numeric keys.
		$keys = array_keys( $sort_orders );
		if ( array_filter( $keys, 'is_numeric' ) === $keys ) {
			$sort_orders = array_combine(
				array_map( 'intval', $keys ),
				array_values( $sort_orders )
			);
		}

		// Sanitize values.
		$sort_orders = array_map( 'sanitize_text_field', wp_unslash( $sort_orders ) );
		$sort_orders = array_map( 'esc_attr', wp_unslash( $sort_orders ) );
		$sort_orders = array_filter( $sort_orders, 'is_numeric' );

		return $sort_orders;
	}

	/**
	 * Get meta key for sort order by category
	 *
	 * @param int $term_id Category term ID.
	 * @return string Meta key.
	 */
	public static function get_sort_meta_key( $term_id ) {
		return 'rwpp_sortorder_' . absint( $term_id );
	}

	/**
	 * Check if user has required permissions
	 *
	 * @return bool True if user has permissions.
	 */
	public static function user_has_permission() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		$role = (array) $user->roles;

		return in_array( 'administrator', $role, true )
			|| in_array( 'shop_manager', $role, true )
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is a WooCommerce capability.
			|| current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Log debug message if WP_DEBUG is enabled
	 *
	 * @param mixed  $message Message to log.
	 * @param string $level   Log level (error, warning, info).
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$prefix = '[RWPP ' . strtoupper( $level ) . '] ';

		if ( is_array( $message ) || is_object( $message ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$message = print_r( $message, true );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $prefix . $message );
	}

	/**
	 * Get admin page URL
	 *
	 * @param string $page    Page slug.
	 * @param array  $args    Optional. Additional query args.
	 * @return string Admin page URL.
	 */
	public static function get_admin_url( $page = 'rwpp-page', $args = [] ) {
		$base_args = [ 'page' => $page ];
		$args      = array_merge( $base_args, $args );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render a template file
	 *
	 * @param string $template_name Template file name (without .php).
	 * @param array  $args          Optional. Variables to pass to template.
	 * @return void
	 */
	public static function render_template( $template_name, $args = [] ) {
		$template_path = RWPP_LOCATION . '/views/' . $template_name . '.php';

		if ( ! file_exists( $template_path ) ) {
			self::log( 'Template not found: ' . $template_name, 'error' );
			return;
		}

		// Extract args to variables.
		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		include $template_path;
	}

	/**
	 * Check if current screen is plugin admin page
	 *
	 * @return bool True if on plugin admin page.
	 */
	public static function is_plugin_admin_page() {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$plugin_pages = [
			'toplevel_page_rwpp-page',
			'rearrange-products_page_rwpp-sortby-categories-page',
			'rearrange-products_page_rwpp-settings-page',
			'rearrange-products_page_rwpp-troubleshooting-page',
		];

		return in_array( $screen->id, $plugin_pages, true );
	}

	/**
	 * Get database version from WordPress options
	 *
	 * @return string Database version or '0.0.0' if not set.
	 */
	public static function get_db_version() {
		return get_option( 'rwpp_db_version', '0.0.0' );
	}

	/**
	 * Update database version in WordPress options
	 *
	 * @param string $version Version string.
	 * @return bool True on success.
	 */
	public static function update_db_version( $version ) {
		return update_option( 'rwpp_db_version', $version );
	}

	/**
	 * Get migration mode status
	 *
	 * @return string Migration mode status.
	 */
	public static function get_migration_mode() {
		return get_option( 'rwpp_migration_mode', 'pending' );
	}

	/**
	 * Set migration mode status
	 *
	 * @param string $mode Migration mode status.
	 * @return bool True on success.
	 */
	public static function set_migration_mode( $mode ) {
		return update_option( 'rwpp_migration_mode', $mode );
	}

	/**
	 * Get system status / diagnostic info
	 *
	 * @return array Grouped diagnostic data.
	 */
	public static function get_system_status() {
		global $wpdb;

		$status = [];

		// Environment.
		$status['Environment'] = [
			'WordPress Version'   => get_bloginfo( 'version' ),
			'WooCommerce Version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
			'PHP Version'         => phpversion(),
			'PHP Memory Limit'    => ini_get( 'memory_limit' ),
			'PHP Max Execution'   => ini_get( 'max_execution_time' ) . 's',
			'PHP Max Input Vars'  => ini_get( 'max_input_vars' ),
			'MySQL Version'       => $wpdb->db_version(),
			'Server Software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
			'HTTPS'               => is_ssl() ? 'Yes' : 'No',
			'Multisite'           => is_multisite() ? 'Yes' : 'No',
		];

		// Plugin.
		$license = 'Free';
		if ( function_exists( 'rwpp_fs' ) ) {
			$fs = rwpp_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'is__premium_only' ) && $fs->is__premium_only() ) {
				$license = 'Premium';
			}
		}

		$status['Plugin'] = [
			'Plugin Version' => self::get_version(),
			'DB Version'     => self::get_db_version(),
			'License'        => $license,
		];

		// Database.
		$migration_status = Database::get_migration_status();

		$status['Database'] = [
			'Product Order Table'   => $migration_status['table_exists'] ? 'Exists' : 'Missing',
			'Presets Table'         => Database::presets_table_exists() ? 'Exists' : 'Missing',
			'Global Sort Records'   => (string) $migration_status['total_global_orders'],
			'Category Sort Records' => (string) $migration_status['total_category_orders'],
		];

		// Settings.
		$effected_loops = get_option( 'rwpp_effected_loops' );
		$wc_sorting     = get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

		$published_products = wp_count_posts( 'product' );
		$total_products     = isset( $published_products->publish ) ? $published_products->publish : 0;

		$categories       = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'count',
			]
		);
		$total_categories = is_wp_error( $categories ) ? 0 : $categories;

		$status['Settings'] = [
			'Apply Sorting To'   => empty( $effected_loops ) ? 'Main Loop Only' : 'All Loops',
			'WC Default Sorting' => $wc_sorting,
			'Total Products'     => (string) $total_products,
			'Total Categories'   => (string) $total_categories,
		];

		// Theme.
		$theme    = wp_get_theme();
		$parent   = $theme->parent();
		$is_child = (bool) $parent;

		$status['Theme'] = [
			'Active Theme' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'Child Theme'  => $is_child ? 'Yes' : 'No',
		];
		if ( $is_child ) {
			$status['Theme']['Parent Theme'] = $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' );
		}

		// Active Plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$plugin_list    = [];

		foreach ( $active_plugins as $plugin_path ) {
			if ( isset( $all_plugins[ $plugin_path ] ) ) {
				$name                 = $all_plugins[ $plugin_path ]['Name'];
				$version              = $all_plugins[ $plugin_path ]['Version'];
				$plugin_list[ $name ] = $version;
			}
		}

		$status['Active Plugins'] = $plugin_list;

		return $status;
	}
}
