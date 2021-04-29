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
	 * CiviCRM Campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM Campaigns.
	 */
	public $campaigns = [];

	/**
	 * The complete set of CiviCRM Campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $all_campaigns The complete set of CiviCRM Campaigns.
	 */
	public $all_campaigns = [];

	/**
	 * CiviCRM Campaign Statuses.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM Campaign Statuses.
	 */
	public $campaigns_status = [];

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

		// Bail early if the CiviCRMCampaign component is not active.
		if ( ! WPCV_WCI()->helper->is_component_enabled( 'CiviCampaign' ) ) {
			// FIXME
			//return;
		}

		// Hook into new WooCommerce Orders with CiviCRM data.
		add_action( 'wpcv_woo_civi/order/form/new', [ $this, 'order_new' ], 10, 2 );

		// Add CiviCRM options to Edit Order screen.
		add_action( 'wpcv_woo_civi/order/form/before', [ $this, 'order_data_additions' ] );

		// Add Campaign ID to Order.
		add_filter( 'wpcv_woo_civi/order/create/params', [ $this, 'campaign_get_for_order' ], 20, 2 );

		// Add Campaign ID to plugin settings fields.
		add_filter( 'wpcv_woo_civi/admin_settings/fields/selects', [ $this, 'campaign_settings_add' ] );

		// Show Campaign on Orders listing screen.
		add_filter( 'manage_shop_order_posts_columns', [ $this, 'columns_head' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'columns_content' ], 10, 2 );

		// Allow Orders to be filtered by Campaign.
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_orders' ], 5 );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 100 );

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

		// Return early if already calculated.
		if ( isset( $this->campaigns ) ) {
			return $this->campaigns;
		}

		$this->campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->campaigns;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name' ],
			'is_active' => 1,
			'status_id' => [ 'NOT IN' => [ 'Completed', 'Cancelled' ] ],
			'options' => [
				'sort' => 'name',
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

		$result = civicrm_api3( 'Campaign', 'get', $params );

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

			return $this->campaigns;

		}

		foreach ( $result['values'] as $key => $value ) {
			$this->campaigns[ $value['id'] ] = $value['name'];
		}

		return $this->campaigns;

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

		// Return early if already calculated.
		if ( ! empty( $this->all_campaigns ) ) {
			return $this->all_campaigns;
		}

		$this->all_campaigns = [
			__( 'None', 'wpcv-woo-civi-integration' ),
		];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->all_campaigns;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name', 'status_id' ],
			'options' => [
				'sort' => 'status_id ASC, created_date DESC, name ASC',
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

		$result = civicrm_api3( 'Campaign', 'get', $params );

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

			return $this->all_campaigns;

		}

		$campaign_statuses = $this->get_campaigns_status();

		foreach ( $result['values'] as $key => $value ) {
			$status = '';
			if ( isset( $value['status_id'] ) && isset( $campaign_statuses[ $value['status_id'] ] ) ) {
				$status = ' - ' . $campaign_statuses[ $value['status_id'] ];
			}
			$this->all_campaigns[ $value['id'] ] = $value['name'] . $status;
		}

		return $this->all_campaigns;

	}

	/**
	 * Get a CiviCRM Campaign by its ID.
	 *
	 * @since 3.0
	 *
	 * @param int $campaign_id The numeric ID of the CiviCRM Campaign.
	 * @return array $campaign The array of data for CiviCRM Campaign, or false on failure.
	 */
	public function get_campaign_by_id( $campaign_id ) {

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'id' => $campaign_id,
			'options' => [
				'limit' => 1,
			],
		];

		$result = civicrm_api3( 'Campaign', 'get', $params );

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
	 * @return array $campaigns_status The array of CiviCRM Campaign Statuses.
	 */
	public function get_campaigns_status() {

		// Return early if already calculated.
		if ( ! empty( $this->campaigns_status ) ) {
			return $this->campaigns_status;
		}

		$this->campaigns_status = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->campaigns_status;
		}

		$params = [
			'sequential' => 1,
			'option_group_id' => 'campaign_status',
			'options' => [
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

		$result = civicrm_api3( 'OptionValue', 'get', $params );

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

			return $this->campaigns_status;

		}

		foreach ( $result['values'] as $key => $value ) {
			$this->campaigns_status[ $value['value'] ] = $value['label'];
		}

		return $this->campaigns_status;

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
			'name' => __( 'Default Campaign', 'wpcv-woo-civi-integration' ),
			'type' => 'select',
			'options' => $this->get_campaigns(),
			'id'   => 'woocommerce_civicrm_campaign_id',
		];

		return $options;

	}

	/**
	 * Gets the CiviCRM Campagin ID for an Order.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function campaign_get_for_order( $params, $order ) {

		// Get the global CiviCRM Campaign ID.
		$default_campaign_id = get_option( 'woocommerce_civicrm_campaign_id', false );

		// Use the local CiviCRM Campaign ID if possible.
		$local_campaign_id = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );
		if ( ! empty( $local_campaign_id ) ) {
			$default_campaign_id = $local_campaign_id;
		}

		if ( ! empty( $default_campaign_id ) ) {
			$params['campaign_id'] = (int) $default_campaign_id;
		}

		return $params;

	}

	/**
	 * Performs necessary actions when a WooCommerce Order is created.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param object $order The Order object.
	 */
	public function order_new( $order_id, $order ) {

		// Add the Campaign ID to the Order.
		$current_campaign_id = get_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', true );
		$new_campaign_id = filter_input( INPUT_POST, 'order_civicrmcampaign', FILTER_VALIDATE_INT );
		if ( false !== $new_campaign_id && $new_campaign_id !== $current_campaign_id ) {
			$this->campaign_update( $order_id, $current_campaign_id, $new_campaign_id );
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $new_campaign_id ) );
		}

	}

	/**
	 * Update Campaign.
	 *
	 * @since 2.0
	 *
	 * @param int $order_id The Order ID.
	 * @param string $old_campaign_id The old Campaign.
	 * @param string $new_campaign_id The new Campaign.
	 * @return bool True if successful, or false on failure.
	 */
	public function campaign_update( $order_id, $old_campaign_id, $new_campaign_id ) {

		// Bail if no Campaign is passed in.
		if ( empty( $old_campaign_id ) && empty( $new_campaign_id ) ) {
			return true;
		}

		// Bail if the Campaign has not changed.
		if ( (int) $old_campaign_id == (int) $new_campaign_id ) {
			return true;
		}

		// Bail if the new Campaign cannot be found.
		$new_campaign = $this->get_campaign_by_id( $new_campaign_id );
		if ( $new_campaign_id === false ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Campaign', 'wpcv-woo-civi-integration' ) );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'order_id' => $order_id,
				'old_campaign_id' => $old_campaign_id,
				'new_campaign_id' => $new_campaign_id,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		// Get Campaign name.
		$campaign_name = '';
		if ( ! empty( $new_campaign['name'] ) ) {
			$campaign_name = $new_campaign['name'];
		}

		// Get Contribution.
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );
		$contribution = WPCV_WCI()->helper->get_contribution_by_invoice_id( $invoice_id );

		// Bail on failure.
		if ( $contribution === false ) {
			return false;
		}

		// Set Campaign.
		// FIXME: Is campaign_id really the Campaign name?
		$contribution['campaign_id'] = $campaign_name;

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		try {

			$result = civicrm_api3( 'Contribution', 'create', $contribution );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to update Contribution', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'contribution' => $contribution,
				'backtrace' => $trace,
			], true ) );

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

		$nb_cols = count( $defaults );
		$new_cols = [
			'campaign' => __( 'Campaign', 'wpcv-woo-civi-integration' ),
		];
		$columns = array_slice( $defaults, 0, $nb_cols - 2, true )
			+ $new_cols
			+ array_slice( $defaults, $nb_cols - 2, $nb_cols, true );

		return $columns;

	}

	/**
	 * Echo the content of a row in a given column.
	 *
	 * @since 2.0
	 *
	 * @param string $column_name The column name.
	 * @param int $post_id The WordPress Post ID.
	 */
	public function columns_content( $column_name, $post_id ) {

		if ( 'campaign' !== $column_name ) {
			return;
		}

		$campaign_id = get_post_meta( $post_id, '_woocommerce_civicrm_campaign_id', true );
		if ( empty( $campaign_id ) ) {
			return;
		}

		$campaign = $this->get_campaign_by_id( $campaign_id );
		if ( empty( $campaign ) ) {
			return;
		}

		echo ( ! empty( $campaign['name'] ) ) ? esc_attr( $campaign['name'] ) : '';

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

		$campaign_list = WPCV_WCI()->campaign->get_all_campaigns();

		if ( $campaign_list && ! empty( $campaign_list ) && is_array( $campaign_list ) ) {
			$selected = filter_input( INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT );

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
		if ( empty( $campaign_id ) ) {
			return;
		}

		// Modify meta query.
		$mq = $query->get( 'meta_query' );
		$meta_query = false !== $mq ? [ 'relation' => 'AND', $mq ] : [];

		// Add Campaign meta query.
		$meta_query['campaign_clause'] = [
			'key' => '_woocommerce_civicrm_campaign_id',
			'value' => $campaign_id,
			'compare' => '==',
		];

		$query->set( 'meta_query', $meta_query );

	}

	/**
	 * Adds a form field to set a Campaign.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_data_additions( $order ) {

		wp_enqueue_script(
			'wccivi_admin_order',
			WPCV_WOO_CIVI_URL . 'assets/js/admin_order.js',
			[ 'jquery' ],
			WPCV_WOO_CIVI_VERSION,
			true
		);

		$order_campaign = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );

		// If there is no Campaign selected, select the default one as defined on our Settings page.
		if ( '' === $order_campaign || false === $order_campaign ) {
			// Get the global CiviCRM Campaign ID.
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' );
		}

		/**
		 * Filter the choice of Campaign List array to fetch.
		 *
		 * To fetch all Campaigns, return something other than 'campaigns'.
		 *
		 * @since 2.2
		 *
		 * @param str The array of Campaigns to fetch. Default 'campaigns'.
		 */
		$campaign_array = apply_filters( 'wpcv_woo_civi/campaign/campaign_list/get', 'campaigns' );

		if ( 'campaigns' === $campaign_array ) {
			$campaign_list = $this->get_campaigns();
		} else {
			$campaign_list = $this->get_all_campaigns();
		}

		?>
		<p class="form-field form-field-wide wc-civicrmcampaign">
			<label for="order_civicrmcampaign"><?php esc_html_e( 'CiviCRM Campaign', 'wpcv-woo-civi-integration' ); ?></label>
			<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="<?php esc_attr( __( 'CiviCRM Campaign', 'wpcv-woo-civi-integration' ) ); ?>">
				<option value=""></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
					<option value="<?php echo esc_attr( $campaign_id ); ?>" <?php selected( $campaign_id, $order_campaign, true ); ?>><?php echo esc_attr( $campaign_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php

	}

}
