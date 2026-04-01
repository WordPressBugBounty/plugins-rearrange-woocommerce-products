<?php
/**
 * Page Header
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if user has pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

// Get support URL based on license status.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$support_url = $has_pro_license && function_exists( 'rwpp_fs' ) ? rwpp_fs()->contact_url() : 'https://wordpress.org/support/plugin/rearrange-woocommerce-products/';
?>
<div id="rwpp-container">

	<div class="rwpp-title-wrapper">
		<h1><?php esc_html_e( 'Rearrange Products for WooCommerce', 'rearrange-woocommerce-products' ); ?></h1>

		<div class="rwpp-header-links">
			<a href="https://wordpress.org/plugins/rearrange-woocommerce-products/" target="_blank" class="rwpp-header-link"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Rate Plugin', 'rearrange-woocommerce-products' ); ?></a>
			<a href="<?php echo esc_url( $support_url ); ?>" <?php echo ! $has_pro_license ? 'target="_blank"' : ''; ?> class="rwpp-header-link"><span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Get Support', 'rearrange-woocommerce-products' ); ?></a>
		</div>
	</div>

	<div class="rwpp-header-wrapper">
		<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Tab navigation display only. ?>
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sort by Products', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-sortby-categories-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-sortby-categories-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sort by Categories', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-sort-presets-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-sort-presets-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Presets', 'rearrange-woocommerce-products' ); ?>
				<?php if ( ! $has_pro_license ) : ?>
					<span class="rwpp-pro-badge">PRO</span>
				<?php endif; ?>
			</a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-smart-sort-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-smart-sort-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Smart Sort', 'rearrange-woocommerce-products' ); ?>
				<?php if ( ! $has_pro_license ) : ?>
					<span class="rwpp-pro-badge">PRO</span>
				<?php endif; ?>
			</a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-import-export-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-import-export-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Import / Export', 'rearrange-woocommerce-products' ); ?>
				<?php if ( ! $has_pro_license ) : ?>
					<span class="rwpp-pro-badge">PRO</span>
				<?php endif; ?>
			</a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-settings-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-settings-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'rearrange-woocommerce-products' ); ?></a>

			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=rwpp-troubleshooting-page' ) ); ?>" class="nav-tab <?php echo ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-troubleshooting-page' === $_GET['page'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Troubleshooting', 'rearrange-woocommerce-products' ); ?></a>
		<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
		</h2>
	</div>
