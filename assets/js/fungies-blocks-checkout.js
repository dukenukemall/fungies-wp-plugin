const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { decodeEntities } = window.wp.htmlEntities;
const { getSetting } = window.wc.wcSettings;
const el = window.wp.element.createElement;

const settings = getSetting("fungies_data", {});
const title = decodeEntities(settings.title || "Fungies Checkout");
const description = decodeEntities(
  settings.description ||
    "Pay securely via Fungies. All major payment methods accepted."
);
const iconUrl = settings.icon || "";

const FungiesLabel = () => {
  return el(
    "span",
    { style: { display: "flex", alignItems: "center", gap: "8px" } },
    iconUrl
      ? el("img", {
          src: iconUrl,
          alt: "Fungies",
          style: { width: "24px", height: "24px", borderRadius: "4px" },
        })
      : null,
    title
  );
};

const FungiesContent = () => {
  return el("span", null, description);
};

registerPaymentMethod({
  name: "fungies",
  label: el(FungiesLabel, null),
  content: el(FungiesContent, null),
  edit: el(FungiesContent, null),
  canMakePayment: () => true,
  paymentMethodId: "fungies",
  ariaLabel: title,
  supports: {
    features: settings.supports || ["products"],
  },
});
