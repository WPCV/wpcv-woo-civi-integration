<?php
/**
 * Custom Products class.
 *
 * Creates our Custom WooCommerce Product Types.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Custom Products class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Products_Custom {

	/**
	 * WooCommerce Custom Product Types meta keys.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $product_types_meta The CiviCRM Custom Product Types meta keys.
	 */
	public $product_types_meta = [
		'civicrm_contribution' => [
			'financial_type_id' => '_wpcv_woo_civicrm_contribution_financial_type_id',
			'pfv_id'            => '_wpcv_woo_civicrm_contribution_pfv_id',
		],
		'civicrm_membership'   => [
			'financial_type_id' => '_wpcv_woo_civicrm_membership_financial_type_id',
			'pfv_id'            => '_wpcv_woo_civicrm_membership_pfv_id',
			'type_id'           => '_wpcv_woo_civicrm_membership_type_id',
		],
		'civicrm_participant'  => [
			'financial_type_id' => '_wpcv_woo_civicrm_participant_financial_type_id',
			'pfv_id'            => '_wpcv_woo_civicrm_participant_pfv_id',
			'event_id'          => '_wpcv_woo_civicrm_participant_event_id',
			'role_id'           => '_wpcv_woo_civicrm_participant_role_id',
		],
	];

	/**
	 * WooCommerce Custom Product Type names.
	 *
	 * These are defined in the class constructor so that they can be translated.
	 *
	 * @since 3.0
	 * @access public
	 * @var array $product_names The WooCommerce Custom Product Type names.
	 */
	public $product_names = [];

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

		// The translatable names of our Custom Product Types.
		$this->product_names = [
			'civicrm_contribution' => __( 'CiviCRM Contribution', 'wpcv-woo-civi-integration' ),
			'civicrm_membership'   => __( 'CiviCRM Membership', 'wpcv-woo-civi-integration' ),
			'civicrm_participant'  => __( 'CiviCRM Participant', 'wpcv-woo-civi-integration' ),
		];

		// Set up Custom Product Types.
		$this->include_files();
		$this->check_setup();
		$this->register_hooks();

	}

	/**
	 * Include files.
	 *
	 * @since 3.0
	 */
	private function include_files() {

		// Include the Custom Product Type class files.
		if ( WPCV_WCI()->helper->is_component_enabled( 'CiviContribute' ) ) {
			include WPCV_WOO_CIVI_PATH . 'includes/products/class-woo-civi-product-contribution.php';
		}
		if ( WPCV_WCI()->membership->active ) {
			include WPCV_WOO_CIVI_PATH . 'includes/products/class-woo-civi-product-membership.php';
		}
		if ( WPCV_WCI()->participant->active ) {
			include WPCV_WOO_CIVI_PATH . 'includes/products/class-woo-civi-product-participant.php';
		}

	}

	/**
	 * Check Custom Product Type setup.
	 *
	 * @since 3.0
	 */
	private function check_setup() {

		// TODO: Prevent this running on every page load.

		// Add the Custom Product Type terms.
		foreach ( $this->product_names as $type => $name ) {

			// Handle CiviCRM Contribution.
			if ( 'civicrm_contribution' === $name ) {
				if ( ! WPCV_WCI()->helper->is_component_enabled( 'CiviContribute' ) ) {
					$this->term_delete( $type );
					unset( $this->product_names['civicrm_contribution'] );
					unset( $this->product_types_meta['civicrm_contribution'] );
				} else {
					$this->term_add( $type );
				}
				continue;
			}

			// Handle CiviCRM Membership.
			if ( 'civicrm_membership' === $name ) {
				if ( ! WPCV_WCI()->membership->active ) {
					$this->term_delete( $type );
					unset( $this->product_names['civicrm_membership'] );
					unset( $this->product_types_meta['civicrm_membership'] );
				} else {
					$this->term_add( $type );
				}
				continue;
			}

			// Handle CiviCRM Participant.
			if ( 'civicrm_participant' === $name ) {
				if ( ! WPCV_WCI()->participant->active ) {
					$this->term_delete( $type );
					unset( $this->product_names['civicrm_participant'] );
					unset( $this->product_types_meta['civicrm_participant'] );
				} else {
					$this->term_add( $type );
				}
				continue;
			}

		}

	}

	/**
	 * Adds the Custom Product Type Term.
	 *
	 * @since 3.0
	 *
	 * @param string $type The Product Type.
	 */
	private function term_add( $type ) {
		if ( ! get_term_by( 'slug', $type, 'product_type' ) ) {
			wp_insert_term( $type, 'product_type' );
		}
	}

	/**
	 * Deletes the Custom Product Type Term.
	 *
	 * @since 3.0
	 *
	 * @param string $type The Product Type.
	 */
	private function term_delete( $type ) {
		if ( get_term_by( 'slug', $type, 'product_type' ) ) {
			wp_delete_term( $type, 'product_type' );
		}
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	private function register_hooks() {

		// Bail if we have no Components enabled.
		if ( empty( $this->product_names ) ) {
			return;
		}

		// Register our Custom Product Types.
		add_filter( 'product_type_selector', [ $this, 'types_add' ] );

		// Add CiviCRM tab to the Custom Product Settings tabs.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'tabs_add' ], 20 );

		// Filter the visible Tabs.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'tabs_filter' ], 20 );

		// Add "CiviCRM Settings" panel template.
		add_action( 'woocommerce_product_data_panels', [ $this, 'panels_add' ], 20 );

		// Save metadata from the "CiviCRM Settings" Tab.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'panel_saved' ], 50 );

		// Clear meta data from the "CiviCRM Settings" Tab.
		add_action( 'wpcv_woo_civi/product/panel/saved/before', [ $this, 'panel_clear_meta' ], 50 );
		add_action( 'wpcv_woo_civi/product/variable/saved/before', [ $this, 'panel_clear_meta' ], 50 );

		// Re-enable the "General" panel.
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'panel_general_enable' ] );
		add_action( 'admin_footer', [ $this, 'panel_general_enable_js' ] );

		// Determine if the Line Item should be skipped.
		add_filter( 'wpcv_woo_civi/products/line_item/skip', [ $this, 'line_item_skip' ], 30, 5 );

		// Filter the Line Item.
		add_filter( 'wpcv_woo_civi/products/line_item', [ $this, 'line_item_filter' ], 50, 5 );

		// Get the Entity Type of a Custom Product Type.
		add_filter( 'wpcv_woo_civi/product/query/entity_type', [ $this, 'entity_type_get' ], 30, 3 );

		// Get the Financial Type ID of a Custom Product Type.
		add_filter( 'wpcv_woo_civi/product/query/financial_type_id', [ $this, 'financial_type_id_get' ], 30, 3 );

		// Save the Financial Type ID of a Custom Product Type.
		add_action( 'wpcv_woo_civi/product/save/financial_type_id', [ $this, 'financial_type_id_save' ], 30, 2 );

		// Get the Price Field Value ID from Product meta.
		add_filter( 'wpcv_woo_civi/product/query/pfv_id', [ $this, 'pfv_id_get' ], 30, 3 );

		// Ensure Custom Product Types have "Add to Cart" button.
		foreach ( $this->product_names as $type => $name ) {
			add_action( "woocommerce_{$type}_add_to_cart", [ $this, 'buttons_add' ] );
		}

		// Filter the Product Type options to exclude Custom Product Types.
		add_filter( 'wpcv_woo_civi/product_types/get/options', [ $this, 'product_types_filter' ], 30 );

	}

	/**
	 * Adds the Custom Product Types to WooCommerce.
	 *
	 * @since 3.0
	 *
	 * @param array $types The existing WooCommerce Product Types.
	 * @return array $types The modified WooCommerce Product Types.
	 */
	public function types_add( $types ) {

		// Add the Product Types.
		foreach ( $this->product_names as $type => $name ) {
			$types[ $type ] = $name;
		}

		return $types;

	}

	/**
	 * Adds a "CiviCRM Settings" Tab to the New & Edit Product screens.
	 *
	 * @since 3.0
	 *
	 * @param array $tabs The existing Product tabs.
	 * @return array $tabs The modified Product tabs.
	 */
	public function tabs_add( $tabs ) {

		foreach ( $this->product_names as $type => $name ) {
			$target          = $type . '_settings';
			$tabs[ $target ] = [
				'label'    => __( 'CiviCRM Settings', 'wpcv-woo-civi-integration' ),
				'target'   => $target,
				'class'    => [
					'show_if_' . $type,
				],
				'priority' => 15,
			];
		}

		return $tabs;

	}

	/**
	 * Filters the array of Product Tabs.
	 *
	 * The default WooCommerce Product Tabs are:
	 *
	 * * 'general'
	 * * 'inventory'
	 * * 'shipping'
	 * * 'linked_product'
	 * * 'attribute'
	 * * 'variations'
	 * * 'advanced'
	 *
	 * @since 3.0
	 *
	 * @param array $tabs The existing array of Product Tabs.
	 * @return array $tabs The modified array of Product Tabs.
	 */
	public function tabs_filter( $tabs ) {

		// Hide some unnecessary Product Tabs.
		foreach ( $this->product_names as $type => $name ) {

			if ( ! empty( $tabs['shipping'] ) ) {
				$tabs['shipping']['class'][] = 'hide_if_' . $type;
			}

			if ( ! empty( $tabs['inventory'] ) ) {
				$tabs['inventory']['class'][] = 'hide_if_' . $type;
			}

			if ( ! empty( $tabs['woocommerce_civicrm'] ) ) {
				$tabs['woocommerce_civicrm']['class'][] = 'hide_if_' . $type;
			}

			if ( ! empty( $tabs['civicrm_variable'] ) ) {
				$tabs['civicrm_variable']['class'][] = 'hide_if_' . $type;
			}

		}

		return $tabs;

	}

	/**
	 * Includes the "CiviCRM Settings" panels on the New & Edit Product screens.
	 *
	 * @since 3.0
	 */
	public function panels_add() {

		global $thepostid, $post;

		// Try and get the ID of the Product.
		$product_id = empty( $thepostid ) ? $post->ID : $thepostid;

		// Construct the Financial Type options.
		$financial_type_options = [
			'' => __( 'Select a Financial Type', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_financial_types();

		// Get the Price Sets.
		$price_sets = WPCV_WCI()->helper->get_price_sets_populated();

		// Path to template directory.
		$directory = WPCV_WOO_CIVI_PATH . 'assets/templates/woocommerce/admin/meta-boxes/views/';

		// Include panel templates.
		include $directory . 'html-product-data-settings-contribution.php';
		include $directory . 'html-product-data-settings-membership.php';
		include $directory . 'html-product-data-settings-participant.php';

	}

	/**
	 * Re-enables the "General" panel on our Custom Product Types.
	 *
	 * @since 3.0
	 */
	public function panel_general_enable() {

		$classes = [];
		foreach ( $this->product_names as $type => $name ) {
			$classes[] = esc_attr( 'show_if_' . $type );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="options_group ' . implode( ' ', $classes ) . ' clear"></div>';

	}

	/**
	 * Shows the "General" Tab for our Custom Product Types.
	 *
	 * @since 3.0
	 */
	public function panel_general_enable_js() {

		// Get current screen.
		$screen = get_current_screen();

		// Bail if it's not what we expect.
		if ( ! ( $screen instanceof WP_Screen ) ) {
			return;
		}

		// Bail if we are not editing a Product.
		if ( 'product' !== $screen->id && 'post' !== $screen->base && 'product' !== $screen->post_type ) {
			return;
		}

		global $post, $product_object;

		// Bail if we don't have a Post object.
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		// Bail if we don't have a Product Post object.
		if ( 'product' !== $post->post_type ) {
			return;
		}

		// Bail if we don't have a Product object.
		if ( ! is_object( $product_object ) ) {
			return;
		}

		// Open script.
		echo "\n" . '<script type="text/javascript">' . "\n";
		echo "\t" . 'jQuery(document).ready(function () {' . "\n";

		// Add "show_if" classes to relevant sections.
		foreach ( $this->product_names as $type => $name ) {
			// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
			echo "\t\t" . "jQuery('#general_product_data .pricing').addClass('show_if_" . esc_attr( $type ) . "');\n";
			echo "\t\t" . 'var tax = jQuery("#general_product_data").find("._tax_status_field");' . "\n";
			echo "\t\t" . 'if (tax.length) {' . "\n";
			echo "\t\t\t" . "tax.parent().addClass('show_if_" . esc_attr( $type ) . "');\n";
			echo "\t\t" . '}' . "\n";
			// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found
		}

		// Show it if this is one of our Product Types.
		$product_type = $product_object->get_type();
		if ( array_key_exists( $product_type, $this->product_names ) ) {
			// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
			echo "\t\t" . 'jQuery("#general_product_data .pricing").show();' . "\n";
			echo "\t\t" . 'if (tax.length) {' . "\n";
			echo "\t\t\t" . "tax.parent().show();\n";
			echo "\t\t" . '}' . "\n";
			// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found
		}

		// Close script.
		echo "\t" . '});' . "\n";
		echo '</script>' . "\n";

	}

	/**
	 * Adds the CiviCRM Settings as meta to the Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_saved( $product ) {

		// Bail if not one of our Product Types.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return;
		}

		/**
		 * Fires before the settings from the "CiviCRM Settings" Product Tab have been saved.
		 *
		 * Used internally by:
		 *
		 * * WPCV_Woo_Civi_Settings_Products::panel_clear_meta() (Priority: 10)
		 * * WPCV_Woo_Civi_Membership::panel_clear_meta() (Priority: 20)
		 * * WPCV_Woo_Civi_Participant::panel_clear_meta() (Priority: 30)
		 * * WPCV_Woo_Civi_Products_Variable::panel_clear_meta() (Priority: 40)
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/custom/panel/saved/before', $product );

		// Save relevant metadata for this Product Type. Nonce has been verified by WooCommerce.
		foreach ( $this->product_types_meta[ $product_type ] as $code => $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$value = sanitize_key( $_POST[ $meta_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$product->add_meta_data( $meta_key, $value, true );
			}
		}

		/**
		 * Fires after the settings from the Custom Product "CiviCRM Settings" Tab have been saved.
		 *
		 * Dynamic action to target a specific Custom Product Type, e.g.
		 *
		 * * wpcv_woo_civi/product/civicrm_contribution/panel/saved/after
		 * * wpcv_woo_civi/product/civicrm_membership/panel/saved/after
		 * * wpcv_woo_civi/product/civicrm_participant/panel/saved/after
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/' . $product_type . '/panel/saved/after', $product );

		/**
		 * Fires after the settings from the "CiviCRM Settings" Product Tab have been saved.
		 *
		 * @since 3.0
		 *
		 * @param WC_Product $product The Product object.
		 */
		do_action( 'wpcv_woo_civi/product/custom/panel/saved/after', $product );

	}

	/**
	 * Clears the metadata for this Product.
	 *
	 * @since 3.0
	 *
	 * @param WC_Product $product The Product object.
	 */
	public function panel_clear_meta( $product ) {

		// Bail if one of our Product Types.
		$product_type = $product->get_type();
		if ( array_key_exists( $product_type, $this->product_types_meta ) ) {
			return;
		}

		// Clear the current metadata.
		foreach ( $this->product_types_meta as $type => $meta ) {
			foreach ( $meta as $code => $meta_key ) {
				$product->delete_meta_data( $meta_key );
			}
		}

	}

	/**
	 * Adds the "Add to cart" button to our Custom Product Type Product pages.
	 *
	 * @see woocommerce_template_single_add_to_cart()
	 *
	 * @since 3.0
	 */
	public function buttons_add() {
		do_action( 'woocommerce_simple_add_to_cart' );
	}

	/**
	 * Gets the Price Field Value ID from Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $pfv_id The possibly found Price Field Value ID.
	 * @param integer $product_id The Product ID.
	 * @param object  $product The WooCommerce Product object.
	 * @return integer $pfv_id The found Price Field Value ID, passed through otherwise.
	 */
	public function pfv_id_get( $pfv_id, $product_id, $product = null ) {

		// Pass through if already found.
		if ( 0 !== $pfv_id ) {
			return $pfv_id;
		}

		// Get the Product if not supplied.
		if ( empty( $product ) ) {
			$product = wc_get_product( $product_id );
		}

		// Pass through if Product not found.
		if ( empty( $product ) ) {
			return $pfv_id;
		}

		// Pass through if not one of our Product Types.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return $pfv_id;
		}

		// Return the Price Field Value ID if found.
		$product_pfv_id = $this->get_meta( $product_id, $product_type, 'pfv_id' );
		if ( ! empty( $product_pfv_id ) ) {
			return $product_pfv_id;
		}

		// Not found.
		return 0;

	}

	/**
	 * Gets the requested meta key for the Custom Product Type.
	 *
	 * This method does not check for the existence of an entry in the array
	 * because it needs to be called correctly and will produce log entries
	 * when it is not.
	 *
	 * @since 3.0
	 *
	 * @param string $type The name of the Custom Product Type.
	 * @param string $key The shorthand for the meta key.
	 * @return string The requested meta key.
	 */
	public function get_meta_key( $type, $key ) {
		return $this->product_types_meta[ $type ][ $key ];
	}

	/**
	 * Gets the metadata from Product meta.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param string  $type The name of the WooCommerce Product Type.
	 * @param string  $key The name of the meta key.
	 * @return mixed $value The value, false otherwise.
	 */
	public function get_meta( $product_id, $type, $key ) {
		$value = false;
		if ( ! empty( $this->product_types_meta[ $type ][ $key ] ) ) {
			$value = get_post_meta( $product_id, $this->product_types_meta[ $type ][ $key ], true );
		}
		return $value;
	}

	/**
	 * Sets the metadata on a Product.
	 *
	 * @since 3.0
	 *
	 * @param integer $product_id The Product ID.
	 * @param string  $type The name of the WooCommerce Product Type.
	 * @param string  $key The name of the meta key.
	 * @param mixed   $value The value to save.
	 */
	public function set_meta( $product_id, $type, $key, $value ) {
		if ( ! empty( $this->product_types_meta[ $type ][ $key ] ) ) {
			update_post_meta( $product_id, $this->product_types_meta[ $type ][ $key ], $value );
		}
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

		// Our Custom Product Types always sync.
		if ( array_key_exists( $product_type, $this->product_types_meta ) ) {
			$skip = false;
		}

		return $skip;

	}

	/**
	 * Filters a Line Item to create an Entity that matches the Custom Product Type.
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

		// Bail if not one of our Product Types.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return $line_item;
		}

		// Send to relevant method.
		switch ( $product_type ) {
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
		$product_type_name = 'civicrm_contribution';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $product_type_name, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $product_type_name, 'pfv_id' );
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
	 * except that the source of the data is the Custom Product Type.
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
		$product_type_name = 'civicrm_membership';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $product_type_name, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Membership Type ID from Product meta.
		$membership_type_id = $this->get_meta( $product_id, $product_type_name, 'type_id' );
		if ( empty( $membership_type_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $product_type_name, 'pfv_id' );
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
	 * except that the source of the data is the Custom Product Type.
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

		// Define the Product Type name.
		$product_type_name = 'civicrm_participant';

		// Get the Product ID.
		$product_id = $product->get_id();

		// Get Financial Type ID from Product meta.
		$financial_type_id = $this->get_meta( $product_id, $product_type_name, 'financial_type_id' );
		if ( empty( $financial_type_id ) ) {
			return $line_item;
		}

		// Get Event ID from Product meta.
		$event_id = $this->get_meta( $product_id, $product_type_name, 'event_id' );
		if ( empty( $event_id ) ) {
			return $line_item;
		}

		// Get Participant Role ID from Product meta.
		$participant_role_id = $this->get_meta( $product_id, $product_type_name, 'role_id' );
		if ( empty( $participant_role_id ) ) {
			return $line_item;
		}

		// Get Price Field Value ID from Product meta.
		$price_field_value_id = $this->get_meta( $product_id, $product_type_name, 'pfv_id' );
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
	 * Gets the Entity Type from Product meta.
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

		// Pass through if not one of our Product Types.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return $entity_type;
		}

		// The Product Type is the same as the Entity Type.
		return $product_type;

	}

	/**
	 * Gets the Financial Type ID from Product meta.
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

		// Pass through if not one of our Product Types.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return $financial_type_id;
		}

		// Return the Financial Type ID if found.
		$product_financial_type_id = $this->get_meta( $product_id, $product_type, 'financial_type_id' );
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

		// Bail if not a Custom Product Type.
		$product_type = $product->get_type();
		if ( ! array_key_exists( $product_type, $this->product_types_meta ) ) {
			return;
		}

		// Save Financial Type ID to meta.
		$this->set_meta( $product->get_id(), $product_type, 'financial_type_id', $financial_type_id );

	}

	/**
	 * Filters the Product Type options to exclude Custom Product Types.
	 *
	 * @since 3.0
	 *
	 * @param array $product_types The existing array of WooCommerce Product Types.
	 * @return array $product_types The modified array of WooCommerce Product Types.
	 */
	public function product_types_filter( $product_types ) {

		// Remove the Custom Product Types.
		foreach ( $this->product_names as $type => $name ) {
			if ( array_key_exists( $type, $product_types ) ) {
				unset( $product_types[ $type ] );
			}
		}

		return $product_types;

	}

}
