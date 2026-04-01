<?php
/**
 * List all products based on selected category
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if user has pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

$rwpp_selected      = 0;
$rwpp_selected_name = __( 'Select Product Category', 'rearrange-woocommerce-products' );
if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_selected = sanitize_text_field( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_term     = get_term( $rwpp_selected, 'product_cat' );
	if ( $rwpp_term && ! is_wp_error( $rwpp_term ) ) {
		$rwpp_selected_name = $rwpp_term->name;
	}
}

// Get all product categories hierarchically.
$rwpp_categories = get_terms(
	[
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);

/**
 * Build hierarchical array.
 *
 * @param array $rwpp_categories List of category terms.
 * @param int   $parent_id       Parent term ID.
 * @return array
 */
function rwpp_build_category_tree( $rwpp_categories, $parent_id = 0 ) {
	$branch = [];
	foreach ( $rwpp_categories as $category ) {
		if ( $category->parent === $parent_id ) {
			$children = rwpp_build_category_tree( $rwpp_categories, $category->term_id );
			if ( $children ) {
				$category->children = $children;
			}
			$branch[] = $category;
		}
	}
	return $branch;
}

/**
 * Check if a category or any of its descendants is selected.
 *
 * @param object $category      Category term object.
 * @param int    $rwpp_selected Selected term ID.
 * @return bool
 */
function rwpp_has_selected_descendant( $category, $rwpp_selected ) {
	if ( $category->term_id === (int) $rwpp_selected ) {
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

/**
 * Render category menu recursively.
 *
 * @param array $rwpp_categories List of category terms.
 * @param int   $rwpp_selected   Selected term ID.
 */
function rwpp_render_category_menu( $rwpp_categories, $rwpp_selected ) {
	if ( empty( $rwpp_categories ) ) {
		return;
	}
	echo '<ul class="rwpp-dropdown-menu">';
	foreach ( $rwpp_categories as $category ) {
		$has_children       = isset( $category->children ) && ! empty( $category->children );
		$is_selected        = ( (int) $rwpp_selected === $category->term_id );
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
$rwpp_join_callback   = function ( $join ) use ( &$rwpp_current_term_id ) {
	if ( $rwpp_current_term_id > 0 ) {
		global $wpdb;
		// Use the Database class to get the correct table name.
		$table_name  = $wpdb->prefix . 'rwpp_product_order';
		$category_id = absint( $rwpp_current_term_id );
		$join       .= " LEFT JOIN {$table_name} AS rwpp_order
				   ON {$wpdb->posts}.ID = rwpp_order.product_id
				   AND rwpp_order.category_id = {$category_id}";

		// Fallback: also join postmeta for unmigrated category sort data.
		$meta_key = 'rwpp_sortorder_' . $category_id;
		$join    .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS rwpp_meta
			  ON {$wpdb->posts}.ID = rwpp_meta.post_id
			  AND rwpp_meta.meta_key = %s",
			$meta_key
		);
	}
	return $join;
};

$rwpp_orderby_callback = function ( $orderby ) use ( &$rwpp_current_term_id ) {
	if ( $rwpp_current_term_id > 0 ) {
		global $wpdb;
		// Category sorting: custom_order -> legacy postmeta -> menu_order -> unsorted.
		return "COALESCE(rwpp_order.sort_order, CAST(rwpp_meta.meta_value AS UNSIGNED), {$wpdb->posts}.menu_order, 9999) ASC, {$wpdb->posts}.post_title ASC";
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
		$rwpp_args = [
			'post_type'      => [ 'product' ],
			'posts_per_page' => 100,
			'post_status'    => [ 'publish', 'private' ],
			'orderby'        => 'none',
			'tax_query'      => array( // phpcs:ignore
				[
					'taxonomy'         => 'product_cat',
					'terms'            => [ $rwpp_current_term_id ],
					'field'            => 'id',
					'operator'         => 'IN',
					'include_children' => true,
				],
			),
		];

		// Add filters to use custom table for sorting.
		add_filter( 'posts_join', $rwpp_join_callback, 100, 1 );
		add_filter( 'posts_orderby', $rwpp_orderby_callback, 100, 1 );

		$rwpp_products = new WP_Query( $rwpp_args );

		// Clean up filters after query.
		remove_filter( 'posts_join', $rwpp_join_callback, 100 );
		remove_filter( 'posts_orderby', $rwpp_orderby_callback, 100 );

		if ( $rwpp_products->have_posts() ) :
			?>
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
			<?php
		endif;
	endif;
	?>
</div>

<?php
if ( isset( $_GET['term_id'] ) && ! empty( $_GET['term_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
	$rwpp_current_term_id = absint( wp_unslash( $_GET['term_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	// Re-apply filters for the second query.
	add_filter( 'posts_join', $rwpp_join_callback, 100, 1 );
	add_filter( 'posts_orderby', $rwpp_orderby_callback, 100, 1 );

	$rwpp_products = new WP_Query( $rwpp_args );

	// Clean up filters after query.
	remove_filter( 'posts_join', $rwpp_join_callback, 100 );
	remove_filter( 'posts_orderby', $rwpp_orderby_callback, 100 );

	if ( $rwpp_products->have_posts() ) :
		?>

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
			<button id="rwpp-save-as-preset" class="button button-secondary button-large" data-category-id="<?php echo isset( $rwpp_current_term_id ) ? esc_attr( $rwpp_current_term_id ) : '0'; ?>" data-has-pro-license="<?php echo $has_pro_license ? '1' : '0'; ?>">
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
		<?php
	else :
		?>
	<div class="rwpp-scrollable-wrapper">
		<div class="rwpp-notice-warning">
			<p><?php esc_html_e( 'No products found in this category.', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</div>
		<?php
	endif;

	wp_reset_postdata();
else :
	?>
	<div class="rwpp-scrollable-wrapper">
		<div class="rwpp-notice-info">
			<p><?php esc_html_e( 'Please select a product category from the dropdown above to view and rearrange products.', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</div>
	<?php
endif;
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
				<input type="hidden" id="rwpp-preset-category-id" value="<?php echo isset( $_GET['term_id'] ) ? esc_attr( absint( $_GET['term_id'] ) ) : '0'; // phpcs:ignore WordPress.Security.NonceVerification ?>">
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
