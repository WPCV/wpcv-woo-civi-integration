<?php
/**
 * Contact Address class.
 *
 * Handles syncing Addresses between WooCommerce and CiviCRM.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Contact Address class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_Contact_Address {

	/**
	 * The Address Location Types.
	 *
	 * Array of key/value pairs holding the Address Location Types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $location_types The Address Location Types.
	 */
	public $location_types;

	/**
	 * Class constructor.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		// Init when the Contact class is fully loaded.
		add_action( 'wpcv_woo_civi/contact/loaded', [ $this, 'initialise' ] );

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

		// Sync WooCommerce and CiviCRM Address for Contact/User.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_address' ], 10, 4 );

		// Sync WooCommerce and CiviCRM Address for User/Contact.
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_address' ], 10, 2 );

	}

	/**
	 * Get the Address Field mappings between WooCommerce and CiviCRM.
	 *
	 * @since 2.0
	 *
	 * @param string $address_type The WooCommerce Address Type. Either 'billing' or 'shipping'.
	 * @return array $mapped_address The Address Field mappings.
	 */
	public function get_mapped_address( $address_type ) {

		$mapped_address = [
			$address_type . '_address_1' => 'street_address',
			$address_type . '_address_2' => 'supplemental_address_1',
			$address_type . '_city' => 'city',
			$address_type . '_postcode' => 'postal_code',
			$address_type . '_country' => 'country_id',
			$address_type . '_state' => 'state_province_id',
			$address_type . '_company' => 'name',
		];

		/**
		 * Filter the Address Field mappings.
		 *
		 * @since 2.0
		 *
		 * @param array $mapped_address The default Address Field mappings.
		 */
		return apply_filters( 'wpcv_woo_civi/address_fields/mappings', $mapped_address );

	}

	/**
	 * Get CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 *
	 * @return array $location_types The array of CiviCRM Address Location Types.
	 */
	public function get_address_location_types() {

		// Return early if already calculated.
		if ( isset( $this->location_types ) ) {
			return $this->location_types;
		}

		$this->location_types = [];

		// Bail if we can't initialise CiviCRM.
		if ( ! WPCV_WCI()->boot_civi() ) {
			return $this->location_types;
		}

		$params = [
			'field' => 'location_type_id',
			'options' => [
				'limit' => 0,
			],
		];

		$result = civicrm_api3( 'Address', 'getoptions', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );

			return $this->location_types;

		}

		// Store values in property.
		if ( ! empty( $result['values'] ) ) {
			$this->location_types = $result['values'];
		}

		return $this->location_types;

	}

	/**
	 * Sync CiviCRM Address from a CiviCRM Contact to a WordPress User.
	 *
	 * Fires when a CiviCRM Contact's Address is edited.
	 *
	 * TODO: This should probably also remove the "civicrm_post" callback because
	 * it is possible for there to be listeners on the "updated_{$meta_type}_meta"
	 * action in WordPress.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/updated_meta_type_meta/
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
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Address' !== $object_name ) {
			return;
		}

		// Bail if the Address being edited is not one of the mapped ones.
		if ( ! in_array( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types(), true ) ) {
			return;
		}

		// Bail if we don't have a Contact ID.
		if ( ! isset( $object_ref->contact_id ) ) {
			return;
		}

		$cms_user = WPCV_WCI()->contact->get_civicrm_ufmatch( $object_ref->contact_id, 'contact_id' );

		// Bail if we don't have a WordPress User.
		if ( ! $cms_user ) {
			return;
		}

		// Proceed.
		$address_type = array_search( $object_ref->location_type_id, WPCV_WCI()->helper->get_mapped_location_types(), true );

		foreach ( $this->get_mapped_address( $address_type ) as $wc_field => $civi_field ) {
			if ( ! empty( $object_ref->{$civi_field} ) && ! is_null( $object_ref->{$civi_field} ) && 'null' !== $object_ref->{$civi_field} ) {

				switch ( $civi_field ) {
					case 'country_id':
						update_user_meta( $cms_user['uf_id'], $wc_field, WPCV_WCI()->states->get_civicrm_country_iso_code( $object_ref->{$civi_field} ) );
						continue 2;
					case 'state_province_id':
						update_user_meta( $cms_user['uf_id'], $wc_field, WPCV_WCI()->states->get_civicrm_state_province_name( $object_ref->{$civi_field} ) );
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
		do_action( 'wpcv_woo_civi/wc_address/updated', $cms_user['uf_id'], $address_type );

	}

	/**
	 * Sync WooCommerce Address from a User to a CiviCRM Contact.
	 *
	 * Fires when a WooCommerce Address is edited.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The WordPress User ID.
	 * @param string $load_address The Address Type. Either 'shipping' or 'billing'.
	 * @return bool True on success, false on failure.
	 */
	public function sync_wp_user_woocommerce_address( $user_id, $load_address ) {

		// Bail if sync is not enabled.
		if ( ! WPCV_WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) {
			return false;
		}

		$customer = new WC_Customer( $user_id );

		$civi_contact = WPCV_WCI()->contact->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// Bail if we don't have a CiviCRM Contact.
		if ( ! $civi_contact ) {
			return false;
		}

		$mapped_location_types = WPCV_WCI()->helper->get_mapped_location_types();
		$civi_address_location_type = $mapped_location_types[ $load_address ];
		$edited_address = [];

		foreach ( $this->get_mapped_address( $load_address ) as $wc_field => $civi_field ) {
			switch ( $civi_field ) {
				case 'country_id':
					$edited_address[ $civi_field ] = WPCV_WCI()->states->get_civicrm_country_id( $customer->{'get_' . $wc_field}() );
					continue 2;
				case 'state_province_id':
					$edited_address[ $civi_field ] = WPCV_WCI()->states->get_civicrm_state_province_id( $customer->{'get_' . $wc_field}(), $edited_address['country_id'] );
					continue 2;
				default:
					$edited_address[ $civi_field ] = $customer->{'get_' . $wc_field}();
					continue 2;
			}
		}

		try {

			$params = [
				'contact_id' => $civi_contact['contact_id'],
				'location_type_id' => $civi_address_location_type,
			];

			$civi_address = civicrm_api3( 'Address', 'getsingle', $params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to fetch Address', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			return false;

		}

		// Prevent reverse sync.
		remove_action( 'civicrm_post', [ $this, 'sync_civi_contact_address' ], 10 );

		try {

			if ( isset( $civi_address ) && empty( $civi_address['is_error'] ) ) {
				$new_params = array_merge( $civi_address, $edited_address );
			} else {
				$new_params = array_merge( $params, $edited_address );
			}

			$create_address = civicrm_api3( 'Address', 'create', $new_params );

		} catch ( CiviCRM_API3_Exception $e ) {

			// Write to CiviCRM log.
			CRM_Core_Error::debug_log_message( __( 'Unable to create/update Address', 'wpcv-woo-civi-integration' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );

			// Write details to PHP log.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'new_params' => $new_params,
				'backtrace' => $trace,
			], true ) );

			add_action( 'civicrm_post', [ $this, 'sync_civi_contact_address' ], 10, 4 );
			return false;

		}

		// Rehook callback.
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_address' ], 10, 4 );

		/**
		 * Broadcast that a CiviCRM Address has been updated.
		 *
		 * @since 2.0
		 *
		 * @param int $contact_id The CiviCRM Contact ID.
		 * @param array $address The CiviCRM Address that has been edited.
		 */
		do_action( 'wpcv_woo_civi/civi_address/updated', $civi_contact['contact_id'], $create_address );

		// Success.
		return true;

	}

}
