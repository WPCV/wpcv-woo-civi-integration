<?php
/**
 * WPCV WooCommerce CiviCRM Helper Class.
 *
 * Miscellaneous utilities.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Helper class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Helper {

	/**
	 * The active Financial Types.
	 *
	 * Array of key/value pairs holding the active financial types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The financial types.
	 */
	public $financial_types;

	/**
	 * The active Membership Types.
	 *
	 * Array of key/value pairs holding the active Membership types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The active Membership types.
	 */
	public $membership_types;

	/**
	 * The CiviCRM Membership Signup OptionValue.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $optionvalue_membership_signup The CiviCRM Membership Signup OptionValue.
	 */
	public $optionvalue_membership_signup;

	/**
	 * The Address Location Types.
	 *
	 * Array of key/value pairs holding the address location types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $location_types The Address Location Types.
	 */
	public $location_types;

	/**
	 * WooCommerce/CiviCRM mapped address location types.
	 *
	 * Array of key/value pairs holding the WooCommerce/CiviCRM address location types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $mapped_location_types The WooCommerce/CiviCRM mapped address location types.
	 */
	public $mapped_location_types;

	/**
	 * CiviCRM states.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $states The CiviCRM states.
	 */
	public $civicrm_states = [];

	/**
	 * CiviCRM campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM campaigns.
	 */
	public $campaigns = [];

	/**
	 * The complete set of CiviCRM campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $all_campaigns The complete set of CiviCRM campaigns.
	 */
	public $all_campaigns = [];

	/**
	 * CiviCRM campaigns status.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM campaigns status.
	 */
	public $campaigns_status = [];

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->inited();
	}

	/**
	 * Initialise this object.
	 *
	 * @since 2.0
	 */
	public function inited() {

		if ( ! WPCV_WCI()->boot_civi() ) {
			return;
		}
		$this->financial_types = $this->get_financial_types();
		$this->membership_types = $this->get_civicrm_membership_types();
		$this->location_types = $this->get_address_location_types();
		$this->civicrm_states = $this->get_civicrm_states();
		$this->campaigns_status = $this->get_campaigns_status();
		$this->campaigns = $this->get_campaigns();
		$this->all_campaigns = $this->get_all_campaigns();
		$this->mapped_location_types = $this->get_mapped_location_types();
		$this->optionvalue_membership_signup = $this->get_civicrm_optionvalue_membership_signup();

	}

	/**
	 * Get a CiviCRM Contact ID for a given WooCommerce Order.
	 *
	 * @since 2.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return int $cid The numeric ID of the CiviCRM Contact.
	 */
	public function civicrm_get_cid( $order ) {

		$email = '';

		// If user is logged in but not in the admin (not a manual order).
		if ( is_user_logged_in() && ! is_admin() ) {
			$current_user = wp_get_current_user();
			$email = $current_user->user_email;
		} else {
			// if there was a customer user field in form (manual order).
			if ( filter_input( INPUT_POST, 'customer_user', FILTER_VALIDATE_INT ) ) {
				$cu_id = filter_input( INPUT_POST, 'customer_user', FILTER_VALIDATE_INT );

				$user_info = get_userdata( $cu_id );
				$email = $user_info->user_email;

			} else {
				$email = $order->get_billing_email();
			}
		}

		$wp_user_id = $order->get_user_id();

		// Backend order should not use the logged in user's contact.
		if ( ! is_admin() && 0 !== $wp_user_id ) {
			try {
				$uf_match = civicrm_api3(
					'UFMatch',
					'get',
					[
						'sequential' => 1,
						'uf_id' => $wp_user_id,
					]
				);
				if ( 1 === $uf_match['count'] ) {
					return $uf_match['values'][0]['contact_id'];
				}
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Failed to get contact from UF table', 'wpcv-woo-civi-integration' ) );
				CRM_Core_Error::debug_log_message( $e->getMessage() );
			}
		} elseif ( $email != '' ) {
			// The customer is anonymous. Look in the CiviCRM contacts table for a
			// contact that matches the billing email.
			$params = [
				'email' => $email,
				'return.contact_id' => true,
				'sequential' => 1,
			];
		}

		if ( ! isset( $params ) ) {
			CRM_Core_Error::debug_log_message( __( 'Cannot guess contact without an email', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		try {
			$contact = civicrm_api3( 'Contact', 'get', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Failed to get contact by email', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		// No matches found, so we will need to create a Contact.
		if ( count( $contact ) == 0 ) {
			return 0;
		}

		$cid = isset( $contact['values'][0]['id'] ) ? $contact['values'][0]['id'] : 0;

		return $cid;

	}

	/**
	 * Get CiviCRM UFMatch data.
	 *
	 * Get UFMatch for contact_id or WP user_id.
	 *
	 * @since 2.0
	 *
	 * @param int $id The CiviCRM Contact ID or WordPress User ID.
	 * @param string $property Either 'contact_id' or 'uf_id'.
	 * @return array $uf_match The UFMatch data.
	 */
	public function get_civicrm_ufmatch( $id, $property ) {

		// TODO: Proper return values.
		if ( ! in_array( $property, [ 'contact_id', 'uf_id' ], true ) ) {
			return;
		}

		try {
			$uf_match = civicrm_api3(
				'UFMatch',
				'getsingle',
				[
					'sequential' => 1,
					$property => $id,
				]
			);
		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		if ( ! empty( $uf_match['is_error'] ) ) {
			return $uf_match;
		}

	}

	/**
	 * Get a CiviCRM Country ID for a given WooCommerce Country ISO Code.
	 *
	 * @since 2.0
	 *
	 * @param string $woocommerce_country TheWooCommerce country ISO code.
	 * @return int $id The CiviCRM Country ID.
	 */
	public function get_civi_country_id( $woocommerce_country ) {

		// TODO: Proper return values.
		if ( empty( $woocommerce_country ) ) {
			return;
		}

		$result = civicrm_api3(
			'Country',
			'getsingle',
			[
				'sequential' => 1,
				'iso_code' => $woocommerce_country,
			]
		);

		if ( ! $result['id'] ) {
			return;
		}

		return $result['id'];

	}

	/**
	 * Get the CiviCRM Country ISO Code for a given Country ID.
	 *
	 * @since 2.0
	 *
	 * @param int $country_id The numeric ID of the CiviCRM Country.
	 * @return string $iso_code The CiviCRM Country ISO Code.
	 */
	public function get_civi_country_iso_code( $country_id ) {

		// TODO: Proper return values.
		if ( empty( $country_id ) ) {
			return;
		}

		$result = civicrm_api3(
			'Country',
			'getsingle',
			[
				'sequential' => 1,
				'id' => $country_id,
			]
		);

		if ( ! $result['iso_code'] ) {
			return;
		}

		return $result['iso_code'];

	}

	/**
	 * Get the ID of a CiviCRM State/Province for a WooCommerce State.
	 *
	 * @since 2.0
	 *
	 * @param string $woocommerce_state The WooCommerce State.
	 * @param int $country_id The numeric ID of the CiviCRM Country.
	 * @return int $id The numeric ID of the CiviCRM State/Province.
	 */
	public function get_civi_state_province_id( $woocommerce_state, $country_id ) {

		// TODO: Proper return values.
		if ( empty( $woocommerce_state ) ) {
			return;
		}

		if ( empty( $this->civicrm_states ) ) {
			$this->civicrm_states = $this->get_civicrm_states();
		}

		foreach ( $this->civicrm_states as $state_id => $state ) {
			if ( $state['country_id'] === $country_id && $state['abbreviation'] === $woocommerce_state ) {
				return $state['id'];
			}

			if ( $state['country_id'] === $country_id && $state['name'] === $woocommerce_state ) {
				return $state['id'];
			}
		}

	}

	/**
	 * Get a CiviCRM State/Province Name or Abbreviation by its ID.
	 *
	 * @since 2.0
	 *
	 * @param int $state_province_id The numeric ID of the CiviCRM State.
	 * @return string $name THe CiviCRM State/Province Name or Abbreviation.
	 */
	public function get_civi_state_province_name( $state_province_id ) {

		// TODO: Proper return values.
		if ( empty( $state_province_id ) ) {
			return;
		}

		if ( empty( $this->civicrm_states ) ) {
			$this->civicrm_states = $this->get_civicrm_states();
		}

		$civi_state = $this->civicrm_states[ $state_province_id ];

		$woocommerce_countries = new WC_Countries();

		foreach ( $woocommerce_countries->get_states() as $country => $states ) {
			$found = array_search( $civi_state['name'], $states, true );
			if ( ! empty( $states ) && $found ) {
				return $found;
			}
		}

		return $civi_state['name'];

	}

	/**
	 * Get the Address Field mappings between WooCommerce and CiviCRM.
	 *
	 * @since 2.0
	 *
	 * @param string $address_type The WooCommerce address type. Either 'billing' or 'shipping'.
	 * @return array $mapped_address The Address Field mappings.
	 */
	public function get_mapped_address( $address_type ) {

		$mapped_address = [
			$address_type . '_address_1' => 'street_address',
			$address_type . '_address_2' => 'supplemental_address_1',
			$address_type . '_city' => 'city',
			$address_type . '_postcode' => 'postal_code',
			$address_type . '_country' => 'country_id',
			$address_type . '_state' => 'state_province_id',
			$address_type . '_company' => 'name',
		];

		/**
		 * Filter the Address Field mappings.
		 *
		 * @since 2.0
		 *
		 * @param array $mapped_address The default Address Field mappings.
		 */
		return apply_filters( 'wpcv_woo_civi/address_fields/mappings', $mapped_address );

	}

	/**
	 * Get data for CiviCRM States.
	 *
	 * Build multi-dimensional array of CiviCRM States, e.g.
	 * array( 'state_id' => array( 'name', 'id', 'abbreviation', 'country_id' ) )
	 *
	 * @since 2.0
	 *
	 * @return array $civicrm_states The array of data for CiviCRM States.
	 */
	private function get_civicrm_states() {

		if ( ! empty( $this->civicrm_states ) ) {
			return $this->civicrm_states;
		}

		$query = 'SELECT name,id,country_id,abbreviation FROM civicrm_state_province';

		$dao = CRM_Core_DAO::executeQuery( $query );
		$civicrm_states = [];
		while ( $dao->fetch() ) {
			$civicrm_states[ $dao->id ] = [
				'id' => $dao->id,
				'name' => $dao->name,
				'abbreviation' => $dao->abbreviation,
				'country_id' => $dao->country_id,
			];
		}

		return $civicrm_states;

	}

	/**
	 * Get CiviCRM Campaigns.
	 *
	 * Build multidimentional array of CiviCRM Campaigns, e.g.
	 * array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) )
	 *
	 * @since 2.2
	 *
	 * @return array $civicrm_campaigns The array of data for CiviCRM Campaigns.
	 */
	private function get_campaigns() {

		if ( ! empty( $this->civicrm_campaigns ) ) {
			return $this->civicrm_campaigns;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name' ],
			'is_active' => 1,
			'status_id' => [ 'NOT IN' => [ 'Completed', 'Cancelled' ] ],
			'options' => [
				'sort' => 'name',
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaigns params before calling the Civi's API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaigns/get/params', $params );

		$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );

		$civicrm_campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];
		foreach ( $campaigns_result['values'] as $key => $value ) {
			$civicrm_campaigns[ $value['id'] ] = $value['name'];
		}
		return $civicrm_campaigns;

	}

	/**
	 * Get all CiviCRM Campaigns with Status.
	 *
	 * Build multidimentional array of all CiviCRM Campaigns, e.g.
	 * array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 *
	 * @return array $all_campaigns The array of data for all CiviCRM Campaigns.
	 */
	private function get_all_campaigns() {

		if ( ! empty( $this->all_campaigns ) ) {
			return $this->all_campaigns;
		}

		if ( ! empty( $this->campaigns_status ) ) {
			$this->campaigns_status = $this->get_campaigns_status();
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name', 'status_id' ],
			'options' => [
				'sort' => 'status_id ASC , created_date DESC , name ASC',
				'limit' => 0,
			],
		];

		/**
		 * Filter all Campaigns params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaigns/get_all/params', $params );

		$all_campaigns_result = civicrm_api3( 'Campaign', 'get', $params );

		$all_campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $all_campaigns_result['values'] as $key => $value ) {
			$status = '';
			if ( isset( $value['status_id'] ) && isset( $this->campaigns_status[ $value['status_id'] ] ) ) {
				$status = ' - ' . $this->campaigns_status[ $value['status_id'] ];
			}
			$all_campaigns[ $value['id'] ] = $value['name'] . $status;
		}

		return $all_campaigns;

	}

	/**
	 * Get CiviCRM Campaign Statuses.
	 *
	 * Build multidimentional array of CiviCRM Campaign Statuses, e.g.
	 * array( 'status_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 *
	 * @return array $civicrm_campaigns_status The array of CiviCRM Campaign Statuses.
	 */
	private function get_campaigns_status() {

		if ( ! empty( $this->campaigns_status ) ) {
			return $this->campaigns_status;
		}

		$params = [
			'sequential' => 1,
			'option_group_id' => 'campaign_status',
		];

		/**
		 * Filter Campaign Statuses params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaign_statuses/get/params', $params );

		$status_result = civicrm_api3( 'OptionValue', 'get', $params );

		if ( 0 === $status_result['is_error'] && $status_result['count'] > 0 ) {

			$civicrm_campaigns_status = [];
			foreach ( $status_result['values'] as $key => $value ) {
				$civicrm_campaigns_status[ $value['value'] ] = $value['label'];
			}

			return $civicrm_campaigns_status;

		} else {
			return false;
		}

	}

	/**
	 * Get mapping between WooCommerce and CiviCRM Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $mapped_location_types The mapped Location Types.
	 */
	private function get_mapped_location_types() {

		$mapped_location_types = [
			'billing' => get_option( 'woocommerce_civicrm_billing_location_type_id' ),
			'shipping' => get_option( 'woocommerce_civicrm_shipping_location_type_id' ),
		];

		/**
		 * Filter mapping between WooCommerce and CiviCRM location types.
		 *
		 * @since 2.0
		 *
		 * @param array $mapped_location_types The default mapped Location Types.
		 */
		return apply_filters( 'wpcv_woo_civi/location_types/mappings', $mapped_location_types );

	}

	/**
	 * Get CiviCRM Financial Types.
	 *
	 * @since 2.0
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types.
	 */
	private function get_financial_types() {

		if ( isset( $this->financial_types ) ) {
			return $this->financial_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
		];

		/**
		 * Filter Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/financial_types/get/params', $params );

		$financial_types_result = civicrm_api3( 'FinancialType', 'get', $params );

		$financial_types = [];
		foreach ( $financial_types_result['values'] as $key => $value ) {
			$financial_types[ $value['id'] ] = $value['name'];
		}

		return $financial_types;

	}

	/**
	 * Get CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $address_types_result The array of CiviCRM Address Location Types.
	 */
	private function get_address_location_types() {

		if ( isset( $this->location_types ) ) {
			return $this->location_types;
		}

		$address_types_result = civicrm_api3( 'Address', 'getoptions', [ 'field' => 'location_type_id' ] );

		return $address_types_result['values'];

	}

	/**
	 * Get CiviCRM Membership Types.
	 *
	 * @since 2.0
	 *
	 * @return array $membership_types The array of CiviCRM Membership Types.
	 */
	public function get_civicrm_membership_types() {

		if ( isset( $this->membership_types ) ) {
			return $this->membership_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
		];

		/**
		 * Filter the Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/membership_types/get/params', $params );

		$membership_types_result = civicrm_api3( 'MembershipType', 'get', $params );

		$membership_types = [];
		foreach ( $membership_types_result['values'] as $key => $value ) {
			$membership_types['by_membership_type_id'][ $value['id'] ] = $value;
			$membership_types['by_financial_type_id'][ $value['financial_type_id'] ] = $value;
		}

		/**
		 * Filter the CiviCRM Membership Types.
		 *
		 * @since 2.0
		 *
		 * @param array $membership_types The existing array of CiviCRM Membership Types.
		 */
		return apply_filters( 'wpcv_woo_civi/membership_types', $membership_types, $membership_types_result );

	}

	/**
	 * Get the CiviCRM Membership Signup OptionValue.
	 *
	 * @since 2.0
	 *
	 * @return array $result The CiviCRM Membership Signup OptionValue.
	 */
	public function get_civicrm_optionvalue_membership_signup() {

		$result = civicrm_api3(
			'OptionValue',
			'get',
			[
				'sequential' => 1,
				'return' => [ 'value' ],
				'name' => 'Membership Signup',
			]
		);

		// TODO: error check and return values.
		return $result['values'][0]['value'];

	}

	/**
	 * Check whether a value is (string) 'yes'.
	 *
	 * @since 2.0
	 *
	 * @param string $value The value to check.
	 * @return bool true | false
	 */
	public function check_yes_no_value( $value ) {
		return 'yes' === $value ? true : false;
	}

	/**
	 * Get WordPress sites on a Multisite installation.
	 *
	 * @since 2.0
	 *
	 * @return array $sites The array of sites.
	 */
	public function get_sites() {

		$sites = [];

		if ( is_multisite() ) {
			$wp_sites = get_sites(
				[
					'orderby' => 'domain',
				]
			);
			foreach ( $wp_sites as $site ) {
				$sites[ $site->blog_id ] = $site->domain;
			}
		}

		return $sites;

	}

	/**
	 * Get the default Contribution amount data.
	 *
	 * Values retrieved are: price set, price_field, and price field value.
	 *
	 * @since 2.4
	 *
	 * @return array $default_contribution_amount_data The default contribution amount data.
	 */
	public function get_default_contribution_price_field_data() {

		try {
			$price_set = civicrm_api3(
				'PriceSet',
				'getsingle',
				[
					'name' => 'default_contribution_amount',
					'is_reserved' => true,
					'api.PriceField.getsingle' => [
						'price_set_id' => "\$value.id",
						'options' => [
							'limit' => 1,
							'sort' => 'id ASC',
						],
					],
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to retrieve default price set', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return null;
		}

		$price_field = $price_set['api.PriceField.getsingle'];
		unset( $price_set['api.PriceField.getsingle'] );

		$default_contribution_amount_data = [
			'price_set' => $price_set,
			'price_field' => $price_field,
		];

		return $default_contribution_amount_data;


	}

	/**
	 * Get the formatted Financial Types options.
	 *
	 * @since 2.4
	 *
	 * @return array $financial_types The Financial Types.
	 */
	public function get_financial_types_options() {

		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );

		$options = [
			sprintf(
				/* translators: %s: The Financial Type */
				'-- ' . __( 'Default (%s)', 'wpcv-woo-civi-integration' ),
				$this->financial_types[ $default_financial_type_id ] ?? __( 'Not set', 'wpcv-woo-civi-integration' )
			),
		]
		+ $this->financial_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'wpcv-woo-civi-integration' ),
		];

		return $options;

	}

	/**
	 * Get the CiviCRM Membership Types options.
	 *
	 * @since 2.4
	 *
	 * @return array $membership_types_options The CiviCRM Membership Types options.
	 */
	public function get_membership_types_options() {

		try {
			$membership_types = civicrm_api3(
				'MembershipType',
				'get',
				[
					'is_active' => true,
					'options.limit' => 0,
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Types.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return [];
		}

		if ( empty( $membership_types['count'] ) ) {
			return [];
		}

		$membership_types_options = [
			0 => '',
		];

		$membership_types_options = array_reduce(
			$membership_types['values'],
			function( $list, $membership_type ) {

				$list[ $membership_type['id'] ] = $membership_type['name'];

				return $list;

			},
			$membership_types_options
		);

		return $membership_types_options;

	}

	/**
	 * Get a CiviCRM Membership Type by its ID.
	 *
	 * @since 2.4
	 *
	 * @param int $id The numeric ID of the CiviCRM Membership Type.
	 * @return array|null $membership_type The CiviCRM Membership Type data, or null on failure.
	 */
	public function get_membership_type( int $id ) {

		try {
			return civicrm_api3(
				'MembershipType',
				'gesingle',
				[
					'id' => $id,
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			return null;
		}

	}

}
