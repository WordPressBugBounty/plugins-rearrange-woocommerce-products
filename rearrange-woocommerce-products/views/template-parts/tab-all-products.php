<?php
/**
 * List all products
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Determine category ID (0 for global, or specific term_id).
$rwpp_current_term_id = 0;
if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_current_term_id = absint( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

// Define callbacks for custom table sorting.
$rwpp_join_callback = function ( $join ) use ( &$rwpp_current_term_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rwpp_product_order';
	$join      .= " LEFT JOIN {$table_name} AS rwpp_order
			   ON {$wpdb->posts}.ID = rwpp_order.product_id
			   AND rwpp_order.category_id = " . absint( $rwpp_current_term_id );
	return $join;
};

$rwpp_orderby_callback = function ( $orderby ) {
	global $wpdb;
	return "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
};

$rwpp_args = array(
	'post_type'      => [ 'product' ],
	'posts_per_page' => 100,
	'post_status'    => [ 'publish' ],
);

if ( $rwpp_current_term_id > 0 ) {
	$rwpp_args['tax_query'] = array( // phpcs:ignore
		[
			'taxonomy' => 'product_cat',
			'terms'    => [ $rwpp_current_term_id ],
			'field'    => 'id',
			'operator' => 'IN',
		],
	);
}

// Add filters to use custom table for sorting.
add_filter( 'posts_join', $rwpp_join_callback, 10, 1 );
add_filter( 'posts_orderby', $rwpp_orderby_callback, 10, 1 );

$rwpp_products = new WP_Query( $rwpp_args );

// Clean up filters after query.
remove_filter( 'posts_join', $rwpp_join_callback, 10 );
remove_filter( 'posts_orderby', $rwpp_orderby_callback, 10 );

if ( $rwpp_products->have_posts() ) : ?>
	<div class="rwpp-product-count">
		<?php
		/* translators: %d: number of products */
		printf( esc_html__( 'Found %d products', 'rearrange-woocommerce-products' ), absint( $rwpp_products->found_posts ) );
		?>
	</div>
	<div class="rwpp-scrollable-wrapper">
		<div id="rwpp-products-list" data-paged="1" data-max-pages="<?php echo esc_attr( $rwpp_products->max_num_pages ); ?>" data-term-id="<?php echo esc_attr( $rwpp_current_term_id ); ?>">
			<?php
			$rwpp_serial_no = 1;
			while ( $rwpp_products->have_posts() ) :
				$rwpp_products->the_post();
				global $post;
				$rwpp_product = wc_get_product( $post->ID ); // output escaped via WooCommerce wc_get_product().
				$product      = $rwpp_product; // Alias for product.php template.
				include 'product.php';
				++$rwpp_serial_no;
	endwhile;
			?>
		</div>
		<!-- Load More Button -->
		<?php if ( $rwpp_products->max_num_pages > 1 ) : ?>
		<div class="rwpp-load-more-container">
			<button id="rwpp-load-more-btn" class="button button-secondary">
				<?php esc_html_e( 'Load More Products', 'rearrange-woocommerce-products' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>

	<div class="rwpp-footer">
		<div class="rwpp-footer-actions">
			<button id="rwpp-save-orders" class="button button-primary button-large"><?php esc_html_e( 'Save Changes', 'rearrange-woocommerce-products' ); ?></button>
		</div>

		<p class="rwpp-important-note">
			<?php esc_html_e( 'Use "single click" to select multiple products and drag them.', 'rearrange-woocommerce-products' ); ?>
		</p>
	</div><!-- .rwpp-footer -->
	<?php
endif;

wp_reset_postdata();
