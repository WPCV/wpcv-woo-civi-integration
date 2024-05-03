<?php
/**
 * Admin class.
 *
 * Handles admin tasks for this plugin.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Admin {

	/**
	 * Admin Page reference.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $admin_page The Admin Page reference.
	 */
	public $admin_page;

	/**
	 * Admin Page slug.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $admin_page_slug The slug of the Admin Page.
	 */
	public $admin_page_slug = 'wpcv_admin';

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
	 * Register WordPress hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		// Bail if WordPress Network Admin.
		if ( is_multisite() && is_network_admin() ) {
			return;
		}

		// Bail if not WordPress Admin.
		if ( ! is_admin() ) {
			return;
		}

		// Add menu item(s) to WordPress admin menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 100 );

		// Add our meta boxes.
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 1 );

		// Enable WooCommerce Javascripts.
		add_filter( 'woocommerce_screen_ids', [ $this, 'admin_scripts_enable_woo' ] );

		// Add AJAX handlers.
		add_action( 'wp_ajax_wpcv_process_event', [ $this, 'event_process' ] );

	}

	/**
	 * Add our admin page(s) to the WordPress admin menu.
	 *
	 * @since 3.0
	 */
	public function admin_menu() {

		// We must be network admin in Multisite.
		if ( is_multisite() && ! is_super_admin() ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add our Admin page to the CiviCRM menu.
		$this->admin_page = add_submenu_page(
			'CiviCRM', // Parent slug.
			__( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ), // Page title.
			__( 'WooCommerce', 'wpcv-woo-civi-integration' ), // Menu title.
			'manage_options', // Required caps.
			$this->admin_page_slug, // Slug name.
			[ $this, 'page_admin' ], // Callback.
			10
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->admin_page, [ $this, 'form_submitted' ] );

		/*
		 * Add styles and scripts only on our Admin page.
		 * @see wp-admin/admin-header.php
		 */
		add_action( 'admin_head-' . $this->admin_page, [ $this, 'admin_head' ] );
		add_action( 'admin_print_styles-' . $this->admin_page, [ $this, 'admin_styles' ] );
		add_action( 'admin_print_scripts-' . $this->admin_page, [ $this, 'admin_scripts' ] );

		/**
		 * Broadcast that the admin page has been added.
		 *
		 * @since 3.0
		 *
		 * @param string $admin_page The handle of the admin page.
		 */
		do_action( 'wpcv_woo_civi/admin/admin_page', $this->admin_page );

	}

	/**
	 * Add metabox scripts and initialise plugin help.
	 *
	 * @since 3.0
	 */
	public function admin_head() {

		// Enqueue WordPress scripts.
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'dashboard' );

	}

	/**
	 * Enqueue required scripts.
	 *
	 * @since 3.0
	 */
	public function admin_scripts() {

		$handle = 'wpcv_woocivi_admin_js';

		// Enqueue Javascript.
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/pages/page-admin.js', WPCV_WOO_CIVI_FILE ),
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			WPCV_WOO_CIVI_VERSION, // Version.
			false
		);

		$localisation = [
			'event_button' => esc_html__( 'Create Product', 'wpcv-woo-civi-integration' ),
			'creating'     => esc_html__( 'Creating...', 'wpcv-woo-civi-integration' ),
		];

		$settings = [
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'notice_success' => '<div class="event_success notice notice-success inline is-dismissible" style="background-color: #f7f7f7; display: none;"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'wpcv-woo-civi-integration' ) . '</span></button></div>',
			'notice_error'   => '<div class="event_error notice notice-error inline is-dismissible" style="background-color: #f7f7f7; display: none;"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'wpcv-woo-civi-integration' ) . '</span></button></div>',
		];

		$vars = [
			'localisation' => $localisation,
			'settings'     => $settings,
		];

		/**
		 * Filter the Javascript vars before localising the script.
		 *
		 * @since 3.0
		 *
		 * @param array $vars The array of Javascript vars.
		 */
		$vars = apply_filters( 'wpcv_woo_civi/admin/admin_scripts/vars', $vars );

		// Localise the WordPress way.
		wp_localize_script( $handle, 'WPCV_Woo_Civi_Admin_Vars', $vars );

		/**
		 * Broadcast that the script has been enqueued.
		 *
		 * @since 3.0
		 *
		 * @param string $handle The handle of the script.
		 * @param array $vars The array of Javascript vars.
		 */
		do_action( 'wpcv_woo_civi/admin/admin_scripts/enqueued', $handle, $vars );

	}

	/**
	 * Force WooCommerce Javascripts to load by adding our Admin Page Screen ID.
	 *
	 * @since 3.0
	 *
	 * @param array $screen_ids The existing array of WooCommerce Screen IDs.
	 * @return array $screen_ids The modified array of WooCommerce Screen IDs.
	 */
	public function admin_scripts_enable_woo( $screen_ids ) {
		$screen_ids[] = 'civicrm_page_' . $this->admin_page_slug;
		return $screen_ids;
	}

	/**
	 * Enqueue any styles needed by our Admin Page.
	 *
	 * @since 3.0
	 */
	public function admin_styles() {

		// Enqueue CSS.
		wp_enqueue_style(
			'wpcv-woo-civi-admin',
			WPCV_WOO_CIVI_URL . 'assets/css/pages/page-admin.css',
			null,
			WPCV_WOO_CIVI_VERSION,
			'all' // Media.
		);

	}

	/**
	 * Show our Admin page.
	 *
	 * @since 3.0
	 */
	public function page_admin() {

		// We must be network admin in Multisite.
		if ( is_multisite() && ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpcv-woo-civi-integration' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpcv-woo-civi-integration' ) );
		}

		// Get current screen.
		$screen = get_current_screen();

		/**
		 * Allow meta boxes to be added to this screen.
		 *
		 * The Screen ID to use is: "civicrm_page_wpcv_woocivi_admin".
		 *
		 * @since 3.0
		 *
		 * @param string $screen_id The ID of the current screen.
		 */
		do_action( 'add_meta_boxes', $screen->id, null );

		// Get the column CSS class.
		$columns     = absint( $screen->get_columns() );
		$columns_css = '';
		if ( $columns ) {
			$columns_css = " columns-$columns";
		}

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/pages/page-admin.php';

	}

	/**
	 * Get the URL for the form action.
	 *
	 * @since 3.0
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function page_submit_url_get() {

		$target_url = menu_page_url( $this->admin_page_slug, false );
		return $target_url;

	}

	/**
	 * Register meta boxes.
	 *
	 * @since 3.0
	 *
	 * @param string $screen_id The Admin Page Screen ID.
	 */
	public function meta_boxes_add( $screen_id ) {

		// Define valid Screen IDs.
		$screen_ids = [
			'civicrm_page_' . $this->admin_page_slug,
		];

		// Bail if not the Screen ID we want.
		if ( ! in_array( $screen_id, $screen_ids, true ) ) {
			return;
		}

		// Bail if user cannot access CiviCRM.
		if ( ! current_user_can( 'access_civicrm' ) ) {
			return;
		}

		// Init common data.
		$data = [];

		// Get the array of Financial Types.
		$data['financial_types'] = [
			'' => __( 'Select a Financial Type', 'wpcv-woo-civi-integration' ),
		] + WPCV_WCI()->helper->get_financial_types();

		// Get the array of Price Sets.
		$data['price_sets'] = WPCV_WCI()->helper->get_price_sets_populated();

		/**
		 * Filter the common metabox data.
		 *
		 * @since 3.0
		 *
		 * @param array $vars The array of metabox data.
		 */
		$data = apply_filters( 'wpcv_woo_civi/admin/meta_boxes_add/data', $data );

		/*
		// Define a handle for the "Create Product for Contribution" metabox.
		$handle = 'wpcv_woocivi_contribution_to_product';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'Create Product for Contribution', 'wpcv-woo-civi-integration' ),
			[ $this, 'meta_box_contribution_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		// Make this metabox closed by default.
		//add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		/*
		// Define a handle for the "Create Product for Membership" metabox.
		$handle = 'wpcv_woocivi_membership_to_product';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'Create Product for Membership', 'wpcv-woo-civi-integration' ),
			[ $this, 'meta_box_membership_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		// Make this metabox closed by default.
		//add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		// Define a handle for the "Create Product for Event" metabox.
		$handle = 'wpcv_woocivi_event_to_product';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'Create Product for Event', 'wpcv-woo-civi-integration' ),
			[ $this, 'meta_box_event_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		/*
		// Make this metabox closed by default.
		add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		/**
		 * Broadcast that the metaboxes have been added.
		 *
		 * @since 3.0
		 *
		 * @param string $screen_id The Screen indentifier.
		 * @param array $vars The array of metabox data.
		 */
		do_action( 'wpcv_woo_civi/admin/meta_boxes_added', $screen_id, $data );

	}

	/**
	 * Load our metaboxes as closed by default.
	 *
	 * @since 3.0
	 *
	 * @param string[] $classes An array of postbox classes.
	 */
	public function meta_box_closed( $classes ) {

		// Add closed class.
		if ( is_array( $classes ) ) {
			if ( ! in_array( 'closed', $classes, true ) ) {
				$classes[] = 'closed';
			}
		}

		return $classes;

	}

	/**
	 * Render "Create Product for Contribution" meta box on Admin screen.
	 *
	 * @since 3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_contribution_render( $unused, $metabox ) {

		// Configure the submit button.
		$metabox['args']['button_title'] = esc_html__( 'Create Product', 'wpcv-woo-civi-integration' );
		$metabox['args']['button_args']  = [
			'data-security' => esc_attr( wp_create_nonce( 'wpcv_manual_sync_contribution' ) ),
			'style'         => 'float: right;',
		];

		// Assume there is no Custom Contribution Product Type.
		$metabox['args']['custom_product_type_exists'] = false;
		if ( WPCV_WCI()->contribution->active ) {
			$metabox['args']['custom_product_type_exists'] = true;
		}

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/metaboxes/metabox-admin-contribution.php';

	}

	/**
	 * Render "Create Product for Membership" meta box on Admin screen.
	 *
	 * @since 3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_membership_render( $unused, $metabox ) {

		// Get the array of Membership Types.
		$metabox['args']['types'] = WPCV_WCI()->membership->get_membership_types_options();

		// Configure the submit button.
		$metabox['args']['button_title'] = esc_html__( 'Create Product', 'wpcv-woo-civi-integration' );
		$metabox['args']['button_args']  = [
			'data-security' => esc_attr( wp_create_nonce( 'wpcv_manual_sync_membership' ) ),
			'style'         => 'float: right;',
		];

		// Assume there is no Custom Membership Product Type.
		$metabox['args']['custom_product_type_exists'] = false;
		if ( WPCV_WCI()->membership->active ) {
			$metabox['args']['custom_product_type_exists'] = true;
		}

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/metaboxes/metabox-admin-membership.php';

	}

	/**
	 * Render "Create Product for Event" meta box on Admin screen.
	 *
	 * @since 3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_event_render( $unused, $metabox ) {

		// Get the array of Events.
		$metabox['args']['events'] = WPCV_WCI()->participant->get_event_options();

		// Get the array of Participant Roles.
		$metabox['args']['roles'] = WPCV_WCI()->participant->get_participant_roles_options();

		// Configure the submit button.
		$metabox['args']['button_title'] = esc_html__( 'Create Product', 'wpcv-woo-civi-integration' );
		$metabox['args']['button_args']  = [
			'data-security' => esc_attr( wp_create_nonce( 'wpcv_manual_sync_event' ) ),
			'style'         => 'float: right;',
		];

		// Assume there is no Custom Participant Product Type.
		$metabox['args']['custom_product_type_exists'] = false;
		if ( WPCV_WCI()->participant->active ) {
			$metabox['args']['custom_product_type_exists'] = true;
		}

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/metaboxes/metabox-admin-event.php';

	}

	/**
	 * Perform actions when the form has been submitted.
	 *
	 * At the moment only the form in the "Create Product for Event" metabox
	 * can be submitted without Javascript being enabled.
	 *
	 * @since 3.0
	 */
	public function form_submitted() {

		/*
		// If our "Create Product for Contribution" button was clicked.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['wpcv_woocivi_contribution_process'] ) ) {
			$this->form_nonce_check();
			$this->contribution_process();
			$this->form_redirect();
		}
		*/

		/*
		// If our "Create Product for Membership" button was clicked.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['wpcv_woocivi_membership_process'] ) ) {
			$this->form_nonce_check();
			$this->membership_process();
			$this->form_redirect();
		}
		*/

		// If our "Create Product for Event" button was clicked.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['wpcv_woocivi_event_process'] ) ) {
			$this->form_nonce_check();
			$this->event_process();
			$this->form_redirect();
		}

		/**
		 * Broadcast that the form has been submitted.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/admin/form_submitted' );

	}

	/**
	 * Check the nonce.
	 *
	 * @since 3.0
	 */
	private function form_nonce_check() {

		// Do we trust the source of the data?
		check_admin_referer( 'wpcv_woocivi_admin_action', 'wpcv_woocivi_admin_nonce' );

	}

	/**
	 * Redirect to the Settings page with an optional extra param.
	 *
	 * @since 3.0
	 *
	 * @param string $mode Pass 'updated' to append the extra param.
	 */
	private function form_redirect( $mode = '' ) {

		// Our default array of arguments.
		$args = [
			'page' => $this->admin_page_slug,
		];

		// Maybe append param.
		if ( 'updated' === $mode ) {
			$args['settings-updated'] = 'true';
		}

		// Redirect to our admin page.
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;

	}

	/**
	 * The "Process Event" AJAX callback.
	 *
	 * @since 3.0
	 */
	public function event_process() {

		// Default response.
		$data = [
			'notice' => esc_html__( 'Could not create WooCommerce Product.', 'wpcv-woo-civi-integration' ),
			'saved'  => false,
		];

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'wpcv_manual_sync_event', false, false );
		}

		// If we get an error.
		if ( false === $result ) {
			$data['notice'] = esc_html__( 'Authentication failed.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Get inputs from POST and validate.
		$inputs = $this->event_inputs_parse( $data );

		// Get the CiviCRM Event data.
		$event = WPCV_WCI()->participant->get_event_by_id( $inputs['event_id'] );
		if ( false === $event ) {
			$data['notice'] = esc_html__( 'Unrecognised Event.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Create Product based on Price Field Value count and selected type.
		if ( count( $inputs['pfv_ids'] ) === 1 ) {
			if ( 'simple' === $inputs['product_type'] ) {
				$product = $this->event_product_create_simple( $inputs, $event );
			} else {
				$product = $this->event_product_create_custom( $inputs, $event );
			}
		} else {
			$product = $this->event_product_create_variable( $inputs, $event );
		}

		// Build success data.
		$data['saved']  = true;
		$data['notice'] = sprintf(
			/* translators: 1: Opening anchor tag, 2: Closing anchor tag */
			esc_html__( 'WooCommerce Product successfully created. %1$sView Product%1$s', 'wpcv-woo-civi-integration' ),
			'<a href="' . $product['permalink'] . '">',
			'</a>'
		);

		// Return the data.
		wp_send_json( $data );

	}

	/**
	 * Gets the inputs from the POST array and validates them.
	 *
	 * @since 3.0
	 *
	 * @param array $data The default AJAX return array.
	 * @return array $inputs The extracted and validated data.
	 */
	public function event_inputs_parse( $data ) {

		// Bail if there are no valid values.
		$values = filter_input( INPUT_POST, 'value', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( empty( $values ) ) {
			$data['notice'] = esc_html__( 'Unrecognised parameters.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Bail if the Product Type is not valid.
		$product_type = isset( $values['product_type'] ) ? sanitize_key( wp_unslash( $values['product_type'] ) ) : '';
		if ( empty( $product_type ) || ! in_array( $product_type, [ 'simple', 'custom' ], true ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Product Type.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Bail if the Financial Type is not valid.
		$financial_type_id = isset( $values['financial_type'] ) ? (int) sanitize_text_field( wp_unslash( $values['financial_type'] ) ) : 0;
		if ( empty( $financial_type_id ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Financial Type.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Bail if the Price Field Values are not valid.
		$pfv_ids = isset( $values['pfv_ids'] ) ? array_map( 'intval', $values['pfv_ids'] ) : [];
		if ( empty( $pfv_ids ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Price Field Values.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Bail if the Event ID is not valid.
		$event_id = isset( $values['event_id'] ) ? (int) sanitize_text_field( wp_unslash( $values['event_id'] ) ) : 0;
		if ( empty( $event_id ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Event ID.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Bail if the Participant Role is not valid.
		$role_id = isset( $values['role'] ) ? (int) sanitize_text_field( wp_unslash( $values['role'] ) ) : 0;
		if ( empty( $role_id ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Participant Role.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Combine inputs into an array.
		$inputs = [
			'product_type'      => $product_type,
			'financial_type_id' => $financial_type_id,
			'pfv_ids'           => $pfv_ids,
			'event_id'          => $event_id,
			'role_id'           => $role_id,
		];

		return $inputs;

	}

	/**
	 * Creates a new Simple Product.
	 *
	 * @since 3.0
	 *
	 * @param array $inputs The validated POST data.
	 * @param array $event The array of CiviCRM Event data.
	 * @return array $product The array of WooCommerce Product data.
	 */
	public function event_product_create_simple( $inputs, $event ) {

		// Init default Simple Product.
		$params = [
			'name'         => $event['title'],
			'description'  => ! empty( $event['description'] ) ? $event['description'] : '',
			'status'       => 'publish',
			'type'         => 'simple',
			'virtual'      => true,
			'downloadable' => false,
			// 'catalog_visibility' => 'hidden',
		];

		// Init Product meta data.
		$params['meta_data'] = [];

		// Add CiviCRM Entity.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products->entity_key,
			'value' => 'civicrm_participant',
		];

		// Add Financial Type ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products->financial_type_key,
			'value' => $inputs['financial_type_id'],
		];

		// Get the Price Field Value ID and add to metadata.
		$pfv_id                = array_pop( $inputs['pfv_ids'] );
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->participant->pfv_key,
			'value' => $pfv_id,
		];

		// Add Event ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->participant->event_key,
			'value' => $inputs['event_id'],
		];

		// Add Participant Role ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->participant->role_key,
			'value' => $inputs['role_id'],
		];

		// Get the full Price Field Value.
		$pfv = WPCV_WCI()->helper->get_price_field_value_by_id( $pfv_id );

		if ( empty( $pfv ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Price Field Value.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Set the Price.
		$params['regular_price'] = (float) $pfv['amount'];

		// Append to the Product title.
		$params['name'] = sprintf(
			/* translators: 1: Event Title, 2: Price Field Value label */
			esc_html__( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ),
			$params['name'],
			$pfv['label']
		);

		// Create the Participant Product.
		$product = WPCV_WCI()->products->create_product( $params );

		return $product;

	}

	/**
	 * Creates a new CiviCRM Participant Product.
	 *
	 * @since 3.0
	 *
	 * @param array $inputs The validated POST data.
	 * @param array $event The array of CiviCRM Event data.
	 * @return array $product The array of WooCommerce Product data.
	 */
	public function event_product_create_custom( $inputs, $event ) {

		// Init default Custom Product.
		$params = [
			'name'         => $event['title'],
			'description'  => ! empty( $event['description'] ) ? $event['description'] : '',
			'status'       => 'publish',
			'type'         => 'civicrm_participant',
			'virtual'      => true,
			'downloadable' => false,
			// 'catalog_visibility' => 'hidden',
		];

		// Declare CiviCRM Entity.
		$entity = 'civicrm_participant';

		// Init Product meta data.
		$params['meta_data'] = [];

		// Add Financial Type ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products_custom->get_meta_key( $entity, 'financial_type_id' ),
			'value' => $inputs['financial_type_id'],
		];

		// Get the Price Field Value ID and add to metadata.
		$pfv_id                = array_pop( $inputs['pfv_ids'] );
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products_custom->get_meta_key( $entity, 'pfv_id' ),
			'value' => $pfv_id,
		];

		// Add Event ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products_custom->get_meta_key( $entity, 'event_id' ),
			'value' => $inputs['event_id'],
		];

		// Add Participant Role ID.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products_custom->get_meta_key( $entity, 'role_id' ),
			'value' => $inputs['role_id'],
		];

		// Get the full Price Field Value.
		$pfv = WPCV_WCI()->helper->get_price_field_value_by_id( $pfv_id );

		if ( empty( $pfv ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Price Field Value.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Set the Price.
		$params['regular_price'] = (float) $pfv['amount'];

		// Append to the Product title.
		$params['name'] = sprintf(
			/* translators: 1: Event Title, 2: Price Field Value label */
			esc_html__( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ),
			$params['name'],
			$pfv['label']
		);

		// Create the Participant Product.
		$product = WPCV_WCI()->products->create_product( $params );

		return $product;

	}

	/**
	 * Creates a new Variable Product.
	 *
	 * @since 3.0
	 *
	 * @param array $inputs The validated POST data.
	 * @param array $event The array of CiviCRM Event data.
	 * @return array $product The array of WooCommerce Product data.
	 */
	public function event_product_create_variable( $inputs, $event ) {

		// Init default Product.
		$params = [
			'name'         => $event['title'],
			'description'  => ! empty( $event['description'] ) ? $event['description'] : '',
			'status'       => 'publish',
			'type'         => 'variable',
			'virtual'      => true,
			'downloadable' => false,
			// 'catalog_visibility' => 'hidden',
		];

		// Get Price Field data.
		$price_field_data = [];
		foreach ( $inputs['pfv_ids'] as $pfv_id ) {
			$price_field = WPCV_WCI()->helper->get_price_field_by_price_field_value_id( $pfv_id );
			if ( ! empty( $price_field ) ) {
				$price_field_data = $price_field;
				break;
			}
		}

		// Bail if there is no Price Field.
		if ( empty( $price_field_data ) ) {
			$data['notice'] = esc_html__( 'Unrecognised Price Field.', 'wpcv-woo-civi-integration' );
			wp_send_json( $data );
		}

		// Init Price Field Attributes.
		$pfv_attributes = [
			'name'      => $price_field_data['label'],
			'position'  => 0,
			'visible'   => true,
			'variation' => true,
			'options'   => [],
		];

		// Build Attribute options.
		foreach ( $inputs['pfv_ids'] as $pfv_id ) {

			// Get the full Price Field Value.
			$pfv = WPCV_WCI()->helper->get_price_field_value_by_id( $pfv_id );
			if ( empty( $pfv ) ) {
				continue;
			}

			// Add the Price Field Value as the slug.
			$pfv_attributes['options'][] = $pfv['label'];

		}

		// Add as Product Attributes.
		$params['attributes'] = [
			$pfv_attributes,
		];

		// Init Product meta data.
		$params['meta_data'] = [];

		// Add CiviCRM Entity to Product metadata.
		$params['meta_data'][] = [
			'key'   => WPCV_WCI()->products_variable->entity_key,
			'value' => 'civicrm_participant',
		];

		// Create the Variable Product.
		$product = WPCV_WCI()->products->create_product( $params );

		// Init Menu Order.
		$menu_order = 0;

		// Set CiviCRM Entity.
		$entity = 'civicrm_participant';

		// Create the Variations.
		foreach ( $inputs['pfv_ids'] as $pfv_id ) {

			// Get the full Price Field Value.
			$pfv = WPCV_WCI()->helper->get_price_field_value_by_id( $pfv_id );
			if ( empty( $pfv ) ) {
				continue;
			}

			// Init default Product Variation.
			$variant = [
				/* translators: 1: Event Title, 2: Price Field Value label */
				'title'         => sprintf( esc_html__( '%1$s (%2$s)', 'wpcv-woo-civi-integration' ), $event['title'], $pfv['label'] ),
				'product_id'    => $product['id'],
				'regular_price' => (float) $pfv['amount'],
				'menu_order'    => $menu_order,
				'virtual'       => true,
				'downloadable'  => false,
			];

			// Build Variation Attributes.
			$attributes   = [];
			$attributes[] = [
				'name'   => $pfv_attributes['name'],
				'option' => $pfv['label'],
			];

			// Assign Variation Attributes.
			$variant['attributes'] = $attributes;

			// Init Product Variation meta data.
			$variant['meta_data'] = [];

			// Add Financial Type ID.
			$variant['meta_data'][] = [
				'key'   => WPCV_WCI()->products_variable->get_meta_key( $entity, 'financial_type_id' ),
				'value' => $inputs['financial_type_id'],
			];

			// Add Price Field Value ID.
			$variant['meta_data'][] = [
				'key'   => WPCV_WCI()->products_variable->get_meta_key( $entity, 'pfv_id' ),
				'value' => $pfv_id,
			];

			// Add Event ID.
			$variant['meta_data'][] = [
				'key'   => WPCV_WCI()->products_variable->get_meta_key( $entity, 'event_id' ),
				'value' => $inputs['event_id'],
			];
			// Add Participant Role ID.
			$variant['meta_data'][] = [
				'key'   => WPCV_WCI()->products_variable->get_meta_key( $entity, 'role_id' ),
				'value' => $inputs['role_id'],
			];

			// Create the Product Variation.
			$variation = WPCV_WCI()->products->create_variation( $variant );

			// Bump Menu Order.
			$menu_order++;

		}

		return $product;

	}

}
