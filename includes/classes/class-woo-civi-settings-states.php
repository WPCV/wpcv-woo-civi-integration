<?php
/**
 * States class.
 *
 * Handles the integration of WooCommerce States with CiviCRM States.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * States class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Settings_States {

	/**
	 * CiviCRM Countries.
	 *
	 * Array of CiviCRM Countries in the form of array( 'country_id' => 'iso_code' ).
	 *
	 * @since 2.0
	 * @access public
	 * @var array $civicrm_countries The CiviCRM countries.
	 */
	public $civicrm_countries = [];


	/**
	 * CiviCRM States.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $civicrm_states The CiviCRM States.
	 */
	public $civicrm_states = [];

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

		// Replace the WooCommerce State/Provinces list with CiviCRM's list.
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
		$civicrm_states = $this->get_civicrm_states();
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
			'version' => 3,
			'sequential' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		$result = civicrm_api( 'Country', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			return $this->civicrm_countries;
		}

		foreach ( $result['values'] as $key => $country ) {
			$this->civicrm_countries[ $country['id'] ] = $country['iso_code'];
		}

		return $this->civicrm_countries;

	}

	/**
	 * Get a CiviCRM Country ID for a given WooCommerce Country ISO Code.
	 *
	 * @since 2.0
	 *
	 * @param string $woo_country The WooCommerce Country ISO code.
	 * @return integer|bool $id The CiviCRM Country ID, or false on failure.
	 */
	public function get_civicrm_country_id( $woo_country ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Bail if no Country ISO code is supplied.
		if ( empty( $woo_country ) ) {
			return false;
		}

		$params = [
			'version' => 3,
			'sequential' => 1,
			'iso_code' => $woo_country,
		];

		$result = civicrm_api( 'Country', 'getsingle', $params );

		// Bail if something went wrong.
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

		return (int) $result['id'];

	}

	/**
	 * Get the CiviCRM Country ISO Code for a given Country ID.
	 *
	 * @since 2.0
	 *
	 * @param integer $country_id The numeric ID of the CiviCRM Country.
	 * @return string|bool $iso_code The CiviCRM Country ISO Code, or false on failure.
	 */
	public function get_civicrm_country_iso_code( $country_id ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Bail if no Country ID is supplied.
		if ( empty( $country_id ) ) {
			return false;
		}

		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $country_id,
		];

		$result = civicrm_api( 'Country', 'getsingle', $params );

		// Bail if something went wrong.
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

		return $result['iso_code'];

	}

	/**
	 * Get data for CiviCRM States.
	 *
	 * Build multi-dimensional array of CiviCRM States, e.g.
	 * array( 'state_id' => array( 'name', 'id', 'abbreviation', 'country_id' ) )
	 *
	 * @since 2.0
	 *
	 * @return array $civicrm_states The array of data for CiviCRM States.
	 */
	public function get_civicrm_states() {

		// Return early if already calculated.
		if ( ! empty( $this->civicrm_states ) ) {
			return $this->civicrm_states;
		}

		$this->civicrm_states = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->civicrm_states;
		}

		// Perform direct query.
		$query = 'SELECT name, id, country_id, abbreviation FROM civicrm_state_province';
		$dao = CRM_Core_DAO::executeQuery( $query );

		while ( $dao->fetch() ) {
			$this->civicrm_states[ $dao->id ] = [
				'id' => $dao->id,
				'name' => $dao->name,
				'abbreviation' => $dao->abbreviation,
				'country_id' => $dao->country_id,
			];
		}

		return $this->civicrm_states;

	}

	/**
	 * Get the ID of a CiviCRM State/Province for a WooCommerce State.
	 *
	 * @since 2.0
	 *
	 * @param string  $woo_state The WooCommerce State.
	 * @param integer $country_id The numeric ID of the CiviCRM Country.
	 * @return integer|bool $id The numeric ID of the CiviCRM State/Province, or false on failure.
	 */
	public function get_civicrm_state_province_id( $woo_state, $country_id ) {

		// Bail if no WooCommerce State is supplied.
		if ( empty( $woo_state ) ) {
			return false;
		}

		// Get CiviCRM States.
		$civicrm_states = $this->get_civicrm_states();

		foreach ( $civicrm_states as $state_id => $state ) {
			if ( (int) $state['country_id'] === (int) $country_id && $state['abbreviation'] === $woo_state ) {
				return (int) $state['id'];
			}
			if ( (int) $state['country_id'] === (int) $country_id && $state['name'] === $woo_state ) {
				return (int) $state['id'];
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Get a CiviCRM State/Province Name or Abbreviation by its ID.
	 *
	 * @since 2.0
	 *
	 * @param integer $state_province_id The numeric ID of the CiviCRM State.
	 * @return string|bool $name The CiviCRM State/Province Name or Abbreviation, or false on failure.
	 */
	public function get_civicrm_state_province_name( $state_province_id ) {

		// Bail if no State/Province ID is supplied.
		if ( empty( $state_province_id ) ) {
			return false;
		}

		// Get CiviCRM States.
		$civicrm_states = $this->get_civicrm_states();

		$civicrm_state = $civicrm_states[ $state_province_id ];
		$woo_countries = new WC_Countries();

		foreach ( $woo_countries->get_states() as $country => $states ) {
			$name = array_search( $civicrm_state['name'], $states, true );
			if ( ! empty( $states ) && $name ) {
				return $name;
			}
		}

		// Fallback.
		return $civicrm_state['name'];

	}

}
