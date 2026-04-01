<?php
/**
 * Plugin Options
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="rwpp-tab-content">
	<h2><?php esc_html_e( 'Settings', 'rearrange-woocommerce-products' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Configure plugin behavior and display options.', 'rearrange-woocommerce-products' ); ?></p>

		<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore ?>
			<div class="rwpp-notice-success">
				<p><strong><?php esc_html_e( 'Changes have been saved.', 'rearrange-woocommerce-products' ); ?></strong></p>
			</div>
		<?php endif; ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'rwpp-settings-group' ); ?>
			<?php do_settings_sections( 'rwpp-settings-group' ); ?>

			<!-- Product Display Loop Settings -->
			<h3>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Product Display Loop', 'rearrange-woocommerce-products' ); ?>
			</h3>
			<p><?php esc_html_e( 'Choose which product loops will be affected by the custom sort order.', 'rearrange-woocommerce-products' ); ?></p>

			<?php $rwpp_effected_loops = esc_attr( get_option( 'rwpp_effected_loops' ) ); ?>

			<div class="rwpp-form-field">
				<label class="rwpp-form-label"><?php esc_html_e( 'Apply Sorting To', 'rearrange-woocommerce-products' ); ?></label>
				<fieldset>
					<p class="mr-1">
						<label>
							<input
								name="rwpp_effected_loops"
								type="radio"
								value="0"
								class="tog"
								<?php echo ( empty( $rwpp_effected_loops ) ) ? 'checked' : ''; ?>
							/>
							<?php esc_html_e( 'Main Loop Only', 'rearrange-woocommerce-products' ); ?>
						</label>
						<small class="rwpp-text-muted-gray rwpp-ml-24"><?php esc_html_e( 'Only applies to the main shop/category page', 'rearrange-woocommerce-products' ); ?></small>
					</p>
					<p class="rwpp-mt-12">
						<label>
							<input
								name="rwpp_effected_loops"
								type="radio"
								value="1"
								class="tog"
								<?php echo ( ! empty( $rwpp_effected_loops ) ) ? 'checked' : ''; ?>
							/>
							<?php esc_html_e( 'All Loops', 'rearrange-woocommerce-products' ); ?>
						</label>
						<small class="rwpp-text-muted-gray rwpp-ml-24"><?php esc_html_e( 'Including shortcodes and custom queries', 'rearrange-woocommerce-products' ); ?></small>
					</p>
					<p class="rwpp-mt-12">
						<code>[product_category category="my-category-slug"]</code>
					</p>
				</fieldset>
			</div>

			<div class="submit-btn-wrapper">
				<?php submit_button( __( 'Save Settings', 'rearrange-woocommerce-products' ), 'primary', 'submit', false ); ?>
			</div>

			<!-- Information Section -->
			<hr style="margin: 30px 0;">

			<h3>
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'About These Settings', 'rearrange-woocommerce-products' ); ?>
			</h3>

			<h4><?php esc_html_e( 'Main Loop Only (Recommended)', 'rearrange-woocommerce-products' ); ?></h4>
			<p><?php esc_html_e( 'Select this if you only want the custom sort order to apply to the main shop page and category pages. This is the recommended setting for most stores.', 'rearrange-woocommerce-products' ); ?></p>

			<h4><?php esc_html_e( 'All Loops', 'rearrange-woocommerce-products' ); ?></h4>
			<p><?php esc_html_e( 'Select this if you want the custom sort order to apply everywhere on your site, including:', 'rearrange-woocommerce-products' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Shop page', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'Category pages', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'WooCommerce shortcodes', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'Widgets', 'rearrange-woocommerce-products' ); ?></li>
			</ul>

			<div class="rwpp-notice-info">
				<p><strong><?php esc_html_e( 'Note:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Using "All Loops" may impact performance on sites with custom product queries or many shortcodes.', 'rearrange-woocommerce-products' ); ?></p>
			</div>
		</form>
</div>
