<?php
/**
 * Contact Address class.
 *
 * Handles syncing Addresses between WooCommerce and CiviCRM.
 *
 * There are some issues to be worked out here because:
 *
 * * An Address can have a Location Type of "Billing".
 * * Multiple Addresses can have their "is_billing" property set.
 *
 * In this plugin the decision was made to go with the "Billing" Location Type
 * and to ignore the "is_billing" property. So for historical reasons, this is
 * the logic that is followed.
 *
 * @see https://lab.civicrm.org/dev/core/-/issues/1727
 * @see https://lab.civicrm.org/dev/core/-/issues/1178
 * @see https://forum.civicrm.org/index.php%3Ftopic=11447.0.html
 * @see https://github.com/artfulrobot/uk.artfulrobot.civicrm.gocardless/blob/1.9.2/CRM/Core/Payment/GoCardless.php#L358
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Contact Address class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Contact_Address {

	/**
	 * Sync enabled flag.
	 *
	 * @since 3.0
	 * @access public
	 * @var bool $sync_enabled True when Address Sync is enabled, false otherwise.
	 */
	public $sync_enabled = false;

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
	 * Class constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		// Init when the Contact class is fully loaded.
		add_action( 'wpcv_woo_civi/contact/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 3.0
	 */
	public function initialise() {

		// Store the WooCommerce option as a boolean.
		$option = get_option( 'woocommerce_civicrm_sync_contact_address', false );
		$this->sync_enabled = WPCV_WCI()->helper->check_yes_no_value( $option );

		// Register Address-related hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Update CiviCRM Address record(s) for User/Contact.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'entities_create' ], 40, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'entities_update' ], 40, 2 );

		// Sync WooCommerce and CiviCRM Address for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Sync WooCommerce and CiviCRM Address for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ], 10, 2 );

	}

	/**
	 * Creates Entities when a Contact has been added.
	 *
	 * @since 3.0
	 *
	 * @param array  $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function entities_create( $contact, $order ) {

		// Pass to update for now.
		$this->entities_update( $contact, $order );

	}

	/**
	 * Updates Entities when a Contact has been edited.
	 *
	 * @since 3.0
	 *
	 * @param array  $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function entities_update( $contact, $order ) {

		$contact_id = $contact['id'];
		$existing_addresses = $this->get_all_by_contact_id( $contact_id );

		try {

			$address_types = WPCV_WCI()->helper->get_mapped_location_types();
			foreach ( $address_types as $address_type => $location_type_id ) {

				// Process Address.
				$address_exists = false;

				// Skip if we don't have both Address_1 and Postcode.
				$address_1 = $order->{'get_' . $address_type . '_address_1'}();
				$postcode = $order->{'get_' . $address_type . '_postcode'}();
				if ( empty( $address_1 ) || empty( $postcode ) ) {
					continue;
				}

				$country = $order->{'get_' . $address_type . '_country'}();
				$country_id = WPCV_WCI()->settings_states->get_civicrm_country_id( $country );
				$state = $order->{'get_' . $address_type . '_state'}();

				// Prime the Address data array.
				$address = [
					'location_type_id'       => $location_type_id,
					'city'                   => $order->{'get_' . $address_type . '_city'}(),
					'postal_code'            => $postcode,
					'name'                   => $order->{'get_' . $address_type . '_company'}(),
					'street_address'         => $address_1,
					'supplemental_address_1' => $order->{'get_' . $address_type . '_address_2'}(),
					'country'                => $country_id,
					'state_province_id'      => WPCV_WCI()->settings_states->get_civicrm_state_province_id( $state, $country_id ),
					'contact_id'             => $contact_id,
				];

				foreach ( $existing_addresses as $existing ) {
					// Does this Address have the desired Location Type?
					if ( isset( $existing->location_type_id ) && $existing->location_type_id === $location_type_id ) {
						$address['id'] = $existing->id;
					} elseif (
						// TODO: Don't create an Address if it's an exact match of another Address.
						// FIXME: should we make 'exact match' configurable?
						isset( $existing->street_address )
						&& isset( $existing->city )
						&& isset( $existing->postal_code )
						&& isset( $address['street_address'] )
						&& $existing->street_address === $address['street_address']
						&& CRM_Utils_Array::value( 'supplemental_address_1', $existing ) === CRM_Utils_Array::value( 'supplemental_address_1', $address )
						&& $existing->city === $address['city']
						&& $existing->postal_code === $address['postal_code']
					) {
						$address_exists = true;
					}
				}

				if ( ! $address_exists ) {

					civicrm_api3( 'Address', 'create', $address );

					$note = sprintf(
						/* translators: 1: Address Type, 2: Street Address */
						__( 'Created new CiviCRM Address of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
						$address_type,
						$address['street_address']
					);

					$order->add_order_note( $note );

				}

			}

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to add/update Address', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				//'params' => $params,
				//'result' => $result,
				'backtrace' => $trace,
			], true ) );

		}

	}

	/**
	 * Sync CiviCRM Address from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Address is edited.
	 *
	 * TODO: This should probably also remove the "civicrm_post" callback because
	 * it is possible for there to be listeners on the "updated_{$meta_type}_meta"
	 * action in WordPress.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/updated_meta_type_meta/
	 *
	 * @since 2.0
	 *
	 * @param string  $op The operation being performed.
	 * @param string  $object_name The entity name.
	 * @param integer $object_id The entity ID.
	 * @param object  $object_ref The entity object.
	 */
	public function sync_civicrm_to_woo( $op, $object_name, $object_id, $object_ref ) {

		// Bail if Address Sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return;
		}

		// Bail if not our target Entity.
		if ( 'Address' !== $object_name ) {
			return;
		}

		// Bail if not our target operation(s).
		if ( 'create' !== $op && 'edit' !== $op ) {
			return;
		}

		// Bail if the Address being edited is not one of the mapped ones.
		$mapped = WPCV_WCI()->helper->get_mapped_location_types();
		if ( ! in_array( (int) $object_ref->location_type_id, $mapped, true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( empty( $object_ref->contact_id ) ) {
			return;
		}

		// Try and find the Contact.
		$contact = WPCV_WCI()->contact->get_by_id( $object_ref->contact_id );
		if ( $contact === false ) {
			return;
		}

		// Is this Contact of the Contact Type in the WooCommerce settings?
		$contact_type = WPCV_WCI()->contact->type_get_synced();

		// Bail if not the synced top-level Contact Type.
		if ( $contact['contact_type'] !== $contact_type['type'] ) {
			return;
		}

		// Bail if not the synced Contact Sub-type.
		if ( ! empty( $contact_type['sub_type'] ) && ! empty( $contact['contact_sub_type'] )  ) {
			if ( ! in_array( $contact_type['sub_type'], $contact['contact_sub_type'] ) ) {
				return;
			}
		}

		// Bail if we don't have a WordPress User.
		$user = WPCV_WCI()->contact->get_ufmatch( $contact['id'], 'contact_id' );
		if ( empty( $user ) ) {
			return;
		}

		// Get the Customr object.
		$customer = new WC_Customer( $user['uf_id'] );

		// Unhook to prevent any possibility of recursion.
		remove_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ] );

		// Update the Fields for the mapped WooCommerce Address Type.
		$address_type = array_search( (int) $object_ref->location_type_id, $mapped, true );
		foreach ( $this->get_field_mappings( $address_type ) as $wc_field => $civi_field ) {

			// Skip if we get one of CiviCRM's null states.
			if ( empty( $object_ref->{$civi_field} ) || 'null' === $object_ref->{$civi_field} ) {
				continue;
			}

			// Get the value of the property.
			$value = $object_ref->{$civi_field};

			// Override for special fields.
			if ( $civi_field === 'country_id' ) {
				$value = WPCV_WCI()->settings_states->get_civicrm_country_iso_code( $value );
			} elseif( $civi_field === 'state_province_id' ) {
				$value = WPCV_WCI()->settings_states->get_civicrm_state_province_name( $value );
			}

			// Update the Customer Field.
			if ( is_callable( [ $customer, "set_{$wc_field}" ] ) ) {
				$customer->{"set_{$wc_field}"}( $value );
			}

		}

		// Additionally update First Name and Last Name if not set.
		if ( ! empty( $contact['first_name'] ) && '' === $customer->{"get_{$address_type}_first_name"}() ) {
			$customer->{"set_{$address_type}_first_name"}( $contact['first_name'] );
		}
		if ( ! empty( $contact['last_name'] ) && '' === $customer->{"get_{$address_type}_last_name"}() ) {
			$customer->{"set_{$address_type}_last_name"}( $contact['last_name'] );
		}

		// Lastly, save the Customer.
		$customer->save();

		// Rehook callback for WooCommerce action.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ], 10, 2 );

		// Let's make an array of the data.
		$args = [
			'op' => $op,
			'object_name' => $object_name,
			'object_id' => $object_id,
			'object_ref' => $object_ref,
			'address_type' => $address_type,
			'customer' => $customer,
			'user_id' => $user['uf_id'],
		];

		/**
		 * Broadcast that a WooCommerce Address has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/address/civicrm_to_woo/synced', $args );

	}

	/**
	 * Sync WooCommerce Address from a User to a CiviCRM Contact.
	 *
	 * Fires when a WooCommerce Address is edited.
	 *
	 * @since 2.0
	 *
	 * @param integer $user_id The WordPress User ID.
	 * @param string  $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_woo_to_civicrm( $user_id, $load_address ) {

		// Bail if Address Sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return false;
		}

		$customer = new WC_Customer( $user_id );

		// Bail if we don't have a CiviCRM Contact.
		$contact = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );
		if ( ! $contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$civi_address_location_type = $mapped_location_types[ $load_address ];
		$edited_address = [];

		foreach ( $this->get_field_mappings( $load_address ) as $wc_field => $civi_field ) {
			switch ( $civi_field ) {
				case 'country_id':
					$edited_address[ $civi_field ] = WPCV_WCI()->settings_states->get_civicrm_country_id( $customer->{'get_' . $wc_field}() );
					continue 2;
				case 'state_province_id':
					$edited_address[ $civi_field ] = WPCV_WCI()->settings_states->get_civicrm_state_province_id( $customer->{'get_' . $wc_field}(), $edited_address['country_id'] );
					continue 2;
				default:
					$edited_address[ $civi_field ] = $customer->{'get_' . $wc_field}();
					continue 2;
			}
		}

		try {

			$params = [
				'contact_id' => $contact['contact_id'],
				'location_type_id' => $civi_address_location_type,
			];

			$civi_address = civicrm_api3( 'Address', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Address', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		// Prevent reverse sync.
		remove_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10 );

		try {

			if ( isset( $civi_address ) && empty( $civi_address['is_error'] ) ) {
				$new_params = array_merge( $civi_address, $edited_address );
			} else {
				$new_params = array_merge( $params, $edited_address );
			}

			$create_address = civicrm_api3( 'Address', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Address', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'new_params' => $new_params,
				'backtrace' => $trace,
			], true ) );

			add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );
			return false;

		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		/**
		 * Broadcast that a CiviCRM Address has been updated.
		 *
		 * @since 2.0
		 *
		 * @param integer $contact_id The CiviCRM Contact ID.
		 * @param array $address The CiviCRM Address that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_address/updated', $contact['contact_id'], $create_address );

		// Success.
		return true;

	}

	/**
	 * Get the Addresses for a Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @return array $addresses The array of data for the Addresses, or empty if none.
	 */
	public function get_all_by_contact_id( $contact_id ) {

		// Init return.
		$addresses = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $addresses;
		}

		// Construct API query.
		$params = [
			'contact_id' => $contact_id,
		];

		// Get Address details via API.
		$result = civicrm_api3( 'Address', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $addresses;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $addresses;
		}

		// Return the result set as an array of objects.
		foreach ( $result['values'] as $item ) {
			$addresses[] = (object) $item;
		}

		return $addresses;

	}

	/**
	 * Get the Primary Address for a Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @return array $address The Address data object, or false if none.
	 */
	public function get_primary_by_contact_id( $contact_id ) {

		// Init return.
		$address = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $address;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'is_primary' => 1,
			'contact_id' => $contact_id,
		];

		// Get Address details via API.
		$result = civicrm_api( 'Address', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $address;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $address;
		}

		// The result set should contain only one item.
		$address = (object) array_pop( $result['values'] );

		return $address;

	}

	/**
	 * Get the Billing Address (as defined by the Location Type) for a Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @return array $address The Address data object, or false if none.
	 */
	public function get_billing_by_contact_id( $contact_id ) {

		// Init return.
		$address = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $address;
		}

		// Get the Location Types.
		$location_types = WPCV_WCI()->helper->get_mapped_location_types();

		// Construct API query.
		$params = [
			'version' => 3,
			'location_type_id' => (int) $location_types['billing'],
			'contact_id' => $contact_id,
		];

		// Get Address details via API.
		$result = civicrm_api( 'Address', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $address;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $address;
		}

		// The result set should contain only one item.
		$address = (object) array_pop( $result['values'] );

		return $address;

	}

	/**
	 * Get the Billing Addresses (as defined by the checkbox) for a Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @return array $address The Address data object, or false if none.
	 */
	public function get_billing_all_by_contact_id( $contact_id ) {

		// Init return.
		$addresses = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $addresses;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'is_billing' => 1,
			'contact_id' => $contact_id,
		];

		// Get Address details via API.
		$result = civicrm_api( 'Address', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $addresses;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $addresses;
		}

		// Return the result set as an array of objects.
		foreach ( $result['values'] as $item ) {
			$addresses[] = (object) $item;
		}

		return $addresses;

	}

	/**
	 * Get CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $location_types The array of CiviCRM Address Location Types.
	 */
	public function get_location_types() {

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

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return $this->location_types;

		}

		// Store values in property.
		if ( ! empty( $result['values'] ) ) {
			$this->location_types = $result['values'];
		}

		return $this->location_types;

	}

	/**
	 * Get the Address Field mappings between WooCommerce and CiviCRM.
	 *
	 * @since 2.0
	 *
	 * @param string $address_type The WooCommerce Address Type. Either 'billing' or 'shipping'.
	 * @return array $mapped_address The Address Field mappings.
	 */
	public function get_field_mappings( $address_type ) {

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

}
