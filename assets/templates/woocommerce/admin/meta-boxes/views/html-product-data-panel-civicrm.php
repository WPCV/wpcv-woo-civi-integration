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

	<?php

	/**
	 * Fires at the beginning of the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/panel/civicrm/before' );

	?>

	<div class="options_group">

		<?php

		// Build args.
		$args = [
			'id'          => WPCV_WCI()->products->entity_key,
			'name'        => WPCV_WCI()->products->entity_key,
			'label'       => __( 'Entity Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'The CiviCRM Entity Type for this Product.', 'wpcv-woo-civi-integration' ),
			'options'     => $entity_options,
		];

		// Always render the Entity Type select.
		woocommerce_wp_select( $args );

		?>

	</div>

	<div class="options_group civicrm_financial_type">

		<?php

		// Build args.
		$args = [
			'id'          => WPCV_WCI()->products->financial_type_key,
			'name'        => WPCV_WCI()->products->financial_type_key,
			'label'       => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'The CiviCRM Financial Type for this Product.', 'wpcv-woo-civi-integration' ),
			'options'     => WPCV_WCI()->helper->get_financial_types(),
		];

		// Always render the Financial Type select.
		woocommerce_wp_select( $args );

		?>

	</div>

	<div class="options_group civicrm_contribution">

		<?php if ( ! empty( $price_sets ) ) : ?>

			<p class="form-field">
				<label for="<?php echo esc_attr( $pfv_key ); ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
				<select name="<?php echo esc_attr( $pfv_key ); ?>" id="<?php echo esc_attr( $pfv_key ); ?>" class="select short">
					<option value="0"><?php esc_html_e( 'Select a Price Field', 'wpcv-woo-civi-integration' ); ?></option>
					<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
						<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
							<?php /* translators: 1: The Price Set title, 2: The Price Set label. */ ?>
							<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
								<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
									<option value="<?php echo esc_attr( $price_field_value_id ); ?>" <?php selected( $price_field_value_id, $pfv_id ); ?>><?php echo esc_html( $price_field_value['label'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</select> <?php echo wc_help_tip( esc_html__( 'Select The Price Field for the Contribution.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

		<?php endif; ?>

	</div>

	<?php

	/**
	 * Fires at the end of the "CiviCRM Settings" Product Tab.
	 *
	 * Used internally by:
	 *
	 * * WPCV_Woo_Civi_Membership::panel_add_markup() (Priority: 10)
	 * * WPCV_Woo_Civi_Participant::panel_add_markup() (Priority: 20)
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/panel/civicrm/after' );

	?>

</div>
