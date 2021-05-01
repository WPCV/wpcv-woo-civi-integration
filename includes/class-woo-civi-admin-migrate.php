<?php
/**
 * Admin Migrate class.
 *
 * Handles admin tasks for this plugin. particularly migrating from the old plugin
 * to the current one.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin Migrate class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Admin_Migrate {

	/**
	 * Migration Page reference.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $migrate_page The Migration Page reference.
	 */
	public $migrate_page;

	/**
	 * Migration Page slug.
	 *
	 * @since 3.0
	 * @access public
	 * @var str $migrate_page_slug The slug of the Migration Page.
	 */
	public $migrate_page_slug = 'wpcv_woocivi_migrate';

	/**
	 * The number of Products to process per AJAX request.
	 *
	 * @since 3.0
	 * @access public
	 * @var int $step_count The number of Products to process per AJAX request.
	 */
	public $step_count = 10;

	/**
	 * Class constructor.
	 *
	 * @since 3.0
	 */
	public function __construct() {

		// Is this the back end?
		if ( ! is_admin() ) {
			return;
		}

		// Init on init.
		add_action( 'init', [ $this, 'initialise' ] );

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

		// Show a notice.
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );

		// Add menu item(s) to WordPress admin menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );

		// Add our meta boxes.
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 1 );

		// Add AJAX handler.
		add_action( 'wp_ajax_wpcv_process_products', [ $this, 'products_process' ] );

	}

	/**
	 * Show a notice when WooCommerce CiviCRM is present.
	 *
	 * @since 3.0
	 */
	public function admin_notice() {

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get current screen.
		$screen = get_current_screen();

		// Bail if it's not what we expect.
		if ( ! ( $screen instanceof WP_Screen ) ) {
			return;
		}

		// Bail if we are on our "Migration" page.
		if ( $screen->id == 'civicrm_page_' . $this->migrate_page_slug ) {
			return;
		}

		// Show general "Call to Action".
		$message = sprintf(
			__( '%1$sWooCommerce CiviCRM%2$s has become %3$sIntegrate CiviCRM with WooCommerce%4$s. Please visit the %5$sMigration Page%6$s to switch over.', 'wpcv-woo-civi-integration' ),
			'<strong>', '</strong>',
			'<strong>', '</strong>',
			'<a href="' . menu_page_url( 'wpcv_woocivi_migrate', false ) . '">', '</a>'
		);

		// Show it.
		echo '<div id="message" class="notice notice-warning">';
		echo '<p>' . $message . '</p>';
		echo '</div>';

	}

	/**
	 * Add our admin page(s) to the WordPress admin menu.
	 *
	 * @since 3.0
	 */
	public function admin_menu() {

		// We must be network admin in Multisite.
		if ( is_multisite() AND ! is_super_admin() ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add our "Migration Page" to the CiviCRM menu.
		$this->migrate_page = add_submenu_page(
			'CiviCRM', // Parent slug.
			__( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ), // Page title.
			__( 'WooCommerce', 'wpcv-woo-civi-integration' ), // Menu title.
			'manage_options', // Required caps.
			$this->migrate_page_slug, // Slug name.
			[ $this, 'page_migrate' ], // Callback.
			10
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->migrate_page, [ $this, 'form_submitted' ] );

		// Add styles and scripts only on our "Migration Page".
		// @see wp-admin/admin-header.php
		add_action( 'admin_head-' . $this->migrate_page, [ $this, 'admin_head' ] );
		add_action( 'admin_print_styles-' . $this->migrate_page, [ $this, 'admin_styles' ] );
		add_action( 'admin_print_scripts-' . $this->migrate_page, [ $this, 'admin_scripts' ] );

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

		$handle = 'wpcv_woocivi_js';

		// Enqueue Javascript.
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/pages/page-admin-migrate.js', WPCV_WOO_CIVI_FILE ),
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			WPCV_WOO_CIVI_VERSION // Version.
		);

		$localisation = [
			'total' => esc_html__( '{{total}} Products to clean up...', 'wpcv-woo-civi-integration' ),
			'current' => esc_html__( 'Processing Products {{from}} to {{to}}', 'wpcv-woo-civi-integration' ),
			'complete' => esc_html__( 'Processing Products {{from}} to {{to}} complete', 'wpcv-woo-civi-integration' ),
			'done' => esc_html__( 'All done!', 'wpcv-woo-civi-integration' ),
		];

		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'total_products' => $this->products_get_count(),
			'batch_count' => $this->step_count,
		];

		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script( $handle, 'WPCV_Woo_Civi_Migrate_Settings', $vars );

	}

	/**
	 * Enqueue any styles needed by our Migrate Page.
	 *
	 * @since 3.0
	 */
	public function admin_styles() {

		// Enqueue CSS.
		wp_enqueue_style(
			'wpcv-woo-civi-admin-migrate',
			WPCV_WOO_CIVI_URL . 'assets/css/pages/page-admin-migrate.css',
			null,
			WPCV_WOO_CIVI_VERSION,
			'all' // Media.
		);

	}

	/**
	 * Show our "Migration Page".
	 *
	 * @since 3.0
	 */
	public function page_migrate() {

		// We must be network admin in Multisite.
		if ( is_multisite() AND ! is_super_admin() ) {
			wp_die( __( 'You do not have permission to access this page.', 'wpcv-woo-civi-integration' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wpcv-woo-civi-integration' ) );
		}

		// Get current screen.
		$screen = get_current_screen();

		/**
		 * Allow meta boxes to be added to this screen.
		 *
		 * The Screen ID to use is: "civicrm_page_wpcv_woocivi_migrate".
		 *
		 * @since 3.0
		 *
		 * @param string $screen_id The ID of the current screen.
		 */
		do_action( 'add_meta_boxes', $screen->id, null );

		// Grab columns.
		$columns = ( 1 == $screen->get_columns() ? '1' : '2' );

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/pages/page-admin-migrate.php';

	}

	/**
	 * Get the URL for the form action.
	 *
	 * @since 3.0
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function page_submit_url_get() {

		// Sanitise admin page url.
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );

		// Strip flag, if present, and rebuild.
		if ( ! empty( $url_array ) ) {
			$url_raw = str_replace( '&amp;updated=true', '', $url_array[0] );
			$target_url = htmlentities( $url_raw . '&updated=true' );
		}

		// --<
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
			'civicrm_page_' . $this->migrate_page_slug,
		];

		// Bail if not the Screen ID we want.
		if ( ! in_array( $screen_id, $screen_ids ) ) {
			return;
		}

		// Bail if user cannot access CiviCRM.
		if ( ! current_user_can( 'access_civicrm' ) ) {
			return;
		}

		// Init data.
		$data = [];

		// Have we already migrated?
		$data['migrated'] = false;
		if ( 'accepted' === get_site_option( 'wpcv_woo_civi_migration', 'not accepted' ) ) {
			$data['migrated'] = true;
		}

		// Only show submit if not migrated.
		if ( $data['migrated'] === false ) {

			// Create Submit metabox.
			add_meta_box(
				'submitdiv',
				__( 'Confirm Migration', 'wpcv-woo-civi-integration' ),
				[ $this, 'meta_box_submit_render' ], // Callback.
				$screen_id, // Screen ID.
				'side', // Column: options are 'normal' and 'side'.
				'core', // Vertical placement: options are 'core', 'high', 'low'.
				$data
			);

		}

		// Init meta box title.
		$title = __( 'Migration Tasks', 'wpcv-woo-civi-integration' );
		if ( $data['migrated'] === true ) {
			$title = __( 'Migration Complete', 'wpcv-woo-civi-integration' );
		}

		// Have we already resolved duplicate metadata?
		$data['metadata'] = false;
		if ( 'metadata' === get_site_option( 'wpcv_woo_civi_migration', 'not resolved' ) ) {
			$data['metadata'] = true;
		}

		// Get the current state of the stepper.
		$data['offset'] = $this->stepped_offset_get( 'products' );

		// Set the button title.
		$data['button_title'] = esc_html__( 'Process Products', 'wpcv-woo-civi-integration' );
		if ( $data['offset'] !== false ) {
			$data['button_title'] = esc_html__( 'Continue Processing', 'wpcv-woo-civi-integration' );
		}

		// Create "Migrate Info" metabox.
		add_meta_box(
			'wpcv_woocivi_info',
			$title,
			[ $this, 'meta_box_migrate_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

	}

	/**
	 * Render Submit meta box on Admin screen.
	 *
	 * @since 3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_submit_render( $unused = NULL, $metabox ) {

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/metaboxes/metabox-migrate-submit.php';

	}

	/**
	 * Render "Migrate Settings" meta box on Admin screen.
	 *
	 * @since 3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_migrate_render( $unused = NULL, $metabox ) {

		// Include template file.
		include WPCV_WOO_CIVI_PATH . 'assets/templates/metaboxes/metabox-migrate-info.php';

	}

	/**
	 * Perform actions when the form has been submitted.
	 *
	 * @since 3.0
	 */
	public function form_submitted() {

		// If our "Submit" button was clicked.
		if ( ! empty( $_POST['wpcv_woocivi_save'] ) ) {
			$this->form_nonce_check();
			$this->form_migration_accept();
			$this->form_redirect( 'updated' );
		}

		// If our "Process Products" button was clicked.
		if ( ! empty( $_POST['wpcv_woocivi_process'] ) ) {
			$this->form_nonce_check();
			$this->products_process();
			$this->form_redirect();
		}

		// If our "Stop" button was clicked.
		if ( ! empty( $_POST['wpcv_woocivi_process_stop'] ) ) {
			$this->form_nonce_check();
			$this->stepped_offset_delete( 'products' );
			$this->form_redirect();
		}

	}

	/**
	 * Accept the migration tasks.
	 *
	 * @since 3.0
	 */
	private function form_migration_accept() {

		// Do this by setting the option to a unique string.
		update_site_option( 'wpcv_woo_civi_migration', 'accepted' );

	}

	/**
	 * Check the nonce.
	 *
	 * @since 3.0
	 */
	private function form_nonce_check() {

		// Do we trust the source of the data?
		check_admin_referer( 'wpcv_woocivi_migrate_action', 'wpcv_woocivi_migrate_nonce' );

	}

	/**
	 * Redirect to the Settings page with an optional extra param.
	 *
	 * @since 3.0
	 *
	 * @param str $mode Pass 'updated' to append the extra param.
	 */
	private function form_redirect( $mode = '' ) {

		// Our default array of arguments.
		$args = [
			'page' => $this->migrate_page_slug,
		];

		// Maybe append param.
		if ( $mode === 'updated' ) {
			$args['settings-updated'] = 'true';
		}

		// Redirect to our admin page.
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;

	}

	/**
	 * Gets the total number of Products to process.
	 *
	 * @since 3.0
	 */
	public function products_get_count() {

		$query = new WP_Query( [
			'post_type' => 'product',
		] );

		return $query->found_posts;

	}

	/**
	 * The "Process Products" AJAX callback.
	 *
	 * @since 3.0
	 */
	public function products_process() {

		// Set a stepper key.
		$key = 'products';

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'wpcv_migrate_products', false, false );
		}

		// If we get an error.
		if ( $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			wp_send_json( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Construct args.
		$query_args = [
			'post_type' => 'product',
			'no_found_rows' => true,
			'numberposts' => $this->step_count,
			'offset' => $offset,
		];

		// The query.
		$query = new WP_Query( $query_args );

		// If we get results.
		if ( $query->have_posts() ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there fewer items than the step count?
			if ( $query->post_count < $this->step_count ) {
				$diff = $query->post_count;
			} else {
				$diff = $this->step_count;
			}

			// Set from and to flags.
			$data['from'] = (int) $offset;
			$data['to'] = $data['from'] + $diff;

			// Find out if CiviMember is active.
			$member_active = false;
			if ( WPCV_WCI()->boot_civi() ) {
				$components = CRM_Core_Component::getEnabledComponents();
				if ( array_key_exists( 'CiviMember', $components ) ) {
					$member_active = true;
				}
			}

			// Loop and set up post.
			while ( $query->have_posts() ) { $query->the_post();

				// Grat Product ID.
				$product_id = get_the_ID();

				// Process this Product.
				$this->product_process( $product_id, $member_active );

			}

			// Reset Post data just in case.
			wp_reset_postdata();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

			// Set the migration option to a unique string.
			update_site_option( 'wpcv_woo_civi_migration', 'metadata' );

		}

		// Send data to browser.
		if ( wp_doing_ajax() ) {
			wp_send_json( $data );
		}

	}

	/**
	 * Process a Product.
	 *
	 * @since 3.0
	 *
	 * @param int $product_id The numeric ID of the Product.
	 * @param bool $member_active True if the CiviMember Component is active.
	 */
	public function product_process( $product_id, $member_active ) {

		// Does the Product have the duplicate meta?
		$duplicate = get_post_meta( $product_id, '_civicrm_contribution_type', true );

		// When it does, update the proper meta and remove duplicate.
		if ( ! empty( $duplicate ) || $duplicate == 0 ) {
			//if ( ! empty( $duplicate ) ) {
				update_post_meta( $product_id, '_woocommerce_civicrm_financial_type_id', $duplicate );
			//}
			delete_post_meta( $product_id, '_civicrm_contribution_type' );
		}

		// Does the Product have meta without the leading underscore?
		$financial_type_id = get_post_meta( $product_id, 'woocommerce_civicrm_financial_type_id', true );

		// When it does, update the proper meta and remove old.
		if ( ! empty( $financial_type_id ) || $financial_type_id == 0 ) {
			//if ( ! empty( $financial_type_id ) ) {
				update_post_meta( $product_id, '_woocommerce_civicrm_financial_type_id', $financial_type_id );
			//}
			delete_post_meta( $product_id, 'woocommerce_civicrm_financial_type_id' );
		}

		// Does the Product have meta without the leading underscore?
		$membership_type_id = get_post_meta( $product_id, 'woocommerce_civicrm_membership_type_id', true );

		// When it does, update the proper meta and remove old.
		if ( ! empty( $membership_type_id ) || $membership_type_id == 0 ) {
			if ( $member_active ) {
				update_post_meta( $product_id, '_woocommerce_civicrm_membership_type_id', $membership_type_id );
			}
			delete_post_meta( $product_id, 'woocommerce_civicrm_membership_type_id' );
		}

	}

	/**
	 * Initialise the stepper.
	 *
	 * @since 3.0
	 *
	 * @param string $key The unique identifier for the stepper.
	 * @param int $offset The unique identifier for the stepper.
	 */
	public function stepped_offset_init( $key ) {

		// Construct option name.
		$option = '_wpcv_woo_civi_migrate_' . $key . '_offset';

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( $option, 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( $option, '0' );

		} else {

			// Use the existing value.
			$offset = (int) get_option( $option, '0' );

		}

		// --<
		return $offset;

	}

	/**
	 * Get the current state of the stepper.
	 *
	 * @since 3.0
	 *
	 * @param string $key The unique identifier for the stepper.
	 * @return int|bool $offset The numeric value of the stepper, or false otherwise.
	 */
	public function stepped_offset_get( $key ) {

		// Construct option name.
		$option = '_wpcv_woo_civi_migrate_' . $key . '_offset';

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( $option, 'fgffgs' ) ) {
			return false;
		}

		// Return the existing value.
		return (int) get_option( $option, '0' );

	}

	/**
	 * Update the stepper.
	 *
	 * @since 3.0
	 *
	 * @param string $key The unique identifier for the stepper.
	 * @param string $to The value for the stepper.
	 */
	public function stepped_offset_update( $key, $to ) {

		// Construct option name.
		$option = '_wpcv_woo_civi_migrate_' . $key . '_offset';

		// Increment offset option.
		update_option( $option, (string) $to );

	}

	/**
	 * Delete the stepper.
	 *
	 * @since 3.0
	 *
	 * @param string $key The unique identifier for the stepper.
	 */
	public function stepped_offset_delete( $key ) {

		// Construct option name.
		$option = '_wpcv_woo_civi_migrate_' . $key . '_offset';

		// Delete the option to start from the beginning.
		delete_option( $option );

	}

}
