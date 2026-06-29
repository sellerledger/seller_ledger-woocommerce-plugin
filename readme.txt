=== Seller Ledger ===
Contributors: sellerledger
Tags: woocommerce, accounting, sellerledger, seller, ledger
Requires at least: 6.5
Tested up to: 6.7
Stable tag: 0.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seller Ledger! WooCommerce bookkeeping made easy.

== Description ==

Bookkeeping Made Easy.

Tracking finances and preparing for taxes is a hassle for any business, but for eCommerce sellers, it’s a nightmare. Fortunately, Seller Ledger has been specifically designed to simplify bookkeeping for online sellers.

Seller Ledger connects securely to your online sales channels (we currently support Amazon, eBay, Etsy, Poshmark, Mercari, Whatnot, Shopify, and WooCommerce) as well as PayPal and most banks and credit cards.

We organize your sales data and selling fees into the proper categories, and make it simple for you to categorize the rest. You can also enter other business transactions manually, including mileage and cost of goods.

Avoid stock-outs. See how much you make per order. Track your inventory and profitability at the level of detail that fits your business.

== External services ==

This plugin connects your store to Seller Ledger, a third-party bookkeeping service, so it can
record your sales and calculate sales tax. This connection is opt-in: nothing is sent until you
enter your Seller Ledger API key and connect your account, and you can disconnect at any time
from the plugin settings.

When connected, the plugin sends the following to the Seller Ledger API (https://app.sellerledger.com):

- Completed orders and refunds (order totals, line items, shipping, discounts, taxes, and the
  ship-to country, state, and postal code) so they can be recorded in your books.
- For real-time sales tax (only when you enable it), the cart contents and the customer's
  ship-to address at checkout, so tax can be calculated.

Your use of Seller Ledger is governed by its Terms of Service (https://www.sellerledger.com/terms)
and Privacy Policy (https://www.sellerledger.com/privacy).

== Frequently Asked Questions ==

= What are the requirements for using Seller Ledger with WooCommerce? =

- A Seller Ledger account
- A Seller Ledger API key (included with a Seller Ledger account)

= What does this plugin cost? =

Please see [Seller Ledger account pricing](https://sellerledger.com/#pricing).
A monthly plan is restricted to 90 days worth of order history; a yearly plan
will go back to the start of the prior year.

== Screenshots ==

1. The Seller Ledger dashboard
2. Setting up a Seller Ledger connection

== Installation ==

1. Unzip the plugin .zip file into the `/wp-content/plugins` directory on your server.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Enter your API key on the Seller Ledger panel in the WooCommerce Settings section

== Changelog ==

= 0.1.0 =
* Add real-time sales tax calculation at checkout via the Seller Ledger sales tax API
* Streamlined connection/onboarding with clearer status and a disconnect option
* Reworked historical import to run in background batches (Action Scheduler)
* Declare High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility
* Bundled libraries are namespaced (PHP-Scoper) to avoid conflicts with other plugins
* Optional "remove data on uninstall" setting and a clear external-services disclosure
* WordPress.org coding-standards pass and security/SQL hardening

= 0.0.3 =
* Various plugin standards updates

= 0.0.2 =
* Fixes to bring the plugin up to WP standards

= 0.0.1 =
* The (beta) version of the Seller Ledger plugin for WooCommerce

== Upgrade Notice ==

N/A
