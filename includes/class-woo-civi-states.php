<?php
/**
 * WPCV WooCommerce CiviCRM States class.
 *
 * Handles the integration of WooCommerce States with CiviCRM States.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM States class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_States {

	/**
	 * Replace WooCommerce States/Counties.
	 *
	 * @since 2.0
	 * @access public
	 * @var bool $replace
	 */
	public $replace = false;

	/**
	 * CiviCRM Countries.
	 *
	 * Array holding CiviCRM country list in the form of array( 'country_id' => 'is_code' ).
	 *
	 * @since 2.0
	 * @access public
	 * @var array $countries The CiviCRM countries.
	 */
	public $civicrm_countries = [];


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

		// Add CiviCRM settings tab.
		add_filter( 'woocommerce_states', [ $this, 'replace_woocommerce_states' ], 10, 1 );

	}

	/**
	 * Replaces the WooCommerce State/Provinces list with CiviCRM's list.
	 *
	 * @since 2.0
	 *
	 * @param array $states The existing WooCommerce State/Provinces.
	 * @return array $states The WooCommerce State/Provinces overwritten with CiviCRM data.
	 */
	public function replace_woocommerce_states( $states ) {

		// Bail early if replace is not enabled.
		$setting = get_option( 'woocommerce_civicrm_replace_woocommerce_states' );
		$replace = WPCV_WCI()->helper->check_yes_no_value( $setting );
		if ( ! $replace ) {
			return $states;
		}

		// Start from scratch.
		$states = [];
		$civicrm_states = WPCV_WCI()->helper->get_civicrm_states();
		$civicrm_countries = $this->get_civicrm_countries();

		foreach ( $civicrm_states as $state_id => $state ) {
			$states[ $civicrm_countries[ $state['country_id'] ] ][ $state['abbreviation'] ] = $state['name'];
		}

		return $states;

	}

	/**
	 * Gets a formatted array of CiviCRM Countries.
	 *
	 * @since 2.0
	 *
	 * @return array $civicrm_countries The CiviCRM country list.
	 */
	public function get_civicrm_countries() {

		// Return early if already calculated.
		if ( ! empty( $this->civicrm_countries ) ) {
			return $this->civicrm_countries;
		}

		$this->civicrm_countries = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->civicrm_countries;
		}

		$params = [
			'sequential' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		$result = civicrm_api3( 'Country', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->civicrm_countries;
		}

		foreach ( $result['values'] as $key => $country ) {
			$this->civicrm_countries[ $country['id'] ] = $country['iso_code'];
		}

		return $this->civicrm_countries;

	}

}
