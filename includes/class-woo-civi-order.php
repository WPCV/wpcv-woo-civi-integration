<?php
/**
 * Order class.
 *
 * Handles the integration of WooCommerce Orders with CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Order class.
 *
 * @since 2.2
 */
class WPCV_Woo_Civi_Order {

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
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_details_add' ], 30 );

		// Add notes to an Order.
		add_action( 'wpcv_woo_civi/contribution/create_from_order', [ $this, 'note_add_contribution_created' ], 10, 2 );
		add_action( 'wpcv_woo_civi/contact/create_from_order', [ $this, 'note_add_contact_created' ], 10, 2 );
		add_action( 'wpcv_woo_civi/contact/update_from_order', [ $this, 'note_add_contact_updated' ], 10, 2 );

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

		// Bail if Order is 'free' (0 amount) and 0 amount setting is enabled.
		$ignore_zero_orders = WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_ignore_0_amount_orders', false ) );
		if ( $ignore_zero_orders && $order->get_total() === 0 ) {
			return false;
		}

		// Add the Contribution record.
		$contribution = WPCV_WCI()->contribution->create_from_order( $order );
		if ( $contribution === false ) {
			return false;
		}

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
		$old_status_id = WPCV_WCI()->contribution->status_map( $old_status );
		$new_status_id = WPCV_WCI()->contribution->status_map( $new_status );
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

		// Overwrite the Contribution Status.
		if ( $order->is_paid() ) {
			$contribution['contribution_status_id'] = 'Completed';
		} else {
			$contribution['contribution_status_id'] = $new_status_id;
		}

		// Overwrite the Contribution "Receive Date".
		if ( $order->is_paid() ) {
			$date_paid = $order->get_date_paid();
			if ( ! empty( $date_paid ) ) {
				$contribution['receive_date'] = $date_paid->date( 'Y-m-d H:i:s' );
			}
		}

		// Update Contribution.
		$contribution = WPCV_WCI()->contribution->update( $contribution );
		if ( empty( $contribution ) ) {

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
	 * Adds a note to an Order when a Contribution has been created.
	 *
	 * @since 3.0
	 *
	 * @param array $contribution The CiviCRM Contribution data.
	 * @param object $order The WooCommerce Order object.
	 */
	public function note_add_contribution_created( $contribution, $order ) {

		// Add an Order note with reference to the created Contribution.
		$link = WPCV_WCI()->helper->get_civi_admin_link(
			'civicrm/contact/view/contribution',
			'reset=1&id=' . $contribution['id'] . '&cid=' . $contribution['contact_id'] . '&action=view'
		);

		$note = sprintf(
			/* translators: %s: The View Contribution link */
			__( 'Contribution %s has been created in CiviCRM', 'wpcv-woo-civi-integration' ),
			'<a href="' . $link . '">' . $contribution['id'] . '</a>'
		);

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
	 * Adds a link to the CiviCRM Contact associated with an Order.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_details_add( $order ) {

		/**
		 * Fires before adding form elements to a WooCommerce Order.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Source::order_details_add() (Priority: 10)
		 * * WPCV_Woo_Civi_Campaign::order_details_add() (Priority: 20)
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
		<p class="form-field form-field-wide wc-civicrmcontact" style="margin: 1.5em 0 1em 0">
			<strong style="display: block;"><?php esc_html_e( 'CiviCRM Contact:', 'wpcv-woo-civi-integration' ); ?></strong> <a href="<?php echo $link; ?>" target="_blank"><?php esc_html_e( 'View Contact in CiviCRM', 'wpcv-woo-civi-integration' ); ?></a>
		</p>
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
