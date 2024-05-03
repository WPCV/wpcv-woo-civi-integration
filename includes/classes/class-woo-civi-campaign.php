<?php
/**
 * Campaign class.
 *
 * Manages Campaign integration between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Campaign class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Campaign {

	/**
	 * CiviCRM Campaign component status.
	 *
	 * True if the CiviCampaign component is active, false by default.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $active The status of the CiviCampaign component.
	 */
	public $active = false;

	/**
	 * WooCommerce Order meta key holding the CiviCRM Campaign ID.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_woocommerce_civicrm_campaign_id';

	/**
	 * Urchin Tracking Module management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $utm The Urchin Tracking Module management object.
	 */
	public $utm;

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

		// Bail early if the CiviCampaign component is not active.
		$this->active = WPCV_WCI()->helper->is_component_enabled( 'CiviCampaign' );
		if ( ! $this->active ) {
			return;
		}

		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this class is loaded.
		 *
		 * Used internally by included classes in order to bootstrap.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/campaign/loaded' );

	}

	/**
	 * Include class files.
	 *
	 * @since 3.0
	 */
	public function include_files() {

		// Include UTM class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-campaign-utm.php';

	}

	/**
	 * Setup objects.
	 *
	 * @since 3.0
	 */
	public function setup_objects() {

		// Init UTM object.
		$this->utm = new WPCV_Woo_Civi_UTM();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		/*
		// Allow Campaign to be set on new Orders in WordPress admin.
		add_action( 'wpcv_woo_civi/order/new', [ $this, 'order_new' ], 10, 2 );
		*/

		// Allow Campaign to be set on Order in WordPress admin.
		add_action( 'woocommerce_update_order', [ $this, 'order_updated' ], 20, 2 );

		// Add CiviCRM options to Edit Order screen.
		add_action( 'wpcv_woo_civi/order/form/before', [ $this, 'order_details_add' ], 20 );

		// Add Campaign ID to Order.
		add_filter( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'campaign_get_for_order' ], 20, 2 );

		// Add Campaign ID to plugin settings fields.
		add_filter( 'wpcv_woo_civi/woo_settings/fields/order/settings', [ $this, 'campaign_settings_add' ] );

		// Show Campaign on Orders listing screen.
		add_filter( 'manage_shop_order_posts_columns', [ $this, 'columns_head' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'columns_content' ], 10, 2 );

		// Allow Orders to be filtered by Campaign.
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_orders' ], 5 );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 100 );

	}

	/**
	 * Gets the CiviCRM Campaign ID from WooCommerce Order meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @return integer|bool $campaign_id The numeric ID of the CiviCRM Campaign, false otherwise.
	 */
	public function get_order_meta( $order_id ) {
		$campaign_id = get_post_meta( $order_id, $this->meta_key, true );
		return (int) $campaign_id;
	}

	/**
	 * Sets the CiviCRM Campaign ID as meta data on a WooCommerce Order.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param integer $campaign_id The numeric ID of the CiviCRM Campaign.
	 */
	public function set_order_meta( $order_id, $campaign_id ) {
		update_post_meta( $order_id, $this->meta_key, (int) $campaign_id );
	}

	/**
	 * Get CiviCRM Campaigns.
	 *
	 * Build multidimentional array of CiviCRM Campaigns, e.g.
	 * array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) )
	 *
	 * @since 2.2
	 *
	 * @return array $campaigns The array of data for CiviCRM Campaigns.
	 */
	public function get_campaigns() {

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Return early if already calculated.
		static $campaigns;
		if ( isset( $campaigns ) ) {
			return $campaigns;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return [];
		}

		$params = [
			'version'    => 3,
			'sequential' => 1,
			'is_active'  => 1,
			'status_id'  => [ 'NOT IN' => [ 'Completed', 'Cancelled' ] ],
			'options'    => [
				'sort'  => 'name',
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaigns params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaigns/get/params', $params );

		$result = civicrm_api( 'Campaign', 'get', $params );

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

		$campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $result['values'] as $key => $value ) {
			$campaigns[ $value['id'] ] = $value['title'];
		}

		return $campaigns;

	}

	/**
	 * Get all CiviCRM Campaigns with Status.
	 *
	 * Build multidimentional array of all CiviCRM Campaigns, e.g.
	 * array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 *
	 * @return array $all_campaigns The array of data for all CiviCRM Campaigns.
	 */
	public function get_all_campaigns() {

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Return early if already calculated.
		static $all_campaigns;
		if ( isset( $all_campaigns ) ) {
			return $all_campaigns;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		$params = [
			'version'    => 3,
			'sequential' => 1,
			'return'     => [ 'id', 'name', 'status_id' ],
			'options'    => [
				'sort'  => 'status_id ASC, created_date DESC, name ASC',
				'limit' => 0,
			],
		];

		/**
		 * Filter all Campaigns params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaigns/get_all/params', $params );

		$result = civicrm_api( 'Campaign', 'get', $params );

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

		$campaign_statuses = $this->get_campaign_statuses();
		if ( empty( $campaign_statuses ) ) {
			return [];
		}

		$all_campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $result['values'] as $key => $value ) {
			$status = '';
			if ( isset( $value['status_id'] ) && isset( $campaign_statuses[ $value['status_id'] ] ) ) {
				$status = ' - ' . $campaign_statuses[ $value['status_id'] ];
			}
			$all_campaigns[ $value['id'] ] = $value['name'] . $status;
		}

		return $all_campaigns;

	}

	/**
	 * Get a CiviCRM Campaign by its ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $campaign_id The numeric ID of the CiviCRM Campaign.
	 * @return array|bool $campaign The array of data for CiviCRM Campaign, or false on failure.
	 */
	public function get_campaign_by_id( $campaign_id ) {

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'version'    => 3,
			'sequential' => 1,
			'id'         => $campaign_id,
			'options'    => [
				'limit' => 1,
			],
		];

		$result = civicrm_api( 'Campaign', 'get', $params );

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
			return false;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The result set should contain only one item.
		$campaign = array_pop( $result['values'] );

		return $campaign;

	}

	/**
	 * Get a CiviCRM Campaign by its ID.
	 *
	 * @since 3.0
	 *
	 * @param integer $campaign_name The name of the CiviCRM Campaign.
	 * @return array|bool $campaign The array of data for CiviCRM Campaign, or false on failure.
	 */
	public function get_campaign_by_name( $campaign_name ) {

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return false;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'version'    => 3,
			'sequential' => 1,
			'name'       => $campaign_name,
			'options'    => [
				'limit' => 1,
			],
		];

		$result = civicrm_api( 'Campaign', 'get', $params );

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
			return false;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The result set should contain only one item.
		$campaign = array_pop( $result['values'] );

		return $campaign;

	}

	/**
	 * Get CiviCRM Campaign Statuses.
	 *
	 * Build multidimentional array of CiviCRM Campaign Statuses, e.g.
	 * array( 'status_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 *
	 * @return array $campaign_statuses The array of CiviCRM Campaign Statuses.
	 */
	public function get_campaign_statuses() {

		// Bail early if the CiviCampaign component is not active.
		if ( ! $this->active ) {
			return [];
		}

		// Return early if already calculated.
		static $campaign_statuses;
		if ( isset( $campaign_statuses ) ) {
			return $campaign_statuses;
		}

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return [];
		}

		$params = [
			'version'         => 3,
			'sequential'      => 1,
			'option_group_id' => 'campaign_status',
			'options'         => [
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaign Statuses params before calling the CiviCRM API.
		 *
		 * @since 2.2
		 *
		 * @param array $params The params to be passed to the CiviCRM API.
		 */
		$params = apply_filters( 'wpcv_woo_civi/campaign_statuses/get/params', $params );

		$result = civicrm_api( 'OptionValue', 'get', $params );

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

		$campaign_statuses = [];

		foreach ( $result['values'] as $key => $value ) {
			$campaign_statuses[ $value['value'] ] = $value['label'];
		}

		return $campaign_statuses;

	}

	/**
	 * Adds Campaign ID to the plugin settings fields.
	 *
	 * @since 3.0
	 *
	 * @param array $options The existing plugin settings fields.
	 * @return array $options The modified plugin settings fields.
	 */
	public function campaign_settings_add( $options ) {

		$options['woocommerce_civicrm_campaign_id'] = [
			'name'    => __( 'Default Campaign', 'wpcv-woo-civi-integration' ),
			'desc'    => __( 'The default Campaign can be overridden on individual Orders.', 'wpcv-woo-civi-integration' ),
			'type'    => 'select',
			'options' => $this->get_campaigns(),
			'id'      => 'woocommerce_civicrm_campaign_id',
		];

		return $options;

	}

	/**
	 * Gets the CiviCRM Campaign ID for an Order.
	 *
	 * @since 3.0
	 *
	 * @param array  $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function campaign_get_for_order( $params, $order ) {

		// Get the global CiviCRM Campaign ID.
		$campaign_id = get_option( 'woocommerce_civicrm_campaign_id', false );

		// Use the local CiviCRM Campaign ID if possible.
		$local_campaign_id = $this->get_order_meta( $order->get_id() );
		if ( ! empty( $local_campaign_id ) ) {
			$campaign_id = $local_campaign_id;
		}

		/**
		 * Filter the Campaign ID.
		 *
		 * Used internally by:
		 *
		 * - WPCV_Woo_Civi_UTM::utm_to_order() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param array  $campaign_id The calculated Campaign ID.
		 * @param object $order The WooCommerce Order object.
		 */
		$campaign_id = apply_filters( 'wpcv_woo_civi/campaign/get_for_order', $campaign_id, $order );

		// Store in Order meta and add to params.
		if ( ! empty( $campaign_id ) ) {
			$this->set_order_meta( $order->get_id(), $campaign_id );
			$params['campaign_id'] = (int) $campaign_id;
		}

		return $params;

	}

	/**
	 * Performs necessary actions when a WooCommerce Order is created.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param object  $order The Order object.
	 */
	public function order_new( $order_id, $order = null ) {

		// Retrieve the current and new Campaign ID.
		$current_campaign_id = $this->get_order_meta( $order_id );
		$new_campaign_id     = filter_input( INPUT_POST, 'order_civicrmcampaign', FILTER_VALIDATE_INT );
		$new_campaign_id     = (int) sanitize_text_field( wp_unslash( $new_campaign_id ) );

		// Update the Contribution.
		if ( ! empty( $new_campaign_id ) && $new_campaign_id !== (int) $current_campaign_id ) {
			$this->campaign_update( $order_id, $current_campaign_id, $new_campaign_id );
			$this->set_order_meta( $order_id, $new_campaign_id );
		}

	}

	/**
	 * Performs necessary actions when a WooCommerce Order is updated.
	 *
	 * The 'woocommerce_update_order' hook can fire more than once when an Order
	 * is updated, so we protect against that to avoid unnecessary updates to
	 * the CiviCRM Contribution.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param object  $order The Order object.
	 */
	public function order_updated( $order_id, $order = null ) {

		if ( ! is_admin() ) {
			return;
		}

		// This only needs to be done once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Sometimes the Order param is missing.
		if ( empty( $order ) ) {
			$order = wc_get_order( $order_id );
		}

		// Use same method as for new Orders for now.
		$this->order_new( $order_id, $order );

		// We're done.
		$done = true;

	}

	/**
	 * Update Campaign.
	 *
	 * @since 2.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param string  $old_campaign_id The old Campaign.
	 * @param string  $new_campaign_id The new Campaign.
	 * @return bool True if successful, or false on failure.
	 */
	public function campaign_update( $order_id, $old_campaign_id, $new_campaign_id ) {

		// Bail if no Campaign is passed in.
		if ( empty( $old_campaign_id ) && empty( $new_campaign_id ) ) {
			return true;
		}

		// Bail if the Campaign has not changed.
		if ( (int) $old_campaign_id === (int) $new_campaign_id ) {
			return true;
		}

		// Log and bail if something went wrong.
		$new_campaign = $this->get_campaign_by_id( $new_campaign_id );
		if ( false === $new_campaign_id ) {
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Campaign', 'wpcv-woo-civi-integration' ) );
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'          => __METHOD__,
				'order_id'        => $order_id,
				'old_campaign_id' => $old_campaign_id,
				'new_campaign_id' => $new_campaign_id,
				'backtrace'       => $trace,
			];
			WPCV_WCI()->log_error( $log );
			return false;
		}

		// Get Campaign name.
		$campaign_name = '';
		if ( ! empty( $new_campaign['name'] ) ) {
			$campaign_name = $new_campaign['name'];
		}

		// Get Contribution.
		$contribution = WPCV_WCI()->contribution->get_by_order_id( $order_id );
		if ( empty( $contribution ) ) {
			return false;
		}

		// Set Campaign.
		$contribution['campaign_id'] = $new_campaign_id;

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		// Remove financial data to prevent recalculation.
		$contribution = WPCV_WCI()->contribution->unset_amounts( $contribution );

		// Update Contribution.
		$contribution = WPCV_WCI()->contribution->update( $contribution );
		if ( empty( $contribution ) ) {
			return false;
		}

		// Success.
		return true;

	}

	/**
	 * Injects new column header.
	 *
	 * @since 3.0
	 *
	 * @param array $defaults The existing columns.
	 * @return array $columns The modified columns.
	 */
	public function columns_head( $defaults ) {

		$nb_cols  = count( $defaults );
		$new_cols = [
			'campaign' => __( 'Campaign', 'wpcv-woo-civi-integration' ),
		];
		$columns  = array_slice( $defaults, 0, $nb_cols - 2, true )
			+ $new_cols
			+ array_slice( $defaults, $nb_cols - 2, $nb_cols, true );

		return $columns;

	}

	/**
	 * Echo the content of a row in a given column.
	 *
	 * @since 2.0
	 *
	 * @param string  $column_name The column name.
	 * @param integer $post_id The WordPress Post ID.
	 */
	public function columns_content( $column_name, $post_id ) {

		if ( 'campaign' !== $column_name ) {
			return;
		}

		$campaign_id = $this->get_order_meta( $post_id );
		if ( empty( $campaign_id ) ) {
			return;
		}

		$campaign = $this->get_campaign_by_id( $campaign_id );
		if ( empty( $campaign ) ) {
			return;
		}

		// Use title if present.
		if ( ! empty( $campaign['title'] ) ) {
			echo esc_attr( $campaign['title'] );
			return;
		}

		// Fall back to name.
		echo esc_attr( $campaign['name'] );

	}

	/**
	 * Show dropdown for Campaigns.
	 *
	 * Fires before the Filter button on the Posts and Pages list tables.
	 *
	 * @since 2.0
	 *
	 * @param string $post_type The WordPress Post Type.
	 */
	public function restrict_manage_orders( $post_type = '' ) {

		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		$campaign_list = $this->get_all_campaigns();

		if ( $campaign_list && ! empty( $campaign_list ) && is_array( $campaign_list ) ) {
			$selected = filter_input( INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT );
			$selected = sanitize_text_field( wp_unslash( $selected ) );

			?>
			<select name='shop_order_campaign_id' id='dropdown_shop_order_campaign_id'>
				<option value=""><?php esc_html_e( 'All campaigns', 'wpcv-woo-civi-integration' ); ?></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
					<option value="<?php echo esc_attr( $campaign_id ); ?>" <?php selected( $selected, $campaign_id ); ?>>
						<?php echo esc_attr( $campaign_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php

		}

	}

	/**
	 * Filter the Posts Query.
	 *
	 * Fires after the query variable object is created, but before the actual query is run.
	 *
	 * @since 2.0
	 *
	 * @param WP_Query $query The WordPress Query object.
	 */
	public function pre_get_posts( $query ) {

		if ( ! is_admin() ) {
			return;
		}

		global $typenow;
		if ( 'shop_order' !== $typenow ) {
			return;
		}

		$campaign_id = filter_input( INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT );
		$campaign_id = (int) sanitize_text_field( wp_unslash( $campaign_id ) );
		if ( empty( $campaign_id ) ) {
			return;
		}

		// Modify meta query.
		$mq = $query->get( 'meta_query' );
		// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$meta_query = false !== $mq ? [ 'relation' => 'AND', $mq ] : [];

		// Add Campaign meta query.
		$meta_query['campaign_clause'] = [
			'key'     => $this->meta_key,
			'value'   => $campaign_id,
			'compare' => '==',
		];

		$query->set( 'meta_query', $meta_query );

	}

	/**
	 * Adds a form field to set a Campaign for the Order.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_details_add( $order ) {

		wp_enqueue_script(
			'wccivi_admin_order',
			WPCV_WOO_CIVI_URL . 'assets/js/woocommerce/admin/page-order-details-general.js',
			[ 'jquery' ],
			WPCV_WOO_CIVI_VERSION,
			true
		);

		// If there is no Campaign selected, select the plugin default.
		$order_campaign = $this->get_order_meta( $order->get_id() );
		if ( empty( $order_campaign ) ) {
			$global_campaign = get_option( 'woocommerce_civicrm_campaign_id' );
			if ( ! empty( $global_campaign ) ) {
				$order_campaign = $global_campaign;
			}
		}

		/**
		 * Filter the choice of Campaign List array to fetch.
		 *
		 * To fetch all Campaigns, return something other than 'campaigns'.
		 *
		 * @since 2.2
		 *
		 * @param string The array of Campaigns to fetch. Default 'campaigns'.
		 */
		$campaign_array = apply_filters( 'wpcv_woo_civi/campaign/campaign_list/get', 'campaigns' );

		if ( 'campaigns' === $campaign_array ) {
			$campaign_list = $this->get_campaigns();
		} else {
			$campaign_list = $this->get_all_campaigns();
		}

		?>
		<p class="form-field form-field-wide wc-civicrmcampaign">
			<label for="order_civicrmcampaign"><?php esc_html_e( 'CiviCRM Campaign:', 'wpcv-woo-civi-integration' ); ?></label>
			<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="<?php esc_attr_e( 'CiviCRM Campaign', 'wpcv-woo-civi-integration' ); ?>">
				<option value=""></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
					<option value="<?php echo esc_attr( $campaign_id ); ?>" <?php selected( $order_campaign, $campaign_id ); ?>><?php echo esc_html( $campaign_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php

	}

}
