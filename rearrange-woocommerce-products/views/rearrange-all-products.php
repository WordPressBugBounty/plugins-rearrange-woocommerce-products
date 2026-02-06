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
if ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-sortby-categories-page' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	include 'template-parts/tab-category-products.php';
} elseif ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-troubleshooting-page' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	include 'template-parts/tab-troubleshooting.php';
} elseif ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && 'rwpp-settings-page' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	include 'template-parts/tab-settings.php';
} else {
	include 'template-parts/tab-all-products.php';
}
?>

</div>

<?php require 'template-parts/footer.php'; ?>
