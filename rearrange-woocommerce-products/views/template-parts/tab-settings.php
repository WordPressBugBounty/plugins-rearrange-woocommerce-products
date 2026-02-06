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
<div class="rwpp-scrollable-wrapper">
	<h3>Settings</h3>

	<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php esc_html_e( 'Changes have been saved.', 'rearrange-woocommerce-products' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<form action="options.php" method="post">
		<?php settings_fields( 'rwpp-settings-group' ); ?>
		<?php do_settings_sections( 'rwpp-settings-group' ); ?>

		<div class="rwpp-panels">
			<!-- Product Display Loop Settings -->
			<div class="rwpp-panel rwpp-settings-panel is-open">
				<div class="rwpp-panel__header">
					<h3 class="rwpp-panel__title">
						<span class="rwpp-panel__icon">🔄</span>
						Product Display Loop
					</h3>
					<span class="rwpp-panel__title-icon dashicons dashicons-arrow-down"></span>
				</div>
				<div class="rwpp-panel__content">
					<p><?php esc_html_e( 'Choose which product loops will be affected by the custom sort order.', 'rearrange-woocommerce-products' ); ?></p>

					<?php $rwpp_effected_loops = esc_attr( get_option( 'rwpp_effected_loops' ) ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Apply Sorting To', 'rearrange-woocommerce-products' ); ?></th>
								<td>
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
											<small style="color: #666; margin-left: 24px;"><?php esc_html_e( 'Only applies to the main shop/category page', 'rearrange-woocommerce-products' ); ?></small>
										</p>
										<p style="margin-top: 12px;">
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
											<small style="color: #666; margin-left: 24px;"><?php esc_html_e( 'Including shortcodes and custom queries', 'rearrange-woocommerce-products' ); ?></small>
										</p>
										<p style="margin-top: 12px;">
											<code>[product_category category="my-category-slug"]</code>
										</p>
									</fieldset>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="submit-btn-wrapper">
						<?php submit_button( __( 'Save Settings', 'rearrange-woocommerce-products' ), 'primary', 'submit', false ); ?>
					</div>
				</div>
			</div>

			<!-- Information Panel -->
			<div class="rwpp-panel rwpp-settings-panel">
				<div class="rwpp-panel__header">
					<h3 class="rwpp-panel__title">
						<span class="rwpp-panel__icon">ℹ️</span>
						About These Settings
					</h3>
					<span class="rwpp-panel__title-icon dashicons dashicons-arrow-down"></span>
				</div>
				<div class="rwpp-panel__content">
					<h4><?php esc_html_e( 'Main Loop Only (Recommended)', 'rearrange-woocommerce-products' ); ?></h4>
					<p><?php esc_html_e( 'Select this if you only want the custom sort order to apply to the main shop page and category pages. This is the recommended setting for most stores.', 'rearrange-woocommerce-products' ); ?></p>

					<h4><?php esc_html_e( 'All Loops', 'rearrange-woocommerce-products' ); ?></h4>
					<p><?php esc_html_e( 'Select this if you want the custom sort order to apply everywhere on your site, including:', 'rearrange-woocommerce-products' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Shop page', 'rearrange-woocommerce-products' ); ?></li>
						<li><?php esc_html_e( 'Category pages', 'rearrange-woocommerce-products' ); ?></li>
						<li><?php esc_html_e( 'WooCommerce shortcodes', 'rearrange-woocommerce-products' ); ?></li>
						<li><?php esc_html_e( 'Custom product queries', 'rearrange-woocommerce-products' ); ?></li>
					</ul>
					<p><strong><?php esc_html_e( 'Note:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Using "All Loops" may impact performance on sites with custom product queries or many shortcodes.', 'rearrange-woocommerce-products' ); ?></p>
				</div>
			</div>
		</div>
	</form>
</div>
