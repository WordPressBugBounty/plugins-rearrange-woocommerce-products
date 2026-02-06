<?php
/**
 * Page Header
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div id="rwpp-container">

	<h1><?php esc_html_e( 'Rearrange Woocommerce Products', 'rearrange-woocommerce-products' ); ?></h1>

	<div class="rwpp-header-wrapper">
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sort by Products', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-sortby-categories-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-sortby-categories-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sort by Categories', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-settings-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-settings-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-troubleshooting-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-troubleshooting-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Troubleshooting', 'rearrange-woocommerce-products' ); ?></a>

			<a href="https://www.aslamdoctor.com/" target="_blank" class="nav-tab"><img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '../img/icon-tea.png' ); ?>" alt=""><?php esc_html_e( 'Let\'s connect', 'rearrange-woocommerce-products' ); ?></a>
			
			<div class="rwpp-header-links">
				<a href="https://wordpress.org/plugins/rearrange-woocommerce-products/" target="_blank" class="rwpp-header-link">⭐ <?php esc_html_e( 'Rate Plugin', 'rearrange-woocommerce-products' ); ?></a>
				<a href="https://wordpress.org/support/plugin/rearrange-woocommerce-products/" target="_blank" class="rwpp-header-link">🧑‍💻 <?php esc_html_e( 'Get Support', 'rearrange-woocommerce-products' ); ?></a>
			</div>
		</h2>

	</div>
