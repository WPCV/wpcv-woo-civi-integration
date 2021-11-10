# Integrate CiviCRM with WooCommerce

**Contributors:** [See full Contributor List](https://github.com/WPCV/wpcv-woo-civi-integration/graphs/contributors)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, woocommerce, contribution, sync<br/>
**Requires at least:** 5.7<br/>
**Tested up to:** 5.8<br/>
**Stable tag:** 3.0<br/>
**License:** GPLv3<br/>
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

A WordPress plugin that creates CiviCRM Contributions, Memberships and Participants from WordCommerce Orders and keeps WordCommerce Customer Accounts in sync with CiviCRM Contact data.


## Description

Please note: this is the development repository for *Integrate CiviCRM with WooCommerce*.

*Integrate CiviCRM with WooCommerce* is a WordPress plugin that creates CiviCRM Contributions, Memberships and Participants from WordCommerce Orders and keeps WordCommerce Customer Accounts in sync with CiviCRM Contact data.


### Features

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


### General Settings

Configure general integration settings in *WooCommerce* &rarr; *Settings* &rarr; *CiviCRM* Tab

<img src="docs/screenshots/wpcv-woo-settings.jpg" alt="General settings for integrating CiviCRM with WooCommerce" width="800" />

### Individual Product Settings

Configure settings for a Product in the *CiviCRM Settings* Tab.

#### Product that creates a CiviCRM Contribution

<img src="docs/screenshots/wpcv-woo-product-tab-contribution.jpg" alt="Settings for integrating a Product with a CiviCRM Contribution" width="500" />

#### Product that creates a CiviCRM Membership

<img src="docs/screenshots/wpcv-woo-product-tab-membership.jpg" alt="Settings for integrating a Product with a CiviCRM Membership" width="500" />

#### Product that creates a CiviCRM Participant

<img src="docs/screenshots/wpcv-woo-product-tab-participant.jpg" alt="Settings for integrating a Product with a CiviCRM Participant" width="500" />

### Product Bulk and Quick Edit

The Products Listing table shows information about how each Product is configured with respect to CiviCRM.

<img src="docs/screenshots/wpcv-woo-products-list.jpg" alt="CiviCRM information shown in the Products Listing table" width="800" />

You can use Bulk Edit and Quick Edit functionality to edit the "Entity Type" and "Financial Type" of a Product. More granular settings must be made on the *CiviCRM Settings* Tab on the individual "Edit Product" page.

<img src="docs/screenshots/wpcv-woo-products-list-bulk-edit.jpg" alt="CiviCRM information shown in the Product Bulk Edit UI" width="800" />

<img src="docs/screenshots/wpcv-woo-products-list-quick-edit.jpg" alt="CiviCRM information shown in the Product Quick Edit UI" width="800" />

### Individual Order Settings

Configure settings for an Order in the *General* section of the "New Order" and "Edit Order" screens.

<img src="docs/screenshots/wpcv-woo-order-panel.jpg" alt="Settings for integrating CiviCRM with an Order" width="500" />


## Developers

Documentation in progress.



## Know Issues

This plugin currently relies on the `Order.create` and `Payment.create` API in CiviCRM to register WooCommerce Orders as CiviCRM Contributions. There is currently a push in CiviCRM to fix various aspects of this API, which is why this plugin should ideally be used with CiviCRM 5.42.1 or greater.

The biggest outstanding issue is for Orders with a number of taxable Products in the same Order. The plugin works well if you are able to avoid taxable Products.

Creating Orders in WooCommerce admin is not fully supported. It is best to create Orders via the Checkout.



## Installation

There are two ways to install from GitHub:

### ZIP Download

If you have downloaded Integrate CiviCRM with WooCommerce as a ZIP file from the GitHub repository, do the following to install the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/wpcv-woo-civi-integration`
2. Make sure *WooCommerce* is activated and configured
3. Make sure *CiviCRM* is activated and configured
4. Activate the plugin
5. Configure the plugin as described above

### git clone

If you have cloned the code from GitHub, it is assumed that you know what you're doing.

