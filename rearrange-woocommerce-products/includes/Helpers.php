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
			$message = print_r( $message, true );
		}

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
}
