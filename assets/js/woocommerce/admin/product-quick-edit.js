/**
 * Javascript for the WooCommerce Product Quick Edit UI.
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
			if ( 'undefined' !== typeof WPCV_WCI_Quick_Edit_Vars ) {
				me.localisation = WPCV_WCI_Quick_Edit_Vars.localisation;
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
			if ( 'undefined' !== typeof WPCV_WCI_Quick_Edit_Vars ) {
				me.settings = WPCV_WCI_Quick_Edit_Vars.settings;
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
	function WPCV_WCI_Product_Quick_Edit() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * @since 3.0
		 */
		this.dom_ready = function() {

			// Store passed-in data.
			me.classes_all = WPCV_WCI_Quick_Edit_Settings.get_setting( 'classes_all' ).join(', ');
			me.class_br = WPCV_WCI_Quick_Edit_Settings.get_setting( 'class_br' );
			me.class_title = WPCV_WCI_Quick_Edit_Settings.get_setting( 'class_title' );
			me.class_entity = WPCV_WCI_Quick_Edit_Settings.get_setting( 'class_entity' );
			me.class_financial = WPCV_WCI_Quick_Edit_Settings.get_setting( 'class_financial' );
			me.class_contribution = WPCV_WCI_Quick_Edit_Settings.get_setting( 'class_contribution' );
			me.classes_membership = WPCV_WCI_Quick_Edit_Settings.get_setting( 'classes_membership' ).join(', ');
			me.classes_participant = WPCV_WCI_Quick_Edit_Settings.get_setting( 'classes_participant' ).join(', ');

			me.listeners();

		};

		/**
		 * Initialise listeners.
		 *
		 * @since 3.0
		 */
		this.listeners = function() {

			/**
			 * Adds a click event listener to the "The List".
			 *
			 * @since 3.0
			 *
			 * @param {Object} event The event object.
			 */
			$('#the-list').on( 'click', '.editinline', function( event ) {

				var wc_inline_data, wpcv_inline_data,
					product_type, entity_type, financial_type_id, pfv_id;

				// Get the Post ID.
				me.post_id = $(this).closest( 'tr' ).attr( 'id' ).replace( 'post-', '' );

				// Hide all Form Elements by default.
				$(me.classes_all).hide();

				// Get the reference to the WooCommerce inline data store.
				wc_inline_data = $('#woocommerce_inline_' + me.post_id);

				// Hide for excluded WooCommerce Product Types.
				product_type = wc_inline_data.find( '.product_type' ).text();
				if ( 'grouped' === product_type || 'external' === product_type ) {
					return;
				}

				// Get the reference to our inline data store.
				wpcv_inline_data = $('#wpcv_woo_civi_inline_' + me.post_id);

				// Get the Product data that we need.
				entity_type = wpcv_inline_data.find( '.entity_type' ).text();
				financial_type_id = wpcv_inline_data.find( '.financial_type_id' ).text();
				//pfv_id = wpcv_inline_data.find( '.pfv_id' ).text();

				// Set the selects that we can.
				$(me.class_entity + ' select').val( entity_type );
				$(me.class_financial + ' select').val( financial_type_id );

				// Show elements for Variable Products.
				if ( 'variable' === product_type ) {
					me.show_section();
					$(me.class_entity).show();
					return;
				}

				// Show elements for our Custom Product Types.
				if ( 'civicrm_contribution' === product_type ) {
					me.show_section();
					$(me.class_financial).show();
					//$(me.class_contribution).show();
					return;
				}
				if ( 'civicrm_membership' === product_type ) {
					me.show_section();
					$(me.class_financial).show();
					//$(me.classes_membership).show();
					return;
				}
				if ( 'civicrm_participant' === product_type ) {
					me.show_section();
					$(me.class_financial).show();
					//$(me.classes_participant).show();
					return;
				}

				// Show only Entity Type if empty.
				if ( ! entity_type ) {
					me.show_section();
					$(me.class_entity).show();
					return;
				}

				// Show common.
				me.show_section();
				$(me.class_entity).show();
				$(me.class_financial).show();

				// Disable full editability.
				return;

				// Show by Entity Type.
				if ( 'civicrm_contribution' === entity_type ) {
					$(me.class_contribution).show();
				}
				if ( 'civicrm_membership' === entity_type ) {
					$(me.classes_membership).show();
				}
				if ( 'civicrm_participant' === entity_type ) {
					$(me.classes_participant).show();
				}

			});

			/**
			 * Adds an onchange event listener to the "Entity Type" select.
			 *
			 * @param {Object} event The event object.
			 */
			$(me.class_entity + ' select').on( 'change', function( event ) {

				var value = $(this).val(), wc_inline_data, wpcv_inline_data;

				// Get the references to the inline data stores.
				wc_inline_data = $('#woocommerce_inline_' + me.post_id);
				//wpcv_inline_data = $('#wpcv_woo_civi_inline_' + me.post_id);

				// Get the Product data that we need.
				product_type = wc_inline_data.find( '.product_type' ).text();
				//entity_type = wpcv_inline_data.find( '.entity_type' ).text();

				// Bail if empty or excluding.
				if ( '' === value || 'civicrm_exclude' === value ) {
					$(me.class_financial).hide();
					return;
				}

				// Bail if Variable Product.
				if ( 'variable' === product_type ) {
					$(me.class_financial).hide();
					return;
				}

				// Show Financial Type.
				$(me.class_financial).show();

			});

		};

		/**
		 * Show section elements.
		 *
		 * @since 3.0
		 */
		this.show_section = function() {
			$(me.class_br).show();
			$(me.class_title).show();
		};

	}

	// Init Settings and Action classes.
	var WPCV_WCI_Quick_Edit_Settings = new WPCV_WCI_Settings();
	WPCV_WCI_Quick_Edit_Settings.init();
	var WPCV_WCI_Quick_Edit = new WPCV_WCI_Product_Quick_Edit();

	/**
	 * Trigger dom_ready methods where necessary.
	 *
	 * @since 3.0
	 *
	 * @param {Object} $ The jQuery object.
	 */
	$(document).ready(function($) {
		WPCV_WCI_Quick_Edit.dom_ready();
	});

} )( jQuery );
