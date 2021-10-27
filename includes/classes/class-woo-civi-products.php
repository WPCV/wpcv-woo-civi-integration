<?php
/**
 * Products class.
 *
 * Manages Products and their integration as Line Items.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Products class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Products {

	/**
	 * WooCommerce Product meta key holding the CiviCRM Entity Type.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The CiviCRM Entity Type meta key.
	 */
	public $entity_key = '_woocommerce_civicrm_entity_type';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Financial Type ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The CiviCRM Financial Type ID meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_financial_type_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Contribution Price Field Value ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $pfv_key The CiviCRM Contribution Price Field Value ID meta key.
	 */
	public $pfv_key = '_woocommerce_civicrm_contribution_pfv_id';

	/**
	 * Hold the Purchase Activity Details when building Line Items.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $purchase_activity The array of Purchase Activity Details.
	 */
	public $purchase_activity = [];

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

		// Add Line Items to Order.
		add_filter( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'items_get_for_order' ], 30, 2 );
		add_filter( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'shipping_get_for_order' ], 40, 2 );

		// Filter the Payment total.
		add_filter( 'wpcv_woo_civi/contribution/payment_create/params', [ $this, 'payment_total_filter' ], 10, 3 );

		// Get Line Items for Payment.
		//add_filter( 'wpcv_woo_civi/contribution/payment_create/params', [ $this, 'items_get_for_payment' ], 10, 3 );

		// Get the Entity Type of a Custom Product Type.
		add_filter( 'wpcv_woo_civi/product/query/entity_type', [ $this, 'entity_type_get' ], 20, 2 );

		// Get the Financial Type ID from WooCommerce Product meta.
		add_filter( 'wpcv_woo_civi/product/query/financial_type_id', [ $this, 'financial_type_id_get' ], 10, 2 );

		// Get the Price Field Value ID from WooCommerce Product meta.
		add_filter( 'wpcv_woo_civi/product/query/pfv_id', [ $this, 'pfv_id_get' ], 20, 2 );

	}

	/**
	 * Gets the Entity Type from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param str $entity_type The possibly found Entity Type.
	 * @param int $product_id The Product ID.
	 * @return str $entity_type The found Entity Type, passed through otherwise.
	 */
	public function entity_type_get( $entity_type, $product_id ) {

		// Pass through if already found.
		if ( $entity_type !== '' ) {
			return $entity_type;
		}

		// Return the contents of the "global" Financial Type ID if found.
		$product_entity_type = get_post_meta( $product_id, $this->entity_key, true );
		if ( ! empty( $product_entity_type ) ) {
			return $product_entity_type;
		}

		// Not found.
		return '';

	}

	/**
	 * Gets the Financial Type ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $financial_type_id The possibly found Financial Type ID.
	 * @param int $product_id The Product ID.
	 * @return int $financial_type_id The found Financial Type ID, passed through otherwise.
	 */
	public function financial_type_id_get( $financial_type_id, $product_id ) {

		// Pass through if already found.
		if ( $financial_type_id !== 0 ) {
			return $financial_type_id;
		}

		// Return the contents of the "global" Financial Type ID if found.
		$product_financial_type_id = get_post_meta( $product_id, $this->meta_key, true );
		if ( ! empty( $product_financial_type_id ) ) {
			return $product_financial_type_id;
		}

		// Not found.
		return 0;

	}

	/**
	 * Gets the Price Field Value ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $pfv_id The possibly found Price Field Value ID.
	 * @param int $product_id The Product ID.
	 * @return int $pfv_id The found Price Field Value ID, passed through otherwise.
	 */
	public function pfv_id_get( $pfv_id, $product_id ) {

		// Pass through if already found.
		if ( $pfv_id !== 0 ) {
			return $pfv_id;
		}

		// Return the contents of the "global" Price Field Value ID if found.
		$product_pfv_id = get_post_meta( $product_id, $this->pfv_key, true );
		if ( ! empty( $product_pfv_id ) ) {
			return $product_pfv_id;
		}

		// Not found.
		return 0;

	}

	/**
	 * Gets the CiviCRM Entity Type from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return str|bool $entity_type The CiviCRM Entity Type, false otherwise.
	 */
	public function get_entity_meta( $product_id ) {
		$entity_type = get_post_meta( $product_id, $this->entity_key, true );
		return $entity_type;
	}

	/**
	 * Sets the CiviCRM Entity Type as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @param str $entity_type The CiviCRM Entity Type.
	 */
	public function set_entity_meta( $product_id, $entity_type ) {
		update_post_meta( $product_id, $this->entity_key, $entity_type );
	}

	/**
	 * Gets the Financial Type ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $financial_type_id The Financial Type ID, false otherwise.
	 */
	public function get_product_meta( $product_id ) {
		$financial_type_id = get_post_meta( $product_id, $this->meta_key, true );
		return $financial_type_id;
	}

	/**
	 * Sets the CiviCRM Financial Type ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @param int $financial_type_id The numeric ID of the Financial Type.
	 */
	public function set_product_meta( $product_id, $financial_type_id ) {
		update_post_meta( $product_id, $this->meta_key, $financial_type_id );
	}

	/**
	 * Gets the Contribution Price Field Value ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $contribution_pfv_id The Contribution Price Field Value ID, false otherwise.
	 */
	public function get_pfv_meta( $product_id ) {
		$contribution_pfv_id = get_post_meta( $product_id, $this->pfv_key, true );
		return $contribution_pfv_id;
	}

	/**
	 * Sets the CiviCRM Contribution Price Field Value ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @param int $contribution_pfv_id The numeric ID of the Contribution Price Field Value.
	 */
	public function set_pfv_meta( $product_id, $contribution_pfv_id ) {
		update_post_meta( $product_id, $this->pfv_key, $contribution_pfv_id );
	}

	/**
	 * Gets the Line Items for an Order.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function items_get_for_order( $params, $order ) {

		$items = $order->get_items();

		// Init Line Items for the CiviCRM Order API.
		$params['line_items'] = [];

		// Filter the params and add Line Items.
		$params = $this->items_build_for_order( $params, $order, $items );

		// Add note after Line Items have been parsed.
		$params['note'] = $this->note_generate();

		return $params;

	}

	/**
	 * Gets the Line Items for an Order.
	 *
	 * @see https://docs.civicrm.org/dev/en/latest/financial/orderAPI/#step-1
	 *
	 * @since 2.2 Line Items added to CiviCRM Contribution.
	 * @since 3.0 Logic moved to this method.
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @param array $items The array of Items in the Order.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function items_build_for_order( $params, $order, $items ) {

		// Bail if no Items.
		if ( empty( $items ) ) {
			return $params;
		}

		// Bail if there isn't a default Price Field ID set in CiviCRM.
		$default_price_field_id = $this->get_default_price_field_id();
		if ( empty( $default_price_field_id ) ) {
			return $params;
		}

		// Track unique Financial Types in the Line Items.
		$financial_types = [];

		foreach ( $items as $item_id => $item ) {

			$product = $item->get_product();
			$product_type = $product->get_type();

			// Skip/exclude if this Product should not be synced to CiviCRM.
			$product_entity_type = $product->get_meta( $this->entity_key );
			// Our Custom Product Types always sync.
			if ( ! array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				if ( '' === $product_entity_type || 'civicrm_exclude' === $product_entity_type ) {
					continue;
				}
			}

			// Skip if this Product still has the legacy "exclude" setting.
			$product_financial_type_id = $product->get_meta( $this->meta_key );
			if ( 'exclude' === $product_financial_type_id ) {
				continue;
			}

			$line_item_data = [
				'entity_table' => 'civicrm_contribution',
				'price_field_id' => $default_price_field_id,
				'unit_price' => WPCV_WCI()->helper->get_civicrm_float( $product->get_price() ),
				'qty' => $item->get_quantity(),
				// The "line_total" must equal the unit_price × qty.
				'line_total' => WPCV_WCI()->helper->get_civicrm_float( $item->get_total() ),
				'tax_amount' => WPCV_WCI()->helper->get_civicrm_float( $item->get_total_tax() ),
				'label' => $product->get_name(),
			];

			// Does this Product have a "global" Price Field Value ID?
			if ( ! empty( $product->get_meta( $this->pfv_key ) ) ) {
				$line_item_data['price_field_value_id'] = $product->get_meta( $this->pfv_key );
			}

			/*
			 * From the docs:
			 *
			 * The Line Item will inherit the "financial_type_id" from the contribution
			 * if it is not set.
			 *
			 * @see https://docs.civicrm.org/dev/en/latest/financial/orderAPI/
			 *
			 * This means that we only need to set this here if it's different to
			 * the one set on the Contribution itself.
			 *
			 * In practice, all Products should have an appropriate Financial Type
			 * set, otherwise bad things will happen.
			 */
			if ( ! empty( $product_financial_type_id ) ) {
				$line_item_data['financial_type_id'] = $product_financial_type_id;
			}

			/*
			 * Construct Line Item.
			 *
			 * We can safely prime all Line Items with a "params" array because the
			 * Order API checks whether it is empty rather than whether it is set.
			 */
			$line_item = [
				'params' => [],
				'line_item' => [ $line_item_data ],
			];

			/**
			 * Filter the Line Item.
			 *
			 * Used internally by:
			 *
			 * * WPCV_Woo_Civi_Tax::line_item_tax_add() (Priority: 10)
			 * * WPCV_Woo_Civi_Membership::line_item_filter() (Priority: 20)
			 * * WPCV_Woo_Civi_Participant::line_item_filter() (Priority: 30)
			 * * WPCV_Woo_Civi_Products_Custom::line_item_filter() (Priority: 50)
			 *
			 * @since 3.0
			 *
			 * @param array $line_item The array of Line Item data.
			 * @param object $item The WooCommerce Item object.
			 * @param object $product The WooCommerce Product object.
			 * @param object $order The WooCommerce Order object.
			 * @param array $params The params as presently constructed.
			 */
			$line_item = apply_filters( 'wpcv_woo_civi/products/line_item', $line_item, $item, $product, $order, $params );

			$params['line_items'][ $item_id ] = $line_item;

			// Store (or overwrite) entry in Financial Types array.
			if ( ! empty( $line_item['line_item'][0]['financial_type_id'] ) ) {
				$financial_type = $line_item['line_item'][0]['financial_type_id'];
				$financial_types[ $financial_type ] = $financial_type;
			} else {
				$financial_types[ $product_financial_type_id ] = $product_financial_type_id;
			}

			// Store the Purchase Activity Details.
			$this->purchase_activity[] = sprintf(
				__( '%1$s x %2$s', 'wpcv-woo-civi-integration' ),
				$product->get_name(),
				$item->get_quantity()
			);

		}

		/*
		 * When there is only one Financial Type for the Line Items - or there is
		 * only one Line Item:
		 *
		 * Override the Contribution's Financial Type because the item(s) may have
		 * been modified to become Membership(s) or Event Participant(s) and the
		 * "parent" Contribution Financial Type should reflect this.
		 *
		 * When there are multiple Line Items with different Financial Types, this
		 * from Rich Lott @artfulrobot:
		 *
		 * "Regarding the Contribution's Financial Type ID, you should omit the
		 * top level one. There was a bug about that (there may still be a bug around
		 * that) but if you have it in ALL your line items, that should do."
		 *
		 * If only that worked. I thought it sounded too good to be true. Doing that
		 * results in: "Mandatory key(s) missing from params array: financial_type_id"
		 * so let's set the default (from Settings) and see what happens.
		 */
		if ( 1 === count( $financial_types ) ) {
			$params['financial_type_id'] = array_pop( $financial_types );
		} else {
			$params['financial_type_id'] = get_option( 'woocommerce_civicrm_financial_type_id' );
		}

		return $params;

	}

	/**
	 * Gets the Shipping Line Item for an Order.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function shipping_get_for_order( $params, $order ) {

		$items = $order->get_items();

		// Bail if no Items.
		if ( empty( $items ) ) {
			return $params;
		}

		// Bail if there isn't a default Price Field ID set in CiviCRM.
		$default_price_field_id = $this->get_default_price_field_id();
		if ( empty( $default_price_field_id ) ) {
			return $params;
		}

		// Grab Shipping cost and sanity check.
		$shipping_cost = $order->get_total_shipping();
		if ( empty( $shipping_cost ) ) {
			$shipping_cost = 0;
		}

		// Ensure number format is CiviCRM-compliant.
		$shipping_cost = WPCV_WCI()->helper->get_civicrm_float( $shipping_cost );
		if ( ! ( floatval( $shipping_cost ) > 0 ) ) {
			return $params;
		}

		// Get the default Financial Type Shipping ID.
		$default_financial_type_shipping_id = get_option( 'woocommerce_civicrm_financial_type_shipping_id' );

		// Needs to be defined in Settings.
		if ( empty( $default_financial_type_shipping_id ) ) {

			// Write message to CiviCRM log.
			$message = __( 'There must be a default Shipping Financial Type set.', 'wpcv-woo-civi-integration' );
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

		/*
		 * Line item for shipping.
		 *
		 * Shouldn't it be added to it's corresponding Product/Line Item?
		 * i.e. an Order can have both shippable and downloadable Products?
		 */
		$params['line_items'][0] = [
			'line_item' => [
				[
					'price_field_id' => $default_price_field_id,
					'qty' => 1,
					'line_total' => $shipping_cost,
					'unit_price' => $shipping_cost,
					'label' => 'Shipping',
					'financial_type_id' => $default_financial_type_shipping_id,
				]
			],
		];

		return $params;

	}

	/**
	 * Get the default Contribution Price Field ID.
	 *
	 * @since 3.0
	 *
	 * @return array|bool $price_field_id The default Contribution Price Field ID, or false on failure.
	 */
	public function get_default_price_field_id() {

		static $price_field_id;
		if ( isset( $price_field_id ) ) {
			return $price_field_id;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'price_set_id' => 'default_contribution_amount',
			'options' => [
				'limit' => 1,
			],
		];

		try {

			$result = civicrm_api3( 'PriceField', 'get', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve default Contribution Price Field ID', 'wpcv-woo-civi-integration' ) );
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

		// Bail if something's amiss.
		if ( empty( $result['id'] ) ) {
			return false;
		}

		$price_field_id = $result['id'];

		return $price_field_id;

	}

	/**
	 * Create string to insert for Purchase Activity Details.
	 *
	 * @since 2.0
	 *
	 * @return string $str The Purchase Activity Details.
	 */
	public function note_generate() {

		$str = '';

		// Bail if empty.
		if ( empty( $this->purchase_activity ) ) {
			return $str;
		}

		// Make human-readable.
		$str =  implode( ', ', $this->purchase_activity );

		return $str;

	}

	/**
	 * Filters the Payment params before calling the CiviCRM API.
	 *
	 * Because there may be one or more Products in the Order that are not to be
	 * synced to CiviCRM, we need to filter the "total_amount" so that the costs
	 * of the Products that are not synced are deducted.
	 *
	 * @since 3.0
	 *
	 * @param array $params The params to be passed to the CiviCRM API.
	 * @param object $order The WooCommerce Order object.
	 * @param array $contribution The CiviCRM Contribution data.
	 */
	public function payment_total_filter( $params, $order, $contribution ) {

		// Try and get the Order Items.
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return $params;
		}

		// Init array holding the deductions to be made.
		$deductions = [];

		foreach ( $items as $item_id => $item ) {

			$product = $item->get_product();
			$product_type = $product->get_type();

			// Assume we should not deduct this Item because it syncs.
			$deduct = false;

			// Check if this Product should not be synced to CiviCRM.
			$product_entity_type = $product->get_meta( $this->entity_key );
			// Our Custom Product Types always sync.
			if ( ! array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				if ( '' === $product_entity_type || 'civicrm_exclude' === $product_entity_type ) {
					$deduct = true;
				}
			}

			// Check if this Product still has the legacy "exclude" setting.
			$product_financial_type_id = $product->get_meta( $this->meta_key );
			if ( 'exclude' === $product_financial_type_id ) {
				$deduct = true;
			}

			// Skip if we should not deduct this Item.
			if ( $deduct === false ) {
				continue;
			}

			// Add deductions.
			$item_total = $item->get_total();
			$item_tax = $item->get_total_tax();
			if ( ! empty( $item_total ) ) {
				$deductions[] = $item_total;
				if ( ! empty( $item_tax ) ) {
					$deductions[] = $item_tax;
				}
			}

		}

		// Do the deductions.
		if ( ! empty( $deductions ) ) {
			foreach ( $deductions as $deduction ) {
				$params['total_amount'] = (float) $params['total_amount'] - (float) $deduction;
			}
		}

		return $params;

	}

	/**
	 * Filter the Payment params before calling the CiviCRM API.
	 *
	 * Not used at present because there is no documentation on the Line Items
	 * and how to build the array to be passed to the Payment API.
	 *
	 * @since 3.0
	 *
	 * @param array $params The params to be passed to the CiviCRM API.
	 * @param object $order The WooCommerce Order object.
	 * @param array $contribution The CiviCRM Contribution data.
	 */
	public function items_get_for_payment( $params, $order, $contribution ) {

		// Try and get the Line Items.
		$items = $this->items_get_by_contribution_id( $contribution['id'] );
		if ( empty( $items ) ) {
			return $params;
		}

		/*
		 * Build Line Item data. For example:
		 *
		 * 'line_item' => [
		 *   '0' => [
		 *     '1' => 10,
		 *   ],
		 *   '1' => [
		 *     '2' => 40,
		 *   ],
		 * ],
		 */
		$items_data = [];
		$count = 0;
		foreach ( $items as $item ) {
			$line_item = [ (string) $count ];
			$count++;
			$line_item[ (string) $count ] = (float) $item['line_total'] + (float) $item['tax_amount'];
			$items_data[] = [ $line_item ];
		}

		$params['line_item'] = $items_data;

		return $params;

	}

	/**
	 * Gets the Line Items for a given Contribution ID.
	 *
	 * @since 3.0
	 *
	 * @param int $contribution_id The numeric ID of the CiviCRM Contribution.
	 * @return array $line_items The CiviCRM Line Item data, or empty on failure.
	 */
	public function items_get_by_contribution_id( $contribution_id ) {

		// Bail if we have no Contribution ID.
		if ( empty( $contribution_id ) ) {
			return [];
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'contribution_id' => $contribution_id,
		];

		// Get Line Item details via API.
		$result = civicrm_api( 'LineItem', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Error trying to find Line Items by Contribution ID', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return [];

		}

 		// The result set is what we want.
		$line_items = [];
		if ( ! empty( $result['values'] ) ) {
			$line_items = $result['values'];
		}

		return $line_items;

	}

}
