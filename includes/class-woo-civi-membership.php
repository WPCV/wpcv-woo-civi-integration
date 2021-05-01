<?php
/**
 * Membership class.
 *
 * Manages Membership integration between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Membership class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Membership {

	/**
	 * CiviCRM Membership component status.
	 *
	 * True if the CiviMember component is active, false by default.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $active The status of the CiviMember component.
	 */
	public $active = false;

	/**
	 * The active Membership Types.
	 *
	 * Array of key/value pairs holding the active Membership Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The active Membership Types.
	 */
	public $membership_types;

	/**
	 * The CiviCRM Membership Signup OptionValue.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $optionvalue_membership_signup The CiviCRM Membership Signup OptionValue.
	 */
	public $optionvalue_membership_signup;

	/**
	 * WooCommerce Product meta key holding the CiviCRM Membership ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Product meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_membership_type_id';

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

		// Bail early if the CiviMember component is not active.
		$this->active = WPCV_WCI()->helper->is_component_enabled( 'CiviMember' );
		if ( ! $this->active ) {
			return;
		}

		// Add Membership Type select to the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/civicrm/after', [ $this, 'panel_add_select' ] );

		// Add Membership Type to Line Item.
		add_action( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ] );

	}

	/**
	 * Adds Membership Type select to the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	public function panel_add_select() {

		woocommerce_wp_select( [
			'id' => 'woocommerce_civicrm_membership_type_id',
			'name' => 'woocommerce_civicrm_membership_type_id',
			'label' => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
			'desc_tip' => 'true',
			'description' => __( 'Select a Membership Type if you would like this Product to create a Membership in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
			'options' => $this->get_membership_types_options(),
		] );

	}

	/**
	 * Adds the CiviCRM Membership setting as meta to the Product.
	 *
	 * @since 2.4
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		if ( isset( $_POST['woocommerce_civicrm_membership_type_id'] ) ) {
			$membership_type_id = sanitize_key( $_POST['woocommerce_civicrm_membership_type_id'] );
			$product->add_meta_data( $this->meta_key, $membership_type_id, true );
		}

	}

	/**
	 * Filters a Line Item to add a Membership Type.
	 *
	 * @since 3.0
	 *
	 * @param array $line_item The array of Line Item data.
	 * @param object $product The WooCommerce Product object.
	 * @param array $params The params to be passed to the CiviCRM API.
	 */
	public function line_item_filter( $line_item, $product, $params ) {

		// Get Membership Type ID from Product meta.
		$product_membership_type_id = $product->get_meta( $this->meta_key );

		// Bail if none found.
		if ( empty( $product_membership_type_id ) ) {
			return $line_item;
		}

		// Grab the Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		$membership_params = [
			'entity_table' => 'civicrm_membership',
			'membership_type_id' => $product_membership_type_id,
		];
		$line_item_params = [
			'membership_type_id' => $product_membership_type_id,
			'contact_id' => $params['contact_id'],
		];

		// Apply Membership to Line Item.
		$line_item = [
			'params' => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $membership_params ),
			],
		];

		// Store the fact that there's a Membership with this Product.
		$this->has_membership[ $product->get_id() ] = $product_membership_type_id;

		return $line_item;

	}

	/**
	 * Gets the Membership Type ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $membership_type_id The Membership Type ID, false otherwise.
	 */
	public function get_product_meta( $product_id ) {
		$membership_type_id = get_post_meta( $product_id, $this->meta_key, true );
		return $membership_type_id;
	}

	/**
	 * Sets the CiviCRM Membership Type ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @param int $membership_type_id The numeric ID of the Membership Type.
	 */
	public function set_product_meta( $product_id, $membership_type_id ) {
		update_post_meta( $product_id, $this->meta_key, $membership_type_id, true );
	}

	/**
	 * Get CiviCRM Membership Types.
	 *
	 * @since 2.0
	 *
	 * @return array $membership_types The array of CiviCRM Membership Types.
	 */
	public function get_membership_types() {

		// Return early if already calculated.
		if ( isset( $this->membership_types ) ) {
			return $this->membership_types;
		}

		$this->membership_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->membership_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		/**
		 * Filter the Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/membership_types/get/params', $params );

		$result = civicrm_api3( 'MembershipType', 'get', $params );

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

			return $this->membership_types;

		}

		foreach ( $result['values'] as $key => $value ) {
			$this->membership_types['by_membership_type_id'][ $value['id'] ] = $value;
			$this->membership_types['by_financial_type_id'][ $value['financial_type_id'] ] = $value;
		}

		/**
		 * Filter the CiviCRM Membership Types.
		 *
		 * @since 2.0
		 *
		 * @param array $membership_types The existing array of CiviCRM Membership Types.
		 * @param array $result The CiviCRM API data array.
		 */
		$this->membership_types = apply_filters( 'wpcv_woo_civi/membership_types', $this->membership_types, $result );

		return $this->membership_types;

	}

	/**
	 * Get the CiviCRM Membership Types options.
	 *
	 * @since 2.4
	 *
	 * @return array $membership_types_options The CiviCRM Membership Types options.
	 */
	public function get_membership_types_options() {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		try {

			$params = [
				'is_active' => true,
				'options' => [
					'limit' => 0,
				],
			];

			$result = civicrm_api3( 'MembershipType', 'get', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Types.', 'wpcv-woo-civi-integration' ) );
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

		if ( empty( $result['count'] ) ) {
			return [];
		}

		$membership_types_options = [
			0 => __( 'None', 'wpcv-woo-civi-integration' ),
		];

		$membership_types_options = array_reduce(
			$result['values'],
			function( $list, $membership_type ) {
				$list[ $membership_type['id'] ] = $membership_type['name'];
				return $list;
			},
			$membership_types_options
		);

		return $membership_types_options;

	}

	/**
	 * Get a CiviCRM Membership Type by its ID.
	 *
	 * @since 2.4
	 *
	 * @param int $id The numeric ID of the CiviCRM Membership Type.
	 * @return array|null $membership_type The CiviCRM Membership Type data, or null on failure.
	 */
	public function get_membership_type( int $id ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return null;
		}

		try {

			$params = [
				'id' => $id,
			];

			$result = civicrm_api3( 'MembershipType', 'gesingle', $params );

			return $result;

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Type.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return null;
		}

	}

	/**
	 * Get the CiviCRM Membership Signup OptionValue.
	 *
	 * @since 2.0
	 *
	 * @return array|bool $result The CiviCRM Membership Signup OptionValue, or false on failure.
	 */
	public function get_membership_signup_optionvalue() {

		// Return early if already calculated.
		if ( isset( $this->optionvalue_membership_signup ) ) {
			return $this->optionvalue_membership_signup;
		}

		$this->optionvalue_membership_signup = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->optionvalue_membership_signup;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'value' ],
			'name' => 'Membership Signup',
		];

		$result = civicrm_api3( 'OptionValue', 'get', $params );

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

			return $this->optionvalue_membership_signup;

		}

		// Sanity check.
		if ( ! empty( $result['values'][0]['value'] ) ) {
			$this->optionvalue_membership_signup = $result['values'][0]['value'];
		}

		return $this->optionvalue_membership_signup;

	}

}
