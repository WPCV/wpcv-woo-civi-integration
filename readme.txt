=== Integrate CiviCRM with WooCommerce ===
Contributors: needle, bastho, mecachisenros, rajeshrhino
Tags: civicrm, woocommerce, integration
Requires PHP: 7.1
Requires at least: 5.7
Tested up to: 5.8
Stable tag: 3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Creates CiviCRM Contributions, Memberships and Participants from WordCommerce Orders and keeps WordCommerce Customer Accounts in sync with CiviCRM Contact data.


== Description ==

1. WooCommerce Orders are created as Contributions in CiviCRM. Each Product in the Order is a Line Item in the Contribution.
2. Sales Tax/VAT & Shipping Costs are configurable/mappable as CiviCRM Financial Types.
3. A default Campaign can be defined for each Contribution, but Campaigns can be customized per Order.
4. Logged in Users are recognised and the Contribution is created against the related CiviCRM Contact record.
5. If not logged in, the plugin tries to find the CiviCRM Contact record using Dedupe Rules and the Contribution is created against the CiviCRM Contact record if one is found.
6. If the CiviCRM Contact does not exist, a new Contact record is created in CiviCRM and the Contribution is created against the newly-created Contact record.
7. The related Contact record link is added to the WooCommerce Order as a note.
8. This plugin enables two-way syncing of Address, Billing Phone, and Billing Email between CiviCRM and WooCommerce. When a User edits their Address, Billing Phone, or Billing Email through their WooCommerce Account >> Edit Address page, their CiviCRM Profile, or through CiviCRM's admin interface, the data will be updated in both CiviCRM and WooCommerce.
9. This plugin can replace WooCommerce's States/Counties list with CiviCRM's State/Province list. (**WARNING!!!** Enabling this option in an existing WooCommerce instance will cause **States/Counties data loss** for **existing Customers** and the **WooCommerce settings** that rely on those.)
10. Basic Membership implementation: select the Membership Type in the CiviCRM Settings panel in the Product screen. If selected, a CiviCRM Membership will be created at checkout.

### Requirements

This plugin requires a minimum of *CiviCRM 5.42.1* and *WooCommerce 5.2.2+*.

### Configuration

Configure general integration settings in *WooCommerce* &rarr; *Settings* &rarr; *CiviCRM* Tab

<img src="https://raw.githubusercontent.com/WPCV/wpcv-woo-civi-integration/main/screenshots/wpcv-woo-settings.jpg" alt="General settings for integrating CiviCRM with WooCommerce" width="600" />

Configure settings for a Product in the *CiviCRM Settings* Tab.

<img src="https://github.com/WPCV/wpcv-woo-civi-integration/raw/main/screenshots/wpcv-woo-product.jpg" alt="Settings for integrating CiviCRM with a Product" width="470" />

Configure settings for an Order in the *General* section of the "New Order" and "Edit Order" screens.

<img src="https://github.com/WPCV/wpcv-woo-civi-integration/raw/main/screenshots/wpcv-woo-order.jpg" alt="Settings for integrating CiviCRM with an Order" width="153" />



== Known Issues ==

This plugin currently relies on the `Order.create` and `Payment.create` API in CiviCRM to register WooCommerce Orders as CiviCRM Contributions. There is currently a push in CiviCRM to fix various aspects of this API, which is why this plugin should ideally be used with CiviCRM 5.42.1 or greater.

The biggest outstanding issue is for Orders with a mix of Contribution, Membership and/or Participant in the same Order. The plugin works well if you are able to avoid these kinds of mixed Orders.

Creating Orders in WooCommerce admin is not fully supported. It is best to create Orders via the Checkout.



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress



== Changelog ==

= 3.0 =

* Initial WordPress Plugin Directory release.

= Prior to 3.0 =

* Please refer to [the changelog](https://github.com/WPCV/wpcv-woo-civi-integration/commits/main) at this plugin's [GitHub repo](https://github.com/WPCV/wpcv-woo-civi-integration).
