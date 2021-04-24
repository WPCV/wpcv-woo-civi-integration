<?php
/**
 * WPCV WooCommerce CiviCRM Manager class.
 *
 * Manages general integration between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Manager class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Manager {

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

		add_action( 'init', [ $this, 'check_utm' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'action_order' ], 10, 3 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'update_order_status' ], 99, 3 );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_data_after_order_details' ], 30 );
		add_action( 'woocommerce_new_order', [ $this, 'save_order' ], 10 );

	}

	/**
	 * Return the Order Number.
	 *
	 * @since 2.2
	 *
	 * @param int $post_id The WordPress Post ID.
	 * @return string $invoice_id The Invoice ID.
	 */
	public function get_invoice_id( $post_id ) {

		$invoice_no = get_post_meta( $post_id, '_order_number', true );
		$invoice_id = ! empty( $invoice_no ) ? $invoice_no : $post_id . '_woocommerce';
		return $invoice_id;

	}

	/**
	 * Action called when a Post is saved.
	 *
	 * @param int $order_id The Order ID.
	 *
	 * @since 2.2
	 */
	public function save_order( $order_id ) {

		// Add the Campaign ID to the Order.
		$current_campaign_id = get_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', true );
		$new_campaign_id = filter_input( INPUT_POST, 'order_civicrmcampaign', FILTER_VALIDATE_INT );
		if ( false !== $new_campaign_id && $new_campaign_id !== $current_campaign_id ) {
			$this->update_campaign( $order_id, $current_campaign_id, $new_campaign_id );
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $new_campaign_id ) );
		}

		// Add the Source to Order.
		$current_civicrmsource = get_post_meta( $order_id, '_order_source', true );
		$new_civicrmsource = filter_input( INPUT_POST, 'order_civicrmsource', FILTER_SANITIZE_STRING );
		if ( false !== $new_civicrmsource && $new_civicrmsource !== $current_civicrmsource ) {
			$this->update_source( $order_id, $new_civicrmsource );
			update_post_meta( $order_id, '_order_source', esc_attr( $new_civicrmsource ) );
		}

		if (
			wp_verify_nonce( filter_input( INPUT_POST, 'woocommerce_civicrm_order_new', FILTER_SANITIZE_STRING ), 'woocommerce_civicrm_order_new' )
			|| ( filter_input( INPUT_POST, 'post_ID', FILTER_VALIDATE_INT ) === null && get_post_meta( $order_id, '_pos', true ) )
		) {
			$this->action_order( $order_id, null, new WC_Order( $order_id ) );
		}

	}

	/**
	 * Action called when Order is created in WooCommerce.
	 *
	 * @since 2.0
	 *
	 * @param int $order_id The Order ID.
	 * @param array $posted_data The posted data.
	 * @param object $order The Order object.
	 * @return int|void $oder_id The Order ID.
	 */
	public function action_order( $order_id, $posted_data, $order ) {

		$cid = WPCV_WCI()->helper->civicrm_get_cid( $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'wpcv-woo-civi-integration' ) );
			return;
		}

		$cid = $this->add_update_contact( $cid, $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be found or created', 'wpcv-woo-civi-integration' ) );
			return;
		}

		$source = $this->generate_source( $order );
		$this->update_source( $order_id, $source );
		update_post_meta( $order_id, '_order_source', $source );

		$this->utm_to_order( $order->get_id() );

		// Add the Contribution record.
		$this->add_contribution( $cid, $order );

		/**
		 * Broadcast that a Contribution record has been added for a new WooCommerce Order.
		 *
		 * @since 2.0
		 *
		 * @param object $order The Order object.
		 * @param int $cid The numeric ID of the CiviCRM Contact.
		 */
		do_action( 'wpcv_woo_civi/action_order', $order, $cid );

		return $order_id;

	}

	/**
	 * Update Order status.
	 *
	 * @since 2.0
	 * @param int $order_id The Order ID.
	 * @param string $old_status The old status.
	 * @param string $new_status The new status.
	 */
	public function update_order_status( $order_id, $old_status, $new_status ) {

		$order = new WC_Order( $order_id );

		$cid = WPCV_WCI()->helper->civicrm_get_cid( $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'wpcv-woo-civi-integration' ) );
			return;
		}

		$params = [
			'invoice_id' => $this->get_invoice_id( $order_id ),
			'return' => [ 'id', 'financial_type_id', 'receive_date', 'total_amount', 'contact_id' ],
		];

		try {

			/**
			 * Filter the Contribution params before calling the CiviCRM API.
			 *
			 * @since 2.0
			 *
			 * @param array $params The params to be passed to the CiviCRM API.
			 */
			$params = apply_filters( 'wpcv_woo_civi/contribution/get/params', $params );

			$contribution = civicrm_api3( 'Contribution', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Unable to find Contribution' );
			return;
		}

		// Update Contribution.
		try {

			$params = [
				'contribution_status_id' => $order->is_paid() ? 'Completed' : $this->map_contribution_status( $order->get_status() ),
				'id' => $contribution['id'],
				// 'financial_type_id' => $contribution['financial_type_id'],
				// 'receive_date' => $contribution['receive_date'],
				// 'total_amount' => $contribution['total_amount'],
				// 'contact_id' => $contribution['contact_id'],
			];

			$result = civicrm_api3( 'Contribution', 'create', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contribution', 'wpcv-woo-civi-integration' ) );
		}

	}

	/**
	 * Update Campaign.
	 *
	 * @since 2.0
	 *
	 * @param int $order_id The Order ID.
	 * @param string $old_campaign_id The old Campaign.
	 * @param string $new_campaign_id The new Campaign.
	 * @return bool True if successful, or false on failure.
	 */
	public function update_campaign( $order_id, $old_campaign_id, $new_campaign_id ) {

		$campaign_name = '';
		if ( false !== $new_campaign_id ) {

			try {

				$params = [
					'sequential' => 1,
					'return' => [ 'name' ],
					'id' => $new_campaign_id,
					'options' => [ 'limit' => 1 ],
				];

				$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );

				$campaign_name = isset( $campaigns_result['values'][0]['name'] ) ? $campaigns_result['values'][0]['name'] : '';

			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Unable to fetch Campaign', 'wpcv-woo-civi-integration' ) );
				return false;
			}

		}

		try {

			$params = [
				'invoice_id' => $this->get_invoice_id( $order_id ),
				'return' => 'id',
			];

			/**
			 * Filter the Contribution params before calling the CiviCRM API.
			 *
			 * @since 2.0
			 *
			 * @param array $params The params to be passed to the CiviCRM API.
			 */
			$params = apply_filters( 'wpcv_woo_civi/contribution/get/params', $params );

			$contribution = civicrm_api3( 'Contribution', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Unable to find Contribution' );
			return false;
		}

		// Update Contribution.
		try {

			$params = [
				'campaign_id' => $campaign_name,
				'id' => $contribution['id'],
			];

			$result = civicrm_api3( 'Contribution', 'create', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contribution', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		// Success.
		return true;

	}

	/**
	 * Update Source.
	 *
	 * @since 2.0
	 *
	 * @param int $order_id The Order ID.
	 * @param string $new_source The new Source.
	 * @return bool True if successful, or false on failure.
	 */
	public function update_source( $order_id, $new_source ) {

		try {

			$params = [
				'invoice_id' => $this->get_invoice_id( $order_id ),
				'return' => 'id',
			];

			/**
			 * Filter the Contribution params before calling the CiviCRM API.
			 *
			 * @since 2.0
			 *
			 * @param array $params The params to be passed to the CiviCRM API.
			 */
			$params = apply_filters( 'wpcv_woo_civi/contribution/get/params', $params );

			$contribution = civicrm_api3( 'Contribution', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Unable to find Contribution' );
			return false;
		}

		// Update Contribution.
		try {

			$params = [
				'source' => $new_source,
				'id' => $contribution['id'],
			];

			$result = civicrm_api3( 'Contribution', 'create', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contribution', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		// Success.
		return true;

	}

	/**
	 * Create or update a CiviCRM Contact.
	 *
	 * @since 2.0
	 *
	 * @param int $cid The numeric ID if the CiviCRM Contact.
	 * @param object $order The Order object.
	 * @return int|bool $cid The numeric ID if the CiviCRM Contact, or false on failure.
	 */
	public function add_update_contact( $cid, $order ) {

		/**
		 * Allow Contact update to be bypassed.
		 *
		 * Return boolean "true" to bypass the update process.
		 *
		 * @since 2.0
		 *
		 * @param bool False by default: do not bypass update.
		 * @param int $cid The numeric ID of the Contact.
		 * @param object $order The WooCommerce Order object.
		 */
		if ( true === apply_filters( 'wpcv_woo_civi/contact/add_update/bypass', false, $cid, $order ) ) {
			return $cid;
		}

		$action = 'create';

		$contact = [];
		if ( 0 !== $cid ) {

			try {

				$params = [
					'contact_id' => $cid,
					'return' => [ 'id', 'contact_source', 'first_name', 'last_name', 'contact_type' ],
				];

				$contact = civicrm_api3( 'contact', 'getsingle', $params );

			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Unable to find Contact', 'wpcv-woo-civi-integration' ) );
				return false;
			}

		} else {
			$contact['contact_type'] = 'Individual';
		}

		// Create Contact.
		// Prepare array to update Contact via CiviCRM API.
		$cid = '';
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
			$cid = $ids['0'];
			$action = 'update';
		}

		// FIXME: Why are we setting display_name?
		if ( '' !== trim( "{$fname} {$lname}" ) ) {
			$contact['display_name'] = "{$fname} {$lname}";
		}

		if ( empty( $contact['contact_source'] ) ) {
			$contact['contact_source'] = __( 'WooCommerce purchase', 'wpcv-woo-civi-integration' );
		}

		// Create (or update) CiviCRM Contact.
		try {

			$result = civicrm_api3( 'Contact', 'create', $contact );

			$cid = $result['id'];

			// FIXME: Use CiviCRM to build URL.
			$contact_url = '<a href="' . get_admin_url() . 'admin.php?page=CiviCRM&q=civicrm/contact/view&reset=1&cid=' . $cid . '">' . __( 'View', 'wpcv-woo-civi-integration' ) . '</a>';

			// Add Order note.
			// FIXME: Use sprintf.
			if ( 'update' === $action ) {
				$note = __( 'CiviCRM Contact Updated - ', 'wpcv-woo-civi-integration' ) . $contact_url;
			} else {
				$note = __( 'Created new CiviCRM Contact - ', 'wpcv-woo-civi-integration' ) . $contact_url;
			}

			$order->add_order_note( $note );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Contact', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		try {

			// FIXME: Error checking.
			$existing_addresses = civicrm_api3( 'Address', 'get', [ 'contact_id' => $cid ] );
			$existing_addresses = $existing_addresses['values'];
			$existing_phones = civicrm_api3( 'Phone', 'get', [ 'contact_id' => $cid ] );
			$existing_phones = $existing_phones['values'];
			$existing_emails = civicrm_api3( 'Email', 'get', [ 'contact_id' => $cid ] );
			$existing_emails = $existing_emails['values'];

			$address_types = WPCV_WCI()->helper->mapped_location_types;
			foreach ( $address_types as $address_type => $location_type_id ) {

				// Process Phone.
				$phone_exists = false;

				// 'shipping_phone' does not exist as a WooCommerce field.
				if ( 'shipping' !== $address_type && ! empty( $order->{'get_' . $address_type . '_phone'}() ) ) {
					$phone = [
						'phone_type_id' => 1,
						'location_type_id' => $location_type_id,
						'phone' => $order->{'get_' . $address_type . '_phone'}(),
						'contact_id' => $cid,
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
						'contact_id' => $cid,
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

					$country_id = WPCV_WCI()->helper->get_civi_country_id( $order->{'get_' . $address_type . '_country'}() );
					$address = [
						'location_type_id'       => $location_type_id,
						'city'                   => $order->{'get_' . $address_type . '_city'}(),
						'postal_code'            => $order->{'get_' . $address_type . '_postcode'}(),
						'name'                   => $order->{'get_' . $address_type . '_company'}(),
						'street_address'         => $order->{'get_' . $address_type . '_address_1'}(),
						'supplemental_address_1' => $order->{'get_' . $address_type . '_address_2'}(),
						'country'                => $country_id,
						'state_province_id'      => WPCV_WCI()->helper->get_civi_state_province_id( $order->{'get_' . $address_type . '_state'}(), $country_id ),
						'contact_id'             => $cid,
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
			CRM_Core_Error::debug_log_message( __( 'Unable to add/update Address or Phone', 'wpcv-woo-civi-integration' ) );
		}

		return $cid;

	}

	/**
	 * Add a Contribution record.
	 *
	 * @since 2.0
	 *
	 * @param int $cid The numeric ID of the CiviCRM Contact.
	 * @param WC_Order $order The Order object.
	 * @return bool True on success, otherwise false.
	 */
	public function add_contribution( $cid, $order ) {

		// Bail if Order is 'free' (0 amount) and 0 amount setting is enabled.
		if ( WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_ignore_0_amount_orders', false ) ) && $order->get_total() === 0 ) {
			return false;
		}

		$order_id = $order->get_id();
		$order_date = $order->get_date_paid();

		$order_paid_date = ! empty( $order_date ) ? $order_date->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		$order_id = $order->get_id();
		$txn_id = __( 'WooCommerce Order - ', 'wpcv-woo-civi-integration' ) . $order_id;
		$invoice_id = $this->get_invoice_id( $order_id );

		// Ensure number format is CiviCRM-compliant.
		$decimal_separator = '.';
		$thousand_separator = '';

		try {

			$params = [
				'sequential' => 1,
				'name' => 'monetaryDecimalPoint',
			];

			$civi_decimal_separator = civicrm_api3( 'Setting', 'getvalue', $params );

			$params = [
				'sequential' => 1,
				'name' => 'monetaryThousandSeparator',
			];

			$civi_thousand_separator = civicrm_api3( 'Setting', 'getvalue', $params );

			if ( is_string( $civi_decimal_separator ) ) {
				$decimal_separator = $civi_decimal_separator;
			}
			if ( is_string( $civi_thousand_separator ) ) {
				$thousand_separator = $civi_thousand_separator;
			}

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Monetary Settings', 'wpcv-woo-civi-integration' ) );
			return false;
		}

		$sales_tax_raw = $order->get_total_tax();
		$sales_tax = number_format( $sales_tax_raw, 2, $decimal_separator, $thousand_separator );

		$shipping_cost = $order->get_total_shipping();

		if ( ! $shipping_cost ) {
			$shipping_cost = 0;
		}
		$shipping_cost = number_format( $shipping_cost, 2, $decimal_separator, $thousand_separator );

		// FIXME: Landmine. CiviCRM doesn't seem to accept financial values with precision greater than 2 digits after the decimal.
		$rounded_total = round( $order->get_total() * 100 ) / 100;

		/*
		 * Couldn't figure where WooCommerce stores the subtotal (ie no TAX price)
		 * So for now...
		 */
		$rounded_subtotal = $rounded_total - $sales_tax_raw;

		$rounded_subtotal = number_format( $rounded_subtotal, 2, $decimal_separator, $thousand_separator );

		// Get the default Financial Type.
		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		// Get the default VAT Financial Type.
		$default_financial_type_vat_id = get_option( 'woocommerce_civicrm_financial_type_vat_id' );
		// Get the default Financial Type Shipping ID.
		$default_financial_type_shipping_id = get_option( 'woocommerce_civicrm_financial_type_shipping_id' );

		// Get the global CiviCRM Campaign ID.
		$woocommerce_civicrm_campaign_id = get_option( 'woocommerce_civicrm_campaign_id', false );
		$local_campaign_id = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );
		// Use the local CiviCRM Campaign ID if possible.
		if ( ! empty( $local_campaign_id ) ) {
			$woocommerce_civicrm_campaign_id = $local_campaign_id;
		}

		$items = $order->get_items();

		$payment_instrument = $this->map_payment_instrument( $order->get_payment_method() );
		$source = $this->generate_source( $order );

		$params = [
			'contact_id' => $cid,
			'financial_type_id' => $default_financial_type_id,
			'payment_instrument_id' => $payment_instrument,
			'trxn_id' => $txn_id,
			'invoice_id' => $invoice_id,
			'source' => $source,
			'receive_date' => $order_paid_date,
			'contribution_status_id' => 'Pending',
			'note' => $this->create_detail_string( $items ),
			'line_items' => [],
		];

		if ( ! empty( $woocommerce_civicrm_campaign_id ) ) {
			$params['campaign_id'] = $woocommerce_civicrm_campaign_id;
		}

		// If the order has VAT (Tax) use VAT Financial Type.
		if ( 0 !== $sales_tax ) {
			// Needs to be set in admin page.
			$params['financial_type_id'] = $default_financial_type_vat_id;
		}

		// TODO: Error checking.
		$default_contribution_amount_data = WPCV_WCI()->helper->get_default_contribution_price_field_data();

		/*
		 * Add line items to CiviCRM Contribution.
		 *
		 * @since 2.2
		 */
		if ( count( $items ) ) {
			$financial_types = [];
			foreach ( $items as $item ) {

				$product = $item->get_product();

				$product_financial_type_id = empty( $product->get_meta( 'woocommerce_civicrm_financial_type_id' ) )
					? get_post_meta( $item['product_id'], '_civicrm_contribution_type', true )
					: $product->get_meta( 'woocommerce_civicrm_financial_type_id' );

				if ( 'exclude' === $product_financial_type_id ) {
					continue;
				}

				if ( empty( $product_financial_type_id ) ) {
					$product_financial_type_id = $default_financial_type_id;
				}
				if ( 0 === $item['qty'] ) {
					$item['qty'] = 1;
				}

				$line_item = [
					'price_field_id' => $default_contribution_amount_data['price_field']['id'],
					'qty' => $item['qty'],
					'line_total' => number_format( $item['line_total'], 2, $decimal_separator, $thousand_separator ),
					'unit_price' => number_format( $item['line_total'] / $item['qty'], 2, $decimal_separator, $thousand_separator ),
					'label' => $item['name'],
					'financial_type_id' => $product_financial_type_id,
				];

				// Get Membership Type ID from Product meta.
				$product_membership_type_id = $product->get_meta( 'woocommerce_civicrm_membership_type_id' );

				// FIXME
				/*
				 * Decide whether we want to override the financial type with
				 * the one from the Membership Type instead of Product/default.
				 */

				// Add line item membership params if applicable.
				if ( ! empty( $product_membership_type_id ) ) {

					$line_item = array_merge(
						$line_item,
						[
							'entity_table' => 'civicrm_membership',
							'membership_type_id' => $product_membership_type_id,
						]
					);

					$line_item_params = [
						'membership_type_id' => $product_membership_type_id,
						'contact_id' => $cid,
					];

				}

				$params['line_items'][ $item->get_id() ] = isset( $line_item_params )
					? [
						'line_item' => [ $line_item ],
						'params' => $line_item_params,
					]
					: [ 'line_item' => [ $line_item ] ];

				$financial_types[ $product_financial_type_id ] = $product_financial_type_id;

			}

			if ( 1 === count( $financial_types ) ) {
				$params['financial_type_id'] = $product_financial_type_id;
			}
		}

		/*
		 * Line item for shipping.
		 *
		 * Shouldn't it be added to it's corresponding product/line_item?
		 * i.e. an order can have both shippable and downloadable products?
		 */
		if ( floatval( $shipping_cost ) > 0 ) {
			$params['line_items'][0] = [
				'line_item' => [
					[
						'price_field_id' => $default_contribution_amount_data['price_field']['id'],
						'qty' => 1,
						'line_total' => $shipping_cost,
						'unit_price' => $shipping_cost,
						'label' => 'Shipping',
						'financial_type_id' => $default_financial_type_shipping_id,
					]
				],
			];
		}

		// Flush UTM cookies.
		$this->delete_utm_cookies();
		try {

			/**
			 * Filter the Contribution params before calling the CiviCRM API.
			 *
			 * @since 2.0
			 *
			 * @param array $params The params to be passed to the CiviCRM API.
			 * @param object $order The WooCommerce Order object.
			 */
			$params = apply_filters( 'wpcv_woo_civi/order/create/params', $params, $order );

			$contribution = civicrm_api3( 'Order', 'create', $params );

			if ( isset( $contribution['id'] ) && $contribution['id'] ) {

				// Adds Order note in reference to the created Contribution.
				$order->add_order_note(
					sprintf(
						/* translators: %s: The Contact Summary Page URL */
						__( 'Contribution %s has been created in CiviCRM', 'wpcv-woo-civi-integration' ),
						'<a href="'
						. add_query_arg(
							[
								'page' => 'CiviCRM',
								'q' => 'civicrm/contact/view/contribution',
								'reset' => '1',
								'id' => $contribution['id'],
								'cid' => $cid,
								'action' => 'view',
								'context' => 'dashboard',
								'selectedChild' => 'contribute',
							],
							admin_url( 'admin.php' )
						)
						. '">' . $contribution['id'] . '</a>'
					)
				);
				update_post_meta( $order_id, '_woocommerce_civicrm_contribution_id', $contribution['id'] );

				return $contribution;

			}

		} catch ( CiviCRM_API3_Exception $e ) {
			// Log the error, but continue.
			CRM_Core_Error::debug_log_message( __( 'Unable to add Contribution', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		return false;

	}

	/**
	 * Maps a WooCommerce payment method to a CiviCRM payment instrument.
	 *
	 * @since 2.0
	 *
	 * @param string $payment_method The WooCommerce payment method.
	 * @return int $id The CiviCRM payment processor ID.
	 */
	public function map_payment_instrument( $payment_method ) {

		$map = [
			'paypal' => 1,
			'cod' => 3,
			'cheque' => 4,
			'bacs' => 5,
		];

		if ( array_key_exists( $payment_method, $map ) ) {
			$id = $map[ $payment_method ];
		} else {
			// Another WooCommerce payment method - good chance this is credit.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Create string to insert for Purchase Activity Details.
	 *
	 * @since 2.0
	 *
	 * @param object $items The Order object.
	 * @return string $str The Purchase Activity Details.
	 */
	public function create_detail_string( $items ) {

		$str = '';
		$n = 1;
		foreach ( $items as $item ) {
			if ( $n > 1 ) {
				$str .= ', ';
			}
			$str .= $item['name'] . ' x ' . $item['quantity'];
			$n++;
		}

		return $str;

	}

	/**
	 * Get the CiviCRM Contribution Status ID for a given WooCommerce Order Status.
	 *
	 * @since 2.0
	 *
	 * @param string $order_status The WooCommerce Order Status.
	 * @return int $id The numeric ID of the CiviCRM Contribution Status.
	 */
	public function map_contribution_status( $order_status ) {

		$map = [
			'wc-completed'  => 1,
			'wc-pending'    => 2,
			'wc-cancelled'  => 3,
			'wc-failed'     => 4,
			'wc-processing' => 5,
			'wc-on-hold'    => 5,
			'wc-refunded'   => 7,
			'completed'  => 1,
			'pending'    => 2,
			'cancelled'  => 3,
			'failed'     => 4,
			'processing' => 5,
			'on-hold'    => 5,
			'refunded'   => 7,
		];

		if ( array_key_exists( $order_status, $map ) ) {
			$id = $map[ $order_status ];
		} else {
			// Oh no.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Generates a string to define a Contribution Source.
	 *
	 * @since 2.2
	 *
	 * @param object $order The Order object.
	 * @return string $source The Contribution Source string.
	 */
	public function generate_source( $order ) {

		// Default is the Order Type.
		// Until 2.2, Contribution Source was exactly the same as Contribution note.
		$source = '';

		if ( get_post_meta( $order->get_id(), '_order_source', true ) === 'pos' ) {
			$source = 'pos';
		} else {

			$cookie = wp_unslash( $_COOKIE );
			// Checks if users comes from a Campaign.
			if ( isset( $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) {
				$source = esc_attr( $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] );
			}
			// Append medium UTM if present.
			if ( isset( $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) {
				$source .= ' / ' . esc_attr( $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] );
			}
		}

		$order_source = get_post_meta( $order->get_id(), '_order_source', true );
		if ( false === $order_source ) {
			$order_source = '';
		}

		if ( '' === $source ) {
			$source = __( 'shop', 'wpcv-woo-civi-integration' );
		}

		return $source;

	}

	/**
	 * Adds a form field to set a Campaign.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_data_after_order_details( $order ) {

		if ( $order->get_status() === 'auto-draft' ) {
			wp_nonce_field( 'woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new' );
		} else {
			wp_nonce_field( 'woocommerce_civicrm_order_edit', 'woocommerce_civicrm_order_edit' );
		}

		wp_enqueue_script(
			'wccivi_admin_order',
			WPCV_WOO_CIVI_URL . 'assets/js/admin_order.js',
			'jquery',
			WPCV_WOO_CIVI_VERSION,
			true
		);

		$order_campaign = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );

		// If there is no Campaign selected, select the default one as defined on our Settings page.
		if ( '' === $order_campaign || false === $order_campaign ) {
			// Get the global CiviCRM Campaign ID.
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' );
		}

		/**
		 * Filter the choice of Campaign List array to fetch.
		 *
		 * To fetch all Campaigns, return something other than 'campaigns'.
		 *
		 * @since 2.2
		 *
		 * @param str The array of Campaigns to fetch. Default 'campaigns'.
		 */
		$campaign_array = apply_filters( 'wpcv_woo_civi/campaign_list/get', 'campaigns' );

		if ( 'campaigns' === $campaign_array ) {
			$campaign_list = WPCV_WCI()->helper->campaigns;
		} else {
			$campaign_list = WPCV_WCI()->helper->all_campaigns;
		}

		?>
		<p class="form-field form-field-wide wc-civicrmcampaign">
			<label for="order_civicrmcampaign"><?php esc_html_e( 'CiviCRM Campaign', 'wpcv-woo-civi-integration' ); ?></label>
			<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="<?php esc_attr( __( 'CiviCRM Campaign', 'wpcv-woo-civi-integration' ) ); ?>">
				<option value=""></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
				<option value="<?php esc_attr( $campaign_id ); ?>" <?php selected( $campaign_id, $order_campaign, true ); ?>><?php echo esc_attr( $campaign_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php

		$order_source = get_post_meta( $order->get_id(), '_order_source', true );
		if ( false === $order_source ) {
			$order_source = '';
		}

		?>
		<p class="form-field form-field-wide wc-civicrmsource">
			<label for="order_civicrmsource"><?php esc_html_e( 'CiviCRM Source', 'wpcv-woo-civi-integration' ); ?></label>
			<input type='text' list="sources" id="order_civicrmsource" name="order_civicrmsource" data-placeholder="<?php esc_attr_e( 'CiviCRM Source', 'wpcv-woo-civi-integration' ); ?>" value="<?php echo esc_attr( $order_source ); ?>">
			<datalist id="sources">

			<?php
			global $wpdb;
			// FIXME: What is this, why use wpdb? Interrogation de la base de données.
			$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_source'" );
			// Parcours des resultats obtenus.
			if ( count( $results ) > 0 ) {
				foreach ( $results as $meta ) {
					echo esc_html( '<option value="' . $meta->meta_value . '">' );
				}
			}
			?>
			</datalist>

		</p>
		<?php
		$cid = WPCV_WCI()->helper->civicrm_get_cid( $order );
		if ( $cid ) {
			?>
			<div class="form-field form-field-wide wc-civicrmsource">
				<h3>
				<?php
				echo sprintf(
					/* translators: %s: Contact Summary Screen link */
					__( 'View %s in CiviCRM', 'wpcv-woo-civi-integration' ),
					'<a href="'
					. add_query_arg(
						[
							'page' => 'CiviCRM',
							'q' => 'civicrm/contact/view/',
							'cid' => $cid,
							'action' => 'view',
							'context' => 'dashboard',
						],
						admin_url( 'admin.php' )
					)
					. '" target="_blank"> '
					. _x( 'Contact', 'in: View Contact in CiviCRM', 'wpcv-woo-civi-integration' )
					. '</a>'
				);
				?>
				</h3>
			</div>
			<?php
		}

	}

	/**
	 * Check if UTM parameters are passed in URL (front-end only).
	 *
	 * @since 2.2
	 */
	public function check_utm() {

		if ( is_admin() ) {
			return;
		}

		if ( isset( $_GET['utm_campaign'] ) || isset( $_GET['utm_source'] ) || isset( $_GET['utm_medium'] ) ) {
			$this->save_utm_cookies();
		}

	}

	/**
	 * Save UTM parameters to cookies.
	 *
	 * @since 2.2
	 */
	private function save_utm_cookies() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		/**
		 * Filter the cookie expiry time.
		 *
		 * @since 2.2
		 *
		 * @param int The duration of the cookie. Default 0.
		 */
		$expire = apply_filters( 'wpcv_woo_civi/utm_cookie/expire', 0 );
		$secure = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );
		$campaign = filter_input( INPUT_GET, 'utm_campaign' );

		if ( false !== $campaign ) {

			try {

				$params = [
					'sequential' => 1,
					'return' => ['id'],
					'name' => esc_attr( $campaign ),
				];

				$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );

				// FIXME: Error checking.
				if ( $campaigns_result && isset( $campaigns_result['values'][0]['id'] ) ) {
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, $campaigns_result['values'][0]['id'], $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
				} else {
					// Remove cookie if Campaign is invalid.
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS );
				}

			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Unable to fetch Campaign', 'wpcv-woo-civi-integration' ) );
				return false;
			}
		}

		$source = filter_input( INPUT_GET, 'utm_source' );
		if ( false !== $source ) {
			setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, esc_attr( $source ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}

		$medium = filter_input( INPUT_GET, 'utm_medium' );
		if ( false !== $medium ) {
			setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, esc_attr( $medium ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}

		// Success.
		return true;

	}

	/**
	 * Saves UTM cookie to post meta.
	 *
	 * @since 2.2
	 *
	 * @param int $order_id The Order ID.
	 */
	private function utm_to_order( $order_id ) {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$cookie = wp_unslash( $_COOKIE );
		if ( isset( $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) {
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) );
			setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		} else {
			// Get the global CiviCRM Campaign ID.
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' );
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', $order_campaign );
		}

	}

	/**
	 * Delete UTM cookies.
	 *
	 * @since 2.2
	 */
	private function delete_utm_cookies() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Remove any existing cookies.
		$past = time() - YEAR_IN_SECONDS;
		setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );

	}

}
