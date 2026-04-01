<?php
/**
 * Smart Sort Tab
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if user has pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

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
 * Build hierarchical array - reuse function from tab-category-products.php.
 *
 * @param array $rwpp_categories List of category terms.
 * @param int   $parent_id       Parent term ID.
 * @return array
 */
function rwpp_ss_build_category_tree( $rwpp_categories, $parent_id = 0 ) {
	$branch = [];
	foreach ( $rwpp_categories as $category ) {
		if ( $category->parent === $parent_id ) {
			$children = rwpp_ss_build_category_tree( $rwpp_categories, $category->term_id );
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
function rwpp_ss_has_selected_descendant( $category, $rwpp_selected ) {
	if ( $category->term_id === (int) $rwpp_selected ) {
		return true;
	}
	if ( isset( $category->children ) && ! empty( $category->children ) ) {
		foreach ( $category->children as $child ) {
			if ( rwpp_ss_has_selected_descendant( $child, $rwpp_selected ) ) {
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
function rwpp_ss_render_category_menu( $rwpp_categories, $rwpp_selected ) {
	if ( empty( $rwpp_categories ) ) {
		return;
	}
	echo '<ul class="rwpp-dropdown-menu">';
	foreach ( $rwpp_categories as $category ) {
		$has_children       = isset( $category->children ) && ! empty( $category->children );
		$is_selected        = ( (int) $rwpp_selected === $category->term_id );
		$has_selected_child = $has_children && rwpp_ss_has_selected_descendant( $category, $rwpp_selected ) && ! $is_selected;
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
				rwpp_ss_render_category_menu( $category->children, $rwpp_selected );
			}
			?>
		</li>
		<?php
	}
	echo '</ul>';
}

$rwpp_category_tree = rwpp_ss_build_category_tree( $rwpp_categories );
?>

<div class="rwpp-tab-content rwpp-smart-sort-content">
	<h2>
		<?php esc_html_e( 'Smart Sort', 'rearrange-woocommerce-products' ); ?>
		<?php if ( ! $has_pro_license ) : ?>
			<span class="rwpp-pro-badge">PRO</span>
		<?php endif; ?>
	</h2>
	<p class="description"><?php esc_html_e( 'Sort products in bulk based on predefined conditions.', 'rearrange-woocommerce-products' ); ?></p>

		<div class="rwpp-smart-sort-form">
			<!-- Apply To Section -->
			<div class="rwpp-form-field">
				<label class="rwpp-form-label"><?php esc_html_e( 'Apply sort order to:', 'rearrange-woocommerce-products' ); ?></label>
				<div class="rwpp-radio-group">
					<label class="rwpp-radio-label">
						<input type="radio" name="rwpp_smart_sort_apply_to" value="all" checked>
						<span><?php esc_html_e( 'All Products', 'rearrange-woocommerce-products' ); ?></span>
					</label>
					<label class="rwpp-radio-label">
						<input type="radio" name="rwpp_smart_sort_apply_to" value="category">
						<span><?php esc_html_e( 'Products under selected category', 'rearrange-woocommerce-products' ); ?></span>
					</label>
				</div>
			</div>

			<!-- Category Selection -->
			<div class="rwpp-form-field rwpp-category-selection rwpp-hidden">
				<label class="rwpp-form-label"><?php esc_html_e( 'Select Category:', 'rearrange-woocommerce-products' ); ?></label>
				<div class="rwpp-custom-dropdown">
					<button type="button" class="rwpp-dropdown-toggle" id="rwpp_smart_sort_category">
						<span class="rwpp-dropdown-text"><?php esc_html_e( 'Select Product Category', 'rearrange-woocommerce-products' ); ?></span>
						<span class="rwpp-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<div class="rwpp-dropdown-content">
						<?php rwpp_ss_render_category_menu( $rwpp_category_tree, 0 ); ?>
					</div>
				</div>
				<input type="hidden" id="rwpp_smart_sort_category_id" value="">
			</div>

			<!-- Sort Order -->
			<div class="rwpp-form-field">
				<label class="rwpp-form-label" for="rwpp_primary_sort_order">
					<?php esc_html_e( 'Sort Order:', 'rearrange-woocommerce-products' ); ?>
				</label>
				<select id="rwpp_primary_sort_order" class="rwpp-select">
					<option value="" disabled selected><?php esc_html_e( 'Select Sort Order', 'rearrange-woocommerce-products' ); ?></option>
					<option value="best_selling"><?php esc_html_e( 'Best Selling First', 'rearrange-woocommerce-products' ); ?></option>
					<option value="most_rated"><?php esc_html_e( 'Most Rated First', 'rearrange-woocommerce-products' ); ?></option>
					<option value="alphabetical_az"><?php esc_html_e( 'Alphabetical A-Z', 'rearrange-woocommerce-products' ); ?></option>
					<option value="in_stock"><?php esc_html_e( 'In Stock First', 'rearrange-woocommerce-products' ); ?></option>
					<option value="price_low_high"><?php esc_html_e( 'Price: Low to High', 'rearrange-woocommerce-products' ); ?></option>
					<option value="price_high_low"><?php esc_html_e( 'Price: High to Low', 'rearrange-woocommerce-products' ); ?></option>
					<option value="latest"><?php esc_html_e( 'Latest First', 'rearrange-woocommerce-products' ); ?></option>
					<option value="oldest"><?php esc_html_e( 'Oldest First', 'rearrange-woocommerce-products' ); ?></option>				<option value="shuffle"><?php esc_html_e( 'Shuffle (Random)', 'rearrange-woocommerce-products' ); ?></option>				</select>
			</div>

			<!-- Apply Button -->
			<div class="rwpp-form-field">
				<button id="rwpp-apply-smart-sort" class="button button-primary button-large" disabled>
					<?php esc_html_e( 'Apply Smart Sort', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>

			<!-- Info Message -->
			<div class="rwpp-notice-info">
				<p>
					<?php esc_html_e( 'This is a one-time action. Apply Smart Sort again for new or updated products.', 'rearrange-woocommerce-products' ); ?><br>
					<?php esc_html_e( 'You can manually reorder products anytime after sorting.', 'rearrange-woocommerce-products' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>

<!-- Confirmation Modal -->
<div class="rwpp-modal" id="rwpp-smart-sort-modal" aria-hidden="true">
	<div class="rwpp-modal__overlay" tabindex="-1" data-micromodal-close>
		<div class="rwpp-modal__container" role="dialog" aria-modal="true" aria-labelledby="rwpp-smart-sort-modal-title">
			<div class="rwpp-modal__header">
				<h2 id="rwpp-smart-sort-modal-title"><?php esc_html_e( 'Confirm Smart Sort', 'rearrange-woocommerce-products' ); ?></h2>
				<button class="rwpp-modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'rearrange-woocommerce-products' ); ?>" data-micromodal-close>✕</button>
			</div>
			<div class="rwpp-modal__content" id="rwpp-smart-sort-modal-content">
				<p><?php esc_html_e( 'Are you sure you want to apply Smart Sort? This will rearrange your products based on the selected criteria.', 'rearrange-woocommerce-products' ); ?></p>
			</div>
			<div class="rwpp-modal__footer" id="rwpp-smart-sort-modal-footer">
				<button type="button" class="button" id="rwpp-smart-sort-cancel" data-micromodal-close>
					<?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?>
				</button>
				<button type="button" class="button button-primary" id="rwpp-smart-sort-confirm">
					<?php esc_html_e( 'Apply Smart Sort', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
