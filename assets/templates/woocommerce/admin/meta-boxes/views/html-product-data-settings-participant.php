<?php
/**
 * The HTML template for the CiviCRM Participant "CiviCRM Settings" Product Tab.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define the Product Type name for use in this template.
$product_type_name = 'civicrm_participant';

// Get meta keys for the form elements.
$financial_type_id_key = $this->get_meta_key( $product_type_name, 'financial_type_id' );
$pfv_id_key            = $this->get_meta_key( $product_type_name, 'pfv_id' );
$event_id_key          = $this->get_meta_key( $product_type_name, 'event_id' );
$role_id_key           = $this->get_meta_key( $product_type_name, 'role_id' );

// Get an initial set of Events.
$options = WPCV_WCI()->participant->get_event_options();

// Get Event ID.
$event_id = $this->get_meta( $product_id, $product_type_name, 'event_id' );

// Get the Event data if set.
$event = false;
if ( ! empty( $event_id ) ) {
	$event = WPCV_WCI()->participant->get_event_by_id( $event_id );
}

// Maybe add to options.
if ( false !== $event ) {
	$options[ $event_id ] = $event['title'];
}

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

		<p class="form-field">
			<label for="<?php echo esc_attr( $event_id_key ); ?>"><?php esc_html_e( 'Event', 'wpcv-woo-civi-integration' ); ?></label>
			<select class="wc-product-search" id="<?php echo esc_attr( $event_id_key ); ?>" name="<?php echo esc_attr( $event_id_key ); ?>" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for a CiviCRM Event&hellip;', 'wpcv-woo-civi-integration' ); ?>" data-action="wpcv_woo_civi_search_events">
				<option value=""><?php esc_html_e( 'None', 'wpcv-woo-civi-integration' ); ?></option>
				<?php $selected = $this->get_meta( $product_id, $product_type_name, 'event_id' ); ?>
				<?php foreach ( $options as $event_id => $event_name ) : ?>
					<option value="<?php echo esc_attr( $event_id ); ?>" <?php selected( $selected, $event_id ); ?>>
						<?php echo esc_attr( $event_name ); ?>
					</option>
				<?php endforeach; ?>
			</select> <?php echo wc_help_tip( esc_html__( 'Select an Event if you would like this Product to create an Event Participant in CiviCRM.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>

		<?php

		// Build Participant Roles options array.
		$participant_roles = [
			'' => __( 'Select a Participant Role', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->participant->get_participant_roles_options();

		// Build args.
		$args = [
			'id'          => $role_id_key,
			'name'        => $role_id_key,
			'label'       => __( 'Participant Role', 'wpcv-woo-civi-integration' ),
			'desc_tip'    => 'true',
			'description' => __( 'Select a Participant Role for the Event Participant.', 'wpcv-woo-civi-integration' ),
			'options'     => $participant_roles,
		];

		// Show Participant Role.
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
				</select> <?php echo wc_help_tip( esc_html__( 'Select The Price Field for the Participant.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
