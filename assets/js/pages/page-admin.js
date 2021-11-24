/**
 * Admin Page Javascript.
 *
 * Implements progress bar functionality on the plugin's Admin Page.
 *
 * @package WPCV_Woo_Civi
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
	function WPCV_Woo_Civi_Admin_Settings() {

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
			if ( 'undefined' !== typeof WPCV_Woo_Civi_Admin_Vars ) {
				me.localisation = WPCV_Woo_Civi_Admin_Vars.localisation;
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
			if ( 'undefined' !== typeof WPCV_Woo_Civi_Admin_Vars ) {
				me.settings = WPCV_Woo_Civi_Admin_Vars.settings;
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
	 * Create Event Object.
	 *
	 * @since 3.0
	 */
	function WPCV_Woo_Civi_Admin_Event() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Event metabox.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.init = function() {
			me.ajax_url = WPCV_WCI_Admin_Settings.get_setting( 'ajax_url' );
			me.notice_success = WPCV_WCI_Admin_Settings.get_setting( 'notice_success' );
			me.notice_error = WPCV_WCI_Admin_Settings.get_setting( 'notice_error' );
			me.creating_label = WPCV_WCI_Admin_Settings.get_localisation( 'creating' );
			me.button_text = WPCV_WCI_Admin_Settings.get_localisation( 'event_button' );
		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.dom_ready = function() {
			me.setup();
			me.listeners();
		};

		/**
		 * Set up Event metabox instance.
		 *
		 * @since 3.0
		 */
		this.setup = function() {

			// Store submit button and AJAX nonce.
			me.event_button = $('#wpcv_woocivi_event_process');
			me.ajax_nonce = me.event_button.data( 'security' );

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.listeners = function() {

			/**
			 * Add a click event listener to start process.
			 *
			 * @param {Object} event The event object.
			 */
			me.event_button.on( 'click', function( event ) {

				var event_data = [];

				// Prevent form submission.
				if ( event.preventDefault ) {
					event.preventDefault();
				}

				// Modify button, then show spinner.
				me.event_button.val( me.creating_label );
				me.event_button.prop( 'disabled', true );
				$(this).next('.spinner').css( 'visibility', 'visible' );

				// Collate data to send.
				event_data = {
					product_type: $('#wpcv_wci_event_product_type').val(),
					event_id: $('#wpcv_wci_event_id').val(),
					financial_type: $('#wpcv_wci_event_financial_type_id').val(),
					role: $('#wpcv_wci_event_role_id').val(),
					pfv_ids: $('#wpcv_wci_event_variations_pfv_ids').val()
				};

				// Disable form elements.
				me.disable();

				// Remove feedback.
				$('.event_feedback .notice').remove();

				// Send.
				me.send( event_data );

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 3.0
		 *
		 * @param {Mixed} value The value to send.
		 */
		this.send = function( value ) {

			// Define vars.
			var data;

			// Data received by WordPress.
			data = {
				action: 'wpcv_process_event',
				_ajax_nonce: me.ajax_nonce,
				value: value
			};

			// Use jQuery post.
			$.post( me.ajax_url, data,

				// Callback.
				function( result, textStatus ) {

					// Update on success, otherwise show error.
					if ( textStatus == 'success' ) {
						me.update( result );
					} else {
						if ( console.log ) {
							console.log( textStatus );
						}
					}

				},

				// Expected format.
				'json'

			);

		};

		/**
		 * Act on the result of the AJAX request.
		 *
		 * @since 3.0
		 *
		 * @param {Array} data The data received from the server.
		 */
		this.update = function( data ) {

			// Always reset button and spinner.
			me.event_button.val( me.button_text );
			me.event_button.prop( 'disabled', false );
			me.event_button.next('.spinner').css( 'visibility', 'hidden' );

			// Were we successful?
			if ( data.saved ) {
				$('.event_feedback').append( me.notice_success );
				$('.event_success p').html( data.notice );
				$('.event_success').show();
			} else {
				$('.event_feedback').append( me.notice_error );
				$('.event_error p').html( data.notice );
				$('.event_error').show();
			}

			/**
			 * Add a click event listener to notice dismiss button.
			 *
			 * @param {Object} event The event object.
			 */
			$('.event_feedback .notice-dismiss').on( 'click', function( event ) {
				container = $(this).parent('.notice');
				container.fadeOut( 750, function() {
					container.remove();
				});
			});

			// Enable form elements.
			me.enable();

		};

		/**
		 * Disable form elements during AJAX request.
		 *
		 * @since 3.0
		 */
		this.disable = function() {
			$('#wpcv_wci_event_product_type').prop( 'disabled', true );
			$('#wpcv_wci_event_id').prop( 'disabled', true );
			$('#wpcv_wci_event_financial_type_id').prop( 'disabled', true );
			$('#wpcv_wci_event_role_id').prop( 'disabled', true );
			$('#wpcv_wci_event_variations_pfv_ids').prop( 'disabled', true );
		};

		/**
		 * Enable form elements after AJAX request.
		 *
		 * @since 3.0
		 */
		this.enable = function() {
			$('#wpcv_wci_event_product_type').prop( 'disabled', false );
			$('#wpcv_wci_event_id').prop( 'disabled', false );
			$('#wpcv_wci_event_financial_type_id').prop( 'disabled', false );
			$('#wpcv_wci_event_role_id').prop( 'disabled', false );
			$('#wpcv_wci_event_variations_pfv_ids').prop( 'disabled', false );
		};

	}

	// Init Settings and Event classes.
	var WPCV_WCI_Admin_Settings = new WPCV_Woo_Civi_Admin_Settings();
	var WPCV_WCI_Admin_Event = new WPCV_Woo_Civi_Admin_Event();
	WPCV_WCI_Admin_Settings.init();
	WPCV_WCI_Admin_Event.init();

	/**
	 * Trigger dom_ready methods where necessary.
	 *
	 * @since 3.0
	 *
	 * @param {Object} $ The jQuery object.
	 */
	$(document).ready(function($) {
		WPCV_WCI_Admin_Event.dom_ready();
	});

} )( jQuery );
