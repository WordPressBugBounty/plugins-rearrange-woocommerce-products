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

<!-- Confirmation Modal -->
<div class="rwpp-modal" id="rwpp-confirm-modal" aria-hidden="true">
	<div class="rwpp-modal__overlay" tabindex="-1" data-micromodal-close>
		<div class="rwpp-modal__container" role="dialog" aria-modal="true" aria-labelledby="rwpp-modal-title">
			<div class="rwpp-modal__header">
				<h2 id="rwpp-modal-title"><?php esc_html_e( 'Confirm Changes', 'rearrange-woocommerce-products' ); ?></h2>
				<button class="rwpp-modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'rearrange-woocommerce-products' ); ?>" data-micromodal-close>✕</button>
			</div>
			<div class="rwpp-modal__content" id="rwpp-modal-content">
				<p><?php esc_html_e( 'Are you sure you want to save the product order changes?', 'rearrange-woocommerce-products' ); ?></p>
			</div>
			<div class="rwpp-modal__footer">
				<button type="button" class="rwpp-modal__btn" id="rwpp-confirm-cancel" data-micromodal-close>
					<?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?>
				</button>
				<button type="button" class="rwpp-modal__btn rwpp-modal__btn--primary" id="rwpp-confirm-yes">
					<?php esc_html_e( 'Save Changes', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

</div>
