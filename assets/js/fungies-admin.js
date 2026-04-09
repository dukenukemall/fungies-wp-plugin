(function ($) {
  "use strict";

  function toggleEnvFields() {
    var isSandbox = $("#fungies_sandbox_mode").is(":checked");

    $('[data-env="production"]').each(function () {
      $(this).prop("disabled", isSandbox);
      $(this).closest("tr").css("opacity", isSandbox ? 0.4 : 1);
    });

    $('[data-env="staging"]').each(function () {
      $(this).prop("disabled", !isSandbox);
      $(this).closest("tr").css("opacity", isSandbox ? 1 : 0.4);
    });

    var $prodTitle = $("h2")
      .filter(function () {
        return $(this).text().indexOf("Production") !== -1;
      })
      .first();
    var $stagingTitle = $("h2")
      .filter(function () {
        return $(this).text().indexOf("Staging") !== -1;
      })
      .first();

    if ($prodTitle.length) {
      $prodTitle.next("p").addBack().css("opacity", isSandbox ? 0.4 : 1);
    }
    if ($stagingTitle.length) {
      $stagingTitle.next("p").addBack().css("opacity", isSandbox ? 1 : 0.4);
    }

    $("#fungies-active-host").text(
      isSandbox ? "api.stage.fungies.net" : "api.fungies.io"
    );
    $("#fungies-sandbox-badge").toggle(isSandbox);
    $("#fungies-prod-badge").toggle(!isSandbox);
  }

  $(document).ready(function () {
    toggleEnvFields();
    $("#fungies_sandbox_mode").on("change", toggleEnvFields);
  });

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
