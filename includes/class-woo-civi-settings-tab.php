<?php
/**
 * WPCV WooCommerce CiviCRM Settings Tab class.
 *
 * Handles the CiviCRM Settings tab on the WooCommerce Settings screen.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Settings Tab class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Settings_Tab {

	/**
	 * Initialise this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
		if ( WCI()->is_network_installed ) {
			$this->register_settings();
		}
	}

	/**
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// Add CiviCRM settings tab.
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		// Add settings for this plugin.
		add_action( 'woocommerce_settings_woocommerce_civicrm', [ $this, 'add_settings_fields' ], 10 );
		// Update settings for this plugin.
		add_action( 'woocommerce_update_options_woocommerce_civicrm', [ $this, 'update_settings_fields' ] );
		// Update network settings.
		add_action( 'network_admin_edit_woocommerce_civicrm_network_settings', [ $this, 'trigger_network_settings' ] );
	}

	/**
	 * Registers the plugin settings.
	 *
	 * @since 2.0
	 */
	public function register_settings() {
		register_setting( 'woocommerce_civicrm_network_settings', 'woocommerce_civicrm_network_settings' );

		// Makes sure function exists.
		if ( ! function_exists( 'add_settings_field' ) ) {
			require_once ABSPATH . '/wp-admin/includes/template.php';
		}

		add_settings_section(
			'woocommerce-civicrm-settings-network-general',
			__( 'General settings', 'wpcv-woo-civi-integration' ),
			[ $this, 'settings_section_callback' ],
			'woocommerce-civicrm-settings-network'
		);

		add_settings_field(
			'woocommerce_civicrm_shop_blog_id',
			__( 'Main WooCommerce blog ID', 'wpcv-woo-civi-integration' ),
			[ $this, 'settings_field_select' ],
			'woocommerce-civicrm-settings-network',
			'woocommerce-civicrm-settings-network-general',
			[
				'name' => 'wc_blog_id',
				'network' => true,
				'description' => __( 'The shop on a multisite network', 'wpcv-woo-civi-integration' ),
				'options' => WCI()->helper->get_sites(),
			]
		);
	}

	/**
	 * Settings section callback.
	 *
	 * @since 2.0
	 */
	public function settings_section_callback() {
		// FIXME: Why is this empty?
	}

	/**
	 * Settings field text.
	 *
	 * @since 2.0
	 *
	 * @param array $args The field params.
	 */
	public function settings_field_text( $args ) {
		$option = 'woocommerce_civicrm_network_settings';
		$options = get_site_option( $option );
		?>
		<input
			name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $args['name'] ); ?>]"
			id="<?php echo esc_attr( $args['name'] ); ?>"
			value="<?php echo ( isset( $options[ $args['name'] ] ) ? esc_attr( $options[ $args['name'] ] ) : '' ); ?>"
			class="regular-text"/>
		<?php if ( isset( $args['description'] ) && $args['description'] ) : ?>
			<div class="description"><?php echo esc_html( $args['description'] ); ?></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Settings field select.
	 *
	 * @since 2.0
	 *
	 * @param array $args The field params.
	 */
	public function settings_field_select( $args ) {
		$option = 'woocommerce_civicrm_network_settings';
		$options = get_site_option( $option );
		?>
		<select
			name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $args['name'] ); ?>]"
			id="<?php echo esc_attr( $args['name'] ); ?>"
			class="regular-select">
			<?php foreach ( (array) $args['options'] as $key => $option ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '', true ); ?>>
					<?php echo esc_attr( $option ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( isset( $args['description'] ) && $args['description'] ) : ?>
		<div class="description"><?php echo esc_html( $args['description'] ); ?></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Trigger network settings.
	 *
	 * @since 2.0
	 */
	public function trigger_network_settings() {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'woocommerce-civicrm-settings', FILTER_SANITIZE_STRING ), 'woocommerce-civicrm-settings' ) ) {
			wp_die( __( 'Cheating uh?', 'wpcv-woo-civi-integration' ) );
		}
		if ( ! empty( $_POST['woocommerce_civicrm_network_settings']['wc_blog_id'] ) ) {
			$settings = [
				'wc_blog_id' => sanitize_text_field( $_POST['woocommerce_civicrm_network_settings']['wc_blog_id'] ),
			];
			update_site_option( 'woocommerce_civicrm_network_settings', $settings );
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'woocommerce-civicrm-settings',
						'confirm' => 'success',
					],
					( network_admin_url( 'settings.php' ) )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'woocommerce-civicrm-settings',
						// FIXME: Not sure this is correct.
						'confirm' => 'error',
					],
					( network_admin_url( 'settings.php' ) )
				)
			);
		}
		exit;
	}

	/**
	 * Add CiviCRM tab to the settings page.
	 *
	 * @since 2.0
	 *
	 * @uses 'woocommerce_settings_tabs_array' filter.
	 *
	 * @param array $setting_tabs The existing setting tabs array.
	 * @return array $setting_tabs The modified setting tabs array.
	 */
	public function add_settings_tab( $setting_tabs ) {
		$setting_tabs['woocommerce_civicrm'] = __( 'CiviCRM', 'wpcv-woo-civi-integration' );
		return $setting_tabs;
	}

	/**
	 * Add settings to the Settings tab.
	 *
	 * @since 2.0
	 */
	public function add_settings_fields() {
		woocommerce_admin_fields( $this->civicrm_settings_fields() );
	}

	/**
	 * Update settings.
	 *
	 * @since 2.0
	 */
	public function update_settings_fields() {
		woocommerce_update_options( $this->civicrm_settings_fields() );
	}

	/**
	 * Settings options.
	 *
	 * @since 2.0
	 *
	 * @return array $options The fields configuration.
	 */
	public function civicrm_settings_fields() {

		$options = [
			'section_title' => [
				'name' => __( 'CiviCRM Settings', 'wpcv-woo-civi-integration' ),
				'type' => 'title',
				'desc' => __( 'Below are the values used when creating contribution/address in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'id' => 'woocommerce_civicrm_section_title',
			],
			'woocommerce_civicrm_financial_type_id' => [
				'name' => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_id',
			],
			'woocommerce_civicrm_financial_type_vat_id' => [
				'name' => __( 'TAX/VAT Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_vat_id',
			],
			'woocommerce_civicrm_financial_type_shipping_id' => [
				'name' => __( 'Shipping Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_shipping_id',
			],
			'woocommerce_civicrm_campaign_id' => [
				'name' => __( 'Default campaign', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->campaigns,
				'id'   => 'woocommerce_civicrm_campaign_id',
			],
			'woocommerce_civicrm_billing_location_type_id' => [
				'name' => __( 'Billing Location Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->location_types,
				'id'   => 'woocommerce_civicrm_billing_location_type_id',
			],
			'woocommerce_civicrm_shipping_location_type_id' => [
				'name' => __( 'Shipping Location Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WCI()->helper->location_types,
				'id'   => 'woocommerce_civicrm_shipping_location_type_id',
			],
			'woocommerce_civicrm_sync_contact_address' => [
				'name' => __( 'Sync Contact address', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize WooCommerce user address with CiviCRM\'s contact address and viceversa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_address',
			],
			'woocommerce_civicrm_sync_contact_phone' => [
				'name' => __( 'Sync Contact billing phone', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize WooCommerce user\'s billing phone with CiviCRM\'s contact billing phone and viceversa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_phone',
			],
			'woocommerce_civicrm_sync_contact_email' => [
				'name' => __( 'Sync Contact billing email', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize WooCommerce user\'s billing email with CiviCRM\'s contact billing email and viceversa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_email',
			],
			'woocommerce_civicrm_replace_woocommerce_states' => [
				'name' => __( 'Replace WooCommerce States', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'WARNING, DATA LOSS!! If enabled, this option will replace WooCommerce\'s States/Countries with CiviCRM\'s States/Provinces, you WILL lose any existing State/Country data for existing Customers. Any WooCommerce Settings that relay on State/Country will have to be reconfigured.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_replace_woocommerce_states',
			],
			'woocommerce_civicrm_ignore_0_amount_orders' => [
				'name' => __( 'Don\'t create 0 amount contributions', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will not create contributions for orders with a total of 0, i.e. free products (using a coupon).', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_ignore_0_amount_orders',
			],
			'woocommerce_civicrm_hide_orders_tab_for_non_customers' => [
				'name' => __( 'Hide orders tab for non customers', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will remove the WooCommerce Orders tab in the contact summary page for non customers contacts.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_hide_orders_tab_for_non_customers',
			],
			'section_end' => [
				'type' => 'sectionend',
				'id' => 'woocommerce_civicrm_section_end',
			],
		];

		/**
		 * Filter fields configuration.
		 *
		 * @since 2.0
		 *
		 * @param array $options The fields configuration.
		 */
		return apply_filters( 'woocommerce_civicrm_admin_settings_fields', $options );

	}


	/**
	 * Network settings.
	 *
	 * @since 2.0
	 */
	public function network_settings() {
		?>
		<div class="wrap">
		<h2><?php esc_html_e( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ); ?></h2>
		<?php settings_errors(); ?>
		<form action="edit.php?action=woocommerce_civicrm_network_settings" method="post">
			<?php wp_nonce_field( 'woocommerce-civicrm-settings', 'woocommerce-civicrm-settings' ); ?>
			<?php settings_fields( 'woocommerce-civicrm-settings-network' ); ?>
			<?php do_settings_sections( 'woocommerce-civicrm-settings-network' ); ?>
			<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

}
