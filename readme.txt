=== Integrate CiviCRM with WooCommerce ===
Contributors: veda-consulting, mecachisenros, rajeshrhino, JoeMurray, kcristiano, cdhassell, bastho
Tags: civicrm, woocommerce, integration
Requires at least: 4.5
Tested up to: 4.8
Stable tag: 2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrates CiviCRM with WooCommerce.


== Description ==

1. WooCommerce orders are created as contributions in CiviCRM. Line items are not created in the contribution, but the product name x quantity are included in the 'source' field of the contribution
2. Salex tax (VAT) & Shipping cost are saved as custom data against contribution
3. Logged in users are recognised and the contribution is created against the related contact record
4. If not logged in, the plugin tries to find the contact record in CiviCRM using Dedupe rules and the contribution is created against the found contact record.
5. If the contact does not exist, a new contact record is created in CiviCRM and the contribution is created against the newly created contact record.
6. Related contact record link is added to the WooCommerce order as notes.
7. Option to sync CiviCRM and WooCommerce address, billing phone, and billing email. If a user edits his/hers address, billing phone, or billing email through the WooCommerce Account >> Edit Address page, CiviCRM profile, or through CiviCRM's backoffice, the data will be updated in both CiviCRM and WooCommerce.
8. Option to replace WooCommerce's States/Counties list with CiviCRM's State/Province list. (WARNING!!! Enabling this option in an exiting WooCommerce instance will cause State/Couny data loss for exiting Customers and WooCommerce settings that relay on those.)

### Requirements

This plugin requires a minimum of *CiviCRM 4.6* and *WooCommerce 3.0+*.

### Configuration

Configure the integration settings in WooCommerce Menu >> Settings >> CiviCRM (Tab)
Direct URL: `https://example.com/wp-admin/admin.php?page=wc-settings&tab=woocommerce_civicrm`



== Installation ==

Step 1: Install Wordpress plugin

Install this Wordpress plugin as usual. More information about installing plugins in Wordpress - https://codex.wordpress.org/Managing_Plugins#Installing_Plugins


== Changelog ==

= 3.0 =

* Plugin refactored and renamed.

= Prior to 3.0 =

* Please refer to [the changelog](https://github.com/WPCV/wpcv-woo-civi-integration/commits/main) at this plugin's [GitHub repo](https://github.com/WPCV/wpcv-woo-civi-integration).
