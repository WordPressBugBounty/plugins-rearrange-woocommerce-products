<?php
/**
 * Main Plugin Class
 *
 * @package ReWooProducts
 */

namespace ReWooProducts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class Plugin {

	/**
	 * Current category ID for frontend queries
	 *
	 * @var int
	 */
	private $current_category_id = 0;

	/**
	 * Flag to track when plugin is applying sort to main query.
	 *
	 * @var bool
	 */
	private $rwpp_applying_main_query_sort = false;

	/**
	 * Setup plugin on initializing class object
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup Hooks
	 */
	public function setup_actions() {
		// Activation/Deactivation hooks
		register_activation_hook( RWPP_BASENAME, [ $this, 'activate' ] );
		register_deactivation_hook( RWPP_BASENAME, [ $this, 'deactivate' ] );

		// Global hooks (run on both admin and frontend)
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'check_database_version' ], 5 );
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

		// AJAX handlers (must be registered before admin_init for AJAX to work)
		add_action( 'wp_ajax_save_all_order', [ $this, 'save_all_order_handler' ] );
		add_action( 'wp_ajax_save_all_order_by_category', [ $this, 'save_all_order_by_category_handler' ] );
		add_action( 'wp_ajax_load_more_products', [ $this, 'load_more_products_handler' ] );
		add_action( 'wp_ajax_rwpp_run_remigration', [ $this, 'run_remigration_handler' ] );

		// Admin-only hooks (conditionally loaded)
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'check_required_plugin' ] );
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
			add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
			add_filter( 'product_cat_row_actions', [ $this, 'add_rearrange_link' ], 10, 2 );
			add_action( 'save_post_product', [ $this, 'new_product_added' ], 10, 3 );
			add_filter( 'plugin_action_links_' . plugin_basename( RWPP_BASENAME ), [ $this, 'add_settings_link_under_plugins_page' ] );
			add_action( 'admin_head', [ $this, 'remove_admin_footer' ] );
		}

		// Frontend hooks (product sorting)
		add_action( 'pre_get_posts', [ $this, 'sort_products_by_category' ], 999 );
		add_filter( 'woocommerce_shortcode_products_query', [ $this, 'modify_product_category_shortcode_query' ], 10, 2 );
	}

	/**
	 * Activate plugin callback
	 *
	 * Creates the custom table and runs migration if needed.
	 *
	 * @return void
	 */
	public static function activate() {
		// Create custom table for product orders
		Database::create_table();

		// Run migration if this is an upgrade
		self::maybe_migrate_data();
	}

	/**
	 * Check database version and run migrations if needed
	 *
	 * This runs on plugins_loaded to ensure all dependencies are available.
	 *
	 * @return void
	 */
	public function check_database_version() {
		$current_db_version = Helpers::get_db_version();
		$required_db_version = '1.0.0'; // Version for custom table implementation

		// If database version is less than required, run migration
		if ( version_compare( $current_db_version, $required_db_version, '<' ) ) {
			// Create table
			Database::create_table();

			// Run migration
			$migration_result = Database::migrate_data();

			// Update database version
			if ( $migration_result['success'] ) {
				Helpers::update_db_version( $required_db_version );
				Helpers::log( 'Migration completed successfully', 'info' );
			} else {
				Helpers::log( 'Migration failed: ' . wp_json_encode( $migration_result['errors'] ), 'error' );
			}
		}
	}

	/**
	 * Run migration on plugin activation
	 *
	 * @return void
	 */
	private static function maybe_migrate_data() {
		$current_db_version = Helpers::get_db_version();

		// Only migrate if version is 0.0.0 (fresh install)
		if ( '0.0.0' !== $current_db_version ) {
			return;
		}

		// Create table and migrate
		Database::create_table();
		$migration_result = Database::migrate_data();

		if ( $migration_result['success'] ) {
			Helpers::update_db_version( '1.0.0' );
		}
	}

	/**
	 * Deactivate plugin callback
	 *
	 * This method is intentionally empty as no cleanup is needed on deactivation.
	 * Product sorting data is preserved to allow reactivation without data loss.
	 * Data is only removed on uninstall via uninstall.php.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// No deactivation tasks required.
	}

	/**
	 * Load plugin text domain for translation purpose
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// WordPress.org automatically loads translations for plugins hosted on their platform.
		// Manual load_plugin_textdomain() call is not needed.
	}

	/**
	 * Declare HPOS compatibility for the plugin
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RWPP_BASENAME, true );
		}
	}

	/**
	 * Add "Rearrange" link under plugins list
	 *
	 * @param array $actions Array of actions.
	 * @return array
	 */
	public function add_settings_link_under_plugins_page( $actions ) {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=rwpp-page' ) . '">' . esc_html__( 'Rearrange Products', 'rearrange-woocommerce-products' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=rwpp-sortby-categories-page' ) . '">' . esc_html__( 'Sort by Categories', 'rearrange-woocommerce-products' ) . '</a>',
		];
		$actions      = array_merge( $plugin_links, $actions );
		return $actions;
	}

	/**
	 * Enqueue CSS and JS files
	 *
	 * @param string $hook Standard WordPress hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! isset( $_REQUEST['page'] ) ) {
			return;
		}

		$pagenow = sanitize_text_field( $_REQUEST['page'] );

		if ( 'rwpp-page' !== $pagenow && 'rwpp-sortby-categories-page' !== $pagenow && 'rwpp-settings-page' !== $pagenow && 'rwpp-troubleshooting-page' !== $pagenow ) {
			return;
		}

		// Load asset file for dependencies and version.
		$asset_file = include RWPP_LOCATION . '/build/main.asset.php';

		// Stylesheets.
		wp_register_style( 'rwpp_css', RWPP_LOCATION_URL . '/build/main.css', [], $asset_file['version'] );
		wp_enqueue_style( 'rwpp_css' );

		// Javascripts.
		wp_register_script( 'rwpp_js', RWPP_LOCATION_URL . '/build/main.js', array_merge( $asset_file['dependencies'], [ 'jquery', 'jquery-ui-sortable' ] ), $asset_file['version'], true );
		wp_localize_script(
			'rwpp_js',
			'rwpp_ajax_var',
			[
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rwpp-ajax-nonce' ),
				'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			]
		);
		wp_enqueue_script( 'rwpp_js' );
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'rwpp-settings-group',
			'rwpp_effected_loops',
			[
				'sanitize_callback' => [ $this, 'sanitize_setting' ],
			]
		);
	}

	/**
	 * Sanitize plugin settings
	 *
	 * @param mixed $value Setting value to sanitize.
	 * @return bool Sanitized boolean value.
	 */
	public function sanitize_setting( $value ) {
		return wp_validate_boolean( $value );
	}

	/**
	 * Check if required plugin is available
	 */
	public function check_required_plugin() {
		// check if woocommerce is installed.
		if ( is_admin() && current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'plugin_notice' ] );

			deactivate_plugins( RWPP_BASENAME );

			if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
	}

	/**
	 * Show plugin activation notice
	 */
	public function plugin_notice() {
		?> <div class="error"><p> <?php esc_html_e( 'Please activate Woocommerce plugin before using', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Rearrange Woocommerce Products', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'plugin', 'rearrange-woocommerce-products' ); ?>.</p></div>
		<?php
	}

	/**
	 * Register Admin Menus
	 */
	public function register_admin_menus() {
		if ( $this->has_required_permissions() ) {
			$user = wp_get_current_user();
			$role = (array) $user->roles;
			if ( in_array( 'administrator', $role, true ) ) {
				$this->add_pages( 'manage_options' );
			} elseif ( in_array( 'shop_manager', $role, true ) ) {
				$this->add_pages( 'shop_manager' );
			} elseif ( current_user_can( 'manage_woocommerce' ) ) {
				$this->add_pages( 'manage_woocommerce' );
			}
		}
	}

	/**
	 * Add page to admin menu
	 *
	 * @param string $role Current User role.
	 */
	public function add_pages( $role ) {
		add_menu_page(
			__( 'Rearrange Products', 'rearrange-woocommerce-products' ),
			__( 'Rearrange Products', 'rearrange-woocommerce-products' ),
			$role,
			'rwpp-page',
			[ $this, 'add_pages_callback' ],
			'dashicons-screenoptions',
			'55.5'
		);

		add_submenu_page(
			'rwpp-page',
			__( 'Sort by Categories', 'rearrange-woocommerce-products' ),
			__( 'Sort by Categories', 'rearrange-woocommerce-products' ),
			$role,
			'rwpp-sortby-categories-page',
			[ $this, 'add_pages_callback' ]
		);

		add_submenu_page(
			'rwpp-page',
			__( 'Settings', 'rearrange-woocommerce-products' ),
			__( 'Settings', 'rearrange-woocommerce-products' ),
			$role,
			'rwpp-settings-page',
			[ $this, 'add_pages_callback' ]
		);

		add_submenu_page(
			'rwpp-page',
			__( 'Troubleshooting', 'rearrange-woocommerce-products' ),
			__( 'Troubleshooting', 'rearrange-woocommerce-products' ),
			$role,
			'rwpp-troubleshooting-page',
			[ $this, 'add_pages_callback' ]
		);
	}

	/**
	 * Callback to add_page
	 */
	public function add_pages_callback() {
		include RWPP_LOCATION . '/views/rearrange-all-products.php';
	}

	/**
	 * Save All Products sort order
	 */
	public function save_all_order_handler() {
		try {
			// Increase execution time for large product sets
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 300 ); // 5 minutes
			}

			if ( ! $this->has_required_permissions() ) {
				Helpers::log( 'Unauthorized AJAX request to save_all_order_handler', 'warning' );
				throw new \Exception( 'Insufficient permissions' );
			}

			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
				Helpers::log( 'Invalid nonce in save_all_order_handler request', 'warning' );
				throw new \Exception( 'Invalid security token' );
			}

			if ( ! isset( $_POST['sort_orders'] ) ) {
				Helpers::log( 'Missing sort_orders data in save_all_order_handler request', 'warning' );
				throw new \Exception( 'Missing sort orders data' );
			}

			$sort_orders = $this->clear_sort_orders( $_POST['sort_orders'] ); // phpcs:ignore

			if ( ! is_array( $sort_orders ) || count( $sort_orders ) === 0 ) {
				Helpers::log( 'Empty sort_orders array in save_all_order_handler', 'warning' );
				throw new \Exception( 'No products to save' );
			}

			// Check if this is a chunked request by looking for chunk markers
			$is_chunk = isset( $_POST['is_chunk'] ) && wp_validate_boolean( $_POST['is_chunk'] ); // phpcs:ignore
			$is_last_chunk = isset( $_POST['is_last_chunk'] ) && wp_validate_boolean( $_POST['is_last_chunk'] ); // phpcs:ignore

			$result = Database::bulk_update_orders( $sort_orders, 0 );

			if ( ! $result ) {
				Helpers::log( 'Database error in bulk_update_orders for global sorting', 'error' );
				throw new \Exception( 'Failed to update product orders in database' );
			}

			// Only update legacy data and show success on the last chunk
			if ( $is_last_chunk || ! $is_chunk ) {
				// Also maintain menu_order for backwards compatibility
				$this->legacy_update_menu_order( $sort_orders );

				echo '<div class="notice notice-success is-dismissible">
				<p><strong>' . esc_html( __( 'All products are rearranged now.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
				</div>';
			}
		} catch ( \Exception $e ) {
			Helpers::log( 'Exception in save_all_order_handler: ' . $e->getMessage(), 'error' );
			echo '<div class="notice notice-error is-dismissible">
			<p><strong>' . esc_html( __( 'An error occurred while rearranging products.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
			</div>';
		}

		wp_die();
	}

	/**
	 * Additional security to escape sort order data
	 *
	 * @param array $sort_orders Sortorders to update.
	 */
	public function clear_sort_orders( $sort_orders ) {
		if ( isset( $sort_orders ) ) {
			$keys = array_keys( $sort_orders );
			if ( array_filter( $keys, 'is_numeric' ) === $keys ) {
				$sort_orders = array_combine(
					array_map( 'intval', $keys ),
					array_values( $sort_orders )
				);
			}
		}

		$sort_orders = isset( $sort_orders ) ? array_map( 'sanitize_text_field', wp_unslash( $sort_orders ) ) : [];
		$sort_orders = isset( $sort_orders ) ? array_map( 'esc_attr', wp_unslash( $sort_orders ) ) : [];
		$sort_orders = array_filter( $sort_orders, 'is_numeric' );

		return $sort_orders;
	}

	/**
	 * Save sort order by category
	 */
	public function save_all_order_by_category_handler() {
		try {
			// Increase execution time for large product sets
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 300 ); // 5 minutes
			}

			if ( ! $this->has_required_permissions() ) {
				Helpers::log( 'Unauthorized AJAX request to save_all_order_by_category_handler', 'warning' );
				throw new \Exception( 'Insufficient permissions' );
			}

			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
				Helpers::log( 'Invalid nonce in save_all_order_by_category_handler request', 'warning' );
				throw new \Exception( 'Invalid security token' );
			}

			if ( ! isset( $_POST['sort_orders'] ) || ! isset( $_POST['term_id'] ) ) {
				Helpers::log( 'Missing sort_orders or term_id data in save_all_order_by_category_handler request', 'warning' );
				throw new \Exception( 'Missing required data' );
			}

			$sort_orders = $this->clear_sort_orders( $_POST['sort_orders'] ); // phpcs:ignore
			$term_id = absint( $_POST['term_id'] ); // phpcs:ignore

			if ( ! is_array( $sort_orders ) || count( $sort_orders ) === 0 ) {
				Helpers::log( 'Empty sort_orders array in save_all_order_by_category_handler for term_id: ' . $term_id, 'warning' );
				throw new \Exception( 'No products to save' );
			}

			if ( $term_id <= 0 ) {
				Helpers::log( 'Invalid term_id: ' . $term_id . ' in save_all_order_by_category_handler', 'warning' );
				throw new \Exception( 'Invalid category' );
			}

			// Check if this is a chunked request by looking for chunk markers
			$is_chunk = isset( $_POST['is_chunk'] ) && wp_validate_boolean( $_POST['is_chunk'] ); // phpcs:ignore
			$is_last_chunk = isset( $_POST['is_last_chunk'] ) && wp_validate_boolean( $_POST['is_last_chunk'] ); // phpcs:ignore

			$result = Database::bulk_update_orders( $sort_orders, $term_id );

			if ( ! $result ) {
				Helpers::log( 'Database error in bulk_update_orders for category sorting, term_id: ' . $term_id, 'error' );
				throw new \Exception( 'Failed to update product orders in database' );
			}

			// Only update legacy data and show success on the last chunk
			if ( $is_last_chunk || ! $is_chunk ) {
				// Also maintain postmeta for backwards compatibility
				$this->legacy_update_postmeta( $sort_orders, $term_id );

				echo '<div class="notice notice-success is-dismissible">
				<p><strong>' . esc_html( __( 'All products are rearranged now.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
				</div>';
			}
		} catch ( \Exception $e ) {
			Helpers::log( 'Exception in save_all_order_by_category_handler: ' . $e->getMessage(), 'error' );
			echo '<div class="notice notice-error is-dismissible">
			<p><strong>' . esc_html( __( 'An error occurred while rearranging products.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
			</div>';
		}
		wp_die();
	}

	/**
	 * Add "Rearrange" link on Product categories under admin
	 *
	 * @param array  $actions Actions.
	 * @param object $term Term object.
	 */
	public function add_rearrange_link( $actions, $term ) {
		$url                       = admin_url( 'admin.php?page=rwpp-sortby-categories-page&term_id=' . $term->term_id );
		$actions['rearrange_link'] = '<a href="' . $url . '" class="rearrange_link">' . __( 'Rearrange Products', 'rearrange-woocommerce-products' ) . '</a>';
		return $actions;
	}

	/**
	 * Modify query for woocommerce product category shortcode.
	 *
	 * @param array $query_args Query args.
	 * @param array $attributes Attributes.
	 * @return array Query args.
	 */
	public function modify_product_category_shortcode_query( $query_args, $attributes ) {
		// Check if the product_cat taxonomy query is being used.
		if ( get_option( 'rwpp_effected_loops' ) && isset( $query_args['tax_query'] ) && isset( $attributes['category'] ) ) {
			// Handle multiple comma-separated categories by using the first one for sorting.
			$category_slug = $attributes['category'];
			if ( strpos( $category_slug, ',' ) !== false ) {
				$categories    = array_map( 'trim', explode( ',', $category_slug ) );
				$category_slug = $categories[0];
			}

			$term = get_term_by( 'slug', $category_slug, 'product_cat' );
			if ( $term ) {
				$term_id = $term->term_id;

				if ( $term_id ) {
					// Store for use in filter callbacks.
					$this->current_category_id = $term_id;

					// Add filters for table join and ordering.
					add_filter( 'posts_join', [ $this, 'join_product_order_table' ], 10, 2 );
					add_filter( 'posts_orderby', [ $this, 'orderby_product_order' ], 10, 2 );

					// Hook to remove filters after query runs.
					add_action( 'posts_selection', [ $this, 'remove_shortcode_sorting_filters' ] );

					// Force orderby to prevent WooCommerce from overriding our custom sort.
					// Setting to 'none' lets our posts_orderby filter take full control.
					$query_args['orderby'] = 'none';
					$query_args['order']   = 'ASC';
				}
			}
		}

		return $query_args;
	}

	/**
	 * Remove shortcode sorting filters after query runs.
	 *
	 * This prevents the plugin's sorting from affecting subsequent queries.
	 *
	 * @return void
	 */
	public function remove_shortcode_sorting_filters() {
		remove_filter( 'posts_join', [ $this, 'join_product_order_table' ], 10 );
		remove_filter( 'posts_orderby', [ $this, 'orderby_product_order' ], 10 );
		remove_action( 'posts_selection', [ $this, 'remove_shortcode_sorting_filters' ] );
	}

	/**
	 * Modify Products loop query to sort by categories
	 *
	 * @param object $query WP_Query variable.
	 */
	public function sort_products_by_category( $query ) {
		// Only target the front-end main query.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( isset( $_GET['orderby'] ) && 'date' === $_GET['orderby'] ) {
			return;
		}

		if ( get_option( 'rwpp_effected_loops' ) ) {
			$checker = ! is_admin();
		} else {
			$checker = is_tax( 'product_cat' ) && $query->is_main_query() && ! is_admin();
		}

		if ( $checker ) {
			$term = get_queried_object();
			if ( $term && is_a( $term, 'WP_Term' ) ) {
				$term_id = $term->term_id;
				if ( $term_id ) {
					// Store category ID for use in filter callbacks.
					$this->current_category_id = $term_id;

					// Set flag to indicate we're applying sort to main query.
					$this->rwpp_applying_main_query_sort = true;

					// Add filters for table join and ordering.
					add_filter( 'posts_join', [ $this, 'join_product_order_table' ], 10, 2 );
					add_filter( 'posts_orderby', [ $this, 'orderby_product_order' ], 10, 2 );

					// Remove filters after posts are selected to prevent affecting secondary queries.
					add_action( 'posts_selection', [ $this, 'remove_sorting_filters' ] );
				}
			}
		}
	}

	/**
	 * Remove sorting filters after main query posts are selected.
	 *
	 * This prevents the plugin's sorting from affecting secondary queries
	 * like widgets, shortcodes, or custom product blocks.
	 *
	 * @return void
	 */
	public function remove_sorting_filters() {
		// Only remove if we actually added the filters.
		if ( isset( $this->rwpp_applying_main_query_sort ) && $this->rwpp_applying_main_query_sort ) {
			remove_filter( 'posts_join', [ $this, 'join_product_order_table' ], 10 );
			remove_filter( 'posts_orderby', [ $this, 'orderby_product_order' ], 10 );
			remove_action( 'posts_selection', [ $this, 'remove_sorting_filters' ] );

			// Reset the flag.
			$this->rwpp_applying_main_query_sort = false;
		}
	}

	/**
	 * Join with custom product order table
	 *
	 * @param string $join JOIN clause.
	 * @param object $query WP_Query object.
	 * @return string Modified JOIN clause.
	 */
	public function join_product_order_table( $join, $query ) {
		global $wpdb;

		if ( isset( $this->current_category_id ) && $this->current_category_id > 0 ) {
			$table_name = Database::get_table_name();
			$category_id = absint( $this->current_category_id );
			$meta_key = 'rwpp_sortorder_' . $category_id;

			$join .= " LEFT JOIN {$table_name} AS rwpp_order
					   ON {$wpdb->posts}.ID = rwpp_order.product_id
					   AND rwpp_order.category_id = {$category_id}";

			// Postmeta fallback for failed v5.0.2 migrations.
			$join .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} AS rwpp_meta
				   ON {$wpdb->posts}.ID = rwpp_meta.post_id
				   AND rwpp_meta.meta_key = %s",
				$meta_key
			);
		}

		return $join;
	}

	/**
	 * Set ORDER BY clause for product sorting
	 *
	 * @param string $orderby ORDER BY clause.
	 * @param object $query WP_Query object.
	 * @return string Modified ORDER BY clause.
	 */
	public function orderby_product_order( $orderby, $query ) {
		global $wpdb;

		if ( isset( $this->current_category_id ) && $this->current_category_id > 0 ) {
			// Fallback chain: custom_table -> postmeta (legacy) -> menu_order -> unsorted.
			$orderby = "COALESCE(rwpp_order.sort_order, CAST(rwpp_meta.meta_value AS UNSIGNED), {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
		}

		return $orderby;
	}

	/**
	 * Update products meta
	 *
	 * @param int $term_id Term ID.
	 */
	public function update_products_meta( $term_id ) {
		global $post;
		$products = new \WP_Query(
			[
				'post_type'      => [ 'product' ],
				'posts_per_page' => '-1',
				'post_status'    => [ 'publish' ],
				'tax_query'      => [ // phpcs:ignore
					[
						'taxonomy' => 'product_cat',
						'terms'    => [ $term_id ],
						'field'    => 'id',
						'operator' => 'IN',
					],
				],
			]
		);

		if ( $products->have_posts() ) :
			while ( $products->have_posts() ) :
				$products->the_post();
				$meta_key   = 'rwpp_sortorder_' . $term_id;
				$menu_order = $post->menu_order;
				$sort_order = get_post_meta( $post->ID, $meta_key );
				if ( ! $sort_order ) {
					update_post_meta( $post->ID, $meta_key, $menu_order );
				}
			endwhile;
		endif;

		wp_reset_postdata();
	}

	/**
	 * When new product created, add sort order to custom table
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post Object.
	 * @param bool   $update Update.
	 */
	public function new_product_added( $post_id, $post, $update ) {
		$terms = wp_get_post_terms( $post_id, 'product_cat' );

		// Get current menu_order to use as default sort order.
		$menu_order = isset( $post->menu_order ) ? absint( $post->menu_order ) : 0;

		// Add product to custom table for each category (only if it doesn't already have an entry).
		if ( $terms ) {
			foreach ( $terms as $term ) {
				// Only add if this product doesn't already have a sort order for this category.
				// get_sort_order() returns null when no entry exists, 0+ when entry exists.
				$existing_order = Database::get_sort_order( $post_id, $term->term_id );
				if ( null === $existing_order ) {
					Database::set_sort_order( $post_id, $term->term_id, $menu_order );
				}
			}
		}

		// Also add global sort order (only if it doesn't already have an entry).
		$existing_global_order = Database::get_sort_order( $post_id, 0 );
		if ( null === $existing_global_order ) {
			Database::set_sort_order( $post_id, 0, $menu_order );
		}

		// Maintain postmeta for backwards compatibility.
		if ( $terms ) {
			foreach ( $terms as $term ) {
				if ( ! metadata_exists( 'post', $post_id, 'rwpp_sortorder_' . $term->term_id ) ) {
					update_post_meta( $post_id, 'rwpp_sortorder_' . $term->term_id, $menu_order );
				}
			}
		}
	}

	/**
	 * Update menu_order in wp_posts for backwards compatibility
	 *
	 * @param array $sort_orders Sort orders array.
	 */
	private function legacy_update_menu_order( $sort_orders ) {
		global $wpdb;

		if ( empty( $sort_orders ) ) {
			return;
		}

		$sql_query = "UPDATE {$wpdb->prefix}posts SET menu_order = ( CASE ";
		$fields_in = '';

		foreach ( $sort_orders as $new_sort_order => $product_id ) {
			$sql_query .= "WHEN ID = '" . intval( $product_id ) . "' AND post_type='product' THEN '" . esc_sql( $new_sort_order ) . "' ";
			$fields_in .= intval( $product_id ) . ',';
		}

		$fields_in = rtrim( $fields_in, ',' );
		$sql_query .= 'ELSE NULL END ) ';
		$sql_query .= "WHERE ID IN ($fields_in) ";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql_query );
	}

	/**
	 * Update postmeta for category sorting for backwards compatibility
	 *
	 * @param array $sort_orders Sort orders array.
	 * @param int   $term_id Category term ID.
	 */
	private function legacy_update_postmeta( $sort_orders, $term_id ) {
		if ( empty( $sort_orders ) ) {
			return;
		}

		foreach ( $sort_orders as $new_sort_order => $product_id ) {
			$meta_key   = 'rwpp_sortorder_' . $term_id;
			$meta_value = $new_sort_order;
			update_post_meta( $product_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Check if meta data exists for specific term in post_meta table
	 *
	 * @param string $meta_key Meta key.
	 */
	public function meta_field_exists( $meta_key ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key='$meta_key'" );
		if ( $result ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if user have permissions
	 */
	public function has_required_permissions() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$role = (array) $user->roles;
			if ( in_array( 'administrator', $role, true ) || in_array( 'shop_manager', $role, true ) || current_user_can( 'manage_woocommerce' ) ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Remove WordPress default admin footer
	 *
	 * @return void
	 */
	public function remove_admin_footer() {
		echo '<style>
			#wpfooter {
				display: none !important;
			}
		</style>';
	}

	/**
	 * Load more products via AJAX
	 *
	 * Handles infinite scroll pagination for products list
	 */
	public function load_more_products_handler() {
		try {
			// Increase execution time for pagination queries
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 60 );
			}

			// Security validation
			if ( ! $this->has_required_permissions() ) {
				Helpers::log( 'Unauthorized AJAX request to load_more_products_handler', 'warning' );
				throw new \Exception( 'Insufficient permissions' );
			}

			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
				Helpers::log( 'Invalid nonce in load_more_products_handler request', 'warning' );
				throw new \Exception( 'Invalid security token' );
			}

			// Get parameters
			$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1; // phpcs:ignore
			$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 100; // phpcs:ignore
			$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0; // phpcs:ignore

			if ( $page < 1 ) {
				$page = 1;
			}

			if ( $per_page < 1 || $per_page > 200 ) {
				$per_page = 100; // Max 200 per request to prevent abuse
			}

			// Sanitize term_id
			$term_id = max( 0, $term_id );

			// Build query with custom table JOIN for sorting
			$current_term_id = $term_id;

			$join_callback = function( $join ) use ( &$current_term_id ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'rwpp_product_order';
				$category_id = absint( $current_term_id );
				$meta_key = 'rwpp_sortorder_' . $category_id;

				$join .= " LEFT JOIN {$table_name} AS rwpp_order
						   ON {$wpdb->posts}.ID = rwpp_order.product_id
						   AND rwpp_order.category_id = {$category_id}";

				// Postmeta fallback for failed v5.0.2 migrations.
				if ( $category_id > 0 ) {
					$join .= $wpdb->prepare(
						" LEFT JOIN {$wpdb->postmeta} AS rwpp_meta
						   ON {$wpdb->posts}.ID = rwpp_meta.post_id
						   AND rwpp_meta.meta_key = %s",
						$meta_key
					);
				}

				return $join;
			};

			$orderby_callback = function( $orderby ) use ( &$current_term_id ) {
				global $wpdb;
				if ( absint( $current_term_id ) > 0 ) {
					return "COALESCE(rwpp_order.sort_order, CAST(rwpp_meta.meta_value AS UNSIGNED), {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
				}
				return "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
			};

			$args = array(
				'post_type'      => array( 'product' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => array( 'publish' ),
			);

			// Add category filter if specified
			if ( $term_id > 0 ) {
				$args['tax_query'] = array( // phpcs:ignore
					array(
						'taxonomy'         => 'product_cat',
						'terms'            => array( $term_id ),
						'field'            => 'id',
						'operator'         => 'IN',
						'include_children' => true,
					),
				);
			}

			// Apply filters for custom table JOIN
			add_filter( 'posts_join', $join_callback, 10, 1 );
			add_filter( 'posts_orderby', $orderby_callback, 10, 1 );

			// Execute query
			$products = new \WP_Query( $args );

			// Clean up filters
			remove_filter( 'posts_join', $join_callback, 10 );
			remove_filter( 'posts_orderby', $orderby_callback, 10 );

			// Build HTML for products
			ob_start();

			$serial_no = ( ( $page - 1 ) * $per_page ) + 1;

			if ( $products->have_posts() ) {
				while ( $products->have_posts() ) {
					$products->the_post();
					global $post;
					$product = wc_get_product( $post->ID ); // output escaped via WooCommerce.
					include RWPP_LOCATION . '/views/template-parts/product.php';
					$serial_no++;
				}
			}

			$products_html = ob_get_clean();
			wp_reset_postdata();

			// Determine if there are more pages
			$has_more = $products->max_num_pages > $page;
			$loaded_count = min( $page * $per_page, $products->found_posts );

			// Return JSON response
			wp_send_json_success(
				array(
					'products_html' => $products_html,
					'has_more'      => $has_more,
					'total'         => $products->found_posts,
					'loaded'        => $loaded_count,
					'current_page'  => $page,
				)
			);

		} catch ( \Exception $e ) {
			Helpers::log( 'Exception in load_more_products_handler: ' . $e->getMessage(), 'error' );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to load products.', 'rearrange-woocommerce-products' ),
				)
			);
		}
		die(); // Ensure we always exit after AJAX handler
	}

	/**
	 * AJAX handler for re-running migration from Troubleshooting page
	 */
	public function run_remigration_handler() {
		if ( ! $this->has_required_permissions() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'rearrange-woocommerce-products' ) ] );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'rearrange-woocommerce-products' ) ] );
		}

		$result = Database::run_remigration();

		if ( $result['success'] ) {
			wp_send_json_success(
				[
					'message'           => __( 'Migration completed successfully.', 'rearrange-woocommerce-products' ),
					'global_migrated'   => $result['global_migrated'],
					'category_migrated' => $result['category_migrated'],
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Migration failed.', 'rearrange-woocommerce-products' ),
					'errors'  => $result['errors'],
				]
			);
		}
	}
}
