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
	 * Empty since class properties are now populated on demand.
	 *
	 * @since 2.0
	 */
	public function initialise() {}

	/**
	 * Get a CiviCRM Setting.
	 *
	 * @since 3.0
	 *
	 * @param string $name The name of the CiviCRM Setting.
	 * @return mixed $setting The value of the CiviCRM Setting, or false on failure.
	 */
	public function get_civicrm_setting( $name ) {

		$setting = false;

		// Init CiviCRM or bail.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $setting;
		}

		$params = [
			'version' => 3,
			'sequential' => 1,
			'name' => $name,
		];

		try {

			$setting = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( Exception $e ) {

			/* translators: %s: The name of the requested CiviCRM Setting */
			$human_readable = sprintf( __( 'Unable to fetch the "%s" setting.', 'wpcv-woo-civi-integration' ), $name );

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( $human_readable );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write extra details to PHP log.
			error_log( print_r( [
				'method' => __METHOD__,
				'error' => $human_readable,
				'message' => $e->getMessage(),
				'params' => $params,
				'setting' => $setting,
				'backtrace' => $e->getTraceAsString(),
			], true ) );

			return false;

		}

		// --<
		return $setting;

	}

	/**
	 * Gets the array of options for CiviCRM Entity Types.
	 *
	 * @since 2.0
	 *
	 * @return array $entity_options The array of CiviCRM Entity Types.
	 */
	public function get_entity_type_options() {

		// Init options array.
		$entity_options = [];

		// Build options for the Entity Types select.
		$entity_options['civicrm_exclude'] = __( 'Do not sync to CiviCRM', 'wpcv-woo-civi-integration' );
		$entity_options['civicrm_contribution'] = __( 'CiviCRM Contribution', 'wpcv-woo-civi-integration' );

		/**
		 * Filters the Entity Types.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Membership::panel_entity_option_add() (Priority: 10)
		 * * WPCV_Woo_Civi_Participant::panel_entity_option_add() (Priority: 20)
		 *
		 * @since 3.0
		 *
		 * @param array $entity_options The array of CiviCRM Entity Types.
		 */
		$entity_options = apply_filters( 'wpcv_woo_civi/product/panel/entity_options', $entity_options );

		return $entity_options;

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
			'billing' => (int) get_option( 'woocommerce_civicrm_billing_location_type_id' ),
			'shipping' => (int) get_option( 'woocommerce_civicrm_shipping_location_type_id' ),
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
	 * Gets the raw data for the CiviCRM Financial Types.
	 *
	 * @since 2.0
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types data.
	 */
	public function get_financial_types_raw() {

		// Return early if already calculated.
		if ( isset( $this->financial_types ) ) {
			return $this->financial_types;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		$params = [
			'version' => 3,
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

		$result = civicrm_api( 'FinancialType', 'get', $params );

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

			return [];

		}

		// Assign result to property.
		$this->financial_types = $result['values'];

		return $this->financial_types;

	}

	/**
	 * Get CiviCRM Financial Type names keyed by ID.
	 *
	 * @since 2.0
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types keyed by ID.
	 */
	public function get_financial_types() {

		$raw_financial_types = $this->get_financial_types_raw();
		if ( empty( $raw_financial_types ) ) {
			return [];
		}

		$financial_types = [];
		foreach ( $raw_financial_types as $key => $value ) {
			$financial_types[ $value['id'] ] = $value['name'];
		}

		return $financial_types;

	}

	/**
	 * Gets the active CiviCRM Price Sets.
	 *
	 * @since 3.0
	 *
	 * @return array $price_sets The array of Price Sets, or false on failure.
	 */
	public function get_price_sets() {

		// Init CiviCRM or bail.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Define Price Set query params.
		$params = [
			'sequential' => 1,
			'is_active' => 1,
			'is_reserved' => 0,
			'options' => [ 'limit' => 0 ],
			'api.PriceField.get' => [
				'sequential' => 0,
				'price_set_id' => "\$value.id",
				'is_active' => 1,
				'options' => [ 'limit' => 0 ],
			],
		];

		try {

			$result = civicrm_api3( 'PriceSet', 'get', $params );

		} catch ( Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Price Sets', 'wpcv-woo-civi-integration' ) );
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

		// Bail if no Price Sets.
		if ( ! $result['count'] ) {
			return false;
		}

		// We want the result set.
		$price_sets = $result['values'];

		return $price_sets;

	}

	/**
	 * Gets the active CiviCRM Price Field Values.
	 *
	 * @since 3.0
	 *
	 * @return array $price_field_values The array of Price Field Values, or false on failure.
	 */
	public function get_price_field_values() {

		// Init CiviCRM or bail.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		// Define Price Field Value query params.
		$params = [
			'sequential' => 0,
			'is_active' => 1,
			'options' => [
				'limit' => 0,
				'sort' => 'weight ASC',
			],
		];

		try {

			$result = civicrm_api3( 'PriceFieldValue', 'get', $params );

		} catch ( Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Price Field Values', 'wpcv-woo-civi-integration' ) );
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

		// Bail if no Price Field Values.
		if ( ! $result['count'] ) {
			return false;
		}

		// We want the result set.
		$price_field_values = $result['values'];

		return $price_field_values;

	}

	/**
	 * Gets the data for the active CiviCRM Price Sets.
	 *
	 * The return array also includes nested arrays for the corresponding Price
	 * Fields and Price Field Values for each Price Set.
	 *
	 * @since 3.0
	 *
	 * @return array $price_set_data The array of Price Set data, or false on failure.
	 */
	public function get_price_sets_populated() {

		// Return early if already built.
		static $price_set_data;
		if ( isset( $price_set_data ) ) {
			return $price_set_data;
		}

		// Get the active Price Sets.
		$price_sets = $this->get_price_sets();
		if ( empty( $price_sets ) ) {
			return false;
		}

		// Get the active Price Field Values.
		$price_field_values = $this->get_price_field_values();
		if ( empty( $price_field_values ) ) {
			return false;
		}

		// Get the CiviCRM Tax Rates and "Tax Enabled" status.
		$tax_rates = WPCV_WCI()->tax->rates_get();
		$tax_enabled = WPCV_WCI()->tax->is_tax_enabled();

		$price_sets_data = [];

		foreach ( $price_sets as $key => $price_set ) {

			// Add renamed ID.
			$price_set_id = (int) $price_set['id'];
			$price_set['price_set_id'] = $price_set_id;

			// Let's give the chained API result array a nicer name.
			$price_set['price_fields'] = $price_set['api.PriceField.get']['values'];

			foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) {

				// Add renamed ID.
				$price_set['price_fields'][ $price_field_id ]['price_field_id'] = $price_field_id;

				foreach ( $price_field_values as $value_id => $price_field_value ) {

					// Add renamed ID.
					$price_field_value['price_field_value_id'] = $value_id;

					// Skip unless matching item.
					if ( (int) $price_field_id !== (int) $price_field_value['price_field_id'] ) {
						continue;
					}

					// Add Tax data if necessary.
					if ( $tax_enabled && ! empty( $tax_rates ) && array_key_exists( $price_field_value['financial_type_id'], $tax_rates ) ) {
						$price_field_value['tax_rate'] = $tax_rates[ $price_field_value['financial_type_id'] ];
						$price_field_value['tax_amount'] = $this->percentage( $price_field_value['amount'], $price_field_value['tax_rate'] );
					}

					// Nest the Price Field Value keyed by its ID.
					$price_set['price_fields'][ $price_field_id ]['price_field_values'][ $value_id ] = $price_field_value;

				}

			}

			// We don't need the chained API array.
			unset( $price_set['api.PriceField.get'] );

			// Add Price Set data to return.
			$price_sets_data[ $price_set_id ] = $price_set;

		}

		return $price_sets_data;

	}

	/**
	 * Calculate the percentage for a given amount.
	 *
	 * @since 3.0
	 *
	 * @param string $amount The amount.
	 * @param string $percentage The percentage.
	 * @return string $amount The calculated percentage amount.
	 */
	public function percentage( $amount, $percentage ) {
		return ( $percentage / 100 ) * $amount;
	}

	/**
	 * Gets the formatted options array of the active CiviCRM Price Sets.
	 *
	 * The return array is formatted for the select with optgroups setting.
	 *
	 * @since 3.0
	 *
	 * @param bool $zero_option True adds the "Select a Price Field" option.
	 * @return array $price_set_options The formatted options array of Price Set data.
	 */
	public function get_price_sets_options( $zero_option = true ) {

		// Get the Price Sets array.
		$price_sets = $this->get_price_sets_populated();
		if ( empty( $price_sets ) ) {
			return [];
		}

		// Init options array.
		$price_set_options = [];
		if ( $zero_option === true ) {
			$price_set_options[0] = __( 'Select a Price Field', 'wpcv-woo-civi-integration' );
		}

		// Build the array for the select with optgroups.
		foreach ( $price_sets as $price_set_id => $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) {
				/* translators: 1: Price Set title, 2: Price Field label */
				$optgroup_label = sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] );
				$optgroup_content = [];
				foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) {
					$optgroup_content[ esc_attr( $price_field_value_id ) ] = esc_html( $price_field_value['label'] );
				}
				$price_set_options[ esc_attr( $optgroup_label ) ] = $optgroup_content;
			}
		}

		return $price_set_options;

	}

	/**
	 * Gets the Price Set data for a given Price Field Value ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_set The array of Price Set data, or false on failure.
	 */
	public function get_price_set_by_price_field_value_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->get_price_sets_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the enclosing Price Set data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_set;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Gets the Price Field data for a given Price Field Value ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_field The array of Price Field data, or false on failure.
	 */
	public function get_price_field_by_price_field_value_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->get_price_sets_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the enclosing Price Field data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_field;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Gets the Price Field Value data for a given Price Field Value ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_field_value The array of Price Field Value data, or false on failure.
	 */
	public function get_price_field_value_by_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->get_price_sets_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the Price Field Value data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_field_value;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Gets the CiviCRM Decimal Separator.
	 *
	 * @since 3.0
	 *
	 * @return string|bool $decimal_separator The CiviCRM Decimal Separator, or false on failure.
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

		} catch ( Exception $e ) {

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
	 * @return string|bool $thousand_separator The CiviCRM Thousand Separator, or false on failure.
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

		} catch ( Exception $e ) {

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
	 * @param integer|float $number The WooCommerce number.
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
	 * Gets the WooCommerce Product Types as options.
	 *
	 * @since 3.0
	 *
	 * @param bool $raw Pass true if all Product Types are required.
	 * @return array $gateways The array of WooCommerce Product Types.
	 */
	public function get_product_types_options( $raw = true ) {

		$all_product_types = wc_get_product_types();

		/**
		 * Filter the WooCommerce Product Types.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Products::product_types_filter() (Priority: 10)
		 * * WPCV_Woo_Civi_Products_Variable::product_types_filter() (Priority: 20)
		 * * WPCV_Woo_Civi_Products_Custom::product_types_filter() (Priority: 30)
		 *
		 * @since 3.0
		 *
		 * @param array $all_product_types The array of all WooCommerce Product Types.
		 */
		$all_product_types = apply_filters( 'wpcv_woo_civi/product_types/get/options', $all_product_types );

		return $all_product_types;

	}

	/**
	 * Gets the WooCommerce Payment Gateways.
	 *
	 * @since 3.0
	 *
	 * @param bool $enabled Pass true if only enabled Payment Gateways are required.
	 * @return array $gateways The array of WooCommerce Payment Gateway objects.
	 */
	public function payment_gateways( $enabled = false ) {

		$gateways = [];

		// Get all Payment Gateways.
		//$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$all_gateways = WC()->payment_gateways->payment_gateways();
		if ( empty( $all_gateways ) ) {
			return $gateways;
		}

		// Only include those which are enabled.
		foreach ( $all_gateways as $gateway ) {
			if ( ! $gateway->is_available() && $enabled ) {
				continue;
			}
			$gateways[] = $gateway;
		}

		return $gateways;

	}

	/**
	 * Gets the WooCommerce Payment Gateways as options.
	 *
	 * @since 3.0
	 *
	 * @return array $gateways The array of Payment Gateway options.
	 */
	public function get_payment_gateway_options() {

		$gateways = [];

		// Get enabled Payment Gateways.
		$enabled_gateways = $this->payment_gateways();
		if ( empty( $enabled_gateways ) ) {
			return $gateways;
		}

		// Build the array for the HTML element.
		foreach ( $enabled_gateways as $gateway ) {
			$gateways[ $gateway->id ] = $gateway->method_title;
		}

		return $gateways;

	}

	/**
	 * Maps a WooCommerce payment method to a CiviCRM payment instrument.
	 *
	 * @since 2.0
	 *
	 * @param string $payment_method The WooCommerce payment method.
	 * @return integer $id The CiviCRM payment processor ID.
	 */
	public function payment_instrument_map( $payment_method ) {

		$map = [
			'paypal' => 1,
			'stripe' => 1,
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
