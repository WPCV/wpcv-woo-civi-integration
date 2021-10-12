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
	 * WooCommerce Order meta key holding the CiviCRM Contact ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_contact_id';

	/**
	 * Whether or not the Order is created via the WooCommerce Checkout.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $is_checkout True if in Checkout, false otherwise.
	 */
	public $is_checkout = false;

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
		$this->register_hooks();

		/**
		 * Broadcast that this class is loaded.
		 *
		 * Used internally by included classes in order to bootstrap.
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
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact-address.php';
		// Include Phone class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact-phone.php';
		// Include Email class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact-email.php';

		// Include Contact Orders Tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact-orders-tab.php';

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
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		// Process new WooCommerce Orders from Checkout.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'checkout_create_order' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'order_processed' ], 10, 3 );

		// Process changes in WooCommerce Orders.
		add_action( 'woocommerce_new_order', [ $this, 'order_new' ], 10, 2 );

	}

	/**
	 * Gets the CiviCRM Contact ID from WooCommerce Order meta.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @return int|bool $contact_id The numeric ID of the CiviCRM Contact, false otherwise.
	 */
	public function get_order_meta( $order_id ) {
		$contact_id = get_post_meta( $order_id, $this->meta_key, true );
		return (int) $contact_id;
	}

	/**
	 * Sets the CiviCRM Contact ID as meta data on a WooCommerce Order.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 */
	public function set_order_meta( $order_id, $contact_id ) {
		update_post_meta( $order_id, $this->meta_key, (int) $contact_id );
	}

	/**
	 * Called when a WooCommerce Order is created from the Checkout.
	 *
	 * The "woocommerce_checkout_create_order" action fires before the
	 * "woocommerce_new_order" action - so this gives us a way to determine the
	 * context in which the Order has been created.
	 *
	 * Note: Orders can also be created via the WooCommerce REST API, so this
	 * plugin also needs to check for that route as well.
	 *
	 * @since 3.0
	 *
	 * @param object $order The Order object.
	 * @param array $data The Order data.
	 */
	public function checkout_create_order( $order, $data ) {

		// Set flag.
		$this->is_checkout = true;

	}

	/**
	 * Performs necessary actions when a WooCommerce Order is created.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param object $order The Order object.
	 */
	public function order_new( $order_id, $order ) {

		// Bail when the Order is created in the Checkout.
		if ( $this->is_checkout ) {
			return;
		}

		// In WordPress admin, mimic the "woocommerce_checkout_order_processed" callback.
		$this->order_processed( $order_id, null, new WC_Order( $order_id ) );

		/**
		 * Broadcast that a new WooCommerce Order with CiviCRM data has been created.
		 *
		 * @since 3.0
		 *
		 * @param int $order_id The Order ID.
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/contact/order/new', $order_id, $order );

	}

	/**
	 * Performs necessary actions when an Order is processed in WooCommerce.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param array $posted_data The posted data.
	 * @param object $order The Order object.
	 */
	public function order_processed( $order_id, $posted_data, $order ) {

		// Get the Contact ID (or false on error)
		$contact_id = $this->get_id_by_order( $order );

		// TODO: Do we want to bail here, or carry on if there's an error?
		if ( false === $contact_id ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'wpcv-woo-civi-integration' ) );
			return;
		}

		// Create (or update) the CiviCRM Contact.
		if ( empty( $contact_id ) ) {

			$contact_id = $this->create_from_order( $order );
			if ( false === $contact_id ) {
				$order->add_order_note( __( 'CiviCRM Contact could not be created', 'wpcv-woo-civi-integration' ) );
				return;
			}

		} else {

			$contact_id = $this->update_from_order( $contact_id, $order );
			if ( false === $contact_id ) {
				$order->add_order_note( __( 'CiviCRM Contact could not be updated', 'wpcv-woo-civi-integration' ) );
				return;
			}

		}

		// Add Contact ID to Order meta.
		if ( empty( $this->get_order_meta( $order->get_id() ) ) ) {
			$this->set_order_meta( $order->get_id(), $contact_id );
		}

	}

	/**
	 * Get the CiviCRM Contact data for a given ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact to query.
	 * @return array|boolean $contact_data An array of Contact data, or false on failure.
	 */
	public function get_by_id( $contact_id ) {

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Define params to get queried Contact.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $contact_id,
			'options' => [
				'limit' => 1, // Only one please.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Contact', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Failed to get Contact by ID', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		$contact_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$contact_data = array_pop( $result['values'] );
			$contact_data['id'] = $contact_data['contact_id'];
		}

		return $contact_data;

	}

	/**
	 * Get the CiviCRM Contacts for a given Email.
	 *
	 * Previous versions of the plugin assume that no Contacts share the same
	 * Email address. This is not necessarily the case.
	 *
	 * @since 3.0
	 *
	 * @param str $email The Email address.
	 * @return array|bool $contacts The array of CiviCRM Contacts, or false on failure.
	 */
	public function get_by_email( $email ) {

		// Sanity check.
		if ( empty( $email ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'email' => $email,
		];

		$result = civicrm_api3( 'Contact', 'get', $params );

		// If there's an error.
		if ( ! empty( $result['is_error'] ) ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Failed to get Contact by Email', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		$contacts = [];

		// Overwrite with array of values if populated.
		if ( ! empty( $result['values'] ) ) {
			$contacts = $result['values'];
			foreach ( $contacts as $contact ) {
				$contact['id'] = $contact['contact_id'];
			}
		}

		return $contacts;

	}

	/**
	 * Gets a suggested CiviCRM Contact ID via the "Unsupervised" Dedupe Rule.
	 *
	 * @since 3.0
	 *
	 * @param array $contact The array of CiviCRM Contact data.
	 * @param string $contact_type The Contact Type.
	 * @return int|boolean $contact_id The suggested Contact ID, or false on failure.
	 */
	public function get_by_dedupe_unsupervised( $contact, $contact_type = 'Individual' ) {

		// Bail if we have no Contact data.
		if ( empty( $contact ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Get the Dedupe params.
		$dedupe_params = CRM_Dedupe_Finder::formatParams( $contact, $contact_type );
		$dedupe_params['check_permission'] = false;

		// Use Dedupe Rules to find possible Contact IDs.
		$contact_ids = CRM_Dedupe_Finder::dupesByParams( $dedupe_params, $contact_type, 'Unsupervised' );

		$contact_id = 0;

		// Return the suggested Contact ID.
		if ( ! empty( $contact_ids ) ) {
			$contact_ids = array_reverse( $contact_ids );
			$contact_id = array_pop( $contact_ids );
		}

		return $contact_id;

	}

	/**
	 * Gets a suggested CiviCRM Contact ID using a specified Dedupe Rule.
	 *
	 * @since 3.0
	 *
	 * @param array $contact The array of Contact data.
	 * @param string $contact_type The Contact Type.
	 * @param int $dedupe_rule_id The Dedupe Rule ID.
	 * @return int|bool $contact_id The numeric Contact ID, or false on failure.
	 */
	public function get_by_dedupe_rule( $contact, $contact_type = 'Individual', $dedupe_rule_id ) {

		// Bail if we have no Contact data.
		if ( empty( $contact ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Build the Dedupe params.
		$dedupe_params = CRM_Dedupe_Finder::formatParams( $contact, $contact_type );
		$dedupe_params['check_permission'] = false;

		$contact_id = 0;

		// Check for duplicates.
		$contact_ids = CRM_Dedupe_Finder::dupesByParams( $dedupe_params, $contact_type, NULL, [], $dedupe_rule_id );

		// Return the suggested Contact ID.
		if ( ! empty( $contact_ids ) ) {
			$contact_ids = array_reverse( $contact_ids );
			$contact_id = array_pop( $contact_ids );
		}

		return $contact_id;

	}

	/**
	 * Get Dedupe Rules.
	 *
	 * By default, all Dedupe Rules for all the top-level Contact Types will be
	 * returned, but you can specify a Contact Type if you want to limit what is
	 * returned.
	 *
	 * @since 3.0
	 *
	 * @param string $contact_type An optional Contact Type to filter rules by.
	 * @return array $dedupe_rules The Dedupe Rules, or empty on failure.
	 */
	public function dedupe_rules_get( $contact_type = '' ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		$dedupe_rules = [];

		// Add the Dedupe rules for all Contact Types.
		$types = [ 'Organization', 'Household', 'Individual' ];
		foreach( $types AS $type ) {
			if ( empty( $contact_type ) ) {
				$dedupe_rules[$type] = CRM_Dedupe_BAO_RuleGroup::getByType( $type );
			} elseif ( $contact_type == $type ) {
				$dedupe_rules[$type] = CRM_Dedupe_BAO_RuleGroup::getByType( $type );
			}
		}

		return $dedupe_rules;

	}

	/**
	 * Gets CiviCRM UFMatch data.
	 *
	 * Get UFMatch by CiviCRM "contact_id" or WordPress "user_id".
	 *
	 * It's okay not to find a UFMatch entry, so use "get" instead of "getsingle"
	 * and only log when there's a genuine API error.
	 *
	 * @since 2.0
	 *
	 * @param int $id The CiviCRM Contact ID or WordPress User ID.
	 * @param string $property Either 'contact_id' or 'uf_id'.
	 * @return array $result The UFMatch data, or empty array on failure.
	 */
	public function get_ufmatch( $id, $property ) {

		$ufmatch = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $ufmatch;
		}

		// Bail if there's a problem with the param.
		if ( ! in_array( $property, [ 'contact_id', 'uf_id' ], true ) ) {
			return $ufmatch;
		}

		$params = [
			'sequential' => 1,
			$property => $id,
		];

		$result = civicrm_api3( 'UFMatch', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM UFMatch data.', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return $ufmatch;

		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $ufmatch;
		}

 		// The result set should contain only one item.
		$ufmatch = array_pop( $result['values'] );

		return $ufmatch;

	}

	/**
	 * Create a CiviCRM Contact for a given set of data.
	 *
	 * @since 0.4
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @return array|boolean $contact_data The array Contact data from the CiviCRM API, or false on failure.
	 */
	public function create( $contact = [] ) {

		// Bail if there's no data.
		if ( empty( $contact ) ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Maybe debug?
		$params = [
			'debug' => 1,
		] + $contact;

		/*
		 * Minimum array to create a Contact:
		 *
		 * $params = [
		 *   'contact_type' => "Individual",
		 *   'contact_sub_type' => "Student",
		 *   'display_name' => "John Doe",
		 * ];
		 *
		 * Updates are triggered by:
		 *
		 * $params['id'] = 255;
		 */
		$result = civicrm_api3( 'Contact', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				//'backtrace' => $trace,
			), true ) );
			return false;
		}

		// Init as empty.
		$contact_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$contact_data = array_pop( $result['values'] );
			$contact_data['contact_id'] = $contact_data['id'];
		}

		return $contact_data;

	}

	/**
	 * Update a CiviCRM Contact with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect a Contact ID
	 * to have been set in the Contact data.
	 *
	 * @since 3.0
	 *
	 * @param array $contact The array of CiviCRM Contact data.
	 * @return array|boolean The array Contact data from the CiviCRM API, or false on failure.
	 */
	public function update( $contact ) {

		// Log and bail if there's no Contact ID.
		if ( empty( $contact['id'] ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to update a Contact.', 'wpcv-woo-civi-integration' ),
				'contact' => $contact,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Pass through.
		return $this->create( $contact );

	}

	/**
	 * Prepares CiviCRM Contact data from Order data.
	 *
	 * @since 3.0
	 *
	 * @param object $order The Order object.
	 * @return array $contact The prepared array of CiviCRM Contact data.
	 */
	public function prepare_from_order( $order ) {

		$contact = [
			'first_name' => '',
			'last_name' => '',
			'email' => '',
		];

		// Maybe populate First Name.
		$first_name = $order->get_billing_first_name();
		if ( ! empty( $first_name ) ) {
			$contact['first_name'] = $first_name;
		}

		// Maybe populate Last Name.
		$last_name = $order->get_billing_last_name();
		if ( ! empty( $last_name ) ) {
			$contact['last_name'] = $last_name;
		}

		// Maybe populate Email.
		$email = $order->get_billing_email();
		if ( ! empty( $first_name ) ) {
			$contact['email'] = $email;
		}

		return $contact;

	}

	/**
	 * Create a CiviCRM Contact from a given Order.
	 *
	 * @since 3.0
	 *
	 * @param object $order The Order object.
	 * @return int|bool $contact_id The numeric ID if the CiviCRM Contact, or false on failure.
	 */
	public function create_from_order( $order ) {

		/**
		 * Allow Contact create to be bypassed.
		 *
		 * Return boolean "true" to bypass this process.
		 *
		 * @since 3.0
		 *
		 * @param bool False by default: do not bypass.
		 * @param object $order The WooCommerce Order object.
		 */
		if ( true === apply_filters( 'wpcv_woo_civi/contact/create_from_order/bypass', false, $order ) ) {
			return false;
		}

		// Prime a Contact to dedupe.
		$contact = $this->prepare_from_order( $order );

		// We must NOT create a default Primary Email.
		unset( $contact['email'] );

		// We need a default Contact Type.
		// TODO: Maybe this should be a setting? With Sub-type as well perhaps?
		$contact['contact_type'] = 'Individual';

		/*
		 * The CiviCRM API requires at least a Display Name when creating a Contact.
		 * When First Name and Last Name are both missing, add a default to prevent
		 * an error when hitting the API.
		 */
		if ( empty( $contact['first_name'] ) && empty( $contact['last_name'] ) ) {
			$contact['display_name'] = __( 'Unknown Name', 'wpcv-woo-civi-integration' );
		}

		// Assign Source because this is a new Contact.
		$contact['contact_source'] = __( 'WooCommerce Purchase', 'wpcv-woo-civi-integration' );

		// Okay, go ahead and create a Contact.
		$contact = $this->create( $contact );

		// Bail if something went wrong.
		if ( $contact === false ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to create Contact', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		/**
		 * Fires when a Contact has been successfully created from an Order.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Order::note_add_contact_created() (Priority: 10)
		 * * WPCV_Woo_Civi_Contact_Email::entities_create() (Priority: 20)
		 * * WPCV_Woo_Civi_Contact_Phone::entities_create() (Priority: 30)
		 * * WPCV_Woo_Civi_Contact_Address::entities_create() (Priority: 40)
		 *
		 * @since 3.0
		 *
		 * @param array $contact The CiviCRM Contact data.
		 * @param object $order The WooCommerce Order object.
		 */
		do_action( 'wpcv_woo_civi/contact/create_from_order', $contact, $order );

		return $contact['id'];

	}

	/**
	 * Update a CiviCRM Contact from a given Order.
	 *
	 * @since 3.0
	 *
	 * @param int $contact_id The numeric ID if the CiviCRM Contact.
	 * @param object $order The Order object.
	 * @return int|bool $contact_id The numeric ID if the CiviCRM Contact, or false on failure.
	 */
	public function update_from_order( $contact_id, $order ) {

		/**
		 * Allow Contact update to be bypassed.
		 *
		 * Return boolean "true" to bypass this process.
		 *
		 * @since 3.0
		 *
		 * @param bool False by default: do not bypass.
		 * @param int $contact_id The numeric ID of the Contact.
		 * @param object $order The WooCommerce Order object.
		 */
		if ( true === apply_filters( 'wpcv_woo_civi/contact/update_from_order/bypass', false, $contact_id, $order ) ) {
			return $contact_id;
		}

		// Try and find the Contact.
		$contact = $this->get_by_id( $contact_id );

		// Sanity check.
		if ( $contact === false ) {
			return false;
		}

		// Prime the Contact array if empty.
		if ( empty( $contact ) ) {
			$contact = [
				'contact_type' => 'Individual',
			];
		}

		// Prime a Contact data array.
		$prepared_contact = $this->prepare_from_order( $order );

		// FIXME: Shouldn't the following depend on if there is existing data?

		// Overwrite First Name with data from Order.
		if ( ! empty( $prepared_contact['first_name'] ) ) {
			$contact['first_name'] = $prepared_contact['first_name'];
		}

		// Overwrite Last Name with data from Order.
		if ( ! empty( $prepared_contact['last_name'] ) ) {
			$contact['last_name'] = $prepared_contact['last_name'];
		}

		// We must NOT create or update the default Primary Email.
		unset( $contact['email'] );

		/*
		 * When First Name and Last Name are both missing, add a default to prevent
		 * an error when hitting the API.
		 */
		if ( empty( $contact['first_name'] ) && empty( $contact['last_name'] ) ) {
			$contact['display_name'] = __( 'Unknown Name', 'wpcv-woo-civi-integration' );
		}

		// Update the CiviCRM Contact.
		$contact = $this->update( $contact );

		// Bail if something went wrong.
		if ( $contact === false ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contact', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		/**
		 * Fires when a Contact has been successfully created or updated from an Order.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Order::note_add_contact_updated() (Priority: 10)
		 * * WPCV_Woo_Civi_Contact_Email::entities_update() (Priority: 20)
		 * * WPCV_Woo_Civi_Contact_Phone::entities_update() (Priority: 30)
		 * * WPCV_Woo_Civi_Contact_Address::entities_update() (Priority: 40)
		 *
		 * @since 3.0
		 *
		 * @param array $contact The CiviCRM Contact data.
		 * @param object $order The WooCommerce Order object.
		 */
		do_action( 'wpcv_woo_civi/contact/update_from_order', $contact, $order );

		return $contact['id'];

	}

	/**
	 * Tries to get a CiviCRM Contact ID for a given WooCommerce Order.
	 *
	 * @since 3.0
	 *
	 * @param object $order The WooCommerce Order object.
	 * @return int|bool $contact_id The numeric ID of the CiviCRM Contact if found.
	 *                              Returns 0 if a CiviCRM Contact cannot be found.
	 *                              Returns boolean false on failure.
	 */
	public function get_id_by_order( $order ) {

		// First check if this Order has the Contact ID stored in its meta data.
		$contact_id = $this->get_order_meta( $order->get_id() );

		// Return early if it does.
		if ( ! empty( $contact_id ) ) {
			return (int) $contact_id;
		}

		// Orders created in WordPress admin should not use the logged in User's Contact.
		// FIXME: Why not? The wrong Contact ID can be returned on the Edit Order screen when there's a duplicate Email.
		// This happens when the Default Org has the same Email as a Contact.

		// Check the WordPress User when it's a Checkout Order.
		if ( $this->is_checkout ) {

			// Check if this Order has a WordPress User ID.
			$user_id = $order->get_user_id();

			// If there's an existing User ID.
			if ( ! empty( $user_id ) ) {

				// Get the matched Contact ID.
				$ufmatch = $this->get_ufmatch( $user_id, 'uf_id' );

				// Return the Contact ID if found.
				if ( $ufmatch !== false && ! empty( $ufmatch['contact_id'] ) ) {
					return (int) $ufmatch['contact_id'];
				}

			}

		}

		/*
		 * Either it's WordPress admin or there's no User ID in the Order.
		 *
		 * This is where we need to analyse the incoming data and use Dedupe
		 * Rules to try and find a matching CiviCRM Contact.
		 */

		// Prime a Contact to dedupe.
		$contact = $this->prepare_from_order( $order );

		// TODO: Create setting to choose a Dedupe Rule.
		// @see self::get_by_dedupe_rule()

		// Fetch the suggested CiviCRM Contact ID using dedupe.
		$contact_id = $this->get_by_dedupe_unsupervised( $contact );

		return $contact_id;

	}

}
