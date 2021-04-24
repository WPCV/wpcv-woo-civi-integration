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
		$this->inited();

	}

	/**
	 * CiviCRM inited.
	 *
	 * @since 2.0
	 */
	public function inited() {

		if ( ! WPCV_WCI()->boot_civi() ) {
			return;
		}

		$this->replace = WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_replace_woocommerce_states' ) );
		$this->civicrm_countries = $this->get_civicrm_countries();

	}

	/**
	 * Function to replace WooCommerce State/Provinces list with CiviCRM's list.
	 *
	 * @since 2.0
	 *
	 * @uses 'woocommerce_states' filter.
	 *
	 * @param array $states The WooCommerce State/Provinces.
	 * @return array $states The modifies State/Provinces.
	 */
	public function replace_woocommerce_states( $states ) {

		// Bail if replace is not enabled.
		if ( ! $this->replace ) {
			return $states;
		}

		$new_states = [];
		foreach ( WPCV_WCI()->helper->civicrm_states as $state_id => $state ) {
			$new_states[ $this->civicrm_countries[ $state['country_id'] ] ][ $state['abbreviation'] ] = $state['name'];
		}

		return $new_states;

	}

	/**
	 * Get the CiviCRM Countries.
	 *
	 * @since 2.0
	 *
	 * @return array $civicrm_countries The CiviCRM country list.
	 */
	public function get_civicrm_countries() {

		if ( ! empty( $this->civicrm_countries ) ) {
			return $this->civicrm_countries;
		}

		$params = [
			'sequential' => 1,
			'options' => [ 'limit' => 0 ],
		]

		$countries = civicrm_api3( 'Country', 'get', $params );

		$civicrm_countries = [];

		// Return early if something went wrong.
		if ( ! empty( $countries['error'] ) ) {
			return $civicrm_countries;
		}

		foreach ( $countries['values'] as $key => $country ) {
			$civicrm_countries[ $country['id'] ] = $country['iso_code'];
		}

		return $civicrm_countries;

	}

}
