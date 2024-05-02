<?php
/**
 * Settings class.
 *
 * Handles admin functionality including:
 *
 * * Managing plugin settings and upgrade tasks.
 * * The "CiviCRM Settings" tab on the WooCommerce Settings screen.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * @since 2.0
 * @since 3.0 Renamed.
 */
class WPCV_Woo_Civi_Settings {

	/**
	 * The installed version of the plugin.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $plugin_version The plugin version.
	 */
	public $plugin_version;

	/**
	 * Upgrade management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $upgrade The Upgrade management object.
	 */
	public $upgrade;

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

		$this->initialise_settings();
		$this->register_hooks();

	}

	/**
	 * Initialise settings.
	 *
	 * @since 3.0
	 */
	public function initialise_settings() {

		// Assign installed plugin version.
		$this->plugin_version = $this->option_get( 'wpcv_woo_civi_version', false );

		// Do upgrade tasks.
		$this->upgrade_tasks();

		// Store version for later reference if there has been a change.
		if ( WPCV_WOO_CIVI_VERSION !== $this->plugin_version ) {
			$this->option_set( 'wpcv_woo_civi_version', WPCV_WOO_CIVI_VERSION );
		}

	}

	/**
	 * Performs tasks when an upgrade is required.
	 *
	 * @since 3.0
	 */
	public function upgrade_tasks() {

		/*
		// If this is a new install (or a migration from a version prior to 3.0).
		if ( false === $this->plugin_version ) {
			// Already handled by migration.
		}
		*/

		/*
		// If this is an upgrade.
		if ( WPCV_WOO_CIVI_VERSION !== $this->plugin_version ) {
			// Do something.
		}
		*/

		/*
		// For future upgrades, use something like the following.
		if ( version_compare( WPCV_WOO_CIVI_VERSION, '3.0.1', '>=' ) ) {
			// Do something.
		}
		*/

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Register a select with optgroup.
		add_action( 'woocommerce_admin_field_select_optgroup', [ $this, 'select_optgroup' ] );

		// Add CiviCRM settings tab.
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'tab_add' ], 50 );

		// Add settings for this plugin.
		add_action( 'woocommerce_settings_woocommerce_civicrm', [ $this, 'fields_add' ], 10 );

		// Update settings for this plugin.
		add_action( 'woocommerce_update_options_woocommerce_civicrm', [ $this, 'fields_update' ] );

		// Always enable CiviCRM Settings panel on Simple Products.
		add_filter( 'default_option_woocommerce_civicrm_product_types_with_panel', [ $this, 'option_panel_default' ], 10, 3 );

		// Use the "Individual" CiviCRM Contact Type by default.
		add_filter( 'default_option_woocommerce_civicrm_contact_type', [ $this, 'option_contact_type_default' ], 10, 3 );

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
		$order_fields   = $this->fields_order();
		$product_fields = $this->fields_product();
		$contact_fields = $this->fields_contact();
		$address_fields = $this->fields_address();
		$other_fields   = $this->fields_other();
		$fields         = $order_fields + $product_fields + $contact_fields + $address_fields + $other_fields;

		/**
		 * Filter the plugin fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The plugin fields array.
		 */
		$fields = apply_filters( 'wpcv_woo_civi/woo_settings/fields', $fields );

		return $fields;

	}

	/**
	 * Defines the Order settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of Order settings fields.
	 */
	public function fields_order() {

		// Init Order section.
		$section_start = [
			'order_title' => [
				'title' => __( 'Order settings', 'wpcv-woo-civi-integration' ),
				'type'  => 'title',
				'desc'  => __( 'This plugin needs to know some information to configure the Orders that are created in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'id'    => 'order_title',
			],
		];

		/**
		 * Filter the Order section array.
		 *
		 * This can be used to add settings directly after the title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Order section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/order/title', $section_start );

		// Init Order settings.
		$settings = [
			'pay_later_gateways'         => [
				'title'    => __( 'Pay Later Payment Methods', 'wpcv-woo-civi-integration' ),
				'type'     => 'multiselect',
				'options'  => WPCV_WCI()->helper->get_payment_gateway_options(),
				'id'       => 'woocommerce_civicrm_pay_later_gateways',
				'autoload' => false,
				'desc'     => __( 'Select all the WooCommerce Payment Methods that are considered "Pay Later" in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'class'    => 'wc-enhanced-select',
			],
			'ignore_0_amount_orders'     => [
				'title' => __( 'Do not create 0 amount Contributions', 'wpcv-woo-civi-integration' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Do not create Contributions for Orders with a total of 0, e.g. for free Products or when using a Coupon.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_ignore_0_amount_orders',
			],
			'financial_type_id'          => [
				'title'   => __( 'Financial Type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select',
				'desc'    => __( 'CiviCRM needs to know what Financial Type to assign to an Order when there are multiple Products in that Order. (Note: every Product that creates a Contribution in CiviCRM should have its Entity Type, Financial Type and Price Field specified in its "CiviCRM Settings" tab.)', 'wpcv-woo-civi-integration' ),
				'options' => WPCV_WCI()->helper->get_financial_types(),
				'id'      => 'woocommerce_civicrm_financial_type_id',
			],
			'financial_type_shipping_id' => [
				'title'   => __( 'Shipping Financial Type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select',
				/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
				'desc'    => sprintf( __( 'Tip: you can manage your %1$sFinancial Types in CiviCRM%2$s.', 'wpcv-woo-civi-integration' ), '<a href="' . WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/admin/financial/financialType', 'reset=1' ) . '">', '</a>' ),
				'options' => WPCV_WCI()->helper->get_financial_types(),
				'id'      => 'woocommerce_civicrm_financial_type_shipping_id',
			],
		];

		/**
		 * Filter the Order settings array.
		 *
		 * This can be used to add further Order settings.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Campaign::campaign_settings_add() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Order settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/order/settings', $settings );

		// Declare section end.
		$section_end = [
			'order_section_end' => [
				'type' => 'sectionend',
				'id'   => 'order_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Order fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The Order fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/order', $fields );

	}

	/**
	 * Defines the Product settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of Product settings fields.
	 */
	public function fields_product() {

		// Init Product section.
		$section_start = [
			'product_title' => [
				'title' => __( 'Product settings', 'wpcv-woo-civi-integration' ),
				'type'  => 'title',
				// 'desc' => __( 'This plugin needs to know some information to configure WooCommerce Products.', 'wpcv-woo-civi-integration' ),
				'id'    => 'product_title',
			],
		];

		// Init Product settings.
		$settings = [
			'product_types_with_panel' => [
				'title'    => __( 'Product Types with CiviCRM Settings', 'wpcv-woo-civi-integration' ),
				'type'     => 'multiselect',
				'options'  => WPCV_WCI()->helper->get_product_types_options( false ),
				'id'       => 'woocommerce_civicrm_product_types_with_panel',
				'autoload' => false,
				'desc'     => __( 'Select the WooCommerce Product Types to show the CiviCRM Settings panel on. You will need to update this setting if you enable additional plugins that provide Custom Product Types that you want to include as Line Items in CiviCRM Orders.', 'wpcv-woo-civi-integration' ),
				'class'    => 'wc-enhanced-select',
			],
		];

		/**
		 * Filter the Product settings array.
		 *
		 * This can be used to add further Product settings.
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Product settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/product/settings', $settings );

		/**
		 * Filter the Product section array.
		 *
		 * This can be used to add settings directly after the title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Product section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/product/title', $section_start );

		// Declare section end.
		$section_end = [
			'product_section_end' => [
				'type' => 'sectionend',
				'id'   => 'product_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Product fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The Product fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/product', $fields );

	}

	/**
	 * Defines the Contact settings fields.
	 *
	 * @since 3.0
	 *
	 * @return array $fields The array of Contact settings fields.
	 */
	public function fields_contact() {

		// Init Contact section.
		$section_start = [
			'contact_title' => [
				'title' => __( 'Contact settings', 'wpcv-woo-civi-integration' ),
				'type'  => 'title',
				// 'desc' => __( 'This plugin needs to know some information to configure WooCommerce Contacts.', 'wpcv-woo-civi-integration' ),
				'id'    => 'contact_title',
			],
		];

		// Init Contact settings.
		$settings = [
			'contact_type'    => [
				'title'   => __( 'Contact Type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select_optgroup',
				'desc'    => __( 'Select the type of Contact that is created when an Order is processed. The most common (and recommended) configuration is to have this set to "Individual". Stick with the default unless you have a good reason to change it.', 'wpcv-woo-civi-integration' ),
				'options' => WPCV_WCI()->contact->types_get(),
				'id'      => 'woocommerce_civicrm_contact_type',
			],
			'contact_subtype' => [
				'title'   => __( 'Contact Sub-type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select_optgroup',
				/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
				'desc'    => sprintf( __( 'Select the sub-type that is assigned to Contacts created (or updated) when an Order is processed. Leave this set to "No Sub-type selected" if you do not want new Contacts to have a sub-type. Tip: you can manage your %1$sContact Sub-types in CiviCRM%2$s.', 'wpcv-woo-civi-integration' ), '<a href="' . WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/admin/options/subtype', 'reset=1' ) . '">', '</a>' ),
				'options' => WPCV_WCI()->contact->subtypes_get_options(),
				'id'      => 'woocommerce_civicrm_contact_subtype',
			],
			'dedupe_rule'     => [
				'title'   => __( 'Dedupe Rule', 'wpcv-woo-civi-integration' ),
				'type'    => 'select_optgroup',
				'desc'    => __( 'Select the Dedupe Rule to use when matching Users to Contacts.', 'wpcv-woo-civi-integration' ),
				'options' => WPCV_WCI()->contact->dedupe_rules_get(),
				'id'      => 'woocommerce_civicrm_dedupe_rule',
			],
		];

		/**
		 * Filter the Contact settings array.
		 *
		 * This can be used to add further Contact settings.
		 *
		 * @since 3.0
		 *
		 * @param array $settings The Contact settings array.
		 */
		$settings = apply_filters( 'wpcv_woo_civi/woo_settings/fields/contact/settings', $settings );

		/**
		 * Filter the Contact section array.
		 *
		 * This can be used to add settings directly after the title.
		 *
		 * @since 3.0
		 *
		 * @param array $section_start The Contact section settings array.
		 */
		$section_start = apply_filters( 'wpcv_woo_civi/woo_settings/fields/contact/title', $section_start );

		// Declare section end.
		$section_end = [
			'contact_section_end' => [
				'type' => 'sectionend',
				'id'   => 'contact_title',
			],
		];

		// Combine these fields.
		$fields = $section_start + $settings + $section_end;

		/**
		 * Filter the Contact fields array.
		 *
		 * @since 3.0
		 *
		 * @param array $fields The Contact fields array.
		 */
		return apply_filters( 'wpcv_woo_civi/woo_settings/fields/contact', $fields );

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
				'type'  => 'title',
				'desc'  => '',
				// 'desc' => __( 'Default settings for synchronizing Addresses in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'id'    => 'address_title',
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
			'billing_location_type_id'  => [
				'title'   => __( 'Billing Location Type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select',
				'options' => WPCV_WCI()->contact->address->get_location_types(),
				'id'      => 'woocommerce_civicrm_billing_location_type_id',
			],
			'shipping_location_type_id' => [
				'title'   => __( 'Shipping Location Type', 'wpcv-woo-civi-integration' ),
				'type'    => 'select',
				/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
				'desc'    => sprintf( __( 'Tip: you can manage your %1$sLocation Types in CiviCRM%2$s.', 'wpcv-woo-civi-integration' ), '<a href="' . WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/admin/locationType', 'reset=1' ) . '">', '</a>' ),
				'options' => WPCV_WCI()->contact->address->get_location_types(),
				'id'      => 'woocommerce_civicrm_shipping_location_type_id',
			],
			'sync_contact_address'      => [
				'title' => __( 'Sync Address', 'wpcv-woo-civi-integration' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Synchronize WooCommerce User Address with its matching CiviCRM Contact Address and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_sync_contact_address',
			],
			'sync_contact_phone'        => [
				'title' => __( 'Sync Billing Phone', 'wpcv-woo-civi-integration' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Synchronize WooCommerce User Billing Phone Number with its matching CiviCRM Contact Billing Phone Number and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_sync_contact_phone',
			],
			'sync_contact_email'        => [
				'title' => __( 'Sync Billing Email', 'wpcv-woo-civi-integration' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Synchronize WooCommerce User Billing Email with its matching CiviCRM Contact Billing Email and vice versa.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_sync_contact_email',
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
				'id'   => 'address_title',
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
				'type'  => 'title',
				'desc'  => '',
				'id'    => 'other_title',
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
				'type'  => 'checkbox',
				'desc'  => __( 'Remove the "Woo Orders" Tab from the CiviCRM Contact screen for non-customer Contacts.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_hide_orders_tab_for_non_customers',
			],
			'replace_woocommerce_states'        => [
				'title' => __( 'Replace WooCommerce States', 'wpcv-woo-civi-integration' ),
				'type'  => 'checkbox',
				'desc'  => __( 'WARNING, POSSIBLE DATA LOSS! If enabled, this plugin will replace the list of States/Countries in WooCommerce with the States/Provinces list from CiviCRM. If this is not a fresh install of WooCommerce and CiviCRM, then you WILL lose any existing State/Country data for existing Customers. Any WooCommerce Settings that rely on State/Country will have to be reconfigured.', 'wpcv-woo-civi-integration' ),
				'id'    => 'woocommerce_civicrm_replace_woocommerce_states',
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
				'id'   => 'other_title',
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

	/**
	 * Test existence of a specified option.
	 *
	 * @since 3.0
	 *
	 * @param string $option_name The name of the option.
	 * @return bool $exists Whether or not the option exists.
	 */
	public function option_exists( $option_name = '' ) {
		if ( $this->option_get( $option_name, 'fenfgehgefdfdjgrkj' ) === 'fenfgehgefdfdjgrkj' ) {
			return false;
		}
		return true;
	}

	/**
	 * Return a value for a specified option.
	 *
	 * @since 3.0
	 *
	 * @param string $option_name The name of the option.
	 * @param string $default The default value of the option if it has no value.
	 * @return mixed $value the value of the option.
	 */
	public function option_get( $option_name = '', $default = false ) {
		$value = get_site_option( $option_name, $default );
		return $value;
	}

	/**
	 * Set a value for a specified option.
	 *
	 * @since 3.0
	 *
	 * @param string $option_name The name of the option.
	 * @param mixed  $value The value to set the option to.
	 * @return bool $success True if the value of the option was successfully updated.
	 */
	public function option_set( $option_name = '', $value = '' ) {
		return update_site_option( $option_name, $value );
	}

	/**
	 * Delete a specified option.
	 *
	 * @since 3.0
	 *
	 * @param string $option_name The name of the option.
	 * @return bool $success True if the option was successfully deleted.
	 */
	public function option_delete( $option_name = '' ) {
		return delete_site_option( $option_name );
	}

	/**
	 * Enable CiviCRM Settings panel on Simple Products by default.
	 *
	 * @since 3.0
	 *
	 * @param mixed  $default The default value to return if the option does not exist.
	 * @param string $option The option name.
	 * @param bool   $passed_default True if `get_option()` was passed a default value.
	 * @return mixed $default The default value when the option does not exist.
	 */
	public function option_panel_default( $default, $option, $passed_default ) {

		// Add Simple Product if there's nothing else.
		if ( empty( $default ) ) {
			$default = [ 'simple' ];
		}

		return $default;

	}

	/**
	 * Use the "Individual" CiviCRM Contact Type if the option does not exist.
	 *
	 * @since 3.0
	 *
	 * @param mixed  $default The default value to return if the option does not exist.
	 * @param string $option The option name.
	 * @param bool   $passed_default True if `get_option()` was passed a default value.
	 * @return mixed $default The default value when the option does not exist.
	 */
	public function option_contact_type_default( $default, $option, $passed_default ) {

		// Use "Individual" if there's nothing else.
		if ( empty( $default ) ) {
			$default = 'Individual';
		}

		return $default;

	}

	/**
	 * Create a select with option groups for a specified option value array.
	 *
	 * @since 3.0
	 *
	 * @param array $value The array of parsed values for the option.
	 */
	public function select_optgroup( $value = [] ) {

		$description = '';
		if ( ! empty( $value['desc'] ) ) {
			$description = '<p class="description">' . wp_kses_post( $value['desc'] ) . '</p>';
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
				<select name="<?php echo esc_attr( $value['id'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>">
					<?php foreach ( $value['options'] as $option_key => $option_value ) : ?>
						<?php if ( is_array( $option_value ) ) : ?>
							<optgroup label="<?php echo esc_attr( $option_key ); ?>">
								<?php foreach ( $option_value as $option_key_inner => $option_value_inner ) : ?>
									<option value="<?php echo esc_attr( $option_key_inner ); ?>" <?php selected( (string) $option_key_inner, (string) esc_attr( $value['value'] ) ); ?>><?php echo esc_html( $option_value_inner ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php else : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, (string) esc_attr( $value['value'] ) ); ?>><?php echo esc_html( $option_value ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select> <?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php

	}

}
