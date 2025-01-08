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
	 * CiviCRM Contribution component status.
	 *
	 * True if the CiviContribute component is active, false by default.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $active The status of the CiviContribute component.
	 */
	public $active = false;

	/**
	 * WooCommerce Order meta key holding the CiviCRM Contribution ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $meta_key The WooCommerce Order meta key.
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

		// Bail early if the CiviContribute component is not active.
		$this->active = WPCV_WCI()->helper->is_component_enabled( 'CiviContribute' );
		if ( ! $this->active ) {
			return;
		}

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {
		// None as yet.
	}

	/**
	 * Unregister hooks.
	 *
	 * If any hooks are added, this needs to be called when initialising the
	 * Migrate class.
	 *
	 * @since 3.0
	 */
	public function unregister_hooks() {

	}

	/**
	 * Gets the CiviCRM Contribution ID from WooCommerce Order meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @return integer|bool $contribution_id The numeric ID of the CiviCRM Contribution, false otherwise.
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
	 * @param integer $order_id The Order ID.
	 * @param integer $contribution_id The numeric ID of the CiviCRM Contribution.
	 */
	public function set_order_meta( $order_id, $contribution_id ) {
		update_post_meta( $order_id, $this->meta_key, $contribution_id );
	}

	/**
	 * Gets a CiviCRM Contribution by its ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $contribution_id The numeric ID of the CiviCRM Contribution.
	 * @return array $result The CiviCRM Contribution data, or empty on failure.
	 */
	public function get_by_id( $contribution_id ) {

		$contribution = [];

		// Bail if we have no Contribution ID.
		if ( empty( $contribution_id ) ) {
			return $contribution;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $contribution;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id'      => $contribution_id,
		];

		// Get Contribution details via API.
		$result = civicrm_api( 'Contribution', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Error trying to find Contribution by ID', 'wpcv-woo-civi-integration' ) );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return $contribution;

		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contribution;
		}

		// The result set should contain only one item.
		$contribution = array_pop( $result['values'] );

		return $contribution;

	}

	/**
	 * Gets the CiviCRM Contribution data for a given WooCommerce Order ID.
	 *
	 * @since 2.2
	 *
	 * @param integer $order_id The numeric ID of the WooCommerce Order.
	 * @return array $contribution The CiviCRM Contribution data, or empty on failure.
	 */
	public function get_by_order_id( $order_id ) {

		$contribution = [];

		// Try the Order meta first.
		$contribution_id = $this->get_order_meta( $order_id );
		if ( ! empty( $contribution_id ) ) {
			$contribution = $this->get_by_id( $contribution_id );
		}

		// Fall back to the old method.
		if ( empty( $contribution ) ) {
			$invoice_id   = $this->get_invoice_id( $order_id );
			$contribution = $this->get_by_invoice_id( $invoice_id );
		}

		return $contribution;

	}

	/**
	 * Gets the Invoice ID aka the Order Number.
	 *
	 * @since 2.2
	 *
	 * @param integer $post_id The WordPress Post ID.
	 * @return string $invoice_id The Invoice ID.
	 */
	public function get_invoice_id( $post_id ) {

		$invoice_no = get_post_meta( $post_id, '_order_number', true );
		$invoice_id = ! empty( $invoice_no ) ? $invoice_no : $post_id . '_woocommerce';

		return $invoice_id;

	}

	/**
	 * Gets the CiviCRM Contribution associated with a WooCommerce Order Number.
	 *
	 * It's okay not to find a Contribution, so use "get" instead of "getsingle"
	 * and only log when there's a genuine API error.
	 *
	 * @since 3.0
	 *
	 * @param string $invoice_id The Invoice ID.
	 * @return array $contribution The CiviCRM Contribution data, or empty on failure.
	 */
	public function get_by_invoice_id( $invoice_id ) {

		$contribution = [];

		// Bail if we have no Invoice ID.
		if ( empty( $invoice_id ) ) {
			return $contribution;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $contribution;
		}

		// Construct API query.
		$params = [
			'version'    => 3,
			'invoice_id' => $invoice_id,
		];

		/**
		 * Filter the Contribution params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/contribution/get_by_invoice_id/params', $params );

		// Get Contribution details via API.
		$result = civicrm_api( 'Contribution', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Error try to find Contribution by Invoice ID', 'wpcv-woo-civi-integration' ) );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return $contribution;

		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contribution;
		}

		// The result set should contain only one item.
		$contribution = array_pop( $result['values'] );

		return $contribution;

	}

	/**
	 * Unsets the amounts in a Contribution record to prevent recalculation.
	 *
	 * @since 3.0
	 *
	 * @param array $contribution The existing array of Contribution data.
	 * @return array $contribution The modified array of Contribution data.
	 */
	public function unset_amounts( $contribution ) {

		// Unset Contribution amounts to prevent recalculation.
		if ( ! empty( $contribution['total_amount'] ) ) {
			unset( $contribution['total_amount'] );
		}
		if ( ! empty( $contribution['fee_amount'] ) ) {
			unset( $contribution['fee_amount'] );
		}
		if ( ! empty( $contribution['net_amount'] ) ) {
			unset( $contribution['net_amount'] );
		}
		if ( ! empty( $contribution['non_deductible_amount'] ) ) {
			unset( $contribution['non_deductible_amount'] );
		}

		return $contribution;

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
			'version' => 3,
			'debug'   => 1,
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
		$result = civicrm_api( 'Contribution', 'create', $params );

		// Sanity check.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Error when creating/updating a Contribution', 'wpcv-woo-civi-integration' ) );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'       => __METHOD__,
				'contribution' => $contribution,
				'params'       => $params,
				'result'       => $result,
				'backtrace'    => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return false;

		}

		// Init as empty.
		$contribution_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$contribution_data = array_pop( $result['values'] );
		}

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
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'       => __METHOD__,
				'message'      => __( 'A numeric ID must be present to update a Contribution.', 'wpcv-woo-civi-integration' ),
				'contribution' => $contribution,
				'backtrace'    => $trace,
			];
			WPCV_WCI()->log_error( $log );
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

			$result = civicrm_api3( 'Order', 'create', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = print_r( $e->getExtraParams(), true );

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create an Order via the CiviCRM Order API', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return false;

		}

		// Sanity check.
		if ( empty( $result['id'] ) || ! is_numeric( $result['id'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return false;
		}

		// Init as empty.
		$contribution = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$contribution = array_pop( $result['values'] );
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
		$payment_instrument_id     = WPCV_WCI()->helper->payment_instrument_map( $order->get_payment_method() );

		// Build a unique Transaction ID.
		$trxn_id    = $order->get_order_key() . '_' . $order_id;
		$invoice_id = $this->get_invoice_id( $order_id );

		// Get dates. These are already adjusted for timezone.
		$date_created = $order->get_date_created();
		$date_paid    = $order->get_date_paid();

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
			'contact_id'             => $contact_id,
			'financial_type_id'      => $default_financial_type_id,
			'payment_instrument_id'  => $payment_instrument_id,
			'trxn_id'                => $trxn_id,
			'invoice_id'             => $invoice_id,
			'receive_date'           => $receive_date,
			'contribution_status_id' => 'Pending',
		];

		// Maybe assign "Pay Later" based on Payment Method.
		$pay_later_methods = get_option( 'woocommerce_civicrm_pay_later_gateways', [] );
		if ( in_array( $order->get_payment_method(), $pay_later_methods, true ) ) {
			$params['is_pay_later'] = 1;
		}

		/*
		 * From the CiviCRM Order API docs:
		 *
		 * If you provide a value to "total_amount", it must equal the sum of
		 * all the "line_total" values.
		 *
		 * Before 5.20 there was a bug that required the top-level "total_amount"
		 * to be provided, but from 5.20 onward you can omit this and it will be
		 * calculated automatically from the sum of the "line_items".
		 *
		 * @see https://docs.civicrm.org/dev/en/latest/financial/orderAPI/#step-1
		 *
		 * Since WooCommerce has already calculated this (including taxes), we can
		 * maintain compatibility with versions of CiviCRM prior to 5.20 by adding
		 * the CiviCRM-compliant total now.
		 *
		 * However, until the following issue is fixed, there is not much point
		 * in sending the Total Amount to CiviCRM because it is recalculated on
		 * submission and may fail because of this.
		 *
		 * @see https://lab.civicrm.org/dev/financial/-/issues/189
		 */
		// $params['total_amount'] = $order->get_total();

		/**
		 * Filter the Order params before calling the CiviCRM API.
		 *
		 * This filter is central to the way in which this plugin builds the Order.
		 * The internal callbacks handle all the different tasks that are needed
		 * to populate the CiviCRM Order API params.
		 *
		 * Used internally by:
		 *
		 * - WPCV_Woo_Civi_Source::source_get_for_order() (Priority: 10)
		 * - WPCV_Woo_Civi_Campaign::campaign_get_for_order() (Priority: 20)
		 * - WPCV_Woo_Civi_Products::items_get_for_order() (Priority: 30)
		 * - WPCV_Woo_Civi_Products::shipping_get_for_order() (Priority: 40)
		 * - WPCV_Woo_Civi_Tax::contribution_tax_add() (Priority: 100)
		 *
		 * @since 2.0
		 *
		 * @param array  $params The params to be passed to the CiviCRM API.
		 * @param object $order The WooCommerce Order object.
		 */
		$params = apply_filters( 'wpcv_woo_civi/contribution/create_from_order/params', $params, $order );

		/*
		 * Do not create the Order if there are no Line Items.
		 *
		 * If this happens, it is because the Order consists entirely of Products
		 * that are set NOT to sync with CiviCRM. In this case, there's no point
		 * in creating the Order.
		 */
		if ( empty( $params['line_items'] ) ) {
			return false;
		}

		// Go ahead.
		$contribution = $this->order_create( $params );
		if ( false === $contribution ) {
			return false;
		}

		// Save Contribution ID in the Order meta.
		$this->set_order_meta( $order_id, $contribution['id'] );

		/**
		 * Broadcast that a Contribution has been created.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Order::note_add_contribution_created() (Priority: 10)
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
	 * Creates a Payment against a Contribution.
	 *
	 * This is the preferred flow according to comments in the code:
	 *
	 * Calling Payment.create to add payments & having it call "completetransaction"
	 * and/or "Contribution.create" to update related entities is the preferred flow.
	 *
	 * @see CRM_Contribute_BAO_Contribution::add()
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param object  $order The Order object.
	 * @return array|bool $payment_data The array of Payment data on success, false otherwise.
	 */
	public function payment_create( $order_id, $order ) {

		// Get Contribution.
		$contribution = $this->get_by_order_id( $order_id );
		if ( empty( $contribution ) ) {
			return false;
		}

		// Bail early if the Order is 'free' (0 amount) and 0 amount setting is enabled.
		$ignore             = get_option( 'woocommerce_civicrm_ignore_0_amount_orders', false );
		$ignore_zero_orders = WPCV_WCI()->helper->check_yes_no_value( $ignore );
		if ( $ignore_zero_orders && $order->get_total() === 0 ) {
			return false;
		}

		// Build a unique Transaction ID.
		$trxn_id        = $order->get_order_key() . '_' . $order_id;
		$transaction_id = $order->get_transaction_id();
		if ( ! empty( $transaction_id ) ) {
			// If the Order has a Transaction ID, use it.
			$trxn_id = $transaction_id;
		}

		$params = [
			'contribution_id'       => $contribution['id'],
			'total_amount'          => $order->get_total(),
			'trxn_date'             => $order->get_date_paid()->date( 'Y-m-d H:i:s' ),
			'trxn_id'               => $trxn_id,
			'payment_instrument_id' => WPCV_WCI()->helper->payment_instrument_map( $order->get_payment_method() ),
		];

		// Assume that there are no synced Line Items.
		$params['has_synced_line_items'] = false;

		/**
		 * Filter the Payment params before calling the CiviCRM API.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Products::payment_total_filter() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param array  $params The params to be passed to the CiviCRM API.
		 * @param object $order The WooCommerce Order object.
		 * @param array  $contribution The CiviCRM Contribution data.
		 */
		$params = apply_filters( 'wpcv_woo_civi/contribution/payment_create/params', $params, $order, $contribution );

		/*
		 * Maybe skip creating the Payment if the Total Amount is 0.
		 *
		 * If this the Total Amount is 0 at this point, it is not because the Order
		 * has a total of 0 but because:
		 *
		 * * The Order consists entirely of Products that are set NOT to sync to CiviCRM.
		 * * The Order consists entirely of free Products that should sync to CiviCRM.
		 * * A combination of the above.
		 *
		 * This should not really be necessary to check because a CiviCRM Order should
		 * not have been created for this Order - and therefore a Contribution for this
		 * Order should not have been found by get_by_order_id() above.
		 */
		if ( 0 === (float) $params['total_amount'] ) {

			// Bail when there are no Products that should sync to CiviCRM.
			if ( false === $params['has_synced_line_items'] ) {
				return false;
			}

		}

		// Clean up params array.
		unset( $params['has_synced_line_items'] );

		try {

			$result = civicrm_api3( 'Payment', 'create', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = print_r( $e->getExtraParams(), true );

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create Payment record.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return false;

		}

		// Init as empty.
		$payment_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$payment_data = array_pop( $result['values'] );
		}

		/**
		 * Broadcast that a Payment has been created in CiviCRM.
		 *
		 * @since 3.0
		 *
		 * @param array  $payment_data The array of Payment data from the CiviCRM API.
		 * @param object $order The WooCommerce Order object.
		 * @param array  $contribution The CiviCRM Contribution data.
		 */
		do_action( 'wpcv_woo_civi/contribution/payment_created', $payment_data, $order, $contribution );

		// Temporarily fix the Payment.
		$this->payment_fix( $payment_data, $order );

		return $payment_data;

	}

	/**
	 * Fixes a Payment.
	 *
	 * This fix is needed because the CiviCRM API assigns the value of the
	 * "Accounts Receivable Account is" Financial Account to the Financial Trxn
	 * entry that the Payment API creates. We need to undo what the API has done
	 * until the API is fixed.
	 *
	 * @since 3.0
	 *
	 * @param array  $payment_data The array of Payment data from the CiviCRM API.
	 * @param object $order The WooCommerce Order object.
	 */
	public function payment_fix( $payment_data, $order ) {

		// Skip if "Pay Later" Payment Method.
		$pay_later_methods = get_option( 'woocommerce_civicrm_pay_later_gateways', [] );
		if ( in_array( $order->get_payment_method(), $pay_later_methods, true ) ) {
			return;
		}

		// Build params to get the full data for the Payment.
		$params = [
			'version' => 3,
			'id'      => (int) $payment_data['id'],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'FinancialTrxn', 'get', $params );

		// Log and bail if something went wrong.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return;
		}

		// Use the full set of Payment data.
		$params = array_pop( $result['values'] );

		// Set the "From Account" to NULL. Yes, this is possible!
		$params['from_financial_account_id'] = '';
		$params['version']                   = 3;

		// Okay, now update the Payment.
		$result = civicrm_api( 'FinancialTrxn', 'create', $params );

		// Log if something went wrong.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
		}

	}

	/**
	 * Adds a Note to a Contribution.
	 *
	 * @since 3.0
	 *
	 * @param array  $contribution The array of CiviCRM Contribution data.
	 * @param string $note The text to add as a Note.
	 * @return array|bool The Note data on success, otherwise false.
	 */
	public function note_add( $contribution, $note ) {

		// Bail if we have no Contribution ID.
		if ( empty( $contribution['id'] ) ) {
			return false;
		}

		$params = [
			'entity_table' => 'civicrm_contribute',
			'entity_id'    => $contribution['id'],
			'contact_id'   => $contribution['contact_id'],
			'note'         => $note,
		];

		try {

			$result = civicrm_api3( 'Note', 'create', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = print_r( $e->getExtraParams(), true );

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create a Note for a Contribution.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return false;

		}

		// Init as empty.
		$note_data = [];

		// The result set should contain only one item.
		if ( ! empty( $result['values'] ) ) {
			$note_data = array_pop( $result['values'] );
		}

		return $note_data;

	}

	/**
	 * Updates a Contribution Status via the CiviCRM API.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param object  $order The Order object.
	 * @param string  $new_status_id The numeric ID of the new Status.
	 * @return bool True on success, otherwise false.
	 */
	public function status_update( $order_id, $order, $new_status_id ) {

		// Get Contribution.
		$contribution = $this->get_by_order_id( $order_id );
		if ( empty( $contribution ) ) {
			return false;
		}

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		/*
		// Overwrite the Contribution "Receive Date".
		if ( $order->is_paid() ) {
			$date_paid = $order->get_date_paid();
			if ( ! empty( $date_paid ) ) {
				$contribution['receive_date'] = $date_paid->date( 'Y-m-d H:i:s' );
			}
		}
		*/

		// Remove financial data to prevent recalculation.
		$contribution = $this->unset_amounts( $contribution );

		// Overwrite the Contribution Status.
		$contribution['contribution_status_id'] = $new_status_id;

		// Update Contribution.
		$contribution = $this->update( $contribution );
		if ( empty( $contribution ) ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contribution Status', 'wpcv-woo-civi-integration' ) );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'       => __METHOD__,
				'contribution' => $contribution,
				'result'       => $result,
				'backtrace'    => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return false;

		}

		/**
		 * Broadcast that a Contribution Status has been updated.
		 *
		 * @since 3.0
		 *
		 * @param array $contribution The CiviCRM Contribution data.
		 * @param integer $order_id The WooCommerce Order ID.
		 * @param object $order The WooCommerce Order object.
		 * @param string $new_status_id The numeric ID of the new Status.
		 */
		do_action( 'wpcv_woo_civi/contribution/status_update', $contribution, $order_id, $order, $new_status_id );

		// Success.
		return $contribution;

	}

	/**
	 * Get the CiviCRM Contribution Status ID for a given WooCommerce Order Status.
	 *
	 * @since 2.0
	 *
	 * @param string $order_status The WooCommerce Order Status.
	 * @return integer $id The numeric ID of the CiviCRM Contribution Status.
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
			'completed'     => 1,
			'pending'       => 2,
			'cancelled'     => 3,
			'failed'        => 4,
			'processing'    => 2,
			'on-hold'       => 2,
			'refunded'      => 7,
		];

		/**
		 * Filter the status mapping array.
		 *
		 * @since 3.0
		 *
		 * @param array  $map The array of mapped statuses.
		 * @param string $order_status The WooCommerce Order Status.
		 */
		$map = apply_filters( 'wpcv_woo_civi/contribution/status_map', $map, $order_status );

		if ( array_key_exists( $order_status, $map ) ) {
			$id = $map[ $order_status ];
		} else {
			// Oh no.
			$id = 1;
		}

		return $id;

	}

}
