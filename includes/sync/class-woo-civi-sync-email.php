<?php
/**
 * WPCV WooCommerce CiviCRM Sync Email class.
 *
 * Handles syncing Email Addresses between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Sync Email class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Sync_Email {

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
	 * @since 2.0
	 */
	public function register_hooks() {

		// FIXME: remove reverse sync.

		// Sync WooCommerce and CiviCRM email for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_email' ], 10, 4 );
		// Sync WooCommerce and CiviCRM email for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_email' ], 10, 2 );

	}

	/**
	 * Sync a CiviCRM Email from a Contact to a WordPress User.
	 *
	 * Fires when a Civi Contact's Email is edited.
	 *
	 * @since 2.0
	 *
	 * @param string $op The operation being performed.
	 * @param string $object_name The entity name.
	 * @param int $object_id The entity id.
	 * @param object $object_ref The entity object.
	 */
	public function sync_civi_contact_email( $op, $object_name, $object_id, $object_ref ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Email' !== $object_name ) {
			return;
		}

		// Bail if the Email being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() ) ) {
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
		$email_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types() );

		// Only for billing Email, there's no shipping Email field.
		if ( 'billing' === $email_type ) {
			update_user_meta( $cms_user['uf_id'], $email_type . '_email', $object_ref->email );
		}

		/**
		 * Broadcast that a WooCommerce Email has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param int $user_id The WordPress User ID.
		 * @param string $email_type The WooCommerce Email Type. Either 'billing' or 'shipping'.
		 */
		do_action( 'wpcv_woo_civi/wc_email/updated', $cms_user['uf_id'], $email_type );

	}

	/**
	 * Sync a WooCommerce Email from a User to a CiviCRM Contact.
	 *
	 * Fires when WooCommerce Email is edited.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The WordPress User ID.
	 * @param string $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_wp_user_woocommerce_email( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) {
			return false;
		}

		// Bail if Email is not of type 'billing'.
		if ( 'billing' !== $load_address ) {
			return false;
		}

		$civi_contact = WPCV_WCI()->helper->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $civi_contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$civi_email_location_type = $mapped_location_types[ $load_address ];

		$customer = new WC_Customer( $user_id );

		$edited_email = [
			'email' => $customer->{'get_' . $load_address . '_email'}(),
		];

		try {

			$params = [
				'contact_id' => $civi_contact['contact_id'],
				'location_type_id' => $civi_email_location_type,
			];

			$civi_email = civicrm_api3( 'Email', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		try {

			if ( isset( $civi_email ) && ! $civi_email['is_error'] ) {
				$new_params = array_merge( $civi_email, $edited_email );
			} else {
				$new_params = array_merge( $params, $edited_email );
			}

			$create_email = civicrm_api3( 'Email', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		/**
		 * Broadcast that a CiviCRM Email has been updated.
		 *
		 * @since 2.0
		 *
		 * @param int $contact_id The CiviCRM Contact ID.
		 * @param array $email The CiviCRM Email that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_email/updated', $civi_contact['contact_id'], $create_email );

		// Success.
		return true;

	}

}
