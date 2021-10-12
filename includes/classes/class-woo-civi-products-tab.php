<?php
/**
 * Products Tab class.
 *
 * Creates a Tab on a WooCommerce "Edit Product" page.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Products Tab class.
 *
 * @since 2.2
 */
class WPCV_Woo_Civi_Products_Tab {

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
	 * @since 3.0
	 */
	public function initialise() {
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.2
	 */
	public function register_hooks() {

		// Add CiviCRM tab to the Product Settings tabs.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'tab_add' ] );

		// Add CiviCRM Product panel template.
		add_action( 'woocommerce_product_data_panels', [ $this, 'panel_add' ] );

		// Save CiviCRM Product settings.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'panel_saved' ] );

		// Append Contribution Type to Product Cat.
		add_action( 'manage_product_posts_custom_column', [ $this, 'columns_content' ], 90, 2 );

		// Product Bulk Edit and Quick Edit operations.
		add_action( 'woocommerce_product_bulk_edit_end', [ $this, 'bulk_edit_markup' ] );
		add_action( 'woocommerce_product_bulk_edit_save', [ $this, 'product_edit_save' ] );
		add_action( 'woocommerce_product_quick_edit_end', [ $this, 'quick_edit_markup' ] );
		add_action( 'woocommerce_product_quick_edit_save', [ $this, 'product_edit_save' ] );

	}

	/**
	 * Adds a "CiviCRM Settings" Product Tab to the New & Edit Product screens.
	 *
	 * @since 2.4
	 *
	 * @param array $tabs The existing Product tabs.
	 * @return array $tabs The modified Product tabs.
	 */
	public function tab_add( $tabs ) {

		$tabs['woocommerce_civicrm'] = [
			'label' => __( 'CiviCRM Settings', 'wpcv-woo-civi-integration' ),
			'target'   => 'woocommerce_civicrm',
		];

		return $tabs;

	}

	/**
	 * Includes the CiviCRM settings panel on the New & Edit Product screens.
	 *
	 * @since 2.4
	 */
	public function panel_add() {

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/meta-boxes/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-data-panel-civicrm.php';

	}

	/**
	 * Adds the CiviCRM settings as meta before Product is saved.
	 *
	 * @since 2.4
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Always save the Financial Type ID.
		if ( isset( $_POST[ WPCV_WCI()->products->meta_key ] ) ) {
			$financial_type_id = sanitize_key( $_POST[ WPCV_WCI()->products->meta_key ] );
			$product->add_meta_data( WPCV_WCI()->products->meta_key, (int) $financial_type_id, true );
		}

		/**
		 * Fires when the settings from the "CiviCRM Settings" Product Tab have been saved.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Membership::panel_saved() (Priority: 10)
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/panel/saved', $product );

	}

	/**
	 * Appends the Financial Type to the Product Category column.
	 *
	 * @since 2.4
	 *
	 * @param string $column_name The column name.
	 * @param int $post_id The WordPress Post ID.
	 */
	public function columns_content( $column_name, $post_id ) {

		if ( 'product_cat' !== $column_name ) {
			return;
		}

		$default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		$product_contribution_type_id = WPCV_WCI()->products->get_product_meta( $post_id );
		$financial_types = WPCV_WCI()->helper->get_financial_types();

		echo '<br>';

		// If there's a specific Financial Type for this Product, use it.
		if ( $product_contribution_type_id !== 0 && isset( $financial_types[ $product_contribution_type_id ] ) ) {
			echo esc_html( $financial_types[ $product_contribution_type_id ] );
			return;
		}

		// Fall back to the default Financial Type.
		$default = isset( $financial_types[ $default_contribution_type_id ] )
			? $financial_types[ $default_contribution_type_id ]
			: __( 'Not set', 'wpcv-woo-civi-integration' );

		/* translators: %s: The default Financial Type */
		echo sprintf( __( '%s (Default)', 'wpcv-woo-civi-integration' ), $default );

	}

	/**
	 * Adds a Contribution Type selector to WooCommerce "Product data" on Bulk Edit screen.
	 *
	 * @since 3.0
	 */
	public function bulk_edit_markup() {

		// Construct select options array.
		$financial_types = WPCV_WCI()->helper->get_financial_types();
		$options = [
			'' => __( '— No change —', 'wpcv-woo-civi-integration' ),
		]
		+ $financial_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'wpcv-woo-civi-integration' ),
		];

		?>
		<label>
			<span class="title"><?php esc_html_e( 'Contribution Type', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_financial_type_id" name="_civicrm_financial_type_id">
					<?php
					foreach ( $options as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
					}
					?>
				</select>
			</span>
		</label>
		<?php

	}

	/**
	 * Adds a Contribution Type selector to WooCommerce "Product data" on Quick Edit screen.
	 *
	 * @since 3.0
	 */
	public function quick_edit_markup() {

		// Construct select options array.
		$financial_types = WPCV_WCI()->helper->get_financial_types();
		$options = [
			'' => __( '— No change —', 'wpcv-woo-civi-integration' ),
		]
		+ $financial_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'wpcv-woo-civi-integration' ),
		];

		?>
		<div class="inline-edit-group">
			<span class="title"><?php esc_html_e( 'Contribution Type', 'wpcv-woo-civi-integration' ); ?></span>
			<span class="input-text-wrap">
				<select class="civicrm_financial_type_id" name="_civicrm_financial_type_id">
					<?php
					foreach ( $options as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
					}
					?>
				</select>
			</span>
		</div>
		<?php

	}

	/**
	 * Saves the Contribution Type when Bulk Edit or Quick Edit is submitted.
	 *
	 * @since 3.0
	 *
	 * @param object $product The WooCommerce Product object being saved.
	 */
	public function product_edit_save( $product ) {

		// Bail if there's none of our data present.
		if ( empty( $_REQUEST['_civicrm_financial_type_id'] ) ) {
			return;
		}

		// Extract Post ID.
		$post_id = $product->get_id();

		// Save Contribution Type directly to Post meta.
		$financial_type_id = sanitize_text_field( $_REQUEST['_civicrm_financial_type_id'] );
		WPCV_WCI()->products->set_product_meta( $post_id, $financial_type_id );

	}

}
