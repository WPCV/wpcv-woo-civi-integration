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
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Sync WooCommerce and CiviCRM Phone for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10, 4 );

		// Sync WooCommerce and CiviCRM Phone for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_phone' ], 10, 2 );

		// Update CiviCRM Phone record(s) for User/Contact.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'entities_create' ], 30, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'entities_update' ], 30, 2 );

	}

	/**
	 * Creates Phone record(s) when a Contact has been added.
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
	 * Updates Phone record(s) when a Contact has been edited.
	 *
	 * @since 3.0
	 *
	 * @param array  $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function entities_update( $contact, $order ) {

		$contact_id = $contact['id'];
		$existing_phones = $this->phones_get_by_contact_id( $contact_id );

		try {

			// Only use 'billing' because there is no 'shipping_phone' in WooCommerce.
			$location_types = WPCV_WCI()->helper->get_mapped_location_types();
			$location_type = 'billing';
			$location_type_id = $location_types['billing'];

			// Process Phone.
			$phone_exists = false;

			$phone_number = $order->{'get_' . $location_type . '_phone'}();
			if ( ! empty( $phone_number ) ) {

				// Prime the Phone data.
				$phone = [
					'phone_type_id' => 1,
					'location_type_id' => $location_type_id,
					'phone' => $phone_number,
					'contact_id' => $contact_id,
				];

				foreach ( $existing_phones as $existing ) {
					// Does this Phone have the same Location Type?
					if ( isset( $existing['location_type_id'] ) && $existing['location_type_id'] === $location_type_id ) {
						// Let's update that one.
						$phone['id'] = $existing['id'];
					}
					// Is this Phone the same as the one from the Order?
					if ( $existing['phone'] === $phone['phone'] ) {
						$phone_exists = true;
					}
				}

				if ( ! $phone_exists ) {

					civicrm_api3( 'Phone', 'create', $phone );

					$note = sprintf(
						/* translators: 1: Location Type, 2: Phone Number */
						__( 'Created new CiviCRM Phone of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
						$location_type,
						$phone['phone']
					);

					$order->add_order_note( $note );

				}

			}

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to add/update Phone', 'wpcv-woo-civi-integration' ) );
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
	 *
	 * @param string  $op The operation being performed.
	 * @param string  $object_name The entity name.
	 * @param integer $object_id The entity id.
	 * @param object  $object_ref The entity object.
	 */
	public function sync_civi_contact_phone( $op, $object_name, $object_id, $object_ref ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Phone' !== $object_name ) {
			return;
		}

		// Bail if the Phone being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types(), true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( ! isset( $object_ref->contact_id ) ) {
			return;
		}

		$cms_user = WPCV_WCI()->contact->get_ufmatch( $object_ref->contact_id, 'contact_id' );

		// Bail if we don't have a WordPress User.
		if ( ! $cms_user ) {
			return;
		}

		// Proceed.
		$phone_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() );

		// Only Billing Phone, there's no Shipping Phone field.
		if ( 'billing' === $phone_type ) {
			update_user_meta( $cms_user['uf_id'], $phone_type . '_phone', $object_ref->phone );
		}

		/**
		 * Broadcast that a WooCommerce Phone has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param integer $user_id The WordPress User ID.
		 * @param string  $phone_type The WooCommerce Phone Type. Either 'billing' or 'shipping'.
		 */
		do_action( 'wpcv_woo_civi/wc_phone/updated', $cms_user['uf_id'], $phone_type );

	}

	/**
	 * Sync a WooCommerce Phone from a User to a CiviCRM Contact.
	 *
	 * Fires when an WooCommerce Phone is edited.
	 *
	 * @since 2.0
	 *
	 * @param integer $user_id The WordPress User ID.
	 * @param string  $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_wp_user_woocommerce_phone( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return false;
		}

		// Bail if Phone is not of type 'billing'.
		if ( 'billing' !== $load_address ) {
			return false;
		}

		$contact = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$civi_phone_location_type = $mapped_location_types[ $load_address ];

		$customer = new WC_Customer( $user_id );

		$edited_phone = [
			'phone' => $customer->{'get_' . $load_address . '_phone'}(),
		];

		try {

			$params = [
				'contact_id' => $contact['contact_id'],
				'location_type_id' => $civi_phone_location_type,
			];

			$civi_phone = civicrm_api3( 'Phone', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Phone', 'wpcv-woo-civi-integration' ) );
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
		remove_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10 );

		try {

			if ( isset( $civi_phone ) && empty( $civi_phone['is_error'] ) ) {
				$new_params = array_merge( $civi_phone, $edited_phone );
			} else {
				$new_params = array_merge( $params, $edited_phone );
			}

			$create_phone = civicrm_api3( 'Phone', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Phone', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'new_params' => $new_params,
				'backtrace' => $trace,
			], true ) );

			add_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10, 4 );
			return false;

		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10, 4 );

		/**
		 * Broadcast that a CiviCRM Phone has been updated.
		 *
		 * @since 2.0
		 *
		 * @param integer $contact_id The CiviCRM Contact ID.
		 * @param array $phone The CiviCRM Phone that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_phone/updated', $contact['contact_id'], $create_phone );

		// Success.
		return true;

	}

	/**
	 * Get the data for a Phone Record.
	 *
	 * @since 3.0
	 *
	 * @param integer $phone_id The numeric ID of the Phone Record.
	 * @return array $phone The array of Phone Record data, or empty if none.
	 */
	public function phone_get_by_id( $phone_id ) {

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
	 * Get the Phone Records for a given Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $phone_data The array of Phone Record data for the CiviCRM Contact.
	 */
	public function phones_get_by_contact_id( $contact_id ) {

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
