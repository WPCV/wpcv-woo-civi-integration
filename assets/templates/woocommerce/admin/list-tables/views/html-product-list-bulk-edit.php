<?php
/**
 * The HTML template for the Product "Bulk Edit" markup.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Fires at the start of the Product "Bulk Edit" markup.
 *
 * @since 3.0
 */
do_action( 'wpcv_woo_civi/product/bulk_edit/before' );

?>
<br class="clear wpcv_woo_civi_bulk_br">
<h4 class="wpcv_woo_civi_bulk_title"><?php esc_html_e( 'CiviCRM Product data', 'wpcv-woo-civi-integration' ); ?></h4>

<label class="wpcv_woo_civi_bulk_entity_type">
	<span class="title"><?php esc_html_e( 'Entity Type', 'wpcv-woo-civi-integration' ); ?></span>
	<span class="input-text-wrap">
		<select class="civicrm_bulk_entity_type" name="_civicrm_bulk_entity_type">
			<?php foreach ( $entity_type_options as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
</label>

<label class="wpcv_woo_civi_bulk_financial_type_id">
	<span class="title"><?php esc_html_e( 'Financial Type', 'wpcv-woo-civi-integration' ); ?></span>
	<span class="input-text-wrap">
		<select class="civicrm_bulk_financial_type_id" name="_civicrm_bulk_financial_type_id">
			<?php foreach ( $financial_type_options as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
</label>

<?php
/*
?>
<?php if ( ! empty( $price_sets ) ) : ?>
	<label class="wpcv_woo_civi_bulk_contribution_pfv_id">
		<span class="title"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></span>
		<span class="input-text-wrap">
			<select class="civirm_bulk_contribution_pfv_id" name="_civirm_bulk_contribution_pfv_id">
				<option value=""><?php esc_html_e( '- No Change -', 'wpcv-woo-civi-integration' ); ?></option>
				<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
					<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
						<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
							<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
								<option value="<?php echo esc_attr( $price_field_value_id ); ?>"><?php echo esc_html( $price_field_value['label'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</select>
		</span>
	</label>
<?php endif; ?>
<?php
*/
?>
<?php

/**
 * Fires at the end of the Product "Bulk Edit" markup.
 *
 * Used internally by:
 *
 * * WPCV_Woo_Civi_Membership::bulk_edit_add_markup() (Priority: 10)
 * * WPCV_Woo_Civi_Participant::bulk_edit_add_markup() (Priority: 20)
 *
 * @since 3.0
 */
do_action( 'wpcv_woo_civi/product/bulk_edit/after' );
