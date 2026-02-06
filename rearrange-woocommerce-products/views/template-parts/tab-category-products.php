<?php
/**
 * List all products based on selected category
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$rwpp_selected = 0;
$rwpp_selected_name = __( 'Select Product Category', 'rearrange-woocommerce-products' );
if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_selected = sanitize_text_field( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_term = get_term( $rwpp_selected, 'product_cat' );
	if ( $rwpp_term && ! is_wp_error( $rwpp_term ) ) {
		$rwpp_selected_name = $rwpp_term->name;
	}
}

// Get all product categories hierarchically.
$rwpp_categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);

// Build hierarchical array.
function rwpp_build_category_tree( $rwpp_categories, $parent_id = 0 ) {
	$branch = array();
	foreach ( $rwpp_categories as $category ) {
		if ( $category->parent == $parent_id ) {
			$children = rwpp_build_category_tree( $rwpp_categories, $category->term_id );
			if ( $children ) {
				$category->children = $children;
			}
			$branch[] = $category;
		}
	}
	return $branch;
}

// Check if a category or any of its descendants is selected.
function rwpp_has_selected_descendant( $category, $rwpp_selected ) {
	if ( $category->term_id == $rwpp_selected ) {
		return true;
	}
	if ( isset( $category->children ) && ! empty( $category->children ) ) {
		foreach ( $category->children as $child ) {
			if ( rwpp_has_selected_descendant( $child, $rwpp_selected ) ) {
				return true;
			}
		}
	}
	return false;
}

// Render category menu recursively.
function rwpp_render_category_menu( $rwpp_categories, $rwpp_selected ) {
	if ( empty( $rwpp_categories ) ) {
		return;
	}
	echo '<ul class="rwpp-dropdown-menu">';
	foreach ( $rwpp_categories as $category ) {
		$has_children = isset( $category->children ) && ! empty( $category->children );
		$is_selected = ( $rwpp_selected == $category->term_id );
		$has_selected_child = $has_children && rwpp_has_selected_descendant( $category, $rwpp_selected ) && ! $is_selected;
		?>
		<li class="<?php echo $has_children ? 'has-children' : ''; ?><?php echo $is_selected ? ' selected' : ''; ?><?php echo $has_selected_child ? ' has-selected-child' : ''; ?>">
			<a href="#" data-term-id="<?php echo esc_attr( $category->term_id ); ?>" data-term-name="<?php echo esc_attr( $category->name ); ?>">
				<?php echo esc_html( $category->name ); ?>
				<?php if ( $has_children ) : ?>
					<span class="arrow dashicons dashicons-arrow-right-alt2"></span>
				<?php endif; ?>
			</a>
			<?php
			if ( $has_children ) {
				rwpp_render_category_menu( $category->children, $rwpp_selected );
			}
			?>
		</li>
		<?php
	}
	echo '</ul>';
}

$rwpp_category_tree = rwpp_build_category_tree( $rwpp_categories );

// Define callbacks that will be used for both queries.
$rwpp_current_term_id = 0;
$rwpp_join_callback = function( $join ) use ( &$rwpp_current_term_id ) {
	if ( $rwpp_current_term_id > 0 ) {
		global $wpdb;
		// Use the Database class to get the correct table name.
		$table_name = $wpdb->prefix . 'rwpp_product_order';
		$join .= " LEFT JOIN {$table_name} AS rwpp_order
				   ON {$wpdb->posts}.ID = rwpp_order.product_id
				   AND rwpp_order.category_id = " . absint( $rwpp_current_term_id );
	}
	return $join;
};

$rwpp_orderby_callback = function( $orderby ) use ( &$rwpp_current_term_id ) {
	if ( $rwpp_current_term_id > 0 ) {
		global $wpdb;
		return "COALESCE(rwpp_order.sort_order, {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
	}
	return $orderby;
};
?>

<div class="rwpp-category-filter">
	<div class="rwpp-custom-dropdown">
		<button type="button" class="rwpp-dropdown-toggle" id="rwpp_product_category">
			<span class="rwpp-dropdown-text"><?php echo esc_html( $rwpp_selected_name ); ?></span>
			<span class="rwpp-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div class="rwpp-dropdown-content">
			<?php rwpp_render_category_menu( $rwpp_category_tree, $rwpp_selected ); ?>
		</div>
	</div>
	<?php

	if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
		$rwpp_current_term_id = absint( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		// Use custom table for sorting (matching frontend behavior).
		$rwpp_args = array(
			'post_type'      => array( 'product' ),
			'posts_per_page' => 100,
			'post_status'    => array( 'publish' ),
			'tax_query'      => array( // phpcs:ignore
				array(
					'taxonomy'         => 'product_cat',
					'terms'            => array( $rwpp_current_term_id ),
					'field'            => 'id',
					'operator'         => 'IN',
					'include_children' => true,
				),
			),
		);

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
		<?php
		endif;
	endif;
	?>
</div>

<?php
if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_current_term_id = absint( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	// Re-apply filters for the second query.
	add_filter( 'posts_join', $rwpp_join_callback, 10, 1 );
	add_filter( 'posts_orderby', $rwpp_orderby_callback, 10, 1 );

	$rwpp_products = new WP_Query( $rwpp_args );

	// Clean up filters after query.
	remove_filter( 'posts_join', $rwpp_join_callback, 10 );
	remove_filter( 'posts_orderby', $rwpp_orderby_callback, 10 );
	
	if ( $rwpp_products->have_posts() ) : ?>
	<div class="rwpp-scrollable-wrapper">
		<div id="rwpp-products-list" data-paged="1" data-max-pages="<?php echo esc_attr( $rwpp_products->max_num_pages ); ?>" data-term-id="<?php echo esc_attr( $rwpp_current_term_id ); ?>">
			<?php
			$rwpp_serial_no = 1;
			while ( $rwpp_products->have_posts() ) :
				$rwpp_products->the_post();
				global $post;
				$rwpp_product = wc_get_product( $post->ID ); // output escaped via WooCommerce wc_get_product().
			$product = $rwpp_product; // Alias for product.php template.
			include 'product.php';
				$rwpp_serial_no++;
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
	else : ?>
	<div class="rwpp-scrollable-wrapper">
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'No products found in this category.', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</div>
	<?php
	endif;

	wp_reset_postdata();
else : ?>
	<div class="rwpp-scrollable-wrapper">
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Please select a product category from the dropdown above to view and rearrange products.', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</div>
	<?php
endif;

