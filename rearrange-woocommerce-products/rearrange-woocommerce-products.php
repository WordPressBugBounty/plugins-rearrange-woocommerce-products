<?php
/**
 * Plugin Name: Rearrange Products for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/rearrange-woocommerce-products/
 * Description: A WordPress plugin to rearrange Products for WooCommerce listed on the Shop page with drag-and-drop functionality.
 * Version: 6.0.2
 * Requires at least: 6.6
 * Requires PHP: 7.4.0
 * Author: Aslam Doctor
 * Author URI: https://aslamdoctor.com/
 * License: GPL v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: rearrange-woocommerce-products
 * Domain Path: /languages
 *
 * WC requires at least: 4.3
 * WC tested up to: 10.9.4
 *
 * @package ReWooProducts
 */

/*
Rearrange Products for WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Rearrange Products for WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Rearrange Products for WooCommerce. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define reusable paths for plugin globally
 */
if ( ! defined( 'RWPP_LOCATION' ) ) {
	define( 'RWPP_LOCATION', __DIR__ );
}

if ( ! defined( 'RWPP_LOCATION_URL' ) ) {
	define( 'RWPP_LOCATION_URL', plugins_url( '', __FILE__ ) );
}

if ( ! defined( 'RWPP_BASENAME' ) ) {
	define( 'RWPP_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Freemius auto-deactivation mechanism.
 * This allows the SDK to automatically deactivate the free version when the premium version is activated.
 */
if ( function_exists( 'rwpp_fs' ) ) {
	// Another version of the plugin is already active.
	// Register this file's basename so Freemius can handle auto-deactivation.
	rwpp_fs()->set_basename( false, __FILE__ );
} else {
	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 */
	if ( ! function_exists( 'rwpp_fs' ) ) {
		/**
		 * Load Composer autoloader
		 */
		if ( file_exists( RWPP_LOCATION . '/vendor/autoload.php' ) ) {
			require_once RWPP_LOCATION . '/vendor/autoload.php';
		}

		/**
		 * Manually include premium-only class files (not PSR-4 compliant due to __premium_only suffix)
		 * Freemius will strip these files from free version builds
		 */
		if ( file_exists( RWPP_LOCATION . '/includes/SortPresets__premium_only.php' ) ) {
			require_once RWPP_LOCATION . '/includes/SortPresets__premium_only.php';
		}

		if ( file_exists( RWPP_LOCATION . '/includes/ImportExport__premium_only.php' ) ) {
			require_once RWPP_LOCATION . '/includes/ImportExport__premium_only.php';
		}

		/**
		 * Initialize Freemius SDK
		 */
		if ( file_exists( RWPP_LOCATION . '/includes/freemius-init.php' ) ) {
			require_once RWPP_LOCATION . '/includes/freemius-init.php';
		}
	}

	/**
	 * Initialize the plugin
	 */
	if ( ! function_exists( 'rwpp_init_plugin' ) ) {
		/**
		 * Initialize plugin
		 *
		 * @return void
		 */
		function rwpp_init_plugin() {
			$rwpp_plugin_obj = new \ReWooProducts\Plugin();
		}
	}

	rwpp_init_plugin();

	// Backward compatibility alias.
	if ( ! class_exists( 'ReWooProducts' ) ) {
		class_alias( 'ReWooProducts\Plugin', 'ReWooProducts' );
	}

	/**
	 * Plugin activation hook.
	 * Creates/updates database tables using dbDelta for safe migrations.
	 */
	register_activation_hook(
		__FILE__,
		function () {
			// Create or update presets table.
			\ReWooProducts\Database::create_presets_table();

			// Update database version.
			update_option( 'rwpp_db_version', '6.0.0' );
		}
	);

	/**
	 * Check database version and run migrations if needed.
	 * This ensures the table is created even if plugin is updated (not just activated).
	 */
	add_action(
		'plugins_loaded',
		function () {
			$current_db_version  = get_option( 'rwpp_db_version', '0' );
			$required_db_version = '6.0.0';

			if ( version_compare( $current_db_version, $required_db_version, '<' ) ) {
				// Create or update presets table.
				\ReWooProducts\Database::create_presets_table();

				// Update database version.
				update_option( 'rwpp_db_version', $required_db_version );
			}
		}
	);
}
