<?php

/**
 * Main Plugin Class
 *
 * @package ReWooProducts
 */
namespace ReWooProducts;

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
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
     * Compound sort configurations for custom SQL filters
     *
     * @var array
     */
    private $compound_sort_configs = [];

    /**
     * Flag to track when plugin is applying sort to main query.
     *
     * @var bool
     */
    private $rwpp_applying_main_query_sort = false;

    /**
     * Current preset orders for shortcode sorting.
     *
     * @var array
     */
    private $current_preset_orders = [];

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
        // Activation/Deactivation hooks.
        register_activation_hook( RWPP_BASENAME, [$this, 'activate'] );
        register_deactivation_hook( RWPP_BASENAME, [$this, 'deactivate'] );
        // Global hooks (run on both admin and frontend).
        add_action( 'plugins_loaded', [$this, 'load_textdomain'] );
        add_action( 'plugins_loaded', [$this, 'check_database_version'], 5 );
        add_action( 'before_woocommerce_init', [$this, 'declare_hpos_compatibility'] );
        // Populate initial sort order after product post type is registered.
        // Runs on every init but is a no-op if entries already exist in the custom table.
        add_action( 'init', function () {
            Database::populate_initial_global_order();
        }, 20 );
        // AJAX handlers (must be registered before admin_init for AJAX to work).
        add_action( 'wp_ajax_save_all_order', [$this, 'save_all_order_handler'] );
        add_action( 'wp_ajax_save_all_order_by_category', [$this, 'save_all_order_by_category_handler'] );
        add_action( 'wp_ajax_load_more_products', [$this, 'load_more_products_handler'] );
        add_action( 'wp_ajax_rwpp_run_remigration', [$this, 'run_remigration_handler'] );
        // Premium AJAX handlers - Only register if premium methods exist.
        // In free version, these methods are stripped by Freemius build process.
        if ( method_exists( $this, 'smart_sort_products_handler__premium_only' ) ) {
            add_action( 'wp_ajax_smart_sort_products', [$this, 'smart_sort_products_handler__premium_only'] );
        }
        // Sort Presets AJAX handlers (Premium).
        if ( method_exists( $this, 'create_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_create_preset', [$this, 'create_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'get_presets_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_get_presets', [$this, 'get_presets_handler__premium_only'] );
        }
        if ( method_exists( $this, 'get_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_get_preset', [$this, 'get_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'update_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_update_preset', [$this, 'update_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'delete_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_delete_preset', [$this, 'delete_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'apply_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_apply_preset', [$this, 'apply_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'duplicate_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_duplicate_preset', [$this, 'duplicate_preset_handler__premium_only'] );
        }
        if ( method_exists( $this, 'save_current_as_preset_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_save_current_as_preset', [$this, 'save_current_as_preset_handler__premium_only'] );
        }
        // Import/Export AJAX handlers (Premium).
        if ( method_exists( $this, 'export_data_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_export_data', [$this, 'export_data_handler__premium_only'] );
        }
        if ( method_exists( $this, 'import_file_handler__premium_only' ) ) {
            add_action( 'wp_ajax_rwpp_import_file', [$this, 'import_file_handler__premium_only'] );
        }
        // Admin-only hooks (conditionally loaded).
        if ( is_admin() ) {
            add_action( 'admin_init', [$this, 'check_required_plugin'] );
            add_action( 'admin_init', [$this, 'register_settings'] );
            add_action( 'admin_enqueue_scripts', [$this, 'enqueue_assets'] );
            add_action( 'admin_menu', [$this, 'register_admin_menus'] );
            add_filter(
                'product_cat_row_actions',
                [$this, 'add_rearrange_link'],
                10,
                2
            );
            add_action(
                'save_post_product',
                [$this, 'new_product_added'],
                10,
                3
            );
            add_action( 'before_delete_post', [$this, 'cleanup_product_on_delete'] );
            add_action(
                'set_object_terms',
                [$this, 'handle_product_category_change'],
                10,
                6
            );
            add_filter( 'plugin_action_links_' . plugin_basename( RWPP_BASENAME ), [$this, 'add_settings_link_under_plugins_page'] );
            add_action( 'admin_head', [$this, 'remove_admin_footer'] );
        }
        // Frontend hooks (product sorting).
        add_action( 'pre_get_posts', [$this, 'sort_products_by_category'], 999 );
        add_filter(
            'woocommerce_shortcode_products_query',
            [$this, 'modify_product_category_shortcode_query'],
            10,
            2
        );
        // Register custom attribute for products and product_category shortcodes.
        add_filter(
            'shortcode_atts_products',
            [$this, 'add_rwpp_order_shortcode_attribute'],
            10,
            4
        );
        add_filter(
            'shortcode_atts_product_category',
            [$this, 'add_rwpp_order_shortcode_attribute'],
            10,
            4
        );
    }

    /**
     * Add rwpp-order attribute to the products shortcode.
     *
     * This allows our custom attribute to pass through WooCommerce's shortcode processing.
     *
     * @param array  $out       Shortcode attributes.
     * @param array  $pairs     Default attributes.
     * @param array  $atts      User defined attributes.
     * @param string $shortcode Shortcode name.
     * @return array Modified attributes.
     */
    public function add_rwpp_order_shortcode_attribute(
        $out,
        $pairs,
        $atts,
        $shortcode
    ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        // Pass through our custom attribute if it was specified.
        if ( isset( $atts['rwpp-order'] ) ) {
            $out['rwpp-order'] = $atts['rwpp-order'];
        }
        return $out;
    }

    /**
     * Activate plugin callback
     *
     * Creates the custom table and runs migration if needed.
     *
     * @return void
     */
    public static function activate() {
        // Create custom table for product orders.
        Database::create_table();
        // Create presets table only if premium code exists.
        if ( class_exists( 'ReWooProducts\\SortPresets' ) ) {
            Database::create_presets_table();
        }
        // Run migration if this is an upgrade.
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
        $required_db_version = '1.0.0';
        // Version for custom table implementation.
        // If database version is less than required, run migration.
        if ( version_compare( $current_db_version, $required_db_version, '<' ) ) {
            // Create table.
            Database::create_table();
            // Run migration.
            $migration_result = Database::migrate_data();
            // Update database version.
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
        // Only migrate if version is 0.0.0 (fresh install).
        if ( '0.0.0' !== $current_db_version ) {
            return;
        }
        // Create table and migrate.
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
        $plugin_links = ['<a href="' . admin_url( 'admin.php?page=rwpp-page' ) . '">' . esc_html__( 'Rearrange Products', 'rearrange-woocommerce-products' ) . '</a>', '<a href="' . admin_url( 'admin.php?page=rwpp-sortby-categories-page' ) . '">' . esc_html__( 'Sort by Categories', 'rearrange-woocommerce-products' ) . '</a>'];
        $actions = array_merge( $plugin_links, $actions );
        return $actions;
    }

    /**
     * Enqueue CSS and JS files
     *
     * @param string $hook Standard WordPress hook.
     */
    public function enqueue_assets( $hook ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page check, no data modification.
        if ( !isset( $_REQUEST['page'] ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Admin page routing, no data modification.
        $pagenow = sanitize_text_field( $_REQUEST['page'] );
        if ( 'rwpp-page' !== $pagenow && 'rwpp-sortby-categories-page' !== $pagenow && 'rwpp-smart-sort-page' !== $pagenow && 'rwpp-sort-presets-page' !== $pagenow && 'rwpp-import-export-page' !== $pagenow && 'rwpp-settings-page' !== $pagenow && 'rwpp-troubleshooting-page' !== $pagenow ) {
            return;
        }
        // Load asset file for dependencies and version.
        $asset_file = (include RWPP_LOCATION . '/build/main.asset.php');
        // Stylesheets.
        wp_register_style(
            'rwpp_css',
            RWPP_LOCATION_URL . '/build/main.css',
            [],
            $asset_file['version']
        );
        wp_enqueue_style( 'rwpp_css' );
        // Javascripts.
        wp_register_script(
            'rwpp_js',
            RWPP_LOCATION_URL . '/build/main.js',
            array_merge( $asset_file['dependencies'], ['jquery', 'jquery-ui-sortable'] ),
            $asset_file['version'],
            true
        );
        wp_localize_script( 'rwpp_js', 'rwpp_ajax_var', [
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'rwpp-ajax-nonce' ),
            'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
        ] );
        wp_enqueue_script( 'rwpp_js' );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'rwpp-settings-group', 'rwpp_effected_loops', [
            'sanitize_callback' => [$this, 'sanitize_setting'],
        ] );
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
        if ( is_admin() && current_user_can( 'activate_plugins' ) && !class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [$this, 'plugin_notice'] );
            deactivate_plugins( RWPP_BASENAME );
            if ( isset( $_GET['activate'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification
                unset($_GET['activate']);
                // phpcs:ignore WordPress.Security.NonceVerification
            }
        }
    }

    /**
     * Show plugin activation notice
     */
    public function plugin_notice() {
        ?> <div class="error"><p> <?php 
        esc_html_e( 'Please activate Woocommerce plugin before using', 'rearrange-woocommerce-products' );
        ?> <strong><?php 
        esc_html_e( 'Rearrange Products for WooCommerce', 'rearrange-woocommerce-products' );
        ?></strong> <?php 
        esc_html_e( 'plugin', 'rearrange-woocommerce-products' );
        ?>.</p></div>
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
                // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
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
            __( 'Rearrange My Products', 'rearrange-woocommerce-products' ),
            __( 'Rearrange My Products', 'rearrange-woocommerce-products' ),
            $role,
            'rwpp-page',
            [$this, 'add_pages_callback'],
            RWPP_LOCATION_URL . '/assets/icon-menu.png',
            '55.5'
        );
        add_submenu_page(
            'rwpp-page',
            __( 'Sort by Categories', 'rearrange-woocommerce-products' ),
            __( 'Sort by Categories', 'rearrange-woocommerce-products' ),
            $role,
            'rwpp-sortby-categories-page',
            [$this, 'add_pages_callback']
        );
        // Pro Feature: Presets.
        $presets_badge = ( !$this->has_pro_license() ? ' <span class="rwpp-pro-badge">PRO</span>' : '' );
        add_submenu_page(
            'rwpp-page',
            __( 'Presets', 'rearrange-woocommerce-products' ),
            __( 'Presets', 'rearrange-woocommerce-products' ) . $presets_badge,
            $role,
            'rwpp-sort-presets-page',
            [$this, 'add_pages_callback']
        );
        // Pro Feature: Smart Sort.
        $smart_sort_badge = ( !$this->has_pro_license() ? ' <span class="rwpp-pro-badge">PRO</span>' : '' );
        add_submenu_page(
            'rwpp-page',
            __( 'Smart Sort', 'rearrange-woocommerce-products' ),
            __( 'Smart Sort', 'rearrange-woocommerce-products' ) . $smart_sort_badge,
            $role,
            'rwpp-smart-sort-page',
            [$this, 'add_pages_callback']
        );
        // Pro Feature: Import / Export.
        $import_export_badge = ( !$this->has_pro_license() ? ' <span class="rwpp-pro-badge">PRO</span>' : '' );
        add_submenu_page(
            'rwpp-page',
            __( 'Import / Export', 'rearrange-woocommerce-products' ),
            __( 'Import / Export', 'rearrange-woocommerce-products' ) . $import_export_badge,
            $role,
            'rwpp-import-export-page',
            [$this, 'add_pages_callback']
        );
        add_submenu_page(
            'rwpp-page',
            __( 'Settings', 'rearrange-woocommerce-products' ),
            __( 'Settings', 'rearrange-woocommerce-products' ),
            $role,
            'rwpp-settings-page',
            [$this, 'add_pages_callback']
        );
        add_submenu_page(
            'rwpp-page',
            __( 'Troubleshooting', 'rearrange-woocommerce-products' ),
            __( 'Troubleshooting', 'rearrange-woocommerce-products' ),
            $role,
            'rwpp-troubleshooting-page',
            [$this, 'add_pages_callback']
        );
    }

    /**
     * Callback to add_page
     */
    public function add_pages_callback() {
        include RWPP_LOCATION . '/views/rearrange-all-products.php';
    }

    /**
     * Save All Products sort order.
     *
     * @throws \Exception If permissions, nonce, or data validation fails.
     */
    public function save_all_order_handler() {
        try {
            // Increase execution time for large product sets.
            if ( function_exists( 'set_time_limit' ) ) {
                set_time_limit( 300 );
                // 5 minutes.
            }
            if ( !$this->has_required_permissions() ) {
                Helpers::log( 'Unauthorized AJAX request to save_all_order_handler', 'warning' );
                throw new \Exception('Insufficient permissions');
            }
            if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
                Helpers::log( 'Invalid nonce in save_all_order_handler request', 'warning' );
                throw new \Exception('Invalid security token');
            }
            if ( !isset( $_POST['sort_orders'] ) ) {
                Helpers::log( 'Missing sort_orders data in save_all_order_handler request', 'warning' );
                throw new \Exception('Missing sort orders data');
            }
            $sort_orders = $this->clear_sort_orders( $_POST['sort_orders'] );
            // phpcs:ignore
            if ( !is_array( $sort_orders ) || count( $sort_orders ) === 0 ) {
                Helpers::log( 'Empty sort_orders array in save_all_order_handler', 'warning' );
                throw new \Exception('No products to save');
            }
            // Check if this is a chunked request by looking for chunk markers.
            $is_chunk = isset( $_POST['is_chunk'] ) && wp_validate_boolean( $_POST['is_chunk'] );
            // phpcs:ignore
            $is_last_chunk = isset( $_POST['is_last_chunk'] ) && wp_validate_boolean( $_POST['is_last_chunk'] );
            // phpcs:ignore
            $result = Database::bulk_update_orders( $sort_orders, 0 );
            if ( !$result ) {
                Helpers::log( 'Database error in bulk_update_orders for global sorting', 'error' );
                throw new \Exception('Failed to update product orders in database');
            }
            // Only update legacy data and show success on the last chunk.
            if ( $is_last_chunk || !$is_chunk ) {
                // Also maintain menu_order for backwards compatibility.
                $this->legacy_update_menu_order( $sort_orders );
                echo '<div class="rwpp-notice-success">
				<p><strong>' . esc_html( __( 'All products are rearranged now.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
				</div>';
            }
        } catch ( \Exception $e ) {
            Helpers::log( 'Exception in save_all_order_handler: ' . $e->getMessage(), 'error' );
            echo '<div class="rwpp-notice-error">
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
                $sort_orders = array_combine( array_map( 'intval', $keys ), array_values( $sort_orders ) );
            }
        }
        $sort_orders = ( isset( $sort_orders ) ? array_map( 'sanitize_text_field', wp_unslash( $sort_orders ) ) : [] );
        $sort_orders = ( isset( $sort_orders ) ? array_map( 'esc_attr', wp_unslash( $sort_orders ) ) : [] );
        $sort_orders = array_filter( $sort_orders, 'is_numeric' );
        return $sort_orders;
    }

    /**
     * Save sort order by category.
     *
     * @throws \Exception If permissions, nonce, or data validation fails.
     */
    public function save_all_order_by_category_handler() {
        try {
            // Increase execution time for large product sets.
            if ( function_exists( 'set_time_limit' ) ) {
                set_time_limit( 300 );
                // 5 minutes.
            }
            if ( !$this->has_required_permissions() ) {
                Helpers::log( 'Unauthorized AJAX request to save_all_order_by_category_handler', 'warning' );
                throw new \Exception('Insufficient permissions');
            }
            if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
                Helpers::log( 'Invalid nonce in save_all_order_by_category_handler request', 'warning' );
                throw new \Exception('Invalid security token');
            }
            if ( !isset( $_POST['sort_orders'] ) || !isset( $_POST['term_id'] ) ) {
                Helpers::log( 'Missing sort_orders or term_id data in save_all_order_by_category_handler request', 'warning' );
                throw new \Exception('Missing required data');
            }
            $sort_orders = $this->clear_sort_orders( $_POST['sort_orders'] );
            // phpcs:ignore
            $term_id = absint( $_POST['term_id'] );
            // phpcs:ignore
            if ( !is_array( $sort_orders ) || count( $sort_orders ) === 0 ) {
                Helpers::log( 'Empty sort_orders array in save_all_order_by_category_handler for term_id: ' . $term_id, 'warning' );
                throw new \Exception('No products to save');
            }
            if ( $term_id <= 0 ) {
                Helpers::log( 'Invalid term_id: ' . $term_id . ' in save_all_order_by_category_handler', 'warning' );
                throw new \Exception('Invalid category');
            }
            // Check if this is a chunked request by looking for chunk markers.
            $is_chunk = isset( $_POST['is_chunk'] ) && wp_validate_boolean( $_POST['is_chunk'] );
            // phpcs:ignore
            $is_last_chunk = isset( $_POST['is_last_chunk'] ) && wp_validate_boolean( $_POST['is_last_chunk'] );
            // phpcs:ignore
            $result = Database::bulk_update_orders( $sort_orders, $term_id );
            if ( !$result ) {
                Helpers::log( 'Database error in bulk_update_orders for category sorting, term_id: ' . $term_id, 'error' );
                throw new \Exception('Failed to update product orders in database');
            }
            // Only update legacy data and show success on the last chunk.
            if ( $is_last_chunk || !$is_chunk ) {
                // Also maintain postmeta for backwards compatibility.
                $this->legacy_update_postmeta( $sort_orders, $term_id );
                echo '<div class="rwpp-notice-success">
				<p><strong>' . esc_html( __( 'All products are rearranged now.', 'rearrange-woocommerce-products' ) ) . '</strong></p>
				</div>';
            }
        } catch ( \Exception $e ) {
            Helpers::log( 'Exception in save_all_order_by_category_handler: ' . $e->getMessage(), 'error' );
            echo '<div class="rwpp-notice-error">
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
        $url = admin_url( 'admin.php?page=rwpp-sortby-categories-page&term_id=' . $term->term_id );
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
        // Clean up any lingering sorting filters from previous shortcode processing.
        // WooCommerce's shortcode transient cache can cause WP_Query to be skipped,
        // preventing posts_selection from firing and leaving filters active.
        $this->cleanup_shortcode_sorting_state();
        // Check for rwpp-order attribute (preset-based sorting) - Pro feature.
        // Note: WordPress normalizes shortcode attributes to lowercase.
        $rwpp_order = ( isset( $attributes['rwpp-order'] ) ? $attributes['rwpp-order'] : (( isset( $attributes['rwpp_order'] ) ? $attributes['rwpp_order'] : null )) );
        if ( $rwpp_order && $this->has_pro_license() ) {
            $preset_name = sanitize_text_field( $rwpp_order );
            // Get the preset by name.
            if ( class_exists( 'ReWooProducts\\SortPresets' ) ) {
                $preset = SortPresets::get_preset_by_name( $preset_name );
                if ( $preset && !empty( $preset->preset_data ) ) {
                    // Determine if this is a product_category shortcode (has category attribute).
                    $is_category_shortcode = isset( $attributes['category'] ) && !empty( $attributes['category'] );
                    $preset_category_id = absint( $preset->category_id );
                    $preset_valid = false;
                    if ( $is_category_shortcode ) {
                        // For product_category shortcode: preset must match the shortcode's category.
                        $category_slug = $attributes['category'];
                        // Handle comma-separated categories - use the first one.
                        if ( false !== strpos( $category_slug, ',' ) ) {
                            $categories = array_map( 'trim', explode( ',', $category_slug ) );
                            $category_slug = $categories[0];
                        }
                        $term = get_term_by( 'slug', $category_slug, 'product_cat' );
                        if ( $term && absint( $term->term_id ) === $preset_category_id ) {
                            $preset_valid = true;
                        }
                    } elseif ( 0 === $preset_category_id ) {
                        // For products shortcode: only Global Sort presets (category_id = 0).
                        $preset_valid = true;
                    }
                    if ( $preset_valid ) {
                        // Decode preset data to get product orders.
                        $preset_data = json_decode( $preset->preset_data, true );
                        if ( isset( $preset_data['orders'] ) && is_array( $preset_data['orders'] ) ) {
                            // Store preset orders for use in filter callbacks.
                            $this->current_preset_orders = $preset_data['orders'];
                            // Add filters for preset-based ordering.
                            add_filter(
                                'posts_join',
                                [$this, 'join_preset_order_table'],
                                100,
                                2
                            );
                            add_filter(
                                'posts_orderby',
                                [$this, 'orderby_preset_order'],
                                100,
                                2
                            );
                            // Hook to remove filters after query runs.
                            add_action( 'posts_selection', [$this, 'remove_preset_sorting_filters'] );
                            // Force orderby to prevent WooCommerce from overriding our custom sort.
                            $query_args['orderby'] = 'none';
                            $query_args['order'] = 'ASC';
                            // Add a unique key to bust WooCommerce's shortcode cache.
                            $query_args['rwpp_preset_sort'] = 'preset_' . $preset->id . '_' . strtotime( $preset->updated_at );
                            // Ensure cleanup after shortcode renders, even on cache hit.
                            add_filter(
                                'woocommerce_shortcode_products_query_results',
                                [$this, 'cleanup_after_shortcode_results'],
                                10,
                                2
                            );
                            return $query_args;
                        }
                    }
                }
            }
        }
        // Check if the product_cat taxonomy query is being used.
        if ( get_option( 'rwpp_effected_loops' ) && isset( $query_args['tax_query'] ) && isset( $attributes['category'] ) ) {
            // Handle multiple comma-separated categories by using the first one for sorting.
            $category_slug = $attributes['category'];
            if ( false !== strpos( $category_slug, ',' ) ) {
                $categories = array_map( 'trim', explode( ',', $category_slug ) );
                $category_slug = $categories[0];
            }
            $term = get_term_by( 'slug', $category_slug, 'product_cat' );
            if ( $term ) {
                $term_id = $term->term_id;
                if ( $term_id ) {
                    // Store for use in filter callbacks.
                    $this->current_category_id = $term_id;
                    // Add filters for table join and ordering.
                    add_filter(
                        'posts_join',
                        [$this, 'join_product_order_table'],
                        100,
                        2
                    );
                    add_filter(
                        'posts_orderby',
                        [$this, 'orderby_product_order'],
                        100,
                        2
                    );
                    // Hook to remove filters after query runs.
                    add_action( 'posts_selection', [$this, 'remove_shortcode_sorting_filters'] );
                    // Force orderby to prevent WooCommerce from overriding our custom sort.
                    // Setting to 'none' lets our posts_orderby filter take full control.
                    $query_args['orderby'] = 'none';
                    $query_args['order'] = 'ASC';
                    // Add a unique key to bust WooCommerce's shortcode cache.
                    // WooCommerce caches shortcode queries based on query args hash.
                    // By adding this unique key, we ensure our sorted results get their own cache entry.
                    $query_args['rwpp_custom_sort'] = $term_id . '_' . Database::get_last_update_time( $term_id );
                    // Ensure cleanup after shortcode renders, even on cache hit.
                    add_filter(
                        'woocommerce_shortcode_products_query_results',
                        [$this, 'cleanup_after_shortcode_results'],
                        10,
                        2
                    );
                }
            }
        }
        // Fallback for page builders (Elementor, Divi, Beaver Builder, etc.)
        // that set tax_query directly instead of using WooCommerce's category attribute.
        // Users opt in by setting "Order By: Menu Order" in the page builder widget.
        if ( empty( $attributes['category'] ) && !empty( $query_args['tax_query'] ) && is_array( $query_args['tax_query'] ) && !empty( $query_args['orderby'] ) && false !== strpos( $query_args['orderby'], 'menu_order' ) ) {
            $term_id = $this->extract_term_id_from_tax_query( $query_args['tax_query'] );
            if ( $term_id ) {
                // Store for use in filter callbacks.
                $this->current_category_id = $term_id;
                // Add filters for table join and ordering.
                add_filter(
                    'posts_join',
                    [$this, 'join_product_order_table'],
                    100,
                    2
                );
                add_filter(
                    'posts_orderby',
                    [$this, 'orderby_product_order'],
                    100,
                    2
                );
                // Hook to remove filters after query runs.
                add_action( 'posts_selection', [$this, 'remove_shortcode_sorting_filters'] );
                // Force orderby to prevent WooCommerce from overriding our custom sort.
                $query_args['orderby'] = 'none';
                $query_args['order'] = 'ASC';
                // Add a unique key to bust WooCommerce's shortcode cache.
                $query_args['rwpp_custom_sort'] = $term_id . '_' . Database::get_last_update_time( $term_id );
                // Ensure cleanup after shortcode renders, even on cache hit.
                add_filter(
                    'woocommerce_shortcode_products_query_results',
                    [$this, 'cleanup_after_shortcode_results'],
                    10,
                    2
                );
            }
        }
        return $query_args;
    }

    /**
     * Add JOIN clause for preset-based ordering.
     *
     * Creates a derived table from the preset's product order data.
     *
     * @param string   $join  Current JOIN clause.
     * @param WP_Query $query The WP_Query instance.
     * @return string Modified JOIN clause.
     */
    public function join_preset_order_table( $join, $query ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( !empty( $this->current_preset_orders ) ) {
            // Build UNION SELECT statements for each product's order position.
            $union_parts = [];
            foreach ( $this->current_preset_orders as $product_id => $sort_order ) {
                $union_parts[] = $wpdb->prepare( 'SELECT %d AS product_id, %d AS preset_order', absint( $product_id ), absint( $sort_order ) );
            }
            if ( !empty( $union_parts ) ) {
                $derived_table = implode( ' UNION ALL ', $union_parts );
                $join .= " LEFT JOIN ({$derived_table}) AS rwpp_preset ON {$wpdb->posts}.ID = rwpp_preset.product_id";
            }
        }
        return $join;
    }

    /**
     * Modify ORDER BY clause for preset-based ordering.
     *
     * @param string   $orderby Current ORDER BY clause.
     * @param WP_Query $query   The WP_Query instance.
     * @return string Modified ORDER BY clause.
     */
    public function orderby_preset_order( $orderby, $query ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( !empty( $this->current_preset_orders ) ) {
            // Products in the preset get their preset_order, others get 9999 to sort at the end.
            $orderby = "COALESCE(rwpp_preset.preset_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
        }
        return $orderby;
    }

    /**
     * Remove preset sorting filters after query runs.
     *
     * This prevents the plugin's sorting from affecting subsequent queries.
     *
     * @return void
     */
    public function remove_preset_sorting_filters() {
        remove_filter( 'posts_join', [$this, 'join_preset_order_table'], 100 );
        remove_filter( 'posts_orderby', [$this, 'orderby_preset_order'], 100 );
        remove_action( 'posts_selection', [$this, 'remove_preset_sorting_filters'] );
        // Clear the preset orders.
        $this->current_preset_orders = [];
    }

    /**
     * Remove shortcode sorting filters after query runs.
     *
     * This prevents the plugin's sorting from affecting subsequent queries.
     *
     * @return void
     */
    public function remove_shortcode_sorting_filters() {
        remove_filter( 'posts_join', [$this, 'join_product_order_table'], 100 );
        remove_filter( 'posts_orderby', [$this, 'orderby_product_order'], 100 );
        remove_action( 'posts_selection', [$this, 'remove_shortcode_sorting_filters'] );
    }

    /**
     * Remove all shortcode sorting filters and reset state.
     *
     * Ensures a clean slate between shortcodes on the same page. This is needed
     * because WooCommerce's shortcode transient cache can bypass WP_Query entirely,
     * preventing posts_selection from firing and leaving filters active.
     *
     * @return void
     */
    private function cleanup_shortcode_sorting_state() {
        // Remove category-based sorting filters.
        remove_filter( 'posts_join', [$this, 'join_product_order_table'], 100 );
        remove_filter( 'posts_orderby', [$this, 'orderby_product_order'], 100 );
        remove_action( 'posts_selection', [$this, 'remove_shortcode_sorting_filters'] );
        // Remove preset-based sorting filters.
        remove_filter( 'posts_join', [$this, 'join_preset_order_table'], 100 );
        remove_filter( 'posts_orderby', [$this, 'orderby_preset_order'], 100 );
        remove_action( 'posts_selection', [$this, 'remove_preset_sorting_filters'] );
        // Remove the results cleanup hook itself.
        remove_filter( 'woocommerce_shortcode_products_query_results', [$this, 'cleanup_after_shortcode_results'], 10 );
        // Clear sorting state.
        $this->current_preset_orders = [];
    }

    /**
     * Clean up sorting filters after WooCommerce shortcode query results.
     *
     * This fires after WC_Shortcode_Products::get_query_results(), regardless of
     * whether the result came from cache or a live WP_Query. This ensures filters
     * are always cleaned up, even when posts_selection doesn't fire due to caching.
     *
     * @param object $results Query results.
     * @param object $shortcode WC_Shortcode_Products instance.
     * @return object Unmodified results.
     */
    public function cleanup_after_shortcode_results( $results, $shortcode ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        $this->cleanup_shortcode_sorting_state();
        return $results;
    }

    /**
     * Extract term ID from a tax_query array for the product_cat taxonomy.
     *
     * Handles different field types (slug, name, term_id, id) since page builders
     * vary in how they set the tax query.
     *
     * @param array $tax_query The tax_query array from query args.
     * @return int The term ID, or 0 if not found.
     */
    private function extract_term_id_from_tax_query( $tax_query ) {
        foreach ( $tax_query as $clause ) {
            if ( !is_array( $clause ) || empty( $clause['taxonomy'] ) || 'product_cat' !== $clause['taxonomy'] ) {
                continue;
            }
            if ( empty( $clause['terms'] ) ) {
                continue;
            }
            // Get the first term value.
            $term_value = ( is_array( $clause['terms'] ) ? reset( $clause['terms'] ) : $clause['terms'] );
            $field = ( !empty( $clause['field'] ) ? $clause['field'] : 'term_id' );
            switch ( $field ) {
                case 'slug':
                    $term = get_term_by( 'slug', $term_value, 'product_cat' );
                    return ( $term ? $term->term_id : 0 );
                case 'name':
                    $term = get_term_by( 'name', $term_value, 'product_cat' );
                    return ( $term ? $term->term_id : 0 );
                case 'term_id':
                case 'id':
                    return absint( $term_value );
            }
        }
        return 0;
    }

    /**
     * Modify Products loop query to sort by categories
     *
     * @param object $query WP_Query variable.
     */
    public function sort_products_by_category( $query ) {
        // Only target the front-end main query.
        if ( is_admin() || !$query->is_main_query() ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Frontend query parameter, no data modification.
        if ( isset( $_GET['orderby'] ) && 'date' === $_GET['orderby'] ) {
            return;
        }
        $apply_sorting = false;
        $current_id = 0;
        if ( get_option( 'rwpp_effected_loops' ) ) {
            // All Loops mode: apply sorting everywhere.
            $apply_sorting = true;
            // On category pages, use category-specific sort order.
            if ( is_tax( 'product_cat' ) ) {
                $term = get_queried_object();
                if ( $term && is_a( $term, 'WP_Term' ) ) {
                    $current_id = $term->term_id;
                } else {
                    $current_id = 0;
                    // Fallback to global sort.
                }
            } else {
                $current_id = 0;
                // Default to global sort for shop, related products, etc.
            }
        } elseif ( is_tax( 'product_cat' ) && $query->is_main_query() ) {
            // Main Loop Only mode: Category page - use category-specific sort.
            $term = get_queried_object();
            if ( $term && is_a( $term, 'WP_Term' ) ) {
                $apply_sorting = true;
                $current_id = $term->term_id;
            }
        } elseif ( is_shop() ) {
            // Main Loop Only mode: Shop page - use global sort.
            $apply_sorting = true;
            $current_id = 0;
        }
        if ( $apply_sorting ) {
            // Store category ID for use in filter callbacks.
            $this->current_category_id = $current_id;
            // Set flag to indicate we're applying sort to main query.
            $this->rwpp_applying_main_query_sort = true;
            // Add filters for table join and ordering.
            add_filter(
                'posts_join',
                [$this, 'join_product_order_table'],
                100,
                2
            );
            add_filter(
                'posts_orderby',
                [$this, 'orderby_product_order'],
                100,
                2
            );
            // Remove filters after main query posts are retrieved to prevent affecting secondary queries.
            // Uses 'the_posts' instead of 'posts_selection' to receive the $query object,
            // enabling an is_main_query() guard that prevents premature removal by secondary
            // queries (e.g. FacetWP internal queries).
            add_filter(
                'the_posts',
                [$this, 'remove_sorting_filters_main_query_only'],
                10,
                2
            );
        }
    }

    /**
     * Remove sorting filters after main query posts are retrieved.
     *
     * Uses the_posts filter (which passes the WP_Query object) instead of
     * posts_selection (which does not) so we can guard with is_main_query().
     * This prevents plugins like FacetWP — which run their own internal
     * WP_Query instances — from prematurely stripping our sorting filters
     * before the main query executes.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The WP_Query instance.
     * @return array Unmodified array of post objects.
     */
    public function remove_sorting_filters_main_query_only( $posts, $query ) {
        if ( $query->is_main_query() && $this->rwpp_applying_main_query_sort ) {
            remove_filter( 'posts_join', [$this, 'join_product_order_table'], 100 );
            remove_filter( 'posts_orderby', [$this, 'orderby_product_order'], 100 );
            remove_filter( 'the_posts', [$this, 'remove_sorting_filters_main_query_only'], 10 );
            $this->rwpp_applying_main_query_sort = false;
        }
        return $posts;
    }

    /**
     * Join with custom product order table
     *
     * @param string $join JOIN clause.
     * @param object $query WP_Query object.
     * @return string Modified JOIN clause.
     */
    public function join_product_order_table( $join, $query ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( isset( $this->current_category_id ) && $this->current_category_id >= 0 ) {
            $table_name = Database::get_table_name();
            $category_id = absint( $this->current_category_id );
            $join .= " LEFT JOIN {$table_name} AS rwpp_order\n\t\t\t\t\t   ON {$wpdb->posts}.ID = rwpp_order.product_id\n\t\t\t\t\t   AND rwpp_order.category_id = {$category_id}";
            // Fallback: also join postmeta for unmigrated category sort data.
            if ( $category_id > 0 ) {
                $meta_key = 'rwpp_sortorder_' . $category_id;
                $join .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS rwpp_meta\n\t\t\t\t\t  ON {$wpdb->posts}.ID = rwpp_meta.post_id\n\t\t\t\t\t  AND rwpp_meta.meta_key = %s", $meta_key );
            }
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
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( isset( $this->current_category_id ) && $this->current_category_id >= 0 ) {
            if ( $this->current_category_id > 0 ) {
                // Category sorting: custom_order -> legacy postmeta -> menu_order -> unsorted.
                $orderby = "COALESCE(rwpp_order.sort_order, CAST(rwpp_meta.meta_value AS UNSIGNED), {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
            } else {
                // Global sorting: custom_order -> menu_order -> unsorted.
                $orderby = "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
            }
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
        $products = new \WP_Query([
            'post_type'      => ['product'],
            'posts_per_page' => '-1',
            'post_status'    => ['publish'],
            'tax_query'      => [
                // phpcs:ignore
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => [$term_id],
                    'field'    => 'id',
                    'operator' => 'IN',
                ],
            ],
        ]);
        if ( $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $meta_key = 'rwpp_sortorder_' . $term_id;
                $menu_order = $post->menu_order;
                $sort_order = get_post_meta( $post->ID, $meta_key, true );
                if ( !$sort_order ) {
                    update_post_meta( $post->ID, $meta_key, $menu_order );
                }
            }
        }
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
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        $terms = wp_get_post_terms( $post_id, 'product_cat' );
        // Get current menu_order to use as default sort order.
        $menu_order = ( isset( $post->menu_order ) ? absint( $post->menu_order ) : 0 );
        // Add product to custom table for each category (only if it doesn't exist).
        if ( $terms ) {
            foreach ( $terms as $term ) {
                // Only add if this product doesn't already have a sort order for this category.
                $existing_order = Database::get_sort_order( $post_id, $term->term_id );
                if ( null === $existing_order ) {
                    Database::set_sort_order( $post_id, $term->term_id, $menu_order );
                }
            }
        }
        // Also add global sort order (only if it doesn't exist).
        $existing_global_order = Database::get_sort_order( $post_id, 0 );
        if ( null === $existing_global_order ) {
            Database::set_sort_order( $post_id, 0, $menu_order );
        }
        // Maintain postmeta for backwards compatibility.
        if ( $terms ) {
            foreach ( $terms as $term ) {
                if ( !metadata_exists( 'post', $post_id, 'rwpp_sortorder_' . $term->term_id ) ) {
                    update_post_meta( $post_id, 'rwpp_sortorder_' . $term->term_id, $menu_order );
                }
            }
        }
    }

    /**
     * Clean up sort order entries when a product is deleted.
     *
     * @param int $post_id Post ID being deleted.
     * @return void
     */
    public function cleanup_product_on_delete( $post_id ) {
        // Verify it's a product post type.
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }
        // Delete all sort order entries for this product.
        Database::delete_product_orders( $post_id );
        // Remove product from all presets.
        $this->remove_product_from_presets( $post_id );
    }

    /**
     * Remove a product from all presets.
     *
     * @param int $product_id Product ID to remove.
     * @return void
     */
    private function remove_product_from_presets( $product_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwpp_sort_presets';
        // Check if presets table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }
        // Get all presets.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $presets = $wpdb->get_results( $wpdb->prepare( 'SELECT id, preset_data, product_count FROM %i', $table_name ) );
        if ( empty( $presets ) ) {
            return;
        }
        foreach ( $presets as $preset ) {
            $preset_data = json_decode( $preset->preset_data, true );
            // Skip if invalid data.
            if ( !$preset_data || !isset( $preset_data['orders'] ) ) {
                continue;
            }
            // Check if this product exists in the preset.
            if ( !isset( $preset_data['orders'][$product_id] ) ) {
                continue;
            }
            // Remove the product from orders.
            unset($preset_data['orders'][$product_id]);
            // Update metadata.
            $preset_data['metadata']['total_products'] = count( $preset_data['orders'] );
            // Update the preset in database.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [
                    'preset_data'   => wp_json_encode( $preset_data ),
                    'product_count' => count( $preset_data['orders'] ),
                    'updated_at'    => current_time( 'mysql' ),
                ],
                [
                    'id' => $preset->id,
                ],
                ['%s', '%d', '%s'],
                ['%d']
            );
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
        // Build the table name placeholder separately, then append the CASE logic.
        $case_sql = 'SET menu_order = ( CASE ';
        $fields_in = '';
        foreach ( $sort_orders as $new_sort_order => $product_id ) {
            $case_sql .= $wpdb->prepare(
                'WHEN ID = %d AND post_type=%s THEN %d ',
                intval( $product_id ),
                'product',
                intval( $new_sort_order )
            );
            $fields_in .= intval( $product_id ) . ',';
        }
        $fields_in = rtrim( $fields_in, ',' );
        $case_sql .= 'ELSE NULL END ) ';
        $case_sql .= "WHERE ID IN ({$fields_in}) ";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( 'UPDATE %i ' . $case_sql, $wpdb->posts ) );
    }

    /**
     * Update postmeta for category sorting for backwards compatibility.
     *
     * @param array $sort_orders Sort orders array.
     * @param int   $term_id Category term ID.
     */
    private function legacy_update_postmeta( $sort_orders, $term_id ) {
        if ( empty( $sort_orders ) ) {
            return;
        }
        foreach ( $sort_orders as $new_sort_order => $product_id ) {
            $meta_key = 'rwpp_sortorder_' . $term_id;
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = %s", $meta_key ) );
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
                // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Check if user has a valid pro license
     *
     * @return bool True if user has valid pro license, false otherwise.
     */
    public function has_pro_license() {
        // Check if Freemius function exists.
        if ( !function_exists( 'rwpp_fs' ) ) {
            return false;
        }
        // Check if user can use premium code (has active license).
        return rwpp_fs()->can_use_premium_code__premium_only();
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
			/* PRO Badge Styling */
			.rwpp-pro-badge {
				display: inline-block;
				background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
				color: #fff;
				font-size: 9px;
				line-height: 1.2;
				font-weight: 700;
				padding: 2px 4px;
				border-radius: 3px;
				margin-left: 6px;
				margin-top: -2px;
				vertical-align: middle;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
			}
		</style>';
    }

    /**
     * Load more products via AJAX.
     *
     * Handles infinite scroll pagination for products list.
     *
     * @throws \Exception If permissions or nonce validation fails.
     */
    public function load_more_products_handler() {
        try {
            // Increase execution time for pagination queries.
            if ( function_exists( 'set_time_limit' ) ) {
                set_time_limit( 60 );
            }
            // Security validation.
            if ( !$this->has_required_permissions() ) {
                Helpers::log( 'Unauthorized AJAX request to load_more_products_handler', 'warning' );
                throw new \Exception('Insufficient permissions');
            }
            if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
                Helpers::log( 'Invalid nonce in load_more_products_handler request', 'warning' );
                throw new \Exception('Invalid security token');
            }
            // Get parameters.
            $page = ( isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1 );
            // phpcs:ignore
            $per_page = ( isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 100 );
            // phpcs:ignore
            $term_id = ( isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0 );
            // phpcs:ignore
            if ( $page < 1 ) {
                $page = 1;
            }
            if ( $per_page < 1 || $per_page > 200 ) {
                $per_page = 100;
                // Max 200 per request to prevent abuse.
            }
            // Sanitize term_id.
            $term_id = max( 0, $term_id );
            // Build query with custom table JOIN for sorting.
            $current_term_id = $term_id;
            $join_callback = function ( $join ) use(&$current_term_id) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'rwpp_product_order';
                $category_id = absint( $current_term_id );
                $join .= " LEFT JOIN {$table_name} AS rwpp_order\n\t\t\t\t\t\t   ON {$wpdb->posts}.ID = rwpp_order.product_id\n\t\t\t\t\t\t   AND rwpp_order.category_id = {$category_id}";
                // Fallback: also join postmeta for unmigrated category sort data.
                if ( $category_id > 0 ) {
                    $meta_key = 'rwpp_sortorder_' . $category_id;
                    $join .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS rwpp_meta\n\t\t\t\t\t\t  ON {$wpdb->posts}.ID = rwpp_meta.post_id\n\t\t\t\t\t\t  AND rwpp_meta.meta_key = %s", $meta_key );
                }
                return $join;
            };
            $orderby_callback = function ( $orderby ) use(&$current_term_id) {
                // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
                global $wpdb;
                if ( $current_term_id > 0 ) {
                    return "COALESCE(rwpp_order.sort_order, CAST(rwpp_meta.meta_value AS UNSIGNED), {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
                }
                return "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
            };
            $args = [
                'post_type'      => ['product'],
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'post_status'    => ['publish'],
            ];
            // Add category filter if specified.
            if ( $term_id > 0 ) {
                $args['tax_query'] = array(
                    // phpcs:ignore
                    [
                        'taxonomy'         => 'product_cat',
                        'terms'            => [$term_id],
                        'field'            => 'id',
                        'operator'         => 'IN',
                        'include_children' => true,
                    ],
                );
            }
            // Apply filters for custom table JOIN.
            add_filter(
                'posts_join',
                $join_callback,
                100,
                1
            );
            add_filter(
                'posts_orderby',
                $orderby_callback,
                100,
                1
            );
            // Execute query.
            $products = new \WP_Query($args);
            // Clean up filters.
            remove_filter( 'posts_join', $join_callback, 100 );
            remove_filter( 'posts_orderby', $orderby_callback, 100 );
            // Build HTML for products.
            ob_start();
            $serial_no = ($page - 1) * $per_page + 1;
            if ( $products->have_posts() ) {
                while ( $products->have_posts() ) {
                    $products->the_post();
                    global $post;
                    $product = wc_get_product( $post->ID );
                    // output escaped via WooCommerce.
                    include RWPP_LOCATION . '/views/template-parts/product.php';
                    ++$serial_no;
                }
            }
            $products_html = ob_get_clean();
            wp_reset_postdata();
            // Determine if there are more pages.
            $has_more = $products->max_num_pages > $page;
            $loaded_count = min( $page * $per_page, $products->found_posts );
            // Return JSON response.
            wp_send_json_success( [
                'products_html' => $products_html,
                'has_more'      => $has_more,
                'total'         => $products->found_posts,
                'loaded'        => $loaded_count,
                'current_page'  => $page,
            ] );
        } catch ( \Exception $e ) {
            Helpers::log( 'Exception in load_more_products_handler: ' . $e->getMessage(), 'error' );
            wp_send_json_error( [
                'message' => __( 'Failed to load products.', 'rearrange-woocommerce-products' ),
            ] );
        }
        die;
        // Ensure we always exit after AJAX handler.
    }

    /**
     * AJAX handler for re-running database migration
     *
     * Triggered from the Troubleshooting tab to re-import sorting data
     * from legacy storage into the custom table.
     */
    public function run_remigration_handler() {
        if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rwpp-ajax-nonce' ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid security token.', 'rearrange-woocommerce-products' ),
            ] );
        }
        if ( !$this->has_required_permissions() ) {
            wp_send_json_error( [
                'message' => __( 'Insufficient permissions.', 'rearrange-woocommerce-products' ),
            ] );
        }
        $result = Database::run_remigration();
        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => sprintf( 
                    /* translators: 1: number of global records migrated, 2: number of category records migrated */
                    __( 'Migration completed successfully. Migrated %1$d global and %2$d category sorting records.', 'rearrange-woocommerce-products' ),
                    $result['global_migrated'],
                    $result['category_migrated']
                 ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => __( 'Migration failed. Please check the error log for details.', 'rearrange-woocommerce-products' ),
                'errors'  => $result['errors'],
            ] );
        }
    }

    /**
     * Build WP_Query orderby arguments for native sorts
     *
     * @param string $primary_sort Primary sort criteria.
     * @return array Query arguments with orderby, order, meta_key, and meta_query as needed.
     */
    private function build_orderby_args( $primary_sort ) {
        // Handle shuffle separately.
        if ( 'shuffle' === $primary_sort ) {
            return [
                'orderby' => 'rand',
            ];
        }
        // Single sort criteria.
        return $this->build_single_orderby( $primary_sort );
    }

    /**
     * Build orderby args for a single sort criteria
     *
     * @param string $criteria Sort criteria.
     * @return array Query arguments.
     */
    private function build_single_orderby( $criteria ) {
        switch ( $criteria ) {
            case 'best_selling':
                // Use WooCommerce's native popularity sorting.
                return [
                    'orderby' => 'popularity',
                    'order'   => 'DESC',
                ];
            case 'most_rated':
                // Use WooCommerce's native rating sorting (sorts by average rating DESC).
                // Note: WC's 'rating' sorts by _wc_average_rating, but it handles NULLs correctly.
                return [
                    'orderby' => 'rating',
                    'order'   => 'DESC',
                ];
            case 'alphabetical_az':
                return [
                    'orderby' => 'title',
                    'order'   => 'ASC',
                ];
            case 'in_stock':
                return [
                    'meta_key' => '_stock_status',
                    'orderby'  => 'meta_value',
                    'order'    => 'ASC',
                ];
            case 'latest':
                return [
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ];
            case 'oldest':
                return [
                    'orderby' => 'date',
                    'order'   => 'ASC',
                ];
            case 'price_low_high':
                return [
                    'meta_key' => '_price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'ASC',
                ];
            case 'price_high_low':
                return [
                    'meta_key' => '_price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'DESC',
                ];
            default:
                return [
                    'orderby' => 'ID',
                    'order'   => 'ASC',
                ];
        }
    }

    /**
     * Custom JOIN for Smart Sort with meta fields.
     *
     * @param string $join  JOIN clause.
     * @param object $query WP_Query object.
     * @return string Modified JOIN clause.
     */
    public function custom_compound_join( $join, $query ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( !isset( $this->compound_sort_configs ) ) {
            return $join;
        }
        $primary = $this->compound_sort_configs['primary'];
        // Add WooCommerce lookup table JOIN if needed.
        if ( isset( $primary['use_wc_lookup'] ) && $primary['use_wc_lookup'] && !strstr( $join, 'wc_product_meta_lookup' ) ) {
            $join .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON {$wpdb->posts}.ID = wc_product_meta_lookup.product_id ";
        }
        // Add JOIN for meta field if needed.
        if ( isset( $primary['meta_key'] ) && !isset( $primary['use_wc_lookup'] ) ) {
            $meta_key = esc_sql( $primary['meta_key'] );
            $join .= " LEFT JOIN {$wpdb->postmeta} AS pm_primary ON ({$wpdb->posts}.ID = pm_primary.post_id AND pm_primary.meta_key = '{$meta_key}')";
        }
        return $join;
    }

    /**
     * Custom ORDER BY for Smart Sort.
     *
     * @param string $orderby ORDER BY clause.
     * @param object $query   WP_Query object.
     * @return string Modified ORDER BY clause.
     */
    public function custom_compound_orderby( $orderby, $query ) {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        global $wpdb;
        if ( !isset( $this->compound_sort_configs ) ) {
            return $orderby;
        }
        $primary = $this->compound_sort_configs['primary'];
        $order_parts = [];
        // Primary sort.
        if ( isset( $primary['use_wc_lookup'] ) && $primary['use_wc_lookup'] ) {
            // Use WooCommerce lookup table fields.
            if ( 'best_selling' === $primary['criteria'] ) {
                $order_parts[] = "wc_product_meta_lookup.total_sales {$primary['order']}";
            } elseif ( 'most_rated' === $primary['criteria'] ) {
                $order_parts[] = "wc_product_meta_lookup.average_rating {$primary['order']}";
                $order_parts[] = "wc_product_meta_lookup.rating_count {$primary['order']}";
            }
        } elseif ( isset( $primary['meta_key'] ) ) {
            // Special handling for stock status: 'instock' should come before 'outofstock'.
            if ( '_stock_status' === $primary['meta_key'] ) {
                $order_parts[] = "CASE WHEN pm_primary.meta_value = 'instock' THEN 0 ELSE 1 END {$primary['order']}";
            } elseif ( 'NUMERIC' === $primary['meta_type'] ) {
                // Handle NULL values - products with meta come first.
                $order_parts[] = '(pm_primary.meta_value IS NOT NULL) DESC';
                $order_parts[] = "CAST(pm_primary.meta_value AS SIGNED) {$primary['order']}";
            } else {
                // Handle NULL values - products with meta come first.
                $order_parts[] = '(pm_primary.meta_value IS NOT NULL) DESC';
                $order_parts[] = "pm_primary.meta_value {$primary['order']}";
            }
        } elseif ( isset( $primary['orderby_key'] ) && 'title' === $primary['orderby_key'] ) {
            $order_parts[] = "{$wpdb->posts}.post_title {$primary['order']}";
        } elseif ( isset( $primary['orderby_key'] ) && 'date' === $primary['orderby_key'] ) {
            $order_parts[] = "{$wpdb->posts}.post_date {$primary['order']}";
        }
        // Always add product_id DESC as final tiebreaker (matches WooCommerce behavior).
        $order_parts[] = "{$wpdb->posts}.ID DESC";
        if ( !empty( $order_parts ) ) {
            return implode( ', ', $order_parts );
        }
        return $orderby;
    }

    /**
     * Get orderby configuration for a specific sort criteria
     *
     * @param string $criteria Sort criteria.
     * @param string $clause_name Clause name for meta_query (e.g., 'primary_clause', 'secondary_clause').
     * @return array Configuration array.
     */
    private function get_orderby_config( $criteria, $clause_name ) {
        switch ( $criteria ) {
            case 'best_selling':
                return [
                    'criteria'      => 'best_selling',
                    'orderby_key'   => $clause_name,
                    'order'         => 'DESC',
                    'use_wc_lookup' => true,
                ];
            case 'most_rated':
                return [
                    'criteria'      => 'most_rated',
                    'orderby_key'   => $clause_name,
                    'order'         => 'DESC',
                    'use_wc_lookup' => true,
                ];
            case 'alphabetical_az':
                return [
                    'orderby_key' => 'title',
                    'order'       => 'ASC',
                ];
            case 'in_stock':
                return [
                    'orderby_key' => 'meta_value',
                    'order'       => 'ASC',
                ];
            default:
                return [
                    'orderby_key' => 'ID',
                    'order'       => 'ASC',
                ];
        }
    }

    /**
     * Check if any products match the sorting criteria
     *
     * @param array  $product_ids Array of product IDs.
     * @param string $sort_criteria Sort criteria to check.
     * @return bool True if at least one product matches, false otherwise.
     */
    private function check_sorting_criteria_match( $product_ids, $sort_criteria ) {
        if ( empty( $product_ids ) ) {
            return false;
        }
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- IDs are sanitized with absint() via array_map.
        switch ( $sort_criteria ) {
            case 'in_stock':
                // Check if any products are in stock.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $in_stock_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta}\n\t\t\t\t\t\tWHERE post_id IN (" . implode( ',', array_map( 'absint', $product_ids ) ) . ")\n\t\t\t\t\t\tAND meta_key = '_stock_status'\n\t\t\t\t\t\tAND meta_value = %s", 'instock' ) );
                return $in_stock_count > 0;
            case 'best_selling':
                // Check if any products have sales.
                if ( !isset( $wpdb->wc_product_meta_lookup ) ) {
                    return true;
                    // Table doesn't exist, assume valid.
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $sales_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->wc_product_meta_lookup}\n\t\t\t\t\tWHERE product_id IN (" . implode( ',', array_map( 'absint', $product_ids ) ) . ')
					AND total_sales > 0' );
                return $sales_count > 0;
            case 'most_rated':
                // Check if any products have ratings.
                if ( !isset( $wpdb->wc_product_meta_lookup ) ) {
                    return true;
                    // Table doesn't exist, assume valid.
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rated_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->wc_product_meta_lookup}\n\t\t\t\t\tWHERE product_id IN (" . implode( ',', array_map( 'absint', $product_ids ) ) . ')
					AND rating_count > 0' );
                return $rated_count > 0;
            case 'price_low_high':
            case 'price_high_low':
                // Check if any products have a price.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $price_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}\n\t\t\t\t\tWHERE post_id IN (" . implode( ',', array_map( 'absint', $product_ids ) ) . ")\n\t\t\t\t\tAND meta_key = '_price'\n\t\t\t\t\tAND meta_value != ''" );
                return $price_count > 0;
            case 'on_sale':
                // Check if any products are on sale.
                $on_sale_count = 0;
                foreach ( $product_ids as $product_id ) {
                    $product = wc_get_product( $product_id );
                    if ( $product && $product->is_on_sale() ) {
                        ++$on_sale_count;
                        break;
                        // Found at least one, no need to check more.
                    }
                }
                return $on_sale_count > 0;
            // These criteria always apply to all products.
            case 'alphabetical_az':
            case 'latest':
            case 'oldest':
            case 'shuffle':
                return true;
            default:
                // Unknown criteria, assume it's valid.
                return true;
        }
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Handle product category changes to update presets
     *
     * When a product is moved to a different category or removed from a category,
     * update all presets for the old category to remove this product.
     *
     * @param int    $object_id  Product ID.
     * @param array  $terms      New term IDs.
     * @param array  $tt_ids     New term taxonomy IDs.
     * @param string $taxonomy   Taxonomy name.
     * @param bool   $append     Whether to append or replace terms.
     * @param array  $old_tt_ids Old term taxonomy IDs.
     */
    public function handle_product_category_change(
        $object_id,
        $terms,
        $tt_ids,
        $taxonomy,
        $append,
        $old_tt_ids
    ) {
        // Only handle product_cat taxonomy for published products.
        if ( 'product_cat' !== $taxonomy || 'product' !== get_post_type( $object_id ) ) {
            return;
        }
        // Get the old categories (before change).
        $old_categories = [];
        if ( !empty( $old_tt_ids ) ) {
            foreach ( $old_tt_ids as $tt_id ) {
                $term = get_term_by( 'term_taxonomy_id', $tt_id, 'product_cat' );
                if ( $term ) {
                    $old_categories[] = $term->term_id;
                }
            }
        }
        // Get the new categories (after change).
        $new_categories = [];
        if ( !empty( $tt_ids ) ) {
            foreach ( $tt_ids as $tt_id ) {
                $term = get_term_by( 'term_taxonomy_id', $tt_id, 'product_cat' );
                if ( $term ) {
                    $new_categories[] = $term->term_id;
                }
            }
        }
        // Find categories that were removed.
        $removed_categories = array_diff( $old_categories, $new_categories );
        if ( empty( $removed_categories ) ) {
            return;
            // No categories removed, nothing to update.
        }
        // Update presets for each removed category.
        foreach ( $removed_categories as $category_id ) {
            $this->remove_product_from_category_presets( $object_id, $category_id );
        }
    }

    /**
     * Remove a product from all presets of a specific category
     *
     * @param int $product_id  Product ID to remove.
     * @param int $category_id Category ID.
     */
    private function remove_product_from_category_presets( $product_id, $category_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwpp_sort_presets';
        // Check if presets table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }
        // Get all presets for this category.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $presets = $wpdb->get_results( $wpdb->prepare( 'SELECT id, preset_data, product_count FROM %i WHERE category_id = %d', $table_name, $category_id ) );
        if ( empty( $presets ) ) {
            return;
        }
        foreach ( $presets as $preset ) {
            $preset_data = json_decode( $preset->preset_data, true );
            // Skip if invalid data.
            if ( !$preset_data || !isset( $preset_data['orders'] ) ) {
                continue;
            }
            // Check if this product exists in the preset.
            if ( !isset( $preset_data['orders'][$product_id] ) ) {
                continue;
            }
            // Remove the product from orders.
            unset($preset_data['orders'][$product_id]);
            // Update metadata.
            $preset_data['metadata']['total_products'] = count( $preset_data['orders'] );
            // Update the preset in database.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [
                    'preset_data'   => wp_json_encode( $preset_data ),
                    'product_count' => count( $preset_data['orders'] ),
                    'updated_at'    => current_time( 'mysql' ),
                ],
                [
                    'id' => $preset->id,
                ],
                ['%s', '%d', '%s'],
                ['%d']
            );
        }
    }

}
