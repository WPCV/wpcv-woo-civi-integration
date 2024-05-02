<?php
/**
 * Create Product for Membership template.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/metaboxes/metabox-admin-membership.php -->
<?php

/**
 * Before Create Product for Membership section.
 *
 * @since 3.0
 */
do_action( 'wpcv_woo_civi/admin/metabox/membership/before' );

?>
<p><em><?php esc_html_e( 'Configure the Product that you want to create.', 'wpcv-woo-civi-integration' ); ?></em></p>

<?php if ( empty( $metabox['args']['custom_product_type_exists'] ) ) : ?>
	<input type="hidden" id="wpcv_wci_membership_product_type" name="wpcv_wci_membership_product_type" value="simple" />
<?php endif; ?>

<table class="form-table">
	<?php if ( ! empty( $metabox['args']['custom_product_type_exists'] ) ) : ?>
		<tr>
			<th scope="row">
				<label for="wpcv_wci_membership_product_type"><?php esc_html_e( 'Product Type', 'wpcv-woo-civi-integration' ); ?></label>
			</th>
			<td>
				<p>
					<select id="wpcv_wci_membership_product_type" name="wpcv_wci_membership_product_type">
						<option value="simple"><?php esc_html_e( 'Simple', 'wpcv-woo-civi-integration' ); ?></option>
						<option value="custom"><?php esc_html_e( 'CiviCRM Membership', 'wpcv-woo-civi-integration' ); ?></option>
					</select>
				</p>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<th scope="row">
			<label for="wpcv_wci_membership_financial_type_id"><?php esc_html_e( 'Financial Type', 'wpcv-woo-civi-integration' ); ?></label>
		</th>
		<td>
			<?php if ( ! empty( $metabox['args']['financial_types'] ) ) : ?>
				<p>
					<select id="wpcv_wci_membership_financial_type_id" name="wpcv_wci_membership_financial_type_id">
						<?php foreach ( $metabox['args']['financial_types'] as $key => $financial_type ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $financial_type ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php esc_html_e( 'Choose the Financial Type that is assigned to Payments made by Members. When using a Price Field Value that is part of a Price Set, the Financial Type assigned to the Price Field Value will be used.', 'wpcv-woo-civi-integration' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="wpcv_wci_type_id"><?php esc_html_e( 'Membership Type', 'wpcv-woo-civi-integration' ); ?></label>
		</th>
		<td>
			<?php if ( ! empty( $metabox['args']['types'] ) ) : ?>
				<p>
					<select id="wpcv_wci_type_id" name="wpcv_wci_type_id">
						<?php foreach ( $metabox['args']['types'] as $type_id => $type_name ) : ?>
							<option value="<?php echo esc_attr( $type_id ); ?>"><?php echo esc_attr( $type_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="wpcv_wci_membership_variations_pfv_ids"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
		</th>
		<td>
			<?php if ( ! empty( $metabox['args']['price_sets'] ) ) : ?>
				<p>
					<select class="wc-enhanced-select" multiple="multiple" id="wpcv_wci_membership_variations_pfv_ids" name="wpcv_wci_membership_variations_pfv_ids[]" style="width: 100%">
						<?php foreach ( $metabox['args']['price_sets'] as $price_set_id => $price_set ) : ?>
							<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
								<?php /* translators: 1: The Price Set title, 2: The Price Set label. */ ?>
								<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
									<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
										<option value="<?php echo esc_attr( $price_field_value_id ); ?>"><?php echo esc_html( $price_field_value['label'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php esc_html_e( 'When you select more than one Price Field Value, a Variable Product will be created instead. Only add Price Field Values from the same Price Set.', 'wpcv-woo-civi-integration' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<div class="membership_feedback">
</div>

<?php submit_button( $metabox['args']['button_title'], 'primary', 'wpcv_woocivi_membership_process', false, $metabox['args']['button_args'] ); ?> <span class="spinner"></span>
<br class="clear">
<?php

/**
 * After Create Product for Membership section.
 *
 * @since 3.0
 */
do_action( 'wpcv_woo_civi/admin/metabox/membership/after' );
