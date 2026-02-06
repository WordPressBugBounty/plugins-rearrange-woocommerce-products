<?php
/**
 * Product Box
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="rwpp-product" data-id="<?php echo esc_attr( $post->ID ); ?>">
	<div class="rwpp-product-handle">
		<span class="dashicons dashicons-menu"></span>
	</div>

	<div class="rwpp-product-image">
		<?php echo $product->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	
	<div class="rwpp-product-details">
		<div class="rwpp-product-name"><?php the_title(); ?></div>
		<div class="rwpp-product-meta">
			<span class="rwpp-product-sku">SKU: <?php echo $product->get_sku() ? esc_html( $product->get_sku() ) : '-'; ?></span>
			<?php if ( $product->is_in_stock() ) : ?>
				<span class="rwpp-product-stock instock"><?php esc_html_e( 'Instock', 'rearrange-woocommerce-products' ); ?></span>
			<?php else : ?>
				<span class="rwpp-product-stock outofstock"><?php esc_html_e( 'Outofstock', 'rearrange-woocommerce-products' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	
	<div class="rwpp-product-price">
		<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	
	<div class="rwpp-product-actions">
		<button class="rwpp-action-btn move-top" title="<?php esc_attr_e( 'Move to top', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-arrow-up-alt"></span>
		</button>
		<button class="rwpp-action-btn move-up" title="<?php esc_attr_e( 'Move up', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-arrow-up-alt2"></span>
		</button>
		<button class="rwpp-action-btn move-down" title="<?php esc_attr_e( 'Move down', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<button class="rwpp-action-btn move-bottom" title="<?php esc_attr_e( 'Move to bottom', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-arrow-down-alt"></span>
		</button>
		<a href="<?php the_permalink(); ?>" class="rwpp-btn-view" target="_blank" title="<?php esc_attr_e( 'View', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'rearrange-woocommerce-products' ); ?>
		</a>
		<a href="<?php echo esc_url( get_edit_post_link() ); ?>" class="rwpp-btn-edit" target="_blank" title="<?php esc_attr_e( 'Edit', 'rearrange-woocommerce-products' ); ?>">
			<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'rearrange-woocommerce-products' ); ?>
		</a>
	</div>
</div>
