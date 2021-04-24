<?php
/**
 * Plugin Name: Integrate CiviCRM with WooCommerce
 * Plugin URI: https://github.com/WPCV/wpcv-woo-civi-integration
 * Description: Provides integration between CiviCRM with WooCommerce.
 * Author: Andrei Mondoc
 * Author URI: https://github.com/mecachisenros
 * Version: 3.0
 * Text Domain: wpcv-woo-civi-integration
 * Domain Path: /languages
 * Depends: CiviCRM
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM class.
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
	 * The Settings Tab management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $settings_tab The Settings Tab management object.
	 */
	public $settings_tab;

	/**
	 * The Orders Contact Tab management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $orders_tab The Orders Tab management object.
	 */
	public $orders_tab;

	/**
	 * The General management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $manager The plugin functionality management object.
	 */
	public $manager;

	/**
	 * The Sync management object.
	 *
	 * Encapsulates synchronisation between WooCommerce and CiviCRM.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $sync The Sync management object.
	 */
	public $sync;

	/**
	 * The Helper management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $helper The Helper management object.
	 */
	public $helper;

	/**
	 * CiviCRM States/Provinces management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $states_replacement The States replacement management object.
	 */
	public $states_replacement;

	/**
	 * WooCommerce Product management object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object products The Product management object.
	 */
	public $products;

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

			// Enable translation first.
			add_action( 'plugins_loaded', [ $this, 'enable_translation' ] );

			// Check dependencies once all plugins are loaded.
			add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );

			// Setup plugin when WooCommerce has been bootstrapped.
			add_action( 'woocommerce_init', [ $this, 'initialise' ] );

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

		// Bootstrap this plugin.
		$this->define_constants();
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this plugin is loaded.
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
	private function define_constants() {

		define( 'WPCV_WOO_CIVI_VERSION', '3.0' );
		define( 'WPCV_WOO_CIVI_FILE', __FILE__ );
		define( 'WPCV_WOO_CIVI_URL', plugin_dir_url( WPCV_WOO_CIVI_FILE ) );
		define( 'WPCV_WOO_CIVI_PATH', plugin_dir_path( WPCV_WOO_CIVI_FILE ) );

	}

	/**
	 * Include plugin files.
	 *
	 * @since 2.0
	 */
	private function include_files() {

		// Include Helper class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-helper.php';
		// Include WooCommerce settings tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-settings-tab.php';
		// Include CiviCRM orders tab class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-orders-contact-tab.php';
		// Include WooCommerce functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-manager.php';
		// Include Address Sync functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-sync.php';
		// Include States replacement functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-states.php';
		// Include Products functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-products.php';
		// Include Orders functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/class-woo-civi-orders.php';

	}

	/**
	 * Set up plugin objects.
	 *
	 * @since 2.0
	 */
	public function setup_objects() {

		// Init orders tab.
		$this->orders_tab = new WPCV_Woo_Civi_Orders_Contact_Tab();
		// Init helper instance.
		$this->helper = new WPCV_Woo_Civi_Helper();
		// Init settings page.
		$this->settings_tab = new WPCV_Woo_Civi_Settings_Tab();
		// Init manager.
		$this->manager = new WPCV_Woo_Civi_Manager();
		// Init states replacement.
		$this->states_replacement = new WPCV_Woo_Civi_States();
		// Init sync manager.
		$this->sync = new WPCV_Woo_Civi_Sync();
		// Init products.
		$this->products = new WPCV_Woo_Civi_Products();
		// Init orders.
		$this->products = new WPCV_Woo_Civi_Orders();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	private function register_hooks() {

		// Add settings link to plugin listing page.
		add_filter( 'plugin_action_links', [ $this, 'add_action_links' ], 10, 2 );

		if ( $this->is_network_activated() ) {
			add_action( 'network_admin_menu', [ $this, 'network_admin_menu' ] );
		}

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
	 * Add the Settings Page menu item.
	 *
	 * @since 2.4
	 */
	public function network_admin_menu() {

		add_submenu_page(
			'settings.php',
			__( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ),
			__( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ),
			'manage_network_options',
			'woocommerce-civicrm-settings',
			[ $this->settings_tab, 'network_settings' ]
		);

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

		// --<
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

		$heading = '<h1>' . __( 'Activation failed', 'wpcv-woo-civi-integration' ) . '</h1>';

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$woo = '<strong>' . __( 'WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf( __( '%1$s requires %2$s to be installed and activated.', 'wpcv-woo-civi-integration' ), $plugin, $woo );
		$deactivated = sprintf( __( 'This plugin has been deactivated! Please activate %s and try again.', 'wpcv-woo-civi-integration' ), $woo );
		$back = sprintf(
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<p>' . $requires . '</p>';
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

		$heading = '<h1>' . __( 'Activation failed', 'wpcv-woo-civi-integration' ) . '</h1>';

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$civicrm = '<strong>' . __( 'CiviCRM', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf( __( '%1$s requires %2$s to be installed and activated.', 'wpcv-woo-civi-integration' ), $plugin, $civicrm );
		$deactivated = sprintf( __( 'This plugin has been deactivated! Please activate %s and try again.', 'wpcv-woo-civi-integration' ), $civicrm );
		$back = sprintf(
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<p>' . $requires . '</p>';
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

		$heading = '<h1>' . __( 'Activation failed', 'wpcv-woo-civi-integration' ) . '</h1>';

		$plugin = '<strong>' . __( 'Integrate CiviCRM with WooCommerce', 'wpcv-woo-civi-integration' ) . '</strong>';
		$civicrm = '<strong>' . __( 'CiviCRM', 'wpcv-woo-civi-integration' ) . '</strong>';

		$requires = sprintf( __( '%1$s requires %2$s to be fully installed and configured.', 'wpcv-woo-civi-integration' ), $plugin, $civicrm );
		$deactivated = sprintf( __( 'This plugin has been deactivated! Please configure %s and try again.', 'wpcv-woo-civi-integration' ), $civicrm );
		$back = sprintf(
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">', '</a>'
		);

		$message = '<p>' . $requires . '</p>';
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
