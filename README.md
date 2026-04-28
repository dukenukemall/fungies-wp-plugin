```
    ███████╗██╗   ██╗███╗   ██╗ ██████╗ ██╗███████╗███████╗
    ██╔════╝██║   ██║████╗  ██║██╔════╝ ██║██╔════╝██╔════╝
    █████╗  ██║   ██║██╔██╗ ██║██║  ███╗██║█████╗  ███████╗
    ██╔══╝  ██║   ██║██║╚██╗██║██║   ██║██║██╔══╝  ╚════██║
    ██║     ╚██████╔╝██║ ╚████║╚██████╔╝██║███████╗███████║
    ╚═╝      ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝ ╚═╝╚══════╝╚══════╝
              ╔═╗╔═╗╦═╗  ╦ ╦╔═╗╔═╗╔═╗╔═╗╔╦╗╔╦╗╔═╗╦═╗╔═╗╔═╗
              ╠╣ ║ ║╠╦╝  ║║║║ ║║ ║║  ║ ║║║║║║║║╣ ╠╦╝║  ║╣
              ╚  ╚═╝╩╚═  ╚╩╝╚═╝╚═╝╚═╝╚═╝╩ ╩╩ ╩╚═╝╩╚═╚═╝╚═╝
```

# Fungies for WooCommerce

