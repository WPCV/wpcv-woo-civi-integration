<?php
/**
 * The HTML template for the "CiviCRM Settings" Variable Product block.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<div class="show_if_variation_virtual options">

	<?php

	/**
	 * Fires at the beginning of the Product Variation "CiviCRM Settings" block.
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array $variation_data The Product Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 */
	do_action( 'wpcv_woo_civi/product/variation/block/before', $loop, $variation_data, $variation );

	?>

	<?php

	// Build args.
	$args = [
		'id'            => $financial_type_id_key,
		'name'          => $financial_type_id_key,
		'value'         => $financial_type_id,
		'label'         => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
		'desc_tip'      => 'true',
		'description'   => __( 'The CiviCRM Financial Type for this Variation.', 'wpcv-woo-civi-integration' ),
		'wrapper_class' => 'form-row form-row-full variable_civicrm_financial_type_id',
		'options'       => $financial_type_options,
	];

	// Always render the Financial Type select.
	woocommerce_wp_select( $args );

	?>

	<?php

	/**
	 * Fires in the middle of the Product Variation "CiviCRM Settings" block.
	 *
	 * Used internally by:
	 *
	 * * WPCV_Woo_Civi_Membership::attributes_add_markup() (Priority: 10)
	 * * WPCV_Woo_Civi_Participant::attributes_add_markup() (Priority: 20)
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array $variation_data The Product Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 * @param string $entity The CiviCRM Entity that this Product Variation is mapped to.
	 */
	do_action( 'wpcv_woo_civi/product/variation/block/middle', $loop, $variation_data, $variation, $entity );

	?>

	<?php if ( ! empty( $price_sets ) ) : ?>

		<p class="form-row form-row-full variable_civicrm_pfv_id">
			<label for="<?php echo esc_attr( $pfv_id_key ); ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
			<?php echo wc_help_tip( esc_html__( 'Select The Price Field for this Variation.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select name="<?php echo esc_attr( $pfv_id_key ); ?>" id="<?php echo esc_attr( $pfv_id_key ); ?>">
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
			</select>
		</p>

	<?php endif; ?>

	<?php

	/**
	 * Fires at the end of the Product Variation "CiviCRM Settings" block.
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array $variation_data The Product Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 */
	do_action( 'wpcv_woo_civi/product/variation/block/after', $loop, $variation_data, $variation );

	?>

</div>
