=== Fungies for WooCommerce ===
Contributors: fungies
Donate link: https://fungies.io
Tags: woocommerce, payments, checkout, digital products
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.0
Stable tag: 2.2.0
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

* **Two-Way Product Sync** — Pull OneTimePayment products from Fungies into WooCommerce AND push WooCommerce products to Fungies as OneTimePayment offers (name, description, price, featured image)
* **Auto-Sync on Product Save** — Editing a WooCommerce product automatically updates the matching offer in Fungies
* **Currency Validation** — Detects your Fungies workspace currency and warns if it differs from your WooCommerce store currency
* **Multi-Item Hosted Checkout** — Carts with multiple products create a Fungies Checkout Element so all line items appear on the hosted checkout page
* **Detailed Sync Panel** — Clear summary under "Sync Now" showing pull/push counts and per-product errors
* **Duplicate Protection** — Products pushed from WooCommerce to Fungies will not be re-imported as duplicates on the next pull
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

== Product Sync ==

Every sync runs two phases on the same triggers:

* **Pull (Fungies to WooCommerce)** — `GET /v0/offers/list`, then for each offer create or update a matching WooCommerce product (name, description, image, price, currency).
* **Push (WooCommerce to Fungies)** — for each published WooCommerce product, build a OneTimePayment product and offer body, then either `PATCH` an existing one or `POST` a new one.

= Triggers =

* **Sync Now button** in WooCommerce → Settings → Fungies
* **WP-Cron**, hourly
* **`woocommerce_update_product` / `woocommerce_new_product`** hooks (push only, debounced 5 seconds)

= Loop and duplicate prevention =

* `_fungies_offer_id` on a WooCommerce product marks it as Fungies-originated → skipped during push.
* `_fungies_pushed_offer_id` on a WooCommerce product marks it as already-pushed → skipped during pull.
* A runtime `is_pulling` flag prevents WooCommerce update hooks from firing while the pull writes to the database.
* A 5-second per-product transient lock debounces rapid-fire saves.
* If a `PATCH` returns 404 "Product not found" (e.g. after switching from staging to production keys), stale IDs are cleared and the product is recreated in the current workspace.

= Currency handling =

The plugin auto-detects the Fungies workspace currency. If your WooCommerce currency does not match, the push phase errors out with a clear message — products are never pushed at the wrong currency.

= Meta keys reference =

* `_fungies_offer_id` — Fungies offer ID this WooCommerce product mirrors (set on pull).
* `_fungies_currency` — currency the offer was priced in (set on pull).
* `_fungies_checkout_url` — pre-built single-offer hosted checkout URL (set on pull).
* `_fungies_pushed_product_id` — Fungies product ID for a WC-originated product (set on push).
* `_fungies_pushed_offer_id` — Fungies offer ID for a WC-originated product (set on push).
* `_fungies_pushed_at` — timestamp of last successful push.

== Checkout URL Generation ==

When a customer clicks **Place Order**, the plugin produces a different Fungies hosted checkout URL depending on how many distinct offers are in the cart.

= Step 1 — Collect offer IDs =

For each cart line item, the builder resolves a Fungies offer ID by checking, in order:

1. `_fungies_offer_id` (product was pulled **from** Fungies).
2. `_fungies_pushed_offer_id` (product was pushed **to** Fungies from WooCommerce).

Each unit (quantity) becomes one entry in the resulting offer-IDs array. Items without either meta key are logged and skipped.

= Step 2 — Build the URL =

**Single offer** (1 distinct offer ID, quantity 1) — no API call is needed. The plugin redirects directly to:

`<store_url>/checkout/<offer_id>?fngs-user-email=...&fngs-customer-country=...`

**Multiple offers** (2 or more, or quantity > 1) — the plugin calls `POST /v0/elements/checkout/create` with all collected offer IDs, then redirects to:

`<store_url>/checkout-element/<element_id>?fngs-user-email=...&fngs-customer-country=...`

The element ID is also stored on the WooCommerce order as `_fungies_checkout_element_id` for traceability. When the customer lands on the hosted checkout, Fungies promotes the element into a checkout session and the URL becomes `…/checkout-element/<element_id>/checkout/<session_id>` — every cart product is visible in the order summary.

= Why two URL shapes? =

The single-offer URL is stateless — no API call, no rate-limit cost, no extra latency. The multi-offer flow has to use the Checkout Element endpoint because Fungies' single-offer URL only carries one offer ID. Before v2.1.6, multi-item carts would silently lose every line item except the first.

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

= 2.2.0 =
* Feature: WooCommerce coupons are now synced to Fungies on every "Sync Now" run. Each coupon (`percent`, `fixed_cart`, `fixed_product`) is created or updated as a Fungies discount with the same code, amount, amount type, expiration date, and usage limit. The Sync panel reports a third "Coupons → Fungies" line with created / updated / error counts. Mapping is workspace-scoped (sandbox vs production) so toggling Sandbox Mode does not orphan the link, and re-running Sync skips coupons that are already in sync.

= 2.1.11 =
* Fix: Customers returning from Fungies hosted checkout on production now reliably land on the WooCommerce order-received ("thank you") page instead of being bounced back to the checkout/cart. Root cause was a race between the Fungies `payment_success` webhook and the customer's redirect: production webhooks fire fast enough that by the time the user lands on `?wc-api=fungies_return`, the order has already moved from `pending` to `processing`, and the old fallback only matched `pending` orders.
* Added `Fungies_Return_Resolver` with three layered recovery strategies, in order of reliability:
  1. WC session — the WC order id is now stashed in the session at redirect time, so the return handler can recover it deterministically without depending on the webhook.
  2. Broadened email fallback — now matches `pending`, `on-hold`, `processing`, `completed` instead of `pending` only.
  3. Brief poll (~3s) for the meta or matching order, in case the webhook is landing right at that moment.
