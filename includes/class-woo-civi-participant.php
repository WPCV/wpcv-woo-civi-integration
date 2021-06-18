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
	 * @var str $event_key The WooCommerce Product meta key.
	 */
	public $event_key = '_woocommerce_civicrm_event_id';

	/**
	 * WooCommerce Product meta key holding the CiviCRM Participant Role ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $role_key The WooCommerce Product meta key.
	 */
	public $role_key = '_woocommerce_civicrm_participant_role_id';

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

		// Add Participant Role select to the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/civicrm/after', [ $this, 'panel_add_markup' ] );

		// AJAX handler for Event searches.
		add_action( 'wp_ajax_wpcv_woo_civi_search_events', [ $this, 'panel_search_events' ] );

		// Save Participant Role on the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved', [ $this, 'panel_saved' ] );

		// Add Participant to Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 30, 5 );

	}

	/**
	 * Gets the CiviCRM Event ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $event_id The CiviCRM Event ID, false otherwise.
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
	 * @param int $product_id The Product ID.
	 * @param int $event_id The numeric ID of the CiviCRM Event.
	 */
	public function set_event_meta( $product_id, $event_id ) {
		update_post_meta( $product_id, $this->event_key, (int) $event_id );
	}

	/**
	 * Gets the Participant Role ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The Product ID.
	 * @return int|bool $participant_role_id The Participant Role ID, false otherwise.
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
	 * @param int $product_id The Product ID.
	 * @param int $participant_role_id The numeric ID of the Participant Role.
	 */
	public function set_role_meta( $product_id, $participant_role_id ) {
		update_post_meta( $product_id, $this->role_key, (int) $participant_role_id );
	}

	/**
	 * Adds Participant Role select to the "CiviCRM Settings" Product Tab.
	 *
	 * @since 3.0
	 */
	public function panel_add_markup() {

		global $thepostid, $post;

		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		?>

		<p class="form-field">
			<label for="<?php echo $this->event_key; ?>"><?php esc_html_e( 'Event', 'wpcv-woo-civi-integration' ); ?></label>
			<select class="wc-product-search" id="<?php echo $this->event_key; ?>" name="<?php echo $this->event_key; ?>" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for a CiviCRM Event&hellip;', 'wpcv-woo-civi-integration' ); ?>" data-action="wpcv_woo_civi_search_events">
				<option value=""><?php esc_html_e( 'None', 'wpcv-woo-civi-integration' ); ?></option>
				<?php $options = $this->get_event_options(); ?>
				<?php $selected = $this->get_event_meta( $product_id ); ?>
				<?php foreach ( $options as $event_id => $event_name ) : ?>
					<option value="<?php echo esc_attr( $event_id ); ?>" <?php selected( $selected, $event_id ); ?>>
						<?php echo esc_attr( $event_name ); ?>
					</option>
				<?php endforeach; ?>
			</select> <?php echo wc_help_tip( __( 'Select an Event if you would like this Product to create an Event Participant in CiviCRM.', 'wpcv-woo-civi-integration' ) ); // WPCS: XSS ok. ?>
		</p>

		<?php

		// Show Participant Role.
		woocommerce_wp_select( [
			'id' => $this->role_key,
			'name' => $this->role_key,
			'label' => __( 'Participant Role', 'wpcv-woo-civi-integration' ),
			'desc_tip' => 'true',
			'description' => __( 'Select a Participant Role for the Event Participant.', 'wpcv-woo-civi-integration' ),
			'options' => $this->get_participant_roles_options(),
		] );

	}

	/**
	 * Adds the CiviCRM Event and  Participant Role settings as meta to the Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
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
			foreach( $events AS $event ) {

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
	 * Adds the CiviCRM Event and Participant Role settings as meta to the Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Save the Event ID.
		if ( isset( $_POST[$this->event_key] ) ) {
			$event_id = sanitize_key( $_POST[$this->event_key] );
			$product->add_meta_data( $this->event_key, (int) $event_id, true );
		}

		// Save the Participant Role ID.
		if ( isset( $_POST[$this->role_key] ) ) {
			$participant_role_id = sanitize_key( $_POST[$this->role_key] );
			$product->add_meta_data( $this->role_key, (int) $participant_role_id, true );
		}

	}

	/**
	 * Filters a Line Item to create an Event Participant.
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

		// Get Participant Role ID from Product meta.
		$participant_role_id = $product->get_meta( $this->role_key );
		if ( empty( $participant_role_id ) ) {
			return $line_item;
		}

		// Get Event ID from Product meta.
		$event_id = $product->get_meta( $this->event_key );
		if ( empty( $event_id ) ) {
			return $line_item;
		}

		// Grab the existing Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		/*
		 * FIXME: Refine "Source" for Event signups, e.g.
		 *
		 * "Rain-forest Cup Youth Soccer Tournament: Shop registration"
		 */
		$line_item_params = [
			'event_id' => $event_id,
			'contact_id' => $params['contact_id'],
			'role_id' => $participant_role_id,
			'source' => __( 'Shop', 'wpcv-woo-civi-integration' ),
		];

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
		if ( in_array( $order->get_payment_method(), $pay_later_methods ) ) {
			$line_item_params['status_id'] = 'Pending from pay later';
		}

		// TODO: Are there other params for the Line Item data?
		$participant_line_item_data = [
			'entity_table' => 'civicrm_participant',
		];

		// Apply Participant to Line Item.
		$line_item = [
			'params' => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $participant_line_item_data ),
			],
		];

		// Store the fact that there's an Event Participant with this Product.
		$this->has_participant[ $product->get_id() ] = [ $event_id, $participant_role_id ];

		return $line_item;

	}

	/**
	 * Get a number of CiviCRM Events.
	 *
	 * @since 3.0
	 *
	 * @param int $limit A number to limit the Event query to.
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
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
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
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
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
			'version' =>'3',
			'name' =>'participant_role'
		];

		try {

			$option_group = civicrm_api( 'OptionGroup', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

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
			'version' =>'3',
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

		$participant_roles_options = [
			0 => __( 'None', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $roles as $key => $value ) {
			$participant_roles_options[ $value['value'] ] = $value['name'];
		}

		return $participant_roles_options;

	}

}
