<?php
/**
 * Contribution class.
 *
 * Handles Contribution functionality.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Orders class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Contribution {

	/**
	 * WooCommerce Order meta key holding the CiviCRM Contribution ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_contribution_id';

	/**
	 * Class constructor.
	 *
	 * @since 3.0
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
	 * @since 3.0
	 */
	public function register_hooks() {

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
	 * Creates or updates a Contribution record.
	 *
	 * @since 3.0
	 *
	 * @param array $contribution The array of Contribution data.
	 * @return array|bool $contribution_data The returned array of Contribution data
	 *                                       on success, false otherwise.
	 */
	public function create( $contribution ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Maybe debug?
		$params = [
			'debug' => 1,
		] + $contribution;

		/*
		 * Minimum array to create a Contribution:
		 *
		 * $params = [
		 *   'financial_type_id' => 4,
		 *   'receive_date' => "Y-m-d H:i:s",
		 *   'total_amount' => "123.45",
		 *   'contact_id' => 123,
		 * ];
		 *
		 * Updates are triggered by:
		 *
		 * $params['id'] = 255;
		 */
		$result = civicrm_api3( 'Contribution', 'create', $params );

		// Sanity check.
		if ( ! empty( $result['error'] ) ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Error when creating/updating a Contribution', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'contribution' => $contribution,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		// Init as empty.
		$contribution_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$contribution_data = array_pop( $result['values'] );
		}

		// --<
		return $contribution_data;

	}

	/**
	 * Update a Contribution record.
	 *
	 * @since 3.0
	 *
	 * @param array $contribution The array of Contribution data.
	 * @return array|bool $contribution_data The returned array of Contribution data
	 *                                       on success, false otherwise.
	 */
	public function update( $contribution ) {

		// Log and bail if there's no Contact ID.
		if ( empty( $contribution['id'] ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numeric ID must be present to update a Contribution.', 'wpcv-woo-civi-integration' ),
				'contribution' => $contribution,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Pass through.
		return $this->create( $contribution );

	}

	/**
	 * Creates a Contribution record via the CiviCRM Order API.
	 *
	 * @since 3.0
	 *
	 * @param array $params The array of CiviCRM Order data.
	 * @return array|bool $order_data The returned array of Contribution data on success, false otherwise.
	 */
	public function order_create( $params ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		try {

			$contribution = civicrm_api3( 'Order', 'create', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log and continue.
			CRM_Core_Error::debug_log_message( __( 'Unable to create a Contribution via the CiviCRM Order API', 'wpcv-woo-civi-integration' ) );
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

		return $contribution;

	}

	/**
	 * Add a Contribution record via the CiviCRM Order API.
	 *
	 * @since 2.0
	 *
	 * @param WC_Order $order The Order object.
	 * @return bool True on success, otherwise false.
	 */
	public function create_from_order( $order ) {

		// Get the Contact ID associated with this Order.
		$contact_id = WPCV_WCI()->contact->get_id_by_order( $order );
		if ( empty( $contact_id ) ) {
			return false;
		}

		$order_id = $order->get_id();

		// Get the default Financial Type & Payment Method.
		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		$payment_instrument_id = WPCV_WCI()->payment_instrument_map( $order->get_payment_method() );

		/* translators: %d: The numeric ID of the WooCommerce Order */
		$trxn_id = sprintf( __( 'WooCommerce Order - %d', 'wpcv-woo-civi-integration' ), (int) $order_id );
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );

		// Get dates. These are already adjusted for timezone.
		$date_created = $order->get_date_created();
		$date_paid = $order->get_date_paid();

		/*
		 * Prime the Contribution's "Receive Date" with the Order's "Date Created".
		 *
		 * The CiviCRM database schema has this to say about the Contribution's
		 * "Receive Date":
		 *
		 * "Date contribution was received - not necessarily the creation date of
		 * the record"
		 *
		 * Since a WooCommerce Order is likely to be created in an unpaid state,
		 * we default to the the Order's "Date Created" and overwrite with the
		 * "Date Paid" if that happens to be populated.
		 */
		$receive_date = $date_created->date( 'Y-m-d H:i:s' );
		if ( ! empty( $date_paid ) ) {
			$receive_date = $date_paid->date( 'Y-m-d H:i:s' );
		}

		// Init Order params.
		$params = [
			'contact_id' => $contact_id,
			'financial_type_id' => $default_financial_type_id,
			'payment_instrument_id' => $payment_instrument_id,
			'trxn_id' => $trxn_id,
			'invoice_id' => $invoice_id,
			'receive_date' => $receive_date,
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

		// Go ahead.
		$contribution = $this->order_create( $params );
		if ( $contribution === false ) {
			return false;
		}

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
		do_action( 'wpcv_woo_civi/contribution/create_from_order', $contribution, $order );

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
	public function status_map( $order_status ) {

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

}
