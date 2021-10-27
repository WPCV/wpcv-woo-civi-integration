<?php
/**
 * Product Settings class.
 *
 * Creates a Tab on a WooCommerce "Edit Product" page and handles "Quick Edit"
 * and "Bulk Edit" functionality on the Products Listing page.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Product Settings class.
 *
 * @since 2.2
 */
class WPCV_Woo_Civi_Settings_Products {

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

		// Clear meta data from the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/custom/panel/saved', [ $this, 'panel_clear_meta' ] );

		// Append Contribution Type to Product Cat.
		add_action( 'manage_product_posts_custom_column', [ $this, 'columns_content' ], 90, 2 );

		// Product Bulk Edit and Quick Edit Javascript.
		add_action( 'admin_enqueue_scripts', [ $this, 'edit_script_enqueue' ] );

		// Product Bulk Edit and Quick Edit operations.
		add_action( 'woocommerce_product_bulk_edit_end', [ $this, 'bulk_edit_markup' ] );
		add_action( 'woocommerce_product_bulk_edit_save', [ $this, 'bulk_edit_save' ] );
		add_action( 'woocommerce_product_quick_edit_end', [ $this, 'quick_edit_markup' ] );
		add_action( 'woocommerce_product_quick_edit_save', [ $this, 'quick_edit_save' ] );

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
			'target' => 'woocommerce_civicrm',
			'class' => [
				'hide_if_grouped',
				'hide_if_external',
			],
		];

		return $tabs;

	}

	/**
	 * Includes the CiviCRM settings panel on the New & Edit Product screens.
	 *
	 * @since 2.4
	 */
	public function panel_add() {

		global $thepostid, $post;

		// Try and get the ID of the Product.
		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		// Get Price Field Value meta key.
		$pfv_key = WPCV_WCI()->products->pfv_key;

		// Get Price Field Value.
		$pfv_id = WPCV_WCI()->products->get_pfv_meta( $product_id );

		// Build options for the the Entities select.
		$entity_options = WPCV_WCI()->helper->get_entity_type_options();

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/meta-boxes/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-data-panel-civicrm.php';

		// Enqueue the Javascript for our panel.
		wp_enqueue_script(
			'wpcv_woo_civi_global_panel',
			plugins_url( 'assets/js/woocommerce/admin/page-product-civicrm-settings-tab.js', WPCV_WOO_CIVI_FILE ),
			[ 'jquery' ],
			WPCV_WOO_CIVI_VERSION, // Version.
			true // In footer.
		);

		// Build data array.
		$vars = [
			'localisation' => [],
			'settings' => [
				'entity_keys' => array_keys( $entity_options ),
				'entity_options' => $entity_options,
			],
		];

		// Localize our script.
		wp_localize_script(
			'wpcv_woo_civi_global_panel',
			'WPCV_WCI_Global_Panel_Vars',
			$vars
		);

	}

	/**
	 * Adds the CiviCRM settings as meta before Product is saved.
	 *
	 * @since 2.4
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Save the Entity Type.
		if ( isset( $_POST[ WPCV_WCI()->products->entity_key ] ) ) {
			$entity_type = sanitize_key( $_POST[ WPCV_WCI()->products->entity_key ] );
			$product->add_meta_data( WPCV_WCI()->products->entity_key, $entity_type, true );
		}

		// Save the Financial Type ID.
		if ( isset( $_POST[ WPCV_WCI()->products->meta_key ] ) ) {
			$financial_type_id = sanitize_key( $_POST[ WPCV_WCI()->products->meta_key ] );
			if ( is_numeric( $financial_type_id ) ) {
				$product->add_meta_data( WPCV_WCI()->products->meta_key, (int) $financial_type_id, true );
			}
		}

		// Save the Price Field Value ID.
		if ( isset( $_POST[ WPCV_WCI()->products->pfv_key ] ) ) {
			$pfv_id = sanitize_key( $_POST[ WPCV_WCI()->products->pfv_key ] );
			if ( is_numeric( $pfv_id ) ) {
				$product->add_meta_data( WPCV_WCI()->products->pfv_key, (int) $pfv_id, true );
			}
		}

		// Clear the "global" meta if it's an excluded Product Type.
		$product_type = $product->get_type();
		if ( 'grouped' === $product_type || 'external' === $product_type ) {
			$this->panel_clear_meta( $product );
		}

		/**
		 * Fires when the settings from the "CiviCRM Settings" Product Tab have been saved.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Membership::panel_saved() (Priority: 10)
		 * * WPCV_Woo_Civi_Participant::panel_saved() (Priority: 20)
		 * * WPCV_Woo_Civi_Products_Custom::panel_clear_meta() (Priority: 50)
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/panel/saved', $product );

	}

	/**
	 * Clears the metadata for this Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_clear_meta( $product ) {

		// Clear the current global Product Contribution metadata.
		$product->delete_meta_data( WPCV_WCI()->products->entity_key );
		$product->delete_meta_data( WPCV_WCI()->products->meta_key );
		$product->delete_meta_data( WPCV_WCI()->products->pfv_key );

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

		// Get the Product object.
		$product = wc_get_product( $post_id );
		if ( empty( $product ) ) {
			return;
		}

		echo '<br>' . "\n";

		// Bail if it's an excluded Product Type.
		$product_type = $product->get_type();
		if ( 'grouped' === $product_type || 'external' === $product_type ) {
			esc_html_e( 'N/A', 'wpcv-woo-civi-integration' );
			return;
		}

		/**
		 * Query the Entity Type for the Product.
		 *
		 * @since 3.0
		 *
		 * @param int Numeric 0 because we are querying the Entity.
		 * @param int $post_id The WordPress Post ID.
		 */
		$entity_type = apply_filters( 'wpcv_woo_civi/product/query/entity_type', '', $post_id );

		/**
		 * Query the Financial Type ID for the Product.
		 *
		 * @since 3.0
		 *
		 * @param int Numeric 0 because we are querying the Financial Type ID.
		 * @param int $post_id The WordPress Post ID.
		 */
		$financial_type_id = apply_filters( 'wpcv_woo_civi/product/query/financial_type_id', 0, $post_id );

		/**
		 * Query the Price Field Value ID for the Product.
		 *
		 * @since 3.0
		 *
		 * @param int Numeric 0 because we are querying the Price Field Value ID.
		 * @param int $post_id The WordPress Post ID.
		 */
		$pfv_id = apply_filters( 'wpcv_woo_civi/product/query/pfv_id', 0, $post_id );

		// Build the CiviCRM Product data.
		$data = $this->columns_data( $post_id, $entity_type, $financial_type_id, $pfv_id );

		// Get the Entity Types and Financial Types.
		$entity_types = WPCV_WCI()->helper->get_entity_type_options();
		$financial_types = WPCV_WCI()->helper->get_financial_types();

		// Init feedback.
		$entity_type_feedback = '';
		$financial_type_feedback = '';

		// Show if this Product needs its Entity Type set.
		if ( empty( $entity_type ) ) {
			$entity_type_feedback = esc_html_e( 'Entity Type not set', 'wpcv-woo-civi-integration' );
		// Show if this Product should not be synced to CiviCRM.
		} elseif ( $entity_type === 'civicrm_exclude' ) {
			$entity_type_feedback = esc_html_e( 'Excluded', 'wpcv-woo-civi-integration' );
		// If there's a specific Entity Type for this Product, use it.
		} elseif ( array_key_exists( $entity_type, $entity_types ) ) {
			$entity_type_feedback = esc_html( $entity_types[ $entity_type ] );
		// Fall back to "Not found".
		} else {
			$entity_type_feedback = esc_html_e( 'Not found', 'wpcv-woo-civi-integration' );
		}

		// Show if it has the legacy excluded setting.
		if ( ! empty( $financial_type_id ) && $financial_type_id === 'exclude' ) {
			$financial_type_feedback = esc_html_e( 'Excluded (legacy)', 'wpcv-woo-civi-integration' );
		// If there's a specific Financial Type for this Product, use it.
		} elseif ( $financial_type_id !== 0 && array_key_exists( $financial_type_id, $financial_types ) ) {
			$financial_type_feedback = esc_html( $financial_types[ $financial_type_id ] );
		// Fall back to "Not found".
		} else {
			$financial_type_feedback = esc_html_e( 'Not found', 'wpcv-woo-civi-integration' );
		}

		// Clear Financial Type when Product is excluded.
		if ( $entity_type === 'civicrm_exclude' ) {
			$financial_type_feedback = '';
		}

		// Show feedback.
		echo $entity_type_feedback;
		if ( ! empty( $financial_type_feedback ) ) {
			echo '<br>' . "\n" . $financial_type_feedback;
		}
		echo $data;

	}

	/**
	 * Builds the Product data in a hidden div.
	 *
	 * @since 3.0
	 *
	 * @param int $post_id The WordPress Post ID.
	 * @param string $entity_type The CiviCRM Entity Type.
	 * @param int $financial_type_id The CiviCRM Financial Type ID.
	 * @param int $pfv_id The CiviCRM Price Field Value ID.
	 * @return str $markup The CiviCRM Product data markup.
	 */
	public function columns_data( $post_id, $entity_type, $financial_type_id, $pfv_id ) {

		// Build the data markup.
		$markup = '';
		$markup .= "\n" . '<div class="hidden" id="wpcv_woo_civi_inline_' . $post_id . '">' . "\n";
		$markup .= "\t" . '<div class="entity_type">' . $entity_type . '</div>' . "\n";
		$markup .= "\t" . '<div class="financial_type_id">' . $financial_type_id . '</div>' . "\n";
		//$markup .= "\t" . '<div class="pfv_id">' . $pfv_id . '</div>' . "\n";
		$markup .= '</div>' . "\n";

		return $markup;

	}

	/**
	 * Enqueues the Javascript for Quick Edit.
	 *
	 * @since 3.0
	 */
	public function edit_script_enqueue() {

		// Get current screen.
		$screen = get_current_screen();
		if ( ! ( $screen instanceof WP_Screen ) || empty( $screen->id ) ) {
			return;
		}

		// Bail if we are not on the right screen.
		if ( $screen->id !== 'edit-product' ) {
			return;
		}

		// Enqueue the Javascript for Quick Edit.
		wp_enqueue_script(
			'wpcv_woo_civi_quick_edit',
			plugins_url( 'assets/js/woocommerce/admin/product-quick-edit.js', WPCV_WOO_CIVI_FILE ),
			[ 'woocommerce_quick-edit' ],
			WPCV_WOO_CIVI_VERSION, // Version.
			true // In footer.
		);

		// Define classes to pass to script.
		$class_br = '.wpcv_woo_civi_br';
		$class_title = '.wpcv_woo_civi_title';
		$class_entity = '.wpcv_woo_civi_entity_type';
		$class_financial = '.wpcv_woo_civi_financial_type_id';
		$class_contribution = '.wpcv_woo_civi_contribution_pfv_id';

		// Memberships and Participants have multiple classes.
		$classes_membership = [
			'.wpcv_woo_civi_membership_type_id',
			'.wpcv_woo_civi_membership_pfv_id',
		];
		$classes_participant = [
			'.wpcv_woo_civi_participant_event_id',
			'.wpcv_woo_civi_participant_role_id',
			'.wpcv_woo_civi_participant_pfv_id'
		];

		// Let's also have the combined array.
		$classes_all = array_merge(
			[ $class_br ],
			[ $class_title ],
			[ $class_entity ],
			[ $class_financial ],
			[ $class_contribution ],
			$classes_membership,
			$classes_participant
		);

		// Build data array.
		$vars = [
			'localisation' => [],
			'settings' => [
				'class_br' => $class_br,
				'class_title' => $class_title,
				'class_entity' => $class_entity,
				'class_financial' => $class_financial,
				'classes_contribution' => $class_contribution,
				'classes_membership' => $classes_membership,
				'classes_participant' => $classes_participant,
				'classes_all' => $classes_all,
			],
		];

		// Localize our script.
		wp_localize_script(
			'wpcv_woo_civi_quick_edit',
			'WPCV_WCI_Quick_Edit_Vars',
			$vars
		);

	}

	/**
	 * Adds selectors to WooCommerce "Product data" on Bulk Edit screen.
	 *
	 * @since 3.0
	 */
	public function bulk_edit_markup() {

		// Construct Entity Type select options array.
		$entity_type_options = [
			'' => __( '— No Change —', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_entity_type_options();

		// Construct Financial Type select options array.
		$financial_type_options = [
			'' => __( '— No Change —', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_financial_types();

		// Get the Price Sets.
		//$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/list-tables/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-list-bulk-edit.php';

	}

	/**
	 * Saves the meta data when Bulk Edit is submitted.
	 *
	 * @since 3.0
	 *
	 * @param object $product The WooCommerce Product object being saved.
	 */
	public function bulk_edit_save( $product ) {

		// Grab Post ID and Product Type.
		$post_id = $product->get_id();
		$product_type = $product->get_type();

		// Bail if it's an excluded Product Type.
		if ( 'grouped' === $product_type || 'external' === $product_type ) {
			return;
		}

		// Maybe save Entity Type.
		if ( ! empty( $_REQUEST['_civicrm_bulk_entity_type'] ) ) {
			$entity_type = sanitize_text_field( $_REQUEST['_civicrm_bulk_entity_type'] );
			if ( ! array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				WPCV_WCI()->products->set_entity_meta( $post_id, $entity_type );
			}
		}

		// Maybe save Financial Type.
		if ( isset( $_REQUEST['_civicrm_bulk_financial_type_id'] ) && '' !== $_REQUEST['_civicrm_bulk_financial_type_id'] ) {
			$financial_type_id = sanitize_text_field( $_REQUEST['_civicrm_bulk_financial_type_id'] );
			if ( array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				WPCV_WCI()->products_custom->set_meta( $post_id, $product_type, 'financial_type_id', $financial_type_id );
			} else {
				WPCV_WCI()->products->set_product_meta( $post_id, $financial_type_id );
			}
		}

		/*
		// Maybe save Price Field Value ID.
		if ( isset( $_REQUEST['_civirm_bulk_contribution_pfv_id'] ) && '' !== $_REQUEST['_civirm_bulk_contribution_pfv_id'] ) {
			$pfv_id = sanitize_text_field( $_REQUEST['_civirm_bulk_contribution_pfv_id'] );
			WPCV_WCI()->products->set_product_meta( $post_id, $pfv_id );
		}
		*/

	}

	/**
	 * Adds selectors to WooCommerce "Product data" on Quick Edit screen.
	 *
	 * @since 3.0
	 */
	public function quick_edit_markup() {

		// Construct Entity Type select options array.
		$entity_type_options = [
			'' => __( 'Not set', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_entity_type_options();

		// Construct Financial Type select options array.
		$financial_type_options = [
			'' => __( 'Not set', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_financial_types();

		// Get the Price Sets.
		//$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/list-tables/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-list-quick-edit.php';

	}

	/**
	 * Saves the meta data when Quick Edit is submitted.
	 *
	 * @since 3.0
	 *
	 * @param object $product The WooCommerce Product object being saved.
	 */
	public function quick_edit_save( $product ) {

		// Grab Post ID and Product Type.
		$post_id = $product->get_id();
		$product_type = $product->get_type();

		// Bail if it's an excluded Product Type.
		if ( 'grouped' === $product_type || 'external' === $product_type ) {
			return;
		}

		// Maybe save Entity Type.
		if ( ! empty( $_REQUEST['_civicrm_entity_type'] ) ) {
			$entity_type = sanitize_text_field( $_REQUEST['_civicrm_entity_type'] );
			if ( ! array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				WPCV_WCI()->products->set_entity_meta( $post_id, $entity_type );
			}
		}

		// Maybe save Financial Type.
		if ( isset( $_REQUEST['_civicrm_financial_type_id'] ) && '' !== $_REQUEST['_civicrm_financial_type_id'] ) {
			$financial_type_id = sanitize_text_field( $_REQUEST['_civicrm_financial_type_id'] );
			if ( array_key_exists( $product_type, WPCV_WCI()->products_custom->product_types_meta ) ) {
				WPCV_WCI()->products_custom->set_meta( $post_id, $product_type, 'financial_type_id', $financial_type_id );
			} else {
				WPCV_WCI()->products->set_product_meta( $post_id, $financial_type_id );
			}
		}

		/*
		// Maybe save Price Field Value ID.
		if ( isset( $_REQUEST['_civirm_contribution_pfv_id'] ) && '' !== $_REQUEST['_civirm_contribution_pfv_id'] ) {
			$pfv_id = sanitize_text_field( $_REQUEST['_civirm_contribution_pfv_id'] );
			WPCV_WCI()->products->set_product_meta( $post_id, $pfv_id );
		}
		*/

	}

}
