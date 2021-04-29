<?php
/**
 * Contact class.
 *
 * Handles Contact-related functionality.
 * Loads the classes which handle syncing data between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Contact class.
 *
 * @since 2.1
 */
class WPCV_Woo_Civi_Contact {

	/**
	 * The Address sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $address The Address sync object.
	 */
	public $address;

	/**
	 * The Email sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $email The Email sync object.
	 */
	public $email;

	/**
	 * The Phone sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $phone The Phone sync object.
	 */
	public $phone;

	/**
	 * The Orders Contact Tab management object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $orders_tab The Orders Tab management object.
	 */
	public $orders_tab;

	/**
	 * Class constructor.
	 *
	 * @since 2.1
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

		$this->include_files();
		$this->setup_objects();

		/**
		 * Broadcast that this class is loaded.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/contact/loaded' );

	}

	/**
	 * Include sync files.
	 *
	 * @since 2.1
	 */
	public function include_files() {

		// Include Address class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-contact-address.php';
		// Include Phone class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-contact-phone.php';
		// Include Email class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-contact-email.php';

		// Include Contact Orders Tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-contact-orders-tab.php';

	}

	/**
	 * Setup sync objects.
	 *
	 * @since 2.1
	 */
	public function setup_objects() {

		// Init Address object.
		$this->address = new WPCV_Woo_Civi_Contact_Address();
		// Init Phone object.
		$this->phone = new WPCV_Woo_Civi_Contact_Phone();
		// Init Email object.
		$this->email = new WPCV_Woo_Civi_Contact_Email();

		// Init Orders Tab object.
		$this->orders_tab = new WPCV_Woo_Civi_Contact_Orders_Tab();

	}

