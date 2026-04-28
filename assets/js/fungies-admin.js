(function ($) {
  "use strict";

  function toggleEnvFields() {
    var isSandbox = $("#fungies_sandbox_mode").is(":checked");

    $('[data-env="production"]').each(function () {
      $(this).closest("tr").css("opacity", isSandbox ? 0.35 : 1);
      $(this).css("pointer-events", isSandbox ? "none" : "auto");
    });

    $('[data-env="staging"]').each(function () {
      $(this).closest("tr").css("opacity", isSandbox ? 1 : 0.35);
      $(this).css("pointer-events", isSandbox ? "auto" : "none");
    });

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

  function renderSyncPanel(resp) {
    var $panel = $("#fungies-sync-result-panel");
    var $inline = $("#fungies-sync-result");
    var $pull = $panel.find(".fungies-sync-pull-text");
    var $push = $panel.find(".fungies-sync-push-text");
    var $errors = $panel.find(".fungies-sync-errors");
    var $errorsList = $panel.find(".fungies-sync-errors-list");
    var $errorsSummary = $panel.find(".fungies-sync-errors-summary");

    $errorsList.empty();
    $panel.removeClass("has-errors fatal");
    $inline.text("");

    if (!resp.success) {
      $panel.addClass("fatal").prop("hidden", false);
      $pull.text("—");
      $push.text("—");
      $errors.prop("hidden", false);
      $errorsSummary.text("Sync failed");
      $errorsList.append(
        $("<li/>").addClass("error").text(resp.data || "Sync request failed.")
      );
      return;
    }

    var d = resp.data || {};
    var pull = d.pull || { created: 0, updated: 0 };
    var push = d.push || { created: 0, updated: 0, skipped: 0, errors: [] };
    var pullSynced = (pull.created || 0) + (pull.updated || 0);
    var pushSynced = (push.created || 0) + (push.updated || 0);
    var errorList = push.errors || [];

    $pull.text(
      pullSynced +
        " (" +
        (pull.created || 0) +
        " created, " +
        (pull.updated || 0) +
        " updated)"
    );
    $push.text(
      pushSynced +
        " (" +
        (push.created || 0) +
        " created, " +
        (push.updated || 0) +
        " updated, " +
        errorList.length +
        " errors)"
    );

    if (errorList.length > 0) {
      $panel.addClass("has-errors");
      $errors.prop("hidden", false);
      $errorsSummary.text(errorList.length + " error(s)");
      errorList.forEach(function (err) {
        var $li = $("<li/>").addClass("error");
        $li.append($("<strong/>").text(err.name || "Unknown product"));
        $li.append(document.createTextNode(": " + (err.message || "")));
        $errorsList.append($li);
      });
    } else {
      $errors.prop("hidden", true);
      $errorsSummary.text("");
    }

    $panel.prop("hidden", false);
  }

  $(document).on("click", "#fungies-sync-products", function () {
    var $btn = $(this);
    var $result = $("#fungies-sync-result");

    $btn.prop("disabled", true).text("Syncing…");
    $result.css("color", "").text("Syncing…");

    $.post(fungiesAdmin.ajaxUrl, {
      action: "fungies_sync_products",
      nonce: fungiesAdmin.nonce,
    })
      .done(function (resp) {
        renderSyncPanel(resp);
      })
      .fail(function () {
        renderSyncPanel({ success: false, data: "Sync request failed." });
      })
      .always(function () {
        $btn.prop("disabled", false).text("Sync Now");
      });
  });
})(jQuery);
