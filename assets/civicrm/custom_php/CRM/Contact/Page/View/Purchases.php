<?php
/**
 * QuickForm class that renders the Purchases Tab on CiviCRM Contact screens.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * QuickForm class that renders the Purchases Tab on CiviCRM Contact screens.
 *
 * @since 2.0
 */
class CRM_Contact_Page_View_Purchases extends CRM_Core_Page {

  /**
   * Render the QuickForm page.
   *
   * @since 2.0
   */
  public function run() {

    CRM_Utils_System::setTitle(__('Purchases', 'wpcv-woo-civi-integration'));

    $cid = CRM_Utils_Request::retrieve('cid', 'Positive', $this);

    $orders = WPCV_WCI()->contact->orders_tab->get_orders($cid);

    $this->assign('i18n', [
      'orderNumber'  => __('Order Number', 'wpcv-woo-civi-integration'),
      'date'         => __('Date', 'wpcv-woo-civi-integration'),
      'billingName'  => __('Billing Name', 'wpcv-woo-civi-integration'),
      'shippingName' => __('Shipping Name', 'wpcv-woo-civi-integration'),
      'itemCount'    => __('Item count', 'wpcv-woo-civi-integration'),
      'amount'       => __('Amount', 'wpcv-woo-civi-integration'),
      'actions'      => __('Actions', 'wpcv-woo-civi-integration'),
      'emptyUid'     => __('This Contact is not linked to any WordPress User or WooCommerce Customer', 'wpcv-woo-civi-integration'),
      'orders'       => __('Orders', 'wpcv-woo-civi-integration'),
      'addOrder'     => __('Add Order', 'wpcv-woo-civi-integration'),
      'edit'         => __('Edit', 'wpcv-woo-civi-integration'),
    ]);

    $this->assign('orders', $orders);

    $uid = abs(CRM_Core_BAO_UFMatch::getUFId($cid));
    if ($uid) {

      $url = add_query_arg(['post_type' => 'shop_order', 'user_id' => $uid], admin_url('post-new.php'));

      /**
       * Filter the URL for the new Order.
       *
       * @since 2.0
       *
       * @param string  $url The URL for the new Order.
       * @param integer $uid The numeric ID of the WordPress User.
       */
      $new_order_url = apply_filters('wpcv_woo_civi/add_order/url', $url, $uid);

      $this->assign('newOrderUrl', $new_order_url);

    }

    parent::run();

  }

}
