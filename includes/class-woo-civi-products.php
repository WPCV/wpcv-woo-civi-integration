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
	 * WooCommerce Product meta key holding the CiviCRM Financial Type ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_financial_type_id';

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
		add_filter( 'wpcv_woo_civi/order/create/params', [ $this, 'items_get_for_order' ], 30, 2 );
		add_filter( 'wpcv_woo_civi/order/create/params', [ $this, 'shipping_get_for_order' ], 40, 2 );

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
		return (int) $financial_type_id;
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
		update_post_meta( $product_id, $this->meta_key, (int) $financial_type_id );
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

		// Add note.
		$params['note'] = $this->note_generate( $items );

		// Init Line Items.
		$params['line_items'] = [];

		// Filter the params and add Line Items.
		$params = $this->items_build_for_order( $params, $order, $items );

		return $params;

	}

	/**
	 * Gets the Line Items for an Order.
	 *
	 * @see https://docs.civicrm.org/dev/en/latest/financial/orderAPI/#step-1
	 *
	 * @since 2.2 Line Items added to CiviCRM Contribution.
	 * @since 3.0 Logic moved to this method
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

		$default_price_set_data = $this->get_default_price_set_data();
		if ( empty( $default_price_set_data ) ) {
			return $params;
		}

		$decimal_separator = WPCV_WCI()->helper->get_decimal_separator();
		$thousand_separator = WPCV_WCI()->helper->get_thousand_separator();
		if ( $decimal_separator === false || $thousand_separator === false ) {
			return $params;
		}

		$financial_types = [];

		foreach ( $items as $item ) {

			$product = $item->get_product();

			$product_financial_type_id = $product->get_meta( $this->meta_key );
			if ( 'exclude' === $product_financial_type_id ) {
				continue;
			}

			if ( empty( $product_financial_type_id ) ) {
				$product_financial_type_id = $default_financial_type_id;
			}

			if ( 0 === $item['qty'] ) {
				$item['qty'] = 1;
			}

			$line_item_data = [
				'price_field_id' => $default_price_set_data['price_field']['id'],
				'qty' => $item['qty'],
				'line_total' => number_format( $item['line_total'], 2, $decimal_separator, $thousand_separator ),
				'unit_price' => number_format( $item['line_total'] / $item['qty'], 2, $decimal_separator, $thousand_separator ),
				'label' => $item['name'],
				'financial_type_id' => $product_financial_type_id,
			];

			// Construct Line Item.
			$line_item = [
				'params' => [],
				'line_item' => [ $line_item_data ],
			];

			/**
			 * Filter the Line Item.
			 *
			 * Used internally by:
			 *
			 * * WPCV_Woo_Civi_Membership::line_item_filter() (Priority: 10)
			 *
			 * @since 3.0
			 *
			 * @param array $line_item The array of Line Item data.
			 * @param object $product The WooCommerce Product object.
			 * @param array $params The params as presently constructed.
			 */
			$line_item = apply_filters( 'wpcv_woo_civi/products/line_item', $line_item, $product, $params );

			$params['line_items'][ $item->get_id() ] = $line_item;

			// FIXME: Override the Financial Type?

			/*
			 * Decide if we want to override the Financial Type with the one from
			 * the Membership Type instead of Product/default.
			 */

			$financial_types[ $product_financial_type_id ] = $product_financial_type_id;

		}

		// Maybe override the Contribution's Financial Type.
		if ( 1 === count( $financial_types ) ) {
			$params['financial_type_id'] = $product_financial_type_id;
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

		$default_price_set_data = $this->get_default_price_set_data();
		if ( empty( $default_price_set_data ) ) {
			return $params;
		}

		$decimal_separator = WPCV_WCI()->helper->get_decimal_separator();
		$thousand_separator = WPCV_WCI()->helper->get_thousand_separator();
		if ( $decimal_separator === false || $thousand_separator === false ) {
			return $params;
		}

		// Grab Shipping cost and sanity check.
		$shipping_cost = $order->get_total_shipping();
		if ( empty( $shipping_cost ) ) {
			$shipping_cost = 0;
		}

		// Ensure number format is CiviCRM-compliant.
		$shipping_cost = number_format( $shipping_cost, 2, $decimal_separator, $thousand_separator );
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
					'price_field_id' => $default_price_set_data['price_field']['id'],
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
	 * Get the Contribution amount data from default Price Set.
	 *
	 * Values retrieved are: price set, price_field, and price field value.
	 *
	 * @since 2.4
	 *
	 * @return array $default_price_set_data The default Price Set data.
	 */
	public function get_default_price_set_data() {

		static $default_price_set_data;
		if ( isset( $default_price_set_data ) ) {
			return $default_price_set_data;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		$params = [
			'name' => 'default_contribution_amount',
			'is_reserved' => true,
			'api.PriceField.getsingle' => [
				'price_set_id' => "\$value.id",
				'options' => [
					'limit' => 1,
					'sort' => 'id ASC',
				],
			],
		];

		try {

			$price_set = civicrm_api3( 'PriceSet', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve default Price Set', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return [];

		}

		$price_field = $price_set['api.PriceField.getsingle'];
		unset( $price_set['api.PriceField.getsingle'] );

		$default_price_set_data = [
			'price_set' => $price_set,
			'price_field' => $price_field,
		];

		return $default_price_set_data;

	}

	/**
	 * Create string to insert for Purchase Activity Details.
	 *
	 * @since 2.0
	 *
	 * @param object $items The Order object.
	 * @return string $str The Purchase Activity Details.
	 */
	public function note_generate( $items ) {

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

}
