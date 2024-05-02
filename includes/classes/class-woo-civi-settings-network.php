<?php
/**
 * Network Settings class.
 *
 * Handles the Network Settings screen.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Network Settings class.
 *
 * @since 3.0
 */
class WPCV_Woo_Civi_Settings_Network {

	/**
	 * Network Settings key.
	 *
	 * @since 3.0
	 * @access public
	 * @var string $settings_key The Network Settings key.
	 */
	public $settings_key = 'woocommerce_civicrm_network_settings';

	/**
	 * Class constructor.
	 *
	 * @since 3.0
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

		// Bail if this plugin isn't network-activated.
		if ( WPCV_WCI()->is_network_activated() ) {
			return;
		}

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0
	 */
	public function register_hooks() {

		// Register network settings.
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Update network settings.
		add_action( 'network_admin_edit_woocommerce_civicrm_network_settings', [ $this, 'network_settings_save' ] );

		// Add Menu item when on Network Admin.
		add_action( 'network_admin_menu', [ $this, 'network_admin_menu' ] );

	}

	/**
	 * Add the Settings Page menu item.
	 *
	 * @since 2.4
	 * @since 3.0 Moved to this class.
	 */
	public function network_admin_menu() {

		// We must be network admin in Multisite.
		if ( ! is_super_admin() ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			__( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ),
			__( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ),
			'manage_network_options',
			'woocommerce-civicrm-settings',
			[ $this, 'network_settings_render' ]
		);

	}

	/**
	 * Network settings.
	 *
	 * @since 2.0
	 * @since 3.0 Moved to this class.
	 */
	public function network_settings_render() {

		// We must be network admin in Multisite.
		if ( ! is_super_admin() ) {
			return;
		}

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Integrate CiviCRM with WooCommerce Settings', 'wpcv-woo-civi-integration' ); ?></h2>
			<?php settings_errors(); ?>
			<form action="edit.php?action=woocommerce_civicrm_network_settings" method="post">
				<?php wp_nonce_field( 'woocommerce-civicrm-settings', 'woocommerce-civicrm-settings' ); ?>
				<?php settings_fields( 'woocommerce-civicrm-settings-network' ); ?>
				<?php do_settings_sections( 'woocommerce-civicrm-settings-network' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php

	}

	/**
	 * Registers the plugin settings.
	 *
	 * @since 2.0
	 * @since 3.0 Moved to this class.
	 */
	public function register_settings() {

		register_setting( $this->settings_key, $this->settings_key );

		add_settings_section(
			'woocommerce-civicrm-settings-network-general',
			__( 'General settings', 'wpcv-woo-civi-integration' ),
			[ $this, 'network_settings_section' ],
			'woocommerce-civicrm-settings-network'
		);

		add_settings_field(
			'woocommerce_civicrm_shop_blog_id',
			__( 'Main WooCommerce Site ID', 'wpcv-woo-civi-integration' ),
			[ $this, 'network_settings_field' ],
			'woocommerce-civicrm-settings-network',
			'woocommerce-civicrm-settings-network-general',
			[
				'name'        => 'wc_blog_id',
				'network'     => true,
				'description' => __( 'The ID of the Site where the WooCommerce Shop is located.', 'wpcv-woo-civi-integration' ),
				'options'     => WPCV_WCI()->helper->get_sites(),
			]
		);

	}

	/**
	 * Settings section callback.
	 *
	 * @since 2.0
	 * @since 3.0 Moved to this class.
	 *
	 * @param array $args Display arguments.
	 */
	public function network_settings_section( $args ) {
		// FIXME: Why is this empty?
	}

	/**
	 * Settings field select.
	 *
	 * @since 2.0
	 * @since 3.0 Moved to this class.
	 *
	 * @param array $args The field params.
	 */
	public function network_settings_field( $args ) {

		$options = get_site_option( $this->settings_key );

		?>
		<select name="<?php echo esc_attr( $this->settings_key ); ?>[<?php echo esc_attr( $args['name'] ); ?>]" id="<?php echo esc_attr( $args['name'] ); ?>" class="regular-select">
			<?php foreach ( (array) $args['options'] as $key => $option ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, ( isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '' ), true ); ?>>
					<?php echo esc_attr( $option ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( isset( $args['description'] ) && $args['description'] ) : ?>
			<div class="description"><?php echo esc_html( $args['description'] ); ?></div>
		<?php endif; ?>
		<?php

	}

	/**
	 * Trigger network settings.
	 *
	 * @since 2.0
	 * @since 3.0 Moved to this class.
	 */
	public function network_settings_save() {

		// Verify our nonce.
		$nonce = filter_input( INPUT_POST, 'woocommerce-civicrm-settings' );
		$nonce = sanitize_text_field( wp_unslash( $nonce ) );
		if ( ! wp_verify_nonce( $nonce, 'woocommerce-civicrm-settings' ) ) {
			wp_die( esc_html__( 'Cheating uh?', 'wpcv-woo-civi-integration' ) );
		}

		if ( ! empty( $_POST[ $this->settings_key ]['wc_blog_id'] ) ) {
			$settings = [
				'wc_blog_id' => (int) sanitize_text_field( wp_unslash( $_POST[ $this->settings_key ]['wc_blog_id'] ) ),
			];
			update_site_option( $this->settings_key, $settings );
			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'woocommerce-civicrm-settings',
						'confirm' => 'success',
					],
					( network_admin_url( 'settings.php' ) )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'woocommerce-civicrm-settings',
						// FIXME: Not sure this is correct.
						'confirm' => 'error',
					],
					( network_admin_url( 'settings.php' ) )
				)
			);
		}

		exit;

	}

}
