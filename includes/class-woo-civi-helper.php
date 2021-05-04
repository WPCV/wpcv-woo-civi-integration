<?php
/**
 * Helper Class.
 *
 * Miscellaneous utilities.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Helper class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Helper {

	/**
	 * The active Financial Types.
	 *
	 * Array of key/value pairs holding the active Financial Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The Financial Types.
	 */
	public $financial_types;

	/**
	 * WooCommerce/CiviCRM mapped Address Location Types.
	 *
	 * Array of key/value pairs holding the WooCommerce/CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $mapped_location_types The WooCommerce/CiviCRM mapped Address Location Types.
	 */
	public $mapped_location_types;

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
	 * @since 2.0
	 */
	public function initialise() {

		// Empty since class properties are now populated on demand.

	}

	/**
	 * Get mapping between WooCommerce and CiviCRM Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $mapped_location_types The mapped Location Types.
	 */
	public function get_mapped_location_types() {

		// Return early if already calculated.
		if ( ! empty( $this->mapped_location_types ) ) {
			return $this->mapped_location_types;
		}

		$this->mapped_location_types = [
			'billing' => get_option( 'woocommerce_civicrm_billing_location_type_id' ),
			'shipping' => get_option( 'woocommerce_civicrm_shipping_location_type_id' ),
		];

		/**
		 * Filter mapping between WooCommerce and CiviCRM Location Types.
		 *
		 * @since 2.0
		 *
		 * @param array $mapped_location_types The default mapped Location Types.
		 */
		$this->mapped_location_types = apply_filters( 'wpcv_woo_civi/location_types/mappings', $this->mapped_location_types );

		return $this->mapped_location_types;

	}

	/**
	 * Get CiviCRM Financial Types.
	 *
	 * @since 2.0
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types keyed by ID.
	 */
	public function get_financial_types() {

		// Return early if already calculated.
		if ( isset( $this->financial_types ) ) {
			return $this->financial_types;
		}

		$this->financial_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->financial_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		/**
		 * Filter Financial Type params before calling the CiviCRM API.
		 *
		 * @since 2.0
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/financial_types/get/params', $params );

		$result = civicrm_api3( 'FinancialType', 'get', $params );

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

			return $this->financial_types;

		}

		foreach ( $result['values'] as $key => $value ) {
			$this->financial_types[ $value['id'] ] = $value['name'];
		}

		return $this->financial_types;

	}

	/**
	 * Get the formatted Financial Types options.
	 *
	 * @since 2.4
	 *
	 * @return array $financial_types The formatted Financial Types options.
	 */
	public function get_financial_types_options() {

		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );

		$financial_types = $this->get_financial_types();

		$options = [
			sprintf(
				/* translators: %s: The Financial Type */
				'-- ' . __( 'Default (%s)', 'wpcv-woo-civi-integration' ),
				$financial_types[ $default_financial_type_id ] ?? __( 'Not set', 'wpcv-woo-civi-integration' )
			),
		]
		+ $financial_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'wpcv-woo-civi-integration' ),
		];

		return $options;

	}

	/**
	 * Gets the CiviCRM Decimal Separator.
	 *
	 * @since 3.0
	 *
	 * @return str|bool $decimal_separator The CiviCRM Decimal Separator, or false on failure.
	 */
	public function get_decimal_separator() {

		// Return early if already calculated.
		static $decimal_separator;
		if ( isset( $decimal_separator ) ) {
			return $decimal_separator;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name' => 'monetaryDecimalPoint',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Decimal Separator', 'wpcv-woo-civi-integration' ) );
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

		$decimal_separator = '.';

		if ( is_string( $result ) ) {
			$decimal_separator = $result;
		}

		return $decimal_separator;

	}

	/**
	 * Gets the CiviCRM Thousand Separator.
	 *
	 * @since 3.0
	 *
	 * @return str|bool $thousand_separator The CiviCRM Thousand Separator, or false on failure.
	 */
	public function get_thousand_separator() {

		// Return early if already calculated.
		static $thousand_separator;
		if ( isset( $thousand_separator ) ) {
			return $thousand_separator;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name' => 'monetaryThousandSeparator',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Thousand Separator', 'wpcv-woo-civi-integration' ) );
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

		$thousand_separator = '';

		if ( is_string( $result ) ) {
			$thousand_separator = $result;
		}

		return $thousand_separator;

	}

	/**
	 * Converts a number to CiviCRM-compliant number format.
	 *
	 * @since 3.0
	 *
	 * @param int|float $number The WooCommerce number.
	 * @return float $civicrm_number The CiviCRM-compliant number.
	 */
	public function get_civicrm_float( $number ) {

		// Return incoming value on error.
		$decimal_separator = $this->get_decimal_separator();
		$thousand_separator = $this->get_thousand_separator();
		if ( $decimal_separator === false || $thousand_separator === false ) {
			return $number;
		}

		$civicrm_number = number_format( $number, 2, $decimal_separator, $thousand_separator );

		return $civicrm_number;

	}

	/**
	 * Maps a WooCommerce payment method to a CiviCRM payment instrument.
	 *
	 * @since 2.0
	 *
	 * @param string $payment_method The WooCommerce payment method.
	 * @return int $id The CiviCRM payment processor ID.
	 */
	public function payment_instrument_map( $payment_method ) {

		$map = [
			'paypal' => 1,
			'cod' => 3,
			'cheque' => 4,
			'bacs' => 5,
		];

		if ( array_key_exists( $payment_method, $map ) ) {
			$id = $map[ $payment_method ];
		} else {
			// Another WooCommerce payment method - good chance this is credit.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Check whether a value is (string) 'yes'.
	 *
	 * @since 2.0
	 *
	 * @param string $value The value to check.
	 * @return bool true | false
	 */
	public function check_yes_no_value( $value ) {
		return 'yes' === $value ? true : false;
	}

	/**
	 * Get a CiviCRM admin link.
	 *
	 * @since 3.0
	 *
	 * @param string $path The CiviCRM path.
	 * @param string $params The CiviCRM parameters.
	 * @return string $link The URL of the CiviCRM page.
	 */
	public function get_civi_admin_link( $path = '', $params = null ) {

		// Init link.
		$link = '';

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $link;
		}

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			$path, // Path to the resource.
			$params, // Params to pass to resource.
			true, // Force an absolute link.
			null, // Fragment (#anchor) to append.
			true, // Encode special HTML characters.
			false, // CMS front end.
			true // CMS back end.
		);

		return $link;

	}

	/**
	 * Finds out if a CiviCRM Component is active.
	 *
	 * @since 3.0
	 *
	 * @param string $component The name of the CiviCRM Component, e.g. 'CiviContribute'.
	 * @return bool True if the Component is active, false otherwise.
	 */
	public function is_component_enabled( $component = '' ) {

		$active = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $active;
		}

		// Get the Component array. CiviCRM handles caching.
		$components = CRM_Core_Component::getEnabledComponents();

		if ( array_key_exists( $component, $components ) ) {
			$active = true;
		}

		return $active;

	}

	/**
	 * Get WordPress sites on a Multisite installation.
	 *
	 * @since 2.0
	 *
	 * @return array $sites The array of sites keyed by ID.
	 */
	public function get_sites() {

		$sites = [];

		if ( is_multisite() ) {

			$query = [
				'orderby' => 'domain',
			];

			$wp_sites = get_sites( $query );

			foreach ( $wp_sites as $site ) {
				$sites[ $site->blog_id ] = $site->domain;
			}

		}

		return $sites;

	}

	/**
	 * Returns the timezone of the current site.
	 *
	 * Gets timezone settings from the database. If a timezone identifier is used
	 * just turns it into a DateTimeZone object. If an offset is used, tries to
	 * find a suitable timezone. If all else fails, uses UTC.
	 *
	 * This is a modified version of the "eo_get_blog_timezone" function in the
	 * Event Organiser plugin.
	 *
	 * @see https://github.com/stephenharris/Event-Organiser/blob/develop/includes/event-organiser-utility-functions.php#L352
	 *
	 * @since 3.0
	 *
	 * @return DateTimeZone $timezone The site timezone.
	*/
	public function get_site_timezone() {

		/*
		// Pseudo-cache.
		static $timezone = false;
		if ( false !== $timezone ) {
			return $timezone;
		}
		*/

		// The pseudo-cache will not be busted on switch_to_blog.
		$timezone = false;

		$tzstring = get_option( 'timezone_string' );
		$offset = get_option( 'gmt_offset' );

		/*
		 * Setting manual offsets should be discouraged.
		 *
		 * The IANA timezone database that provides PHP's timezone support uses
		 * (reversed) POSIX style signs.
		 *
		 * @see https://github.com/stephenharris/Event-Organiser/issues/287
		 * @see http://us.php.net/manual/en/timezones.others.php
		 * @see https://bugs.php.net/bug.php?id=45543
		 * @see https://bugs.php.net/bug.php?id=45528
		 */
		if ( empty( $tzstring ) && 0 != $offset && floor( $offset ) == $offset ) {
			$offset_string = $offset > 0 ? "-$offset" : '+' . absint( $offset );
			$tzstring = 'Etc/GMT' . $offset_string;
		}

		// Default to 'UTC' if the timezone string is empty.
		if ( empty( $tzstring ) ) {
			$tzstring = 'UTC';
		}

		// If we already have a DateTimeZone object, return that.
		if ( $tzstring instanceof DateTimeZone ) {
			$timezone = $tzstring;
			return $timezone;
		}

		// Create DateTimeZone object from timezone string.
		$timezone = new DateTimeZone( $tzstring );

		return $timezone;

	}

}
