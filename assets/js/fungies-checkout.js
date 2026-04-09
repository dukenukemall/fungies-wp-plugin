(function ($) {
  "use strict";

  if (typeof fungiesCheckout === "undefined") {
    return;
  }

  var settings = fungiesCheckout;

  function getBillingData() {
    return {
      email: $("#billing_email").val() || "",
      firstName: $("#billing_first_name").val() || "",
      lastName: $("#billing_last_name").val() || "",
      country: $("#billing_country").val() || "",
      state: $("#billing_state").val() || "",
      city: $("#billing_city").val() || "",
      zipCode: $("#billing_postcode").val() || "",
    };
  }

  function getCheckoutUrl() {
    if (settings.cartItems && settings.cartItems.length > 0) {
      return settings.cartItems[0].checkoutUrl || "";
    }
    return "";
  }

  function getItems() {
    return (settings.cartItems || []).map(function (item) {
      return { offerId: item.offerId, quantity: item.quantity };
    });
  }

  function openOverlayCheckout() {
    var checkoutUrl = getCheckoutUrl();
    if (!checkoutUrl) {
      console.error("[Fungies] No checkout URL found for cart items.");
      return;
    }

    console.log("[Fungies] Opening overlay checkout:", checkoutUrl);

    if (typeof Fungies !== "undefined" && Fungies.Checkout) {
      Fungies.Checkout.open({
        checkoutUrl: checkoutUrl,
        settings: { mode: "overlay" },
        items: getItems(),
        billingData: getBillingData(),
      });
    } else {
      console.error("[Fungies] Fungies SDK not loaded.");
    }
  }

  function initEmbeddedCheckout() {
    var container = document.getElementById("fungies-checkout-embed");
    if (!container) return;

    var checkoutUrl = getCheckoutUrl();
    if (!checkoutUrl) return;

    console.log("[Fungies] Initializing embedded checkout:", checkoutUrl);

    if (typeof Fungies !== "undefined" && Fungies.Checkout) {
      Fungies.Checkout.open({
        checkoutUrl: checkoutUrl,
        settings: { mode: "embed", frameTarget: "fungies-checkout-embed" },
        items: getItems(),
        billingData: getBillingData(),
      });
    }
  }

  if (settings.mode === "overlay") {
    $(document.body).on("checkout_place_order_fungies", function () {
      openOverlayCheckout();
      return false;
    });
  }

  if (settings.mode === "embedded") {
    $(document).ready(function () {
      initEmbeddedCheckout();

      $(document.body).on("updated_checkout", function () {
        initEmbeddedCheckout();
      });
    });
  }
})(jQuery);
