<?php
/**
 * Page Footer
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!-- Page Navigation Buttons -->
<div class="rwpp-page-nav-buttons">
	<button id="rwpp-scroll-to-top" class="rwpp-page-nav-btn rwpp-scroll-top" style="display: none;" aria-label="<?php esc_attr_e( 'Back to Top', 'rearrange-woocommerce-products' ); ?>">
		<span class="dashicons dashicons-arrow-up-alt2"></span>
		<span class="rwpp-nav-label"><?php esc_html_e( 'Back to Top', 'rearrange-woocommerce-products' ); ?></span>
	</button>
	<button id="rwpp-scroll-to-bottom" class="rwpp-page-nav-btn rwpp-scroll-bottom" style="display: none;" aria-label="<?php esc_attr_e( 'Jump to Bottom', 'rearrange-woocommerce-products' ); ?>">
		<span class="dashicons dashicons-arrow-down-alt2"></span>
		<span class="rwpp-nav-label"><?php esc_html_e( 'Jump to Bottom', 'rearrange-woocommerce-products' ); ?></span>
	</button>
</div>
</div>
