<?php
/**
 * Plugin Name: Integrate CiviCRM with WooCommerce
 * Plugin URI: https://github.com/WPCV/wpcv-woo-civi-integration
 * Description: Provides integration between CiviCRM and WooCommerce.
 * Author: WPCV
 * Author URI: https://github.com/WPCV
 * Version: 3.0
 * Requires at least: 5.7
 * Requires PHP: 7.1
 * Text Domain: wpcv-woo-civi-integration
 * Domain Path: /languages
 * Depends: CiviCRM
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap class.
 *
 * A class that encapsulates this plugin's functionality.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi {

	/**
	 * The class instance.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $instance The class instance.
	 */
	private static $instance;

	/**
	 * The Helper object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $helper The Helper object.
	 */
	public $helper;

	/**
	 * The Network Settings object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $settings_network The Network Settings object.
	 */
	public $settings_network;

	/**
	 * The Settings Tab object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $settings_tab The Settings Tab object.
	 */
	public $settings_tab;

	/**
	 * CiviCRM States/Provinces object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $states The States/Provinces object.
	 */
	public $states;

	/**
	 * The Contact object.
	 *
	 * @since 2.0
	 * @since 3.0 Renamed from "sync".
	 * @access public
	 * @var object $contact The Contact object.
	 */
	public $contact;

	/**
	 * WooCommerce Contribution object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $contribution The CiviCRM Contribution integration object.
	 */
	public $contribution;

	/**
	 * WooCommerce Order integration object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $order The WooCommerce Order integration object.
	 */
	public $order;

	/**
	 * Source management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $source The Source management object.
	 */
	public $source;

	/**
	 * Tax/VAT management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $tax The Tax/VAT management object.
	 */
	public $tax;

	/**
	 * Campaign management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $campaign The Campaign management object.
	 */
	public $campaign;

	/**
	 * Products object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $products The Products object.
	 */
	public $products;

	/**
	 * WooCommerce Product Tab object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object $products_tab The WooCommerce Product Tab object.
	 */
	public $products_tab;

	/**
	 * Membership management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $membership The Membership management object.
	 */
	public $membership;

	/**
	 * Participant management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $participant The Participant management object.
	 */
	public $participant;

	/**
	 * Dummy instance constructor.
	 *
	 * @since 3.0
	 */
	public function __construct() {}

	/**
	 * Returns a single instance of this object when called.
	 *
	 * @since 2.0
	 *
	 * @return object $instance The instance.
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) ) {

			// Instantiate.
			self::$instance = new WPCV_Woo_Civi();

			// Always define constants.
			self::define_constants();

			// Enable translation first.
			add_action( 'plugins_loaded', [ self::$instance, 'enable_translation' ] );

			// Check dependencies once all plugins are loaded.
			add_action( 'plugins_loaded', [ self::$instance, 'check_dependencies' ] );

			// Setup plugin when WooCommerce has been bootstrapped.
			add_action( 'woocommerce_init', [ self::$instance, 'initialise' ] );

		}

		// Always return instance.
		return self::$instance;

	}

	/**
	 * Initialise this plugin once WooCommerce has been bootstrapped.
	 *
	 * @since 3.0
	 */
	public function initialise() {

		// Defer to "WooCommerce CiviCRM" if present.
		if ( function_exists( 'WCI' ) ) {
			$this->migrate();
			return;
		}

		// Bootstrap this plugin.
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this plugin is loaded.
		 *
		 * Used internally by included classes in order to bootstrap.
		 *
		 * @since 2.0
		 */
		do_action( 'wpcv_woo_civi/loaded' );

	}

	/**
	 * Define plugin constants.
	 *
	 * @since 2.0
	 */
	private static function define_constants() {

		define( 'WPCV_WOO_CIVI_VERSION', '3.0' );
		define( 'WPCV_WOO_CIVI_FILE', __FILE__ );
		define( 'WPCV_WOO_CIVI_URL', plugin_dir_url( WPCV_WOO_CIVI_FILE ) );
		define( 'WPCV_WOO_CIVI_PATH', plugin_dir_path( WPCV_WOO_CIVI_FILE ) );

	}

	/**
	 * Bootstrap Migration functionality.
	 *
	 * @since 3.0
	 */
	private function migrate() {

		// Include Helper class and init.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-helper.php';
		$this->helper = new WPCV_Woo_Civi_Helper();

		// Include Contribution class and init.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contribution.php';
		$this->contribution = new WPCV_Woo_Civi_Contribution();

		// Include Admin Migrate class and init.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-admin-migrate.php';
		$this->migrate = new WPCV_Woo_Civi_Admin_Migrate();

	}

	/**
	 * Include plugin files.
	 *
	 * @since 2.0
	 */
	private function include_files() {

		// Include Helper class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-helper.php';

		// Include Network Settings class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings-network.php';
		// Include WooCommerce Settings Tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings-tab.php';
		// Include States class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-states.php';

		// Include Contact class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact.php';

		// Include Contribution class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contribution.php';
		// Include Order class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-order.php';
		// Include Source class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-source.php';
		// Include Tax/VAT class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-tax.php';

		// Include Products class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-products.php';
		// Include Products Tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-products-tab.php';

		// Include Campaign class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-campaign.php';
		// Include Membership class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-membership.php';
		// Include Participant class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-participant.php';

	}

	/**
	 * Set up plugin objects.
	 *
	 * @since 2.0
	 */
	public function setup_objects() {

		// Init helper object.
		$this->helper = new WPCV_Woo_Civi_Helper();

		// Init Network Settings object.
		$this->settings_network = new WPCV_Woo_Civi_Settings_Network();
		// Init Settings Tab object.
		$this->settings_tab = new WPCV_Woo_Civi_Settings_Tab();
		// Init States object.
		$this->states = new WPCV_Woo_Civi_States();

		// Init Contact object.
		$this->contact = new WPCV_Woo_Civi_Contact();

		// Init Contribution object.
		$this->contribution = new WPCV_Woo_Civi_Contribution();
		// Init Order object.
		$this->order = new WPCV_Woo_Civi_Order();
		// Init Source object.
		$this->source = new WPCV_Woo_Civi_Source();
		// Init Tax/VAT object.
		$this->tax = new WPCV_Woo_Civi_Tax();

		// Init Products object.
		$this->products = new WPCV_Woo_Civi_Products();
		// Init Products Tab object.
		$this->products_tab = new WPCV_Woo_Civi_Products_Tab();

		// Init Campaign object.
		$this->campaign = new WPCV_Woo_Civi_Campaign();
		// Init Membership object.
		$this->membership = new WPCV_Woo_Civi_Membership();
		// Init Participant object.
		$this->participant = new WPCV_Woo_Civi_Participant();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	private function register_hooks() {

		// Add settings link to plugin listing page.
		add_filter( 'plugin_action_links', [ $this, 'add_action_links' ], 10, 2 );

	}

	/**
	 * Plugin activation.
	 *
	 * @since 2.1
	 */
	public function activate() {

		$this->clear_civi_cache();

		/**
		 * Broadcast that this plugin has been activated.
		 *
		 * @since 2.0
		 */
		do_action( 'wpcv_woo_civi/activated' );

	}

	/**
	 * Bootstrap CiviCRM.
	 *
	 * @since 2.1
	 *
	 * @return bool True if CiviCRM was initialised, false otherwise.
	 */
	public function boot_civi() {

		// Init only when CiviCRM is fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}
		if ( ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Try and initialise CiviCRM.
		return civi_wp()->initialize();

	}

	/**
	 * Clear CiviCRM cache.
	 *
	 * @since 2.1
	 */
	public function clear_civi_cache() {

		// Bail if no CiviCRM.
		if ( ! $this->boot_civi() ) {
			return;
		}

		CRM_Core_Config::singleton()->cleanup( 1, false );
		CRM_Core_Config::clearDBCache();
		CRM_Utils_System::flushCache();

	}

	/**
	 * Add Settings link to plugin listing page.
	 *
	 * @since 2.0
	 *
	 * @param array $links The list of plugin links.
	 * @param string $file The plugin file.
	 * @return string $links The modified list of plugin links.
	 */
	public function add_action_links( $links, $file ) {

		if ( plugin_basename( WPCV_WOO_CIVI_FILE ) === $file ) {
			$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=woocommerce_civicrm' ) . '">' . __( 'Settings', 'wpcv-woo-civi-integration' ) . '</a>';
		}

		return $links;

	}

	/**
	 * Check if this plugin is network activated.
	 *
	 * @since 3.0
	 *
	 * @return bool $is_network_active True if network activated, false otherwise.
	 */
	public function is_network_activated() {

		// Only need to test once.
		static $is_network_active;

		// Have we done this already?
		if ( isset( $is_network_active ) ) {
			return $is_network_active;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {
			$is_network_active = false;
			return $is_network_active;
		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to this plugin.
		$this_plugin = plugin_basename( WPCV_WOO_CIVI_FILE );

		// Test if network active.
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		return $is_network_active;

	}

	/**
	 * Load translation files.
	 *
	 * Reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 2.0
	 */
	public function enable_translation() {

		// Load translations if present.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			// Unique name.
			'wpcv-woo-civi-integration',
			// Deprecated argument.
			'',
			// Relative path to translation files.
			dirname( plugin_basename( WPCV_WOO_CIVI_FILE ) ) . '/languages/'
		);

	}

	/**
	 * Check plugin dependencies.
	 *
	 * If any of these checks fail, this plugin will self-deactivate and exit.
	 * This check takes place on "plugins_loaded" which happens prior to the
	 * "woocommerce_init" action.
	 *
	 * @since 2.0
	 */
	public function check_dependencies() {

		// Bail if WooCommerce is not available.
		if ( ! function_exists( 'WC' ) ) {
			$this->display_woocommerce_required_notice();
		}

		// Bail if CiviCRM is not available.
		if ( ! function_exists( 'civi_wp' ) ) {
			$this->display_civicrm_required_notice();
		}

		// Bail if CiviCRM is not installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			$this->display_civicrm_initialised_notice();
		}

	}

	/**
	 * Display WooCommerce required notice.
	 *
	 * @since 2.0
	 */
	public function display_woocommerce_required_notice() {

		$heading = __( 'Activation failed', 'wpcv-woo-civi-integration' );

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$woo = '<strong>' . __( 'WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf(
			/* translators: %1$s: The plugin name, %2$s: WooCommerce */
			__( '%1$s requires %2$s to be installed and activated.', 'wpcv-woo-civi-integration' ),
			$plugin,
			$woo
		);
		$deactivated = sprintf(
			/* translators: %s: WooCommerce */
			__( 'This plugin has been deactivated! Please activate %s and try again.', 'wpcv-woo-civi-integration' ),
			$woo
		);
		$back = sprintf(
			/* translators: %1$s: The opening anchor tag, %2$s: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		deactivate_plugins( plugin_basename( WPCV_WOO_CIVI_FILE ) );

		wp_die( $message );

	}

	/**
	 * Display CiviCRM required notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_required_notice() {

		$heading = __( 'Activation failed', 'wpcv-woo-civi-integration' );

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$civicrm = '<strong>' . __( 'CiviCRM', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf(
			/* translators: %1$s: The plugin name, %2$s: WooCommerce */
			__( '%1$s requires %2$s to be installed and activated.', 'wpcv-woo-civi-integration' ),
			$plugin,
			$civicrm
		);
		$deactivated = sprintf(
			/* translators: %s: WooCommerce */
			__( 'This plugin has been deactivated! Please activate %s and try again.', 'wpcv-woo-civi-integration' ),
			$civicrm
		);
		$back = sprintf(
			/* translators: %1$s: The opening anchor tag, %2$s: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		deactivate_plugins( plugin_basename( WPCV_WOO_CIVI_FILE ) );

		wp_die( $message );

	}

	/**
	 * Display CiviCRM not initialised notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_initialised_notice() {

		$heading = __( 'Activation failed', 'wpcv-woo-civi-integration' );

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$civicrm = '<strong>' . __( 'CiviCRM', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf(
			/* translators: %1$s: The plugin name, %2$s: WooCommerce */
			__( '%1$s requires %2$s to be fully installed and configured.', 'wpcv-woo-civi-integration' ),
			$plugin,
			$civicrm
		);
		$deactivated = sprintf(
			/* translators: %s: WooCommerce */
			__( 'This plugin has been deactivated! Please configure %s and try again.', 'wpcv-woo-civi-integration' ),
			$civicrm
		);
		$back = sprintf(
			/* translators: %1$s: The opening anchor tag, %2$s: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		deactivate_plugins( plugin_basename( WPCV_WOO_CIVI_FILE ) );

		wp_die( $message );

	}

}

/**
 * Instantiate plugin.
 *
 * @since 2.1
 *
 * @return WPCV_Woo_Civi The plugin instance.
 */
function WPCV_WCI() {
	return WPCV_Woo_Civi::instance();
}

WPCV_WCI();

register_activation_hook( __FILE__, [ WPCV_WCI(), 'activate' ] );
