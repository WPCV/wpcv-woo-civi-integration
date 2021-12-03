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

		// Sync WooCommerce and CiviCRM email for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_email' ], 10, 4 );

		// Sync WooCommerce and CiviCRM email for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_email' ], 10, 2 );

		// Update CiviCRM Email record(s) for User/Contact.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'entities_create' ], 20, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'entities_update' ], 20, 2 );

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

		$contact_id = $contact['id'];
		$existing_emails = $this->emails_get_by_contact_id( $contact_id );

		try {

			// Only use 'billing' because there is no 'shipping_email' in WooCommerce.
			$location_types = WPCV_WCI()->helper->get_mapped_location_types();
			$location_type = 'billing';
			$location_type_id = $location_types['billing'];

			// Process Email.
			$email_exists = false;

			$email_address = $order->{'get_' . $location_type . '_email'}();
			if ( ! empty( $email_address ) ) {

				// Prime the Email data.
				$email = [
					'location_type_id' => $location_type_id,
					'email' => $email_address,
					'contact_id' => $contact_id,
				];

				foreach ( $existing_emails as $existing ) {
					// Does this Email have the same Location Type?
					if ( isset( $existing['location_type_id'] ) && $existing['location_type_id'] === $location_type_id ) {
						// Let's update that one.
						$email['id'] = $existing['id'];
					}
					// Is this Email the same as the one from the Order?
					if ( isset( $existing['email'] ) && $existing['email'] === $email['email'] ) {
						// FIXME: Should we still create a new Email with the 'Billing' Location Type?
						$email_exists = true;
					}
				}

				if ( ! $email_exists ) {

					civicrm_api3( 'Email', 'create', $email );

					$note = sprintf(
						/* translators: 1: Location Type, 2: Email Address */
						__( 'Created new CiviCRM Email of type %1$s: %2$s', 'wpcv-woo-civi-integration' ),
						$location_type,
						$email['email']
					);

					$order->add_order_note( $note );

				}

			}

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to add/update Email', 'wpcv-woo-civi-integration' ) );
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
	 * Sync a CiviCRM Email from a Contact to a WordPress User.
	 *
	 * Fires when a Civi Contact's Email is edited.
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
	public function sync_civi_contact_email( $op, $object_name, $object_id, $object_ref ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Email' !== $object_name ) {
			return;
		}

		// Bail if the Email being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() ) ) {
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
		$email_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() );

		// Only for billing Email, there's no shipping Email field.
		if ( 'billing' === $email_type ) {
			update_user_meta( $cms_user['uf_id'], $email_type . '_email', $object_ref->email );
		}

		/**
		 * Broadcast that a WooCommerce Email has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param integer $user_id The WordPress User ID.
		 * @param string $email_type The WooCommerce Email Type. Either 'billing' or 'shipping'.
		 */
		do_action( 'wpcv_woo_civi/wc_email/updated', $cms_user['uf_id'], $email_type );

	}

	/**
	 * Sync a WooCommerce Email from a User to a CiviCRM Contact.
	 *
	 * Fires when WooCommerce Email is edited.
	 *
	 * @since 2.0
	 *
	 * @param integer $user_id The WordPress User ID.
	 * @param string  $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_wp_user_woocommerce_email( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) {
			return false;
		}

		// Bail if Email is not of type 'billing'.
		if ( 'billing' !== $load_address ) {
			return false;
		}

		$contact = WPCV_WCI()->contact->get_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$civi_email_location_type = $mapped_location_types[ $load_address ];

		$customer = new WC_Customer( $user_id );

		$edited_email = [
			'email' => $customer->{'get_' . $load_address . '_email'}(),
		];

		try {

			$params = [
				'contact_id' => $contact['contact_id'],
				'location_type_id' => $civi_email_location_type,
			];

			$civi_email = civicrm_api3( 'Email', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Email', 'wpcv-woo-civi-integration' ) );
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
		remove_action( 'civicrm_post', [ $this, 'sync_civi_contact_email' ], 10 );

		try {

			if ( isset( $civi_email ) && empty( $civi_email['is_error'] ) ) {
				$new_params = array_merge( $civi_email, $edited_email );
			} else {
				$new_params = array_merge( $params, $edited_email );
			}

			$create_email = civicrm_api3( 'Email', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Email', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'new_params' => $new_params,
				'backtrace' => $trace,
			], true ) );

			add_action( 'civicrm_post', [ $this, 'sync_civi_contact_email' ], 10, 4 );
			return false;

		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_email' ], 10, 4 );

		/**
		 * Broadcast that a CiviCRM Email has been updated.
		 *
		 * @since 2.0
		 *
		 * @param integer $contact_id The CiviCRM Contact ID.
		 * @param array $email The CiviCRM Email that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_email/updated', $contact['contact_id'], $create_email );

		// Success.
		return true;

	}

	/**
	 * Tries to get the Email of the current User.
	 *
	 * @since 3.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return str|bool $email The Email of the current User, or false if not found.
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
	 * Get the Emails for a given Contact ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $email_data The array of Email data for the CiviCRM Contact.
	 */
	public function emails_get_by_contact_id( $contact_id ) {

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
