<?php
/**
 * WPCV WooCommerce CiviCRM Sync Phone class.
 *
 * Handles syncing Phone Numbers between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Sync Phone class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Sync_Phone {

	/**
	 * Class constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		// Init when the sync loader class is fully loaded.
		add_action( 'wpcv_woo_civi/sync/loaded', [ $this, 'initialise' ] );

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
	 * @since 0.2
	 */
	public function register_hooks() {

		// Sync WooCommerce and CiviCRM Phone for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10, 4 );
		// Sync WooCommerce and CiviCRM Phone for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_phone' ], 10, 2 );

	}

	/**
	 * Sync a CiviCRM Phone from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Phone is edited.
	 *
	 * @since 2.0
	 * @param string $op The operation being performed.
	 * @param string $object_name The entity name.
	 * @param int $object_id The entity id.
	 * @param object $object_ref The entity object.
	 */
	public function sync_civi_contact_phone( $op, $object_name, $object_id, $object_ref ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Phone' !== $object_name ) {
			return;
		}

		// Bail if the Phone being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WPCV_WCI()->helper->mapped_location_types, true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( ! isset( $object_ref->contact_id ) ) {
			return;
		}

		$cms_user = WPCV_WCI()->helper->get_civicrm_ufmatch( $object_ref->contact_id, 'contact_id' );

		// Bail if we don't have a WordPress User.
		if ( ! $cms_user ) {
			return;
		}

		// Proceed.
		$phone_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->mapped_location_types );

		// Only Billing Phone, there's no Shipping Phone field.
		if ( 'billing' === $phone_type ) {
			update_user_meta( $cms_user['uf_id'], $phone_type . '_phone', $object_ref->phone );
		}

		/**
		 * Broadcast that a WooCommerce Phone has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param int $user_id The WordPress User ID.
		 * @param string $phone_type The WooCommerce Phone Type. Either 'billing' or 'shipping'.
		 */
		do_action( 'wpcv_woo_civi/wc_phone/updated', $cms_user['uf_id'], $phone_type );

	}

	/**
	 * Sync a WooCommerce Phone from a User to a CiviCRM Contact.
	 *
	 * Fires when an WooCommerce Phone is edited.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The WordPress User ID.
	 * @param string $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_wp_user_woocommerce_phone( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return false;
		}

		// Bail if Phone is not of type 'billing'.
		if ( 'billing' !== $load_address ) {
			return false;
		}

		$civi_contact = WPCV_WCI()->helper->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $civi_contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->mapped_location_types;
		$civi_phone_location_type = $mapped_location_types[ $load_address ];

		$customer = new WC_Customer( $user_id );

		$edited_phone = [
			'phone' => $customer->{'get_' . $load_address . '_phone'}(),
		];

		$params = [
			'contact_id' => $civi_contact['contact_id'],
			'location_type_id' => $civi_phone_location_type,
		];

		try {
			$civi_phone = civicrm_api3( 'Phone', 'getsingle', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		try {

			if ( isset( $civi_phone ) && ! $civi_phone['is_error'] ) {
				$new_params = array_merge( $civi_phone, $edited_phone );
			} else {
				$new_params = array_merge( $params, $edited_phone );
			}

			$create_phone = civicrm_api3( 'Phone', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		/**
		 * Broadcast that a CiviCRM Phone has been updated.
		 *
		 * @since 2.0
		 *
		 * @param int $contact_id The CiviCRM Contact ID.
		 * @param array $phone The CiviCRM Phone that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_phone/updated', $civi_contact['contact_id'], $create_phone );

		// Success.
		return true;

	}

}
