<?php
/**
 * The HTML template for the "CiviCRM Settings" Product Tab.
 *
 * @package WPCV_Woo_Civi
 * @since 2.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<div id="woocommerce_civicrm" class="panel woocommerce_options_panel hidden">
	<div>
		<?php

		/**
		 * Fires at the beginning of the "CiviCRM Settings" Product Tab.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/product/panel/civicrm/before' );

		// Always render the Financial Type select.
		woocommerce_wp_select( [
			'id' => WPCV_WCI()->products->meta_key,
			'name' => WPCV_WCI()->products->meta_key,
			'label' => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
			'desc_tip' => 'true',
			'description' => __( 'The CiviCRM Financial Type for this Product.', 'wpcv-woo-civi-integration' ),
			'options' => WPCV_WCI()->helper->get_financial_types_options(),
		] );

		/**
		 * Fires at the end of the "CiviCRM Settings" Product Tab.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Membership::panel_add_markup() (Priority: 10)
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/product/panel/civicrm/after' );

		?>
	</div>
</div>
