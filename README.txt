=== Delivery Options For PostNet ===
Contributors: freemantech
Tags: WooCommerce, PostNet, Shipping, Delivery
Requires at least: 4.0
Tested up to: 6.7.2
Requires PHP: 7.4
Stable tag: 1.0.6
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

== Changelog ==
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
