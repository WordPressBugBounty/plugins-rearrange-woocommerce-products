<?php
/**
 * Import / Export Tab
 *
 * @package ReWooProducts
 */

use ReWooProducts\Helpers;
use ReWooProducts\SortPresets;

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
 * Build hierarchical array.
 *
 * @param array $rwpp_categories List of category terms.
 * @param int   $parent_id       Parent term ID.
 * @return array
 */
function rwpp_ie_build_category_tree( $rwpp_categories, $parent_id = 0 ) {
	$branch = [];
	foreach ( $rwpp_categories as $category ) {
		if ( $category->parent === $parent_id ) {
			$children = rwpp_ie_build_category_tree( $rwpp_categories, $category->term_id );
			if ( $children ) {
				$category->children = $children;
			}
			$branch[] = $category;
		}
	}
	return $branch;
}

/**
 * Render category menu recursively.
 *
 * @param array $rwpp_categories List of category terms.
 */
function rwpp_ie_render_category_menu( $rwpp_categories ) {
	if ( empty( $rwpp_categories ) ) {
		return;
	}
	echo '<ul class="rwpp-dropdown-menu">';
	foreach ( $rwpp_categories as $category ) {
		$has_children = isset( $category->children ) && ! empty( $category->children );
		?>
		<li class="<?php echo $has_children ? 'has-children' : ''; ?>">
			<a href="#" data-term-id="<?php echo esc_attr( $category->term_id ); ?>" data-term-name="<?php echo esc_attr( $category->name ); ?>">
				<?php echo esc_html( $category->name ); ?>
				<?php if ( $has_children ) : ?>
					<span class="arrow dashicons dashicons-arrow-right-alt2"></span>
				<?php endif; ?>
			</a>
			<?php
			if ( $has_children ) {
				rwpp_ie_render_category_menu( $category->children );
			}
			?>
		</li>
		<?php
	}
	echo '</ul>';
}

$rwpp_category_tree = rwpp_ie_build_category_tree( $rwpp_categories );

// Check if global products exist for export.
global $wpdb;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$table_name = $wpdb->prefix . 'rwpp_product_order';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_global_products = false;

// Check if table exists and has global products.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$product_count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE category_id = %d",
			0
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$has_global_products = $product_count > 0;
}
?>

