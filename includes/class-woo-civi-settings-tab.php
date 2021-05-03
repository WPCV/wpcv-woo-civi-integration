<?php
/**
 * Settings Tab class.
 *
 * Handles the "CiviCRM Settings" tab on the WooCommerce Settings screen.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Settings Tab class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Settings_Tab {

	/**
	 * Class constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		// Init when this plugin is fully loaded.
		add_action( 'wpcv_woo_civi/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 3.0
	 */
	public function initialise() {

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Add CiviCRM settings tab.
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'tab_add' ], 50 );

		// Add settings for this plugin.
		add_action( 'woocommerce_settings_woocommerce_civicrm', [ $this, 'fields_add' ], 10 );

		// Update settings for this plugin.
		add_action( 'woocommerce_update_options_woocommerce_civicrm', [ $this, 'fields_update' ] );

	}

	/**
	 * Adds the "CiviCRM" Tab to the WooCommerce Settings page.
	 *
	 * @since 2.0
	 *
	 * @param array $setting_tabs The existing setting tabs array.
	 * @return array $setting_tabs The modified setting tabs array.
	 */
	public function tab_add( $setting_tabs ) {

		$setting_tabs['woocommerce_civicrm'] = __( 'CiviCRM', 'wpcv-woo-civi-integration' );

		return $setting_tabs;

	}

	/**
	 * Add settings to the Settings tab.
	 *
	 * @since 2.0
	 */
	public function fields_add() {
		woocommerce_admin_fields( $this->fields_define() );
	}

	/**
	 * Update settings.
	 *
	 * @since 2.0
	 */
	public function fields_update() {
		woocommerce_update_options( $this->fields_define() );
	}

	/**
	 * Defines the plugin settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of plugin settings fields.
	 */
	public function fields_define() {

		// Define and combine plugin settings fields.
		$contribution_fields = $this->fields_contribution();
		$address_fields = $this->fields_address();
		$other_fields = $this->fields_other();
		$fields = $contribution_fields + $address_fields + $other_fields;

		/**
		 * Filter the plugin fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The plugin fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields', $fields );

		return $fields;
	}

	/**
	 * Defines the Contribution settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of Contribution settings fields.
	 */
	public function fields_contribution() {

		// Init Contribution section.
		$section_start = [
			'contribution_title' => [
				'title' => __( 'Contribution settings', 'wpcv-woo-civi-integration' ),
				'type' => 'title',
				'desc' => __( 'Below are the default settings that are used when creating Contributions in CiviCRM. The Finanical Type can be overridden on individual Products on the "CiviCRM Settings" tab.', 'wpcv-woo-civi-integration' ),
				'id' => 'contribution_title',
			],
		];

		/**
		 * Filter the Contribution section array.
		 *
		 * This can be used to add settings directly after the title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Contribution section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/contribution/title', $section_start );

		// Init Contribution settings.
		$settings = [
			'financial_type_id' => [
				'title' => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WPCV_WCI()->helper->get_financial_types(),
				'id'   => 'woocommerce_civicrm_financial_type_id',
			],
			'financial_type_vat_id' => [
				'title' => __( 'Tax/VAT Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WPCV_WCI()->helper->get_financial_types(),
				'id'   => 'woocommerce_civicrm_financial_type_vat_id',
			],
			'financial_type_shipping_id' => [
				'title' => __( 'Shipping Financial Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WPCV_WCI()->helper->get_financial_types(),
				'id'   => 'woocommerce_civicrm_financial_type_shipping_id',
			],
			'ignore_0_amount_orders' => [
				'title' => __( 'Do not create 0 amount Contributions', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'Do not create Contributions for Orders with a total of 0, e.g. for free Products or when using a Coupon.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_ignore_0_amount_orders',
			],
		];

		/**
		 * Filter the Contribution settings array.
		 *
		 * This can be used to add further Contribution settings.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Campaign::campaign_settings_add() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Contribution settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/contribution/settings', $settings );

		// Declare section end.
		$section_end = [
			'contribution_section_end' => [
				'type' => 'sectionend',
				'id' => 'contribution_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Contribution fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The Contribution fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/contribution', $fields );

	}

	/**
	 * Address settings options.
	 *
	 * @since 3.0
	 *
	 * @return array $options The Address settings fields.
	 */
	public function fields_address() {

		// Init Address section.
		$section_start = [
			'address_title' => [
				'title' => __( 'Address, Phone and Email settings', 'wpcv-woo-civi-integration' ),
				'type' => 'title',
				'desc' => '',
				//'desc' => __( 'Default settings for synchronizing Addresses in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'id' => 'address_title',
			],
		];

		/**
		 * Filter the Address section array.
		 *
		 * This can be used to add settings directly after the Address title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Address section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/address/title', $section_start );

		// Init Address settings.
		$settings = [
			'billing_location_type_id' => [
				'title' => __( 'Billing Location Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WPCV_WCI()->contact->address->get_address_location_types(),
				'id'   => 'woocommerce_civicrm_billing_location_type_id',
			],
			'shipping_location_type_id' => [
				'title' => __( 'Shipping Location Type', 'wpcv-woo-civi-integration' ),
				'type' => 'select',
				'options' => WPCV_WCI()->contact->address->get_address_location_types(),
				'id'   => 'woocommerce_civicrm_shipping_location_type_id',
			],
			'sync_contact_address' => [
				'title' => __( 'Sync Address', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'Synchronize WooCommerce User Address with its matching CiviCRM Contact Address and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_address',
			],
			'sync_contact_phone' => [
				'title' => __( 'Sync Billing Phone', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'Synchronize WooCommerce User Billing Phone Number with its matching CiviCRM Contact Billing Phone Number and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_phone',
			],
			'sync_contact_email' => [
				'title' => __( 'Sync Billing Email', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'Synchronize WooCommerce User Billing Email with its matching CiviCRM Contact Billing Email and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_sync_contact_email',
			],
		];

		/**
		 * Filter the Address settings array.
		 *
		 * This can be used to add further Address settings.
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Address settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/address/settings', $settings );

		// Init section end.
		$section_end = [
			'address_section_end' => [
				'type' => 'sectionend',
				'id' => 'address_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Address settings array.
		 *
		 * @since 2.0
		 *
		 * @param array $fields The Address settings array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/address', $fields );

	}

	/**
	 * Defines the Other settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of Other settings fields.
	 */
	public function fields_other() {

		// Init Contribution section.
		$section_start = [
			'other_title' => [
				'title' => __( 'Other settings', 'wpcv-woo-civi-integration' ),
				'type' => 'title',
				'desc' => '',
				'id' => 'other_title',
			],
		];

		/**
		 * Filter the Other section array.
		 *
		 * This can be used to add settings directly after the title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Other section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/other/title', $section_start );

		// Init Contribution settings.
		$settings = [
			'hide_orders_tab_for_non_customers' => [
				'title' => __( 'Hide "Woo Orders" Tab for non-customers', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'Remove the "Woo Orders" Tab from the CiviCRM Contact screen for non-customer Contacts.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_hide_orders_tab_for_non_customers',
			],
			'replace_woocommerce_states' => [
				'title' => __( 'Replace WooCommerce States', 'wpcv-woo-civi-integration' ),
				'type' => 'checkbox',
				'desc' => __( 'WARNING, POSSIBLE DATA LOSS! If enabled, this plugin will replace the list of States/Countries in WooCommerce with the States/Provinces list from CiviCRM. If this is not a fresh install of WooCommerce and CiviCRM, then you WILL lose any existing State/Country data for existing Customers. Any WooCommerce Settings that rely on State/Country will have to be reconfigured.', 'wpcv-woo-civi-integration' ),
				'id'   => 'woocommerce_civicrm_replace_woocommerce_states',
			],
		];

		/**
		 * Filter the Other settings array.
		 *
		 * This can be used to add further Other settings.
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Other settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/other/settings', $settings );

		// Declare section end.
		$section_end = [
			'other_section_end' => [
				'type' => 'sectionend',
				'id' => 'other_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Other fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The Other fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/other', $fields );

	}

}
