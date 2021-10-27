/**
 * Javascript for the WooCommerce Add & Edit Product screens.
 *
 * @package WPCV_Woo_Civi
 * @since 3.0
 */

/**
 * Pass the jQuery shortcut in.
 *
 * @since 3.0
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings class.
	 *
	 * @since 3.0
	 */
	function WPCV_WCI_Settings() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * @since 3.0
		 */
		this.init = function() {
			me.init_localisation();
			me.init_settings();
		};

		// Init localisation array.
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 3.0
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof WPCV_WCI_Global_Panel_Vars ) {
				me.localisation = WPCV_WCI_Global_Panel_Vars.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 3.0
		 *
		 * @param {String} identifier The identifier for the desired localisation string.
		 * @return {String} The localised string.
		 */
		this.get_localisation = function( identifier ) {
			return me.localisation[identifier];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 3.0
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof WPCV_WCI_Global_Panel_Vars ) {
				me.settings = WPCV_WCI_Global_Panel_Vars.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 3.0
		 *
		 * @param {String} The identifier for the desired setting.
		 * @return The value of the setting.
		 */
		this.get_setting = function( identifier ) {
			return me.settings[identifier];
		};

	}

	/**
	 * Create Action class.
	 *
	 * @since 3.0
	 */
	function WPCV_WCI_Action() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise sections.
		 *
		 * @since 3.0
		 */
		this.init = function() {
		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * @since 3.0
		 */
		this.dom_ready = function() {
			me.setup();
			me.listeners();
		};

		/**
		 * Do initial setup.
		 *
		 * @since 3.0
		 */
		this.setup = function() {
			me.entity_type_select = $('#_woocommerce_civicrm_entity_type');
			me.show_hide();
		};

		/**
		 * Initialise listeners.
		 *
		 * @since 3.0
		 */
		this.listeners = function() {

			/**
			 * Add an onchange event listener to the "Entity Type" section select.
			 *
			 * @param {Object} event The event object.
			 */
			me.entity_type_select.on( 'change', function( event ) {
				me.show_hide();
			});

		};

		/**
		 * Show/hide sections.
		 *
		 * @since 3.0
		 */
		this.show_hide = function() {

			// Hide all first.
			$('#woocommerce_civicrm .civicrm_financial_type').hide();
			var keys = WPCV_WCI_Global_Panel_Settings.get_setting( 'entity_keys' );
			for ( item in keys ) {
				if ( keys[item] !== '' ) {
					$('#woocommerce_civicrm .' + keys[item] ).hide();
				}
			}

			// Show section based on the value.
			if ( me.entity_type_select.val() !== 'civicrm_exclude' ) {
				var selector = '.' + me.entity_type_select.val();
				$('#woocommerce_civicrm .civicrm_financial_type').show();
				$('#woocommerce_civicrm ' + selector).show();
			}

		};

	}

	// Init Settings and Action classes.
	var WPCV_WCI_Global_Panel_Settings = new WPCV_WCI_Settings();
	var WPCV_WCI_Global_Panel_Action = new WPCV_WCI_Action();
	WPCV_WCI_Global_Panel_Settings.init();
	WPCV_WCI_Global_Panel_Action.init();

	/**
	 * Trigger dom_ready methods where necessary.
	 *
	 * @since 3.0
	 *
	 * @param {Object} $ The jQuery object.
	 */
	$(document).ready(function($) {
		WPCV_WCI_Global_Panel_Action.dom_ready();
	});

} )( jQuery );
