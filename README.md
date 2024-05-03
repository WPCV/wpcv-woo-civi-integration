# Integrate CiviCRM with WooCommerce

**Contributors:** [See full Contributor List](https://github.com/WPCV/wpcv-woo-civi-integration/graphs/contributors)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, woocommerce, contribution, sync<br/>
**Requires at least:** 5.7<br/>
**Tested up to:** 6.5<br/>
**Stable tag:** 3.1.2a<br/>
**License:** GPLv3<br/>
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

A WordPress plugin that creates CiviCRM Contributions, Memberships and Participants from WooCommerce Orders and keeps WooCommerce Customer Accounts in sync with CiviCRM Contact data.



## Description

Please note: this is the development repository for *Integrate CiviCRM with WooCommerce*.

*Integrate CiviCRM with WooCommerce* is a WordPress plugin that creates CiviCRM Contributions, Memberships and Participants from WooCommerce  Products and keeps WooCommerce Customer Accounts in sync with CiviCRM Contact data.



### Features

#### Contributions, Memberships and Participants

* Easily create WooCommerce Products that create Contributions, Memberships and Participants in CiviCRM.
* Each Product in a WooCommerce Order becomes a Line Item in the CiviCRM Contribution.
* Membership and Participant Statuses are updated when a WooCommerce Order is completed.
* Assign a default CiviCRM Campaign to all WooCommerce Orders or customize the Campaign per Order.
* Shipping Costs can be mapped to a CiviCRM Financial Type.

#### CiviCRM Contacts

* Logged in Users are recognised and the Contribution is created against the related CiviCRM Contact record.
* If not logged in, the plugin tries to find the CiviCRM Contact record using Dedupe Rules and the Contribution is created against the CiviCRM Contact record if one is found.
* If the CiviCRM Contact does not exist, a new Contact record is created in CiviCRM and the Contribution is created against the newly-created Contact record.
* The related Contact record link is added to each WooCommerce Order as a note.

#### Customer Accounts

* This plugin enables two-way syncing of Address, Billing Phone, and Billing Email between CiviCRM and WooCommerce.
* When a User edits their Address, Billing Phone, or Billing Email through their WooCommerce Account page, their CiviCRM Profile, or through CiviCRM's admin interface, the data will be updated in both CiviCRM and WooCommerce.

#### State/Province sync

* This plugin can replace WooCommerce's States/Counties list with CiviCRM's State/Province list.



### Requirements

This plugin requires a minimum of *CiviCRM 5.42.1* and *WooCommerce 5.2.2+*.



## Setup

Please refer to the [documentation](docs/SETUP.md).



## Known Issues

This plugin currently relies on the `Order.create` and `Payment.create` API in CiviCRM to register WooCommerce Orders as CiviCRM Contributions. There is currently a push in CiviCRM to fix various aspects of this API, which is why this plugin should ideally be used with CiviCRM 5.42.1 or greater.

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

