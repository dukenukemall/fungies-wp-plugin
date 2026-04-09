( function () {
  "use strict";

  var wcBlocksRegistry = window.wc && window.wc.wcBlocksRegistry;
  var wcSettings = window.wc && window.wc.wcSettings;
  var wpElement = window.wp && window.wp.element;
  var wpHtmlEntities = window.wp && window.wp.htmlEntities;

  if ( ! wcBlocksRegistry || ! wcSettings || ! wpElement ) {
    console.error( "[Fungies] Block checkout globals missing:", {
      wcBlocksRegistry: !! wcBlocksRegistry,
      wcSettings: !! wcSettings,
      wpElement: !! wpElement,
    } );
    return;
  }

  var el = wpElement.createElement;
  var settings = wcSettings.getSetting( "fungies_data", null );

  if ( ! settings ) {
    console.error( "[Fungies] fungies_data not found in wcSettings — is_active() likely returned false" );
    return;
  }

  var decode = wpHtmlEntities ? wpHtmlEntities.decodeEntities : function ( s ) { return s; };
  var title = decode( settings.title || "Fungies Checkout" );
  var description = decode( settings.description || "Pay securely via Fungies. All major payment methods accepted." );
  var iconUrl = settings.icon || "";
  var features = ( Array.isArray( settings.supports ) && settings.supports.length ) ? settings.supports : [ "products" ];

  var Label = function () {
    if ( iconUrl ) {
      return el(
        "span",
        { style: { display: "flex", alignItems: "center", gap: "8px" } },
        el( "img", {
          src: iconUrl,
          alt: "Fungies",
          style: { width: "24px", height: "24px", borderRadius: "4px" },
        } ),
        title
      );
    }
    return el( "span", null, title );
  };

  var Content = function () {
    return el( "span", null, description );
  };

  wcBlocksRegistry.registerPaymentMethod( {
    name: "fungies",
    label: el( Label, null ),
    content: el( Content, null ),
    edit: el( Content, null ),
    canMakePayment: function () { return true; },
    paymentMethodId: "fungies",
    ariaLabel: title,
    supports: {
      features: features,
    },
  } );

  console.log( "[Fungies] Payment method registered for block checkout", { features: features } );
} )();
