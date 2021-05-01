<?php
/**
 * Orders class.
 *
 * Handles the integration of WooCommerce Orders with CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Orders class.
 *
 * @since 2.2
 */
class WPCV_Woo_Civi_Orders {

	/**
	 * WooCommerce Order meta key holding the CiviCRM Contribution ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_contribution_id';

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

		// Process new WooCommerce Orders from Checkout.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'checkout_create_order' ], 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'order_processed' ], 20, 3 );

		// Process changes in WooCommerce Orders.
		add_action( 'woocommerce_new_order', [ $this, 'order_new' ], 20, 2 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'order_status_changed' ], 99, 4 );

		// Add CiviCRM options to Edit Order screen.
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_data_additions' ], 30 );

		// Add Contact info notes to an Order.
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'note_add_contact_created' ], 10, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'note_add_contact_updated' ], 10, 2 );

	}

	/**
	 * Gets the CiviCRM Contribution ID from WooCommerce Order meta.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @return int|bool $contribution_id The numeric ID of the CiviCRM Contribution, false otherwise.
	 */
	public function get_order_meta( $order_id ) {
		$contribution_id = get_post_meta( $order_id, $this->meta_key, true );
		return $contribution_id;
	}

	/**
	 * Sets the CiviCRM Contribution ID as meta data on a WooCommerce Order.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param int $contribution_id The numeric ID of the CiviCRM Contribution.
	 */
	public function set_order_meta( $order_id, $contribution_id ) {
		update_post_meta( $order_id, $this->meta_key, $contribution_id );
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
	 * @since 2.2
	 * @since 3.0 Renamed from "save_order".
	 * @since 3.0 Added $order param.
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
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Source::order_new() (Priority: 10)
		 * * WPCV_Woo_Civi_Campaign::utm_to_order() (Priority: 20)
		 *
		 * @since 3.0
		 *
		 * @param int $order_id The Order ID.
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/order/new', $order_id, $order );

	}

	/**
	 * Performs necessary actions when an Order is processed in WooCommerce.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed from "action_order".
	 *
	 * @param int $order_id The Order ID.
	 * @param array $posted_data The posted data.
	 * @param object $order The Order object.
	 */
	public function order_processed( $order_id, $posted_data, $order ) {

		// Add the Contribution record.
		$contribution = $this->contribution_create( $order );

		/**
		 * Broadcast that a Contribution record has been added for a new WooCommerce Order.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Source::order_processed() (Priority: 10)
		 * * WPCV_Woo_Civi_UTM::utm_to_order() (Priority: 20)
		 *
		 * @since 3.0
		 *
		 * @param int $order_id The Order ID.
		 * @param object $order The Order object.
		 * @param array $contribution The array of Contribution data, or false on failure.
		 */
		do_action( 'wpcv_woo_civi/order/processed', $order_id, $order, $contribution );

	}

	/**
	 * Performs necessary actions when the status of an Order is changed.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed from "update_order_status".
	 * @since 3.0 Added $order param.
	 *
	 * @param int $order_id The Order ID.
	 * @param string $old_status The old status.
	 * @param string $new_status The new status.
	 * @param object $order The Order object.
	 */
	public function order_status_changed( $order_id, $old_status, $new_status, $order ) {

		// Return early if there is no change in the WooCommerce Order status.
		if ( $old_status === $new_status ) {
			return;
		}

		// Return early if there is no change in the CiviCRM Contrbution status.
		$old_status_id = $this->contribution_status_map( $old_status );
		$new_status_id = $this->contribution_status_map( $new_status );
		if ( $old_status_id === $new_status_id ) {
			return;
		}

		// Get Contribution.
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );
		$contribution = WPCV_WCI()->helper->get_contribution_by_invoice_id( $invoice_id );
		if ( empty( $contribution ) ) {
			return;
		}

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		// Overwrite a Contribution Status.
		if ( $order->is_paid() ) {
			$contribution['contribution_status_id'] = 'Completed';
		} else {
			$contribution['contribution_status_id'] = $new_status_id;
		}

		// Update Contribution.
		$result = civicrm_api3( 'Contribution', 'create', $contribution );

