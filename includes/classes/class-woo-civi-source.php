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
	 * @var string $meta_key The WooCommerce Order meta key.
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

		/*
		// Allow Source to be set on new Orders in WordPress admin.
		add_action( 'wpcv_woo_civi/order/new', [ $this, 'order_new' ], 10, 2 );
		*/

		// Allow Source to be set on Orders in WordPress admin.
		add_action( 'woocommerce_update_order', [ $this, 'order_updated' ], 10, 2 );

		/*
		// Hook into WooCommerce Order processed.
		add_action( 'wpcv_woo_civi/order/processed', [ $this, 'order_processed' ], 10, 2 );
		*/

		// Add CiviCRM options to Edit Order screen.
		add_action( 'wpcv_woo_civi/order/form/before', [ $this, 'order_details_add' ] );

		// Add Source to Order.
		add_filter( 'wpcv_woo_civi/contribution/create_from_order/params', [ $this, 'source_get_for_order' ], 10, 2 );

		/*
		// Add Source to plugin settings fields.
		add_filter( 'wpcv_woo_civi/woo_settings/fields/contribution/settings', [ $this, 'source_settings_add' ] );
		*/

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
	 * @param integer $order_id The Order ID.
	 * @return string|bool $source The Source string, false otherwise.
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
	 * @param integer $order_id The Order ID.
	 * @param string  $source The Source string.
	 */
	public function set_order_meta( $order_id, $source ) {
		update_post_meta( $order_id, $this->meta_key, (string) $source );
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

		// Retrieve the current Source.
		$current_source = $this->get_order_meta( $order_id );

		// Get new Source.
		$new_source = filter_input( INPUT_POST, 'order_civicrmsource' );
		$new_source = sanitize_text_field( wp_unslash( $new_source ) );

		// Generate the new Source if there isn't one.
		if ( empty( $new_source ) ) {
			if ( empty( $order ) ) {
				$order = wc_get_order( $order_id );
			}
			$new_source = $this->source_generate( $order );
		}

		// Update the Contribution.
		if ( $new_source !== $current_source ) {
			$this->source_update( $order_id, $new_source );
			$this->set_order_meta( $order_id, esc_attr( $new_source ) );
		}

	}

	/**
	 * Called when a WooCommerce Order is updated in WordPress admin.
	 *
	 * This fires before "order_processed()" so things can get a bit confusing
	 * since "order_processed()" is called in both the Checkout and by the
	 * "New Order" screen in WordPress admin.
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
	 * Performs necessary actions when a WooCommerce Order has been processed.
	 *
	 * @since 3.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param object  $order The Order object.
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
	 * Updates the Source of a Contribution.
	 *
	 * @since 2.0
	 *
	 * @param integer $order_id The Order ID.
	 * @param string  $new_source The new Source.
	 * @return bool True if successful, or false on failure.
	 */
	public function source_update( $order_id, $new_source ) {

		// Bail if no Source is passed in.
		if ( empty( $new_source ) ) {
			return false;
		}

		// Get Contribution.
		$contribution = WPCV_WCI()->contribution->get_by_order_id( $order_id );
		if ( empty( $contribution ) ) {
			return false;
		}

		// Set Source.
		$contribution['source'] = $new_source;

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
	 * Generates a string to define a Contribution Source.
	 *
	 * Rescued comment in case it's helpful:
	 *
	 * "Until 2.2, Contribution Source was exactly the same as Contribution note."
	 *
	 * @since 2.2
	 *
	 * @param object $order The Order object.
	 * @return string $source The Contribution Source string.
	 */
	public function source_generate( $order = null ) {

		// TODO: Should this be a setting?
		$source = __( 'Shop', 'wpcv-woo-civi-integration' );

		/**
		 * Filter the Contribution Source string.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_UTM::utm_filter_source() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param string $source The Contribution Source string.
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
	 * @param array  $params The existing array of params for the CiviCRM API.
	 * @param object $order The Order object.
	 * @return array $params The modified array of params for the CiviCRM API.
	 */
	public function source_get_for_order( $params, $order ) {

		// Generate the default Source if there isn't one.
		$source = $this->get_order_meta( $order->get_id() );
		if ( empty( $source ) ) {
			$source = $this->source_generate( $order );
			$this->set_order_meta( $order->get_id(), $source );
		}

		$params['source'] = $source;

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

		$nb_cols  = count( $defaults );
		$new_cols = [
			'source' => __( 'Source', 'wpcv-woo-civi-integration' ),
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = %s", $this->meta_key ) );
		if ( count( $results ) > 0 ) {
			$selected = filter_input( INPUT_GET, 'shop_order_source' );
			$selected = sanitize_text_field( wp_unslash( $selected ) );

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
		$source = sanitize_text_field( wp_unslash( $source ) );
		if ( empty( $source ) ) {
			return;
		}

		// Modify meta query.
		$mq = $query->get( 'meta_query' );
		// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$meta_query = false !== $mq ? [ 'relation' => 'AND', $mq ] : [];

		// Add Source meta query.
		$meta_query['source_clause'] = [
			'key'     => $this->meta_key,
			'value'   => $source,
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = %s", $this->meta_key ) );

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
