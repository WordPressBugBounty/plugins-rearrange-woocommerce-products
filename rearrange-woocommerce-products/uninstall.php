<?php
// exit if uninstall constant is not defined
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin options
delete_option( 'rwpp_db_version' );
delete_option( 'rwpp_migration_mode' );
delete_option( 'rwpp_effected_loops' );

// Delete postmeta (category-specific sort orders) - backwards compatibility.
$rwpp_sql_query = "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE 'rwpp_%'";
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $rwpp_sql_query );

// Drop custom table for product orders.
$rwpp_table_name = $wpdb->prefix . 'rwpp_product_order';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$rwpp_table_name}" );
