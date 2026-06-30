=== Seller Ledger ===
Contributors: sellerledger
Tags: woocommerce, accounting, bookkeeping, sales tax, ecommerce
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated bookkeeping and sales tax for WooCommerce. Sync orders to Seller Ledger and automatically calculate and collect tax at checkout.

== Description ==

Tracking finances and preparing for taxes is a hassle for any business, but for eCommerce sellers it's a nightmare. **Seller Ledger** is simple, automated accounting built specifically for online and marketplace sellers — and this plugin connects your WooCommerce store to it.

Once connected, your completed WooCommerce orders and refunds flow into Seller Ledger automatically, and you can calculate and collect jurisdiction-level sales tax in real time at checkout.

= Automated bookkeeping =

* Import orders, shipping, fees, and refunds and automatically sort them into the right accounting categories.
* Real double-entry accounting under a simple interface — no accounting background required.
* Monthly and annual Profit & Loss reports, regularly updated with current sales and expense data.
* A Schedule C tax report generated from your sales and expense history.
* Track inventory and cost of goods, and see your profit on every order.

= Reconciliation across channels =

* Consolidate WooCommerce alongside Amazon, eBay, Etsy, Shopify, Walmart, and other marketplaces into one set of books.
* Orders, selling fees, refunds, and returned items are all tracked.
* Marketplace and processor payouts are recorded as transfers, so when you also connect the bank account that receives them, deposits net cleanly and never inflate your Profit & Loss.

= Bank and account connections =

* Securely connect most banks and credit card accounts to import transactions automatically and eliminate manual entry.
* Connect your sales channels and payment processors (including PayPal) so everything reconciles in one place.

= Sales tax: collection and reports =

* **Collect the right tax at checkout.** This plugin uses Seller Ledger's sales tax engine to calculate jurisdiction-level tax — state, county, city, and special districts — in real time, based on the customer's shipping address and the states where you have nexus.
* **Economic nexus tracking.** Seller Ledger builds every state's economic-nexus thresholds into the software, shows you where you have nexus, and alerts you as you approach each threshold so you're never caught by surprise.
* **Return-ready reports.** Your sales and tax collected are grouped by jurisdiction so that, when it's time to file, you have everything you need to remit and file your state returns.

= How the WooCommerce plugin works =

* One-click connect from Seller Ledger provisions your API key and connection — no copy-pasting keys.
* Completed orders and refunds sync automatically in the background.
* Turn on real-time sales tax to calculate tax at checkout for the states where you have established nexus; other destinations are left untaxed.
* See your connection status, sync history, and the states you're collecting in right from WooCommerce settings, and re-import historical orders on demand.

Seller Ledger was built by founders who ran product and engineering at TaxJar. Try it free for 30 days — only pay if you're happy.

== External services ==

This plugin connects your store to Seller Ledger, a third-party bookkeeping service, so it can
record your sales and calculate sales tax. This connection is opt-in: nothing is sent until you
connect your Seller Ledger account, and you can disconnect at any time by removing the plugin or
clearing your API key in the plugin settings.

When connected, the plugin sends the following to the Seller Ledger API (https://app.sellerledger.com):

- Completed orders and refunds (order totals, line items, shipping, discounts, taxes, the buyer
  name, and the ship-to country, state, and postal code) so they can be recorded in your books.
- For real-time sales tax (only when you enable it), the cart contents and the customer's
  ship-to address at checkout, so tax can be calculated.

Your use of Seller Ledger is governed by its Terms of Service (https://www.sellerledger.com/terms)
and Privacy Policy (https://www.sellerledger.com/privacy).

== Frequently Asked Questions ==

= What are the requirements for using Seller Ledger with WooCommerce? =

- A Seller Ledger account
- A Seller Ledger API key (included with a Seller Ledger account)

= What does this plugin cost? =

Please see [Seller Ledger account pricing](https://sellerledger.com/pricing/). Plans start at
$10/month with a 30-day free trial. A monthly plan imports up to 90 days of order history; an
annual plan imports back to the start of the prior year.

= Does this plugin calculate sales tax at checkout? =

Yes. Enable real-time sales tax in the plugin settings and Seller Ledger calculates
jurisdiction-level tax at checkout for the states where you have established nexus. Destinations
where you don't have nexus are left untaxed.

= Does Seller Ledger track economic nexus? =

Yes. Every state's economic-nexus thresholds are built in. Seller Ledger shows where you have
nexus and alerts you as you approach each state's threshold.

= Does Seller Ledger file my sales tax returns? =

Not yet. Seller Ledger gives you return-ready reports broken down by jurisdiction so you can
remit and file accurately.

= Is Seller Ledger real double-entry accounting? =

Yes — with a simplified interface that hides the balance-sheet and double-entry mechanics so you
don't need an accounting background to use it.

= How are marketplace and processor payouts handled? =

Payouts are recorded as transfers rather than income. When you also connect the receiving bank
account, the matching deposits net out and don't inflate your Profit & Loss.

== Screenshots ==

1. The Seller Ledger dashboard
2. Connecting your store to Seller Ledger
3. Sales tax nexus tracking across states
4. Return-ready sales tax reports broken down by jurisdiction

== Installation ==

1. Unzip the plugin .zip file into the `/wp-content/plugins` directory on your server.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open the Seller Ledger panel in WooCommerce > Settings and click Connect, or paste your API key.

== Changelog ==

= 0.1.0 =
* Real-time sales tax at checkout via the Seller Ledger sales tax engine, collecting only where you have established nexus
* See the states you collect in — with nexus type, established date, and filing frequency — in WooCommerce settings
* One-click connect from Seller Ledger that provisions your API key and connection
* Per-order sync status on the order screen, and a combined Transactions tab with sync history plus on-demand import
* Historical import runs in background batches (Action Scheduler)
* High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility
* Bundled libraries are namespaced (PHP-Scoper) to avoid conflicts with other plugins
* WordPress.org coding-standards pass and security/SQL hardening

= 0.0.3 =
* Various plugin standards updates

= 0.0.2 =
* Fixes to bring the plugin up to WP standards

= 0.0.1 =
* The (beta) version of the Seller Ledger plugin for WooCommerce

== Upgrade Notice ==

N/A
