<!-- assets/templates/metaboxes/metabox-migrate-info.php -->
<?php if ( $metabox['args']['migrated'] === false ) : ?>

	<h3><?php _e( 'Why migrate?', 'wpcv-woo-civi-integration' ) ?></h3>

	<p><?php _e( 'The WooCommerce CiviCRM plugin is no longer being developed as a standalone plugin, so the functionality that it provides has been transferred to this plugin. New features and bug fixes will only be added to this plugin from now on. Plus you get the convenience of updating this plugin via your WordPress Updates page.', 'wpcv-woo-civi-integration' ); ?></p>

	<h3><?php _e( 'What needs to be done?', 'wpcv-woo-civi-integration' ) ?></h3>

	<p><?php _e( 'Before you go ahead and deactivate and delete the WooCommerce CiviCRM plugin, there are few things that need to be checked to make sure your site continues to work as normal.', 'wpcv-woo-civi-integration' ); ?> <em><?php _e( 'Integrate CiviCRM with WooCommerce will not affect your site until you have deactivated WooCommerce CiviCRM.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<h4><?php _e( 'Filters and Actions', 'wpcv-woo-civi-integration' ) ?></h3>

	<p><em><?php _e( 'If you have not implemented any of the Filters or Actions from the WooCommerce CiviCRM plugin, then it is unlikely that you will need to take any further action before migrating.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<p><?php _e( 'Filters and Actions have undergone a major overhaul and there isn’t really a simple substitution formula that we can give you. If you are technical enough to have used them to modify or extend the behaviour of the WooCommerce CiviCRM plugin, then we are confident that you are capable of figuring out their replacements by looking at the equivalent classes, functions and templates in this plugin.', 'wpcv-woo-civi-integration' ); ?></p>

	<p><?php _e( 'This is really just a reminder that you need to do so.', 'wpcv-woo-civi-integration' ); ?></p>

	<h4><?php _e( 'Settings', 'wpcv-woo-civi-integration' ) ?></h3>

	<p><?php _e( 'Luckily there is no uninstall routine in the WooCommerce CiviCRM plugin at present. This means that WooCommerce CiviCRM will not auto-delete its settings when it is deleted. This plugin can therefore use those settings unchanged and you should not notice any difference once you have deactivated WooCommerce CiviCRM. Nevertheless, you should only deactivate and delete WooCommerce CiviCRM when you are sure everything mentioned here has been attended to.', 'wpcv-woo-civi-integration' ); ?></p>

<?php else : ?>

	<h3><?php _e( 'Congratulations!', 'wpcv-woo-civi-integration' ) ?></h3>

	<p><em><?php _e( 'You have successfully migrated from WooCommerce CiviCRM to Integrate CiviCRM with WooCommerce.', 'wpcv-woo-civi-integration' ); ?></em></p>

	<p><?php echo sprintf(
		__( 'You can now go to your %1$sPlugins page%2$s and deactivate the WooCommerce CiviCRM plugin.', 'wpcv-woo-civi-integration' ),
		'<a href="' . admin_url( 'plugins.php' ) . '">',
		'</a>'
	); ?></p>

<?php endif; ?>
