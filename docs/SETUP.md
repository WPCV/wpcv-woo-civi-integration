# Setup

## Customer Account sync

Enabling two-way syncing of Address, Billing Phone and Billing Email between CiviCRM and WooCommerce means that when a an edit is made to an Address, Billing Phone, or Billing Email through:

* A User's WooCommerce Account page
* A Contact's CiviCRM Profile or
* Through CiviCRM's admin interface

The data will be updated in both CiviCRM and WooCommerce.



## State/Province sync

**Important note:** although it is clearly desirable to have matching data in WooCommerce and CiviCRM, selecting the option to replace WooCommerce's States/Counties list with CiviCRM's State/Province list will cause **States/Counties data loss** for **existing Customers** and the **WooCommerce settings** that rely on those.

For maximum data integrity, it is best to choose the replacement when first this plugin and WooCommerce are first installed.



## Plugin Settings

### General Settings

Configure general integration settings in *WooCommerce* &rarr; *Settings* &rarr; *CiviCRM* Tab

<img src="screenshots/wpcv-woo-settings.jpg" alt="General settings for integrating CiviCRM with WooCommerce" width="800" />

### Individual Product Settings

Configure settings for a Product in the *CiviCRM Settings* Tab.

#### Product that creates a CiviCRM Contribution

<img src="screenshots/wpcv-woo-product-tab-contribution.jpg" alt="Settings for integrating a Product with a CiviCRM Contribution" width="500" />

#### Product that creates a CiviCRM Membership

<img src="screenshots/wpcv-woo-product-tab-membership.jpg" alt="Settings for integrating a Product with a CiviCRM Membership" width="500" />

#### Product that creates a CiviCRM Participant

<img src="screenshots/wpcv-woo-product-tab-participant.jpg" alt="Settings for integrating a Product with a CiviCRM Participant" width="500" />

### Product Bulk and Quick Edit

The Products Listing table shows information about how each Product is configured with respect to CiviCRM.

<img src="screenshots/wpcv-woo-products-list.jpg" alt="CiviCRM information shown in the Products Listing table" width="800" />

You can use Bulk Edit and Quick Edit functionality to edit the "Entity Type" and "Financial Type" of a Product. More granular settings must be made on the *CiviCRM Settings* Tab on the individual "Edit Product" page.

<img src="screenshots/wpcv-woo-products-list-bulk-edit.jpg" alt="CiviCRM information shown in the Product Bulk Edit UI" width="800" />

<img src="screenshots/wpcv-woo-products-list-quick-edit.jpg" alt="CiviCRM information shown in the Product Quick Edit UI" width="800" />

### Individual Order Settings

Configure settings for an Order in the *General* section of the "New Order" and "Edit Order" screens.

<img src="screenshots/wpcv-woo-order-panel.jpg" alt="Settings for integrating CiviCRM with an Order" width="500" />

