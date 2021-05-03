<?php
/**
 * Source class.
 *
 * Manages Source integration between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Source class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Source {

	/**
	 * WooCommerce Order meta key holding the Source string.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $meta_key The WooCommerce Order meta key.
	 */
	public $meta_key = '_order_source';

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

		// Allow Source to be set on Orders in WordPress admin.
		add_action( 'wpcv_woo_civi/order/new', [ $this, 'order_updated' ], 10, 2 );
		add_action( 'woocommerce_update_order', [ $this, 'order_updated' ], 10, 2 );

		// Hook into WooCommerce Order processed.
		add_action( 'wpcv_woo_civi/order/processed', [ $this, 'order_processed' ], 10, 2 );

		// Add CiviCRM options to Edit Order screen.
		add_action( 'wpcv_woo_civi/order/form/before', [ $this, 'order_details_add' ] );

		// Add Source ID to Order.
		add_filter( 'wpcv_woo_civi/order/create/params', [ $this, 'source_get_for_order' ], 20, 2 );

		// Show Source on Orders listing screen.
		add_filter( 'manage_shop_order_posts_columns', [ $this, 'columns_head' ], 30 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'columns_content' ], 10, 2 );

		// Allow Orders to be filtered by Source.
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_orders' ], 5 );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 100 );

	}

	/**
	 * Gets the Source from WooCommerce Order meta.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @return str|bool $source The Source string, false otherwise.
	 */
	public function get_order_meta( $order_id ) {
		$source = (string) get_post_meta( $order_id, $this->meta_key, true );
		return $source;
	}

	/**
	 * Sets the CiviCRM Source as meta data on a WooCommerce Order.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param str $source The Source string.
	 */
	public function set_order_meta( $order_id, $source ) {
		update_post_meta( $order_id, $this->meta_key, (string) $source );
	}

	/**
	 * Called when a WooCommerce Order is updated in WordPress admin.
	 *
	 * This fires before "order_processed()" so things can get a bit confusing
	 * since "order_processed()" is called in both the Checkout and by the
	 * "New Order" screen in WordPress admin.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param object $order The Order object.
	 */
	public function order_updated( $order_id, $order ) {

		if ( ! is_admin() ) {
			return;
		}

		// Add the Source to Order.
		$current_source = $this->get_order_meta( $order_id );
		$new_source = filter_input( INPUT_POST, 'order_civicrmsource', FILTER_SANITIZE_STRING );

		// Generate the default Source if there isn't one.
		if ( empty( $new_source ) ) {
			$new_source = $this->source_generate( $order );
			$this->set_order_meta( $order_id, esc_attr( $new_source ) );
		}

		// Update the Contribution.
		if ( $new_source !== $current_source ) {
			$this->source_update( $order_id, $new_source );
		}

	}

	/**
	 * Performs necessary actions when a WooCommerce Order has been processed.
	 *
	 * @since 3.0
	 *
	 * @param int $order_id The Order ID.
	 * @param object $order The Order object.
	 */
	public function order_processed( $order_id, $order ) {

		// Generate the default Source if there isn't one.
		$source = $this->get_order_meta( $order_id );
		if ( empty( $source ) ) {
			$source = $this->source_generate( $order );
			$this->set_order_meta( $order_id, $source );
		}

		// Update the Contribution.
		$this->source_update( $order_id, $source );

	}

	/**
	 * Update Source.
	 *
	 * @since 2.0
	 *
	 * @param int $order_id The Order ID.
	 * @param string $new_source The new Source.
	 * @return bool True if successful, or false on failure.
	 */
	public function source_update( $order_id, $new_source ) {

		// Bail if no Campaign is passed in.
		if ( empty( $new_source ) ) {
			return false;
		}

		// Get Contribution.
		$invoice_id = WPCV_WCI()->helper->get_invoice_id( $order_id );
		$contribution = WPCV_WCI()->helper->get_contribution_by_invoice_id( $invoice_id );

		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'order_id' => $order_id,
			'contribution' => $contribution,
			//'backtrace' => $trace,
		], true ) );

		if ( empty( $contribution ) ) {
			return false;
		}

		// Set Source.
		$contribution['source'] = $new_source;

		// Ignore Contribution Note if already present.
		if ( ! empty( $contribution['contribution_note'] ) ) {
			unset( $contribution['contribution_note'] );
		}

		// Update Contribution.
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
	 * Generates a string to define a Contribution Source.
	 *
	 * @since 2.2
	 *
	 * @param object $order The Order object.
	 * @return string $source The Contribution Source string.
	 */
	public function source_generate( $order ) {

		// Default is the Order Type.
		// Until 2.2, Contribution Source was exactly the same as Contribution note.
		// TODO: What should be the default here?
		$source = __( 'Shop', 'wpcv-woo-civi-integration' );

		/**
		 * Filter the Contribution Source string.
		 *
		 * @since 3.0
		 *
		 * @param str $source The Contribution Source string.
		 * @param object $order The Order object.
		 */
		$source = apply_filters( 'wpcv_woo_civi/order/source/generate', $source, $order );

		return $source;

	}

	/**
	 * Gets the Source for an Order.
	 *
	 * @since 3.0
	 *
	 * @param array $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function source_get_for_order( $params, $order ) {

		$params['source'] = $this->source_generate( $order );

		return $params;

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
			'source' => __( 'Source', 'wpcv-woo-civi-integration' ),
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

		if ( 'source' !== $column_name ) {
			return;
		}

		echo esc_html( $this->get_order_meta( $post_id ) );

	}

	/**
	 * Show dropdown for Sources.
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

		global $wpdb;
		$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '{$this->meta_key}'" );
		if ( count( $results ) > 0 ) {
			$selected = filter_input( INPUT_GET, 'shop_order_source' );

			?>
			<select name='shop_order_source' id='dropdown_shop_order_source'>
				<option value=""><?php esc_html_e( 'All sources', 'wpcv-woo-civi-integration' ); ?></option>
				<?php foreach ( $results as $meta ) : ?>
					<option value="<?php echo esc_attr( $meta->meta_value ); ?>" <?php selected( $selected, $meta->meta_value ); ?>>
						<?php echo esc_attr( $meta->meta_value ); ?>
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

		$source = filter_input( INPUT_GET, 'shop_order_source' );
		if ( empty( $source ) ) {
			return;
		}

		// Modify meta query.
		$mq = $query->get( 'meta_query' );
		$meta_query = false !== $mq ? [ 'relation' => 'AND', $mq ] : [];

		// Add Source meta query.
		$meta_query['source_clause'] = [
			'key' => $this->meta_key,
			'value' => $source,
			'compare' => '==',
		];

		$query->set( 'meta_query', $meta_query );

	}

	/**
	 * Adds a form field to set a Source for the Order.
	 *
	 * @since 2.2
	 *
	 * @param object $order The WooCommerce Order object.
	 */
	public function order_details_add( $order ) {

		// Generate the default Source if there isn't one.
		$source = $this->get_order_meta( $order->get_id() );
		if ( empty( $source ) ) {
			$source = $this->source_generate( $order );
		}

		// Query database directly.
		global $wpdb;
		$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '{$this->meta_key}'" );

		?>
		<p class="form-field form-field-wide wc-civicrmsource">
			<label for="order_civicrmsource"><?php esc_html_e( 'CiviCRM Source:', 'wpcv-woo-civi-integration' ); ?></label>
			<input type="text" list="sources" id="order_civicrmsource" name="order_civicrmsource" data-placeholder="<?php esc_attr_e( 'CiviCRM Source', 'wpcv-woo-civi-integration' ); ?>" value="<?php echo esc_attr( $source ); ?>">
			<datalist id="sources">
				<?php if ( count( $results ) > 0 ) : ?>
					<?php foreach ( $results as $meta ) : ?>
						<option value="<?php echo esc_attr( $meta->meta_value ); ?>"></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</datalist>
		</p>
		<?php

	}

}
