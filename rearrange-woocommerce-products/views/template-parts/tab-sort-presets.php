<?php
/**
 * Sort Presets Tab Template
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ReWooProducts\SortPresets;

// Check if user has pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

// Get all presets (no pagination).
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$presets = SortPresets::get_all_presets( [ 'per_page' => -1 ] );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$preset_count = count( $presets );
?>

<div class="rwpp-tab-content">
	<h2>
		<?php esc_html_e( 'Sort Presets', 'rearrange-woocommerce-products' ); ?>
		<?php if ( ! $has_pro_license ) : ?>
			<span class="rwpp-pro-badge">PRO</span>
		<?php endif; ?>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Manage your saved product arrangements. Save current sort orders and restore them anytime.', 'rearrange-woocommerce-products' ); ?>
	</p>

		<div class="rwpp-presets-toolbar">
			<div class="rwpp-presets-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwpp-page' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'Create New Preset', 'rearrange-woocommerce-products' ); ?>
				</a>
			</div>

			<div class="rwpp-presets-search">
				<input type="search" id="rwpp-preset-search" class="rwpp-preset-search-input" placeholder="<?php esc_attr_e( 'Search presets...', 'rearrange-woocommerce-products' ); ?>">
			</div>
		</div>

		<?php if ( $preset_count > 0 ) : ?>
			<div class="rwpp-presets-list" id="rwpp-presets-list">
				<?php
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				foreach ( $presets as $preset ) :
					?>
					<?php
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$category_name = __( 'Global Sort', 'rearrange-woocommerce-products' );
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$category_slug = '';
					if ( $preset->category_id > 0 ) {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited
						$term = get_term( $preset->category_id, 'product_cat' );
						if ( $term && ! is_wp_error( $term ) ) {
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$category_name = sprintf(
								/* translators: %s: category name */
								__( 'Category: "%s"', 'rearrange-woocommerce-products' ),
								$term->name
							);
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$category_slug = $term->slug;
						}
					}

					// Generate shortcode based on preset type.
					if ( $preset->category_id > 0 && ! empty( $category_slug ) ) {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						$shortcode = '[product_category category="' . $category_slug . '" rwpp-order="' . $preset->preset_name . '"]';
					} else {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						$shortcode = '[products rwpp-order="' . $preset->preset_name . '"]';
					}

					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$created_date = gmdate( 'M d, Y', strtotime( $preset->created_at ) );
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$updated_date = gmdate( 'M d, Y', strtotime( $preset->updated_at ) );
					?>

					<div class="rwpp-preset-card" data-preset-id="<?php echo esc_attr( $preset->id ); ?>">
						<div>
							<h3 class="rwpp-preset-title"><?php echo esc_html( $preset->preset_name ); ?></h3>

							<?php if ( ! empty( $preset->description ) ) : ?>
								<div class="rwpp-preset-description"><?php echo esc_html( $preset->description ); ?></div>
							<?php endif; ?>

							<div class="rwpp-preset-meta">
								<span class="rwpp-preset-scope"><?php echo esc_html( $category_name ); ?></span>
								<span class="rwpp-preset-separator">•</span>
								<span class="rwpp-preset-count">
					<?php
					// Get actual product count excluding trashed products.
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$actual_count = SortPresets::get_preset_actual_product_count( $preset );
					echo esc_html(
						sprintf(
							/* translators: %d: number of products */
							_n( '%d product', '%d products', $actual_count, 'rearrange-woocommerce-products' ),
							$actual_count
						)
					);
					?>
				</span>
								<span class="rwpp-preset-separator">•</span>
								<span>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: date */
											__( 'Created: %s', 'rearrange-woocommerce-products' ),
											$created_date
										)
									);
									?>
								</span>
							</div>

							<div class="rwpp-preset-shortcode">
								<span class="rwpp-shortcode-label"><?php esc_html_e( 'Shortcode:', 'rearrange-woocommerce-products' ); ?></span>
								<code class="rwpp-shortcode-code" data-shortcode="<?php echo esc_attr( $shortcode ); ?>" title="<?php esc_attr_e( 'Click to copy', 'rearrange-woocommerce-products' ); ?>"><?php echo esc_html( $shortcode ); ?></code>
							</div>
						</div>

						<div class="rwpp-preset-actions">
					<button type="button" class="button button-primary rwpp-apply-preset" data-preset-id="<?php echo esc_attr( $preset->id ); ?>" data-category-id="<?php echo esc_attr( $preset->category_id ); ?>">
						<?php echo $preset->category_id > 0 ? esc_html__( 'Apply to Category', 'rearrange-woocommerce-products' ) : esc_html__( 'Apply to Products', 'rearrange-woocommerce-products' ); ?>
					</button>

					<div class="rwpp-preset-actions-more">
						<button type="button" class="button rwpp-preset-actions-toggle" aria-label="<?php esc_attr_e( 'More actions', 'rearrange-woocommerce-products' ); ?>" aria-expanded="false">
							<span class="dashicons dashicons-ellipsis"></span>
						</button>
						<div class="rwpp-preset-actions-dropdown" style="display: none;">
							<button type="button" class="rwpp-preset-action-item rwpp-edit-preset" data-preset-id="<?php echo esc_attr( $preset->id ); ?>">
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Edit', 'rearrange-woocommerce-products' ); ?>
							</button>
							<button type="button" class="rwpp-preset-action-item rwpp-duplicate-preset" data-preset-id="<?php echo esc_attr( $preset->id ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
								<?php esc_html_e( 'Duplicate', 'rearrange-woocommerce-products' ); ?>
							</button>
							<button type="button" class="rwpp-preset-action-item rwpp-delete-preset" data-preset-id="<?php echo esc_attr( $preset->id ); ?>">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Delete', 'rearrange-woocommerce-products' ); ?>
							</button>
						</div>
					</div>
				</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Empty state for search with no results -->
			<div class="rwpp-preset-empty-state rwpp-search-empty-state" id="rwpp-search-empty-state" style="display: none;">
				<div class="rwpp-empty-icon"><span class="dashicons dashicons-search"></span></div>
				<h3><?php esc_html_e( 'No Presets Found', 'rearrange-woocommerce-products' ); ?></h3>
				<p><?php esc_html_e( 'No presets match your search criteria. Try a different search term.', 'rearrange-woocommerce-products' ); ?></p>
			</div>

			<div class="rwpp-notice-info rwpp-presets-tip">
				<p>
					<strong><?php esc_html_e( 'Tip:', 'rearrange-woocommerce-products' ); ?></strong>
					<?php esc_html_e( 'Go to "Sort Products" or "Sort by Categories" page, arrange your products, and click "Save as Preset" to create a new preset.', 'rearrange-woocommerce-products' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div class="rwpp-preset-empty-state">
				<div class="rwpp-empty-icon"><span class="dashicons dashicons-archive"></span></div>
				<h3><?php esc_html_e( 'No Presets Yet', 'rearrange-woocommerce-products' ); ?></h3>
				<p><?php esc_html_e( 'Create your first preset by going to "Sort Products" or "Sort by Categories" page, arrange your products, and click "Save as Preset".', 'rearrange-woocommerce-products' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Edit Preset Modal -->
<div id="rwpp-edit-preset-modal" class="rwpp-modal rwpp-modal-hidden">
	<div class="rwpp-modal-overlay"></div>
	<div class="rwpp-modal-content">
		<div class="rwpp-modal-header">
			<h2><?php esc_html_e( 'Edit Preset', 'rearrange-woocommerce-products' ); ?></h2>
			<button type="button" class="rwpp-modal-close">&times;</button>
		</div>
		<div class="rwpp-modal-body">
			<input type="hidden" id="rwpp-edit-preset-id">
			<div class="rwpp-form-field">
				<label class="rwpp-form-label" for="rwpp-edit-preset-name"><?php esc_html_e( 'Preset Name:', 'rearrange-woocommerce-products' ); ?> <span class="rwpp-text-danger">*</span></label>
				<input type="text" id="rwpp-edit-preset-name" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Black Friday 2026', 'rearrange-woocommerce-products' ); ?>" maxlength="100" required>
				<span class="rwpp-char-counter" id="rwpp-edit-preset-name-counter">0/100</span>
			</div>
			<div class="rwpp-form-field">
				<label class="rwpp-form-label" for="rwpp-edit-preset-description"><?php esc_html_e( 'Description (optional):', 'rearrange-woocommerce-products' ); ?></label>
				<textarea id="rwpp-edit-preset-description" class="widefat" placeholder="<?php esc_attr_e( 'Brief description of this arrangement.', 'rearrange-woocommerce-products' ); ?>" rows="3" maxlength="200"></textarea>
				<span class="rwpp-char-counter" id="rwpp-edit-preset-description-counter">0/200</span>
			</div>
		</div>
		<div class="rwpp-modal-footer">
			<button type="button" class="button" id="rwpp-cancel-edit-preset"><?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?></button>
			<button type="button" class="button button-primary" id="rwpp-save-edit-preset"><?php esc_html_e( 'Save Changes', 'rearrange-woocommerce-products' ); ?></button>
		</div>
	</div>
</div>

<!-- Confirm Modal (Generic) -->
<div id="rwpp-confirm-modal" class="rwpp-modal rwpp-modal-hidden">
	<div class="rwpp-modal-overlay"></div>
	<div class="rwpp-modal-content">
		<div class="rwpp-modal-header">
			<h2 id="rwpp-confirm-modal-title"><?php esc_html_e( 'Confirm Action', 'rearrange-woocommerce-products' ); ?></h2>
			<button type="button" class="rwpp-modal-close" data-action="cancel">&times;</button>
		</div>
		<div class="rwpp-modal-body">
			<p id="rwpp-confirm-modal-message"></p>
		</div>
		<div class="rwpp-modal-footer">
			<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?></button>
			<button type="button" class="button button-primary" id="rwpp-confirm-modal-confirm"><?php esc_html_e( 'Confirm', 'rearrange-woocommerce-products' ); ?></button>
		</div>
	</div>
</div>

<!-- Alert Modal (Generic) -->
<div id="rwpp-alert-modal" class="rwpp-modal rwpp-modal-hidden">
	<div class="rwpp-modal-overlay"></div>
	<div class="rwpp-modal-content">
		<div class="rwpp-modal-header">
			<h2 id="rwpp-alert-modal-title"><?php esc_html_e( 'Notice', 'rearrange-woocommerce-products' ); ?></h2>
			<button type="button" class="rwpp-modal-close" data-action="close">&times;</button>
		</div>
		<div class="rwpp-modal-body">
			<div id="rwpp-alert-modal-message"></div>
		</div>
		<div class="rwpp-modal-footer">
			<button type="button" class="button button-primary" data-action="close"><?php esc_html_e( 'Close', 'rearrange-woocommerce-products' ); ?></button>
		</div>
	</div>
</div>

<!-- Duplicate Preset Modal -->
<div id="rwpp-duplicate-preset-modal" class="rwpp-modal rwpp-modal-hidden">
	<div class="rwpp-modal-overlay"></div>
	<div class="rwpp-modal-content">
		<div class="rwpp-modal-header">
			<h2><?php esc_html_e( 'Duplicate Preset', 'rearrange-woocommerce-products' ); ?></h2>
			<button type="button" class="rwpp-modal-close">&times;</button>
		</div>
		<div class="rwpp-modal-body">
			<input type="hidden" id="rwpp-duplicate-preset-id">
			<div class="rwpp-form-field">
				<label class="rwpp-form-label" for="rwpp-duplicate-preset-name"><?php esc_html_e( 'New Preset Name:', 'rearrange-woocommerce-products' ); ?> <span class="rwpp-text-danger">*</span></label>
				<input type="text" id="rwpp-duplicate-preset-name" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Black Friday 2026', 'rearrange-woocommerce-products' ); ?>" maxlength="100" required>
			</div>
		</div>
		<div class="rwpp-modal-footer">
			<button type="button" class="button" id="rwpp-cancel-duplicate-preset"><?php esc_html_e( 'Cancel', 'rearrange-woocommerce-products' ); ?></button>
			<button type="button" class="button button-primary" id="rwpp-save-duplicate-preset"><?php esc_html_e( 'Duplicate Preset', 'rearrange-woocommerce-products' ); ?></button>
		</div>
	</div>
</div>
