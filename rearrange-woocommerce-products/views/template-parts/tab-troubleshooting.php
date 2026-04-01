<?php
/**
 * Troubleshooting steps
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="rwpp-tab-content">
	<h2><?php esc_html_e( 'Troubleshooting', 'rearrange-woocommerce-products' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Common issues and solutions for product sorting.', 'rearrange-woocommerce-products' ); ?></p>

	<!-- Issue 1: Shop page sorting not working -->
	<details class="rwpp-accordion" open>
		<summary>
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'Products Aren\'t Showing in the Order I Arranged Them', 'rearrange-woocommerce-products' ); ?>
		</summary>
		<div class="rwpp-accordion-content">
			<p><?php esc_html_e( 'This usually happens because your shop\'s sorting preference is set differently. Here\'s how to fix it:', 'rearrange-woocommerce-products' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'In your WordPress admin, click', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Appearance', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'then', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Customize', 'rearrange-woocommerce-products' ); ?></strong></li>
				<li><?php esc_html_e( 'Look for', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'WooCommerce', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'in the menu on the left', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'Click', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Product Catalogue', 'rearrange-woocommerce-products' ); ?></strong></li>
				<li><?php esc_html_e( 'Find the', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Default Product Sorting', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'option', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'Select', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( '"Default sorting (custom ordering + name)"', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'from the dropdown', 'rearrange-woocommerce-products' ); ?></li>
				<li><?php esc_html_e( 'Click', 'rearrange-woocommerce-products' ); ?> <strong><?php esc_html_e( 'Publish', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'to save your changes', 'rearrange-woocommerce-products' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'Tip:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Your shop must be set to use "custom ordering" for your arrangement to show up. Other sorting methods (like by price or newest) will override it.', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</details>

	<!-- Issue 2: Large product list not saving -->
	<details class="rwpp-accordion">
		<summary>
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'Can\'t Save Changes with Many Products', 'rearrange-woocommerce-products' ); ?>
		</summary>
		<div class="rwpp-accordion-content">
			<p><?php esc_html_e( 'If you have hundreds (or thousands!) of products and saving isn\'t working, your server might need a boost. Think of it like your server running out of breath when trying to save all those changes at once.', 'rearrange-woocommerce-products' ); ?></p>
			<h4><?php esc_html_e( 'What might help:', 'rearrange-woocommerce-products' ); ?></h4>
			<p><?php esc_html_e( 'Your server has resource limits that can prevent large operations from completing. Contact your web hosting support and ask them to increase these two settings:', 'rearrange-woocommerce-products' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Memory limit', 'rearrange-woocommerce-products' ); ?></strong> - <?php esc_html_e( 'How much RAM the plugin can use', 'rearrange-woocommerce-products' ); ?></li>
				<li><strong><?php esc_html_e( 'Execution time', 'rearrange-woocommerce-products' ); ?></strong> - <?php esc_html_e( 'How long the save operation can take', 'rearrange-woocommerce-products' ); ?></li>
			</ul>
			<h4><?php esc_html_e( 'What to ask your hosting provider:', 'rearrange-woocommerce-products' ); ?></h4>
			<p><?php esc_html_e( '"Can you increase the PHP memory_limit to 256MB or higher and max_execution_time to 300 seconds or higher?"', 'rearrange-woocommerce-products' ); ?></p>
			<p><strong><?php esc_html_e( 'After they update it:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Come back and try saving your product order again. It should work now!', 'rearrange-woocommerce-products' ); ?></p>
		</div>
	</details>

	<!-- Issue 3: Page Builder Compatibility -->
	<details class="rwpp-accordion">
		<summary>
			<span class="dashicons dashicons-layout"></span>
			<?php esc_html_e( 'Using a Page Builder Plugin for Product Display', 'rearrange-woocommerce-products' ); ?>
		</summary>
		<div class="rwpp-accordion-content">
			<p><?php esc_html_e( 'If you\'re using a page builder plugin (such as Elementor, Divi, WPBakery, or similar) to display your products, the custom sort order may not automatically apply. This is because page builder plugins often have their own product query settings that work independently of WooCommerce\'s default sorting.', 'rearrange-woocommerce-products' ); ?></p>
			<h4><?php esc_html_e( 'What you can do:', 'rearrange-woocommerce-products' ); ?></h4>
			<ol>
				<li><strong><?php esc_html_e( 'Check your page builder\'s settings:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Look in your page builder\'s product widget/module for sorting options', 'rearrange-woocommerce-products' ); ?></li>
				<li><strong><?php esc_html_e( 'Look for "custom order" or "menu order":', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'Many page builders have a "menu order" or "custom order" option in their product display settings', 'rearrange-woocommerce-products' ); ?></li>
				<li><strong><?php esc_html_e( 'Contact your page builder\'s support:', 'rearrange-woocommerce-products' ); ?></strong> <?php esc_html_e( 'If you need help configuring this, your page builder\'s support team will be able to guide you through their specific settings', 'rearrange-woocommerce-products' ); ?></li>
			</ol>
		</div>
	</details>

	<!-- Issue 4: Re-run Database Migration -->
	<details class="rwpp-accordion">
		<summary>
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Re-run Database Migration', 'rearrange-woocommerce-products' ); ?>
		</summary>
		<div class="rwpp-accordion-content">
			<p><?php esc_html_e( 'If your category-specific product sorting is not displaying correctly on the frontend, the database migration from the previous version may not have completed successfully.', 'rearrange-woocommerce-products' ); ?></p>
			<p><?php esc_html_e( 'Click the button below to re-run the migration. This will re-import all sorting data from the legacy storage format into the new custom table.', 'rearrange-woocommerce-products' ); ?></p>
			<p style="color: #d63638; font-weight: 600;"><?php esc_html_e( 'Warning: This will migrate your sort order from the previous version to the new version. Any changes made to the sort order after the initial migration will be overwritten.', 'rearrange-woocommerce-products' ); ?></p>
			<p>
				<button type="button" class="button button-primary" id="rwpp-run-remigration">
					<?php esc_html_e( 'Re-run Migration', 'rearrange-woocommerce-products' ); ?>
				</button>
				<span id="rwpp-remigration-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
			</p>
			<div id="rwpp-remigration-result" style="margin-top: 12px;"></div>
		</div>
	</details>

	<!-- System Status / Diagnostic Info -->
	<details class="rwpp-accordion">
		<summary>
			<span class="dashicons dashicons-info-outline"></span>
			<?php esc_html_e( 'System Status / Diagnostic Info', 'rearrange-woocommerce-products' ); ?>
		</summary>
		<div class="rwpp-accordion-content">
			<p><?php esc_html_e( 'Copy and paste this information into your support request to help us diagnose issues faster.', 'rearrange-woocommerce-products' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="rwpp-copy-system-status">
					<span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Copy to Clipboard', 'rearrange-woocommerce-products' ); ?>
				</button>
				<span id="rwpp-copy-status-msg" style="margin-left: 8px; color: #1c9648; font-weight: 600; display: none;"><?php esc_html_e( 'Copied!', 'rearrange-woocommerce-products' ); ?></span>
			</p>

			<?php
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$system_status = \ReWooProducts\Helpers::get_system_status();
			$plain_text    = '';

			foreach ( $system_status as $group => $fields ) :
				$plain_text .= '### ' . $group . "\n";
				?>
				<h4 style="margin: 16px 0 8px;"><?php echo esc_html( $group ); ?></h4>
				<table class="rwpp-system-status-table">
					<?php
					foreach ( $fields as $label => $value ) :
						$plain_text .= $label . ': ' . $value . "\n";
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php
				$plain_text .= "\n";
			endforeach;
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			?>

			<textarea id="rwpp-system-status-text" style="position: absolute; left: -9999px;" readonly><?php echo esc_textarea( trim( $plain_text ) ); ?></textarea>
		</div>
	</details>
</div>
