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
	 * Array of key/value pairs holding the active Financial Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The Financial Types.
	 */
	public $financial_types;

	/**
	 * The active Membership Types.
	 *
	 * Array of key/value pairs holding the active Membership Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The active Membership Types.
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
	 * Array of key/value pairs holding the Address Location Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $location_types The Address Location Types.
	 */
	public $location_types;

	/**
	 * WooCommerce/CiviCRM mapped Address Location Types.
	 *
	 * Array of key/value pairs holding the WooCommerce/CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $mapped_location_types The WooCommerce/CiviCRM mapped Address Location Types.
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
	 * CiviCRM Campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM Campaigns.
	 */
	public $campaigns = [];

	/**
	 * The complete set of CiviCRM Campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $all_campaigns The complete set of CiviCRM Campaigns.
	 */
	public $all_campaigns = [];

	/**
	 * CiviCRM Campaign Statuses.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM Campaign Statuses.
	 */
	public $campaigns_status = [];

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
	 * @since 2.0
	 */
	public function initialise() {

		// Empty since class properties are now populated on demand.

	}

	/**
	 * Get a CiviCRM Contact ID for a given WooCommerce Order.
	 *
	 * @since 2.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return int|bool $cid The numeric ID of the CiviCRM Contact if found. Returns
	 *                       0 if a Contact needs to be created, or false on failure.
	 */
	public function civicrm_get_cid( $order ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

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

		// Backend Order should not use the logged in User's Contact.
		if ( ! is_admin() && 0 !== $wp_user_id ) {

			try {

				$params = [
					'sequential' => 1,
					'uf_id' => $wp_user_id,
				];

				$result = civicrm_api3( 'UFMatch', 'get', $params );

				if ( 1 === $result['count'] && ! empty( $result['values'][0]['contact_id'] ) ) {
					return (int) $result['values'][0]['contact_id'];
				}

			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Failed to get a Contact from UFMatch table', 'wpcv-woo-civi-integration' ) );
				CRM_Core_Error::debug_log_message( $e->getMessage() );
				return false;
			}

		} elseif ( $email != '' ) {

			/*
			 * The customer is anonymous. Look in the CiviCRM Contacts table for a
			 * Contact that matches the Billing Email.
			 */
			$params = [
				'email' => $email,
				'return.contact_id' => true,
				'sequential' => 1,
			];

		}

		// Return early if something went wrong.
		if ( ! isset( $params ) ) {
			CRM_Core_Error::debug_log_message( __( 'Cannot guess the Contact without an Email', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		try {
			$contact = civicrm_api3( 'Contact', 'get', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Failed to get Contact by Email', 'wpcv-woo-civi-integration' ) );
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
	 * @return array|bool $result The UFMatch data, or false on failure.
	 */
	public function get_civicrm_ufmatch( $id, $property ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Bail if there's a problem with the param.
		if ( ! in_array( $property, [ 'contact_id', 'uf_id' ], true ) ) {
			return false;
		}

		try {

			$params = [
				'sequential' => 1,
				$property => $id,
			];

			$result = civicrm_api3( 'UFMatch', 'getsingle', $params );

		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		// Return the UFMatch data if there's no error.
		if ( empty( $result['is_error'] ) ) {
			return $result;
		}

		// Fallback.
		return false;

	}

	/**
	 * Get a CiviCRM Country ID for a given WooCommerce Country ISO Code.
	 *
	 * @since 2.0
	 *
	 * @param string $woocommerce_country The WooCommerce Country ISO code.
	 * @return int|bool $id The CiviCRM Country ID, or false on failure.
	 */
	public function get_civi_country_id( $woocommerce_country ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Bail if no Country ISO code is supplied.
		if ( empty( $woocommerce_country ) ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'iso_code' => $woocommerce_country,
		];

		$result = civicrm_api3( 'Country', 'getsingle', $params );

		// Bail if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return false;
		}

		return (int) $result['id'];

	}

	/**
	 * Get the CiviCRM Country ISO Code for a given Country ID.
	 *
	 * @since 2.0
	 *
	 * @param int $country_id The numeric ID of the CiviCRM Country.
	 * @return str|bool $iso_code The CiviCRM Country ISO Code, or false on failure.
	 */
	public function get_civi_country_iso_code( $country_id ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Bail if no Country ID is supplied.
		if ( empty( $country_id ) ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'id' => $country_id,
		];

		$result = civicrm_api3( 'Country', 'getsingle', $params );

		// Bail if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return false;
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
	 * @return int|bool $id The numeric ID of the CiviCRM State/Province, or false on failure.
	 */
	public function get_civi_state_province_id( $woocommerce_state, $country_id ) {

		// Bail if no WooCommerce State is supplied.
		if ( empty( $woocommerce_state ) ) {
			return false;
		}

		// Get CiviCRM States.
		$civicrm_states = $this->get_civicrm_states();

		foreach ( $civicrm_states as $state_id => $state ) {
			if ( $state['country_id'] === $country_id && $state['abbreviation'] === $woocommerce_state ) {
				return (int) $state['id'];
			}

			if ( $state['country_id'] === $country_id && $state['name'] === $woocommerce_state ) {
				return (int) $state['id'];
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Get a CiviCRM State/Province Name or Abbreviation by its ID.
	 *
	 * @since 2.0
	 *
	 * @param int $state_province_id The numeric ID of the CiviCRM State.
	 * @return string|bool $name The CiviCRM State/Province Name or Abbreviation, or false on failure.
	 */
	public function get_civi_state_province_name( $state_province_id ) {

		// Bail if no State/Province ID is supplied.
		if ( empty( $state_province_id ) ) {
			return false;
		}

		// Get CiviCRM States.
		$civicrm_states = $this->get_civicrm_states();

		$civi_state = $civicrm_states[ $state_province_id ];
		$woocommerce_countries = new WC_Countries();

		foreach ( $woocommerce_countries->get_states() as $country => $states ) {
			$name = array_search( $civi_state['name'], $states, true );
			if ( ! empty( $states ) && $name ) {
				return $name;
			}
		}

		// Fallback.
		return $civi_state['name'];

	}

	/**
	 * Get the Address Field mappings between WooCommerce and CiviCRM.
	 *
	 * @since 2.0
	 *
	 * @param string $address_type The WooCommerce Address Type. Either 'billing' or 'shipping'.
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
	public function get_civicrm_states() {

		// Return early if already calculated.
		if ( ! empty( $this->civicrm_states ) ) {
			return $this->civicrm_states;
		}

		$this->civicrm_states = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->civicrm_states;
		}

		// Perform direct query.
		$query = 'SELECT name,id,country_id,abbreviation FROM civicrm_state_province';
		$dao = CRM_Core_DAO::executeQuery( $query );

		while ( $dao->fetch() ) {
			$this->civicrm_states[ $dao->id ] = [
				'id' => $dao->id,
				'name' => $dao->name,
				'abbreviation' => $dao->abbreviation,
				'country_id' => $dao->country_id,
			];
		}

		return $this->civicrm_states;

	}

	/**
	 * Get CiviCRM Campaigns.
	 *
	 * Build multidimentional array of CiviCRM Campaigns, e.g.
	 * array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) )
	 *
	 * @since 2.2
	 *
	 * @return array $campaigns The array of data for CiviCRM Campaigns.
	 */
	public function get_campaigns() {

		// Return early if already calculated.
		if ( isset( $this->campaigns ) ) {
			return $this->campaigns;
		}

		$this->campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->campaigns;
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
		 * Filter Campaigns params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaigns/get/params', $params );

		$result = civicrm_api3( 'Campaign', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->campaigns;
		}

		foreach ( $result['values'] as $key => $value ) {
			$this->campaigns[ $value['id'] ] = $value['name'];
		}

		return $this->campaigns;

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
	public function get_all_campaigns() {

		// Return early if already calculated.
		if ( ! empty( $this->all_campaigns ) ) {
			return $this->all_campaigns;
		}

		$this->all_campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->all_campaigns;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name', 'status_id' ],
			'options' => [
				'sort' => 'status_id ASC, created_date DESC, name ASC',
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

		$result = civicrm_api3( 'Campaign', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->all_campaigns;
		}

		$campaign_statuses = $this->get_campaigns_status();

		foreach ( $result['values'] as $key => $value ) {
			$status = '';
			if ( isset( $value['status_id'] ) && isset( $campaign_statuses[ $value['status_id'] ] ) ) {
				$status = ' - ' . $campaign_statuses[ $value['status_id'] ];
			}
			$this->all_campaigns[ $value['id'] ] = $value['name'] . $status;
		}

		return $this->all_campaigns;

	}

	/**
	 * Get CiviCRM Campaign Statuses.
	 *
	 * Build multidimentional array of CiviCRM Campaign Statuses, e.g.
	 * array( 'status_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 *
	 * @return array $campaigns_status The array of CiviCRM Campaign Statuses.
	 */
	public function get_campaigns_status() {

		// Return early if already calculated.
		if ( ! empty( $this->campaigns_status ) ) {
			return $this->campaigns_status;
		}

		$this->campaigns_status = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->campaigns_status;
		}

		$params = [
			'sequential' => 1,
			'option_group_id' => 'campaign_status',
			'options' => [
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaign Statuses params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaign_statuses/get/params', $params );

		$result = civicrm_api3( 'OptionValue', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->campaigns_status;
		}

		foreach ( $result['values'] as $key => $value ) {
			$this->campaigns_status[ $value['value'] ] = $value['label'];
		}

		return $this->campaigns_status;

	}

	/**
	 * Get mapping between WooCommerce and CiviCRM Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $mapped_location_types The mapped Location Types.
	 */
	public function get_mapped_location_types() {

		// Return early if already calculated.
		if ( ! empty( $this->mapped_location_types ) ) {
			return $this->mapped_location_types;
		}

		$this->mapped_location_types = [
			'billing' => get_option( 'woocommerce_civicrm_billing_location_type_id' ),
			'shipping' => get_option( 'woocommerce_civicrm_shipping_location_type_id' ),
		];

		/**
		 * Filter mapping between WooCommerce and CiviCRM Location Types.
		 *
		 * @since 2.0
		 *
		 * @param array $mapped_location_types The default mapped Location Types.
		 */
		$this->mapped_location_types = apply_filters( 'wpcv_woo_civi/location_types/mappings', $this->mapped_location_types );

		return $this->mapped_location_types;

	}

	/**
	 * Get CiviCRM Financial Types.
	 *
	 * @since 2.0
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types.
	 */
	public function get_financial_types() {

		// Return early if already calculated.
		if ( isset( $this->financial_types ) ) {
			return $this->financial_types;
		}

		$this->financial_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->financial_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		/**
		 * Filter Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/financial_types/get/params', $params );

		$result = civicrm_api3( 'FinancialType', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->financial_types;
		}

		foreach ( $result['values'] as $key => $value ) {
			$this->financial_types[ $value['id'] ] = $value['name'];
		}

		return $this->financial_types;

	}

	/**
	 * Get CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $location_types The array of CiviCRM Address Location Types.
	 */
	public function get_address_location_types() {

		// Return early if already calculated.
		if ( isset( $this->location_types ) ) {
			return $this->location_types;
		}

		$this->location_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->location_types;
		}

		$params = [
			'field' => 'location_type_id',
			'options' => [
				'limit' => 0,
			],
		];

		$result = civicrm_api3( 'Address', 'getoptions', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->location_types;
		}

		// Store values in property.
		if ( ! empty( $result['values'] ) ) {
			$this->location_types = $result['values'];
		}

		return $this->location_types;

	}

	/**
	 * Get CiviCRM Membership Types.
	 *
	 * @since 2.0
	 *
	 * @return array $membership_types The array of CiviCRM Membership Types.
	 */
	public function get_civicrm_membership_types() {

		// Return early if already calculated.
		if ( isset( $this->membership_types ) ) {
			return $this->membership_types;
		}

		$this->membership_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->membership_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		/**
		 * Filter the Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/membership_types/get/params', $params );

		$result = civicrm_api3( 'MembershipType', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->membership_types;
		}

		foreach ( $result['values'] as $key => $value ) {
			$this->membership_types['by_membership_type_id'][ $value['id'] ] = $value;
			$this->membership_types['by_financial_type_id'][ $value['financial_type_id'] ] = $value;
		}

		/**
		 * Filter the CiviCRM Membership Types.
		 *
		 * @since 2.0
		 *
		 * @param array $membership_types The existing array of CiviCRM Membership Types.
		 * @param array $result The CiviCRM API data array.
		 */
		$this->membership_types = apply_filters( 'wpcv_woo_civi/membership_types', $this->membership_types, $result );

		return $this->membership_types;

	}

	/**
	 * Get the CiviCRM Membership Signup OptionValue.
	 *
	 * @since 2.0
	 *
	 * @return array|bool $result The CiviCRM Membership Signup OptionValue, or false on failure.
	 */
	public function get_civicrm_optionvalue_membership_signup() {

		// Return early if already calculated.
		if ( isset( $this->optionvalue_membership_signup ) ) {
			return $this->optionvalue_membership_signup;
		}

		$this->optionvalue_membership_signup = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->optionvalue_membership_signup;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'value' ],
			'name' => 'Membership Signup',
		];

		$result = civicrm_api3( 'OptionValue', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->optionvalue_membership_signup;
		}

		// Sanity check.
		if ( ! empty( $result['values'][0]['value'] ) ) {
			$this->optionvalue_membership_signup = $result['values'][0]['value'];
		}

		return $this->optionvalue_membership_signup;

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
	 * @return array $sites The array of sites keyed by ID.
	 */
	public function get_sites() {

		$sites = [];

		if ( is_multisite() ) {

			$query = [
				'orderby' => 'domain',
			];

			$wp_sites = get_sites( $query );

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
	 * @return array $default_contribution_amount_data The default Contribution amount data.
	 */
	public function get_default_contribution_price_field_data() {

		static $default_contribution_amount_data;
		if ( isset( $default_contribution_amount_data ) ) {
			return $default_contribution_amount_data;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return null;
		}

		try {

			$params = [
				'name' => 'default_contribution_amount',
				'is_reserved' => true,
				'api.PriceField.getsingle' => [
					'price_set_id' => "\$value.id",
					'options' => [
						'limit' => 1,
						'sort' => 'id ASC',
					],
				],
			];

			$price_set = civicrm_api3( 'PriceSet', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve default Price Set', 'wpcv-woo-civi-integration' ) );
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
	 * @return array $financial_types The formatted Financial Types options.
	 */
	public function get_financial_types_options() {

		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );

		$financial_types = $this->get_financial_types();

		$options = [
			sprintf(
				/* translators: %s: The Financial Type */
				'-- ' . __( 'Default (%s)', 'wpcv-woo-civi-integration' ),
				$financial_types[ $default_financial_type_id ] ?? __( 'Not set', 'wpcv-woo-civi-integration' )
			),
		]
		+ $financial_types +
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

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		try {

			$params = [
				'is_active' => true,
				'options' => [
					'limit' => 0,
				],
			];

			$result = civicrm_api3( 'MembershipType', 'get', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Types.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return [];
		}

		if ( empty( $result['count'] ) ) {
			return [];
		}

		$membership_types_options = [
			0 => __( 'None', 'wpcv-woo-civi-integration' ),
		];

		$membership_types_options = array_reduce(
			$result['values'],
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

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return null;
		}

		try {

			$params = [
				'id' => $id,
			];

			return civicrm_api3( 'MembershipType', 'gesingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			return null;
		}

	}

	/**
	 * Gets the CiviCRM Decimal Separator.
	 *
	 * @since 3.0
	 *
	 * @return str|bool $decimal_separator The CiviCRM Decimal Separator, or false on failure.
	 */
	public function get_decimal_separator() {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$decimal_separator = '.';

		try {

			$params = [
				'sequential' => 1,
				'name' => 'monetaryDecimalPoint',
			];

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

			if ( is_string( $result ) ) {
				$decimal_separator = $result;
			}

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Decimal Separator', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		return $decimal_separator;

	}

	/**
	 * Gets the CiviCRM Thousand Separator.
	 *
	 * @since 3.0
	 *
	 * @return str|bool $thousand_separator The CiviCRM Thousand Separator, or false on failure.
	 */
	public function get_thousand_separator() {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$thousand_separator = '';

		try {

			$params = [
				'sequential' => 1,
				'name' => 'monetaryThousandSeparator',
			];

			$civi_thousand_separator = civicrm_api3( 'Setting', 'getvalue', $params );

			if ( is_string( $civi_thousand_separator ) ) {
				$thousand_separator = $civi_thousand_separator;
			}

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Thousand Separator', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		return $thousand_separator;

	}

	/**
	 * Get a CiviCRM admin link.
	 *
	 * @since 3.0
	 *
	 * @param string $path The CiviCRM path.
	 * @param string $params The CiviCRM parameters.
	 * @return string $link The URL of the CiviCRM page.
	 */
	public function get_civi_admin_link( $path = '', $params = null ) {

		// Init link.
		$link = '';

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $link;
		}

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			$path, // Path to the resource.
			$params, // Params to pass to resource.
			true, // Force an absolute link.
			null, // Fragment (#anchor) to append.
			true, // Encode special HTML characters.
			false, // CMS front end.
			true // CMS back end.
		);

		// --<
		return $link;

	}

}
