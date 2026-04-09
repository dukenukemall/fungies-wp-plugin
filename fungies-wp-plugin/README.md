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

> Connect your WooCommerce store to [Fungies.io](https://fungies.io) — sync products, accept payments through Fungies checkout, and keep orders perfectly in sync.

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
  │  ┌───────────────┐  │ Checkout│  ┌────────────────┐ │
  │  │   Customer    │──┼─────────┼─►│ Fungies Chkout │ │
  │  └───────────────┘  │         │  └────────────────┘ │
  └─────────────────────┘         └─────────────────────┘
```

Fungies acts as your **Merchant of Record** — handling payments, taxes, and compliance. WooCommerce is your **storefront**. This plugin bridges them seamlessly.

---

## Features

```
  ╔══════════════════════════════════════════════════════════╗
  ║                    FEATURE MATRIX                       ║
  ╠══════════════════════════════════════════════════════════╣
  ║  ✓  API Key Management          (Admin Settings)        ║
  ║  ✓  Product Sync                (Fungies → WooCommerce) ║
  ║  ✓  Overlay Checkout            (Popup Modal)           ║
  ║  ✓  Embedded Checkout           (Inline on Page)        ║
  ║  ✓  Hosted Checkout             (Redirect to Fungies)   ║
  ║  ✓  Webhook Integration         (Real-time Sync)        ║
  ║  ✓  Order Creation              (Auto from Webhooks)    ║
  ║  ✓  Refund Handling             (Auto Status Updates)   ║
  ║  ✓  Subscription Support        (Create/Renew/Cancel)   ║
  ║  ✓  Dashboard Widget            (Sync Status at Glance) ║
  ║  ✓  WooCommerce Logging         (Full Audit Trail)      ║
  ╚══════════════════════════════════════════════════════════╝
```

---

## How It Works

```
  ┌──────┐    ┌──────────┐    ┌─────────┐    ┌──────────┐    ┌───────────┐
  │ Shop │    │    WC     │    │ Fungies │    │ Fungies  │    │    WC     │
  │ Owner│    │ Checkout  │    │   SDK   │    │   API    │    │  Orders   │
  └──┬───┘    └────┬─────┘    └────┬────┘    └────┬─────┘    └─────┬─────┘
     │             │               │              │                │
     │ 1. Configure API Keys      │              │                │
     │─────────────┼───────────────┼──────────────┤                │
     │             │               │              │                │
     │ 2. Sync     │               │              │                │
     │─────────────┼───────────────┼─────────────►│                │
     │             │               │              │                │
     │             │  3. Customer  │              │                │
     │             │    checks out │              │                │
     │             │──────────────►│              │                │
     │             │               │              │                │
     │             │               │ 4. Process   │                │
     │             │               │    payment   │                │
     │             │               │─────────────►│                │
     │             │               │              │                │
     │             │               │              │ 5. Webhook     │
     │             │               │              │───────────────►│
     │             │               │              │                │
     │             │               │              │  Order Created │
     │             │               │              │◄───────────────│
     │             │               │              │                │
  ───┴─────────────┴───────────────┴──────────────┴────────────────┴───
```

1. **Configure** — Paste your Fungies API keys in WooCommerce → Settings → Fungies
2. **Sync Products** — Click "Sync Now" or let the hourly cron pull products automatically
3. **Customer Shops** — Customers browse your WC store and proceed to checkout
4. **Fungies Checkout** — Payment is handled by Fungies (overlay, embedded, or redirect)
5. **Webhook Sync** — Fungies sends `payment_success` → plugin creates/completes the WC order

---

## Requirements

| Requirement   | Version  |
|---------------|----------|
| WordPress     | ≥ 5.8    |
| WooCommerce   | ≥ 6.0    |
| PHP           | ≥ 7.4    |
| Fungies Account | [Sign up](https://fungies.io) |

---

## Installation

### Manual Upload

1. Download the plugin as a `.zip` file
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

### Step 1: Get Your API Keys

1. Log in to your [Fungies Dashboard](https://app.fungies.io)
2. Navigate to **Settings → API Keys**
3. Copy your **Public Key** (`pub_...`) and **Secret Key** (`sec_...`)
4. Copy your **Webhook Secret** from the Webhooks settings

### Step 2: Plugin Settings

Navigate to **WooCommerce → Settings → Fungies**:

```
  ┌─────────────────────────────────────────────────────┐
  │              Fungies Settings                       │
  ├─────────────────────────────────────────────────────┤
  │                                                     │
  │  Public Key:     [pub_xxxxxxxxxxxxx           ]     │
  │  Secret Key:     [••••••••••••••••••          ]     │
  │  Webhook Secret: [••••••••••••••••••          ]     │
  │                                                     │
  │  Checkout Mode:  [ Overlay (popup)          ▾]     │
  │  Sandbox Mode:   [✓]                                │
  │                                                     │
  │  Webhook URL:    https://yoursite.com/wp-json/      │
  │                  fungies/v1/webhook                  │
  │                                                     │
  │  [Test Connection]  ✓ Connection successful!        │
  │  [  Sync Now     ]  Synced 12 products              │
  │                                                     │
  └─────────────────────────────────────────────────────┘
```

### Step 3: Configure Webhook in Fungies

1. Go to your Fungies Dashboard → **Webhooks**
2. Add a new endpoint with the URL shown on the settings page
3. Select the events: `payment_success`, `payment_failed`, `payment_refunded`, `subscription_created`, `subscription_interval`, `subscription_cancelled`

---

## Checkout Modes

| Mode | Description | How It Works |
|------|-------------|-------------|
| **Overlay** | Popup modal | Intercepts WC "Place Order" button, opens Fungies checkout as a popup overlay |
| **Embedded** | Inline on page | Renders Fungies checkout directly within the WC checkout page |
| **Hosted** | Redirect | Sends the customer to a Fungies-hosted checkout page with billing data prefilled |

---

## Plugin Architecture

```
  fungies-wp-plugin/
  │
  ├── fungies-wp-plugin.php              Main entry point
  │
  ├── includes/
  │   ├── class-fungies-loader.php       Hook orchestrator
  │   ├── class-fungies-api-client.php   Fungies REST API wrapper
  │   ├── class-fungies-admin-settings.php  WC Settings tab
  │   ├── class-fungies-product-sync.php    Product sync engine
  │   ├── class-fungies-payment-gateway.php WC Payment Gateway
  │   ├── class-fungies-checkout.php        Frontend SDK integration
  │   ├── class-fungies-webhook-handler.php Webhook endpoint + verification
  │   ├── class-fungies-order-sync.php      Event → WC Order routing
  │   ├── class-fungies-order-metabox.php   Order admin meta box
  │   ├── class-fungies-product-metabox.php Product admin meta box
  │   └── class-fungies-dashboard-widget.php  WP Dashboard widget
  │
  ├── assets/
  │   ├── js/
  │   │   ├── fungies-admin.js           Admin AJAX handlers
  │   │   └── fungies-checkout.js        Frontend checkout logic
  │   └── css/
  │       └── fungies-admin.css          Admin styles
  │
  └── templates/
      └── checkout-button.php            WC checkout button override
```

---

## Product Sync Mapping

```
  ┌──────────────────────┐          ┌──────────────────────┐
  │     FUNGIES           │          │    WOOCOMMERCE        │
  ├──────────────────────┤          ├──────────────────────┤
  │ Product.name         │────────►│ post_title            │
  │ Product.description  │────────►│ post_content          │
  │ Product.id           │────────►│ _fungies_product_id   │
  │ Product.checkoutUrl  │────────►│ _fungies_checkout_url │
  │ Product.imageUrl     │────────►│ Featured image        │
  │ Offer.id             │────────►│ _fungies_offer_id     │
  │ Offer.price          │────────►│ _price / _sale_price  │
  │ Offer.originalPrice  │────────►│ _regular_price        │
  │ Offer.currency       │────────►│ _fungies_currency     │
  └──────────────────────┘          └──────────────────────┘
```

---

## Webhook Events

| Fungies Event | WooCommerce Action |
|---|---|
| `payment_success` | Create/complete WC order, store Fungies metadata |
| `payment_failed` | Update order status to `failed` |
| `payment_refunded` | Create WC refund record, set status to `refunded` |
| `subscription_created` | Store subscription ID in order meta |
| `subscription_interval` | Create renewal order |
| `subscription_cancelled` | Update subscription status in meta |

### Webhook Security

All incoming webhooks are verified using **HMAC-SHA256** signature validation against your configured webhook secret. Duplicate events are filtered via idempotency key tracking.

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
| `_fungies_event_id` | Idempotency key |
| `_fungies_invoice_url` | Invoice PDF link |
| `_fungies_fee` | Fungies processing fee |
| `_fungies_tax` | Tax amount |

---

## WooCommerce Logging

All API calls, webhook events, and sync operations are logged via WooCommerce's built-in logger. View logs at:

**WooCommerce → Status → Logs → `fungies-*`**

---

## FAQ

**Q: Does this replace WooCommerce payments entirely?**
A: Yes. When enabled, Fungies handles all payment processing as the Merchant of Record, including taxes and compliance.

**Q: Can I use this alongside other WC payment gateways?**
A: Yes. Fungies registers as a standard WC payment gateway. Customers can choose it at checkout alongside any other enabled gateways.

**Q: How often do products sync automatically?**
A: Every hour via WP Cron. You can also trigger a manual sync anytime from the settings page.

**Q: What happens if a webhook fails?**
A: The plugin returns appropriate HTTP status codes. Configure retry logic in your Fungies webhook settings for resilience.

---

## Development

```bash
# Clone
git clone https://github.com/dukenukemall/fungies-wp-plugin.git

# Symlink into local WP install
ln -s /path/to/fungies-wp-plugin /path/to/wordpress/wp-content/plugins/fungies-wp-plugin

# Activate
wp plugin activate fungies-wp-plugin
```

---

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

---

```
  ╔═══════════════════════════════════════════════════╗
  ║   Built with ♥ for the Fungies + WooCommerce     ║
  ║   community. Questions? Visit fungies.io/help     ║
  ╚═══════════════════════════════════════════════════╝
```