		// Sanity check.
		if ( ! empty( $result['error'] ) ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to update Order Status', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'contribution' => $contribution,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

		}

	}

	/**
	 * Add a Contribution record.
	 *
	 * @since 2.0
	 *
	 * @param WC_Order $order The Order object.
	 * @return bool True on success, otherwise false.
	 */
	public function contribution_create( $order ) {

		// Bail if Order is 'free' (0 amount) and 0 amount setting is enabled.
		$ignore_zero_orders = WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_ignore_0_amount_orders', false ) );
		if ( $ignore_zero_orders && $order->get_total() === 0 ) {
			return false;
		}

		$order_id = $order->get_id();

		// Get the default Financial Type & Payment Method.
		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		$payment_instrument_id = $this->payment_instrument_map( $order->get_payment_method() );

		/* translators: %d: The numeric ID of the WooCommerce Order */
		$trxn_id = sprintf( __( 'WooCommerce Order - %d', 'wpcv-woo-civi-integration' ), (int) $order_id );
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );

		// FIXME: Date calculation.
		$order_date = $order->get_date_paid();
		$order_paid_date = ! empty( $order_date ) ? $order_date->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		// Get the Contact ID associated with this Order.
		$contact_id = WPCV_WCI()->contact->get_id_by_order( $order );

		// FIXME: Error check?

		// Init Order params.
		$params = [
			'contact_id' => $contact_id,
			'financial_type_id' => $default_financial_type_id,
			'payment_instrument_id' => $payment_instrument_id,
			'trxn_id' => $trxn_id,
			'invoice_id' => $invoice_id,
			'receive_date' => $order_paid_date,
			'contribution_status_id' => 'Pending',
		];

		// Override Financial Type if Order has a tax value.
		// FIXME: This could be done via the filter below.
		$params = $this->tax_add_to_order( $params, $order );

		/**
		 * Filter the Contribution params before calling the CiviCRM API.
		 *
		 * Used internally by:
		 *
		 * - WPCV_Woo_Civi_Source (Priority: 10)
		 * - WPCV_Woo_Civi_Campaign (Priority: 20)
		 * - WPCV_Woo_Civi_Products (Priority: 30)
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 * @param object $order The WooCommerce Order object.
		 */
		$params = apply_filters( 'wpcv_woo_civi/order/create/params', $params, $order );

		try {

			$contribution = civicrm_api3( 'Order', 'create', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log and continue.
			CRM_Core_Error::debug_log_message( __( 'Unable to add Contribution', 'wpcv-woo-civi-integration' ) );
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

		// Sanity check.
		if ( empty( $contribution['id'] ) || ! is_numeric( $contribution['id'] ) ) {

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'contribution' => $contribution,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		// Add an Order note with reference to the created Contribution.
		$link = WPCV_WCI()->helper->get_civi_admin_link(
			'civicrm/contact/view/contribution',
			'reset=1&id=' . $contribution['id'] . '&cid=' . $contact_id . '&action=view'
		);

		$note = sprintf(
			/* translators: %s: The View Contribution link */
			__( 'Contribution %s has been created in CiviCRM', 'wpcv-woo-civi-integration' ),
			'<a href="' . $link . '">' . $contribution['id'] . '</a>'
		);

		$order->add_order_note( $note );

		// Save Contribution ID in post meta.
		$this->set_order_meta( $order_id, $contribution['id'] );

		/**
		 * Broadcast that a Contribution has been created.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_UTM::utm_cookies_delete() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param array $contribution The CiviCRM Contribution data.
		 * @param object $order The WooCommerce Order object.
		 */
		do_action( 'wpcv_woo_civi/order/created', $contribution, $order );

		// Success.
		return $contribution;

	}

	/**
	 * Filters the Order params to add the Tax and override Financial Type.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function tax_add_to_order( $params, $order ) {

		// Bail if we can't get the Monetary settings.
		$decimal_separator = WPCV_WCI()->helper->get_decimal_separator();
		$thousand_separator = WPCV_WCI()->helper->get_thousand_separator();
		if ( $decimal_separator === false || $thousand_separator === false ) {
			return $params;
		}

		$tax_raw = $order->get_total_tax();

		// Return early if the Order has no Tax.
		if ( 0 === $tax_raw ) {
			return $params;
		}

		// Ensure number format is CiviCRM-compliant.
		$tax = number_format( $tax_raw, 2, $decimal_separator, $thousand_separator );

		// FIXME: Neither $rounded_total nor $rounded_subtotal are used.

		// FIXME: CiviCRM doesn't seem to accept financial values with precision greater than 2 digits after the decimal.
		//$rounded_total = round( $order->get_total() * 100 ) / 100;

		/*
		 * Couldn't figure where WooCommerce stores the subtotal (ie no TAX price)
		 * So for now...
		 *
		 * CMW: The WooCommerce Order has all the meta needed here:
		 *
		 * * "_order_total"
		 * * "_order_tax"
		 * * "_order_shipping_tax"
		 *
		 * There are also Order methods that can help:
		 *
		 * * WC_Abstract_Order::get_item_total( $item, $inc_tax = false, $round = true )
		 *
		 * None of this is properly implemented at present.
		 */
		//$rounded_subtotal = $rounded_total - $tax_raw;

		// Ensure number format is CiviCRM-compliant.
		//$rounded_subtotal = number_format( $rounded_subtotal, 2, $decimal_separator, $thousand_separator );

		// Get the default VAT Financial Type.
		$default_financial_type_vat_id = get_option( 'woocommerce_civicrm_financial_type_vat_id' );

		// Needs to be defined in Settings.
		if ( empty( $default_financial_type_vat_id ) ) {

			// Write message to CiviCRM log.
			$message = __( 'There must be a default VAT Financial Type set.', 'wpcv-woo-civi-integration' );
			CRM_Core_Error::debug_log_message( $message );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' =>  $message,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return $params;

		}

		// Override with the default VAT Financial Type.
		$params['financial_type_id'] = $default_financial_type_vat_id;

		return $params;

	}

	/**
	 * Get the CiviCRM Contribution Status ID for a given WooCommerce Order Status.
	 *
	 * @since 2.0
	 *
	 * @param string $order_status The WooCommerce Order Status.
	 * @return int $id The numeric ID of the CiviCRM Contribution Status.
	 */
	public function contribution_status_map( $order_status ) {

		$map = [
			'wc-completed'  => 1,
			'wc-pending'    => 2,
			'wc-cancelled'  => 3,
			'wc-failed'     => 4,
			'wc-processing' => 2,
			'wc-on-hold'    => 2,
			'wc-refunded'   => 7,
			'completed'  => 1,
			'pending'    => 2,
			'cancelled'  => 3,
			'failed'     => 4,
			'processing' => 2,
			'on-hold'    => 2,
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
	 * Maps a WooCommerce payment method to a CiviCRM payment instrument.
	 *
	 * @since 2.0
	 *
	 * @param string $payment_method The WooCommerce payment method.
	 * @return int $id The CiviCRM payment processor ID.
	 */
	public function payment_instrument_map( $payment_method ) {

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
	 * Adds a note to an Order when a Contact has been added or edited.
	 *
	 * @since 3.0
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function note_add_contact_created( $contact, $order ) {

		// Get the link to the Contact in CiviCRM.
		$link = WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/contact/view', 'reset=1&cid=' . $contact['contact_id'] );
		$contact_url = '<a href="' . $link . '">' . __( 'View', 'wpcv-woo-civi-integration' ) . '</a>';

		/* translators: %s: The link to the Contact in CiviCRM */
		$note = sprintf( __( 'Created new CiviCRM Contact - %s', 'wpcv-woo-civi-integration' ), $contact_url );

		// Add Order note.
		$order->add_order_note( $note );

	}

	/**
	 * Adds a note to an Order when a Contact has been added or edited.
	 *
	 * @since 3.0
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function note_add_contact_updated( $contact, $order ) {

		// Get the link to the Contact in CiviCRM.
		$link = WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/contact/view', 'reset=1&cid=' . $contact['contact_id'] );
		$contact_url = '<a href="' . $link . '">' . __( 'View', 'wpcv-woo-civi-integration' ) . '</a>';

		/* translators: %s: The link to the Contact in CiviCRM */
		$note = sprintf( __( 'CiviCRM Contact Updated - %s', 'wpcv-woo-civi-integration' ), $contact_url );

		// Add Order note.
		$order->add_order_note( $note );

	}

	/**
	 * Adds a form field to set a Campaign.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_data_additions( $order ) {

		// TODO: Source and Campaign can hook in to the form directly.

		/**
		 * Fires before adding form elements to a WooCommerce Order.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Source::order_data_additions() (Priority: 10)
		 * * WPCV_Woo_Civi_Campaign::order_data_additions() (Priority: 20)
		 *
		 * @since 3.0
		 *
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/order/form/before', $order );

		$contact_id = WPCV_WCI()->contact->get_id_by_order( $order );
		if ( empty( $contact_id ) ) {
			return;
		}

		$link = WPCV_WCI()->helper->get_civi_admin_link( 'civicrm/contact/view', 'reset=1&cid=' . $contact_id );

		?>
		<div class="form-field form-field-wide wc-civicrmcontact">
			<h3><a href="<?php echo $link; ?>" target="_blank"><?php esc_html_e( 'View Contact in CiviCRM', 'wpcv-woo-civi-integration' ); ?></a></h3>
		</div>
		<?php

		/**
		 * Fires after adding form elements to a WooCommerce Order.
		 *
		 * @since 3.0
		 *
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/order/form/after', $order );

	}

}
