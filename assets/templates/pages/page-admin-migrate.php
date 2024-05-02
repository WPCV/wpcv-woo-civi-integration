<?php
/**
 * Migration Page template.
 *
 * Handles markup for the Migration Page.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/pages/page-admin-migrate.php -->
<div class="wrap">

	<h1><?php esc_html_e( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ); ?></h1>

	<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<form method="post" id="wpcv_woocivi_migrate_form" action="<?php echo $this->page_submit_url_get(); ?>">

		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field( 'wpcv_woocivi_migrate_action', 'wpcv_woocivi_migrate_nonce' ); ?>

		<div id="poststuff">

			<div id="post-body" class="metabox-holder columns-<?php echo esc_attr( $columns ); ?>">

				<!--<div id="post-body-content">
				</div>--><!-- #post-body-content -->

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'side', null ); ?>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'normal', null ); ?>
					<?php do_meta_boxes( $screen->id, 'advanced', null ); ?>
				</div>

			</div><!-- #post-body -->
			<br class="clear">

		</div><!-- #poststuff -->

	</form>

</div><!-- /.wrap -->
