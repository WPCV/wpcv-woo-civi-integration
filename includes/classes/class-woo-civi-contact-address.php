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
		$option             = get_option( 'woocommerce_civicrm_sync_contact_address', false );
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

		// Sync CiviCRM Contact Address to WooCommerce User Address.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Sync WooCommerce User Address to CiviCRM Contact Address.
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

		// Get the existing Address records for this Contact.
		$existing_addresses = $this->get_all_by_contact_id( $contact_id );

		// Process Addresses.
		$address_types = WPCV_WCI()->helper->get_mapped_location_types();
		foreach ( $address_types as $address_type => $location_type_id ) {

			// Skip if we don't have both Address_1 and Postcode.
			$address_1 = '';
			if ( is_callable( [ $order, "get_{$address_type}_address_1" ] ) ) {
				$address_1 = $order->{"get_{$address_type}_address_1"}();
			}
			$postcode = '';
			if ( is_callable( [ $order, "get_{$address_type}_postcode" ] ) ) {
				$postcode = $order->{"get_{$address_type}_postcode"}();
			}
			if ( empty( $address_1 ) || empty( $postcode ) ) {
				continue;
			}

			$country_id = null;
			if ( is_callable( [ $order, "get_{$address_type}_country" ] ) ) {
				$country    = $order->{"get_{$address_type}_country"}();
				$country_id = WPCV_WCI()->settings_states->get_civicrm_country_id( $country );
			}

			$state = null;
			if ( is_callable( [ $order, "get_{$address_type}_state" ] ) ) {
				$state = $order->{"get_{$address_type}_state"}();
			}

			$state_province_id = null;
			if ( ! empty( $state ) && ! empty( $country_id ) ) {
				$state_province_id = WPCV_WCI()->settings_states->get_civicrm_state_province_id( $state, $country_id );
			}

			$city = '';
			if ( is_callable( [ $order, "get_{$address_type}_city" ] ) ) {
				$city = $order->{"get_{$address_type}_city"}();
			}

			$company = '';
			if ( is_callable( [ $order, "get_{$address_type}_company" ] ) ) {
				$company = $order->{"get_{$address_type}_company"}();
			}

			$address_2 = '';
			if ( is_callable( [ $order, "get_{$address_type}_address_2" ] ) ) {
				$address_2 = $order->{"get_{$address_type}_address_2"}();
			}

			// Prime the Address data array.
			$address_params = [
				'location_type_id'       => $location_type_id,
				'city'                   => $city,
				'postal_code'            => $postcode,
				'name'                   => $company,
				'street_address'         => $address_1,
				'supplemental_address_1' => $address_2,
				'country'                => $country_id,
				'state_province_id'      => $state_province_id,
				'contact_id'             => $contact_id,
			];

			// Try and find an existing CiviCRM Address record.
			foreach ( $existing_addresses as $existing ) {
				// Does this Address have the desired Location Type?
				if ( isset( $existing->location_type_id ) && (int) $existing->location_type_id === $location_type_id ) {
					// Let's update that one.
					$address_params['id'] = (int) $existing->id;
					// Skip if no update needed.
					if ( $this->is_match( $existing, $address_params ) ) {
						continue 2;
					}
				}
			}

			// If we haven't found one of the matching Location Type.
			if ( empty( $address_params['id'] ) ) {
				// Look for an Address that's the same as the one from the Order.
				foreach ( $existing_addresses as $existing ) {
					if ( $this->is_match( $existing, $address_params ) ) {
						// Skip creating a new Address record since we already have it.
						continue 2;
					}
				}
			}

			// Create new or update existing Address record.
			if ( empty( $address_params['id'] ) ) {
				$address = $this->create( $address_params );
			} else {
				$address = $this->update( $address_params );
			}

			// Skip if something went wrong.
			if ( empty( $address ) ) {
				continue;
			}

			// Construct note for Order.
			if ( empty( $address_params['id'] ) ) {
				$note = sprintf(
					/* translators: 1: Address Type, 2: Street Address */
					__( 'Created new CiviCRM Address of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
					$address_type,
					$address['street_address']
				);
			} else {
				$note = sprintf(
					/* translators: 1: Address Type, 2: Street Address */
					__( 'Updated CiviCRM Address of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
					$address_type,
					$address['street_address']
				);
			}

			// Add note.
			$order->add_order_note( $note );

		}

	}

	/**
	 * Checks if two CiviCRM Addresses are the same.
	 *
	 * @since 3.0
	 *
	 * @param array|object $one The first Address.
	 * @param array|object $two The second Address.
	 * @return bool True if the Addresses match, false otherwise.
	 */
	public function is_match( $one, $two ) {

		// Cast params as objects for comparison.
		if ( ! is_object( $one ) ) {
			$one = (object) $one;
		}
		if ( ! is_object( $two ) ) {
			$two = (object) $two;
		}

		// Backfill before comparing.
		$one->street_address = empty( $one->street_address ) ? '' : $one->street_address;
		$two->street_address = empty( $two->street_address ) ? '' : $two->street_address;
		if ( $one->street_address !== $two->street_address ) {
			return false;
		}

		$one->postal_code = empty( $one->postal_code ) ? '' : $one->postal_code;
		$two->postal_code = empty( $two->postal_code ) ? '' : $two->postal_code;
		if ( $one->postal_code !== $two->postal_code ) {
			return false;
		}

		$one->city = empty( $one->city ) ? '' : $one->city;
		$two->city = empty( $two->city ) ? '' : $two->city;
		if ( $one->city !== $two->city ) {
			return false;
		}

		$one->supplemental_address_1 = empty( $one->supplemental_address_1 ) ? '' : $one->supplemental_address_1;
		$two->supplemental_address_1 = empty( $two->supplemental_address_1 ) ? '' : $two->supplemental_address_1;
		if ( $one->supplemental_address_1 !== $two->supplemental_address_1 ) {
			return false;
		}

		// TODO: Maybe add more Address fields.

		return true;

	}

	/**
	 * Sync CiviCRM Address from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Address is edited.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed.
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
		if ( false === $contact ) {
			return;
		}

		// Bail if the Contact doesn't have the synced Contact Type.
		if ( ! WPCV_WCI()->contact->type_is_synced( $contact ) ) {
			return;
		}

		// Bail if we don't have a WordPress User match.
		$ufmatch = WPCV_WCI()->contact->get_ufmatch( $contact['id'], 'contact_id' );
		if ( empty( $ufmatch ) ) {
			return;
		}

		// Use the Customer object.
		$customer = new WC_Customer( $ufmatch['uf_id'] );

		// Unhook to prevent any possibility of recursion.
		remove_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ] );

		// Also unhook CiviCRM's callbacks.
		remove_action( 'user_register', [ civi_wp()->users, 'update_user' ] );
		remove_action( 'profile_update', [ civi_wp()->users, 'update_user' ] );

		/**
		 * Fires before syncing a CiviCRM Address from a CiviCRM Contact to a WordPress User.
		 *
		 * This allows plugins to unhook their callbacks which might interfere with
		 * this syncing procedure. Callbacks can be rehooked with the corresponding
		 * `wpcv_woo_civi/contact/address/sync_civicrm_to_woo/post` action.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/contact/address/sync_civicrm_to_woo/pre' );

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
			if ( 'country_id' === $civi_field ) {
				$value = WPCV_WCI()->settings_states->get_civicrm_country_iso_code( $value );
			} elseif ( 'state_province_id' === $civi_field ) {
				$value = WPCV_WCI()->settings_states->get_civicrm_state_province_name( $value );
			}

			// Update the Customer Field.
			if ( is_callable( [ $customer, "set_{$wc_field}" ] ) ) {
				$customer->{"set_{$wc_field}"}( $value );
			}

		}

		// Additionally update First Name and Last Name if not set.
		$first_name = '';
		if ( is_callable( [ $customer, "get_{$address_type}_first_name" ] ) ) {
			$first_name = $customer->{"get_{$address_type}_first_name"}();
		}
		if ( ! empty( $contact['first_name'] ) && empty( $first_name ) ) {
			if ( is_callable( [ $customer, "set_{$address_type}_first_name" ] ) ) {
				$customer->{"set_{$address_type}_first_name"}( $contact['first_name'] );
			}
		}

		$last_name = '';
		if ( is_callable( [ $customer, "get_{$address_type}_last_name" ] ) ) {
			$last_name = $customer->{"get_{$address_type}_last_name"}();
		}
		if ( ! empty( $contact['last_name'] ) && empty( $last_name ) ) {
			if ( is_callable( [ $customer, "set_{$address_type}_last_name" ] ) ) {
				$customer->{"set_{$address_type}_last_name"}( $contact['last_name'] );
			}
		}

		// Lastly, save the Customer.
		$customer->save();

		// Rehook callback for WooCommerce action.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ], 10, 2 );

		// Rehook CiviCRM's callbacks.
		add_action( 'user_register', [ civi_wp()->users, 'update_user' ] );
		add_action( 'profile_update', [ civi_wp()->users, 'update_user' ] );

		/**
		 * Fires after syncing a CiviCRM Address from a CiviCRM Contact to a WordPress User.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/contact/address/sync_civicrm_to_woo/post' );

		// Let's make an array of the data.
		$args = [
			'op'           => $op,
			'object_name'  => $object_name,
			'object_id'    => $object_id,
			'object_ref'   => $object_ref,
			'address_type' => $address_type,
			'customer'     => $customer,
			'user_id'      => $ufmatch['uf_id'],
		];

		/**
		 * Broadcast that a WooCommerce Address has been updated from CiviCRM data.
		 *
		 * @since 3.0
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
	 * @param string  $address_type The Address Type. Either 'shipping' or 'billing'.
	 */
	public function sync_woo_to_civicrm( $user_id, $address_type ) {

		// Bail if Address Sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return;
		}

		// Bail if we don't have a CiviCRM UFMatch record.
		$ufmatch = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );
		if ( empty( $ufmatch ) ) {
			return;
		}

		// Try and find the Contact.
		$contact = WPCV_WCI()->contact->get_by_id( $ufmatch['contact_id'] );
		if ( false === $contact ) {
			return;
		}

		// Add the synced Contact Type if the Contact doesn't have it.
		if ( ! WPCV_WCI()->contact->type_is_synced( $contact ) ) {
			$contact = WPCV_WCI()->contact->subtype_add_to_contact( $contact );
			WPCV_WCI()->contact->update( $contact );
		}

		// Use the Customer object.
		$customer = new WC_Customer( $user_id );

		// Build the array for the mapped CiviCRM Address.
		$address_params = [];
		foreach ( $this->get_field_mappings( $address_type ) as $wc_field => $civi_field ) {

			// Assign the value.
			$value = '';
			if ( is_callable( [ $customer, "get_{$wc_field}" ] ) ) {
				$value = $customer->{"get_{$wc_field}"}();
			}

			// Override for special fields.
			if ( 'country_id' === $civi_field ) {
				$value = WPCV_WCI()->settings_states->get_civicrm_country_id( $value );
			} elseif ( 'state_province_id' === $civi_field ) {
				// This relies on the order of the Field mappings to work.
				$value = WPCV_WCI()->settings_states->get_civicrm_state_province_id( $value, $address_params['country_id'] );
			}

			// Add to CiviCRM Address array.
			$address_params[ $civi_field ] = $value;

		}

		// Get the Location Type of the edited Woo Address.
		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$location_type_id      = $mapped_location_types[ $address_type ];

		// Get the matching Address from CiviCRM.
		$existing_address = $this->get_by_contact_id_and_location( $contact['id'], $location_type_id );

		// Prevent reverse sync.
		remove_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10 );

		// Create new Address or update existing.
		if ( ! empty( $existing_address ) ) {
			$params  = array_merge( $existing_address, $address_params );
			$address = $this->update( $params );
		} else {
			$address_params['contact_id']       = $contact['id'];
			$address_params['location_type_id'] = $location_type_id;
			$address                            = $this->create( $address_params );
		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Let's make an array of the data.
		$args = [
			'user_id'      => $user_id,
			'address_type' => $address_type,
			'customer'     => $customer,
			'contact'      => $contact,
			'address'      => $address,
		];

		/**
		 * Broadcast that a CiviCRM Address has been updated from WooCommerce data.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/address/woo_to_civicrm/synced', $args );

	}

	/**
	 * Create a CiviCRM Address for a given set of data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean $address The array of Address data, or false on failure.
	 */
	public function create( $params = [] ) {

		// Bail if there's no data.
		if ( empty( $params ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Add API version.
		$params['version'] = 3;

		// Call the API.
		$result = civicrm_api( 'Address', 'create', $params );

		// Log and bail if something went wrong.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return false;
		}

		// The result set should contain only one item.
		$address = false;
		if ( ! empty( $result['values'] ) ) {
			$address = array_pop( $result['values'] );
		}

		return $address;

	}

	/**
	 * Update a CiviCRM Address with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect an Address ID
	 * to have been set in the Address data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean The array of Address data from the CiviCRM API, or false on failure.
	 */
	public function update( $params = [] ) {

		// Log and bail if there's no Address ID.
		if ( empty( $params['id'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'A numeric ID must be present to update an Address.', 'wpcv-woo-civi-integration' ),
				'address'   => $address,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return false;
		}

		// Pass through.
		return $this->create( $params );

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
			'version'    => 3,
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
	 * Gets a Contact's Address of a given Location Type.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @param integer $location_type_id The numeric ID of the Location Type.
	 * @return array $address The array of Address data, empty otherwise.
	 */
	public function get_by_contact_id_and_location( $contact_id, $location_type_id ) {

		// Init return.
		$address = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $address;
		}

		// Construct API query.
		$params = [
			'version'          => 3,
			'contact_id'       => $contact_id,
			'location_type_id' => $location_type_id,
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
		$address = array_pop( $result['values'] );

		return $address;

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
			'version'    => 3,
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
			'version'          => 3,
			'location_type_id' => (int) $location_types['billing'],
			'contact_id'       => $contact_id,
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
			'version'    => 3,
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
			'version' => 3,
			'field'   => 'location_type_id',
			'options' => [
				'limit' => 0,
			],
		];

		$result = civicrm_api( 'Address', 'getoptions', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {

			// Write details to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

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
			$address_type . '_city'      => 'city',
			$address_type . '_postcode'  => 'postal_code',
			$address_type . '_country'   => 'country_id',
			$address_type . '_state'     => 'state_province_id',
			$address_type . '_company'   => 'name',
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
