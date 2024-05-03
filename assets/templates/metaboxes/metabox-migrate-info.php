<?php
/**
 * Migration Page Info metabox template.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/metaboxes/metabox-migrate-info.php -->
<?php if ( false === $metabox['args']['migrated'] ) : ?>

	<h3><?php esc_html_e( 'Why migrate?', 'wpcv-woo-civi-integration' ); ?></h3>

	<p><?php esc_html_e( 'The WooCommerce CiviCRM plugin is no longer being developed and the functionality that it provides has been transferred to this plugin. New features and bug fixes will only be added to this plugin from now on. Plus you get the convenience of updating this plugin via your WordPress Updates page.', 'wpcv-woo-civi-integration' ); ?></p>

	<h3><?php esc_html_e( 'What needs to be done?', 'wpcv-woo-civi-integration' ); ?></h3>

	<p><?php esc_html_e( 'Before you go ahead and deactivate and delete the WooCommerce CiviCRM plugin, there are few things that need to be checked to make sure your site continues to work as normal.', 'wpcv-woo-civi-integration' ); ?> <em><?php esc_html_e( 'Integrate CiviCRM with WooCommerce will not affect your site until you have started one of the migration steps below, so take your time.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<h4><?php esc_html_e( 'Filters and Actions', 'wpcv-woo-civi-integration' ); ?></h4>

	<p><em><?php esc_html_e( 'If you have not implemented any of the Filters or Actions from the WooCommerce CiviCRM plugin, then you can skip this section.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<p><?php esc_html_e( 'Filters and Actions have undergone a major overhaul and there isn’t really a simple substitution formula that we can give you. If you are technical enough to have used them to modify or extend the behaviour of the WooCommerce CiviCRM plugin, then we are confident that you are capable of figuring out their replacements by looking at the equivalent classes, functions and templates in this plugin.', 'wpcv-woo-civi-integration' ); ?> <em><?php esc_html_e( 'You need to do so before taking any further action.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<?php if ( false === $metabox['args']['product-metadata'] ) : ?>

		<div id="wpcv_woocivi_products">

			<h4><?php esc_html_e( 'Product Metadata', 'wpcv-woo-civi-integration' ); ?></h4>

			<p><?php esc_html_e( 'The WooCommerce CiviCRM plugin duplicated some Product metadata in certain circumstances. Click the "Upgrade Products" button below to resolve this issue.', 'wpcv-woo-civi-integration' ); ?></p>

			<?php if ( false !== $metabox['args']['product-offset'] ) : ?>
				<?php submit_button( esc_html__( 'Stop', 'wpcv-woo-civi-integration' ), 'secondary', 'wpcv_woocivi_products_process_stop', false ); ?>
			<?php endif; ?>

			<?php submit_button( $metabox['args']['product-button_title'], 'primary', 'wpcv_woocivi_products_process', false, [ 'data-security' => esc_attr( wp_create_nonce( 'wpcv_migrate_products' ) ) ] ); ?>

			<div id="product-progress-bar"><div class="progress-label"></div></div>

		</div>

	<?php endif; ?>

	<?php if ( false === $metabox['args']['order-metadata'] ) : ?>

		<div id="wpcv_woocivi_orders">

			<h4><?php esc_html_e( 'Order Metadata', 'wpcv-woo-civi-integration' ); ?></h4>

			<p><?php esc_html_e( 'This plugin needs to store some information in WooCommerce Order metadata so that it can perform certain tasks. Click the "Upgrade Orders" button below to start the upgrade process.', 'wpcv-woo-civi-integration' ); ?></p>

			<?php if ( false !== $metabox['args']['order-offset'] ) : ?>
				<?php submit_button( esc_html__( 'Stop', 'wpcv-woo-civi-integration' ), 'secondary', 'wpcv_woocivi_orders_process_stop', false ); ?>
			<?php endif; ?>

			<?php submit_button( $metabox['args']['order-button_title'], 'primary', 'wpcv_woocivi_orders_process', false, [ 'data-security' => esc_attr( wp_create_nonce( 'wpcv_migrate_orders' ) ) ] ); ?>

			<div id="order-progress-bar"><div class="progress-label"></div></div>

		</div>

	<?php endif; ?>

	<h4><?php esc_html_e( 'Settings', 'wpcv-woo-civi-integration' ); ?></h4>

	<p><?php esc_html_e( 'Luckily there is no uninstall routine in the WooCommerce CiviCRM plugin at present. This means that WooCommerce CiviCRM will not auto-delete its settings when it is deleted and this plugin can therefore use many of those legacy settings. There are some changes and additions to the CiviCRM settings on WooCommerce Products that cannot be automated - so you should only deactivate and delete WooCommerce CiviCRM when you are sure everything mentioned here has been attended to and you have clicked "Submit".', 'wpcv-woo-civi-integration' ); ?></p>

<?php else : ?>

	<h3><?php esc_html_e( 'Congratulations!', 'wpcv-woo-civi-integration' ); ?></h3>

	<p><em><?php esc_html_e( 'You have successfully migrated from WooCommerce CiviCRM to Integrate CiviCRM with WooCommerce.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<p>
		<?php

		echo sprintf(
			/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
			esc_html__( 'You can now go to your %1$sPlugins page%2$s and deactivate the WooCommerce CiviCRM plugin.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
			'</a>'
		);

		?>
	</p>

	<p><?php esc_html_e( 'Reminder: When you have deactivated WooCommerce CiviCRM, make sure you visit the "CiviCRM" Settings Tab to configure Integrate CiviCRM with WooCommerce. You will also need to review the CiviCRM settings for every active Product in your Shop to ensure they are all properly configured.', 'wpcv-woo-civi-integration' ); ?></p>

<?php endif; ?>