	/**
	 * Get a CiviCRM Contact ID for a given WooCommerce Order.
	 *
	 * @since 2.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return int|bool $contact_id The numeric ID of the CiviCRM Contact if found.
	 *                              Returns 0 if a Contact needs to be created, or false on failure.
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
		// FIXME: Why not? The wrong Contact ID can returned on the Edit Order screen when there's a duplicate Email.
		// This happens when the Default Org has the same Email as a Contact.
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

				// Write to CiviCRM log.
				CRM_Core_Error::debug_log_message( __( 'Failed to get a Contact from UFMatch table', 'wpcv-woo-civi-integration' ) );
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
			$result = civicrm_api3( 'Contact', 'get', $params );
		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Failed to get Contact by Email', 'wpcv-woo-civi-integration' ) );
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

		// No matches found, so we will need to create a Contact.
		if ( count( $result ) == 0 ) {
			return 0;
		}

		$contact_id = isset( $result['values'][0]['id'] ) ? $result['values'][0]['id'] : 0;
		return $contact_id;

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

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM UFMatch data.', 'wpcv-woo-civi-integration' ) );
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

		// Return the UFMatch data if there's no error.
		if ( empty( $result['is_error'] ) ) {
			return $result;
		}

		// Fallback.
		return false;

	}

	/**
	 * Create or update a CiviCRM Contact.
	 *
	 * @since 2.0
	 *
	 * @param int $contact_id The numeric ID if the CiviCRM Contact.
	 * @param object $order The Order object.
	 * @return int|bool $contact_id The numeric ID if the CiviCRM Contact, or false on failure.
	 */
	public function add_update_contact( $contact_id, $order ) {

		/**
		 * Allow Contact update to be bypassed.
		 *
		 * Return boolean "true" to bypass the update process.
		 *
		 * @since 2.0
		 *
		 * @param bool False by default: do not bypass update.
		 * @param int $contact_id The numeric ID of the Contact.
		 * @param object $order The WooCommerce Order object.
		 */
		if ( true === apply_filters( 'wpcv_woo_civi/contact/add_update/bypass', false, $contact_id, $order ) ) {
			return $contact_id;
		}

		$action = 'create';

		$contact = [];
		if ( 0 !== $contact_id ) {

			try {

				$params = [
					'contact_id' => $contact_id,
					'return' => [ 'id', 'contact_source', 'first_name', 'last_name', 'contact_type' ],
				];

				$contact = civicrm_api3( 'contact', 'getsingle', $params );

			} catch ( CiviCRM_API3_Exception $e ) {

				// Write to CiviCRM log.
				CRM_Core_Error::debug_log_message( __( 'Unable to find Contact', 'wpcv-woo-civi-integration' ) );
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

		} else {
			$contact['contact_type'] = 'Individual';
		}

		// Create Contact.
		// Prepare array to update Contact via CiviCRM API.
		$contact_id = '';
		$email = $order->get_billing_email();
		$fname = $order->get_billing_first_name();
		$lname = $order->get_billing_last_name();

		// Try to get an existing CiviCRM Contact ID using dedupe.
		if ( '' !== $fname ) {
			$contact['first_name'] = $fname;
		} else {
			unset( $contact['first_name'] );
		}
		if ( '' !== $lname ) {
			$contact['last_name'] = $lname;
		} else {
			unset( $contact['last_name'] );
		}

		$contact['email'] = $email;
		$dedupe_params = CRM_Dedupe_Finder::formatParams( $contact, $contact['contact_type'] );
		$dedupe_params['check_permission'] = false;
		$ids = CRM_Dedupe_Finder::dupesByParams( $dedupe_params, $contact['contact_type'], 'Unsupervised' );

		if ( $ids ) {
			$contact_id = $ids['0'];
			$action = 'update';
		}

		if ( empty( $contact['contact_source'] ) ) {
			$contact['contact_source'] = __( 'WooCommerce purchase', 'wpcv-woo-civi-integration' );
		}

		// Create (or update) CiviCRM Contact.
		try {

			$result = civicrm_api3( 'Contact', 'create', $contact );

			$contact_id = $result['id'];

			// Get the link to the Contact in CiviCRM.
			$link = WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/contact/view', 'reset=1&cid=' . $contact_id );
			$contact_url = '<a href="' . $link . '">' . __( 'View', 'wpcv-woo-civi-integration' ) . '</a>';

			// Add Order note.
			// FIXME: Always records "CiviCRM Contact Updated".
			if ( 'update' === $action ) {
				/* translators: %s: The link to the Contact in CiviCRM */
				$note = sprintf( __( 'CiviCRM Contact Updated - %s', 'wpcv-woo-civi-integration' ), $contact_url );
			} else {
				/* translators: %s: The link to the Contact in CiviCRM */
				$note = sprintf( __( 'Created new CiviCRM Contact - %s', 'wpcv-woo-civi-integration' ), $contact_url );
			}

			$order->add_order_note( $note );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Contact', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'contact' => $contact,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		try {

			// FIXME: Error checking.
			$existing_addresses = civicrm_api3( 'Address', 'get', [ 'contact_id' => $contact_id ] );
			$existing_addresses = $existing_addresses['values'];
			$existing_phones = civicrm_api3( 'Phone', 'get', [ 'contact_id' => $contact_id ] );
			$existing_phones = $existing_phones['values'];
			$existing_emails = civicrm_api3( 'Email', 'get', [ 'contact_id' => $contact_id ] );
			$existing_emails = $existing_emails['values'];

			$address_types = WPCV_WCI()->helper->get_mapped_location_types();
			foreach ( $address_types as $address_type => $location_type_id ) {

				// Process Phone.
				$phone_exists = false;

				// 'shipping_phone' does not exist as a WooCommerce field.
				if ( 'shipping' !== $address_type && ! empty( $order->{'get_' . $address_type . '_phone'}() ) ) {
					$phone = [
						'phone_type_id' => 1,
						'location_type_id' => $location_type_id,
						'phone' => $order->{'get_' . $address_type . '_phone'}(),
						'contact_id' => $contact_id,
					];
					foreach ( $existing_phones as $existing_phone ) {
						if ( isset( $existing_phone['location_type_id'] ) && $existing_phone['location_type_id'] === $location_type_id ) {
							$phone['id'] = $existing_phone['id'];
						}
						if ( $existing_phone['phone'] === $phone['phone'] ) {
							$phone_exists = true;
						}
					}
					if ( ! $phone_exists ) {

						// FIXME: Error checking.
						civicrm_api3( 'Phone', 'create', $phone );

						/* translators: %1$s: Address Type, %2$s: Phone Number */
						$note = sprintf( __( 'Created new CiviCRM Phone of type %1$s: %2$s', 'wpcv-woo-civi-integration' ), $address_type, $phone['phone'] );
						$order->add_order_note( $note );
					}
				}

				// Process Email.
				$email_exists = false;

				// 'shipping_email' does not exist as a WooCommerce field.
				if ( 'shipping' !== $address_type && ! empty( $order->{'get_' . $address_type . '_email'}() ) ) {
					$email = [
						'location_type_id' => $location_type_id,
						'email' => $order->{'get_' . $address_type . '_email'}(),
						'contact_id' => $contact_id,
					];
					foreach ( $existing_emails as $existing_email ) {
						if ( isset( $existing_email['location_type_id'] ) && $existing_email['location_type_id'] === $location_type_id ) {
							$email['id'] = $existing_email['id'];
						}
						if ( isset( $existing_email['email'] ) && $existing_email['email'] === $email['email'] ) {
							$email_exists = true;
						}
					}
					if ( ! $email_exists ) {

						// FIXME: Error checking.
						civicrm_api3( 'Email', 'create', $email );

						/* translators: %1$s: Address Type, %2$s: Email Address */
						$note = sprintf( __( 'Created new CiviCRM Email of type %1$s: %2$s', 'wpcv-woo-civi-integration' ), $address_type, $email['email'] );
						$order->add_order_note( $note );
					}
				}

				// Process Address.
				$address_exists = false;

				if ( ! empty( $order->{'get_' . $address_type . '_address_1'}() ) && ! empty( $order->{'get_' . $address_type . '_postcode'}() ) ) {

					$country_id = WPCV_WCI()->states->get_civicrm_country_id( $order->{'get_' . $address_type . '_country'}() );
					$address = [
						'location_type_id'       => $location_type_id,
						'city'                   => $order->{'get_' . $address_type . '_city'}(),
						'postal_code'            => $order->{'get_' . $address_type . '_postcode'}(),
						'name'                   => $order->{'get_' . $address_type . '_company'}(),
						'street_address'         => $order->{'get_' . $address_type . '_address_1'}(),
						'supplemental_address_1' => $order->{'get_' . $address_type . '_address_2'}(),
						'country'                => $country_id,
						'state_province_id'      => WPCV_WCI()->states->get_civicrm_state_province_id( $order->{'get_' . $address_type . '_state'}(), $country_id ),
						'contact_id'             => $contact_id,
					];

					foreach ( $existing_addresses as $existing ) {
						if ( isset( $existing['location_type_id'] ) && $existing['location_type_id'] === $location_type_id ) {
							$address['id'] = $existing['id'];
						} elseif (
							// TODO: Don't create if exact match of another - should we make 'exact match' configurable?
							isset( $existing['street_address'] )
							&& isset( $existing['city'] )
							&& isset( $existing['postal_code'] )
							&& isset( $address['street_address'] )
							&& $existing['street_address'] === $address['street_address']
							&& CRM_Utils_Array::value( 'supplemental_address_1', $existing ) === CRM_Utils_Array::value( 'supplemental_address_1', $address )
							&& $existing['city'] == $address['city']
							&& $existing['postal_code'] === $address['postal_code']
						) {
							$address_exists = true;
						}
					}
					if ( ! $address_exists ) {

						// FIXME: Error checking.
						civicrm_api3( 'Address', 'create', $address );

						/* translators: %1$s: Address Type, %2$s: Street Address */
						$note = sprintf( __( 'Created new CiviCRM Address of type %1$s: %2$s', 'wpcv-woo-civi-integration' ), $address_type, $address['street_address'] );
						$order->add_order_note( $note );
					}
				}
			}

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to add/update Address or Phone', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				//'params' => $params,
				//'result' => $result,
				'message' => 'HUGE TRY/CATCH FFS',
				'backtrace' => $trace,
			], true ) );

		}

		return $contact_id;

	}

}
