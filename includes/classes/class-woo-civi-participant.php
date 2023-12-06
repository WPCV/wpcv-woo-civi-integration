<?php
/**
 * Event Participant class.
 *
 * Manages Event Participant integration between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Event Participant class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Participant {

	/**
	 * CiviCRM Event component status.
	 *
	 * True if the CiviEvent component is active, false by default.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $active The status of the CiviEvent component.
	 */
	public $active = false;

	/**
	 * WooCommerce Product meta key holding the CiviCRM Event ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $event_key The WooCommerce Product meta key.
	 */
	public $event_key = '_woocommerce_civicrm_event_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Participant Role ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $role_key The WooCommerce Product meta key.
	 */
	public $role_key = '_woocommerce_civicrm_participant_role_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Participant Price Field Value ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $pfv_key The CiviCRM Participant Price Field Value ID meta key.
	 */
	public $pfv_key = '_woocommerce_civicrm_participant_pfv_id';

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

		// Bail early if the CiviEvent component is not active.
		$this->active = WPCV_WCI()->helper->is_component_enabled( 'CiviEvent' );
		if ( ! $this->active ) {
			return;
		}

		// Add Participant to Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 30, 5 );

		// Add Entity Type to the options for the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/entity_options', [ $this, 'panel_entity_option_add' ], 20 );

		// Add Event and Participant Role to the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/civicrm/after', [ $this, 'panel_add_markup' ], 20 );

		// AJAX handler for Event searches.
		add_action( 'wp_ajax_wpcv_woo_civi_search_events', [ $this, 'panel_search_events' ] );

		// Save meta data from the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved/after', [ $this, 'panel_saved' ], 20 );

		// Clear meta data from the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/variable/panel/saved/before', [ $this, 'panel_clear_meta' ], 30 );
		add_action( 'wpcv_woo_civi/product/custom/panel/saved/before', [ $this, 'panel_clear_meta' ], 30 );

		// Add Event and Participant Role to the Product Variation "CiviCRM Settings".
		add_action( 'wpcv_woo_civi/product/variation/block/middle', [ $this, 'attributes_add_markup' ], 20, 4 );

		// Save Event and Participant Role for the Product Variation.
		add_action( 'wpcv_woo_civi/product/variation/attributes/saved/after', [ $this, 'variation_saved' ], 10, 3 );

		// Add Participant data to the Product "Bulk Edit" and "Quick Edit" markup.
		//add_action( 'wpcv_woo_civi/product/bulk_edit/after', [ $this, 'bulk_edit_add_markup' ] );
		//add_action( 'wpcv_woo_civi/product/quick_edit/after', [ $this, 'quick_edit_add_markup' ] );

	}

	/**
	 * Gets the CiviCRM Event ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @return integer|bool $event_id The CiviCRM Event ID, false otherwise.
	 */
	public function get_event_meta( $product_id ) {
		$event_id = get_post_meta( $product_id, $this->event_key, true );
		return (int) $event_id;
	}

	/**
	 * Sets the CiviCRM Event ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param integer $event_id The numeric ID of the CiviCRM Event.
	 */
	public function set_event_meta( $product_id, $event_id ) {
		update_post_meta( $product_id, $this->event_key, (int) $event_id );
	}

	/**
	 * Gets the Participant Role ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @return integer|bool $participant_role_id The Participant Role ID, false otherwise.
	 */
	public function get_role_meta( $product_id ) {
		$participant_role_id = get_post_meta( $product_id, $this->role_key, true );
		return (int) $participant_role_id;
	}

	/**
	 * Sets the CiviCRM Participant Role ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param integer $participant_role_id The numeric ID of the Participant Role.
	 */
	public function set_role_meta( $product_id, $participant_role_id ) {
		update_post_meta( $product_id, $this->role_key, (int) $participant_role_id );
	}

	/**
	 * Gets the Participant Price Field Value ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @return integer|bool $participant_pfv_id The Participant Price Field Value ID, false otherwise.
	 */
	public function get_pfv_meta( $product_id ) {
		$participant_pfv_id = get_post_meta( $product_id, $this->pfv_key, true );
		return $participant_pfv_id;
	}

	/**
	 * Sets the CiviCRM Participant Price Field Value ID as meta data on a WooCommerce Product.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param integer $participant_pfv_id The numeric ID of the Participant Price Field Value.
	 */
	public function set_pfv_meta( $product_id, $participant_pfv_id ) {
		update_post_meta( $product_id, $this->pfv_key, $participant_pfv_id );
	}

	/**
	 * Filters a Line Item to create an Event Participant.
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

		// Get Event ID from Product meta.
		$event_id = $product->get_meta( $this->event_key );
		if ( empty( $event_id ) ) {
			return $line_item;
		}

		// Get Participant Role ID from Product meta.
		$participant_role_id = $product->get_meta( $this->role_key );
		if ( empty( $participant_role_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $product->get_meta( $this->pfv_key );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Make an array of the params.
		$args = [
			'item' => $item,
			'product' => $product,
			'order' => $order,
			'params' => $params,
			'event_id' => $event_id,
			'participant_role_id' => $participant_role_id,
			'price_field_value_id' => $price_field_value_id,
		];

		// Populate the Line Item.
		$line_item = $this->line_item_populate( $line_item, $args );

		return $line_item;

	}

	/**
	 * Populates a Line Item to create an Event Participant.
	 *
	 * @since 3.0
	 *
	 * @param array $line_item The array of Line Item data.
	 * @param array $args The array of collected params.
	 * @return array $line_item The populated array of Line Item data.
	 */
	public function line_item_populate( $line_item, $args = [] ) {

		// Get Price Field Value data.
		$price_field_value = WPCV_WCI()->helper->get_price_field_value_by_id( $args['price_field_value_id'] );
		if ( empty( $price_field_value ) ) {
			return $line_item;
		}

		// Get Price Field data.
		$price_field = WPCV_WCI()->helper->get_price_field_by_price_field_value_id( $args['price_field_value_id'] );
		if ( empty( $price_field ) ) {
			return $line_item;
		}

		// Grab the existing Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		// If a specific Financial Type ID is supplied, use it.
		if ( ! empty( $args['financial_type_id'] ) ) {
			$line_item_data['financial_type_id'] = $args['financial_type_id'];
		}

		// Init the params sub-array.
		$line_item_params = [
			'event_id' => $args['event_id'],
			'contact_id' => $args['params']['contact_id'],
			'role_id' => $args['participant_role_id'],
			'price_set_id' => $price_field['price_set_id'],
			'fee_level' => $price_field_value['label'],
			'fee_amount' => $line_item_data['line_total'],
		];

		// Add Tax if set.
		if ( ! empty( $line_item_data['tax_amount'] ) ) {
			$line_item_params['fee_amount'] = (float) $line_item_params['fee_amount'] + (float) $line_item_data['tax_amount'];
		}

		// Set a descriptive source.
		// NOTE: CiviCRM populates this with the Payment Method.
		$line_item_params['source'] = sprintf(
			/* translators: 1: Source text, 2: Product name */
			__( '%1$s: %2$s', 'wpcv-woo-civi-integration' ),
			WPCV_WCI()->source->source_generate(),
			$args['product']->get_name()
		);

		/*
		// Build source with CiviCRM Event data if we can.
		$event = $this->get_event_by_id( $event_id );
		if ( $event !== false ) {
			$line_item_params['source'] = sprintf(
				__( '%1$s: %2$s' ),
				WPCV_WCI()->source->source_generate(),
				$event['title']
			);
		}
		*/

		/*
		 * From the CiviCRM Order API docs:
		 *
		 * Before 5.20 there was a bug such that you had to pass in:
		 *
		 * "status_id": "Pending from incomplete transaction"
		 *
		 * Otherwise the participant was created as "Registered" even before the
		 * payment had been made.
		 *
		 * @see https://docs.civicrm.org/dev/en/latest/financial/orderAPI/
		 *
		 * Let's maintain compatibility with versions of CiviCRM prior to 5.20 by
		 * adding that status now.
		 */
		$line_item_params['status_id'] = 'Pending from incomplete transaction';

		// Override with "Pay Later" status when necessary.
		$pay_later_methods = get_option( 'woocommerce_civicrm_pay_later_gateways', [] );
		if ( in_array( $args['order']->get_payment_method(), $pay_later_methods ) ) {
			$line_item_params['status_id'] = 'Pending from pay later';
		}

		// TODO: Are there other params for the Line Item data?
		$participant_line_item_data = [
			'entity_table' => 'civicrm_participant',
			'price_field_id' => $price_field_value['price_field_id'],
			'price_field_value_id' => $args['price_field_value_id'],
			'label' => $price_field_value['label'],
		];

		// Apply Participant to Line Item.
		$line_item = [
			'params' => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $participant_line_item_data ),
			],
		];

		return $line_item;

	}

	/**
	 * Get the CiviCRM Event data for a given set of parameters.
	 *
	 * @since 3.0
	 *
	 * @param array $args The arguments to query CiviCRM Events by.
	 * @return array|boolean $events_data An array of Events data, or empty on failure.
	 */
	public function get_events_by( $args = [] ) {

		// Init return.
		$events_data = [];

		// Bail if we have no args.
		if ( empty( $args ) ) {
			return $events_data;
		}

		// Try and init CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $events_data;
		}

		// Define params query Events.
		$params = [
			'version' => 3,
			'is_public' => 1,
			'is_template' => 0,
			'options' => [
				'limit' => 0, // No limit.
				'sort' => [
					'start_date ASC', // Earliest Events first.
				],
			],
		] + $args;

		// Maybe merge in the options.
		$options = $params['options'];
		if ( ! empty( $args['options'] ) ) {
			$options = array_merge( $params['options'], $args['options'] );
		}

		// Merge in the arguments.
		$params = array_merge( $params, $args );

		// Add options.
		$params['options'] = $options;

		// Call the API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $events_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $events_data;
		}

		// The result set it what we want.
		$events_data = $result['values'];

		// --<
		return $events_data;

	}

	/**
	 * Get the CiviCRM Event data for a given ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $event_id The numeric ID of the CiviCRM Event to query.
	 * @return array|boolean $event_data An array of Event data, or false on failure.
	 */
	public function get_event_by_id( $event_id ) {

		// Init return.
		$event_data = false;

		// Bail if we have no Event ID.
		if ( empty( $event_id ) ) {
			return $event_data;
		}

		// Try and init CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $event_data;
		}

		// Define params to get queried Event.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $event_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $event_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $event_data;
		}

		// The result set should contain only one item.
		$event_data = array_pop( $result['values'] );

		// --<
		return $event_data;

	}

	/**
	 * Get a number of CiviCRM Events.
	 *
	 * @since 3.0
	 *
	 * @param integer $limit A number to limit the Event query to.
	 * @return array|boolean $event_data An array of Event data, or false on failure.
	 */
	public function get_events( $limit = 25 ) {

		// Init return.
		$event_data = false;

		// Try and init CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $event_data;
		}

		// Define params to get Events.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'is_public' => 1,
			'is_template' => 0,
			'options' => [
				'limit' => $limit,
			],
		];

		// Call the API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $event_data;
		}

		// Override return if there are results.
		if ( ! empty( $result['values'] ) ) {
			$event_data = $result['values'];
		}

		// --<
		return $event_data;

	}

	/**
	 * Get the CiviCRM Event options.
	 *
	 * @since 3.0
	 *
	 * @return array $event_options The CiviCRM Event options.
	 */
	public function get_event_options() {

		// Return early if already calculated.
		static $event_options;
		if ( isset( $event_options ) ) {
			return $event_options;
		}

		// Bail early if the CiviEvent component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		// Get the array of Events.
		$events = $this->get_events();
		if ( empty( $events ) ) {
			return [];
		}

		$event_options = [
			0 => __( 'None', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $events as $key => $value ) {
			$event_options[ $value['id'] ] = $value['title'];
		}

		return $event_options;

	}

	/**
	 * Get the CiviCRM Event data for a given search string.
	 *
	 * @since 0.5
	 *
	 * @param string $search The search string to query.
	 * @return array|boolean $event_data An array of Event data, or false on failure.
	 */
	public function get_by_search_string( $search ) {

		// Init return.
		$event_data = false;

		// Bail if we have no search string.
		if ( empty( $search ) ) {
			return $event_data;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $event_data;
		}

		// Define params to get queried Event.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'input' => $search,
			'title' => 'label',
			'search_field' => 'title',
			'label_field' => 'title',
			'options' => [
				'limit' => 25, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Event', 'getlist', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $event_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $event_data;
		}

		// --<
		return $result['values'];

	}

	/**
	 * Get all Participant Roles.
	 *
	 * @since 3.0
	 *
	 * @return array $participant_roles The array of CiviCRM Participant Role data.
	 */
	public function get_participant_roles() {

		// Return early if already calculated.
		static $participant_roles;
		if ( isset( $participant_roles ) ) {
			return $participant_roles;
		}

		// Bail early if the CiviEvent component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		// First, get Participant Role Option Group ID.
		$params = [
			'version' => 3,
			'name' => 'participant_role',
		];

		try {

			$option_group = civicrm_api( 'OptionGroup', 'getsingle', $params );

		} catch ( Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to retrieve CiviCRM Participant Role Option Group.', 'wpcv-woo-civi-integration' ) );
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

		// Now get the values for that Option Group.
		$params = [
			'version' => 3,
			'is_active' => 1,
			'option_group_id' => $option_group['id'],
			'options' => [
				'sort' => 'weight ASC',
			],
		];

		$result = civicrm_api( 'OptionValue', 'get', $params );

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

		$participant_roles = [];

		foreach ( $result['values'] as $key => $value ) {
			$participant_roles[ $value['id'] ] = $value;
		}

		return $participant_roles;

	}

	/**
	 * Get the CiviCRM Participant Roles options.
	 *
	 * @since 3.0
	 *
	 * @return array $participant_roles_options The CiviCRM Participant Roles options.
	 */
	public function get_participant_roles_options() {

		// Return early if already calculated.
		static $participant_roles_options;
		if ( isset( $participant_roles_options ) ) {
			return $participant_roles_options;
		}

		// Bail early if the CiviEvent component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		// Get the array of Participant Roles.
		$roles = $this->get_participant_roles();
		if ( empty( $roles ) ) {
			return [];
		}

		$participant_roles_options = [];
		foreach ( $roles as $key => $value ) {
			$participant_roles_options[ $value['value'] ] = $value['name'];
		}

		return $participant_roles_options;

	}

	/**
	 * Adds the Participant option to the select on the "global" CiviCRM Settings Product Tab.
	 *
	 * @since 3.0
	 *
	 * @param array $entity_options The array of CiviCRM Entity Types.
	 * @return array $entity_options The modified array of CiviCRM Entity Types.
	 */
	public function panel_entity_option_add( $entity_options ) {
		$entity_options['civicrm_participant'] = __( 'CiviCRM Participant', 'wpcv-woo-civi-integration' );
		return $entity_options;
	}

	/**
	 * Adds the CiviCRM Event and Participant Role settings as meta to the Product.
	 *
	 * @since 3.0
	 */
	public function panel_search_events() {

		check_ajax_referer( 'search-products', 'security' );

		$term = '';
		if ( isset( $_GET['term'] ) ) {
			$term = (string) wc_clean( wp_unslash( $_GET['term'] ) );
		}

		if ( empty( $term ) ) {
			wp_die();
		}

		// Get Events.
		$events = $this->get_by_search_string( $term );

		// Maybe append results.
		$results = [];
		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {

				// Add Event Date if present.
				$title = $event['label'];
				if ( ! empty( $event['description'][0] ) ) {
					$title .= '<br><em>' . $event['description'][0] . '</em>';
				}

				// TODO: Permission to view Event?

				// Append to results.
				$results[ $event['id'] ] = $title;

			}
		}

		wp_send_json( $results );

	}

	/**
	 * Adds the CiviCRM Event, Participant Role and Price Field Value settings as meta to the Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Save the Event ID.
		if ( isset( $_POST[ $this->event_key ] ) ) {
			$event_id = sanitize_key( $_POST[ $this->event_key ] );
			$product->add_meta_data( $this->event_key, (int) $event_id, true );
		}

		// Save the Participant Role ID.
		if ( isset( $_POST[ $this->role_key ] ) ) {
			$participant_role_id = sanitize_key( $_POST[ $this->role_key ] );
			$product->add_meta_data( $this->role_key, (int) $participant_role_id, true );
		}

		// Save the Participant Price Field Value ID.
		if ( isset( $_POST[ $this->pfv_key ] ) ) {
			$participant_pfv_id = sanitize_key( $_POST[ $this->pfv_key ] );
			$product->add_meta_data( $this->pfv_key, (int) $participant_pfv_id, true );
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

		// Clear the current global Product Participant metadata.
		$product->delete_meta_data( WPCV_WCI()->participant->event_key );
		$product->delete_meta_data( WPCV_WCI()->participant->role_key );
		$product->delete_meta_data( WPCV_WCI()->participant->pfv_key );

	}

	/**
	 * Adds Event and Participant Role to the "CiviCRM Settings" Product Tab.
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

		// Get an initial set of Events.
		$options = $this->get_event_options();

		// Get Event ID.
		$event_id = $this->get_event_meta( $product_id );

		// Get the Event data if set.
		$event = false;
		if ( ! empty( $event_id ) ) {
			$event = $this->get_event_by_id( $event_id );
		}

		// Maybe add to options.
		if ( $event !== false ) {
			$options[ $event_id ] = $event['title'];
		}

		// Get Price Field Value.
		$pfv_id = $this->get_pfv_meta( $product_id );

		?>
		<div class="options_group civicrm_participant">

			<p class="form-field">
				<label for="<?php echo $this->event_key; ?>"><?php esc_html_e( 'Event', 'wpcv-woo-civi-integration' ); ?></label>
				<select class="wc-product-search" id="<?php echo $this->event_key; ?>" name="<?php echo $this->event_key; ?>" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for a CiviCRM Event&hellip;', 'wpcv-woo-civi-integration' ); ?>" data-action="wpcv_woo_civi_search_events">
					<option value=""><?php esc_html_e( 'None', 'wpcv-woo-civi-integration' ); ?></option>
					<?php $selected = $this->get_event_meta( $product_id ); ?>
					<?php foreach ( $options as $event_id => $event_name ) : ?>
						<option value="<?php echo esc_attr( $event_id ); ?>" <?php selected( $selected, $event_id ); ?>>
							<?php echo esc_attr( $event_name ); ?>
						</option>
					<?php endforeach; ?>
				</select> <?php echo wc_help_tip( __( 'Select an Event if you would like this Product to create an Event Participant in CiviCRM.', 'wpcv-woo-civi-integration' ) ); ?>
			</p>

			<?php

			// Build Participant Roles options array.
			$participant_roles = [
				'' => __( 'Select a Participant Role', 'wpcv-woo-civi-integration' ),
			] + WPCV_WCI()->participant->get_participant_roles_options();

			// Show Participant Role.
			woocommerce_wp_select( [
				'id' => $this->role_key,
				'name' => $this->role_key,
				'label' => __( 'Participant Role', 'wpcv-woo-civi-integration' ),
				'desc_tip' => 'true',
				'description' => __( 'Select a Participant Role for the Event Participant.', 'wpcv-woo-civi-integration' ),
				'options' => $participant_roles,
			] );

			?>

			<p class="form-field">
				<label for="<?php echo $this->pfv_key; ?>"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></label>
				<select name="<?php echo $this->pfv_key; ?>" id="<?php echo $this->pfv_key; ?>" class="select short">
					<option value="0"><?php esc_html_e( 'Select a Price Field', 'wpcv-woo-civi-integration' ); ?></option>
					<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
						<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
							<optgroup label="<?php echo esc_attr( sprintf( __( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $price_set['title'], $price_field['label'] ) ); ?>">
								<?php foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) : ?>
									<option value="<?php echo esc_attr( $price_field_value_id ); ?>" <?php selected( $price_field_value_id, $pfv_id ); ?>><?php echo esc_html( $price_field_value['label'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</select> <?php echo wc_help_tip( __( 'Select The Price Field for the Event Participant.', 'wpcv-woo-civi-integration' ) ); ?>
			</p>

		</div>
		<?php

	}

	/**
	 * Adds Event and Participant Role to the Product Variation "CiviCRM Settings".
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array   $variation_data The Product Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 * @param string  $entity The CiviCRM Entity that this Product Variation is mapped to.
	 */
	public function attributes_add_markup( $loop, $variation_data, $variation, $entity ) {

		// TODO: We nay still want to include these for Product Type switching.

		// Bail if this is not a CiviCRM Participant.
		if ( $entity !== 'civicrm_participant' ) {
			return;
		}

		// Get the meta keys.
		$event_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'event_id' );
		$role_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'role_id' );

		// Add loop item.
		$event_key .= '-' . $loop;
		$role_key .= '-' . $loop;

		// Get an initial set of Events.
		$options = $this->get_event_options();

		// Get the Event ID.
		$event_id = WPCV_WCI()->products_variable->get_meta( $variation->ID, $entity, 'event_id' );

		// Get the Event data if set.
		$event = false;
		if ( ! empty( $event_id ) ) {
			$event = $this->get_event_by_id( $event_id );
		}

		// Maybe add to options.
		if ( $event !== false ) {
			$options[ $event_id ] = $event['title'];
		}

		// Build Participant Roles options array.
		$participant_roles = [
			'' => __( 'Select a Participant Role', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->participant->get_participant_roles_options();

		// Get the Participant Role ID.
		$role_id = WPCV_WCI()->products_variable->get_meta( $variation->ID, $entity, 'role_id' );

		?>
		<p class="form-row form-row-full variable_civicrm_event_id">
			<label for="<?php echo $event_key; ?>"><?php esc_html_e( 'Event', 'wpcv-woo-civi-integration' ); ?></label>
			<?php echo wc_help_tip( __( 'Select an Event if you would like this Product Variation to create an Event Participant in CiviCRM.', 'wpcv-woo-civi-integration' ) ); ?>
			<br>
			<style>
				.variable_civicrm_event_id .select2-container {
					width: 100% !important;
				}
			</style>
			<select class="wc-product-search" id="<?php echo $event_key; ?>" name="<?php echo $event_key; ?>" style="clear: left;" data-placeholder="<?php esc_attr_e( 'Search for a CiviCRM Event&hellip;', 'wpcv-woo-civi-integration' ); ?>" data-action="wpcv_woo_civi_search_events">
				<option value=""><?php esc_html_e( 'None', 'wpcv-woo-civi-integration' ); ?></option>
				<?php $selected = WPCV_WCI()->products_variable->get_meta( $variation->ID, $entity, 'event_id' ); ?>
				<?php foreach ( $options as $event_id => $event_name ) : ?>
					<option value="<?php echo esc_attr( $event_id ); ?>" <?php selected( $selected, $event_id ); ?>>
						<?php echo esc_attr( $event_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php

		// Show Participant Role.
		woocommerce_wp_select( [
			'id' => $role_key,
			'name' => $role_key,
			'value' => $role_id,
			'label' => __( 'Participant Role', 'wpcv-woo-civi-integration' ),
			'desc_tip' => 'true',
			'description' => __( 'Select a Participant Role for the Event Participant.', 'wpcv-woo-civi-integration' ),
			'wrapper_class' => 'form-row form-row-full variable_civicrm_role_id',
			'options' => $participant_roles,
		] );

		?>

		<?php

	}

	/**
	 * Saves the Event and Participant Role to the Product Variation "CiviCRM Settings".
	 *
	 * @since 3.0
	 *
	 * @param WC_Product_Variation $variation The Product Variation object.
	 * @param integer              $loop The position in the loop.
	 * @param string               $entity The CiviCRM Entity Type.
	 */
	public function variation_saved( $variation, $loop, $entity ) {

		// Bail if this is not a CiviCRM Participant.
		if ( $entity !== 'civicrm_participant' ) {
			return;
		}

		// Get the meta keys.
		$event_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'event_id' );
		$role_key = WPCV_WCI()->products_variable->get_meta_key( $entity, 'role_id' );

		// Add loop item.
		$event_loop_key = $event_key . '-' . $loop;
		$role_loop_key = $role_key . '-' . $loop;

		// Save the Event ID.
		if ( isset( $_POST[ $event_loop_key ] ) ) {
			$event_id = sanitize_key( $_POST[ $event_loop_key ] );
			$variation->add_meta_data( $event_key, (int) $event_id, true );
		}

		// Save the Participant Role ID.
		if ( isset( $_POST[ $role_loop_key ] ) ) {
			$participant_role_id = sanitize_key( $_POST[ $role_loop_key ] );
			$variation->add_meta_data( $role_key, (int) $participant_role_id, true );
		}

	}

	/**
	 * Adds Participant data selects to the Product "Bulk Edit" markup.
	 *
	 * @since 3.0
	 */
	public function bulk_edit_add_markup() {

		// Build Participant Roles options array.
		$participant_roles = [
			'' => __( '- No Change -', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->participant->get_participant_roles_options();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		?>
		<label class="wpcv_woo_civi_event_id">
			<span class="title"><?php esc_html_e( 'Event', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<?php esc_html_e( 'Edit the Product to set the Event', 'wpcv-woo-civi-integration' ); ?>
			</span>
		</label>

		<label class="wpcv_woo_civi_bulk_participant_role_id">
			<span class="title"><?php esc_html_e( 'Participant Role', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_bulk_participant_role_id" name="_civicrm_bulk_participant_role_id">
					<?php foreach ( $participant_roles as $key => $value ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
		</label>

		<?php if ( ! empty( $price_sets ) ) : ?>
			<label class="wpcv_woo_civi_bulk_participant_pfv_id">
				<span class="title"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></span>
				<span class="input-text-wrap">
					<select class="civicrm_bulk_participant_pfv_id" name="_civicrm_bulk_participant_pfv_id">
						<option value=""><?php esc_html_e( '- No Change -', 'wpcv-woo-civi-integration' ); ?></option>
						<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
							<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
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
	 * Adds Participant data selects to the Product "Quick Edit" markup.
	 *
	 * @since 3.0
	 */
	public function quick_edit_add_markup() {

		// Build Participant Roles options array.
		$participant_roles = [
			'' => __( 'Not set', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->participant->get_participant_roles_options();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		?>
		<div class="inline-edit-group wpcv_woo_civi_participant_event_id">
			<span class="title"><?php esc_html_e( 'Edit the Product to set the Event', 'wpcv-woo-civi-integration' ); ?></span>
		</div>

		<div class="inline-edit-group wpcv_woo_civi_participant_role_id">
			<span class="title"><?php esc_html_e( 'Participant Role', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_participant_role_id" name="_civicrm_participant_role_id">
					<?php foreach ( $participant_roles as $key => $value ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
		</div>

		<?php if ( ! empty( $price_sets ) ) : ?>
			<div class="inline-edit-group wpcv_woo_civi_participant_pfv_id">
				<span class="title"><?php esc_html_e( 'Price Field Value', 'wpcv-woo-civi-integration' ); ?></span>
				<span class="input-text-wrap">
					<select class="civicrm_participant_pfv_id" name="_civicrm_participant_pfv_id" id="_civicrm_participant_pfv_id" class="select short">
						<option value=""><?php esc_html_e( 'Not set', 'wpcv-woo-civi-integration' ); ?></option>
						<?php foreach ( $price_sets as $price_set_id => $price_set ) : ?>
							<?php foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) : ?>
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
