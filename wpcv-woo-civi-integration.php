<?php
/**
 * Plugin Name: Integrate CiviCRM with WooCommerce
 * Plugin URI: https://github.com/WPCV/wpcv-woo-civi-integration
 * GitHub Plugin URI: https://github.com/WPCV/wpcv-woo-civi-integration
 * Description: Provides integration between CiviCRM and WooCommerce.
 * Author: WPCV
 * Author URI: https://github.com/WPCV
 * Version: 3.1.1a
 * Requires at least: 5.7
 * Requires PHP: 7.1
 * Text Domain: wpcv-woo-civi-integration
 * Domain Path: /languages
 * Depends: CiviCRM
 *
 * @package WPCV_Woo_Civi
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
	 * The Migrate object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $migrate The Migrate object.
	 */
	public $migrate;

	/**
	 * The Admin object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $admin The Admin object.
	 */
	public $admin;

	/**
	 * The Helper object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $helper The Helper object.
	 */
	public $helper;

	/**
	 * The Settings object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $settings The Settings object.
	 */
	public $settings;

	/**
	 * The Network Settings object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $settings_network The Network Settings object.
	 */
	public $settings_network;

	/**
	 * CiviCRM States/Provinces object.
	 *
	 * @since 2.0
	 * @access public
	 * @var object $settings_states The States/Provinces object.
	 */
	public $settings_states;

	/**
	 * WooCommerce Product Settings object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object $settings_products The WooCommerce Product Settings object.
	 */
	public $settings_products;

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
	 * Products object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $products The Products object.
	 */
	public $products;

	/**
	 * WooCommerce Custom Products object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object $products_custom The WooCommerce Custom Products object.
	 */
	public $products_custom;

	/**
	 * WooCommerce Variable Products object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object $products_variable The WooCommerce Variable Products object.
	 */
	public $products_variable;

	/**
	 * Campaign management object.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $campaign The Campaign management object.
	 */
	public $campaign;

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
	 * Dependency check flag.
	 *
	 * @since 3.0
	 * @access public
	 * @var object $okay_to_load True if dependency check succeeds, false otherwise.
	 */
	public $okay_to_load = false;

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

			// Register CiviCRM configuration.
			self::register_config_hooks();

			// Enable translation first.
			add_action( 'plugins_loaded', [ self::$instance, 'enable_translation' ] );

			// Declare status of compatibility with WooCommerce HPOS.
			add_action( 'before_woocommerce_init', [ self::$instance, 'declare_woocommerce_hpos_status' ] );

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

		// Always include Helper class and init.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-helper.php';
		$this->helper = new WPCV_Woo_Civi_Helper();

		// Bail if dependency check fails.
		if ( ! $this->check_dependencies_on_load() ) {
			return;
		}

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

		define( 'WPCV_WOO_CIVI_VERSION', '3.1.1a' );
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

		// Include Admin class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-admin.php';

		// Include Settings classes.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings-network.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings-states.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-settings-products.php';

		// Include Contact class.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contact.php';

		// Include Financial classes.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-contribution.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-order.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-source.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-tax.php';

		// Include Product classes.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-products.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-products-variable.php';

		// Maybe include Custom Products class.
		if ( defined( 'WPCV_WOO_CIVI_PRODUCTS_CUSTOM' ) && WPCV_WOO_CIVI_PRODUCTS_CUSTOM ) {
			include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-products-custom.php';
		}

		// Include CiviCRM Component classes.
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-campaign.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-membership.php';
		include WPCV_WOO_CIVI_PATH . 'includes/classes/class-woo-civi-participant.php';

	}

	/**
	 * Set up plugin objects.
	 *
	 * @since 2.0
	 */
	private function setup_objects() {

		// Init Admin object.
		$this->admin = new WPCV_Woo_Civi_Admin();

		// Init Settings objects.
		$this->settings = new WPCV_Woo_Civi_Settings();
		$this->settings_network = new WPCV_Woo_Civi_Settings_Network();
		$this->settings_states = new WPCV_Woo_Civi_Settings_States();
		$this->settings_products = new WPCV_Woo_Civi_Settings_Products();

		// Init Contact object.
		$this->contact = new WPCV_Woo_Civi_Contact();

		// Init Financial objects.
		$this->contribution = new WPCV_Woo_Civi_Contribution();
		$this->order = new WPCV_Woo_Civi_Order();
		$this->source = new WPCV_Woo_Civi_Source();
		$this->tax = new WPCV_Woo_Civi_Tax();

		// Init Product objects.
		$this->products = new WPCV_Woo_Civi_Products();
		$this->products_variable = new WPCV_Woo_Civi_Products_Variable();

		// Maybe init Custom Products object.
		if ( defined( 'WPCV_WOO_CIVI_PRODUCTS_CUSTOM' ) && WPCV_WOO_CIVI_PRODUCTS_CUSTOM ) {
			$this->products_custom = new WPCV_Woo_Civi_Products_Custom();
		}

		// Init CiviCRM Component objects.
		$this->campaign = new WPCV_Woo_Civi_Campaign();
		$this->membership = new WPCV_Woo_Civi_Membership();
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

		$this->check_dependencies();
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
	 * Register CiviCRM hooks early.
	 *
	 * These callbacks need to be registered as early as possible because we do
	 * not know how early other plugins may try and bootstrap CiviCRM.
	 *
	 * @since 3.0
	 */
	public static function register_config_hooks() {

		// Register custom PHP directory.
		add_action( 'civicrm_config', [ self::$instance, 'register_custom_php_directory' ], 10 );

		// Register custom template directory.
		add_action( 'civicrm_config', [ self::$instance, 'register_custom_template_directory' ], 10 );

		// Register menu callback.
		add_filter( 'civicrm_xmlMenu', [ self::$instance, 'register_menu_callback' ], 10 );

	}

	/**
	 * Register PHP directory.
	 *
	 * @since 2.0
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_custom_php_directory( &$config ) {

		// Define our custom path.
		$custom_path = WPCV_WOO_CIVI_PATH . 'assets/civicrm/custom_php';

		// Add to include path.
		$include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		// phpcs:ignore
		set_include_path( $include_path );

	}

	/**
	 * Register template directory.
	 *
	 * @since 2.0
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_custom_template_directory( &$config ) {

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Add our custom template directory.
		$custom_path = WPCV_WOO_CIVI_PATH . 'assets/civicrm/custom_tpl';
		$template->addTemplateDir( $custom_path );

		// Add to include path.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		// phpcs:ignore
		set_include_path( $template_include_path );

	}

	/**
	 * Register XML file.
	 *
	 * @since 2.0
	 *
	 * @param array $files The array for files used to build the menu.
	 */
	public function register_menu_callback( &$files ) {
		$files[] = WPCV_WOO_CIVI_PATH . 'assets/civicrm/xml/menu.xml';
	}

	/**
	 * Add Settings link to plugin listing page.
	 *
	 * @since 2.0
	 *
	 * @param array  $links The list of plugin links.
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
	 * Check plugin dependencies when already installed.
	 *
	 * If any of these checks fail, this plugin will skip its load procedures.
	 *
	 * Note that no WooCommerce checks are made because this check takes place
	 * in the callback to the "woocommerce_init" action and will not be called
	 * if WooCommerce is not installed.
	 *
	 * @since 3.0
	 */
	public function check_dependencies_on_load() {

		// Bail if CiviCRM is not available.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Bail if CiviCRM is not installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}

		// Bail early if the CiviContribute component is not active.
		$contribute_active = $this->helper->is_component_enabled( 'CiviContribute' );
		if ( ! $contribute_active ) {
			return false;
		}

		// We're good to go.
		$this->okay_to_load = true;
		return true;

	}

	/**
	 * Declare status of compatibility with WooCommerce HPOS.
	 *
	 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
	 *
	 * @since 3.0
	 */
	public function declare_woocommerce_hpos_status() {

		// Bail if we can't declare compatibility.
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		// When this plugin is compatible, switch to final param to "true".
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, false );

	}

	/**
	 * Check plugin dependencies on plugin activation.
	 *
	 * If any of these checks fail, this plugin will self-deactivate and exit.
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
			/* translators: 1: The plugin name, 2: WooCommerce */
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
			/* translators: 1: The opening anchor tag, 2: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">',
			'</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
			/* translators: 1: The plugin name, 2: CiviCRM */
			__( '%1$s requires %2$s to be installed and activated.', 'wpcv-woo-civi-integration' ),
			$plugin,
			$civicrm
		);
		$deactivated = sprintf(
			/* translators: %s: CiviCRM */
			__( 'This plugin has been deactivated! Please activate %s and try again.', 'wpcv-woo-civi-integration' ),
			$civicrm
		);
		$back = sprintf(
			/* translators: 1: The opening anchor tag, 2: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">',
			'</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
			/* translators: 1: The plugin name, 2: CiviCRM */
			__( '%1$s requires %2$s to be fully installed and configured.', 'wpcv-woo-civi-integration' ),
			$plugin,
			$civicrm
		);
		$deactivated = sprintf(
			/* translators: %s: CiviCRM */
			__( 'This plugin has been deactivated! Please configure %s and try again.', 'wpcv-woo-civi-integration' ),
			$civicrm
		);
		$back = sprintf(
			/* translators: 1: The opening anchor tag, 2: The closing anchor tag */
			__( 'Back to the WordPress %1$splugins page%2$s.', 'wpcv-woo-civi-integration' ),
			'<a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">',
			'</a>'
		);

		$message = '<h1>' . $heading . '</h1>';
		$message .= '<p>' . $requires . '</p>';
		$message .= '<p>' . $deactivated . '</p>';
		$message .= '<p>' . $back . '</p>';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
