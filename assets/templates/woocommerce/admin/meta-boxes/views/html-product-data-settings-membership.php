<?php
/**
 * The HTML template for the CiviCRM Membership "CiviCRM Settings" Product Tab.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the Product Type name for use in this template.
$product_type_name = 'civicrm_membership';

// Get meta keys for the form elements.
$financial_type_id_key = $this->get_meta_key( $product_type_name, 'financial_type_id' );
$pfv_id_key            = $this->get_meta_key( $product_type_name, 'pfv_id' );
$type_id_key           = $this->get_meta_key( $product_type_name, 'type_id' );

// Get the Price Field Value ID.
$pfv_id = $this->get_meta( $product_id, $product_type_name, 'pfv_id' );

?>
<div id="<?php echo esc_attr( $product_type_name ); ?>_settings" class="panel woocommerce_options_panel">

	<?php

	/**
	 * Fires at the beginning of the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/' . $product_type_name . '/settings/before' );

	?>

	<div class="options_group">

		<?php

		// Build args.
		$args = [
			'id'          => $financial_type_id_key,
			'name'        => $financial_type_id_key,
			'label'       => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'The CiviCRM Financial Type for this Product.', 'wpcv-woo-civi-integration' ),
			'options'     => $financial_type_options,
		];

		// Always render the Financial Type select.
		woocommerce_wp_select( $args );

		?>

	</div>

	<div class="options_group">

		<?php

		// Build Membership Types options array.
		$membership_types = [
			'' => __( 'Select a Membership Type', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->membership->get_membership_types_options();

		// Build args.
		$args = [
			'id'          => $type_id_key,
			'name'        => $type_id_key,
			'label'       => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'Select the Membership Type to be created in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
			'options'     => $membership_types,
		];

		woocommerce_wp_select( $args );

		?>

		<?php if ( ! empty( $price_sets ) ) : ?>

			<p class="form-field">
				<label for="<?php echo esc_attr( $pfv_id_key ); ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
				<select name="<?php echo esc_attr( $pfv_id_key ); ?>" id="<?php echo esc_attr( $pfv_id_key ); ?>" class="select short">
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
				</select> <?php echo wc_help_tip( esc_html__( 'Select The Price Field for the Membership.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

		<?php endif; ?>

	</div>

	<?php

	/**
	 * Fires at the end of the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	do_action( 'wpcv_woo_civi/product/' . $product_type_name . '/settings/after' );

	?>

</div>
