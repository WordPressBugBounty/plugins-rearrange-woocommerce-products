<?php
/**
 * Plugin Name: Rearrange Products for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/rearrange-woocommerce-products/
 * Description: A WordPress plugin to rearrange WooCommerce products listed on the Shop page with drag-and-drop functionality.
 * Version: 5.0.9
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
 * WC tested up to: 10.4.3
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
 * Load Composer autoloader
 */
if ( file_exists( RWPP_LOCATION . '/vendor/autoload.php' ) ) {
	require_once RWPP_LOCATION . '/vendor/autoload.php';
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
