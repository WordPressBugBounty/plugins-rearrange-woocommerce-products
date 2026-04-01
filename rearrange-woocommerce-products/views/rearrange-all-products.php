<?php
/**
 * Master Template
 *
 * @package ReWooProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<?php require 'template-parts/header.php'; ?>

<div class="rwpp-content-wrapper">

<input type="hidden" name="rwpp_current_page_url" id="rwpp_current_page_url" value="<?php echo isset( $_SERVER['REQUEST_URI'] ) ? esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>">

<?php
// Get current page.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

// Define pro pages.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$pro_pages = [ 'rwpp-smart-sort-page', 'rwpp-sort-presets-page', 'rwpp-import-export-page' ];

// Check if current page is a pro page and user doesn't have pro license.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$is_pro_page = in_array( $current_page, $pro_pages, true );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$has_pro_license = function_exists( 'rwpp_fs' ) && rwpp_fs()->can_use_premium_code__premium_only();

if ( $is_pro_page && ! $has_pro_license ) {
	// Show upgrade notice for pro pages.
	include 'template-parts/upgrade-notice.php';
} elseif ( 'rwpp-sortby-categories-page' === $current_page ) {
	// Show requested page.
	include 'template-parts/tab-category-products.php';
} elseif ( 'rwpp-smart-sort-page' === $current_page ) {
	include 'template-parts/tab-smart-sort.php';
} elseif ( 'rwpp-sort-presets-page' === $current_page ) {
	include 'template-parts/tab-sort-presets.php';
} elseif ( 'rwpp-import-export-page' === $current_page ) {
	include 'template-parts/tab-import-export.php';
} elseif ( 'rwpp-troubleshooting-page' === $current_page ) {
	include 'template-parts/tab-troubleshooting.php';
} elseif ( 'rwpp-settings-page' === $current_page ) {
	include 'template-parts/tab-settings.php';
} else {
	include 'template-parts/tab-all-products.php';
}
?>

</div>

<?php require 'template-parts/footer.php'; ?>
