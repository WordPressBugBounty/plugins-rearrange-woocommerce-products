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
<div class="rwpp-scrollable-wrapper">
	<h3>Troubleshooting</h3>

	<div class="rwpp-panels">
		<!-- Panel 1: Shop page sorting not working -->
		<div class="rwpp-panel is-open">
			<div class="rwpp-panel__header">
				<h3 class="rwpp-panel__title">
					<span class="rwpp-panel__icon">⚙️</span>
					Products Aren't Showing in the Order I Arranged Them
				</h3>
				<span class="rwpp-panel__title-icon dashicons dashicons-arrow-down"></span>
			</div>
			<div class="rwpp-panel__content">
				<p>This usually happens because your shop's sorting preference is set differently. Here's how to fix it:</p>
				<ol>
					<li>In your WordPress admin, click <strong>Appearance</strong> then <strong>Customize</strong></li>
					<li>Look for <strong>WooCommerce</strong> in the menu on the left</li>
					<li>Click <strong>Product Catalogue</strong></li>
					<li>Find the <strong>Default Product Sorting</strong> option</li>
					<li>Select <strong>"Default sorting (custom ordering + name)"</strong> from the dropdown</li>
					<li>Click <strong>Publish</strong> to save your changes</li>
				</ol>
				<p><strong>Tip:</strong> Your shop must be set to use "custom ordering" for your arrangement to show up. Other sorting methods (like by price or newest) will override it.</p>
			</div>
		</div>

		<!-- Panel 2: Large product list not saving -->
		<div class="rwpp-panel">
			<div class="rwpp-panel__header">
				<h3 class="rwpp-panel__title">
					<span class="rwpp-panel__icon">📊</span>
					Can't Save Changes with Many Products
				</h3>
				<span class="rwpp-panel__title-icon dashicons dashicons-arrow-down"></span>
			</div>
			<div class="rwpp-panel__content">
				<p>If you have hundreds (or thousands!) of products and saving isn't working, your server might need a boost. Think of it like your server running out of breath when trying to save all those changes at once.</p>
				<h4>What might help:</h4>
				<p>Your server has resource limits that can prevent large operations from completing. Contact your web hosting support and ask them to increase these two settings:</p>
				<ul>
					<li><strong>Memory limit</strong> - How much RAM the plugin can use</li>
					<li><strong>Execution time</strong> - How long the save operation can take</li>
				</ul>
				<h4>What to ask your hosting provider:</h4>
				<p>"Can you increase the PHP memory_limit to 256MB or higher and max_execution_time to 300 seconds or higher?"</p>
				<p><strong>After they update it:</strong> Come back and try saving your product order again. It should work now!</p>
			</div>
		</div>

		<!-- Panel 3: General tips -->
		<div class="rwpp-panel">
			<div class="rwpp-panel__header">
				<h3 class="rwpp-panel__title">
					<span class="rwpp-panel__icon">💡</span>
					Pro Tips to Get the Best Results
				</h3>
				<span class="rwpp-panel__title-icon dashicons dashicons-arrow-down"></span>
			</div>
			<div class="rwpp-panel__content">
				<h4>How to succeed:</h4>
				<ul>
					<li><strong>Save after rearranging:</strong> Always click "Save Changes" when you're done moving products around</li>
					<li><strong>Look for the success message:</strong> Wait for the green success notification before closing the page</li>
					<li><strong>Drag multiple at once:</strong> Click products to select multiple ones, then drag them as a group</li>
					<li><strong>Be patient:</strong> If you have a huge product list (1000+), saving might take 30 seconds or more</li>
					<li><strong>Double-check on your shop:</strong> Visit your shop page to confirm the order looks right</li>
				</ul>
				<h4>If things still aren't working:</h4>
				<ol>
					<li><strong>Check your shop settings:</strong> Make sure the shop is set to "Default sorting (custom ordering + name)" - see the first section above</li>
					<li><strong>Clear your browser cache:</strong> Sometimes browsers hold onto old information</li>
					<li><strong>Try a different browser:</strong> This helps confirm if it's a browser issue</li>
					<li><strong>Make sure you're logged in as admin:</strong> You need admin or shop manager access</li>
					<li><strong>Contact your hosting provider:</strong> If the above doesn't help, they can check your server settings</li>
				</ol>
			</div>
		</div>
	</div>
</div>
