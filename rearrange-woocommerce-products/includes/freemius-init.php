<?php

/**
 * Freemius SDK Integration
 *
 * @package ReWooProducts
 */
// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !function_exists( 'rwpp_fs' ) ) {
    /**
     * Create a helper function for easy SDK access.
     *
     * @return Freemius
     */
    function rwpp_fs() {
        global $rwpp_fs;
        if ( !isset( $rwpp_fs ) ) {
            // Include Freemius SDK.
            // SDK is auto-loaded through Composer.
            $rwpp_fs = fs_dynamic_init( [
                'id'               => '22677',
                'slug'             => 'rearrange-woocommerce-products',
                'type'             => 'plugin',
                'public_key'       => 'pk_ab989221837901a23d0d123dbb55e',
                'is_premium'       => false,
                'premium_suffix'   => 'Pro',
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'trial'            => [
                    'days'               => 3,
                    'is_require_payment' => true,
                ],
                'menu'             => [
                    'slug'       => 'rwpp-page',
                    'first-path' => 'admin.php?page=rwpp-page',
                    'contact'    => true,
                    'support'    => false,
                ],
                'is_live'          => true,
                'is_org_compliant' => true,
            ] );
        }
        return $rwpp_fs;
    }

    // Init Freemius.
    rwpp_fs();
    // Signal that SDK was initiated.
    do_action( 'rwpp_fs_loaded' );
    /**
     * Uninstall cleanup function for Freemius integration.
     *
     * This function is called by Freemius after the uninstall event is reported to the server.
     * It cleans up all plugin data including options, postmeta, and custom tables.
     *
     * @return void
     */
    function rwpp_fs_uninstall_cleanup() {
        global $wpdb;
        // Delete plugin options.
        delete_option( 'rwpp_db_version' );
        delete_option( 'rwpp_migration_mode' );
        delete_option( 'rwpp_effected_loops' );
        // Delete postmeta (category-specific sort orders) - backwards compatibility.
        $rwpp_like_pattern = $wpdb->esc_like( 'rwpp_' ) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, $rwpp_like_pattern ) );
        // Drop custom table for product orders.
        $rwpp_table_name = $wpdb->prefix . 'rwpp_product_order';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $rwpp_table_name ) );
        // Drop custom table for sort presets.
        $rwpp_presets_table = $wpdb->prefix . 'rwpp_sort_presets';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $rwpp_presets_table ) );
    }

    // Hook uninstall cleanup to Freemius after_uninstall action.
    rwpp_fs()->add_action( 'after_uninstall', 'rwpp_fs_uninstall_cleanup' );
    /**
     * Hide contact menu for premium users.
     *
     * Premium users should use their own support channels instead of the Freemius contact form.
     *
     * @param bool   $is_visible Whether the submenu is visible.
     * @param string $menu_id    The submenu ID.
     * @return bool Whether the submenu should be visible.
     */
    function rwpp_hide_contact_menu_for_premium(  $is_visible, $menu_id  ) {
        if ( 'contact' === $menu_id ) {
            // Hide contact menu if user has premium access.
            return rwpp_fs()->can_use_premium_code__premium_only();
        }
        return $is_visible;
    }

    rwpp_fs()->add_filter(
        'is_submenu_visible',
        'rwpp_hide_contact_menu_for_premium',
        10,
        2
    );
}