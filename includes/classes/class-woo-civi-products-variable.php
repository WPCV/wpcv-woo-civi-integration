<?php
/**
 * Variable Products class.
 *
 * Handles functionality for the WooCommerce the Variable Product Type.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Variable Products class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Products_Variable {

	/**
	 * WooCommerce Product meta key holding the CiviCRM Entity Type.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $entity_key The CiviCRM Entity Type meta key.
	 */
	public $entity_key = '_wpcv_wci_variable_civicrm_entity_type';

	/**
	 * WooCommerce Product Variation meta keys.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $product_variation_meta The WooCommerce Product Variation meta keys.
	 */
	public $product_variation_meta = [
		'civicrm_contribution' => [
			'financial_type_id' => '_wpcv_wci_variable_contribution_financial_type_id',
			'pfv_id'            => '_wpcv_wci_variable_contribution_pfv_id',
		],
		'civicrm_membership'   => [
			'financial_type_id' => '_wpcv_wci_variable_membership_financial_type_id',
			'pfv_id'            => '_wpcv_wci_variable_membership_pfv_id',
			'type_id'           => '_wpcv_wci_variable_membership_type_id',
		],
		'civicrm_participant'  => [
			'financial_type_id' => '_wpcv_wci_variable_participant_financial_type_id',
			'pfv_id'            => '_wpcv_wci_variable_participant_pfv_id',
			'event_id'          => '_wpcv_wci_variable_participant_event_id',
			'role_id'           => '_wpcv_wci_variable_participant_role_id',
		],
	];

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

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	private function register_hooks() {

		// Add CiviCRM tab to the Variable Product Settings tabs.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'tab_add' ], 10 );

		// Add CiviCRM Variable Product panel template.
		add_action( 'woocommerce_product_data_panels', [ $this, 'panel_add' ], 10 );

		// Save metadata from the Variable Product "CiviCRM Settings" Tab.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'panel_saved' ], 30 );

		// Clear meta data from the "CiviCRM Settings" Product Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved/before', [ $this, 'panel_clear_meta' ], 40 );
		add_action( 'wpcv_woo_civi/product/custom/panel/saved/before', [ $this, 'panel_clear_meta' ], 40 );

		// Determine if the Line Item should be skipped.
		add_filter( 'wpcv_woo_civi/products/line_item/skip', [ $this, 'line_item_skip' ], 40, 5 );

		// Filter the Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 40, 5 );

		// Get the Entity Type of a Variable Product Type.
		add_filter( 'wpcv_woo_civi/product/query/entity_type', [ $this, 'entity_type_get' ], 20, 3 );

		// Save the Entity Type of a Variable Product Type.
		add_action( 'wpcv_woo_civi/product/save/entity_type', [ $this, 'entity_type_save' ], 20, 2 );

		/*
		// Get the Financial Type ID of a Product Variation.
		add_filter( 'wpcv_woo_civi/product/query/financial_type_id', [ $this, 'financial_type_id_get' ], 20, 3 );
		*/

		/*
		// Save the Financial Type ID of a Product Variation.
		add_action( 'wpcv_woo_civi/product/save/financial_type_id', [ $this, 'financial_type_id_save' ], 20, 2 );
		*/

		// Filter the Product Type options to exclude Variable Product Type.
		add_filter( 'wpcv_woo_civi/product_types/get/options', [ $this, 'product_types_filter' ], 20 );

		// Add CiviCRM Product Variation template.
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_attributes_render' ], 10, 3 );

		// Add metadata to the Product Variation before it is saved.
		add_action( 'woocommerce_admin_process_variation_object', [ $this, 'variation_saved' ], 10, 2 );

	}

	/**
	 * Adds a "CiviCRM Settings" Product Tab to the New & Edit Product screens.
	 *
	 * @since 3.0
	 *
	 * @param array $tabs The existing Product tabs.
	 * @return array $tabs The modified Product tabs.
	 */
	public function tab_add( $tabs ) {

		$tabs['civicrm_variable'] = [
			'label'  => __( 'CiviCRM Entity', 'wpcv-woo-civi-integration' ),
			'target' => 'civicrm_variable',
			'class'  => [
				'show_if_variable',
			],
		];

		return $tabs;

	}

	/**
	 * Includes the CiviCRM Settings panels on the New & Edit Product screens.
	 *
	 * @since 3.0
	 */
	public function panel_add() {

		global $thepostid, $post;

		// Try and get the ID of the Product.
		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		// Build options for the the Entities select.
		$entity_options = WPCV_WCI()->helper->get_entity_type_options();

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/meta-boxes/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-data-panel-variable.php';

	}

	/**
	 * Adds the CiviCRM Settings as meta to the Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Bail if not a Variable Product Type.
		$product_type = $product->get_type();
		if ( 'variable' !== $product->get_type() ) {
			return;
		}

		/**
		 * Fires before the settings from the Variable Product "CiviCRM Settings" Tab have been saved.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Settings_Products::panel_clear_meta() (Priority: 10)
		 * * WPCV_Woo_Civi_Membership::panel_clear_meta() (Priority: 20)
		 * * WPCV_Woo_Civi_Participant::panel_clear_meta() (Priority: 30)
		 * * WPCV_Woo_Civi_Products_Custom::panel_clear_meta() (Priority: 50)
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/variable/panel/saved/before', $product );

		// Save the Entity Type. Nonce has been verified by WooCommerce.
		if ( isset( $_POST[ $this->entity_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$entity_type = sanitize_key( $_POST[ $this->entity_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->add_meta_data( $this->entity_key, $entity_type, true );
		}

		/**
		 * Fires after the settings from the Variable Product "CiviCRM Settings" Tab have been saved.
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/variable/panel/saved/after', $product );

	}

	/**
	 * Clears the metadata for this Variable Product.
	 *
	 * TODO: What happens to Product Variations when the Product Type is switched?
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_clear_meta( $product ) {

		// Clear the current Variable Product metadata.
		$product->delete_meta_data( $this->entity_key );

	}

	/**
	 * Renders the CiviCRM Settings on the Product Variation.
	 *
	 * @since 3.0
	 *
	 * @param integer $loop The position in the loop.
	 * @param array   $variation_data The Variation data.
	 * @param WP_Post $variation The WordPress Post data.
	 */
	public function variation_attributes_render( $loop, $variation_data, $variation ) {

		// Get parent Variable Product.
		$parent = wc_get_product( $variation->post_parent );

		// Get parent CiviCRM Entity Type.
		$entity = $parent->get_meta( $this->entity_key );

		// Bail there isn't one or it's excluded from sync.
		if ( empty( $entity ) || 'civicrm_exclude' === $entity ) {
			return;
		}

		// Construct the Financial Type options.
		$financial_type_options = [
			'' => __( 'Select a Financial Type', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_financial_types();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		// Get common meta keys for the form elements.
		$financial_type_id_key = $this->get_meta_key( $entity, 'financial_type_id' );
		$pfv_id_key            = $this->get_meta_key( $entity, 'pfv_id' );

		// Add loop item.
		$financial_type_id_key .= '-' . $loop;
		$pfv_id_key            .= '-' . $loop;

		// Get common metadata.
		$financial_type_id = $this->get_meta( $variation->ID, $entity, 'financial_type_id' );
		$pfv_id            = $this->get_meta( $variation->ID, $entity, 'pfv_id' );

		// Include template.
		$directory = 'assets/templates/woocommerce/admin/meta-boxes/views/';
		include WPCV_WOO_CIVI_PATH . $directory . 'html-product-data-panel-variation.php';

	}

	/**
	 * Sets the Product Variation properties before it is saved.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product_Variation $variation The Product Variation object.
	 * @param integer              $loop The position in the loop.
	 */
	public function variation_saved( $variation, $loop ) {

		/**
		 * Fires before the Product Variation properties have been saved.
		 *
		 * @since 3.0
		 *
		 * @param WC_Product_Variation $variation The Product Variation object.
		 * @param integer $loop The position in the loop.
		 */
		do_action( 'wpcv_woo_civi/product/variation/attributes/saved/before', $variation, $loop );

		// Clear existing Product Variation metadata.
		// TODO: Maybe move this to a "before" callback.
		foreach ( $this->product_variation_meta as $entity => $data ) {
			foreach ( $data as $shorthand => $meta_key ) {
				$variation->delete_meta_data( $meta_key );
			}
		}

		// Get CiviCRM Entity Type.
		$entity_type = $this->entity_type_get_from_parent( $variation );

		// Bail there isn't one or it's excluded from sync.
		if ( empty( $entity_type ) || 'civicrm_exclude' === $entity_type ) {
			return;
		}

		// Get meta keys.
		$financial_type_key = $this->get_meta_key( $entity_type, 'financial_type_id' );
		$pfv_key            = $this->get_meta_key( $entity_type, 'pfv_id' );

		// Add loop item.
		$financial_type_loop_key = $financial_type_key . '-' . $loop;
		$pfv_loop_key            = $pfv_key . '-' . $loop;

		// Save the Financial Type. Nonce has been verified by WooCommerce.
		if ( isset( $_POST[ $financial_type_loop_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_key( $_POST[ $financial_type_loop_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$variation->add_meta_data( $financial_type_key, $value, true );
		}

		// Save the Price Field Value.
		if ( isset( $_POST[ $pfv_loop_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_key( $_POST[ $pfv_loop_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$variation->add_meta_data( $pfv_key, $value, true );
		}

		/**
		 * Fires when the Product Variation properties have been saved.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Membership::variation_saved() (Priority: 10)
		 * * WPCV_Woo_Civi_Participant::variation_saved() (Priority: 20)
		 *
		 * @since 3.0
		 *
		 * @param WC_Product_Variation $variation The Product Variation object.
		 * @param integer $loop The position in the loop.
		 * @param string $entity_type The CiviCRM Entity Type.
		 */
		do_action( 'wpcv_woo_civi/product/variation/attributes/saved/after', $variation, $loop, $entity_type );

	}

	/**
	 * Determines if a Line Item should be skipped.
	 *
	 * @since 3.0
	 *
	 * @param bool   $skip The possibly set "skip" flag.
	 * @param object $item The WooCommerce Item object.
	 * @param object $product The WooCommerce Product object.
	 * @param string $product_type The WooCommerce Product Type.
	 * @param string $entity The mapped CiviCRM Entity.
	 * @return bool $skip The determined "skip" flag.
	 */
	public function line_item_skip( $skip, $item, $product, $product_type, $entity ) {

		// Exclude when empty or when specified.
		if ( '' === $entity || 'civicrm_exclude' === $entity ) {
			$skip = true;
		}

		return $skip;

	}

	/**
	 * Filters a Line Item to create an Entity that matches the Product Variation.
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

		// Bail if not a Product Variation.
		$product_type = $product->get_type();
		if ( 'variation' !== $product_type ) {
			return $line_item;
		}

		// Get the mapped CiviCRM Entity.
		$entity_type = $this->entity_type_get_from_parent( $product );

		// Send to relevant method.
		switch ( $entity_type ) {
			case 'civicrm_contribution':
				$line_item = $this->line_item_contribution_filter( $line_item, $item, $product, $order, $params );
				break;
			case 'civicrm_membership':
				$line_item = $this->line_item_membership_filter( $line_item, $item, $product, $order, $params );
				break;
			case 'civicrm_participant':
				$line_item = $this->line_item_participant_filter( $line_item, $item, $product, $order, $params );
				break;
		}

		return $line_item;

	}

	/**
	 * Filters a Line Item to add a Contribution.
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
	public function line_item_contribution_filter( $line_item, $item, $product, $order, $params ) {

		// Define the Product Type name.
		$entity_type = 'civicrm_contribution';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $entity_type, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $entity_type, 'pfv_id' );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Get Price Field Value data.
		$price_field_value = WPCV_WCI()->helper->get_price_field_value_by_id( $price_field_value_id );
		if ( empty( $price_field_value ) ) {
			return $line_item;
		}

		// Get Price Field data.
		$price_field = WPCV_WCI()->helper->get_price_field_by_price_field_value_id( $price_field_value_id );
		if ( empty( $price_field ) ) {
			return $line_item;
		}

		// Grab the existing Line Item data.
		$line_item_data = array_pop( $line_item['line_item'] );

		// TODO: Are there other params for the Line Item data?
		$contribution_line_item_data = [
			'financial_type_id'    => $financial_type_id,
			'price_field_id'       => $price_field_value['price_field_id'],
			'price_field_value_id' => $price_field_value_id,
			'label'                => $price_field_value['label'],
		];

		// TODO: Look at the Line Item.
		$line_item_params = [];

		// Apply Contribution to Line Item.
		$line_item = [
			'params'    => $line_item_params,
			'line_item' => [
				array_merge( $line_item_data, $contribution_line_item_data ),
			],
		];

		return $line_item;

	}

	/**
	 * Filters a Line Item to add a Membership.
	 *
	 * This method is a modified clone of the global Membership Line Item filter
	 * except that the source of the data is the Product Variation.
	 *
	 * @see WPCV_Woo_Civi_Membership::line_item_filter()
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
	public function line_item_membership_filter( $line_item, $item, $product, $order, $params ) {

		// Bail if CiviMember isn't active.
		if ( ! WPCV_WCI()->membership->active ) {
			return $line_item;
		}

		// Define the Product Type name.
		$entity_type = 'civicrm_membership';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $entity_type, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Membership Type ID from Product meta.
		$membership_type_id = $this->get_meta( $product_id, $entity_type, 'type_id' );
		if ( empty( $membership_type_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $entity_type, 'pfv_id' );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Make an array of the params.
		$args = [
			'item'                 => $item,
			'product'              => $product,
			'order'                => $order,
			'params'               => $params,
			'financial_type_id'    => $financial_type_id,
			'membership_type_id'   => $membership_type_id,
			'price_field_value_id' => $price_field_value_id,
		];

		// Populate the Line Item.
		$line_item = WPCV_WCI()->membership->line_item_populate( $line_item, $args );

		return $line_item;

	}

	/**
	 * Filters a Line Item to create an Event Participant.
	 *
	 * This method is a modified clone of the global Participant Line Item filter
	 * except that the source of the data is the Product Variation.
	 *
	 * @see WPCV_Woo_Civi_Participant::line_item_filter()
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
	public function line_item_participant_filter( $line_item, $item, $product, $order, $params ) {

		// Bail if CiviEvent isn't active.
		if ( ! WPCV_WCI()->participant->active ) {
			return $line_item;
		}

		// Define the Entity Type.
		$entity_type = 'civicrm_participant';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $entity_type, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Event ID from Product meta.
		$event_id = $this->get_meta( $product_id, $entity_type, 'event_id' );
		if ( empty( $event_id ) ) {
			return $line_item;
		}

		// Get Participant Role ID from Product meta.
		$participant_role_id = $this->get_meta( $product_id, $entity_type, 'role_id' );
		if ( empty( $participant_role_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $entity_type, 'pfv_id' );
		if ( empty( $price_field_value_id ) ) {
			return $line_item;
		}

		// Make an array of the params.
		$args = [
			'item'                 => $item,
			'product'              => $product,
			'order'                => $order,
			'params'               => $params,
			'financial_type_id'    => $financial_type_id,
			'event_id'             => $event_id,
			'participant_role_id'  => $participant_role_id,
			'price_field_value_id' => $price_field_value_id,
		];

		// Populate the Line Item.
		$line_item = WPCV_WCI()->participant->line_item_populate( $line_item, $args );

		return $line_item;

	}

	/**
	 * Gets the requested meta key for the Product Variation.
	 *
	 * This method does not check for the existence of an entry in the array
	 * because it needs to be called correctly and will produce log entries
	 * when it is not.
	 *
	 * @since 3.0
	 *
	 * @param string $type The type of Product Variation.
	 * @param string $key The shorthand for the meta key.
	 * @return string The requested meta key.
	 */
	public function get_meta_key( $type, $key ) {

		// Log when incorrectly called.
		if ( empty( $this->product_variation_meta[ $type ][ $key ] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'type'      => $type,
				'key'       => $key,
				'backtrace' => $trace,
			];
			WPCV_WCI()->log_error( $log );
		}

		return $this->product_variation_meta[ $type ][ $key ];

	}

	/**
	 * Gets the metadata from Product Variation meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param string  $type The type of Product Variation.
	 * @param string  $key The name of the meta key.
	 * @return mixed $value The value, false otherwise.
	 */
	public function get_meta( $product_id, $type, $key ) {
		$value = false;
		if ( ! empty( $this->product_variation_meta[ $type ][ $key ] ) ) {
			$value = get_post_meta( $product_id, $this->product_variation_meta[ $type ][ $key ], true );
		}
		return $value;
	}

	/**
	 * Sets the metadata on a Product Variation.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param string  $type The type of Product Variation.
	 * @param string  $key The name of the meta key.
	 * @param mixed   $value The value to save.
	 */
	public function set_meta( $product_id, $type, $key, $value ) {
		if ( ! empty( $this->product_variation_meta[ $type ][ $key ] ) ) {
			update_post_meta( $product_id, $this->product_variation_meta[ $type ][ $key ], $value );
		}
	}

	/**
	 * Gets the Entity Type from the parent Product of a Product Variation.
	 *
	 * @since 3.0
	 *
	 * @param object $product The WooCommerce Product object.
	 * @return string $entity_type The found Entity Type, empty otherwise.
	 */
	public function entity_type_get_from_parent( $product ) {

		// Init return.
		$entity_type = '';

		// Bail if no Product passed in.
		if ( empty( $product ) ) {
			return $entity_type;
		}

		// Bail if not a Product Variation.
		$product_type = $product->get_type();
		if ( 'variation' !== $product_type ) {
			return $entity_type;
		}

		// Get parent Product.
		$parent_id = $product->get_parent_id();
		$parent    = wc_get_product( $parent_id );
		if ( empty( $parent ) ) {
			return $entity_type;
		}

		// Get the Entity Type.
		$entity_type = $parent->get_meta( $this->entity_key );

		return $entity_type;

	}

	/**
	 * Gets the Entity Type from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param string  $entity_type The possibly found Entity Type.
	 * @param integer $product_id The Product ID.
	 * @param object  $product The WooCommerce Product object.
	 * @return string $entity_type The found Entity Type, passed through otherwise.
	 */
	public function entity_type_get( $entity_type, $product_id, $product = null ) {

		// Pass through if already found.
		if ( '' !== $entity_type ) {
			return $entity_type;
		}

		// Get the Product if not supplied.
		if ( empty( $product ) ) {
			$product = wc_get_product( $product_id );
		}

		// Pass through if Product not found.
		if ( empty( $product ) ) {
			return $entity_type;
		}

		// Pass through if not a Variable Product or a Product Variation.
		$product_type = $product->get_type();
		if ( 'variable' !== $product_type && 'variation' !== $product_type ) {
			return $entity_type;
		}

		// Get Entity Type from meta when Variable Product.
		if ( 'variable' === $product_type ) {
			$entity_type = $product->get_meta( $this->entity_key );
		}

		// Get Entity Type from parent meta when Product Variation.
		if ( 'variation' === $product_type ) {
			$entity_type = $this->entity_type_get_from_parent( $product );
		}

		return $entity_type;

	}

	/**
	 * Saves the Entity Type to WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param object $product The WooCommerce Product object.
	 * @param string $entity_type The CiviCRM Entity Type.
	 */
	public function entity_type_save( $product, $entity_type ) {

		// Bail if no Product passed in.
		if ( empty( $product ) ) {
			return;
		}

		// Bail if not a Variable Product or a Product Variation.
		$product_type = $product->get_type();
		if ( 'variable' !== $product_type && 'variation' !== $product_type ) {
			return;
		}

		// Save Entity Type to meta.
		if ( 'variable' === $product_type ) {
			$product->add_meta_data( $this->entity_key, $entity_type, true );
			$id = $product->save();
		}

		// Get Entity Type from parent meta when Product Variation.
		if ( 'variation' === $product_type ) {
			$parent_id = $product->get_parent_id();
			$parent    = wc_get_product( $parent_id );
			$parent->add_meta_data( $this->entity_key, $entity_type, true );
			$id = $parent->save();
		}

	}

	/**
	 * Gets the Financial Type ID from WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $financial_type_id The possibly found Financial Type ID.
	 * @param integer $product_id The Product ID.
	 * @param object  $product The WooCommerce Product object.
	 * @return integer $financial_type_id The found Financial Type ID, passed through otherwise.
	 */
	public function financial_type_id_get( $financial_type_id, $product_id, $product = null ) {

		// Pass through if already found.
		if ( 0 !== $financial_type_id ) {
			return $financial_type_id;
		}

		// Get the Product if not supplied.
		if ( empty( $product ) ) {
			$product = wc_get_product( $product_id );
		}

		// Pass through if Product not found.
		if ( empty( $product ) ) {
			return $financial_type_id;
		}

		// Pass through if not a Product Variation.
		$product_type = $product->get_type();
		if ( 'variation' !== $product_type ) {
			return $entity_type;
		}

		// Get Entity Type from parent Product.
		$entity_type = $this->entity_type_get_from_parent( $product );

		// Return Financial Type ID if found.
		$financial_type_id_key     = $this->get_meta_key( $entity_type, 'financial_type_id' );
		$product_financial_type_id = $product->get_meta( $financial_type_id_key );
		if ( ! empty( $product_financial_type_id ) ) {
			return $product_financial_type_id;
		}

		// Not found.
		return 0;

	}

	/**
	 * Saves the Financial Type ID to WooCommerce Product meta.
	 *
	 * @since 3.0
	 *
	 * @param object  $product The WooCommerce Product object.
	 * @param integer $financial_type_id The Financial Type ID.
	 */
	public function financial_type_id_save( $product, $financial_type_id ) {

		// Bail if no Product passed in.
		if ( empty( $product ) ) {
			return;
		}

		// Bail if not a Product Variation.
		$product_type = $product->get_type();
		if ( 'variation' !== $product_type ) {
			return;
		}

		// Get Entity from parent Product.
		$entity_type = $this->entity_type_get_from_parent( $product );

		// Save Financial Type ID to meta.
		$this->set_meta( $product->get_id(), $entity_type, 'financial_type_id', $financial_type_id );

	}

	/**
	 * Filters the Product Type options to exclude Variable Product Type.
	 *
	 * @since 3.0
	 *
	 * @param array $product_types The existing array of WooCommerce Product Types.
	 * @return array $product_types The modified array of WooCommerce Product Types.
	 */
	public function product_types_filter( $product_types ) {

		// Remove Variable Product if present.
		if ( array_key_exists( 'variable', $product_types ) ) {
			unset( $product_types['variable'] );
		}

		return $product_types;

	}

}
