const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { decodeEntities } = window.wp.htmlEntities;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting("fungies_data", {});
const title = decodeEntities(settings.title || "Fungies Checkout");
const description = decodeEntities(
  settings.description ||
    "Pay securely via Fungies. All major payment methods accepted."
);

const FungiesLabel = () => {
  return window.wp.element.createElement("span", null, title);
};

const FungiesContent = () => {
  return window.wp.element.createElement("span", null, description);
};

registerPaymentMethod({
  name: "fungies",
  label: window.wp.element.createElement(FungiesLabel, null),
  content: window.wp.element.createElement(FungiesContent, null),
  edit: window.wp.element.createElement(FungiesContent, null),
  canMakePayment: () => true,
  ariaLabel: title,
  supports: {
    features: settings.supports || ["products"],
  },
});
