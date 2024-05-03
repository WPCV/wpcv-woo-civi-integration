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
	 * @var string $meta_key The WooCommerce Product meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_membership_type_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Membership Price Field Value ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $pfv_key The CiviCRM Membership Price Field Value ID meta key.
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

		// Add Membership Type to Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 20, 5 );

		// Add Entity Type to the options for the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/entity_options', [ $this, 'panel_entity_option_add' ], 20 );

		// Add Membership Type select to the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/civicrm/after', [ $this, 'panel_add_markup' ] );

		// Save Membership Type on the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved/after', [ $this, 'panel_saved' ] );

		// Clear meta data from the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/variable/panel/saved/before', [ $this, 'panel_clear_meta' ], 20 );
		add_action( 'wpcv_woo_civi/product/custom/panel/saved/before', [ $this, 'panel_clear_meta' ], 20 );

		// Add Membership Type to the Product Variation "CiviCRM Settings".
		add_action( 'wpcv_woo_civi/product/variation/block/middle', [ $this, 'attributes_add_markup' ], 10, 4 );

		// Save Membership Type for the Product Variation.
		add_action( 'wpcv_woo_civi/product/variation/attributes/saved/after', [ $this, 'variation_saved' ], 10, 3 );

		/*
		// Add Membership data to the Product "Bulk Edit" and "Quick Edit" markup.
		add_action( 'wpcv_woo_civi/product/bulk_edit/after', [ $this, 'bulk_edit_add_markup' ] );
		add_action( 'wpcv_woo_civi/product/quick_edit/after', [ $this, 'quick_edit_add_markup' ] );
		*/

	}

	/**
	 * Gets the Membership Type ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @return integer|bool $membership_type_id The Membership Type ID, false otherwise.
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
	 * @param integer $product_id The Product ID.
	 * @param integer $membership_type_id The numeric ID of the Membership Type.
	 */
	public function set_product_meta( $product_id, $membership_type_id ) {
		update_post_meta( $product_id, $this->meta_key, $membership_type_id );
	}

	/**
	 * Gets the Membership Price Field Value ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @return integer|bool $membership_pfv_id The Membership Price Field Value ID, false otherwise.
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
	 * @param integer $product_id The Product ID.
	 * @param integer $membership_pfv_id The numeric ID of the Membership Price Field Value.
	 */
	public function set_pfv_meta( $product_id, $membership_pfv_id ) {
		update_post_meta( $product_id, $this->pfv_key, $membership_pfv_id );
	}

	/**
	 * Filters a Line Item to add a Membership Type.
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
	public function line_item_filter( $line_item, $item, $product, $order, $params ) {

		// Get Membership Type ID from Product meta.
		$membership_type_id = $product->get_meta( $this->meta_key );
		if ( empty( $membership_type_id ) ) {
			return $line_item;
		}

		// Get Membership Price Field Value ID from Product meta.
		$price_field_value_id = $product->get_meta( $this->pfv_key );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Make an array of the params.
		$args = [
			'item'                 => $item,
			'product'              => $product,
			'order'                => $order,
			'params'               => $params,
			'membership_type_id'   => $membership_type_id,
			'price_field_value_id' => $price_field_value_id,
		];

		// Populate the Line Item.
		$line_item = $this->line_item_populate( $line_item, $args );

		return $line_item;

	}

	/**
	 * Populates a Line Item to create a Membership.
	 *
	 * @since 3.0
	 *
	 * @param array $line_item The array of Line Item data.
	 * @param array $args The array of collected params.
	 * @return array $line_item The populated array of Line Item data.
	 */
	public function line_item_populate( $line_item, $args = [] ) {

		// Get default Membership Price Field ID.
		$default_price_field_id = $this->get_default_price_field_id();
		if ( empty( $default_price_field_id ) ) {
			return $line_item;
		}

		// Get Price Field data.
		$price_field = WPCV_WCI()->helper->get_price_field_by_price_field_value_id( $args['price_field_value_id'] );
		if ( empty( $price_field ) ) {
			return $line_item;
		}

		// Grab the Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		// If a specific Financial Type ID is supplied, use it.
		if ( ! empty( $args['financial_type_id'] ) ) {
			$line_item_data['financial_type_id'] = $args['financial_type_id'];
		}

		$line_item_params = [
			'membership_type_id' => $args['membership_type_id'],
			'source'             => __( 'Shop', 'wpcv-woo-civi-integration' ),
			'contact_id'         => $args['params']['contact_id'],
			'skipStatusCal'      => 1,
			'status_id'          => 'Pending',
		];

		$membership_line_item_data = [
			'entity_table'         => 'civicrm_membership',
			'price_field_id'       => $default_price_field_id,
			'price_field_value_id' => $args['price_field_value_id'],
		];

		// Maybe override the default Price Field ID.
		if ( ! empty( $price_field['price_field_id'] ) ) {
			$membership_line_item_data['price_field_id'] = $price_field['price_field_id'];
		}

		// Apply Membership to Line Item.
		$line_item = [
			'params'    => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $membership_line_item_data ),
			],
		];

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
			'version'    => 3,
			'sequential' => 1,
			'is_active'  => 1,
			'options'    => [
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

		$result = civicrm_api( 'MembershipType', 'get', $params );

		// Log and bail if something went wrong.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return [];
		}

		$membership_types = [];

		foreach ( $result['values'] as $key => $value ) {
			$membership_types['by_membership_type_id'][ $value['id'] ]               = $value;
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
			'options'   => [
				'limit' => 0,
			],
		];

		try {

			$result = civicrm_api3( 'MembershipType', 'get', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Types.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

			return [];

		}

		if ( empty( $result['count'] ) ) {
			return [];
		}

		$membership_types_options = [];
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
	 * @param integer $id The numeric ID of the CiviCRM Membership Type.
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

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Membership Type.', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

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
			'sequential'   => 1,
			'price_set_id' => 'default_membership_type_amount',
			'options'      => [
				'limit' => 1,
			],
		];

		try {

			$result = civicrm_api3( 'PriceField', 'get', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve default Membership Price Field ID', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $message );
			CRM_Core_Error::debug_log_message( $code );
			CRM_Core_Error::debug_log_message( $extra );

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );

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
	 * Adds the Membership option to the select on the "global" CiviCRM Settings Product Tab.
	 *
	 * @since 3.0
	 *
	 * @param array $entity_options The array of CiviCRM Entity Types.
	 * @return array $entity_options The modified array of CiviCRM Entity Types.
	 */
	public function panel_entity_option_add( $entity_options ) {
		$entity_options['civicrm_membership'] = __( 'CiviCRM Membership', 'wpcv-woo-civi-integration' );
		return $entity_options;
	}

	/**
	 * Adds the CiviCRM Membership and Price Field Value settings as meta to the Product.
	 *
	 * @since 2.4
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Save the Membership Type ID. Nonce has been verified by WooCommerce.
		if ( isset( $_POST[ $this->meta_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$membership_type_id = sanitize_key( $_POST[ $this->meta_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->add_meta_data( $this->meta_key, (int) $membership_type_id, true );
		}

		// Save the Membership Price Field Value ID.
		if ( isset( $_POST[ $this->pfv_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$membership_pfv_id = sanitize_key( $_POST[ $this->pfv_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->add_meta_data( $this->pfv_key, (int) $membership_pfv_id, true );
		}

	}

	/**
	 * Clears the metadata for this Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_clear_meta( $product ) {

		if ( ! $this->active ) {
			return;
		}

		// Clear the current global Product Membership metadata.
		$product->delete_meta_data( $this->meta_key );
		$product->delete_meta_data( $this->pfv_key );

	}

	/**
	 * Adds Membership data selects to the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	public function panel_add_markup() {

		global $thepostid, $post;

		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		// Bail if there aren't any Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();
		if ( empty( $price_sets ) ) {
			return;
		}

		// Get Price Field Value.
		$pfv_id = $this->get_pfv_meta( $product_id );

		?>
		<div class="options_group civicrm_membership">

			<?php

			$membership_types = [
				'' => __( 'Select a Membership Type', 'wpcv-woo-civi-integration' ),
			] + WPCV_WCI()->membership->get_membership_types_options();

			$args = [
				'id'          => $this->meta_key,
				'name'        => $this->meta_key,
				'label'       => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
				'desc_tip'    => 'true',
				'description' => __( 'Select a Membership Type if you would like this Product to create a Membership in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
				'options'     => $membership_types,
			];

			woocommerce_wp_select( $args );

			?>

			<p class="form-field">
				<label for="<?php echo esc_attr( $this->pfv_key ); ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
				<select name="<?php echo esc_attr( $this->pfv_key ); ?>" id="<?php echo esc_attr( $this->pfv_key ); ?>" class="select short">
					<option value="0"><?php esc_html_e( 'Select a Price Field', 'wpcv-woo-civi-integration' ); ?></option>
					<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
						<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
							<?php /* translators: 1: The Price Set title, 2: The Price Set label. */ ?>
							<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
								<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
									<option value="<?php echo esc_attr( $price_field_value_id ); ?>" <?php selected( $price_field_value_id, $pfv_id ); ?>><?php echo esc_html( $price_field_value['label'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</select> <?php echo wc_help_tip( esc_html__( 'Select The Price Field for the Membership.', 'wpcv-woo-civi-integration' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

		</div>
		<?php

	}

	/**
	 * Adds Membership to the Product Variation "CiviCRM Settings".
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array   $variation_data The Product Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 * @param string  $entity The CiviCRM Entity that this Product Variation is mapped to.
	 */
	public function attributes_add_markup( $loop, $variation_data, $variation, $entity ) {

		// TODO: We may still want to include these for Product Type switching.

		// Bail if this is not a CiviCRM Membership.
		if ( 'civicrm_membership' !== $entity ) {
			return;
		}

		// Get the meta key.
		$type_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'type_id' );

		// Add loop item.
		$type_key .= '-' . $loop;

		// Build Membership Types options array.
		$membership_types = [
			'' => __( 'Select a Membership Type', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->membership->get_membership_types_options();

		// Get the Membership Type ID.
		$type_id = WPCV_WCI()->products_variable->get_meta( $variation->ID, $entity, 'type_id' );

		// Build args.
		$args = [
			'id'            => $type_key,
			'name'          => $type_key,
			'value'         => $type_id,
			'label'         => __( 'Membership Type', 'wpcv-woo-civi-integration' ),
			'desc_tip'      => 'true',
			'description'   => __( 'Select a Membership Type if you would like this Product Variation to create a Membership in CiviCRM. The Membership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'wpcv-woo-civi-integration' ),
			'wrapper_class' => 'form-row form-row-full variable_civicrm_type_id',
			'options'       => $membership_types,
		];

		// Show Participant Role.
		woocommerce_wp_select( $args );

	}

	/**
	 * Saves the Membership Type to the Product Variation "CiviCRM Settings".
	 *
	 * @since 3.0
	 *
	 * @param WC_Product_Variation $variation The Product Variation object.
	 * @param integer              $loop The position in the loop.
	 * @param string               $entity The CiviCRM Entity Type.
	 */
	public function variation_saved( $variation, $loop, $entity ) {

		// Bail if this is not a CiviCRM Membership.
		if ( 'civicrm_membership' !== $entity ) {
			return;
		}

		// Get the meta key.
		$type_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'type_id' );

		// Add loop item.
		$type_loop_key = $type_key . '-' . $loop;

		// Save the Membership Type. Nonce has been verified by WooCommerce.
		if ( isset( $_POST[ $type_loop_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_key( $_POST[ $type_loop_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$variation->add_meta_data( $type_key, $value, true );
		}

	}

	/**
	 * Adds Membership data selects to the Product "Bulk Edit" markup.
	 *
	 * @since 3.0
	 */
	public function bulk_edit_add_markup() {

		$membership_types = [
			'' => __( '- No Change -', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->membership->get_membership_types_options();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		?>
		<label class="wpcv_woo_civi_bulk_membership_type_id">
			<span class="title"><?php esc_html_e( 'Membership Type', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_bulk_membership_type_id" name="_civicrm_bulk_membership_type_id">
					<?php foreach ( $membership_types as $key => $value ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
		</label>

		<?php if ( ! empty( $price_sets ) ) : ?>
			<label class="wpcv_woo_civi_bulk_membership_pfv_id">
				<span class="title"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></span>
				<span class="input-text-wrap">
					<select class="civicrm_bulk_membership_pfv_id" name="_civicrm_bulk_membership_pfv_id">
						<option value=""><?php esc_html_e( '- No Change -', 'wpcv-woo-civi-integration' ); ?></option>
						<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
							<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
								<?php /* translators: 1: The Price Set title, 2: The Price Set label. */ ?>
								<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
									<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
										<option value="<?php echo esc_attr( $price_field_value_id ); ?>"><?php echo esc_html( $price_field_value['label'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
				</span>
			</label>
		<?php endif; ?>
		<?php

	}

	/**
	 * Adds Membership data selects to the Product "Quick Edit" markup.
	 *
	 * @since 3.0
	 */
	public function quick_edit_add_markup() {

		$membership_types = [
			'' => __( 'Not set', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->membership->get_membership_types_options();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		?>
		<div class="inline-edit-group wpcv_woo_civi_membership_type_id">
			<span class="title"><?php esc_html_e( 'Membership Type', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_membership_type_id" name="_civicrm_membership_type_id">
					<?php foreach ( $membership_types as $key => $value ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
		</div>

		<?php if ( ! empty( $price_sets ) ) : ?>
			<div class="inline-edit-group wpcv_woo_civi_membership_pfv_id">
				<span class="title"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></span>
				<span class="input-text-wrap">
					<select class="civicrm_membership_pfv_id" name="_civicrm_membership_pfv_id">
						<option value=""><?php esc_html_e( 'Not set', 'wpcv-woo-civi-integration' ); ?></option>
						<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
							<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
								<?php /* translators: 1: The Price Set title, 2: The Price Set label. */ ?>
								<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
									<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
										<option value="<?php echo esc_attr( $price_field_value_id ); ?>"><?php echo esc_html( $price_field_value['label'] ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
				</span>
			</div>
		<?php endif; ?>
		<?php

	}

}
