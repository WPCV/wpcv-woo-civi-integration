<?php
/**
 * Tax class.
 *
 * Handles integration of Tax when CiviCampaign is enabled.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Tax class.
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
		//add_action( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'contribution_tax_add' ], 100, 2 );

		// Add Tax to Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_tax_add' ], 10, 5 );

	}

	/**
	 * Gets the CiviCRM "Enable Tax and Invoicing" setting.
	 *
	 * @since 3.0
	 *
	 * @return bool $setting True if enabled, false otherwise.
	 */
	public function is_tax_enabled() {

		// Return early if already found.
		static $setting;
		if ( isset( $setting ) ) {
			return $setting;
		}

		$setting = false;
		$result = WPCV_WCI()->helper->get_civicrm_setting( 'invoicing' );
		if ( ! empty( $result ) ) {
			$setting = $result;
		}

		return $setting;

	}

	/**
	 * Filters the Order params to add the Tax.
	 *
	 * Previously, the plugin used to override Financial Type. This has been
	 * disabled until it's clear how Financial Types should be applied.
	 *
	 * @since 3.0
	 *
	 * @param array  $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function contribution_tax_add( $params, $order ) {

		// Return early if the Order has no Tax.
		$total_tax = $order->get_total_tax();
		if ( 0 === $total_tax ) {
			return $params;
		}

		// Tax is recalculated by the Order API.
		// TODO: Review when float issues are resolved.

		// Assign Tax to CiviCRM API params.
		$params['tax_amount'] = $total_tax;

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

	}

	/**
	 * Filters a Line Item to add Tax/VAT.
	 *
	 * @since 3.0
	 *
	 * @param array  $line_item The array of Line Item data.
	 * @param object $item The WooCommerce Item object.
	 * @param object $product The WooCommerce Product object.
	 * @param object $order The WooCommerce Order object.
	 * @param array  $params The params to be passed to the CiviCRM API.
	 * @return array $line_item The modified array of Line Item data.
	 */
	public function line_item_tax_add( $line_item, $item, $product, $order, $params ) {

		// Bail if Product is not taxable.
		if ( ! $product->is_taxable() ) {
			return $line_item;
		}

		// Preserve the Line Item params.
		$line_item_params = $line_item['params'];

		// Grab the Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		$line_item_data['tax_amount'] = $item->get_total_tax();

		// Apply Tax to Line Item.
		$line_item = [
			//'params' => $line_item_params,
			'line_item' => [
				$line_item_data,
			],
		];

		return $line_item;

	}

	/**
	 * Gets the CiviCRM Tax Rates.
	 *
	 * The array of Tax Rates has the form: [ <financial_type_id> => <tax_rate> ]
	 *
	 * @since 3.0
	 *
	 * @return array|bool The array of Tax Rates, or false on failure.
	 */
	public function rates_get() {

		// Return early if already found.
		static $tax_rates;
		if ( isset( $tax_rates ) ) {
			return $tax_rates;
		}

		// Init CiviCRM or bail.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'version' => 3,
			'return' => [
				'id',
				'entity_table',
				'entity_id',
				'account_relationship',
				'financial_account_id',
				'financial_account_id.financial_account_type_id',
				'financial_account_id.tax_rate',
			],
			'financial_account_id.is_active' => 1,
			'financial_account_id.is_tax' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'EntityFinancialAccount', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {

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

		// Return early if there's nothing to see.
		if ( 0 === (int) $result['count'] ) {
			return false;
		}

		// Build tax rates.
		$tax_rates = array_reduce(
			$result['values'],
			function( $tax_rates, $financial_account ) {
				$tax_rates[ $financial_account['entity_id'] ] = $financial_account['financial_account_id.tax_rate'];
				return $tax_rates;
			},
			[]
		);

		return $tax_rates;

	}

	/**
	 * Gets the CiviCRM "Tax Term" setting.
	 *
	 * @since 3.0
	 *
	 * @return string $setting The Tax Term, empty otherwise.
	 */
	public function term_get() {

		// Return early if already found.
		static $setting;
		if ( isset( $setting ) ) {
			return $setting;
		}

		$setting = '';
		$result = WPCV_WCI()->helper->get_civicrm_setting( 'tax_term' );
		if ( ! empty( $result ) ) {
			$setting = $result;
		}

		return $setting;

	}

	/**
	 * Gets the CiviCRM "Tax Display Settings" setting.
	 *
	 * @since 3.0
	 *
	 * @return string $tax_display_settings The Tax Display Settings, empty otherwise.
	 */
	public function display_settings_get() {

		// Return early if already found.
		static $setting;
		if ( isset( $setting ) ) {
			return $setting;
		}

		$setting = '';
		$result = WPCV_WCI()->helper->get_civicrm_setting( 'tax_display_settings' );
		if ( ! empty( $result ) ) {
			$setting = $result;
		}

		return $setting;

	}

}
