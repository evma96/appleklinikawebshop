(function($){
  // Move WooCommerce notices into our wrapper
  function moveWooCommerceNotices() {
    var $allNotices = $(".wc-nero-ai-image-optimizer-settings-wrap .notice, .wc-nero-ai-image-optimizer-settings-wrap .woocommerce-error, .wc-nero-ai-image-optimizer-settings-wrap .woocommerce-info, .wc-nero-ai-image-optimizer-settings-wrap .woocommerce-message, .wc-nero-ai-image-optimizer-settings-wrap .woocommerce-notice");
    var $noticesWrapper = $(".wc-nero-ai-image-optimizer-notices-wrapper");
    var $wrapperNotices = $noticesWrapper.find(".notice, .woocommerce-error, .woocommerce-info, .woocommerce-message, .woocommerce-notice");
    if ($allNotices.length > $wrapperNotices.length && $noticesWrapper.length) {
      $allNotices.not($wrapperNotices).each(function () {
        $(this).appendTo($noticesWrapper);
      });
      if ($noticesWrapper.children().length > 0) {
        $noticesWrapper.css("margin-bottom", "24px");
      }
    }
  }

  // Show a WP notice in our wrapper
  function showNotice(type, message) {
    var noticeClass = "notice notice-" + (type === "error" ? "error" : "success");
    var $notice = $("<div>", {
      class: noticeClass + " is-dismissible",
      html: "<p>" + message + "</p>"
    });
    $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
    $(".wc-nero-ai-image-optimizer-notices-wrapper").prepend($notice);
    setTimeout(function () {
      $notice.fadeOut(200, function () { $(this).remove(); });
    }, 5000);
    $notice.on("click", ".notice-dismiss", function () {
      $notice.fadeOut(200, function () { $(this).remove(); });
    });
  }

  function showApiKeyError(message) {
    $(".wc-nero-ai-image-optimizer-error").remove();
    $(".wc-nero-ai-image-optimizer-valid").remove();
    var $error = $("<div>", {
      class: "wc-nero-ai-image-optimizer-error",
      style: "color: #a00; font-size: 13px; position: absolute; top: 100%; left: 0; margin-top: 4px;",
      text: message
    });
    $(".wc-nero-ai-image-optimizer-input-group").append($error);
  }

  function showApiKeyValid(message) {
    $(".wc-nero-ai-image-optimizer-error").remove();
    $(".wc-nero-ai-image-optimizer-valid").remove();
    var $ok = $("<div>", {
      class: "wc-nero-ai-image-optimizer-valid",
      style: "color: #28a745; font-size: 13px; position: absolute; top: 100%; left: 0; margin-top: 4px;",
      text: message
    });
    $(".wc-nero-ai-image-optimizer-input-group").append($ok);
  }

  function refreshCredits() {
    var apiKey = $("#wc_nero_ai_image_optimizer_api_key").val();
    var $creditsEl = $("#wc-nero-ai-credits-remaining");
    var $creditsText = $("#wc-nero-ai-credits-text");
    var $creditsDisplay = $creditsEl.parent();

    if (!apiKey || !$creditsEl.length) {
      $creditsText.show();
      $creditsDisplay.hide();
      $(".wc-nero-ai-image-optimizer-error, .wc-nero-ai-image-optimizer-valid").remove();
      try { showApiKeyError("Invalid API key !"); } catch (e) {}
      return;
    }

    $creditsEl.html('<svg style="vertical-align:middle;display:inline-block;" width="18" height="18" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="#43a047" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/></circle></svg>');
    $creditsText.hide();
    $creditsDisplay.show();

    $.ajax({
      url: "https://api.nero.com/biz/api/apikey",
      type: "GET",
      data: { key: apiKey },
      dataType: "json",
      success: function (data) {
        if (data && data.code === 0 && data.data && typeof data.data.remaining_credits !== "undefined") {
          var credits = data.data.remaining_credits;
          var color = credits === 0 ? '#dc3545' : '#0073aa';
          $creditsEl.html(credits).css({ 'color': color, 'text-decoration': 'none' });
          $creditsText.hide();
          $creditsDisplay.show();
          $(".wc-nero-ai-image-optimizer-error, .wc-nero-ai-image-optimizer-valid").remove();
          try { showApiKeyValid("Valid API key !"); } catch (e) {}
        } else {
          $creditsEl.html("-").css({ 'color': '#0073aa', 'text-decoration': 'none' });
          $creditsText.show();
          $creditsDisplay.hide();
          $(".wc-nero-ai-image-optimizer-error, .wc-nero-ai-image-optimizer-valid").remove();
          showApiKeyError("Invalid API key !");
        }
      },
      error: function () {
        $creditsEl.html("-").css({ 'color': '#0073aa', 'text-decoration': 'none' });
        $creditsText.show();
        $creditsDisplay.hide();
        $(".wc-nero-ai-image-optimizer-error, .wc-nero-ai-image-optimizer-valid").remove();
        showApiKeyError("Invalid API key !");
      }
    });
  }

  // Public event hook to refresh credits
  document.addEventListener("wc-nero-ai-refresh-credits", function(){
    try { refreshCredits(); } catch(e) {}
  });

  $(function(){
    setTimeout(moveWooCommerceNotices, 100);
    setTimeout(refreshCredits, 200);
  });
})(jQuery); 