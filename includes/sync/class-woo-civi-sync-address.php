<?php
/**
 * WPCV WooCommerce CiviCRM Sync Address class.
 *
 * Handles syncing addresses between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WPCV WooCommerce CiviCRM Sync Address class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Sync_Address {

	/**
	 * Initialise this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {
		// Sync WooCommerce and CiviCRM address for contact/user.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_address' ], 10, 4 );
		// Sync WooCommerce and CiviCRM address for user/contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_address' ], 10, 2 );
	}

	/**
	 * Sync CiviCRM Address from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Address is edited.
	 *
	 * @since 2.0
	 *
	 * @param string $op The operation being performed.
	 * @param string $object_name The entity name.
	 * @param int $object_id The entity ID.
	 * @param object $object_ref The entity object.
	 */
	public function sync_civi_contact_address( $op, $object_name, $object_id, $object_ref ) {

		// Bail if sync is not enabled.
		if ( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Address' !== $object_name ) {
			return;
		}

		// Bail if the Address being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WCI()->helper->mapped_location_types, true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( ! isset( $object_ref->contact_id ) ) {
			return;
		}

		$cms_user = WCI()->helper->get_civicrm_ufmatch( $object_ref->contact_id, 'contact_id' );

		// Bail if we don't have a WordPress User ID.
		if ( ! $cms_user ) {
			return;
		}

		// Proceed.
		$address_type = array_search( $object_ref->location_type_id, WCI()->helper->mapped_location_types, true );

		foreach ( WCI()->helper->get_mapped_address( $address_type ) as $wc_field => $civi_field ) {
			if ( ! empty( $object_ref->{$civi_field} ) && ! is_null( $object_ref->{$civi_field} ) && 'null' !== $object_ref->{$civi_field} ) {

				switch ( $civi_field ) {
					case 'country_id':
						update_user_meta( $cms_user['uf_id'], $wc_field, WCI()->helper->get_civi_country_iso_code( $object_ref->{$civi_field} ) );
						continue 2;
					case 'state_province_id':
						update_user_meta( $cms_user['uf_id'], $wc_field, WCI()->helper->get_civi_state_province_name( $object_ref->{$civi_field} ) );
						continue 2;
					default:
						update_user_meta( $cms_user['uf_id'], $wc_field, $object_ref->{$civi_field} );
						continue 2;
				}
			}
		}

		/**
		 * Broadcast that a WooCommerce Address has been updated for a User.
		 *
		 * @since 2.0
		 *
		 * @param int $user_id The WordPress User ID.
		 * @param string $address_type The WooCommerce Address Type. Either 'billing' or 'shipping'.
		 */
		do_action( 'woocommerce_civicrm_wc_address_updated', $cms_user['uf_id'], $address_type );

	}

	/**
	 * Sync WooCommerce Address from a User to a CiviCRM Contact.
	 *
	 * Fires when WooCommerce Address is edited.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The WordPress User ID.
	 * @param string $load_address The Address Type. Either 'shipping' or 'billing'.
	 */
	public function sync_wp_user_woocommerce_address( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) {
			return;
		}

		$customer = new WC_Customer( $user_id );

		$civi_contact = WCI()->helper->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $civi_contact ) {
			return;
		}

		$mapped_location_types = WCI()->helper->mapped_location_types;
		$civi_address_location_type = $mapped_location_types[ $load_address ];
		$edited_address = [];
		foreach ( WCI()->helper->get_mapped_address( $load_address ) as $wc_field => $civi_field ) {
			switch ( $civi_field ) {
				case 'country_id':
					$edited_address[ $civi_field ] = WCI()->helper->get_civi_country_id( $customer->{'get_' . $wc_field}() );
					continue 2;
				case 'state_province_id':
					$edited_address[ $civi_field ] = WCI()->helper->get_civi_state_province_id( $customer->{'get_' . $wc_field}(), $edited_address['country_id'] );
					continue 2;
				default:
					$edited_address[ $civi_field ] = $customer->{'get_' . $wc_field}();
					continue 2;
			}
		}

		$params = [
			'contact_id' => $civi_contact['contact_id'],
			'location_type_id' => $civi_address_location_type,
		];

		try {
			$civi_address = civicrm_api3( 'Address', 'getsingle', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		try {
			if ( isset( $civi_address ) && ! $civi_address['is_error'] ) {
				$new_params = array_merge( $civi_address, $edited_address );
			} else {
				$new_params = array_merge( $params, $edited_address );
			}
			$create_address = civicrm_api3( 'Address', 'create', $new_params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		/**
		 * Broadcast that a CiviCRM Address has been updated.
		 *
		 * @since 2.0
		 *
		 * @param int $contact_id The CiviCRM Contact ID.
		 * @param array $address The CiviCRM Address that has been edited.
		 */
		do_action( 'woocommerce_civicrm_civi_address_updated', $civi_contact['contact_id'], $create_address );
	}

}
