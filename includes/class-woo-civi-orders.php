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

		// Process changes in WooCommerce Orders.
		add_action( 'woocommerce_new_order', [ $this, 'order_new' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'order_processed' ], 10, 3 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'order_status_changed' ], 99, 4 );

		// Add CiviCRM options to Edit Order screen.
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_data_additions' ], 30 );

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

		// In dashbord context, "woocommerce_checkout_order_processed" is not called after a creation.
		$nonce = filter_input( INPUT_POST, 'woocommerce_civicrm_order_new', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'woocommerce_civicrm_order_new' ) ) {
			return;
		}

		// FIXME: Is this really necessary?
		$this->order_processed( $order_id, null, new WC_Order( $order_id ) );

		/**
		 * Broadcast that a new WooCommerce Order with CiviCRM data has been created.
		 *
		 * @since 3.0
		 *
		 * @param int $order_id The Order ID.
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/order/form/new', $order_id, $order );

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

		$contact_id = WPCV_WCI()->contact->civicrm_get_cid( $order );
		if ( false === $contact_id ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'wpcv-woo-civi-integration' ) );
			return;
		}

		$contact_id = WPCV_WCI()->contact->add_update_contact( $contact_id, $order );
		if ( false === $contact_id ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be found or created', 'wpcv-woo-civi-integration' ) );
			return;
		}

		// Add the Contribution record.
		$this->contribution_create( $contact_id, $order );

		/**
		 * Broadcast that a Contribution record has been added for a new WooCommerce Order.
		 *
		 * @since 3.0
		 *
		 * @param int $order_id The Order ID.
		 * @param object $order The Order object.
		 * @param int $contact_id The numeric ID of the CiviCRM Contact.
		 */
		do_action( 'wpcv_woo_civi/order/processed', $order_id, $order, $contact_id );

		return $order_id;

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

		$contact_id = WPCV_WCI()->contact->civicrm_get_cid( $order );
		if ( false === $contact_id ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'wpcv-woo-civi-integration' ) );
			return;
		}

		// Get Contribution.
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );
		$contribution = WPCV_WCI()->helper->get_contribution_by_invoice_id( $invoice_id );

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		// Overwrite a Contribution Status.
		if ( $order->is_paid() ) {
			$contribution['contribution_status_id'] = 'Completed';
		} else {
			$contribution['contribution_status_id'] = $this->contribution_status_map( $order->get_status() );
		}

		// Update Contribution.
		// FIXME: Won't update twice when Order is created:
		// "Cannot change contribution status from Pending to In Progress."
		// Execution never reaches line after API call.
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
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 * @param WC_Order $order The Order object.
	 * @return bool True on success, otherwise false.
	 */
	public function contribution_create( $contact_id, $order ) {

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

		try {

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
		update_post_meta( $order_id, '_woocommerce_civicrm_contribution_id', $contribution['id'] );

		/**
		 * Broadcast that a Contribution has been created.
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
	 * Adds a form field to set a Campaign.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_data_additions( $order ) {

		if ( $order->get_status() === 'auto-draft' ) {
			wp_nonce_field( 'woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new' );
		} else {
			wp_nonce_field( 'woocommerce_civicrm_order_edit', 'woocommerce_civicrm_order_edit' );
		}

		/**
		 * Fires before adding form elements to a WooCommerce Order.
		 *
		 * @since 3.0
		 *
		 * @param object $order The Order object.
		 */
		do_action( 'wpcv_woo_civi/order/form/before', $order );

		$contact_id = WPCV_WCI()->contact->civicrm_get_cid( $order );
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
