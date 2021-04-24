# Integrate CiviCRM with WooCommerce

## Installation

Step 1: Install Wordpress plugin

Install this Wordpress plugin as usual. More information about installing plugins in Wordpress - https://codex.wordpress.org/Managing_Plugins#Installing_Plugins

## Configuration

Configure the integration settings in WooCommerce Menu >> Settings >> CiviCRM (Tab)
Direct URL: https://example.com/wp-admin/admin.php?page=wc-settings&tab=woocommerce_civicrm

![Settings to integrate CiviCRM with WooCommerce](./screenshots/settings.jpg)

## Functionality

1. WooCommerce orders are created as contributions in CiviCRM, each product in the order is a line item in the Contribution.
2. Sales TAX/VAt & Shipping cost are configurable/mappable as CiviCRM Financial Types.
3. A global campaign can be defined for each contribution, but campaigns can be customized per order.
4. Logged in users are recognised and the contribution is created against the related contact record.
5. If not logged in, the plugin tries to find the contact record in CiviCRM using Dedupe rules and the contribution is created against the found contact record.
6. If the contact does not exist, a new contact record is created in CiviCRM and the contribution is created against the newly created contact record.
7. Related contact record link is added to the WooCommerce order as notes.
8. Option to sync CiviCRM and WooCommerce address, billing phone, and billing email. If a user edits his/hers address, billing phone, or billing email through the WooCommerce Account >> Edit Address page, CiviCRM profile, or through CiviCRM's backoffice, the data will be updated in both CiviCRM and WooCommerce.
9. Option to replace WooCommerce's States/Counties list with CiviCRM's State/Province list. (**WARNING!!!** Enabling this option in an exiting WooCommerce instance will cause **State/County data loss** for **exiting Customers** and **WooCommerce settings** that relay on those.)
10. Basic Membership implementation: select the Membership type in CiviCRM Settings panel in the Product screen, if set, a membership will be created at checkout.

## Developers

Documentation in progress.
