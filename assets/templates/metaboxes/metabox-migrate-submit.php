<?php
/**
 * Migration Page Submit metabox template.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/metaboxes/metabox-migrate-submit.php -->
<div class="submitbox">
	<div id="minor-publishing">
		<div id="misc-publishing-actions">
			<div class="misc-pub-section">
				<span><?php esc_html_e( 'I have checked everything mentioned here and I am ready to migrate.', 'wpcv-woo-civi-integration' ); ?></span>
			</div>
		</div>
		<div class="clear"></div>
	</div>

	<div id="major-publishing-actions">
		<div id="publishing-action">
			<?php submit_button( esc_html__( 'Submit', 'wpcv-woo-civi-integration' ), 'primary', 'wpcv_woocivi_save', false, $metabox['args']['submit-atts'] ); ?>
			<input type="hidden" name="action" value="update" />
		</div>
		<div class="clear"></div>
	</div>
</div>
