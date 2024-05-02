<?php
/**
 * Urchin Tracking Module class.
 *
 * Handles integration of Urchin Tracking Module when CiviCampaign is enabled.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Urchin Tracking Module class.
 *
 * @since 2.0
 */
class WPCV_Woo_Civi_UTM {

	/**
	 * Class constructor.
	 *
	 * @since 3.0
	 */
	public function __construct() {

		// Init when the Campaign class is fully loaded.
		add_action( 'wpcv_woo_civi/campaign/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 3.0
	 */
	public function initialise() {

		// Bail if the CiviCampaign component is not active.
		if ( ! WPCV_WCI()->helper->is_component_enabled( 'CiviCampaign' ) ) {
			return;
		}

		$this->register_hooks();
		$this->utm_check();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		// Save UTM Campaign cookie content to the Order.
		add_action( 'wpcv_woo_civi/campaign/get_for_order', [ $this, 'utm_to_order' ], 10, 2 );

		// Flush cookies when a Contribution has been created from an Order.
		add_action( 'wpcv_woo_civi/contribution/create_from_order', [ $this, 'utm_cookies_delete' ] );

		// Filter the Contribution Source.
		add_filter( 'wpcv_woo_civi/order/source/generate', [ $this, 'utm_filter_source' ] );

	}

	/**
	 * Check if UTM parameters are passed in URL (front-end only).
	 *
	 * @since 2.2
	 */
	private function utm_check() {

		if ( is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['utm_campaign'] ) || isset( $_GET['utm_source'] ) || isset( $_GET['utm_medium'] ) ) {
			$this->utm_cookies_save();
		}

	}

	/**
	 * Save UTM parameters to cookies.
	 *
	 * @since 2.2
	 */
	private function utm_cookies_save() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		/**
		 * Filter the cookie expiry time.
		 *
		 * @since 2.2
		 *
		 * @param integer The duration of the cookie. Default 0.
		 */
		$expire = apply_filters( 'wpcv_woo_civi/utm_cookie/expire', 0 );
		$secure = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );

		$campaign_name = filter_input( INPUT_GET, 'utm_campaign' );
		$campaign_name = sanitize_text_field( wp_unslash( $campaign_name ) );
		if ( ! empty( $campaign_name ) ) {
			$campaign_cookie = 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH;
			$campaign        = WPCV_WCI()->campaign->get_campaign_by_name( esc_attr( $campaign_name ) );
			if ( ! empty( $campaign['id'] ) && is_numeric( $campaign['id'] ) ) {
				setcookie( $campaign_cookie, $campaign['id'], $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
			} else {
				// Remove cookie if Campaign is invalid.
				setcookie( $campaign_cookie, ' ', time() - YEAR_IN_SECONDS );
			}
		}

		$source = filter_input( INPUT_GET, 'utm_source' );
		if ( false !== $source ) {
			$source = sanitize_text_field( wp_unslash( $source ) );
			setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, esc_attr( $source ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}

		$medium = filter_input( INPUT_GET, 'utm_medium' );
		if ( false !== $medium ) {
			$medium = sanitize_text_field( wp_unslash( $medium ) );
			setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, esc_attr( $medium ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}

		// Success.
		return true;

	}

	/**
	 * Delete UTM cookies.
	 *
	 * @since 2.2
	 */
	public function utm_cookies_delete() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Remove any existing cookies.
		$past = time() - YEAR_IN_SECONDS;
		setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );

	}

	/**
	 * Saves UTM Campaign cookie content to the Order post meta.
	 *
	 * @since 2.2
	 * @since 3.0 Used only on new Orders.
	 *
	 * @param integer $campaign_id The calculated Campaign ID.
	 * @param object  $order The WooCommerce Order object.
	 * @return integer $campaign_id The possibly overridden Campaign ID.
	 */
	public function utm_to_order( $campaign_id, $order ) {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return $campaign_id;
		}

		$cookie          = wp_unslash( $_COOKIE );
		$campaign_cookie = 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH;

		// Override with the UTM Campaign ID if present.
		if ( ! empty( $cookie[ $campaign_cookie ] ) ) {
			$campaign_id = (int) esc_attr( $cookie[ $campaign_cookie ] );
		}

		return $campaign_id;

	}

	/**
	 * Filters the Contribution Source.
	 *
	 * @since 3.0
	 *
	 * @param string $source The existing Contribution Source string.
	 * @return string $source The modified Contribution Source string.
	 */
	public function utm_filter_source( $source ) {

		$cookie        = wp_unslash( $_COOKIE );
		$source_cookie = 'woocommerce_civicrm_utm_source_' . COOKIEHASH;
		$medium_cookie = 'woocommerce_civicrm_utm_medium_' . COOKIEHASH;

		// Bail early if there's no data.
		if ( empty( $cookie[ $source_cookie ] ) && empty( $cookie[ $medium_cookie ] ) ) {
			return $source;
		}

		// Build new Source string.
		$tmp = [];
		if ( ! empty( $cookie[ $source_cookie ] ) ) {
			$tmp[] = esc_attr( $cookie[ $source_cookie ] );
		}
		if ( ! empty( $cookie[ $medium_cookie ] ) ) {
			$tmp[] = esc_attr( $cookie[ $medium_cookie ] );
		}
		$source = implode( ' / ', $tmp );

		return $source;

	}

}
