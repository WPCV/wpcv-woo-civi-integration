<?php
/**
 * Contact Phone class.
 *
 * Handles syncing Phone Numbers between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Contact Phone class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Contact_Phone {

	/**
	 * Sync enabled flag.
	 *
	 * @since 3.0
	 * @access public
	 * @var bool $sync_enabled True when Phone Sync is enabled, false otherwise.
	 */
	public $sync_enabled = false;

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
		$option = get_option( 'woocommerce_civicrm_sync_contact_phone', false );
		$this->sync_enabled = WPCV_WCI()->helper->check_yes_no_value( $option );

		// Register Phone-related hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Update CiviCRM Phone record(s) for User/Contact.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'entities_create' ], 30, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'entities_update' ], 30, 2 );

		// Sync WooCommerce and CiviCRM Phone for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Sync WooCommerce and CiviCRM Phone for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_phone' ], 10, 2 );

	}

	/**
	 * Creates CiviCRM Phone record(s) when a Contact has been added.
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
	 * Updates CiviCRM Phone record(s) when a Contact has been edited.
	 *
	 * @since 3.0
	 *
	 * @param array  $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function entities_update( $contact, $order ) {

		// Only use 'billing' because there is no 'shipping_phone' in WooCommerce.
		$location_type = 'billing';
		$location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$location_type_id = $location_types[ $location_type ];

		// Bail if there's no Phone Number in the Order.
		$phone_number = '';
		if ( is_callable( [ $order, "get_{$location_type}_phone" ] ) ) {
			$phone_number = $order->{"get_{$location_type}_phone"}();
		}
		if ( empty( $phone_number ) ) {
			return;
		}

		$contact_id = $contact['id'];

		// Prime the Phone data.
		$phone_params = [
			'phone_type_id' => 1,
			'location_type_id' => $location_type_id,
			'phone' => $phone_number,
			'contact_id' => $contact_id,
		];

		// Get the existing Phone records for this Contact.
		$existing_phones = $this->get_all_by_contact_id( $contact_id );

		// Process Phone.
		$phone_exists = false;
		foreach ( $existing_phones as $existing ) {
			// Does this Phone have the same Location Type?
			if ( isset( $existing['location_type_id'] ) && $existing['location_type_id'] === $location_type_id ) {
				// Let's update that one.
				$phone_params['id'] = $existing['id'];
			}
			// Is this Phone the same as the one from the Order?
			if ( isset( $existing['phone'] ) && $existing['phone'] === $phone_number ) {
				// FIXME: Should we still create a new Phone with the 'Billing' Location Type?
				$phone_exists = true;
			}
		}

		// Skip if no update needed.
		if ( $phone_exists ) {
			return;
		}

		// Create new or update existing Phone record.
		if ( empty( $phone_params['id'] ) ) {
			$phone = $this->create( $phone_params );
		} else {
			$phone = $this->update( $phone_params );
		}

		// Bail if something went wrong.
		if ( empty( $phone ) ) {
			return;
		}

		// Construct note for Order.
		if ( empty( $phone_params['id'] ) ) {
			$note = sprintf(
				/* translators: 1: Location Type, 2: Phone Number */
				__( 'Created new CiviCRM Phone of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
				$location_type,
				$phone['phone']
			);
		} else {
			$note = sprintf(
				/* translators: 1: Location Type, 2: Phone Number */
				__( 'Updated CiviCRM Phone of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
				$location_type,
				$phone['phone']
			);
		}

		// Add note.
		$order->add_order_note( $note );

		// Let's make an array of the data.
		$args = [
			'phone' => $phone,
			'location_type' => $location_type,
			'contact' => $contact,
			'order' => $order,
		];

		/**
		 * Broadcast that a CiviCRM Phone has been updated from WooCommerce data.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/phone/entities_updated', $args );

	}

	/**
	 * Sync a CiviCRM Phone from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Phone is edited.
	 *
	 * TODO: This should probably also remove the "civicrm_post" callback because
	 * it is possible for there to be listeners on the "updated_{$meta_type}_meta"
	 * action in WordPress.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/updated_meta_type_meta/
	 *
	 * @since 2.0
	 * @since 3.0 Renamed.
	 *
	 * @param string  $op The operation being performed.
	 * @param string  $object_name The entity name.
	 * @param integer $object_id The entity id.
	 * @param object  $object_ref The entity object.
	 */
	public function sync_civicrm_to_woo( $op, $object_name, $object_id, $object_ref ) {

		// Bail if Phone Sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return;
		}

		// Bail if not our target Entity.
		if ( 'Phone' !== $object_name ) {
			return;
		}

		// Bail if not our target operation(s).
		if ( 'edit' !== $op ) {
			return;
		}

		// Bail if the Phone being edited is not one of the mapped ones.
		$mapped = WPCV_WCI()->helper->get_mapped_location_types();
		if ( ! in_array( (int) $object_ref->location_type_id, $mapped, true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( empty( $object_ref->contact_id ) ) {
			return;
		}

		// Bail if we don't have a WordPress User.
		$cms_user = WPCV_WCI()->contact->get_ufmatch( $object_ref->contact_id, 'contact_id' );
		if ( ! $cms_user ) {
			return;
		}

		// Only for Billing Phone, there's no Shipping Phone field.
		$phone_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() );
		if ( 'billing' !== $phone_type ) {
			return;
		}

		// Do the update now.
		// TODO: Convert to WooCommerce methods.
		update_user_meta( $cms_user['uf_id'], $phone_type . '_phone', $object_ref->phone );

		// Let's make an array of the data.
		$args = [
			'phone' => $object_ref,
			'phone_type' => $phone_type,
			'user_id' => $cms_user['uf_id'],
			'user' => $cms_user['uf_id'],
		];

		/**
		 * Broadcast that a WooCommerce Phone has been updated for a User.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/phone/civicrm_to_woo/synced', $args );

	}

	/**
	 * Sync a WooCommerce Phone from a User to a CiviCRM Contact.
	 *
	 * Fires when an WooCommerce Phone is edited.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed.
	 *
	 * @param integer $user_id The WordPress User ID.
	 * @param string  $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_woo_to_civicrm( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return false;
		}

		// Bail if Phone is not of type 'billing'.
		if ( 'billing' !== $load_address ) {
			return false;
		}

		// Bail if we don't have a CiviCRM Contact.
		$contact = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );
		if ( ! $contact ) {
			return false;
		}

		// Get the "billing" Location Type ID.
		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$location_type_id = $mapped_location_types[ $load_address ];

		// Try and get the full data for the existing Phone.
		$existing_phone = $this->get_by_contact_id_and_location( $contact['contact_id'], $location_type_id );

		// Get the WooCommerce Customer Phone.
		$customer = new WC_Customer( $user_id );
		$customer_phone = '';
		if ( is_callable( [ $customer, "get_{$load_address}_phone" ] ) ) {
			$customer_phone = $customer->{"get_{$load_address}_phone"}();
		}

		// Build the array for the mapped CiviCRM Phone.
		$phone_params = [
			'phone' => $customer_phone,
		];

		// Prevent reverse sync.
		remove_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10 );

		// Create new Phone or update existing.
		if ( ! empty( $existing_phone ) ) {
			$params = array_merge( $existing_phone, $phone_params );
			$phone = $this->update( $params );
		} else {
			$phone_params['contact_id'] = $contact['id'];
			$phone_params['location_type_id'] = $location_type_id;
			$phone = $this->create( $phone_params );
		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Let's make an array of the data.
		$args = [
			'phone' => $phone,
			'contact' => $contact,
			'address_type' => $load_address,
			'customer' => $customer,
			'user_id' => $user_id,
		];

		/**
		 * Broadcast that a CiviCRM Phone has been updated from WooCommerce data.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/phone/woo_to_civicrm/synced', $args );

		// Success.
		return true;

	}

	/**
	 * Creates a CiviCRM Phone record for a given set of data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean $phone The array of Phone data, or false on failure.
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

		// Call the API.
		$result = civicrm_api3( 'Phone', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// The result set should contain only one item.
		$phone = false;
		if ( ! empty( $result['values'] ) ) {
			$phone = array_pop( $result['values'] );
		}

		return $phone;

	}

	/**
	 * Update a CiviCRM Phone with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect a Phone ID
	 * to have been set in the Phone data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean The array of Phone data from the CiviCRM API, or false on failure.
	 */
	public function update( $params = [] ) {

		// Log and bail if there's no Phone ID.
		if ( empty( $params['id'] ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numeric ID must be present to update a Phone record.', 'wpcv-woo-civi-integration' ),
				'phone' => $phone,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Pass through.
		return $this->create( $params );

	}

	/**
	 * Gets the data for a Phone Record.
	 *
	 * @since 3.0
	 *
	 * @param integer $phone_id The numeric ID of the Phone Record.
	 * @return array $phone The array of Phone Record data, or empty if none.
	 */
	public function get_by_id( $phone_id ) {

		$phone = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $phone;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $phone_id,
		];

		// Get Phone Record details via API.
		$result = civicrm_api( 'Phone', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $phone;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $phone;
		}

		// The result set should contain only one item.
		$phone = array_pop( $result['values'] );

		return $phone;

	}

	/**
	 * Gets a Contact's Phone of a given Location Type.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @param integer $location_type_id The numeric ID of the Location Type.
	 * @return array $phone The array of Phone data, empty otherwise.
	 */
	public function get_by_contact_id_and_location( $contact_id, $location_type_id ) {

		// Init return.
		$phone = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $phone;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
			'location_type_id' => $location_type_id,
		];

		// Get Phone details via API.
		$result = civicrm_api( 'Phone', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $phone;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $phone;
		}

		// The result set should contain only one item.
		$phone = array_pop( $result['values'] );

		return $phone;

	}

	/**
	 * Get the Phone Records for a given Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $phone_data The array of Phone Record data for the CiviCRM Contact.
	 */
	public function get_all_by_contact_id( $contact_id ) {

		$phone_data = [];

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return $phone_data;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $phone_data;
		}

		// Define params to get queried Phone Records.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'contact_id' => $contact_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Phone', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $phone_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $phone_data;
		}

		// The result set it what we want.
		$phone_data = $result['values'];

		return $phone_data;

	}

}
