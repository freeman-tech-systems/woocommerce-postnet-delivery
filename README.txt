=== Delivery Options For PostNet ===
Contributors: freemantech
Tags: WooCommerce, PostNet, Shipping, Delivery
Requires at least: 4.0
Tested up to: 6.7.2
Requires PHP: 7.4
Stable tag: 1.0.11
License: GPL v2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Delivery Options For PostNet is a plugin that adds PostNet delivery options to your WooCommerce store.

== Description ==
Delivery Options For PostNet is a plugin that adds PostNet delivery options to your WooCommerce store. Offer customers the flexibility to choose from various PostNet shipping methods such as Free Shipping, PostNet to PostNet, Express, and Economy.

This plugin integrates with PostNet's API to provide real-time shipping rates, generate waybills, and notify stores about new orders. By using this plugin, you agree that order data will be sent to PostNet for processing.

= Third-Party Service Integration =
This plugin relies on PostNet's API as a third-party service for the following functionalities:
* Fetching real-time shipping rates
* Generating waybills for orders
* Notifying PostNet stores about new orders

For more information about PostNet's services, please visit: https://www.postnet.co.za/

= Privacy and Terms =
By using this plugin, you agree to PostNet's terms of service and privacy policy: [PostNet Privacy Policy](https://www.postnet.co.za/app-privacy-policy)

== Features ==
* Multiple Shipping Options: Provide different shipping methods to your customers.
* Easy Configuration: Simple setup and configuration in the WooCommerce settings.
* WooCommerce Compatibility: Compatible with the latest version of WooCommerce.
* Customizable: Modify the shipping methods according to your store's needs.
* Multi Site Mode: Support for multiple collection addresses with delayed waybill creation until order completion.

== Installation ==
1. Download the plugin zip file from the [releases page](https://github.com/freeman-tech-systems/woocommerce-postnet-delivery/releases).
2. In your WordPress admin dashboard, go to Plugins > Add New > Upload Plugin.
3. Choose the downloaded zip file and click Install Now.
4. Activate the plugin through the Plugins menu in WordPress.
5. Under WooCommerce in the menu click on the PostNet Delivery menu item to configure the plugin.
6. This plugin is compatible with the WooCommerce Checkout Block in the block editor, and also supports the previous `[woocommerce_checkout]` shortcode as well.
7. This plugin is not yet compatible with the new WooCommerce Product management screen, so you will need to use the Classic screen instead.

== Usage ==
Setup and usage instructions can be found at [PostNet WooCommerce Plugin](https://www.postnet.co.za/woocommerce-app-info).

== Multi Site Mode ==
The plugin now supports Multi Site Mode, which allows you to:

* Configure multiple collection addresses instead of using PostNet stores as the origin
* Delay waybill creation until orders are marked as "Completed"
* Select specific collection addresses for each order in the admin panel

To enable Multi Site Mode:

1. Go to WooCommerce > PostNet Delivery in your WordPress admin
2. Check the "Enable Multi Site Mode" option
3. Configure your collection addresses with the required fields:
   - Street Address
   - Additional Address Info (optional)
   - Country Code
   - Suburb/City
   - Postal Code
   - Company/Business Name
   - Contact Number
   - Contact Person
4. Save your settings

### Validation Features

The plugin includes several validation mechanisms to ensure proper setup:

* **Order Completion Blocking**: Orders cannot be marked as "Completed" without selecting a collection address
* **Visual Indicators**: Clear visual feedback shows whether collection addresses are properly configured
* **Real-time Validation**: JavaScript validation prevents status changes without proper address selection
* **Error Messages**: Helpful error messages guide users through the required setup process

When Multi Site Mode is enabled:
* Waybills will not be created automatically when orders are placed
* You must select a collection address for each order in the admin panel
* **Order completion is blocked until a collection address is selected**
* Waybills will only be created when the order status is changed to "Completed"
* The selected collection address will be used as the originating address on the waybill

== Changelog ==
= 1.0.11 =
* PostNet shipping options are now identified by stored instance IDs instead of method titles
* Clicking "Configure PostNet Shipping" saves a unique reference for each created option so the plugin continues to work correctly if you rename the methods in WooCommerce
* Checkout store selector and waybill logic now use these IDs for all checks

= 1.0.10 =
* Added flat rate options for Regional Centre - Express, Regional Centre - Economy, Main Centre - Express, and Main Centre - Economy delivery options
* Flat rates can be configured in settings; if set, they override individual product calculations
* If flat rates are left blank, the plugin continues to use individual product-based fee calculations

= 1.0.8 =
* Added Multi Site Mode support
* Added multiple collection addresses configuration
* Delayed waybill creation until order completion in Multi Site Mode
* Added collection address selection in admin order panel
* Enhanced API integration with collection address data
* Refactored waybill creation logic to eliminate code duplication
* Fixed fatal error when WC()->session is null during order status changes
* Added comprehensive error handling and logging for better debugging

= 1.0.7 =
* Force update

= 1.0.6 =
* Fix destination store bug

= 1.0.5 =
* Fix waybill and tracking links on order

= 1.0.4 =
* Auto select the closest destination store for PostNet to PostNet deliveries

= 1.0.3 =
* Fix the map marker on the map search

= 1.0.2 =
* Added google map search for picking the destination store

= 1.0.1 =
* Added support for the checkout blocks on the checkout page

= 1.0.0 =
* Initial release with basic PostNet delivery options.

== Contributing ==
1. Make sure you have the latest version of Docker Desktop installed and running. [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Clone the repository to your local workspace.
3. Open a terminal and navigate to the project folder.
4. Run the following docker compose command:
   `docker compose -f docker/docker-compose.yml up --build`
5. Navigate to [http://postnet_plugin.lvh.me:8080/](http://postnet_plugin.lvh.me:8080/)
6. Complete the WordPress setup.
7. Login.
8. Install and Activate WooCommerce.
9. Setup a basic store.

== License ==
This plugin is licensed under the GPL v2 License. See the [LICENSE](https://github.com/freeman-tech-systems/woocommerce-postnet-delivery/blob/main/LICENSE) file for more details.

== Support ==
For any issues, please open a ticket on the [GitHub issues page](https://github.com/freeman-tech-systems/woocommerce-postnet-delivery/issues).

== Credits ==
Developed by [Freeman Tech Systems](https://github.com/freeman-tech-systems).
