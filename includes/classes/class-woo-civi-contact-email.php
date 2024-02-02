<?php
/**
 * Contact Email class.
 *
 * Handles syncing Email Addresses between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Contact Email class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Contact_Email {

	/**
	 * Sync enabled flag.
	 *
	 * @since 3.0
	 * @access public
	 * @var bool $sync_enabled True when Email Sync is enabled, false otherwise.
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
		$option = get_option( 'woocommerce_civicrm_sync_contact_email', false );
		$this->sync_enabled = WPCV_WCI()->helper->check_yes_no_value( $option );

		// Register Email-related hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		// Update CiviCRM Email record(s) for User/Contact.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'entities_create' ], 20, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'entities_update' ], 20, 2 );

		// Sync CiviCRM Contact Email to WooCommerce User Email.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Sync WooCommerce User Email to CiviCRM Contact Email.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_woo_to_civicrm' ], 10, 2 );

	}

	/**
	 * Creates Email record(s) when a Contact has been added.
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
	 * Updates Email record(s) when a Contact has been edited.
	 *
	 * @since 3.0
	 *
	 * @param array  $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function entities_update( $contact, $order ) {

		// Only use 'billing' because there is no 'shipping_email' in WooCommerce.
		$location_type = 'billing';
		$location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$location_type_id = (int) $location_types[ $location_type ];

		// Bail if there's no Email in the Order.
		$email_address = '';
		if ( is_callable( [ $order, "get_{$location_type}_email" ] ) ) {
			$email_address = $order->{"get_{$location_type}_email"}();
		}
		if ( empty( $email_address ) ) {
			return;
		}

		$contact_id = $contact['id'];

		// Prime the Email data.
		$email_params = [
			'location_type_id' => $location_type_id,
			'email' => $email_address,
			'contact_id' => $contact_id,
		];

		// Get the existing Email records for this Contact.
		$existing_emails = $this->get_all_by_contact_id( $contact_id );

		// Try and find an existing CiviCRM Email record.
		foreach ( $existing_emails as $existing ) {
			// Does this Email have the same Location Type?
			if ( isset( $existing['location_type_id'] ) && (int) $existing['location_type_id'] === $location_type_id ) {
				// Let's update that one.
				$email_params['id'] = $existing['id'];
				// Although no need if it hasn't changed.
				if ( isset( $existing['email'] ) && $existing['email'] === $email_address ) {
					return;
				}
			}

		}

		// If we haven't found one of the matching Location Type.
		if ( empty( $email_params['id'] ) ) {
			// Look for a Email record that's the same as the one from the Order.
			foreach ( $existing_emails as $existing ) {
				if ( isset( $existing['email'] ) && $existing['email'] === $email_address ) {
					// Skip creating a new Email record since we already have it.
					return;
				}
			}
		}

		// Create new or update existing Email record.
		if ( empty( $email_params['id'] ) ) {
			$email = $this->create( $email_params );
		} else {
			$email = $this->update( $email_params );
		}

		// Bail if something went wrong.
		if ( empty( $email ) ) {
			return;
		}

		// Construct note for Order.
		if ( empty( $email_params['id'] ) ) {
			$note = sprintf(
				/* translators: 1: Location Type, 2: Email */
				__( 'Created new CiviCRM Email of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
				$location_type,
				$email['email']
			);
		} else {
			$note = sprintf(
				/* translators: 1: Location Type, 2: Email */
				__( 'Updated CiviCRM Email of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
				$location_type,
				$email['email']
			);
		}

		// Add note.
		$order->add_order_note( $note );

		// Let's make an array of the data.
		$args = [
			'email' => $email,
			'location_type' => $location_type,
			'contact' => $contact,
			'order' => $order,
		];

		/**
		 * Broadcast that a CiviCRM Email has been updated from WooCommerce data.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/email/entities_updated', $args );

	}

	/**
	 * Sync a CiviCRM Email from a Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Email is edited.
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

		// Bail if Email Sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return;
		}

		// Bail if not our target Entity.
		if ( 'Email' !== $object_name ) {
			return;
		}

		// Bail if not our target operation(s).
		if ( 'create' !== $op && 'edit' !== $op ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( empty( $object_ref->contact_id ) ) {
			return;
		}

		// Bail if the Email being edited is not one of the mapped ones.
		$mapped = WPCV_WCI()->helper->get_mapped_location_types();
		if ( ! in_array( (int) $object_ref->location_type_id, $mapped, true ) ) {
			return;
		}

		// Bail if the Contact doesn't have the synced Contact Type.
		if ( ! WPCV_WCI()->contact->type_is_synced( (int) $object_ref->contact_id ) ) {
			return;
		}

		// Bail if we don't have a WordPress User match.
		$ufmatch = WPCV_WCI()->contact->get_ufmatch( (int) $object_ref->contact_id, 'contact_id' );
		if ( ! $ufmatch ) {
			return;
		}

		/*
		 * Only sync billing Email because there is no shipping Email field in
		 * WooCommerce and CiviCRM itself (or CiviCRM Profile Sync if present)
		 * handles syncing the Contact Primary Email to WordPress User Email.
		 */
		$email_type = array_search( (int) $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() );
		if ( 'billing' !== $email_type ) {
			return;
		}

		/**
		 * Fires before syncing a CiviCRM Email from a CiviCRM Contact to a WordPress User.
		 *
		 * This allows plugins to unhook their callbacks which might interfere with
		 * this syncing procedure. Callbacks can be rehooked with the corresponding
		 * `wpcv_woo_civi/contact/email/sync_civicrm_to_woo/post` action.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/contact/email/sync_civicrm_to_woo/pre' );

		// Set the WooCommerce Customer Email.
		$customer = new WC_Customer( $ufmatch['uf_id'] );
		if ( is_callable( [ $customer, "set_{$email_type}_email" ] ) ) {
			$customer->{"set_{$email_type}_email"}( $object_ref->email );
			$customer->save();
		}

		/**
		 * Fires after syncing a CiviCRM Email from a CiviCRM Contact to a WordPress User.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/contact/email/sync_civicrm_to_woo/post' );

		// Let's make an array of the data.
		$args = [
			'op' => $op,
			'object_name' => $object_name,
			'object_id' => $object_id,
			'object_ref' => $object_ref,
			'email_type' => $email_type,
			'customer' => $customer,
			'user_id' => $ufmatch['uf_id'],
		];

		/**
		 * Broadcast that a WooCommerce Email has been updated for a User.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/email/civicrm_to_woo/synced', $args );

	}

	/**
	 * Sync a WooCommerce Email from a User to a CiviCRM Contact.
	 *
	 * Fires when WooCommerce Email is edited.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed.
	 *
	 * @param integer $user_id The WordPress User ID.
	 * @param string  $address_type The Address Type. Either 'shipping' or 'billing'.
	 */
	public function sync_woo_to_civicrm( $user_id, $address_type ) {

		// Bail if sync is not enabled.
		if ( ! $this->sync_enabled ) {
			return;
		}

		// Bail if Email is not of type 'billing'.
		if ( 'billing' !== $address_type ) {
			return;
		}

		// Bail if we don't have a CiviCRM Contact.
		$ufmatch = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );
		if ( ! $ufmatch ) {
			return;
		}

		// Try and find the Contact.
		$contact = WPCV_WCI()->contact->get_by_id( $ufmatch['contact_id'] );
		if ( $contact === false ) {
			return;
		}

		// Add the synced Contact Type if the Contact doesn't have it.
		if ( ! WPCV_WCI()->contact->type_is_synced( $contact ) ) {
			$contact = WPCV_WCI()->contact->subtype_add_to_contact( $contact );
			WPCV_WCI()->contact->update( $contact );
		}

		// Get the "billing" Location Type ID.
		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$location_type_id = $mapped_location_types[ $address_type ];

		// Try and get the full data for the existing Email.
		$existing_email = $this->get_by_contact_id_and_location( $ufmatch['contact_id'], $location_type_id );

		// Get the WooCommerce Customer Email.
		$customer = new WC_Customer( $user_id );
		$customer_email = '';
		if ( is_callable( [ $customer, "get_{$address_type}_email" ] ) ) {
			$customer_email = $customer->{"get_{$address_type}_email"}();
		}

		// Build the array for the mapped CiviCRM Email.
		$email_params = [
			'email' => $customer_email,
		];

		// Prevent reverse sync.
		remove_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10 );

		// Create new Email or update existing.
		if ( ! empty( $existing_email ) ) {
			$params = array_merge( $existing_email, $email_params );
			$email = $this->update( $params );
		} else {
			$email_params['contact_id'] = $ufmatch['contact_id'];
			$email_params['location_type_id'] = $location_type_id;
			$email = $this->create( $email_params );
		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civicrm_to_woo' ], 10, 4 );

		// Let's make an array of the data.
		$args = [
			'user_id' => $user_id,
			'address_type' => $address_type,
			'customer' => $customer,
			'contact' => $ufmatch,
			'email' => $email,
		];

		/**
		 * Broadcast that a CiviCRM Email has been updated from WooCommerce data.
		 *
		 * @since 3.0
		 *
		 * @param array $args The array of data.
		 */
		do_action( 'wpcv_woo_civi/email/woo_to_civicrm/synced', $args );

	}

	/**
	 * Creates a CiviCRM Email record for a given set of data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean $email The array of Email data, or false on failure.
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
		$result = civicrm_api( 'Email', 'create', $params );

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
		$email = false;
		if ( ! empty( $result['values'] ) ) {
			$email = array_pop( $result['values'] );
		}

		return $email;

	}

	/**
	 * Update a CiviCRM Email with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect an Email ID
	 * to have been set in the Email data.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of params to pass to the CiviCRM API.
	 * @return array|boolean The array of Email data from the CiviCRM API, or false on failure.
	 */
	public function update( $params = [] ) {

		// Log and bail if there's no Email ID.
		if ( empty( $params['id'] ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numeric ID must be present to update an Email record.', 'wpcv-woo-civi-integration' ),
				'email' => $email,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Pass through.
		return $this->create( $params );

	}

	/**
	 * Tries to get the Email of the current User.
	 *
	 * @since 3.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return string|bool $email The Email of the current User, or false if not found.
	 */
	public function get_by_order( $order ) {

		$email = false;

		// If User is logged in but not in WordPress admin, i.e. it's not a manual Order.
		if ( is_user_logged_in() && ! is_admin() ) {
			$current_user = wp_get_current_user();
			$email = $current_user->user_email;
			return $email;
		}

		// If there was a "Customer User" field in form, i.e. it's a manual Order.
		$customer_id = filter_input( INPUT_POST, 'customer_user', FILTER_VALIDATE_INT );
		if ( ! empty( $customer_id ) && is_numeric( $customer_id ) ) {
			$user_info = get_userdata( (int) $customer_id );
			$email = $user_info->user_email;
			return $email;
		}

		// Fall back to the Billing Email in the Order if there is one.
		$order_email = $order->get_billing_email();
		if ( ! empty( $order_email ) ) {
			$email = $order_email;
		}

		return $email;

	}

	/**
	 * Gets a Contact's Email of a given Location Type.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the Contact.
	 * @param integer $location_type_id The numeric ID of the Location Type.
	 * @return array $email The array of Email data, empty otherwise.
	 */
	public function get_by_contact_id_and_location( $contact_id, $location_type_id ) {

		// Init return.
		$email = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $email;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
			'location_type_id' => $location_type_id,
		];

		// Get Email details via API.
		$result = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $email;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $email;
		}

		// The result set should contain only one item.
		$email = array_pop( $result['values'] );

		return $email;

	}

	/**
	 * Get the Emails for a given Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $email_data The array of Email data for the CiviCRM Contact.
	 */
	public function get_all_by_contact_id( $contact_id ) {

		$email_data = [];

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return $email_data;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $email_data;
		}

		// Define params to get queried Emails.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'contact_id' => $contact_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $email_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $email_data;
		}

		// The result set it what we want.
		$email_data = $result['values'];

		return $email_data;

	}

}
