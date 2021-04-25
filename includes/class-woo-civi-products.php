<?php
/**
 * WPCV WooCommerce CiviCRM Product class.
 *
 * Handles the integration of WooCommerce Products with CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Product class.
 *
 * @since 2.2
 */
class WPCV_Woo_Civi_Products {

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
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Add CiviCRM tab to the Product Settings tabs.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_civicrm_product_tab' ] );
		// Add CiviCRM Product panel template.
		add_action( 'woocommerce_product_data_panels', [ $this, 'add_civicrm_product_panel' ] );
		// Save CiviCRM Product settings.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_civicrm_product_settings' ] );

		add_action( 'bulk_edit_custom_box', [ $this, 'add_contribution_to_quick_edit' ], 10, 2 );

		add_action( 'manage_product_posts_custom_column', [ $this, 'columns_content' ], 90, 2 );

		// Bulk / quick edit.
		add_action( 'save_post', [ $this, 'bulk_and_quick_edit_save_post' ], 10, 2 );

	}

	/**
	 * Adds a CiviCRM settings tab to the new/edit Product screen.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_product_data_tabs' filter.
	 *
	 * @param array $tabs The existing Product tabs.
	 * @return array $tabs The modified Product tabs.
	 */
	public function add_civicrm_product_tab( $tabs ) {

		$tabs['woocommerce_civicrm'] = [
			'label' => __( 'CiviCRM Settings', 'wpcv-woo-civi-integration' ),
			'target'   => 'woocommerce_civicrm',
		];

		return $tabs;

	}

	/**
	 * Includes the CiviCRM settings panel to the new/edit Product screen.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_product_data_panels' action.
	 */
	public function add_civicrm_product_panel() {
		include WPCV_WOO_CIVI_PATH . 'assets/templates/tabs/tab-product-data-civicrm-settings.php';
	}

	/**
	 * Add the CiviCRM Product settings as meta before Product is saved.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_admin_process_product_object' action.
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function save_civicrm_product_settings( $product ) {

		if ( isset( $_POST['woocommerce_civicrm_financial_type_id'] ) ) {
			$financial_type_id = sanitize_key( $_POST['woocommerce_civicrm_financial_type_id'] );
			$product->add_meta_data( 'woocommerce_civicrm_financial_type_id', $financial_type_id, true );
		}

		if ( isset( $_POST['woocommerce_civicrm_membership_type_id'] ) ) {
			$membership_type_id = sanitize_key( $_POST['woocommerce_civicrm_membership_type_id'] );
			$product->add_meta_data( 'woocommerce_civicrm_membership_type_id', $membership_type_id, true );
		}

	}

	/**
	 * Append the Financial Type to the Product Category column.
	 *
	 * @since 2.4
	 *
	 * @param string $column_name The column name.
	 * @param int $post_id The WordPress Post ID.
	 */
	public function columns_content( $column_name, $post_id ) {

		if ( 'product_cat' === $column_name ) {
			$contribution_type = get_post_meta( $post_id, '_civicrm_contribution_type', true );
			$default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
			$contributions_types = WPCV_WCI()->helper->get_financial_types();
			echo '<br>' . (
				( null !== $contribution_type && isset( $contributions_types[ $contribution_type ] ) )
					? esc_html( $contributions_types[ $contribution_type ] )
					: sprintf(
						/* translators: %s: The default Financial Type */
						__( '%s (Default)', 'wpcv-woo-civi-integration' ),
						isset( $contributions_types[ $default_contribution_type_id ] )
							? $contributions_types[ $default_contribution_type_id ]
							: __( 'Not set', 'wpcv-woo-civi-integration' )
					)
			);
		}

	}


	/**
	 * Contribution fields for Products.
	 *
	 * @since 2.4
	 */
	public function contribution_fields_bulk() {

		echo '
			<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title">' . __( 'Contribution Type', 'wpcv-woo-civi-integration' ) . '</span>
				<span class="input-text-wrap">
				<select style="" id="_civicrm_contribution_type" name="civicrm_contribution_type" class="select short">';
		$contributions_types = WPCV_WCI()->helper->get_financial_types();
		$options = [
			__( '— No change —', 'wpcv-woo-civi-integration' ),
		]
		+ $contributions_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'wpcv-woo-civi-integration' ),
		];

		foreach ( $options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '">' . $value . '</option>';
		}
		echo '</select>
				</span>
			</label>
		</div>';

	}

	/**
	 * Add Contribution to Quick Edit.
	 *
	 * @since 2.4
	 *
	 * @param string $column_name The column name.
	 * @param string $post_type The WordPress Post Type.
	 */
	public function add_contribution_to_quick_edit( $column_name, $post_type ) {

		if ( 'product_cat' !== $column_name || 'product' !== $post_type ) {
			return;
		}

		echo '<div class="inline-edit-col-right" style="float:right;">';
		$this->contribution_fields_bulk();
		echo '</div>';

	}

	/**
	 * Quick and bulk edit saving.
	 *
	 * TODO: "update_post_meta" doesn't cause "save_post" to fire again, so the
	 * protection against recursion is likely to be redundant.
	 *
	 * @since 2.3
	 *
	 * @param int $post_id The Post ID being saved.
	 * @param object $post The Post object being saved.
	 */
	public function bulk_and_quick_edit_save_post( $post_id, $post ) {

		remove_action( 'save_post', [ $this, 'bulk_and_quick_edit_save_post' ] );

		if ( isset( $_GET['civicrm_contribution_type'] ) ) {
			$civicrm_contribution_type = sanitize_text_field( $_GET['civicrm_contribution_type'] );
			update_post_meta( $post_id, '_civicrm_contribution_type', $civicrm_contribution_type );
		}

		add_action( 'save_post', [ $this, 'bulk_and_quick_edit_save_post' ], 10, 2 );

	}

}
