/**
* WPCV WooCommerce CiviCRM "Migrate Page" Javascript.
 *
 * Implements progress bar functionality on the plugin's "Migration Page".
 *
 * @package WPCV_Woo_Civi
 */

/**
 * Create Migrate object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 3.0
 */
var WPCV_Woo_Civi_Migrate = WPCV_Woo_Civi_Migrate || {};



/**
 * Pass the jQuery shortcut in.
 *
 * @since 3.0
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Object.
	 *
	 * @since 3.0
	 */
	WPCV_Woo_Civi_Migrate.settings = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.init = function() {
			me.init_localisation();
			me.init_settings();
		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.dom_ready = function() {

		};

		// Init localisation array
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 3.0
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof WPCV_Woo_Civi_Migrate_Settings ) {
				me.localisation = WPCV_Woo_Civi_Migrate_Settings.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 3.0
		 *
		 * @param {String} The identifier for the desired localisation string
		 * @return {String} The localised string
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
			if ( 'undefined' !== typeof WPCV_Woo_Civi_Migrate_Settings ) {
				me.settings = WPCV_Woo_Civi_Migrate_Settings.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 3.0
		 *
		 * @param {String} The identifier for the desired setting
		 * @return The value of the setting
		 */
		this.get_setting = function( identifier ) {
			return me.settings[identifier];
		};

	};

	/**
	 * Create Progress Bar Object.
	 *
	 * @since 3.0
	 */
	WPCV_Woo_Civi_Migrate.progress_bar = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Progress Bar.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.init = function() {

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
		 * Set up Progress Bar instance.
		 *
		 * @since 3.0
		 */
		this.setup = function() {

			// Assign properties.
			me.bar = $('#progress-bar');
			me.label = $('#progress-bar .progress-label');
			me.total = WPCV_Woo_Civi_Migrate.settings.get_setting( 'total_products' );
			me.label_init = WPCV_Woo_Civi_Migrate.settings.get_localisation( 'total' );
			me.label_current = WPCV_Woo_Civi_Migrate.settings.get_localisation( 'current' );
			me.label_complete = WPCV_Woo_Civi_Migrate.settings.get_localisation( 'complete' );
			me.label_done = WPCV_Woo_Civi_Migrate.settings.get_localisation( 'done' );

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 3.0
		 */
		this.listeners = function() {

			// Declare vars.
			var button = $('#wpcv_woocivi_process');

			// The AJAX nonce.
			me.ajax_nonce = button.data( 'security' );

			/**
			 * Add a click event listener to start process.
			 *
			 * @param {Object} event The event object.
			 */
			button.on( 'click', function( event ) {

				// Prevent form submission.
				if ( event.preventDefault ) {
					event.preventDefault();
				}

				// Initialise progress bar.
				me.bar.progressbar({
					value: false,
					max: me.total
				});

				// Show progress bar if not already shown.
				me.bar.show();

				// Initialise progress bar label
				me.label.html( me.label_init.replace( '{{total}}', me.total ) );

				// Send.
				me.send();

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 3.0
		 *
		 * @param {Array} data The data received from the server
		 */
		this.update = function( data ) {

			// Declare vars.
			var val, batch_count;

			// Are we still in progress?
			if ( data.finished == 'false' ) {

				// Get current value of progress bar.
				val = me.bar.progressbar( 'value' ) || 0;

				// Update progress bar label.
				me.label.html(
					me.label_complete.replace( '{{from}}', data.from ).replace( '{{to}}', data.to )
				);

				// Get number per batch.
				batch_count = parseInt( WPCV_Woo_Civi_Migrate.settings.get_setting( 'batch_count' ) );

				// Update progress bar.
				me.bar.progressbar( 'value', val + batch_count );

				// Trigger next batch.
				me.send();

			} else {

				// Update progress bar label.
				me.label.html( me.label_done );

				// Hide the Products section.
				setTimeout(function () {
					$('#wpcv_woocivi_products').hide();
				}, 2000 );

			}

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 3.0
		 */
		this.send = function() {

			// Define vars.
			var url, data;

			// URL to post to.
			url = WPCV_Woo_Civi_Migrate.settings.get_setting( 'ajax_url' );

			// Data received by WordPress.
			data = {
				action: 'wpcv_process_products',
				_ajax_nonce: me.ajax_nonce
			};

			// Use jQuery post.
			$.post( url, data,

				// Callback.
				function( data, textStatus ) {

					// If success.
					if ( textStatus == 'success' ) {

						// Update progress bar.
						me.update( data );

					} else {

						// Show error.
						if ( console.log ) {
							console.log( textStatus );
						}

					}

				},

				// Expected format.
				'json'

			);

		};

	};

	// Init settings.
	WPCV_Woo_Civi_Migrate.settings.init();

	// Init Progress Bar.
	WPCV_Woo_Civi_Migrate.progress_bar.init();

} )( jQuery );



/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 3.0
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now.
	WPCV_Woo_Civi_Migrate.settings.dom_ready();

	// The DOM is loaded now.
	WPCV_Woo_Civi_Migrate.progress_bar.dom_ready();

}); // end document.ready()



