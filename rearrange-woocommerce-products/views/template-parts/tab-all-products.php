<?php
/**
 * List all products
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if user has pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

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

$rwpp_orderby_callback = function ( $orderby ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	global $wpdb;
	return "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
};

$rwpp_args = [
	'post_type'      => [ 'product' ],
	'posts_per_page' => 100,
	'post_status'    => [ 'publish', 'private' ],
	'orderby'        => 'none',
];

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
add_filter( 'posts_join', $rwpp_join_callback, 100, 1 );
add_filter( 'posts_orderby', $rwpp_orderby_callback, 100, 1 );

$rwpp_products = new WP_Query( $rwpp_args );

// Clean up filters after query.
remove_filter( 'posts_join', $rwpp_join_callback, 100 );
remove_filter( 'posts_orderby', $rwpp_orderby_callback, 100 );

if ( $rwpp_products->have_posts() ) : ?>
	<div class="rwpp-product-count">
		<?php
		/* translators: %d: number of products */
		printf( esc_html__( 'Found %d products', 'rearrange-woocommerce-products' ), absint( $rwpp_products->found_posts ) );
		?>

		<!-- Selection Counter -->
		<span class="rwpp-selection-counter rwpp-text-primary" id="rwpp-selection-counter" style="display: none;">
			<span class="rwpp-selection-count">0</span>
			<span class="rwpp-selection-text">products selected</span>
		</span>
	</div>

	<div class="rwpp-scrollable-wrapper">
		<div id="rwpp-products-list" data-paged="1" data-max-pages="<?php echo esc_attr( $rwpp_products->max_num_pages ); ?>" data-term-id="<?php echo esc_attr( $rwpp_current_term_id ); ?>" data-total-products="<?php echo esc_attr( $rwpp_products->found_posts ); ?>">
			<?php
			$rwpp_serial_no = 1;
			while ( $rwpp_products->have_posts() ) :
				$rwpp_products->the_post();
				global $post;
				$rwpp_product = wc_get_product( $post->ID ); // output escaped via WooCommerce wc_get_product().
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$product = $rwpp_product; // Alias for product.php template.
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
			<button id="rwpp-save-as-preset" class="button button-secondary button-large" data-category-id="0" data-has-pro-license="<?php echo $has_pro_license ? '1' : '0'; ?>">
				<span class="dashicons dashicons-heart"></span>
				<?php esc_html_e( 'Save as Preset', 'rearrange-woocommerce-products' ); ?>
				<?php if ( ! $has_pro_license ) : ?>
					<span class="rwpp-pro-badge">PRO</span>
				<?php endif; ?>
			</button>
		</div>

		<p class="rwpp-important-note">
			<?php esc_html_e( 'Use "single click" to select multiple products and drag them.', 'rearrange-woocommerce-products' ); ?>
		</p>
	</div><!-- .rwpp-footer -->
<?php else : ?>
	<div class="rwpp-tab-content rwpp-smart-sort-content">
		<div class="rwpp-products-empty-state">
			<div class="rwpp-empty-icon"><span class="dashicons dashicons-products"></span></div>
			<h3><?php esc_html_e( 'No Products Available', 'rearrange-woocommerce-products' ); ?></h3>
			<p><?php esc_html_e( 'It looks like you don\'t have any published products yet. Create your first product in WooCommerce to start arranging your product order.', 'rearrange-woocommerce-products' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button button-primary">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'Create Product', 'rearrange-woocommerce-products' ); ?>
			</a>
		</div>
	</div>
	<?php
endif;

wp_reset_postdata();
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
				<button type="button" class="button" id="rwpp-confirm-cancel" data-micromodal-close>
					<?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?>
				</button>
				<button type="button" class="button button-primary" id="rwpp-confirm-yes">
					<?php esc_html_e( 'Save Changes', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Save as Preset Modal -->
<div class="rwpp-modal" id="rwpp-save-preset-modal" aria-hidden="true">
	<div class="rwpp-modal__overlay" tabindex="-1" data-micromodal-close>
		<div class="rwpp-modal__container" role="dialog" aria-modal="true" aria-labelledby="rwpp-preset-modal-title">
			<div class="rwpp-modal__header">
				<h2 id="rwpp-preset-modal-title"><?php esc_html_e( 'Save Current Order as Preset', 'rearrange-woocommerce-products' ); ?></h2>
				<button type="button" class="rwpp-modal-close">&times;</button>
			</div>
			<div class="rwpp-modal__content" id="rwpp-preset-modal-content">
				<input type="hidden" id="rwpp-preset-category-id" value="0">
				<div class="rwpp-form-field">
					<label class="rwpp-form-label" for="rwpp-preset-name">
						<?php esc_html_e( 'Preset Name:', 'rearrange-woocommerce-products' ); ?> <span class="rwpp-text-danger">*</span>
					</label>
					<input type="text" id="rwpp-preset-name" class="widefat" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Black Friday 2026', 'rearrange-woocommerce-products' ); ?>" required>
					<span class="rwpp-char-counter" id="rwpp-preset-name-counter">0/100</span>
				</div>
				<div class="rwpp-form-field">
					<label class="rwpp-form-label" for="rwpp-preset-description">
						<?php esc_html_e( 'Description (optional):', 'rearrange-woocommerce-products' ); ?>
					</label>
					<textarea id="rwpp-preset-description" class="widefat" rows="3" maxlength="200" placeholder="<?php esc_attr_e( 'Brief description of this arrangement.', 'rearrange-woocommerce-products' ); ?>"></textarea>
					<span class="rwpp-char-counter" id="rwpp-preset-description-counter">0/200</span>
				</div>
				<div class="rwpp-notice-info">
					<p>
						<?php esc_html_e( 'This will save the current product arrangement. You can restore it later from the Sort Presets page.', 'rearrange-woocommerce-products' ); ?>
					</p>
				</div>
			</div>
			<div class="rwpp-modal__footer">
				<button type="button" class="button" id="rwpp-preset-cancel" data-micromodal-close>
					<?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?>
				</button>
				<button type="button" class="button button-primary" id="rwpp-preset-save">
					<?php esc_html_e( 'Save Preset', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
