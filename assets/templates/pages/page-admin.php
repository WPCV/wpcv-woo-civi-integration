<?php
/**
 * Admin Page template.
 *
 * Handles markup for the Admin Page.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/pages/page-admin.php -->
<div class="wrap">

	<h1><?php esc_html_e( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ); ?></h1>

	<p><?php esc_html_e( 'Here are some utilities for creating Products from Entities in CiviCRM.', 'wpcv-woo-civi-integration' ); ?></p>

	<p>
		<?php

		echo sprintf(
			/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
			esc_html__( 'If you are looking for the WooCommerce settings for this plugin, you can %1$sfind them here%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=woocommerce_civicrm' ) ) . '">',
			'</a>'
		);

		?>
	</p>

	<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<form method="post" id="wpcv_woocivi_admin_form" action="<?php echo $this->page_submit_url_get(); ?>">

		<?php wp_nonce_field( 'wpcv_woocivi_admin_action', 'wpcv_woocivi_admin_nonce' ); ?>
		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>

		<div id="welcome-panel" class="welcome-panel hidden">
		</div>

		<div id="dashboard-widgets-wrap">

			<div id="dashboard-widgets" class="metabox-holder<?php echo esc_attr( $columns_css ); ?>">

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'side', '' ); ?>
				</div>

			</div><!-- #post-body -->
			<br class="clear">

		</div><!-- #poststuff -->

	</form>

</div><!-- /.wrap -->