> Connect your WooCommerce store to [Fungies.io](https://fungies.io) — sync products, accept payments through Fungies hosted checkout, and keep orders perfectly in sync.

---

## Overview

```
  ┌─────────────────────┐         ┌─────────────────────┐
  │   WooCommerce Store │         │    Fungies.io        │
  │                     │         │                      │
  │  ┌───────────────┐  │  Sync   │  ┌────────────────┐ │
  │  │   Products    │◄─┼─────────┼──│   Products     │ │
  │  └───────────────┘  │         │  └────────────────┘ │
  │                     │         │                      │
  │  ┌───────────────┐  │ Webhook │  ┌────────────────┐ │
  │  │    Orders     │◄─┼─────────┼──│   Payments     │ │
  │  └───────────────┘  │         │  └────────────────┘ │
  │                     │         │                      │
  │  ┌───────────────┐  │Redirect │  ┌────────────────┐ │
  │  │   Customer    │──┼─────────┼─►│ Hosted Chkout  │ │
  │  └───────────────┘  │         │  └────────────────┘ │
  └─────────────────────┘         └─────────────────────┘
```

Fungies acts as your **Merchant of Record** — handling payments, taxes, and compliance. WooCommerce is your **storefront**. This plugin bridges them seamlessly.

---

## Features

| Feature | Description |
|---------|-------------|
| API Key Management | Production + Staging keys in WC Settings |
| Sandbox / Staging Mode | Routes to `api.stage.fungies.net` |
| Two-Way Product Sync | Pull Fungies offers → WC products **and** push WC products → Fungies OneTimePayment offers |
| Auto-Sync on Save | Editing a WC product automatically updates the matching Fungies offer |
| Currency Validation | Auto-detects Fungies workspace currency; warns on mismatch |
| Multi-Item Checkout | Multiple cart items create a Fungies Checkout Element so all line items appear on the hosted checkout page |
| Detailed Sync Panel | Pull/push summaries + collapsible error list under the "Sync Now" button |
| Duplicate Protection | Products pushed to Fungies are never re-imported as duplicates on the next pull |
| Hosted Checkout | Redirect to Fungies checkout page |
| Webhook Integration | Real-time order sync |
| Order Creation | Auto from payment webhooks |
| Refund Handling | Auto status updates |
| Subscription Support | Create / Renew / Cancel |
| Post-Purchase Redirect | Returns customer to WC order page |
| WooCommerce Logging | Full audit trail |

---

## How It Works

1. **Configure** — Paste your Fungies API keys and Store URL in WooCommerce → Settings → Fungies
2. **Sync Products** — Click "Sync Now" or let the hourly cron pull OneTimePayment products automatically
3. **Customer Shops** — Customers browse your WC store and proceed to checkout
4. **Fungies Checkout** — Customer is redirected to Fungies hosted checkout to pay
5. **Payment Complete** — Fungies redirects customer back to your WooCommerce thank-you page
6. **Webhook Sync** — Fungies sends `payment_success` → plugin completes the WC order

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | ≥ 5.8 |
| WooCommerce | ≥ 6.0 |
| PHP | ≥ 7.4 |
| Fungies Account | [Sign up](https://fungies.io) |

---

## Installation

### Manual Upload

1. Download the plugin `.zip` from the [latest release](https://github.com/dukenukemall/fungies-wp-plugin/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Activate**

### From Source

```bash
cd wp-content/plugins/
git clone https://github.com/dukenukemall/fungies-wp-plugin.git
```

Then activate via **WordPress Admin → Plugins**.

---

## Configuration

### Step 1: Choose Your Environment

| Environment | API URL | Dashboard |
|---|---|---|
| **Production** | `https://api.fungies.io/v0` | [app.fungies.io](https://app.fungies.io) |
| **Sandbox** | `https://api.stage.fungies.net/v0` | [app.stage.fungies.net](https://app.stage.fungies.net) |

> **Important:** Production and staging are **completely separate**. API keys, products, and webhooks are all independent. Staging keys will NOT work against the production API, and vice versa.

### Step 2: Get Your API Keys

1. Log in to your Fungies Dashboard (production or staging)
2. Go to **Developers → API Keys**
3. Click **Generate API Key**
4. Copy your **Public Key** (`pub_...`) and **Secret Key** (`sec_...`)
5. Copy your **Webhook Secret** from **Developers → Webhooks**

### Step 3: Plugin Settings

Navigate to **WooCommerce → Settings → Fungies** and fill in:

| Setting | Value |
|---------|-------|
| Sandbox Mode | Check if using staging |
| Public Key | Your `pub_...` key |
| Secret Key | Your `sec_...` key |
| Webhook Secret | From Fungies webhook settings |
| **Fungies Store URL** | Your store URL (see Step 4) |

### Step 4: Match Store Currencies

> **Your WooCommerce store currency must match the currency set in your Fungies workspace.**

1. Check your Fungies currency in **Fungies Dashboard → Settings → General** (under "Currency")
2. Check your WooCommerce currency in **WooCommerce → Settings → General** (under "Currency options")
3. Make sure both are set to the same currency (e.g. both USD, both EUR)

If the currencies don't match, product prices will display incorrectly and checkout totals may be wrong.

### Step 5: Publish Your Fungies Store & Get the Store URL

> **Your Fungies store must be published for the hosted checkout to work.**

1. In the Fungies Dashboard, click **"Go to Store"** in the top menu
2. Make sure the store is **published** (not draft)
3. Copy the store URL — it looks like:
   - **Production:** `https://yourname.app.fungies.io`
   - **Staging:** `https://yourname.stage.fungies.net`
4. Paste it into the **Fungies Store URL** field in WooCommerce → Settings → Fungies
5. Click **Save Changes**

### Step 6: Configure Webhook in Fungies

1. Go to **Fungies Dashboard → Developers → Webhooks**
2. Add a new endpoint with the **Webhook URL** shown on the plugin settings page
   (e.g. `https://yoursite.com/wp-json/fungies/v1/webhook`)
3. Select the events: `payment_success`, `payment_failed`, `payment_refunded`, `subscription_created`, `subscription_interval`, `subscription_cancelled`

### Step 7: Configure Post-Purchase Redirect URL in Fungies

After a customer completes payment on Fungies, they need to be redirected back to your WooCommerce store. This is configured **store-wide** in the Fungies Dashboard.

1. Go to **Fungies Dashboard → Settings → Store → Checkout tab**
2. Scroll down to **"Success redirection settings"**
3. In the **Instant Redirect URL** field, paste the **Post-Purchase Redirect URL** shown on the plugin settings page:
   ```
   https://yoursite.com/?wc-api=fungies_return
   ```
4. In **URL Parameters**, add these system parameters from the dropdown:
   - **Order id** (appears as `fngs-order-id` in the URL)
   - **User email** (appears as `fngs-user-email` in the URL)

   > **Note:** The dropdown list shows human-readable names (e.g. "Order id", "User email"). Once selected, they are automatically converted to the correct URL parameters (`fngs-order-id`, `fngs-user-email`). The names in the dropdown may differ from the final parameter names -- this is expected.

5. The final redirect URL will look like:
   ```
   https://yoursite.com/?wc-api=fungies_return&fngs-order-id={fngs-order-id}&fngs-user-email={fngs-user-email}
   ```
6. Click **Save**

> **How it works:** After payment, Fungies redirects the customer to this URL with the Fungies order ID. The plugin looks up the matching WooCommerce order and sends the customer to the WooCommerce "Order Received" thank-you page.

See the [Fungies redirect documentation](https://help.fungies.io/for-saas-developers/redirecting-after-purchase) for more details on available system parameters.

### Step 8: Test the Full Flow

1. Enable **Sandbox Mode** and use staging keys
2. Click **Sync Now** to pull products
3. Add a product to cart and proceed to checkout
4. Select **Fungies Checkout** and place order
5. You should be redirected to the Fungies hosted checkout page
6. Pay using [Stripe test cards](https://docs.stripe.com/testing?testing-method=card-numbers)
7. After payment, you should be redirected back to the WooCommerce thank-you page
8. Check **WooCommerce → Orders** — the order should be marked as completed
9. Check logs at **WooCommerce → Status → Logs → `fungies-*`**

> When ready to go live, uncheck Sandbox Mode, switch to production keys and store URL, and Save Changes.

---

## Product Sync (Two-Way)

Every sync runs **two phases**: a **pull** (Fungies → WooCommerce) followed by a **push** (WooCommerce → Fungies). Both phases run on the same triggers:

| Trigger | When |
|---|---|
| **Sync Now button** | WooCommerce → Settings → Fungies |
| **WP-Cron** | Every hour |
| **Product save hook** | `woocommerce_update_product` / `woocommerce_new_product` (push only, debounced 5 s) |

### Pull — Fungies → WooCommerce

```
Fungies                                WooCommerce
───────                                ───────────
GET /v0/offers/list  ──────────►  for each offer:
                                    ├─ skip if already pushed from WC*
                                    ├─ find matching WC product by _fungies_offer_id
                                    ├─ create or update WC product:
                                    │    name, description, regular_price,
                                    │    featured image, _fungies_currency,
                                    │    _fungies_offer_id, _fungies_checkout_url
                                    └─ remove duplicates (one-time cleanup)
```

\* Offers whose ID is in any WC product's `_fungies_pushed_offer_id` meta are **skipped** to prevent re-importing products that originated in WC.

### Push — WooCommerce → Fungies

```
WooCommerce                                  Fungies
───────────                                  ───────
for each published WC product:
  ├─ skip if has _fungies_offer_id (originated in Fungies)
  ├─ assert WC currency == Fungies workspace currency  ──► error if mismatch
  ├─ build product body (name, description, cover, type=OneTimePayment, status=ACTIVE)
  ├─ build offer body (name, currency, price in major units)
  │
  ├─ if _fungies_pushed_product_id + _fungies_pushed_offer_id exist:
  │     PATCH /v0/products/{id}/update
  │     PATCH /v0/offers/{id}/update
  │     └─ on 404 "not found" ► clear stale IDs, fall through to create
  │
  └─ otherwise:
        POST /v0/products/create  ──► store _fungies_pushed_product_id
        POST /v0/offers/create    ──► store _fungies_pushed_offer_id
                                  ──► store _fungies_pushed_at
```

### Loop & duplicate prevention

| Mechanism | Purpose |
|---|---|
| `_fungies_offer_id` on WC product | Marks WC products that **came from Fungies** — skipped during push |
| `_fungies_pushed_offer_id` on WC product | Marks WC products that have been **pushed to Fungies** — skipped during pull |
| `Fungies_Product_Sync::$is_pulling` flag | Prevents `woocommerce_update_product` hook from firing while the pull phase writes to WC |
| `fungies_push_lock_<id>` 5 s transient | De-bounces rapid-fire WC product updates |
| `cleanup_pushed_duplicates()` SQL pass | Removes duplicates that existed before v2.1.5 |
| 404 → recreate fallback | Recovers from stale IDs when API keys point to a different Fungies workspace (v2.1.7) |

### Meta keys reference

| Meta key | Set during | Purpose |
|---|---|---|
| `_fungies_offer_id` | Pull | The Fungies offer ID this WC product mirrors |
| `_fungies_currency` | Pull | Currency the offer was priced in |
| `_fungies_checkout_url` | Pull | Pre-built single-offer hosted checkout URL |
| `_fungies_pushed_product_id` | Push | Fungies product ID for a WC-originated product |
| `_fungies_pushed_offer_id` | Push | Fungies offer ID for a WC-originated product |
| `_fungies_pushed_at` | Push | Timestamp of last successful push |

---

## Checkout Flow

```
  Customer          WooCommerce           Fungies             WooCommerce
  ────────          ───────────           ───────             ───────────
     │                   │                   │                     │
     │  1. Place Order   │                   │                     │
     │──────────────────►│                   │                     │
     │                   │                   │                     │
     │                   │ 2. Create pending  │                     │
     │                   │    order           │                     │
     │                   │                   │                     │
     │  3. Redirect to Fungies checkout      │                     │
     │◄──────────────────│                   │                     │
     │──────────────────────────────────────►│                     │
     │                   │                   │                     │
     │  4. Pay on Fungies│                   │                     │
     │──────────────────────────────────────►│                     │
     │                   │                   │                     │
     │                   │                   │ 5. Webhook           │
     │                   │                   │ payment_success      │
     │                   │                   │────────────────────►│
     │                   │                   │                     │
     │  6. Redirect back │                   │  Order completed     │
     │◄─────────────────────────────────────│                     │
     │──────────────────────────────────────────────────────────►│
     │                   │                   │                     │
     │  7. Thank-you page│                   │                     │
     │◄─────────────────────────────────────────────────────────│
```

---

## Checkout URL Generation

When a customer clicks **Place Order**, `Fungies_Payment_Gateway::process_payment()` delegates to `Fungies_Checkout_URL_Builder::build($order)`, which produces a different URL shape depending on **how many distinct offers** are in the cart.

### Step 1 — Collect offer IDs

For each line item the builder resolves a Fungies offer ID by checking, in order:

1. `_fungies_offer_id` (product was pulled **from** Fungies)
2. `_fungies_pushed_offer_id` (product was pushed **to** Fungies from WC)

Each unit (quantity) becomes one entry in the resulting `$offer_ids[]` array. Items without either meta key are logged and skipped.

### Step 2 — Build the URL

#### Single offer (1 distinct offer ID, qty 1)

No API call is needed — Fungies exposes a deterministic single-offer hosted checkout URL:

```
<store_url>/checkout/<offer_id>
  ?fngs-user-email=<billing_email>
  &fngs-customer-country=<billing_country>
```

Example:

```
https://azzeki.com/checkout/d8ef9d66-7d5f-4c30-88d6-4ff8fd612b88
  ?fngs-user-email=keviolf%40gmail.com
  &fngs-customer-country=PL
```

#### Multiple offers (2+, or qty > 1)

The builder calls the Fungies Checkout Element API to bundle all offers into a single hosted checkout session:

```
POST /v0/elements/checkout/create
{
  "name": "WC Order #22071",
  "offersIds": [
    "076b348f-804f-4c1f-bce3-5541d410992a",   ← Fungies-originated product
    "d8ef9d66-7d5f-4c30-88d6-4ff8fd612b88"    ← WC-originated, pushed product
  ]
}
```

The response's `data.checkoutElement.id` is stored on the WC order as `_fungies_checkout_element_id` and used to build:

```
<store_url>/checkout-element/<element_id>
  ?fngs-user-email=<billing_email>
  &fngs-customer-country=<billing_country>
```

Example:

```
https://azzeki.com/checkout-element/e3e1e78d-c31a-41f9-a058-b1ad7c9acbb9
  ?fngs-user-email=keviolf%40gmail.com
  &fngs-customer-country=PL
```

When the customer lands there, Fungies promotes the element into a checkout session and the URL becomes `…/checkout-element/<element_id>/checkout/<session_id>` — both products are visible in the order summary on the hosted page.

### Decision tree

```
                 Cart items
                     │
                     ▼
          collect offer IDs from
        _fungies_offer_id  →  _fungies_pushed_offer_id
                     │
            ┌────────┴────────┐
            │                 │
       0 offers           1+ offers
            │                 │
            ▼          ┌──────┴──────┐
   fall back to:       │             │
   _fungies_checkout_url   1 offer       2+ offers
   on any cart item        │             │
            │              ▼             ▼
            │     /checkout/{id}   POST /elements/checkout/create
            │                            │
            ▼                            ▼
   if still none →           /checkout-element/{element_id}
   wc order received URL              │
   (logged as warning)                │
                              store element_id on order
                              for traceability
```

### Why two URL shapes?

The single-offer URL is **stateless** — no API call, no rate-limit cost, no extra latency. The multi-offer flow has to use the Checkout Element endpoint because Fungies' single-offer URL only carries one offer ID. v2.1.6 introduced this split; before that, multi-item carts would silently lose every line item except the first.

---

## Webhook Events

| Fungies Event | WooCommerce Action |
|---|---|
| `payment_success` | Complete WC order, store Fungies metadata |
| `payment_failed` | Update order status to `failed` |
| `payment_refunded` | Create WC refund, set status to `refunded` |
| `subscription_created` | Store subscription ID in order meta |
| `subscription_interval` | Create renewal order |
| `subscription_cancelled` | Update subscription status in meta |

### Webhook Security

All incoming webhooks are verified using **HMAC-SHA256** signature validation. Duplicate events are filtered via idempotency key tracking.

---

## Order Metadata

When a payment succeeds, the following metadata is stored on the WC order:

| Meta Key | Description |
|---|---|
| `_fungies_order_id` | Fungies order UUID |
| `_fungies_order_number` | Fungies order number |
| `_fungies_payment_id` | Payment UUID |
| `_fungies_payment_type` | `one_time`, `subscription_initial`, etc. |
| `_fungies_subscription_id` | Subscription UUID (if applicable) |
| `_fungies_invoice_url` | Invoice PDF link |
| `_fungies_fee` | Fungies processing fee |
| `_fungies_tax` | Tax amount |

---

## FAQ

**Q: Does the Fungies store need to be published?**
A: Yes. The hosted checkout URL only works when your Fungies store is published. Go to the Fungies Dashboard and make sure your store is not in draft mode.

**Q: Why don't customers get redirected back after payment?**
A: You need to configure the **Instant Redirect URL** in Fungies Dashboard → Settings → Store → Checkout tab. See [Step 7](#step-7-configure-post-purchase-redirect-url-in-fungies) above.

**Q: Can I use this alongside other WC payment gateways?**
A: Yes. Fungies registers as a standard WC payment gateway. Customers can choose it at checkout alongside any other enabled gateways.

**Q: Which Fungies products are synced?**
A: Only **OneTimePayment** products and their offers are synced. Other product types (Digital Downloads, Subscriptions, Game Keys, etc.) are not imported. The product name and description from Fungies are used for the WooCommerce product listing.

**Q: How often do products sync automatically?**
A: Every hour via WP Cron. You can also trigger a manual sync anytime from the settings page.

**Q: How do I test without processing real payments?**
A: Enable **Sandbox Mode**, use staging keys from [app.stage.fungies.net](https://app.stage.fungies.net), and pay with [Stripe test cards](https://docs.stripe.com/testing?testing-method=card-numbers).

---

## Changelog

### 2.1.7
- **Fixed:** Push to Fungies now recovers from "Product not found" errors (e.g. after switching from staging to production API keys). Stale Fungies product/offer IDs are cleared and the product is recreated in the current workspace.
- **Fixed:** When a pushed offer is missing in the current workspace but the product still exists, the offer is recreated under the existing product instead of erroring out.
- **Refactor:** Extracted product/offer body builders into `Fungies_Product_Body` to keep the push class focused.

### 2.1.6
- **Fixed:** Multi-item carts now create a Fungies Checkout Element so **all** line items appear on the hosted checkout page (previously only the first item was sent).
- **Fixed:** Products pushed from WC to Fungies are now resolved at checkout — they were silently skipped before.
- **Added:** New `POST /v0/elements/checkout/create` integration in the API client.
- **Added:** WC order metadata `_fungies_checkout_element_id` for traceability of multi-item checkouts.
- **Refactor:** Extracted hosted checkout URL building into `Fungies_Checkout_URL_Builder`.

### 2.1.5
- **Fixed:** Duplicate WC products created when pulling offers we previously pushed from WooCommerce.
- **Added:** One-time cleanup that removes existing duplicates on the next sync.
- **Added:** Pull phase now skips offers that already have `_fungies_pushed_offer_id`.

### 2.1.4
- **Fixed:** HTTP 400 "Invalid input" on Fungies product/offer PATCH — the `id` field is now sent in the request body in addition to the URL path.
- **Improved:** Verbose request body logging in the API client.

### 2.1.3
- **Fixed:** HTTP 500 on `POST /v0/products/create` by sending the required `status: ACTIVE` field.
- **Fixed:** Offer prices are now sent in major currency units (no more accidental ×100).

### 2.1.2
- **Fixed:** Plugin zip path separators (forward slashes) for Linux-based WP hosts.
- **Fixed:** Versioned top-level folder inside the zip to avoid "destination folder already exists" on upload.

### 2.1.1
- **Fixed:** Fatal error on plugin install caused by Windows-style path separators in the zip.

### 2.1.0
- **Added:** Two-way product sync — push WC products to Fungies as OneTimePayment offers (name, description, price, featured image).
- **Added:** Auto-push to Fungies when a WC product is saved/updated.
- **Added:** Currency auto-detection and mismatch validation.
- **Added:** Detailed sync result panel under "Sync Now".
- **Added:** Loop guard so editing a Fungies-imported product in WC does not push back to Fungies.

### 2.0.3
- Security: webhook handler now rejects requests when the webhook secret is not configured.
- Security: escape and sanitize currency code in storefront price output.
- Security: use `esc_html(esc_url())` for non-href URL display in admin settings.

For older releases, see [`readme.txt`](readme.txt).

---

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
