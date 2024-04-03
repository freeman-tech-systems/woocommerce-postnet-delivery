# Getting Started

1. Make sure you have the latest version of Docker Desktop installed and running. [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Clone the repository to your local workspace.
3. Open a terminal and navigate to the project folder.
4. Run the following docker compose command
  ```
  docker compose -f docker/docker-compose.yml up --build
  ```
5. Navigate to http://postnet_plugin.lvh.me:8080/
6. Complete the Wordpress setup.
7. Login
8. Install and Activate WooCommerce
9. Setup a basic store
10. Activate the WooCommerce PostNet Delivery plugin
11. Under WooCommerce in the menu click on the PostNet Delivery menu item to configure the plugin
12. This plugin is not yet compatible with the WooCommerce Checkout Block in the block editor so you will need to use the `[woocommerce_checkout]` shortcode instead.
13. This plugin is not yet compatible with the new WooCommerce Product management screen so you will need to use the Classic screen instead.