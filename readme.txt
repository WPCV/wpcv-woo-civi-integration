=== Integrate CiviCRM with WooCommerce ===
Contributors: veda-consulting, mecachisenros, rajeshrhino, JoeMurray, kcristiano, cdhassell, bastho
Tags: civicrm, woocommerce, integration
Requires PHP: 7.1
Requires at least: 4.9
Tested up to: 5.7
Stable tag: 2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrates CiviCRM with WooCommerce.


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

This plugin requires a minimum of *CiviCRM 5.20* and *WooCommerce 3.9+*.

### Configuration

Configure general integration settings in *WooCommerce* &rarr; *Settings* &rarr; *CiviCRM* Tab

<img src="https://github.com/WPCV/wpcv-woo-civi-integration/screenshots/wpcv-woo-settings.jpg" alt="General settings for integrating CiviCRM with WooCommerce" width="600" />

Configure settings for a Product in the *CiviCRM Settings* Tab.

<img src="https://github.com/WPCV/wpcv-woo-civi-integration/screenshots/wpcv-woo-product.jpg" alt="Settings for integrating CiviCRM with a Product" width="470" />

Configure settings for an Order in the *General* section of the "New Order" and "Edit Order" screens.

<img src="https://github.com/WPCV/wpcv-woo-civi-integration/screenshots/wpcv-woo-order.jpg" alt="Settings for integrating CiviCRM with anOrder" width="153" />



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress



== Changelog ==

= 3.0 =

* Initial WordPress Plugin Directory release.

= Prior to 3.0 =

* Please refer to [the changelog](https://github.com/WPCV/wpcv-woo-civi-integration/commits/main) at this plugin's [GitHub repo](https://github.com/WPCV/wpcv-woo-civi-integration).
