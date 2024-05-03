<?php
/**
 * The HTML template for the "CiviCRM Settings" Variable Product Tab.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<div id="civicrm_variable" class="panel woocommerce_options_panel hidden">

	<?php

	/**
	 * Fires at the beginning of the "CiviCRM Settings" Variable Product Tab.
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/panel/variable/before' );

	?>

	<div class="options_group">

		<?php

		// Build args.
		$args = [
			'id'          => $this->entity_key,
			'name'        => $this->entity_key,
			'label'       => __( 'Entity Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'The CiviCRM Entity Type for this Product. Other CiviCRM settings are applied in the Variations.', 'wpcv-woo-civi-integration' ),
			'options'     => $entity_options,
		];

		// Always render the Entity Type select.
		woocommerce_wp_select( $args );

		?>

	</div>

	<?php

	/**
	 * Fires at the end of the "CiviCRM Settings" Variable Product Tab.
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/panel/variable/after' );

	?>

</div>