* `_fungies_order_id` post meta is now linked at return time using the `fngs-order-id` URL param, so subsequent webhook calls always match the existing order via meta and never create duplicate orphan orders.

= 2.1.10 =
* Build: Added `build.ps1` that produces the WordPress-ready zip via `git archive` and refuses to ship if any entry uses backslash separators. Locks in the v2.1.9 packaging fix so we can never regress to the v2.1.8 broken-zip bug again.
* No runtime code changes — same plugin code as v2.1.9.

= 2.1.9 =
* Fixed: Plugin zip path separators (forward slashes) for Linux-based WordPress hosts. The 2.1.8 zip was built with PowerShell `Compress-Archive` which uses backslashes inside the zip, causing "Plugin file does not exist" errors on Linux hosts. v2.1.9 ships the same code as 2.1.8 in a properly-built zip.

= 2.1.8 =
* Fixed: Toggling Sandbox Mode (or rotating API keys) no longer creates duplicate products in the destination workspace. Pushed product/offer IDs are now stored per workspace (`_fungies_pushed_*__<workspace_hash>`), so the original mapping in the previous workspace is preserved on environment switch.
* Added: `Fungies_Workspace_Meta` helper that scopes all push-side post meta by a hash of the active secret key.
* Migration: Legacy unscoped `_fungies_pushed_*` meta is read as a fallback and silently migrated to the active workspace on the next successful push.
* Updated: `Sync Now` pull-deduplication and one-time cleanup queries now consider both legacy and workspace-scoped meta keys.

= 2.1.7 =
* Fixed: Push to Fungies now recovers from "Product not found" errors (e.g. after switching from staging to production API keys). Stale Fungies product/offer IDs are cleared and the product is recreated in the current workspace.
* Fixed: When a pushed offer is missing in the current workspace but the product still exists, the offer is recreated under the existing product instead of erroring out.
* Refactor: Extracted product/offer body builders into `Fungies_Product_Body` to keep the push class focused.

= 2.1.6 =
* Fixed: Multi-item carts now create a Fungies Checkout Element so all line items appear on the hosted checkout page (previously only the first item was sent)
* Fixed: Products pushed from WooCommerce to Fungies (`_fungies_pushed_offer_id`) are now resolved at checkout — they were silently skipped before
* Added: New `POST /v0/elements/checkout/create` integration in the API client
* Added: WC order metadata `_fungies_checkout_element_id` for traceability of multi-item checkouts
* Refactor: Extracted hosted checkout URL building into `Fungies_Checkout_URL_Builder`

= 2.1.5 =
* Fixed: Duplicate WooCommerce products created when pulling offers we previously pushed from WooCommerce
* Added: One-time cleanup that removes existing duplicate WC products on the next sync
* Added: Pull phase now skips offers with a `_fungies_pushed_offer_id` to prevent re-import

= 2.1.4 =
* Fixed: HTTP 400 "Invalid input" on Fungies product/offer PATCH — the `id` field is now included in the PATCH request body in addition to the URL path
* Improved: Verbose request body logging in the API client for easier troubleshooting

= 2.1.3 =
* Fixed: HTTP 500 on `POST /v0/products/create` by sending the required `status: ACTIVE` field
* Fixed: Offer prices are now sent in major currency units (no more accidental ×100 multiplication)

= 2.1.2 =
* Fixed: Plugin zip path separators (forward slashes) for Linux-based WordPress hosts
* Fixed: Versioned top-level folder inside the zip to avoid "destination folder already exists" errors

= 2.1.1 =
* Fixed: Fatal error on plugin install on some Linux hosts caused by Windows-style path separators in the zip

= 2.1.0 =
* Added: Two-way product sync — push WooCommerce products to Fungies as OneTimePayment offers (name, description, price, featured image)
* Added: Auto-push to Fungies when a WooCommerce product is saved/updated
* Added: Currency auto-detection of the Fungies workspace currency with mismatch validation
* Added: Detailed sync result panel under the "Sync Now" button (pull/push summaries + collapsible error list)
* Added: Loop guard so editing a Fungies-imported product in WooCommerce does not push back to Fungies

= 2.0.3 =
* Security: Webhook handler now rejects requests when webhook secret is not configured
* Security: Escape and sanitize currency code in storefront price output
* Security: Use esc_html(esc_url()) for non-href URL display in admin settings

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

= 2.1.7 =
Fixes "Product not found" errors when pushing to a different Fungies workspace (e.g. switching staging → production). Recommended update.

= 2.1.6 =
Fixes multi-item Fungies hosted checkout — all cart products are now sent to Fungies. Recommended update.

= 2.1.5 =
Fixes duplicate WooCommerce products that appeared on sync. Recommended update.

= 2.1.4 =
Fixes Fungies product/offer update errors (HTTP 400 Invalid input).

= 2.1.3 =
Fixes Fungies product creation errors and price unit handling.

= 2.1.0 =
Adds two-way product sync — WooCommerce products are pushed to Fungies. Make sure your WooCommerce currency matches your Fungies workspace currency before syncing.

= 2.0.3 =
Security hardening: webhook signature enforcement, escaping fixes.

= 2.0.2 =
WordPress.org guideline compliance: third-party service disclosure, admin notice fix, cleanup.

= 2.0.1 =
Recommended update with improved webhook handling and bug fixes.

= 2.0.0 =
Major update with WooCommerce Blocks and HPOS support. Review your settings after updating.