<div class="rwpp-tab-content">
	<h2>
		<?php esc_html_e( 'Import / Export', 'rearrange-woocommerce-products' ); ?>
		<?php if ( ! $has_pro_license ) : ?>
			<span class="rwpp-pro-badge">PRO</span>
		<?php endif; ?>
	</h2>
	<p class="description"><?php esc_html_e( 'Export your product sort order or import it from a backup.', 'rearrange-woocommerce-products' ); ?></p>

		<div class="rwpp-import-export-grid">
			<!-- Export Section -->
			<div class="rwpp-import-export-section rwpp-export-section">
				<h3><?php esc_html_e( 'Export', 'rearrange-woocommerce-products' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Choose what you want to export:', 'rearrange-woocommerce-products' ); ?></p>

				<div class="rwpp-export-container">
			<!-- Export Type Selection -->
			<div class="rwpp-form-group">
				<label for="rwpp-export-type-select">
					<?php esc_html_e( 'Export Type', 'rearrange-woocommerce-products' ); ?>
				</label>
				<select id="rwpp-export-type-select" class="rwpp-select">
					<option value="global"><?php esc_html_e( 'Global Sort Order', 'rearrange-woocommerce-products' ); ?></option>
					<option value="category"><?php esc_html_e( 'Category Sort Order', 'rearrange-woocommerce-products' ); ?></option>
					<option value="preset"><?php esc_html_e( 'Preset', 'rearrange-woocommerce-products' ); ?></option>
				</select>
			</div>

			<!-- Export Type Description -->
			<div class="rwpp-form-group">
				<p class="description" id="rwpp-export-type-description">
					<?php esc_html_e( 'Export all products with global sort order (shop page)', 'rearrange-woocommerce-products' ); ?>
				</p>
			</div>

			<!-- Category Selection (hidden by default) -->
			<div class="rwpp-form-group rwpp-export-field" id="rwpp-export-category-field" style="display: none;">
				<label><?php esc_html_e( 'Select Category', 'rearrange-woocommerce-products' ); ?></label>
				<div class="rwpp-custom-dropdown">
					<button type="button" class="rwpp-dropdown-toggle" id="rwpp-export-category-dropdown">
						<span class="rwpp-dropdown-text"><?php esc_html_e( 'Select Product Category', 'rearrange-woocommerce-products' ); ?></span>
						<span class="rwpp-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<div class="rwpp-dropdown-content">
						<?php rwpp_ie_render_category_menu( $rwpp_category_tree ); ?>
					</div>
				</div>
				<input type="hidden" id="rwpp-export-category-select" value="">
			</div>

			<!-- Preset Selection (hidden by default) -->
			<div class="rwpp-form-group rwpp-export-field" id="rwpp-export-preset-field" style="display: none;">
				<label for="rwpp-export-preset-select-new"><?php esc_html_e( 'Select Preset', 'rearrange-woocommerce-products' ); ?></label>
				<select id="rwpp-export-preset-select-new" class="rwpp-select">
					<option value="0"><?php esc_html_e( 'Select a preset...', 'rearrange-woocommerce-products' ); ?></option>
					<?php
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$presets = SortPresets::get_all_presets( [ 'per_page' => -1 ] );
					if ( ! empty( $presets ) ) {
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						foreach ( $presets as $preset ) {
							echo '<option value="' . esc_attr( $preset->id ) . '">';
							echo esc_html( $preset->preset_name );
							echo '</option>';
						}
					}
					?>
				</select>
				<?php if ( empty( $presets ) ) : ?>
					<p class="description" style="margin-top: 10px; color: #999;">
						<?php esc_html_e( 'No presets available. Create a preset first.', 'rearrange-woocommerce-products' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Warning for no global products -->
			<?php if ( ! $has_global_products ) : ?>
				<div class="rwpp-form-group" id="rwpp-export-global-warning">
					<p class="description" style="color: #999;">
						<?php esc_html_e( 'No products available to export. Arrange products first.', 'rearrange-woocommerce-products' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Single Export Button -->
			<button id="rwpp-export-button" class="button button-primary" <?php echo ! $has_global_products ? 'disabled' : ''; ?>>
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export', 'rearrange-woocommerce-products' ); ?>
			</button>
		</div>
			</div>

			<!-- Import Section -->
			<div class="rwpp-import-export-section rwpp-import-section">
				<h3><?php esc_html_e( 'Import', 'rearrange-woocommerce-products' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Import product sort orders from a JSON file:', 'rearrange-woocommerce-products' ); ?></p>

				<div class="rwpp-import-container">
					<!-- Drop Zone -->
					<div id="rwpp-import-drop-zone" class="rwpp-import-drop-zone">
						<span class="dashicons dashicons-upload"></span>
						<p><?php esc_html_e( 'Drag and drop your JSON file here', 'rearrange-woocommerce-products' ); ?></p>
						<p class="rwpp-text-small"><?php esc_html_e( 'or', 'rearrange-woocommerce-products' ); ?></p>
						<button type="button" class="button" id="rwpp-browse-files-btn">
							<?php esc_html_e( 'Browse Files', 'rearrange-woocommerce-products' ); ?>
						</button>
						<input type="file" id="rwpp-import-file-input" accept=".json" style="display: none;">
					</div>

					<!-- File Info -->
					<div id="rwpp-import-file-info" class="rwpp-import-file-info rwpp-hidden">
						<p>
							<strong><?php esc_html_e( 'Selected File:', 'rearrange-woocommerce-products' ); ?></strong> <span id="rwpp-import-file-name"></span>
							<br>
							<strong><?php esc_html_e( 'Size:', 'rearrange-woocommerce-products' ); ?></strong> <span id="rwpp-import-file-size"></span>
						</p>
						<button id="rwpp-import-button" class="button button-primary" disabled>
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Import File', 'rearrange-woocommerce-products' ); ?>
						</button>
					</div>

					<!-- Import Info Box -->
					<div class="rwpp-notice-info">
						<p>
							<strong><?php esc_html_e( 'Note:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'The import process will automatically detect the export type and match products by SKU when product IDs don\'t match.', 'rearrange-woocommerce-products' ); ?>
						</p>
					</div>
				</div>
			</div>
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

<!-- Confirm Modal -->
<div class="rwpp-modal" id="rwpp-confirm-modal" aria-hidden="true">
	<div class="rwpp-modal__overlay" tabindex="-1" data-micromodal-close>
		<div class="rwpp-modal__container" role="dialog" aria-modal="true" aria-labelledby="rwpp-modal-title">
			<div class="rwpp-modal__header">
				<h2 id="rwpp-modal-title"><?php esc_html_e( 'Import Complete', 'rearrange-woocommerce-products' ); ?></h2>
				<button class="rwpp-modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'rearrange-woocommerce-products' ); ?>" data-micromodal-close>✕</button>
			</div>
			<div class="rwpp-modal__content" id="rwpp-modal-content">
				<p><?php esc_html_e( 'Import in progress...', 'rearrange-woocommerce-products' ); ?></p>
			</div>
			<div class="rwpp-modal__footer">
				<button type="button" class="button button-primary" data-micromodal-close>
					<?php esc_html_e( 'Close', 'rearrange-woocommerce-products' ); ?>
				</button>
			</div>
		</div>
	</div>
