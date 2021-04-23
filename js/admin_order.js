/**
 * WPCV WooCommerce CiviCRM Javascript.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

/**
 * Act when the document has been rendered.
 *
 * @since 2.0
 */
jQuery( document ).ready(function(){

  // Convert Campaign dropdown to Select2.
  jQuery('#order_civicrmcampaign').select2();

});
