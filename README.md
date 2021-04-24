# Integrate CiviCRM with WooCommerce

A WordPress plugin that integrates CiviCRM with WooCommerce.



## Description

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

This plugin requires a minimum of *CiviCRM 4.6* and *WooCommerce 3.0+*.

### Configuration

Configure the integration settings in WooCommerce >> Settings >> CiviCRM (Tab)

Example URL: `https://example.com/wp-admin/admin.php?page=wc-settings&tab=woocommerce_civicrm`

![Settings to integrate CiviCRM with WooCommerce](./screenshots/settings.jpg)



## Developers

Documentation in progress.



## Installation

There are two ways to install from GitHub:

### ZIP Download

If you have downloaded Integrate CiviCRM with WooCommerce as a ZIP file from the GitHub repository, do the following to install the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/https://github.com/WPCV/wpcv-woo-civi-integration`
2. CiviCRM Profile Sync is installed. Read on for activation

### git clone

If you have cloned the code from GitHub, it is assumed that you know what you're doing.

