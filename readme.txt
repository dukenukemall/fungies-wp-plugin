=== Fungies for WooCommerce ===
Contributors: fungies
Tags: woocommerce, payments, checkout, digital products, merchant of record
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.0
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WooCommerce store to Fungies.io — sync products, accept payments through Fungies hosted checkout, and keep orders perfectly in sync.

== Description ==

**Fungies for WooCommerce** lets you sell digital products through your WooCommerce store while [Fungies.io](https://fungies.io) handles payments, taxes, and compliance as your **Merchant of Record**.

You keep full control of your storefront. Fungies takes care of the hard parts — payment processing, tax collection, invoicing, and regulatory compliance — so you can focus on your products.

= How It Works =

1. **Connect** — Paste your Fungies API keys in WooCommerce → Settings → Fungies
2. **Sync** — Your Fungies products are automatically imported into WooCommerce
3. **Sell** — Customers browse your store and check out via Fungies hosted checkout
4. **Get Paid** — Fungies processes the payment and sends a webhook to complete the WooCommerce order
5. **Stay in Sync** — Orders, refunds, and subscriptions are kept up to date automatically

= Features =

* **Automatic Product Sync** — OneTimePayment products from Fungies are synced into WooCommerce on an hourly schedule, or manually with one click
* **Hosted Checkout** — Customers are redirected to a secure Fungies checkout page to complete payment, then returned to your WooCommerce thank-you page
* **Real-Time Order Sync** — Webhooks keep WooCommerce orders in sync with Fungies payments, including completions, failures, and refunds
* **Subscription Support** — Handles subscription creation, renewal, and cancellation events from Fungies
* **Sandbox / Staging Mode** — Test the full flow with staging API keys and Stripe test cards before going live
* **Secure Webhooks** — All incoming webhooks are verified with HMAC-SHA256 signatures and protected against duplicate processing
* **WooCommerce Blocks Compatible** — Works with both the classic checkout and the new WooCommerce block-based checkout
* **HPOS Compatible** — Fully compatible with WooCommerce High-Performance Order Storage (custom order tables)
* **Detailed Logging** — Full audit trail in WooCommerce → Status → Logs for easy debugging
* **Dashboard Widget** — See your sync status at a glance from the WordPress dashboard

= Why Fungies? =

Fungies acts as your Merchant of Record, which means:

* **No payment gateway setup** — Fungies handles Stripe, PayPal, and more
* **Automatic tax collection** — Sales tax, VAT, and GST handled globally
* **Invoicing & compliance** — Professional invoices generated for every transaction
* **Fraud protection** — Built-in fraud detection and chargeback handling

= Use Cases =

* Sell software, ebooks, courses, or any digital product
* Add a WooCommerce storefront to your existing Fungies catalog
* Let Fungies handle payments and taxes while you manage the shopping experience

= Third-Party Service: Fungies.io =

This plugin connects your WooCommerce store to the **Fungies.io** platform, an external third-party service operated by Fungies Inc.

**What data is sent to Fungies:**

* During **product sync**, the plugin sends your API keys to the Fungies API to retrieve your product catalog.
* During **checkout**, customers are redirected to the Fungies hosted checkout page. Their email address and country code are passed as URL parameters.
* During **webhook processing**, Fungies sends order and payment data (order ID, payment status, amounts, subscription details) to your WordPress site.

**Service endpoints used:**

* Production API: `https://api.fungies.io/v0`
* Staging API: `https://api.stage.fungies.net/v0`
* Hosted checkout: `https://{your-store}.app.fungies.io`

**A Fungies account is required** to use this plugin. You can sign up at [fungies.io](https://fungies.io).

**Legal documents:**

* [General Terms of Use](https://help.fungies.io/legal/general-terms-of-use)
* [SaaS Terms of Use](https://help.fungies.io/legal/saas-terms-of-use)
* [Privacy Policy](https://help.fungies.io/legal/privacy-policy)
* [Cookies and Tracking](https://help.fungies.io/legal/cookies-and-tracking)

== Installation ==

= Automatic Installation =

1. Go to **WordPress Admin → Plugins → Add New**
2. Search for **"Fungies for WooCommerce"**
3. Click **Install Now**, then **Activate**

= Manual Installation =

1. Download the plugin `.zip` file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Click **Activate**

= Setup =

After activation:

1. Go to **WooCommerce → Settings → Fungies**
2. Enter your Fungies API keys (Public Key, Secret Key, Webhook Secret)
3. Enter your published Fungies Store URL
4. Configure the webhook endpoint in your [Fungies Dashboard](https://app.fungies.io) → Developers → Webhooks
5. Configure the post-purchase redirect URL in Fungies Dashboard → Settings → Store → Checkout tab
6. Click **Sync Now** to import your products

For detailed setup instructions, see the [full documentation](https://help.fungies.io).

== Frequently Asked Questions ==

= Does the Fungies store need to be published? =

Yes. The hosted checkout URL only works when your Fungies store is published. Go to the Fungies Dashboard and make sure your store is not in draft mode.

= Which Fungies products are synced? =

Only **OneTimePayment** products and their offers are synced into WooCommerce. Other product types (Digital Downloads, Subscriptions, Game Keys, etc.) are not imported. Product names and descriptions from Fungies are used for the WooCommerce product listings.

= Can I use this alongside other WooCommerce payment gateways? =

Yes. Fungies registers as a standard WooCommerce payment gateway. Customers can choose it at checkout alongside any other enabled gateways.

= How often do products sync automatically? =

Every hour via WordPress Cron. You can also trigger a manual sync anytime from the Fungies settings page.

= How do I test without processing real payments? =

Enable **Sandbox Mode** in the plugin settings, use staging keys from [app.stage.fungies.net](https://app.stage.fungies.net), and pay with [Stripe test cards](https://docs.stripe.com/testing?testing-method=card-numbers).

= Why don't customers get redirected back after payment? =

You need to configure the **Instant Redirect URL** in Fungies Dashboard → Settings → Store → Checkout tab. Use the Post-Purchase Redirect URL shown on the plugin settings page and add the Order ID and User Email URL parameters.

= Do I need separate API keys for sandbox and production? =

Yes. Production and staging environments in Fungies are completely separate. API keys, products, and webhooks are independent — staging keys will not work against the production API, and vice versa.

= What webhook events should I enable? =

Enable these events in your Fungies webhook configuration: `payment_success`, `payment_failed`, `payment_refunded`, `subscription_created`, `subscription_interval`, and `subscription_cancelled`.

= What order metadata is stored? =

When a payment succeeds, the plugin stores the Fungies order ID, order number, payment ID, payment type, subscription ID (if applicable), invoice URL, processing fee, and tax amount on the WooCommerce order.

= Does it work with WooCommerce Blocks checkout? =

Yes. The plugin is fully compatible with both the classic WooCommerce checkout and the new block-based cart and checkout experience.

== Screenshots ==

1. Plugin settings page in WooCommerce → Settings → Fungies
2. Product sync status on the WordPress dashboard widget
3. Fungies order metadata displayed on the WooCommerce order edit screen
4. Fungies checkout option at the WooCommerce checkout page

== Changelog ==

= 2.0.2 =
* Added third-party service disclosure for WordPress.org guideline compliance
* Fixed admin notice to use modern WordPress notice pattern
* Removed unused template and script files

= 2.0.1 =
* Improved webhook signature validation
* Enhanced order sync reliability
* Bug fixes and stability improvements

= 2.0.0 =
* Major rewrite with improved architecture
* Added WooCommerce Blocks checkout support
* Added HPOS (High-Performance Order Storage) compatibility
* Added dashboard sync status widget
* Added subscription event handling (create, renew, cancel)
* Added automatic order creation from webhooks
* Added idempotency protection for duplicate webhook events
* Improved product sync with hourly cron scheduling
* Improved currency handling for block-based checkout
* Enhanced admin UI with connection test and manual sync

= 1.0.0 =
* Initial release
* Product sync from Fungies to WooCommerce
* Hosted checkout redirect
* Webhook-based order completion
* Sandbox mode support

== Upgrade Notice ==

= 2.0.2 =
WordPress.org guideline compliance: third-party service disclosure, admin notice fix, cleanup.

= 2.0.1 =
Recommended update with improved webhook handling and bug fixes.

= 2.0.0 =
Major update with WooCommerce Blocks and HPOS support. Review your settings after updating.
