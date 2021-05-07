<?php
/**
 * Tax class.
 *
 * Handles integration of Tax when CiviCAmpaing is enabled.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Urchin Tracking Module class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Tax {

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

		// Bail if Tax is not enabled in WooCommerce.
		if ( ! wc_tax_enabled() ) {
			return;
		}

		// Bail if Tax is not enabled in CiviCRM.
		if ( ! $this->is_tax_enabled() ) {
			return;
		}

		// FIXME: Check if CiviCRM has appropriate Financial Types.

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		// Modify params when an Order has a tax value.
		add_action( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'contribution_tax_add' ], 100, 2 );

		// Add Tax to Line Item.
		add_action( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_tax_add' ], 10, 4 );

	}

	/**
	 * Gets the CiviCRM "Enable Tax and Invoicing" setting.
	 *
	 * @since 3.0
	 *
	 * @return bool $enabled True if enabled, false otherwise.
	 */
	public function is_tax_enabled() {

		// Return early if already found.
		static $tax_enabled;
		if ( isset( $tax_enabled ) ) {
			return $tax_enabled;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name' => 'invoicing',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch "Enable Tax and Invoicing" setting.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $e->getMessage(),
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		$tax_enabled = false;

		if ( ! empty( $result ) ) {
			$tax_enabled = $result;
		}

		return $tax_enabled;

	}

	/**
	 * Filters the Order params to add the Tax.
	 *
	 * Previously, the plugin used to override Financial Type. This has been
	 * disabled until it's clear how Financial Types should be applied.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function contribution_tax_add( $params, $order ) {

		// Return early if the Order has no Tax.
		$total_tax = $order->get_total_tax();
		if ( 0 === $total_tax ) {
			return $params;
		}

		// Ensure number format is CiviCRM-compliant.
		$params['tax_amount'] = WPCV_WCI()->helper->get_civicrm_float( $total_tax );

		/*
		 * Some notes on overriding the Financial Type.
		 *
		 * It should be flawed logic to override the Financial Type in this way
		 * because each Product should have its own Financial Type set correctly.
		 *
		 * Moreover, we would need cascading Financial Type settings for each
		 * Entity Type that can be created via the CiviCRM Order API.
		 *
		 * What actually happens is:
		 *
		 * Everything works nicely with purely taxable and purely non-taxable Orders.
		 * With a mix of taxable and non-taxable in the same Order:
		 *
		 * - If the Contribution's "financial_type_id" has a "Sales Tax Account",
		 * CiviCRM assume all Line Items are taxable and adds tax to those that
		 * are not taxable, then updates the Contribution's total_amount and
		 * considers a full Payment not to cover the total.
		 *
		 * - If the Contribution's "financial_type_id" does NOT have a "Sales Tax
		 * Account", CiviCRM assumes all Line Items are NOT taxable and creates
		 * an incorrect total_amount for the Contribution when the Order is created.
		 *
		 * So, at present, DO NOT have a mix of taxable and non-taxable Products
		 * in WooCommerce or bad things will happen.
		 */
		return $params;

		/*
		// Get the default Tax/VAT Financial Type.
		$default_financial_type_vat_id = get_option( 'woocommerce_civicrm_financial_type_vat_id' );

		// Needs to be defined in Settings.
		if ( empty( $default_financial_type_vat_id ) ) {

			// Write message to CiviCRM log.
			$message = __( 'There must be a default Tax/VAT Financial Type set.', 'wpcv-woo-civi-integration' );
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
		*/

	}

	/**
	 * Filters a Line Item to add Tax/VAT.
	 *
	 * @since 3.0
	 *
	 * @param array $line_item The array of Line Item data.
	 * @param object $item The WooCommerce Item object.
	 * @param object $product The WooCommerce Product object.
	 * @param array $params The params to be passed to the CiviCRM API.
	 */
	public function line_item_tax_add( $line_item, $item, $product, $params ) {

		// Bail if Product is not taxable.
		if ( ! $product->is_taxable() ) {
			return $line_item;
		}

		// Grab the Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		$line_item_data['tax_amount'] = WPCV_WCI()->helper->get_civicrm_float( $item->get_total_tax() );

		// Apply Tax to Line Item.
		$line_item = [
			'line_item' => [
				$line_item_data,
			],
		];

		return $line_item;

	}

}
