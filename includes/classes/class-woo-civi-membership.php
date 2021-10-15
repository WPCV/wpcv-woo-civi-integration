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
	 * WooCommerce Product meta key holding the CiviCRM Membership ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Product meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_membership_type_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Membership Price Field Value ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $pfv_key The CiviCRM Membership Price Field Value ID meta key.
	 */
	public $pfv_key = '_woocommerce_civicrm_membership_pfv_id';

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
		add_action( 'wpcv_woo_civi/product/panel/civicrm/after', [ $this, 'panel_add_markup' ] );

		// Save Membership Type on the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved', [ $this, 'panel_saved' ] );

		// Add Membership Type to Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 20, 5 );

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
		update_post_meta( $product_id, $this->meta_key, $membership_type_id );
	}

	/**
	 * Gets the Membership Price Field Value ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $membership_pfv_id The Membership Price Field Value ID, false otherwise.
	 */
	public function get_pfv_meta( $product_id ) {
		$membership_pfv_id = get_post_meta( $product_id, $this->pfv_key, true );
		return $membership_pfv_id;
	}

	/**
	 * Sets the CiviCRM Membership Price Field Value ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @param int $membership_type_id The numeric ID of the Membership Price Field Value.
	 */
	public function set_pfv_meta( $product_id, $membership_pfv_id ) {
		update_post_meta( $product_id, $this->pfv_key, $membership_pfv_id );
	}

	/**
	 * Filters a Line Item to add a Membership Type.
	 *
	 * @since 3.0
	 *
	 * @param array $line_item The array of Line Item data.
	 * @param object $item The WooCommerce Item object.
	 * @param object $product The WooCommerce Product object.
	 * @param object $order The WooCommerce Order object.
	 * @param array $params The params to be passed to the CiviCRM API.
	 */
	public function line_item_filter( $line_item, $item, $product, $order, $params ) {

		// Get Membership Type ID from Product meta.
		$product_membership_type_id = $product->get_meta( $this->meta_key );
		if ( empty( $product_membership_type_id ) ) {
			return $line_item;
		}

		// Get Membership Price Field Value ID from Product meta.
		$price_field_value_id = $product->get_meta( $this->pfv_key );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Get Membership Price Field ID.
		$default_price_field_id = $this->get_default_price_field_id();
		if ( empty( $default_price_field_id ) ) {
			return $line_item;
		}

		// Grab the Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		$line_item_params = [
			'membership_type_id' => $product_membership_type_id,
			'source' => __( 'Shop', 'wpcv-woo-civi-integration' ),
			'contact_id' => $params['contact_id'],
			'skipStatusCal' => 1,
			'status_id' => 'Pending',
		];

		$membership_line_item_data = [
			'entity_table' => 'civicrm_membership',
			'price_field_id' => $default_price_field_id,
			'price_field_value_id' => $price_field_value_id,
		];

		// Apply Membership to Line Item.
		$line_item = [
			'params' => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $membership_line_item_data ),
			],
		];

		// Store the fact that there's a Membership with this Product.
		$this->has_membership[ $product->get_id() ] = $product_membership_type_id;

		return $line_item;

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
		static $membership_types;
		if ( isset( $membership_types ) ) {
			return $membership_types;
		}

		// Bail early if the CiviMember component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
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

			return [];

		}

		$membership_types = [];

		foreach ( $result['values'] as $key => $value ) {
			$membership_types['by_membership_type_id'][ $value['id'] ] = $value;
			$membership_types['by_financial_type_id'][ $value['financial_type_id'] ] = $value;
		}

		/**
		 * Filter the CiviCRM Membership Types.
		 *
		 * @since 2.0
		 *
		 * @param array $membership_types The existing array of CiviCRM Membership Types.
		 * @param array $result The CiviCRM API data array.
		 */
		$membership_types = apply_filters( 'wpcv_woo_civi/membership_types', $membership_types, $result );

		return $membership_types;

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

		$params = [
			'is_active' => true,
			'options' => [
				'limit' => 0,
			],
		];

		try {

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
				$list[ (int) $membership_type['id'] ] = $membership_type['name'];
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
	public function get_membership_type( $id ) {

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
	 * Get the default Membership Price Field ID.
	 *
	 * @since 3.0
	 *
	 * @return array|bool $price_field_id The default Membership Price Field ID, or false on failure.
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
			'price_set_id' => 'default_membership_type_amount',
			'options' => [
				'limit' => 1,
			],
		];

		try {

			$result = civicrm_api3( 'PriceField', 'get', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve default Membership Price Field ID', 'wpcv-woo-civi-integration' ) );
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
	 * Adds the CiviCRM Membership and Price Field Value settings as meta to the Product.
	 *
	 * @since 2.4
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Save the Membership Type ID.
		if ( isset( $_POST[$this->meta_key] ) ) {
			$membership_type_id = sanitize_key( $_POST[$this->meta_key] );
			$product->add_meta_data( $this->meta_key, (int) $membership_type_id, true );
		}

		// Save the Membership Price Field Value ID.
		if ( isset( $_POST[$this->pfv_key] ) ) {
			$membership_pfv_id = sanitize_key( $_POST[$this->pfv_key] );
			$product->add_meta_data( $this->pfv_key, (int) $membership_pfv_id, true );
		}

	}

	/**
	 * Adds Membership Type select to the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	public function panel_add_markup() {

		global $thepostid, $post;

		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		?>

		<div class="options_group">

			<?php

			woocommerce_wp_select( [
				'id' => $this->meta_key,
				'name' => $this->meta_key,
				'label' => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
				'desc_tip' => 'true',
				'description' => __( 'Select a Membership Type if you would like this Product to create a Membership in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'options' => $this->get_membership_types_options(),
			] );

			// Bail if there aren't any Price Sets.
			$price_sets = WPCV_WCI()->helper->get_price_sets_populated();
			if ( empty( $price_sets ) ) {
				return;
			}

			// Get Price Field Value.
			$pfv_id = $this->get_pfv_meta( $product_id );

			?>

			<p class="form-field">
				<label for="<?php echo $this->pfv_key; ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
				<select name="<?php echo $this->pfv_key; ?>" id="<?php echo $this->pfv_key; ?>" class="select short">
					<option value="0"><?php _e( 'Select a Price Field', 'cf-civicrm' ); ?></option>
					<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
						<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
							<optgroup label="<?php esc_attr_e( sprintf( __( 'CiviCRM Price Set: %1$s - Price Field:  %2$s', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
								<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
									<option value="<?php esc_attr_e( $price_field_value_id ); ?>" <?php selected( $price_field_value_id, $pfv_id ); ?>><?php esc_html_e( $price_field_value['label'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</select> <?php echo wc_help_tip( __( 'Select The Price Field for the Membership.', 'wpcv-woo-civi-integration' ) ); ?>
			</p>

		</div>

		<?php

	}

}
