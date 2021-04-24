<?php
/**
 * The HTML template for the CiviCRM Settings Tab in the Product Tabs.
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

		woocommerce_wp_select(
			[
				'id' => 'woocommerce_civicrm_financial_type_id',
				'name' => 'woocommerce_civicrm_financial_type_id',
				'label' => __( 'Financial type', 'wpcv-woo-civi-integration' ),
				'desc_tip' => 'true',
				'description' => __( 'The CiviCRM financial type for this product.', 'wpcv-woo-civi-integration' ),
				'options' => WPCV_WCI()->helper->get_financial_types_options(),
			]
		);

		woocommerce_wp_select(
			[
				'id' => 'woocommerce_civicrm_membership_type_id',
				'name' => 'woocommerce_civicrm_membership_type_id',
				'label' => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
				'desc_tip' => 'true',
				'description' => __( 'Select a Membership Type if you would like this product to create a Membership in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'options' => WPCV_WCI()->helper->get_membership_types_options(),
			]
		);

		?>
	</div>
</div>
