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
| Product Sync | Fungies offers → WooCommerce products |
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
2. **Sync Products** — Click "Sync Now" or let the hourly cron pull products automatically
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

### Step 4: Publish Your Fungies Store & Get the Store URL

> **Your Fungies store must be published for the hosted checkout to work.**

1. In the Fungies Dashboard, click **"Go to Store"** in the top menu
2. Make sure the store is **published** (not draft)
3. Copy the store URL — it looks like:
   - **Production:** `https://yourname.app.fungies.io`
   - **Staging:** `https://yourname.stage.fungies.net`
4. Paste it into the **Fungies Store URL** field in WooCommerce → Settings → Fungies
5. Click **Save Changes**

### Step 5: Configure Webhook in Fungies

1. Go to **Fungies Dashboard → Developers → Webhooks**
2. Add a new endpoint with the **Webhook URL** shown on the plugin settings page
   (e.g. `https://yoursite.com/wp-json/fungies/v1/webhook`)
3. Select the events: `payment_success`, `payment_failed`, `payment_refunded`, `subscription_created`, `subscription_interval`, `subscription_cancelled`

### Step 6: Configure Post-Purchase Redirect URL in Fungies

After a customer completes payment on Fungies, they need to be redirected back to your WooCommerce store. This is configured **store-wide** in the Fungies Dashboard.

1. Go to **Fungies Dashboard → Settings → Store → Checkout tab**
2. Scroll down to **"Success redirection settings"**
3. In the **Instant Redirect URL** field, paste the **Post-Purchase Redirect URL** shown on the plugin settings page:
   ```
   https://yoursite.com/?wc-api=fungies_return
   ```
4. In **URL Parameters**, add these system parameters:
   - **`fngs-order-id`** — so Fungies appends the order ID to the redirect
   - **`fngs-user-email`** — so Fungies appends the customer email to the redirect
5. The final redirect URL will look like:
   ```
   https://yoursite.com/?wc-api=fungies_return&fngs-order-id={fngs-order-id}&fngs-user-email={fngs-user-email}
   ```
6. Click **Save**

> **How it works:** After payment, Fungies redirects the customer to this URL with the Fungies order ID. The plugin looks up the matching WooCommerce order and sends the customer to the WooCommerce "Order Received" thank-you page.

See the [Fungies redirect documentation](https://help.fungies.io/for-saas-developers/redirecting-after-purchase) for more details on available system parameters.

### Step 7: Test the Full Flow

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
A: You need to configure the **Instant Redirect URL** in Fungies Dashboard → Settings → Store → Checkout tab. See [Step 6](#step-6-configure-post-purchase-redirect-url-in-fungies) above.

**Q: Can I use this alongside other WC payment gateways?**
A: Yes. Fungies registers as a standard WC payment gateway. Customers can choose it at checkout alongside any other enabled gateways.

**Q: How often do products sync automatically?**
A: Every hour via WP Cron. You can also trigger a manual sync anytime from the settings page.

**Q: How do I test without processing real payments?**
A: Enable **Sandbox Mode**, use staging keys from [app.stage.fungies.net](https://app.stage.fungies.net), and pay with [Stripe test cards](https://docs.stripe.com/testing?testing-method=card-numbers).

---

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
