<?php
/**
 * WPCV WooCommerce CiviCRM Sync class.
 *
 * Loads the classes which handle syncing data between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Sync class.
 *
 * @since 2.1
 */
class WPCV_Woo_Civi_Sync {

	/**
	 * The Address sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $address The Address sync object.
	 */
	public $address;

	/**
	 * The Email sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $email The Email sync object.
	 */
	public $email;

	/**
	 * The Phone sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var object $phone The Phone sync object.
	 */
	public $phone;

	/**
	 * Class constructor.
	 *
	 * @since 2.1
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

		$this->include_files();
		$this->setup_objects();

		/**
		 * Broadcast that this class is loaded.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcv_woo_civi/sync/loaded' );

	}

	/**
	 * Include sync files.
	 *
	 * @since 2.1
	 */
	public function include_files() {

		// Include Address Sync functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/sync/class-woo-civi-sync-address.php';
		// Include Phone Sync functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/sync/class-woo-civi-sync-phone.php';
		// Include Email Sync functionality class.
		include WPCV_WOO_CIVI_PATH . 'includes/sync/class-woo-civi-sync-email.php';

	}

	/**
	 * Setup sync objects.
	 *
	 * @since 2.1
	 */
	public function setup_objects() {

		// Init address sync.
		$this->address = new WPCV_Woo_Civi_Sync_Address();
		// Init phone sync.
		$this->phone = new WPCV_Woo_Civi_Sync_Phone();
		// Init email sync.
		$this->email = new WPCV_Woo_Civi_Sync_Email();

	}

}
