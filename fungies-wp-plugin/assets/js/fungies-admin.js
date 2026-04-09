(function ($) {
  "use strict";

  $(document).on("click", "#fungies-test-connection", function () {
    var $btn = $(this);
    var $result = $("#fungies-test-result");

    $btn.prop("disabled", true).text("Testing…");
    $result.text("");

    $.post(fungiesAdmin.ajaxUrl, {
      action: "fungies_test_connection",
      nonce: fungiesAdmin.nonce,
    })
      .done(function (resp) {
        $result
          .css("color", resp.success ? "green" : "red")
          .text(resp.data);
      })
      .fail(function () {
        $result.css("color", "red").text("Request failed.");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Test Connection");
      });
  });

  $(document).on("click", "#fungies-sync-products", function () {
    var $btn = $(this);
    var $result = $("#fungies-sync-result");

    $btn.prop("disabled", true).text("Syncing…");
    $result.text("");

    $.post(fungiesAdmin.ajaxUrl, {
      action: "fungies_sync_products",
      nonce: fungiesAdmin.nonce,
    })
      .done(function (resp) {
        if (resp.success) {
          $result.css("color", "green").text(resp.data.message);
        } else {
          $result.css("color", "red").text(resp.data);
        }
      })
      .fail(function () {
        $result.css("color", "red").text("Sync request failed.");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Sync Now");
      });
  });
})(jQuery);
