<?php
/**
 * Upgrade Notice Template
 *
 * Displayed when users try to access pro features without a valid license.
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Get the feature name based on current page.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$feature_name = '';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
switch ( $current_page ) {
	case 'rwpp-smart-sort-page':
		$feature_name = __( 'Smart Sort', 'rearrange-woocommerce-products' );
		break;
	case 'rwpp-sort-presets-page':
		$feature_name = __( 'Presets', 'rearrange-woocommerce-products' );
		break;
	case 'rwpp-import-export-page':
		$feature_name = __( 'Import / Export', 'rearrange-woocommerce-products' );
		break;
	default:
		$feature_name = __( 'This Feature', 'rearrange-woocommerce-products' );
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Get pricing URL from Freemius.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$pricing_url = function_exists( 'rwpp_fs' ) ? rwpp_fs()->get_upgrade_url() : admin_url( 'admin.php?page=rwpp-page' );
?>
<div class="rwpp-tab-content rwpp-upgrade-notice-tab">
	<div class="rwpp-upgrade-page">
		<div class="rwpp-upgrade-content-wrapper">
			<!-- Header Section -->
		<div class="rwpp-upgrade-header">
			<div class="rwpp-upgrade-icon-circle">
				<span class="dashicons dashicons-lock"></span>
			</div>
			<h1 class="rwpp-upgrade-title"><?php esc_html_e( 'Unlock Premium Features', 'rearrange-woocommerce-products' ); ?></h1>
			<p class="rwpp-upgrade-subtitle">
				<?php esc_html_e( 'Watch how Presets, Smart Sort, and Import/Export work together to save time and boost sales.', 'rearrange-woocommerce-products' ); ?>
			</p>
			<p class="rwpp-upgrade-trust">
				<a href="https://wordpress.org/plugins/rearrange-woocommerce-products/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Trusted by 20,000+ active stores', 'rearrange-woocommerce-products' ); ?>
				</a>
			</p>
		</div>

		<!-- Video Section -->
		<div class="rwpp-upgrade-video-container">
			<div class="rwpp-demo-video">
				<iframe width="560" height="315" src="https://www.youtube.com/embed/ZZS-jXK_GJE" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
			</div>
		</div>

		<!-- Pro Features Grid -->
		<div class="rwpp-features-section">
			<h2 class="rwpp-features-title"><?php esc_html_e( 'Pro Features Include:', 'rearrange-woocommerce-products' ); ?></h2>

			<div class="rwpp-features-grid">
				<div class="rwpp-feature-card">
					<div class="rwpp-feature-icon rwpp-icon-orange">
						<span class="dashicons dashicons-sort"></span>
					</div>
					<h3><?php esc_html_e( 'Smart Sort', 'rearrange-woocommerce-products' ); ?></h3>
					<p><?php esc_html_e( 'Automatically sort products by best-selling, ratings, stock status, price, or custom rules.', 'rearrange-woocommerce-products' ); ?></p>
				</div>

				<div class="rwpp-feature-card">
					<div class="rwpp-feature-icon rwpp-icon-purple">
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<h3><?php esc_html_e( 'Presets', 'rearrange-woocommerce-products' ); ?></h3>
					<p><?php esc_html_e( 'Save your favorite product arrangements and apply them instantly across categories.', 'rearrange-woocommerce-products' ); ?></p>
				</div>

				<div class="rwpp-feature-card">
					<div class="rwpp-feature-icon rwpp-icon-blue">
						<span class="dashicons dashicons-database-import"></span>
					</div>
					<h3><?php esc_html_e( 'Import / Export', 'rearrange-woocommerce-products' ); ?></h3>
					<p><?php esc_html_e( 'Backup your product order or transfer it seamlessly between sites.', 'rearrange-woocommerce-products' ); ?></p>
				</div>

				<div class="rwpp-feature-card">
					<div class="rwpp-feature-icon rwpp-icon-green">
						<span class="dashicons dashicons-sos"></span>
					</div>
					<h3><?php esc_html_e( 'Priority Support', 'rearrange-woocommerce-products' ); ?></h3>
					<p><?php esc_html_e( 'Get fast, expert help whenever you need it with dedicated priority support.', 'rearrange-woocommerce-products' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Why Upgrade Section -->
		<div class="rwpp-why-upgrade">
			<div class="rwpp-why-upgrade-content">
				<h2>
					<span class="dashicons dashicons-arrow-up-alt"></span>
					<?php esc_html_e( 'Why Upgrade to Pro?', 'rearrange-woocommerce-products' ); ?>
				</h2>
				<p class="rwpp-why-upgrade-subtitle">
					<?php esc_html_e( 'Join thousands of stores that save 10+ hours every week managing their product catalog.', 'rearrange-woocommerce-products' ); ?>
				</p>

				<div class="rwpp-benefits-grid">
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Save hours arranging products', 'rearrange-woocommerce-products' ); ?></span>
					</div>
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Boost sales with smart ordering', 'rearrange-woocommerce-products' ); ?></span>
					</div>
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'One-click preset application', 'rearrange-woocommerce-products' ); ?></span>
					</div>
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Backup & restore with ease', 'rearrange-woocommerce-products' ); ?></span>
					</div>
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Priority email support', 'rearrange-woocommerce-products' ); ?></span>
					</div>
					<div class="rwpp-benefit-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Regular feature updates', 'rearrange-woocommerce-products' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Pricing Section -->
		<div class="rwpp-pricing-section">
			<h2 class="rwpp-pricing-title"><?php esc_html_e( 'Ready to Upgrade?', 'rearrange-woocommerce-products' ); ?></h2>
			<p class="rwpp-pricing-subtitle"><?php esc_html_e( 'Get instant access to all premium features', 'rearrange-woocommerce-products' ); ?></p>

			<a href="<?php echo esc_url( $pricing_url ); ?>" class="rwpp-pricing-cta">
				<?php esc_html_e( 'Upgrade Now', 'rearrange-woocommerce-products' ); ?>
			</a>
		</div>

		<!-- Testimonial Section -->
		<div class="rwpp-testimonial-carousel">
			<div class="rwpp-testimonial-carousel-wrapper">
				<div class="rwpp-testimonial-slides">
					<div class="rwpp-testimonial-slide active">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">M</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'medusa88', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"Great plugin. Very happy with the new update with product images, which makes it much easier to rearrange. Had a small issue which was fixed within a day – great support!"', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>

					<div class="rwpp-testimonial-slide">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">DD</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'DesignDNA', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"Easy to use and WORKS!!!"', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>

					<div class="rwpp-testimonial-slide">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">DO</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'dreamoutlaw', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"OMG! What a time saver! This was a perfect solution for me. After agonizing over the thought of manually rearranging the products in my Woocommerce site, I literally installed the plugin in seconds, and rearranged my products in seconds. Results were instantaneous. Not exaggerating. Very grateful!"', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>

					<div class="rwpp-testimonial-slide">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">TC</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'TomCobbley', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"Before I found this I spent ages trying to re-order my products in various categories. This plugin is easy to use and does a good job."', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>

					<div class="rwpp-testimonial-slide">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">YC</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'Yuriy Chamkoriyski', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"This plugin is very small, but very useful. It allows you to arrange your store products as you wish. The support from the author is simple outstanding! Fast and thoughfull! Thanks"', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>

					<div class="rwpp-testimonial-slide">
						<div class="rwpp-testimonial-card">
							<div class="rwpp-testimonial-header">
								<div class="rwpp-testimonial-avatar">AS</div>
								<div class="rwpp-testimonial-author">
									<div class="rwpp-author-name"><?php esc_html_e( 'Ahmad Syauqie', 'rearrange-woocommerce-products' ); ?></div>
								</div>
							</div>
							<p class="rwpp-testimonial-text">
								<?php esc_html_e( '"I tried so many attempts to arrange product orders on WooCommerce, none of them are working. After I found this plugin, and voila! It works like charms! Thanks dude, you saved my day!"', 'rearrange-woocommerce-products' ); ?>
							</p>
							<div class="rwpp-testimonial-rating">
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
								<span class="dashicons dashicons-star-filled"></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Dots Navigation -->
			<div class="rwpp-carousel-dots">
				<button class="rwpp-carousel-dot active" aria-label="<?php esc_attr_e( 'Go to testimonial 1', 'rearrange-woocommerce-products' ); ?>" data-slide="0"></button>
				<button class="rwpp-carousel-dot" aria-label="<?php esc_attr_e( 'Go to testimonial 2', 'rearrange-woocommerce-products' ); ?>" data-slide="1"></button>
				<button class="rwpp-carousel-dot" aria-label="<?php esc_attr_e( 'Go to testimonial 3', 'rearrange-woocommerce-products' ); ?>" data-slide="2"></button>
				<button class="rwpp-carousel-dot" aria-label="<?php esc_attr_e( 'Go to testimonial 4', 'rearrange-woocommerce-products' ); ?>" data-slide="3"></button>
				<button class="rwpp-carousel-dot" aria-label="<?php esc_attr_e( 'Go to testimonial 5', 'rearrange-woocommerce-products' ); ?>" data-slide="4"></button>
				<button class="rwpp-carousel-dot" aria-label="<?php esc_attr_e( 'Go to testimonial 6', 'rearrange-woocommerce-products' ); ?>" data-slide="5"></button>
			</div>
		</div>
		</div>
	</div>
</div>
