/**
 * WooCommerce Nero AI Image Optimizer - Admin Script
 */
jQuery(document).ready(function ($) {
  var selectedImagesRemove = [];
  var selectedImagesChange = [];
  function getList() {
    return currentMode === "change-bg"
      ? selectedImagesChange
      : selectedImagesRemove;
  }
  function setList(arr) {
    if (currentMode === "change-bg") {
      selectedImagesChange = arr;
    } else {
      selectedImagesRemove = arr;
    }
  }
  var mediaFrame;
  var currentPage = 1;
  var pageSize = 6;
  var MAX_SELECTION = 50;
  var currentMode = "remove-bg"; // 'remove-bg' | 'change-bg'
  var isProcessing = false; // global processing flag to control UI state
  var isActionWorking = false; // replace/download running flag
  // Cache image statuses across pagination/tabs
  var statusMap = {}; // { [attachmentId]: { status: 'pending'|'processing'|'success'|'error', text: string } }
  // Cache latest processed meta to persist across re-render
  var metaMap = {}; // { [attachmentId]: { thumb_url, full_url, filename, width, height, mime, filesize } }
  // Cache composed (canvas) preview URLs for change-bg color/gradient results
  var composedUrlMap = {}; // { [attachmentId]: blobObjectURL }

  // Perf helpers: debounce & throttle, and blob URL revokers
  function debounce(fn, wait) {
    var timeoutId;
    return function () {
      var ctx = this,
        args = arguments;
      clearTimeout(timeoutId);
      timeoutId = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }
  function throttle(fn, wait) {
    var last = 0,
      timer = null,
      lastArgs,
      lastCtx;
    return function () {
      var now = Date.now();
      lastArgs = arguments;
      lastCtx = this;
      if (now - last >= wait) {
        last = now;
        fn.apply(lastCtx, lastArgs);
      } else if (!timer) {
        timer = setTimeout(function () {
          last = Date.now();
          timer = null;
          fn.apply(lastCtx, lastArgs);
        }, wait - (now - last));
      }
    };
  }
  function isBlobUrl(url) {
    return typeof url === "string" && url.indexOf("blob:") === 0;
  }
  function revokeComposedUrl(attachmentId) {
    try {
      var url = composedUrlMap[attachmentId];
      if (isBlobUrl(url) && typeof URL !== "undefined" && URL.revokeObjectURL) {
        URL.revokeObjectURL(url);
      }
    } catch (e) {}
    delete composedUrlMap[attachmentId];
  }
  function revokeAllComposedUrls() {
    try {
      Object.keys(composedUrlMap || {}).forEach(function (id) {
        var url = composedUrlMap[id];
        if (
          isBlobUrl(url) &&
          typeof URL !== "undefined" &&
          URL.revokeObjectURL
        ) {
          URL.revokeObjectURL(url);
        }
        delete composedUrlMap[id];
      });
    } catch (e) {
      composedUrlMap = {};
    }
  }

  // Selected background state for change-bg mode (not displayed in DOM)
  var selectedBackground = null; // { type: 'color'|'gradient'|'image', value: string }

  // Compute per-image credit based on mode and background selection
  function getPerImageCredit() {
    if (
      currentMode === "change-bg" &&
      selectedBackground &&
      selectedBackground.value &&
      selectedBackground.type === "image"
    ) {
      return 2;
    }
    return 1;
  }
  function formatCredit(n) {
    return n + " " + (n === 1 ? "Credit" : "Credits");
  }
  function updateCreditBadges() {
    try {
      var per = getPerImageCredit();
      $(".wc-nero-ai-credit-badge").each(function () {
        $(this).text(formatCredit(per));
      });
    } catch (e) {}
  }

  // Simple Toast helper
  function showToast(message) {
    var $existing = $("#wc-nero-ai-toast");
    if ($existing.length) {
      $existing.remove();
    }
    var $toast = $(
      '<div id="wc-nero-ai-toast" class="wc-nero-ai-image-optimizer-toast" role="status" aria-live="polite"></div>'
    );
    // Convert \n to <br> for proper line breaks
    var htmlMessage = message.replace(/\n/g, "<br>");
    $toast.html(htmlMessage);
    $("body").append($toast);
    setTimeout(function () {
      $toast.addClass("show");
    }, 10);
    setTimeout(function () {
      $toast.removeClass("show");
      setTimeout(function () {
        $toast.remove();
      }, 300);
    }, 5000);
  }

  // API key and credits validation before selecting images
  function getApiStatusForSelection() {
    try {
      var apiKey = (
        $("#wc_nero_ai_image_optimizer_api_key").val() || ""
      ).trim();
      var $creditsEl = $("#wc-nero-ai-credits-remaining");
      var creditsText = ($creditsEl.text() || "").trim();
      var creditsNum = parseInt(creditsText, 10);
      var hasCredits = !isNaN(creditsNum) && creditsNum > 0;
      var invalidKeyHint = $(".wc-nero-ai-image-optimizer-error").length > 0;
      var isValidKey = !!apiKey && !invalidKeyHint;
      return { isValidKey: isValidKey, hasCredits: hasCredits };
    } catch (e) {
      return { isValidKey: false, hasCredits: false };
    }
  }

  function guardSelectImages(e) {
    var st = getApiStatusForSelection();
    if (!st.isValidKey) {
      showToast("Invalid API key. Please enter a valid API key.");
      if (e && typeof e.preventDefault === "function") e.preventDefault();
      return false;
    }
    if (!st.hasCredits) {
      showToast("Insufficient credits. Please get more credits.");
      if (e && typeof e.preventDefault === "function") e.preventDefault();
      return false;
    }
    return true;
  }

  function canStartProcessing() {
    if (getList().length === 0) return false;
    if (currentMode === "change-bg") {
      return !!(selectedBackground && selectedBackground.value);
    }
    return true;
  }

  function updateStartButtonState() {
    var $btn = $("#wc-nero-ai-image-optimizer-start-processing");
    if (getList().length === 0) {
      $btn.prop("disabled", true).hide();
      return;
    }
    // when list not empty, we show Start Over button alongside
    ensureStartOverButton();
    // In change-bg after a finished batch, keep Start hidden even if background is re-selected
    if (currentMode === "change-bg" && lastBatchFinished) {
      $btn.hide();
      return;
    }
    $btn.prop("disabled", !canStartProcessing() || isProcessing).show();
  }

  // Determine whether the current tab is in initial state (no selections and not processing)
  function isCurrentTabInitial() {
    return !isProcessing && getList().length === 0;
  }

  function ensureStartOverButton() {
    var $group = $(
      ".wc-nero-ai-image-optimizer-files-header .wc-nero-ai-image-optimizer-btn-group"
    );
    if ($group.length === 0) return;
    var $startOver = $("#wc-nero-ai-start-over");
    if ($startOver.length === 0) {
      $startOver = $(
        '<button type="button" id="wc-nero-ai-start-over" class="wc-nero-ai-image-optimizer-btn-secondary">Start Over</button>'
      );
      $group.prepend($startOver);
    } else {
      if ($startOver.prev().length > 0) {
        $startOver.prependTo($group);
      }
    }
    $startOver
      .removeClass("wc-nero-ai-image-optimizer-btn-primary")
      .addClass("wc-nero-ai-image-optimizer-btn-secondary");
    // Disable when processing or when replace/download is running
    $startOver.prop("disabled", isProcessing || isActionWorking).show();
  }

  function clearAndResetUI() {
    // Clear current mode list
    setList([]);
    // Reset caches
    statusMap = {};
    metaMap = {};
    // Revoke any in-memory blob URLs to avoid leaks
    revokeAllComposedUrls();
    isActionWorking = false;
    // Reset pagination
    currentPage = 1;
    // Hide replace/download if any
    $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
    // Hide retry button when resetting to initial state
    $("#wc-nero-ai-retry-batch").remove();
    // Remove result banner if exists
    hideResultBanner();
    // Clear last batch state
    lastBatchResults = [];
    lastBatchFinished = false;
    // Clear thumbnail cache by adding cache-busting parameters
    try {
      $(".wc-nero-ai-image-optimizer-image-item img").each(function () {
        var $img = $(this);
        var currentSrc = $img.attr("src");
        if (currentSrc && currentSrc.indexOf("cv=") === -1) {
          var cacheBusted = addCacheBusting(currentSrc);
          $img.attr("src", cacheBusted);
        }
      });
    } catch (e) {}
    // Restore select/clear buttons
    $("#wc-nero-ai-image-optimizer-select-images").show();
    $("#wc-nero-ai-image-optimizer-clear-all").hide();
    // Reset start button state
    $("#wc-nero-ai-image-optimizer-start-processing")
      .text("Start Bulk Processing")
      .prop("disabled", true)
      .show();
    // Update empty state and list
    renderSelectedImages();
    // Remove credits UI
    $("#wc-nero-ai-credits-banner").remove();
    // Update Add new Background button
    updateAddBackgroundButton();
  }

  // Helper function to update image status in UI and cache
  function updateImageStatus(id, status, text) {
    // Write to cache first
    statusMap[id] = { status: status, text: text };
    // Update DOM if present
    var $statusDiv = $(
      `.wc-nero-ai-image-optimizer-image-item[data-id="${id}"] .wc-nero-ai-image-optimizer-image-status`
    );
    if ($statusDiv.length) {
      $statusDiv
        .text(text)
        .removeClass("pending processing success error")
        .addClass(status);
      // Hide credit badge if this item is already in terminal state
      if (status === "success" || status === "error") {
        $(
          `.wc-nero-ai-image-optimizer-image-item[data-id="${id}"] .wc-nero-ai-credit-badge`
        ).hide();
      }
    }
  }

  // Start Over click -> clear and back to initial state
  $(document).on("click", "#wc-nero-ai-start-over", function () {
    if (isProcessing) return;
    clearAndResetUI();
  });

  // Tabs switching
  $(document).on("click", ".wc-nero-ai-image-optimizer-tab", function () {
    var tab = $(this).attr("data-tab");
    if (!tab) return;
    // Clicking the current active tab should do nothing
    if (tab === currentMode) return;
    // Prevent switching away when current tab is not in initial state
    if (tab !== currentMode && !isCurrentTabInitial()) {
      showToast(
        isProcessing
          ? "Batch processing… Cannot switch tabs."
          : "Please click Start Over to switch tabs."
      );
      return;
    }
    $(".wc-nero-ai-image-optimizer-tab").removeClass(
      "wc-nero-ai-image-optimizer-tab-active"
    );
    $(this).addClass("wc-nero-ai-image-optimizer-tab-active");
    currentMode = tab;
    if (tab === "change-bg") {
      updateAddBackgroundButton();
      maybeShowBgWarning();
    } else {
      updateAddBackgroundButton();
      hideBgWarning();
    }
    updateStartButtonState();
    // Reset to first page and re-render list for the newly selected tab
    currentPage = 1;
    renderSelectedImages();
    // Re-render action bar based on cached state after switching back
    renderGlobalActionBar();
    // Ensure credit badges text reflects the new mode
    updateCreditBadges();
  });

  // Background picker from media library
  var bgFrame;
  $(document).on("click", "#wc-nero-ai-select-background", function (e) {
    e.preventDefault();
    if (bgFrame) {
      bgFrame.open();
      return;
    }
    bgFrame = wp.media({
      title: "Select Background Image",
      button: { text: "Use this image" },
      library: {
        type: ["image"],
      },
      multiple: false,
    });
    bgFrame.on("select", function () {
      var att = bgFrame.state().get("selection").first().toJSON();
      if (att && att.url) {
        // Check if the selected background image format is supported
        var supportedTypes = [
          "image/jpeg",
          "image/jpg",
          "image/png",
          "image/bmp",
          "image/webp",
        ];

        var mimeType = att.mime || "";
        var isSupported = supportedTypes.some(function (type) {
          return mimeType.indexOf(type) !== -1;
        });

        if (!isSupported) {
          showToast(
            "Unsupported format: " +
              (att.subtype || mimeType) +
              ".\nOnly JPG, JPEG, JPE, PNG, BMP, and WEBP are supported."
          );
          return;
        }

        selectedBackground = { type: "image", value: att.url };
        updateStartButtonState();
        if (currentMode === "change-bg") maybeShowBgWarning();
        try {
          applyColorBackgroundsForCurrentList();
        } catch (e) {}
        try {
          renderCreditsBanner();
        } catch (e) {}
        try {
          updateCreditBadges();
        } catch (e) {}
      }
    });
    bgFrame.open();
  });

  // Open custom dialog for Add new Background
  $(document).on("click", "#wc-nero-ai-add-background", function (e) {
    e.preventDefault();
    openBackgroundDialog();
  });

  function openBackgroundDialog() {
    var $overlay = $(
      '<div class="wc-nero-ai-bg-overlay" role="dialog" aria-modal="true"></div>'
    );
    var presets = [
      { type: "color", value: "#FFFFFF" },
      { type: "color", value: "#000000" },
      { type: "color", value: "#F83613" },
      { type: "color", value: "#2563EB" },
      { type: "color", value: "#22C55E" },
      { type: "color", value: "#F59E0B" },
      {
        type: "gradient",
        value: "linear-gradient(150.64deg, #E93CDF 16.67%, #546BE0 83.33%)",
      },
      {
        type: "gradient",
        value: "linear-gradient(149.72deg, #2949FF 17%, #14D4F7 80.5%)",
      },
      {
        type: "gradient",
        value: "linear-gradient(149.72deg, #FFD000 20.5%, #36E2A9 83.67%)",
      },
      {
        type: "gradient",
        value: "linear-gradient(153.82deg, #F7921C 17.22%, #FEE790 78.35%)",
      },
      {
        type: "gradient",
        value: "linear-gradient(149.72deg, #FFB520 17.91%, #FF48DE 78.67%)",
      },
      {
        type: "gradient",
        value: "linear-gradient(149.72deg, #F0101F 15.5%, #3717E8 82.16%)",
      },
    ];
    var modal = [
      '<div class="wc-nero-ai-bg-modal">',
      '<div class="wc-nero-ai-bg-header">Add new background</div>',
      '<div class="wc-nero-ai-bg-body">',
      '<div class="wc-nero-ai-bg-upload-area" id="wc-nero-ai-bg-upload-area">',
      '  <button type="button" class="wc-nero-ai-bg-upload" id="wc-nero-ai-bg-upload-btn">',
      '    <span class="dashicons dashicons-upload" style="margin-right:8px"></span>Upload Background Image',
      "  </button>",
      "</div>",
      '<div class="wc-nero-ai-bg-sep"><span>or</span></div>',
      '<div class="wc-nero-ai-bg-color">',
      '<div class="wc-nero-ai-colorpicker">',
      '<div class="wc-nero-ai-cp-label"><span class="dashicons dashicons-edit" style="margin-right:6px;"></span>Custom</div>',
      '<div class="wc-nero-ai-cp-row">',
      '<div class="wc-nero-ai-cp-sv" id="wc-nero-ai-cp-sv"><div class="wc-nero-ai-cp-sv-thumb" id="wc-nero-ai-cp-sv-thumb"></div></div>',
      '<div class="wc-nero-ai-cp-hue" id="wc-nero-ai-cp-hue"><div class="wc-nero-ai-cp-hue-thumb" id="wc-nero-ai-cp-hue-thumb"></div></div>',
      "</div>",
      '<input type="text" id="wc-nero-ai-bg-hex" value="#FFFFFF" maxlength="7" />',
      "</div>",
      '<div class="wc-nero-ai-bg-color-right" id="wc-nero-ai-bg-swatches"></div>',
      "</div>",
      "</div>",
      '<div class="wc-nero-ai-bg-footer">',
      '<button type="button" class="wc-nero-ai-image-optimizer-btn-secondary" id="wc-nero-ai-bg-cancel">Cancel</button>',
      '<button type="button" class="wc-nero-ai-image-optimizer-btn-primary" id="wc-nero-ai-bg-apply">Apply to all</button>',
      "</div>",
      "</div>",
    ].join("");
    $overlay.html(modal);
    $("body").append($overlay);

    var selected = { type: "color", value: "#FFFFFF" };
    function truncateFileName(name) {
      try {
        var n = String(name || "image");
        if (n.length <= 20) return n;
        var ext = n.split(".").pop();
        var base = n.substring(0, n.length - ext.length - 1);
        if (base.length > 16)
          base = base.substring(0, 12) + "…" + base.slice(-3);
        return base + "." + ext;
      } catch (e) {
        return name || "image";
      }
    }
    function getBestPreviewUrl(att) {
      try {
        if (!att) return "";
        var sizes = att.sizes || {};
        var candidates = [
          "full",
          "2048x2048",
          "1536x1536",
          "large",
          "medium_large",
          "medium",
        ];
        for (var i = 0; i < candidates.length; i++) {
          var key = candidates[i];
          if (sizes[key] && sizes[key].url)
            return addCacheBusting(sizes[key].url);
        }
        return addCacheBusting(att.url || "");
      } catch (e) {
        return att && att.url ? addCacheBusting(att.url) : "";
      }
    }
    function renderFileBar(opts) {
      var thumbUrl = (opts && opts.thumbUrl) || (opts && opts.url) || "";
      var previewUrl =
        (opts && opts.previewUrl) || (opts && opts.url) || thumbUrl;

      // Add cache busting to image URLs
      thumbUrl = addCacheBusting(thumbUrl);
      previewUrl = addCacheBusting(previewUrl);

      var fileName = truncateFileName((opts && opts.filename) || "image");
      var fileSize =
        (opts && (opts.filesizeHumanReadable || opts.filesize)) || "";
      var html = [
        '<div class="wc-nero-ai-bg-upload-row">',
        '  <button type="button" class="wc-nero-ai-bg-file is-selected" id="wc-nero-ai-bg-file" aria-pressed="true">',
        '    <span class="wc-nero-ai-bg-file-thumb" data-url="' +
          previewUrl +
          '" style="background-image:url(\'' +
          thumbUrl +
          "')\"></span>",
        '    <span class="wc-nero-ai-bg-file-meta">',
        '      <span class="wc-nero-ai-bg-file-name" title="' +
          ((opts && (opts.filename || opts.url)) || "") +
          '">' +
          fileName +
          "</span>",
        '      <span class="wc-nero-ai-bg-file-size">' +
          (fileSize || " ") +
          "</span>",
        "    </span>",
        "  </button>",
        '  <button type="button" class="wc-nero-ai-bg-replace" id="wc-nero-ai-bg-replace">Replace</button>',
        "</div>",
      ].join("");
      $("#wc-nero-ai-bg-upload-area").html(html);
      // Bind click to (re)select image background
      $(document).off("click.wcNeroFile");
      $(document).on("click.wcNeroFile", "#wc-nero-ai-bg-file", function () {
        selected = {
          type: "image",
          value:
            (opts && opts.url) ||
            (selectedBackground && selectedBackground.value),
        };
        $(".wc-nero-ai-bg-swatch").removeClass("active");
        $("#wc-nero-ai-bg-hex")
          .prop("disabled", true)
          .removeClass("wc-nero-ai-invalid");
        setFileBarSelected(true);
      });
      // Hover preview bindings
      $(document).off(
        "mouseenter.wcNeroPrev mousemove.wcNeroPrev mouseleave.wcNeroPrev"
      );
      $(document).on(
        "mouseenter.wcNeroPrev",
        "#wc-nero-ai-bg-file .wc-nero-ai-bg-file-thumb",
        function (e) {
          var url = $(this).attr("data-url") || "";
          // Background picker preview should NOT show checkerboard
          createPreview(url, e.pageX, e.pageY, false);
        }
      );
      $(document).on(
        "mousemove.wcNeroPrev",
        "#wc-nero-ai-bg-file .wc-nero-ai-bg-file-thumb",
        (function () {
          var throttledBgMove = throttle(function (pageX, pageY) {
            movePreview(pageX, pageY);
          }, 32);
          return function (e) {
            throttledBgMove(e.pageX, e.pageY);
          };
        })()
      );
      $(document).on(
        "mouseleave.wcNeroPrev",
        "#wc-nero-ai-bg-file .wc-nero-ai-bg-file-thumb",
        function () {
          destroyPreview();
        }
      );
    }
    function setFileBarSelected(active) {
      var $b = $("#wc-nero-ai-bg-file");
      if (!$b.length) return;
      $b.toggleClass("is-selected", !!active).attr("aria-pressed", !!active);
    }
    function blurFileBar() {
      var $b = $("#wc-nero-ai-bg-file");
      if ($b && $b.length) {
        try {
          $b.blur();
        } catch (e) {}
      }
    }

    // --- Color math helpers ---
    function hsvToRgb(h, s, v) {
      h = Math.max(0, Math.min(360, h));
      s = Math.max(0, Math.min(100, s)) / 100;
      v = Math.max(0, Math.min(100, v)) / 100;
      var c = v * s;
      var x = c * (1 - Math.abs(((h / 60) % 2) - 1));
      var m = v - c;
      var r = 0,
        g = 0,
        b = 0;
      if (0 <= h && h < 60) {
        r = c;
        g = x;
        b = 0;
      } else if (60 <= h && h < 120) {
        r = x;
        g = c;
        b = 0;
      } else if (120 <= h && h < 180) {
        r = 0;
        g = c;
        b = x;
      } else if (180 <= h && h < 240) {
        r = 0;
        g = x;
        b = c;
      } else if (240 <= h && h < 300) {
        r = x;
        g = 0;
        b = c;
      } else {
        r = c;
        g = 0;
        b = x;
      }
      r = Math.round((r + m) * 255);
      g = Math.round((g + m) * 255);
      b = Math.round((b + m) * 255);
      return { r: r, g: g, b: b };
    }
    function rgbToHex(r, g, b) {
      return (
        "#" +
        [r, g, b]
          .map(function (n) {
            var s = n.toString(16).toUpperCase();
            return s.length === 1 ? "0" + s : s;
          })
          .join("")
      );
    }
    function hexToRgb(hex) {
      var m = /^#?([0-9a-fA-F]{6})$/.exec(hex || "");
      if (!m) return null;
      var i = parseInt(m[1], 16);
      return { r: (i >> 16) & 255, g: (i >> 8) & 255, b: i & 255 };
    }
    function rgbToHsv(r, g, b) {
      r /= 255;
      g /= 255;
      b /= 255;
      var max = Math.max(r, g, b),
        min = Math.min(r, g, b);
      var h,
        s,
        v = max;
      var d = max - min;
      s = max === 0 ? 0 : d / max;
      if (max === min) {
        h = 0;
      } else {
        switch (max) {
          case r:
            h = (g - b) / d + (g < b ? 6 : 0);
            break;
          case g:
            h = (b - r) / d + 2;
            break;
          case b:
            h = (r - g) / d + 4;
            break;
        }
        h *= 60;
      }
      return { h: h, s: s * 100, v: v * 100 };
    }

    var state = { h: 0, s: 0, v: 100 };
    var $sv = $("#wc-nero-ai-cp-sv"),
      $svThumb = $("#wc-nero-ai-cp-sv-thumb");
    var $hue = $("#wc-nero-ai-cp-hue"),
      $hueThumb = $("#wc-nero-ai-cp-hue-thumb");

    function updateSVBackground() {
      var hueColor = "hsl(" + state.h + ", 100%, 50%)";
      $sv.css(
        "background",
        "linear-gradient(to right, #fff, rgba(255,255,255,0)), linear-gradient(to top, #000, rgba(0,0,0,0)), " +
          hueColor
      );
    }
    function updateThumbs() {
      $svThumb.css({ left: state.s + "%", top: 100 - state.v + "%" });
      $hueThumb.css({ top: (state.h / 360) * 100 + "%" });
    }
    function applyState() {
      var rgb = hsvToRgb(state.h, state.s, state.v);
      var hex = rgbToHex(rgb.r, rgb.g, rgb.b);
      selected = { type: "color", value: hex };
      $("#wc-nero-ai-bg-hex").val(hex);
      updateSVBackground();
      updateThumbs();
      // Ensure image selection highlighting is cleared when choosing color
      setFileBarSelected(false);
      blurFileBar();
    }
    function setFromHex(hex) {
      var rgb = hexToRgb(hex);
      if (!rgb) return;
      var hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
      state.h = hsv.h;
      state.s = Math.max(0, Math.min(100, hsv.s));
      state.v = Math.max(0, Math.min(100, hsv.v));
      applyState();
    }
    // init
    applyState();

    // Recent colors memory (max 6)
    var RECENT_KEY = "wc_nero_ai_recent_colors";
    function loadRecent() {
      try {
        var s = window.localStorage.getItem(RECENT_KEY);
        if (!s) return [];
        var arr = JSON.parse(s);
        if (!Array.isArray(arr)) return [];
        return arr.filter(function (x) {
          return /^#[0-9A-F]{6}$/.test((x || "").toUpperCase());
        });
      } catch (e) {
        return [];
      }
    }
    function saveRecent(list) {
      try {
        window.localStorage.setItem(
          RECENT_KEY,
          JSON.stringify(list.slice(0, 6))
        );
      } catch (e) {}
    }
    var recentColors = loadRecent();
    function normalizeHex(hex) {
      var t = (hex || "").toUpperCase();
      return /^#[0-9A-F]{6}$/.test(t) ? t : null;
    }
    function ensureRecentUI() {
      var $wrap = $("#wc-nero-ai-recent");
      if ($wrap.length) return $wrap;
      var $swRight = $("#wc-nero-ai-bg-swatches");
      if ($swRight.length) {
        $swRight.prepend(
          '<div class="wc-nero-ai-recent-title wc-nero-ai-recent-hidden" id="wc-nero-ai-recent-title">New</div><div class="wc-nero-ai-recent wc-nero-ai-recent-hidden" id="wc-nero-ai-recent"></div>'
        );
        return $("#wc-nero-ai-recent");
      }
      return $();
    }
    function renderRecent() {
      var $rc = ensureRecentUI();
      if (!$rc.length) return;
      $rc.empty();
      if (recentColors.length === 0) {
        $rc.addClass("wc-nero-ai-recent-hidden");
        $("#wc-nero-ai-recent-title").addClass("wc-nero-ai-recent-hidden");
        return;
      }
      $("#wc-nero-ai-recent-title")
        .removeClass("wc-nero-ai-recent-hidden")
        .css("display", "");
      $rc.removeClass("wc-nero-ai-recent-hidden").css("display", "");
      recentColors.slice(0, 6).forEach(function (h, idx) {
        var cls = idx === 0 ? "wc-nero-ai-swatch-large" : "";
        var $b = $(
          '<button type="button" class="wc-nero-ai-bg-swatch ' +
            cls +
            '" aria-label="' +
            h +
            '"></button>'
        );
        $b.css("background", h);
        $b.on("click", function () {
          selectPreset({ type: "color", value: h }, $b);
        });
        $rc.append($b);
      });
    }
    function addRecentColor(hex) {
      var h = normalizeHex(hex);
      if (!h) return;
      // do not record when current selection is gradient
      if (selected && selected.type === "gradient") return;
      recentColors = [h]
        .concat(
          recentColors.filter(function (x) {
            return x !== h;
          })
        )
        .slice(0, 6);
      renderRecent();
      saveRecent(recentColors);
      // Activate the newly added swatch and ensure highlight
      try {
        $(".wc-nero-ai-bg-swatch").removeClass("active");
        var $btn = $(
          "#wc-nero-ai-recent .wc-nero-ai-bg-swatch[aria-label='" + h + "']"
        ).first();
        if ($btn.length) {
          $btn.addClass("active");
        }
      } catch (e) {}
    }

    // Build swatches
    var $sw = $("#wc-nero-ai-bg-swatches");
    presets.forEach(function (p, idx) {
      var label = p.type === "color" ? p.value : "Gradient";
      var $b = $(
        '<button type="button" class="wc-nero-ai-bg-swatch" aria-label="' +
          label +
          '"></button>'
      );
      $b.css("background", p.value);
      $b.attr("data-value", p.value);
      if (idx === 0) $b.addClass("active");
      $b.on("click", function () {
        selectPreset(p, $b);
      });
      $sw.append($b);
    });
    // Ensure recent UI exists initially
    renderRecent();

    // Initialize dialog selection from memory if available
    (function initFromExisting() {
      try {
        if (!selectedBackground || !selectedBackground.value) return;
        // Clear default active first; will restore if no match found
        $(".wc-nero-ai-bg-swatch").removeClass("active");
        if (selectedBackground.type === "color") {
          var hex = (selectedBackground.value || "").toUpperCase();
          if (/^#[0-9A-F]{6}$/.test(hex)) {
            selected = { type: "color", value: hex };
            setFromHex(hex);
            $("#wc-nero-ai-bg-hex")
              .prop("disabled", false)
              .removeClass("wc-nero-ai-invalid");
            // Highlight preset or recent swatch matching this color
            var $match = $(
              '.wc-nero-ai-bg-swatch[data-value="' + hex + '"]'
            ).first();
            if (!$match.length) {
              $match = $(
                "#wc-nero-ai-recent .wc-nero-ai-bg-swatch[aria-label='" +
                  hex +
                  "']"
              ).first();
            }
            if ($match.length) $match.addClass("active");
          }
        } else if (selectedBackground.type === "gradient") {
          selected = { type: "gradient", value: selectedBackground.value };
          $("#wc-nero-ai-bg-hex")
            .prop("disabled", true)
            .removeClass("wc-nero-ai-invalid");
          // Highlight gradient swatch by data-value
          var $g = $(
            '.wc-nero-ai-bg-swatch[data-value="' +
              selectedBackground.value +
              '"]'
          ).first();
          if ($g.length) $g.addClass("active");
        } else if (selectedBackground.type === "image") {
          selected = { type: "image", value: selectedBackground.value };
          var url = selectedBackground.value;
          var fname = (function (u) {
            try {
              var p = u.split("?")[0];
              return p.substring(p.lastIndexOf("/") + 1) || "image";
            } catch (e) {
              return "image";
            }
          })(url);
          renderFileBar({
            url: url,
            thumbUrl: url,
            previewUrl: url,
            filename: fname,
          });
          setFileBarSelected(true);
          // No swatch should be active for image selection
        }
      } catch (e) {}
    })();

    function selectPreset(preset, $btn) {
      $(".wc-nero-ai-bg-swatch").removeClass("active");
      if ($btn) $btn.addClass("active");
      if (preset.type === "color") {
        setFromHex(preset.value.toUpperCase());
        $("#wc-nero-ai-bg-hex")
          .prop("disabled", false)
          .removeClass("wc-nero-ai-invalid");
        setFileBarSelected(false);
        blurFileBar();
      } else if (preset.type === "gradient") {
        selected = { type: "gradient", value: preset.value };
        // Disable HEX input when gradient is selected
        $("#wc-nero-ai-bg-hex")
          .prop("disabled", true)
          .removeClass("wc-nero-ai-invalid");
        setFileBarSelected(false);
        blurFileBar();
      }
    }

    $(document).on("input", "#wc-nero-ai-bg-hex", function () {
      var t = ($(this).val() || "").toUpperCase();
      var isValid = /^#[0-9A-F]{6}$/.test(t);
      $(this).toggleClass("wc-nero-ai-invalid", !isValid);
      if (isValid) {
        setFromHex(t);
      }
      // Clear preset highlight when custom HEX input is used
      if (isValid) {
        $(".wc-nero-ai-bg-swatch").removeClass("active");
      }
      // Ensure input stays enabled for manual color editing
      $(this).prop("disabled", false);
      if (isValid) {
        addRecentColor(t);
      }
      setFileBarSelected(false);
      blurFileBar();
    });

    // SV drag
    function bindDrag($el, onMove, onEnd) {
      var dragging = false;
      function move(e) {
        if (!dragging) return;
        var rect = $el[0].getBoundingClientRect();
        var x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
        var y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
        onMove(x, y, rect);
      }
      $el.on("mousedown touchstart", function (e) {
        dragging = true;
        move(e);
        e.preventDefault();
        // Remove focus from file button to prevent focus-based highlight
        try {
          blurFileBar();
        } catch (e2) {}
      });
      $(document).on("mousemove.wcNeroCP touchmove.wcNeroCP", move);
      $(document).on(
        "mouseup.wcNeroCP touchend.wcNeroCP touchcancel.wcNeroCP",
        function () {
          if (dragging && typeof onEnd === "function") {
            onEnd();
          }
          dragging = false;
        }
      );
    }
    bindDrag(
      $sv,
      function (x, y, rect) {
        var xx = Math.max(0, Math.min(rect.width, x));
        var yy = Math.max(0, Math.min(rect.height, y));
        state.s = Math.round((xx / rect.width) * 100);
        state.v = Math.round((1 - yy / rect.height) * 100);
        applyState();
      },
      function () {
        addRecentColor(selected && selected.value);
      }
    );
    bindDrag(
      $hue,
      function (x, y, rect) {
        var yy = Math.max(0, Math.min(rect.height, y));
        state.h = Math.round((yy / rect.height) * 360);
        applyState();
      },
      function () {
        addRecentColor(selected && selected.value);
      }
    );

    // Upload/Replace -> media frame
    var localFrame;
    // Avoid duplicate bindings across multiple dialog openings
    $(document).off(
      "click.wcNeroBgUpload",
      "#wc-nero-ai-bg-upload-btn, #wc-nero-ai-bg-replace"
    );
    $(document).on(
      "click.wcNeroBgUpload",
      "#wc-nero-ai-bg-upload-btn, #wc-nero-ai-bg-replace",
      function (e) {
        e.preventDefault();
        if (localFrame) {
          localFrame.open();
          return;
        }
        localFrame = wp.media({
          title: "Upload Background Image",
          button: { text: "Use this image" },
          library: {
            type: ["image"],
          },
          multiple: false,
        });
        localFrame.on("select", function () {
          var att = localFrame.state().get("selection").first().toJSON();
          if (att && att.url) {
            // Check if the selected background image format is supported
            var supportedTypes = [
              "image/jpeg",
              "image/jpg",
              "image/png",
              "image/bmp",
              "image/webp",
            ];

            var mimeType = att.mime || "";
            var isSupported = supportedTypes.some(function (type) {
              return mimeType.indexOf(type) !== -1;
            });

            if (!isSupported) {
              showToast(
                "Unsupported format: " +
                  (att.subtype || mimeType) +
                  ".\nOnly JPG, JPEG, JPE, PNG, BMP, and WEBP are supported."
              );
              return;
            }

            selected = { type: "image", value: att.url };
            // Visual feedback + clear color highlight + disable HEX input
            $(".wc-nero-ai-bg-swatch").removeClass("active");
            $("#wc-nero-ai-bg-hex")
              .prop("disabled", true)
              .removeClass("wc-nero-ai-invalid");
            var thumbUrl =
              (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ||
              att.url;
            renderFileBar({
              url: att.url,
              thumbUrl: thumbUrl,
              previewUrl: getBestPreviewUrl(att),
              filename: att.filename || "image",
              filesizeHumanReadable: att.filesizeHumanReadable || "",
            });
            setFileBarSelected(true);
            try {
              localFrame.close();
              // Reset frame reference to avoid stale closures on next open
              localFrame = null;
            } catch (e) {}
          }
        });
        localFrame.open();
      }
    );

    // Cancel
    $(document).on("click", "#wc-nero-ai-bg-cancel", function () {
      // Cleanup handlers and media frame on dialog close
      try {
        if (localFrame) {
          localFrame.close();
          localFrame = null;
        }
      } catch (e) {}
      try {
        destroyPreview();
      } catch (e) {}
      $(document).off(
        "click.wcNeroBgUpload",
        "#wc-nero-ai-bg-upload-btn, #wc-nero-ai-bg-replace"
      );
      $overlay.remove();
    });
    // Escape close
    $(document).on("keydown.wcNeroBg", function (ev) {
      if (ev.key === "Escape") {
        try {
          if (localFrame) {
            localFrame.close();
            localFrame = null;
          }
        } catch (e) {}
        $(document).off(
          "click.wcNeroBgUpload",
          "#wc-nero-ai-bg-upload-btn, #wc-nero-ai-bg-replace"
        );
        $overlay.remove();
        $(document).off("keydown.wcNeroBg");
      }
    });
    // Apply
    $(document).on("click", "#wc-nero-ai-bg-apply", function () {
      if (selected && selected.value) {
        selectedBackground = { type: selected.type, value: selected.value };
        updateStartButtonState();
        if (currentMode === "change-bg") maybeShowBgWarning();
        try {
          applyColorBackgroundsForCurrentList();
        } catch (e) {}
        try {
          renderCreditsBanner();
        } catch (e) {}
        try {
          updateCreditBadges();
        } catch (e) {}
      }
      try {
        if (localFrame) {
          localFrame.close();
          localFrame = null;
        }
      } catch (e) {}
      $(document).off(
        "click.wcNeroBgUpload",
        "#wc-nero-ai-bg-upload-btn, #wc-nero-ai-bg-replace"
      );
      $overlay.remove();
    });
  }

  function hideBgWarning() {
    $("#wc-nero-ai-bg-warning").hide().text("");
  }
  function maybeShowBgWarning() {
    if (currentMode !== "change-bg") {
      hideBgWarning();
      return;
    }
    var hasBg = !!(selectedBackground && selectedBackground.value);
    var hasImages = getList().length > 0;
    // Only show the message when there ARE images but NO background
    if (hasImages && !hasBg) {
      $("#wc-nero-ai-bg-warning")
        .text("Please add new background firstly!")
        .show();
    } else {
      hideBgWarning();
    }
  }

  // Control Add new Background button visibility/disabled state
  function updateAddBackgroundButton() {
    var $btn = $("#wc-nero-ai-add-background");
    if ($btn.length === 0) return;
    if (currentMode !== "change-bg") {
      $btn.hide();
      return;
    }
    var hasImages = (getList() || []).length > 0;
    if (!hasImages) {
      $btn.hide();
      return;
    }

    // Processing: visible but disabled
    if (isProcessing) {
      $btn
        .show()
        .prop("disabled", true)
        .removeClass("wc-nero-ai-image-optimizer-btn-primary")
        .addClass("wc-nero-ai-image-optimizer-btn-secondary");
      return;
    }

    // Finished: only show when all failed
    if (lastBatchFinished) {
      var successCount = 0,
        totalCount = 0;
      try {
        successCount = (lastBatchResults || []).filter(function (r) {
          return r && r.result === "Ready";
        }).length;
        totalCount = (lastBatchResults || []).length;
      } catch (e) {}
      if (totalCount > 0 && successCount === 0) {
        $btn
          .show()
          .prop("disabled", false)
          .removeClass("wc-nero-ai-image-optimizer-btn-primary")
          .addClass("wc-nero-ai-image-optimizer-btn-secondary");
      } else {
        $btn.hide();
      }
      return;
    }

    // Initial (not finished and not processing): show enabled
    $btn
      .show()
      .prop("disabled", false)
      .removeClass("wc-nero-ai-image-optimizer-btn-secondary")
      .addClass("wc-nero-ai-image-optimizer-btn-primary");
  }

  // --- Frontend background application (color) for change-bg mode ---
  function parseBgSelection() {
    if (
      selectedBackground &&
      selectedBackground.type === "color" &&
      selectedBackground.value
    ) {
      return { type: "color", value: selectedBackground.value };
    }
    return null;
  }

  function applyColorBackgroundToId(attachmentId) {
    // Do not change UI background. Always keep row background clear.
    var $row = $(
      '.wc-nero-ai-image-optimizer-image-item[data-id="' + attachmentId + '"]'
    );
    if ($row.length === 0) return;
    $row.css("background", "");
  }

  // Canvas composition helper: compose foreground (processed transparent image)
  // with selected background (color or image) and return a Blob and blob URL (PNG).
  function composeCanvasWithBackground(foregroundUrl, selection) {
    return new Promise(function (resolve, reject) {
      if (!foregroundUrl || !selection || !selection.value) {
        return reject(new Error("Invalid compose inputs"));
      }

      var fg = new Image();
      try {
        fg.crossOrigin = "anonymous";
      } catch (e) {}
      fg.onload = function () {
        var canvas = document.createElement("canvas");
        canvas.width = fg.naturalWidth || fg.width;
        canvas.height = fg.naturalHeight || fg.height;
        var ctx = canvas.getContext("2d");

        function finalize() {
          try {
            canvas.toBlob(
              function (blob) {
                if (!blob) {
                  return reject(new Error("Canvas toBlob failed"));
                }
                var url = URL.createObjectURL(blob);
                resolve({ blob: blob, url: url });
              },
              "image/png",
              1
            );
          } catch (e) {
            reject(e);
          }
        }

        // Minimal parser for CSS linear-gradient(angle, color stop, ...)
        function parseLinearGradient(input) {
          try {
            var str = String(input || "").trim();
            if (str.indexOf("linear-gradient(") !== 0) return null;
            var inside = str.slice(str.indexOf("(") + 1, str.lastIndexOf(")"));
            var parts = inside.split(",");
            if (parts.length < 2) return null;
            var anglePart = parts.shift().trim();
            var angleDeg = 180; // default
            var m = anglePart.match(/(-?\d+(?:\.\d+)?)deg/i);
            if (m) angleDeg = parseFloat(m[1]);
            var stops = parts
              .map(function (p) {
                var t = p.trim();
                var colorMatch = t.match(/#([0-9a-fA-F]{6})/);
                var color = colorMatch
                  ? "#" + colorMatch[1].toUpperCase()
                  : null;
                var stopMatch = t.match(/(-?\d+(?:\.\d+)?)%/);
                var pos = stopMatch
                  ? Math.max(0, Math.min(100, parseFloat(stopMatch[1]))) / 100
                  : null;
                return { color: color, pos: pos };
              })
              .filter(function (s) {
                return !!s.color;
              });
            if (stops.length === 0) return null;
            // Distribute positions if missing
            var n = stops.length;
            for (var i = 0; i < n; i++) {
              if (stops[i].pos == null) {
                stops[i].pos = n === 1 ? 0 : i / (n - 1);
              }
            }
            // Clamp and sort
            stops.forEach(function (s) {
              s.pos = Math.max(0, Math.min(1, s.pos));
            });
            stops.sort(function (a, b) {
              return a.pos - b.pos;
            });
            return { angle: angleDeg, stops: stops };
          } catch (e) {
            return null;
          }
        }

        if (selection.type === "color") {
          ctx.fillStyle = selection.value;
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(fg, 0, 0, canvas.width, canvas.height);
          return finalize();
        }

        if (selection.type === "gradient") {
          var parsed = parseLinearGradient(selection.value);
          if (parsed && parsed.stops && parsed.stops.length > 0) {
            // Force gradient direction: top-left -> bottom-right (ignore CSS angle)
            var g = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            parsed.stops.forEach(function (s) {
              g.addColorStop(s.pos, s.color);
            });
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(fg, 0, 0, canvas.width, canvas.height);
            return finalize();
          }
          // fallback to unsupported path below
        }

        if (selection.type === "image") {
          var bg = new Image();
          try {
            bg.crossOrigin = "anonymous";
          } catch (e) {}
          bg.onload = function () {
            // Draw background as cover
            var cw = canvas.width,
              ch = canvas.height,
              br = bg.width / bg.height,
              cr = cw / ch,
              dw,
              dh,
              dx,
              dy;
            if (br > cr) {
              dh = ch;
              dw = dh * br;
              dx = (cw - dw) / 2;
              dy = 0;
            } else {
              dw = cw;
              dh = dw / br;
              dx = 0;
              dy = (ch - dh) / 2;
            }
            ctx.drawImage(bg, dx, dy, dw, dh);
            ctx.drawImage(fg, 0, 0, cw, ch);
            return finalize();
          };
          bg.onerror = function (e) {
            reject(new Error("Failed to load background image"));
          };
          bg.src = addCacheBusting(selection.value);
          return;
        }

        reject(new Error("Unsupported background type"));
      };
      fg.onerror = function (e) {
        reject(new Error("Failed to load foreground image"));
      };
      // cache-bust in case of CDN caching
      var u =
        foregroundUrl +
        (foregroundUrl.indexOf("?") !== -1 ? "&" : "?") +
        "cv=" +
        Date.now();
      fg.src = addCacheBusting(u);
    });
  }

  function applyColorBackgroundsForCurrentList() {
    var list = getList();
    if (!Array.isArray(list) || list.length === 0) return;
    for (var i = 0; i < list.length; i++) {
      applyColorBackgroundToId(list[i].id);
    }
  }

  // Helper: middle truncate (first 3 + ... + last 7)
  function truncateMiddle(str) {
    if (!str) return "";
    if (str.length <= 10) return str;
    return str.slice(0, 3) + "..." + str.slice(-7);
  }

  // Measure original text width in px regardless of container
  function measureTextWidth(element, text) {
    var clone = element.cloneNode(false);
    clone.style.visibility = "hidden";
    clone.style.position = "absolute";
    clone.style.whiteSpace = "nowrap";
    clone.style.maxWidth = "none";
    clone.style.width = "auto";
    clone.textContent = text;
    document.body.appendChild(clone);
    var width = clone.offsetWidth;
    document.body.removeChild(clone);
    return width;
  }

  function applyFilenameTruncation() {
    var $names = $(".wc-nero-ai-image-optimizer-image-name");
    if ($names.length === 0) return;
    $names.each(function () {
      var $el = $(this);
      var full = $el.attr("data-full-name") || $el.text();
      $el.attr("data-full-name", full);
      $el.attr("title", full);
      // reset to full first
      $el.text(full);
      // only truncate if the original width exceeds 300px
      var fullWidth = measureTextWidth(this, full);
      if (fullWidth > 200) {
        $el.text(truncateMiddle(full));
      }
    });
  }

  // Determine if all currently listed items are in terminal state for UI purposes
  function areAllCurrentItemsTerminalForUI() {
    var list = getList();
    if (!list || list.length === 0) return false;
    for (var i = 0; i < list.length; i++) {
      var id = list[i].id;
      var st = statusMap[id] && statusMap[id].status;
      if (st !== "success" && st !== "error") return false;
    }
    return true;
  }

  // Tooltip helpers for action buttons
  var tooltipEl = null;
  // Global cache for last batch results to persist across tab switches
  var lastBatchResults = [];
  var lastBatchFinished = false;

  // Re-render global action bar based on cached results and current list state
  function renderGlobalActionBar() {
    // Only render when not processing and the last batch has finished
    if (isProcessing || !lastBatchFinished) {
      $("#wc-nero-ai-retry-batch").remove();
      return;
    }

    var $group = $(
      ".wc-nero-ai-image-optimizer-files-header .wc-nero-ai-image-optimizer-btn-group"
    );
    if ($group.length === 0) return;

    var list = getList();
    if (!Array.isArray(list) || list.length === 0) {
      $(
        "#wc-nero-ai-replace-all, #wc-nero-ai-download-all, #wc-nero-ai-retry-batch"
      ).remove();
      return;
    }

    // Ensure header is in completed layout when all current items are terminal
    var allTerminalUI = areAllCurrentItemsTerminalForUI();
    if (allTerminalUI) {
      ensureStartOverButton();
      $("#wc-nero-ai-image-optimizer-start-processing").hide();
      $(
        "#wc-nero-ai-image-optimizer-select-images, #wc-nero-ai-image-optimizer-clear-all"
      ).hide();
      $("#wc-nero-ai-start-over")
        .prop("disabled", isProcessing || isActionWorking)
        .show();
    }

    // Determine whether current list has any ready results from the last batch
    var idSet = {};
    for (var i = 0; i < list.length; i++) {
      idSet[list[i].id] = true;
    }
    var hasReady = (lastBatchResults || []).some(function (r) {
      return r && r.result === "Ready" && r.task && idSet[r.attachmentId];
    });

    // Insert Retry button when all failed (no ready)
    var totalCount = (lastBatchResults || []).length;
    var successCount = (lastBatchResults || []).filter(function (r) {
      return r && r.result === "Ready" && r.task && idSet[r.attachmentId];
    }).length;
    var allFailed = totalCount > 0 && successCount === 0;

    // Ensure Retry exists in header after Add new Background
    $("#wc-nero-ai-retry-batch").remove();
    if (allFailed) {
      var $addBg = $("#wc-nero-ai-add-background");
      var $retry = $(
        '<button type="button" id="wc-nero-ai-retry-batch" class="wc-nero-ai-image-optimizer-btn-primary">Retry</button>'
      );
      if ($addBg.length) {
        $addBg.after($retry);
      } else {
        // If no Add new Background button, insert Retry at the beginning of the group
        $group.prepend($retry);
      }
      // Click -> re-run batch
      $(document)
        .off("click.wcNeroHeaderRetry", "#wc-nero-ai-retry-batch")
        .on("click.wcNeroHeaderRetry", "#wc-nero-ai-retry-batch", function () {
          try {
            $("#wc-nero-ai-result-banner").remove();
          } catch (e) {}
          var $start = $("#wc-nero-ai-image-optimizer-start-processing");
          if ($start.length) {
            $start.trigger("click");
          }
        });
    }

    // Clear existing action buttons first (keep potential Retry)
    $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();

    if (allTerminalUI && hasReady) {
      var $replace = $(
        '<button type="button" class="wc-nero-ai-image-optimizer-btn-primary" id="wc-nero-ai-replace-all">Replace All</button>'
      );
      var $download = $(
        '<button type="button" class="wc-nero-ai-image-optimizer-btn-primary" id="wc-nero-ai-download-all" style="background:#28a745">Download All</button>'
      );
      $group.append($replace);
      $group.append($download);

      // Bind basic tooltips using existing helpers
      $(document)
        .off(
          "mouseenter.wcNeroTipG mouseleave.wcNeroTipG focus.wcNeroTipG blur.wcNeroTipG",
          "#wc-nero-ai-replace-all, #wc-nero-ai-download-all"
        )
        .on(
          "mouseenter.wcNeroTipG focus.wcNeroTipG",
          "#wc-nero-ai-replace-all",
          function () {
            showActionTooltip(
              $(this),
              "Replace original images with processed versions"
            );
          }
        )
        .on(
          "mouseleave.wcNeroTipG blur.wcNeroTipG",
          "#wc-nero-ai-replace-all",
          function () {
            hideActionTooltip();
          }
        )
        .on(
          "mouseenter.wcNeroTipG focus.wcNeroTipG",
          "#wc-nero-ai-download-all",
          function () {
            showActionTooltip(
              $(this),
              "Download processed images as new files (keeps originals)"
            );
          }
        )
        .on(
          "mouseleave.wcNeroTipG blur.wcNeroTipG",
          "#wc-nero-ai-download-all",
          function () {
            hideActionTooltip();
          }
        );
    }
  }

  function showActionTooltip($target, text) {
    hideActionTooltip();
    tooltipEl = document.createElement("div");
    tooltipEl.className = "wc-nero-ai-tooltip";
    tooltipEl.textContent = text;
    document.body.appendChild(tooltipEl);
    positionTooltip($target);
    requestAnimationFrame(function () {
      tooltipEl.classList.add("show");
    });
  }
  function hideActionTooltip() {
    if (!tooltipEl) return;
    tooltipEl.classList.remove("show");
    var el = tooltipEl;
    tooltipEl = null;
    setTimeout(function () {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 150);
  }
  function positionTooltip($target) {
    if (!tooltipEl) return;
    var rect = $target[0].getBoundingClientRect();
    var tipRect = tooltipEl.getBoundingClientRect();
    var top = window.scrollY + rect.top - tipRect.height - 8; // top above button
    var left = window.scrollX + rect.left + (rect.width - tipRect.width) / 2; // centered
    tooltipEl.style.top = top + "px";
    tooltipEl.style.left = left + "px";
  }
  $(window).on("scroll resize", function () {
    if (!tooltipEl) return;
    var $btn = $(
      "#wc-nero-ai-replace-all:hover, #wc-nero-ai-download-all:hover"
    );
    if ($btn.length) positionTooltip($btn);
  });

  // Result banner helpers
  function hideResultBanner() {
    $("#wc-nero-ai-result-banner").remove();
  }
  function renderCompletionBanner(totalCount, successCount) {
    hideResultBanner();
    var $tabs = $(".wc-nero-ai-image-optimizer-tabs");
    if ($tabs.length === 0) return;
    var $banner = $(
      '<div id="wc-nero-ai-result-banner" class="wc-nero-ai-result-banner" role="status" aria-live="polite"></div>'
    );
    var allFailed = totalCount > 0 && successCount === 0;
    var html =
      '<div class="wc-nero-ai-result-left">' +
      '<div class="wc-nero-ai-result-text">' +
      '<div class="wc-nero-ai-result-title"><span class="dashicons dashicons-yes" style="font-size:26px;margin-right:8px;"></span>Processing Complete!</div>' +
      '<div class="wc-nero-ai-result-meta">Total: ' +
      totalCount +
      " images • Successfully Processed: " +
      successCount +
      "</div></div></div>" +
      '<button type="button" class="wc-nero-ai-result-close" aria-label="Close">×</button>';
    $banner.html(html);
    $tabs.after($banner);
    $(document)
      .off(
        "click.wcNeroBanner",
        "#wc-nero-ai-result-banner .wc-nero-ai-result-close"
      )
      .on(
        "click.wcNeroBanner",
        "#wc-nero-ai-result-banner .wc-nero-ai-result-close",
        function () {
          hideResultBanner();
        }
      );

    // Bind Retry action when all tasks failed
    if (allFailed) {
      $(document)
        .off(
          "click.wcNeroBannerRetry",
          "#wc-nero-ai-result-banner .wc-nero-ai-result-retry"
        )
        .on(
          "click.wcNeroBannerRetry",
          "#wc-nero-ai-result-banner .wc-nero-ai-result-retry",
          function () {
            try {
              hideResultBanner();
            } catch (e) {}
            var $start = $("#wc-nero-ai-image-optimizer-start-processing");
            if ($start.length) {
              $start.trigger("click");
            }
          }
        );
    }
  }

  // Render a custom result banner (e.g., Replacing/Downloading Complete)
  function renderCustomResultBanner(
    titleText,
    totalCount,
    successCount,
    successLabel
  ) {
    hideResultBanner();
    var $tabs = $(".wc-nero-ai-image-optimizer-tabs");
    if ($tabs.length === 0) return;
    var $banner = $(
      '<div id="wc-nero-ai-result-banner" class="wc-nero-ai-result-banner" role="status" aria-live="polite"></div>'
    );
    var html =
      '<div class="wc-nero-ai-result-left">' +
      '<div class="wc-nero-ai-result-text">' +
      '<div class="wc-nero-ai-result-title"><span class="dashicons dashicons-yes" style="font-size:26px;margin-right:8px;"></span>' +
      titleText +
      "</div>" +
      '<div class="wc-nero-ai-result-meta">Total: ' +
      totalCount +
      " images • " +
      successLabel +
      ": " +
      successCount +
      "</div></div></div>" +
      '<button type="button" class="wc-nero-ai-result-close" aria-label="Close">×</button>';
    $banner.html(html);
    $tabs.after($banner);
    $(document)
      .off(
        "click.wcNeroBanner",
        "#wc-nero-ai-result-banner .wc-nero-ai-result-close"
      )
      .on(
        "click.wcNeroBanner",
        "#wc-nero-ai-result-banner .wc-nero-ai-result-close",
        function () {
          hideResultBanner();
        }
      );
  }

  // --- Media Library Interaction ---
  $("#wc-nero-ai-image-optimizer-select-images").on("click", function (e) {
    e.preventDefault();
    if (!guardSelectImages(e)) return;

    if (mediaFrame) {
      mediaFrame.open();
      return;
    }

    mediaFrame = wp.media({
      title: "Select Images to Process",
      button: {
        text: "Use these images",
      },
      multiple: "add",
      library: {
        type: ["image"],
      },
    });

    // Auto-select newly added attachments in library to selection (enables confirm button)
    mediaFrame.on("open", function () {
      try {
        var state = mediaFrame && mediaFrame.state && mediaFrame.state();
        var lib = state && state.get && state.get("library");
        var selection = state && state.get && state.get("selection");
        if (!lib || !selection) return;
        lib.off("add.wcNeroAutoSelect");
        lib.on("add.wcNeroAutoSelect", function (att) {
          try {
            selection.add(att);
          } catch (e) {}
        });
      } catch (e) {}
    });

    // Auto-select newly uploaded attachments to enable the confirm button immediately
    mediaFrame.on("upload:success", function (file) {
      try {
        var state = mediaFrame && mediaFrame.state && mediaFrame.state();
        var selection = state && state.get && state.get("selection");
        if (!selection || !file || !file.id) return;
        var att =
          wp.media && wp.media.attachment ? wp.media.attachment(file.id) : null;
        if (!att) return;
        if (typeof att.fetch === "function") {
          // Ensure the model has attributes before adding
          var xhr = att.fetch();
          if (xhr && typeof xhr.always === "function") {
            xhr.always(function () {
              try {
                selection.add(att);
              } catch (e) {}
            });
          } else {
            try {
              selection.add(att);
            } catch (e) {}
          }
        } else {
          try {
            selection.add(att);
          } catch (e) {}
        }
      } catch (e) {}
    });

    mediaFrame.on("select", function () {
      var selection = mediaFrame.state().get("selection");
      var list = getList();
      var supportedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/bmp",
        "image/webp",
      ];

      selection.each(function (attachment) {
        if (list.length >= MAX_SELECTION) {
          return;
        }
        attachment = attachment.toJSON();

        // Check if the selected image format is supported
        var mimeType = attachment.mime || "";
        var isSupported = supportedTypes.some(function (type) {
          return mimeType.indexOf(type) !== -1;
        });

        if (!isSupported) {
          showToast(
            "Unsupported format: " +
              (attachment.subtype || mimeType) +
              ".\nOnly JPG, JPEG, JPE, PNG, BMP, and WEBP are supported."
          );
          return;
        }

        if (!list.find((img) => img.id === attachment.id)) {
          list.push(attachment);
        }
      });

      setList(list);
      if (list.length >= MAX_SELECTION) {
        showToast("You can select up to 50 images at once.");
      }
      currentPage = 1;
      renderSelectedImages();
      try {
        updateCreditBadges();
      } catch (e) {}
    });

    mediaFrame.open();
  });

  $("#wc-nero-ai-image-optimizer-clear-all").on("click", function () {
    setList([]);
    currentPage = 1;
    renderSelectedImages();
  });

  function humanFileSize(bytes) {
    if (!bytes && bytes !== 0) return "";
    var thresh = 1024;
    if (Math.abs(bytes) < thresh) {
      return bytes + " B";
    }
    var units = ["KB", "MB", "GB", "TB"];
    var u = -1;
    do {
      bytes /= thresh;
      ++u;
    } while (Math.abs(bytes) >= thresh && u < units.length - 1);
    return bytes.toFixed(1) + " " + units[u];
  }

  // Try to fetch remote file size via HEAD request; CORS may block. Callback gets bytes or null.
  function fetchFileSize(url, cb) {
    if (!url || typeof cb !== "function") return;
    var u = url + (url.indexOf("?") !== -1 ? "&" : "?") + "fs=" + Date.now();
    $.ajax({ type: "HEAD", url: u, crossDomain: true })
      .done(function (data, status, xhr) {
        var len = parseInt(xhr.getResponseHeader("Content-Length"), 10);
        cb(isNaN(len) ? null : len);
      })
      .fail(function () {
        cb(null);
      });
  }

  // Add cache busting parameter to any image URL
  function addCacheBusting(url) {
    if (!url || typeof url !== "string") return url;
    if (url.indexOf("cv=") !== -1) return url; // Already has cache busting
    if (url.indexOf("blob:") === 0) return url; // Skip blob URLs
    if (url.indexOf("data:") === 0) return url; // Skip data URLs

    var separator = url.indexOf("?") !== -1 ? "&" : "?";
    return url.split("#")[0] + separator + "cv=" + Date.now();
  }

  // Refresh a specific attachment inside wp.media modal without full page reload
  function refreshWpMediaAttachment(attachmentId, hint) {
    try {
      if (typeof wp === "undefined" || !wp.media || !wp.media.model) return;
      var model = wp.media.model.Attachment.get(attachmentId);
      var updateTiles = function () {
        try {
          var thumbUrl = null;
          if (hint && hint.thumb_url) {
            thumbUrl = hint.thumb_url;
          } else if (model && typeof model.get === "function") {
            var sizes = model.get("sizes") || {};
            if (sizes.thumbnail && sizes.thumbnail.url) {
              thumbUrl = sizes.thumbnail.url;
            } else if (model.get("url")) {
              thumbUrl = model.get("url");
            }
          }
          if (!thumbUrl) return;
          var cacheBusted = addCacheBusting(thumbUrl);
          var selector =
            '.media-modal .attachments .attachment[data-id="' +
            attachmentId +
            '"] img';
          var $imgs = jQuery(selector);
          if ($imgs && $imgs.length) {
            $imgs.each(function () {
              this.src = cacheBusted;
            });
          }

          // Also refresh any other image elements that might be showing this attachment
          var otherSelectors = [
            '.media-modal .attachments .attachment[data-id="' +
              attachmentId +
              '"] .attachment-preview',
            '.media-modal .attachments .attachment[data-id="' +
              attachmentId +
              '"] .thumbnail',
            '.media-modal .attachments .attachment[data-id="' +
              attachmentId +
              '"] .attachment-icon',
          ];

          otherSelectors.forEach(function (sel) {
            var $otherImgs = jQuery(sel + " img");
            if ($otherImgs && $otherImgs.length) {
              $otherImgs.each(function () {
                this.src = cacheBusted;
              });
            }
          });
        } catch (e) {}
      };
      if (model && typeof model.fetch === "function") {
        var xhr = model.fetch();
        if (xhr && typeof xhr.always === "function") {
          xhr.always(updateTiles);
        } else {
          updateTiles();
        }
        if (typeof model.trigger === "function") {
          model.trigger("change");
        }
      } else {
        updateTiles();
      }
    } catch (e) {}
  }

  function buildItemHtml(attachment) {
    var imageUrl =
      attachment.sizes && attachment.sizes.thumbnail
        ? attachment.sizes.thumbnail.url
        : attachment.url;
    var previewUrl = attachment && attachment.url ? attachment.url : imageUrl; // original image as preview by default

    // Add cache busting to image URLs
    imageUrl = addCacheBusting(imageUrl);
    previewUrl = addCacheBusting(previewUrl);

    var sizeTxt = attachment.filesizeInBytes
      ? humanFileSize(attachment.filesizeInBytes)
      : "";
    var dimsTxt =
      attachment.width && attachment.height
        ? attachment.width + "×" + attachment.height
        : "";
    var typeTxt = (
      attachment.subtype ||
      (attachment.mime && attachment.mime.split("/")[1]) ||
      ""
    ).toUpperCase();

    var detailParts = [sizeTxt, dimsTxt, typeTxt].filter(Boolean);
    var details = detailParts.join(" • ");
    var creditText = formatCredit(getPerImageCredit());

    return `
      <div class="wc-nero-ai-image-optimizer-image-item" data-id="${
        attachment.id
      }" data-preview="${previewUrl}">
        <img src="${imageUrl}" alt="${attachment.filename || "image"}" />
        <div class="wc-nero-ai-image-optimizer-image-info">
          <div class="wc-nero-ai-image-optimizer-image-name" data-full-name="${
            attachment.filename || "Unnamed"
          }">${attachment.filename || "Unnamed"}</div>
          ${
            details
              ? `<div class="wc-nero-ai-image-optimizer-image-details">${details}</div>`
              : ""
          }
        </div>
        <span class="wc-nero-ai-credit-badge">${creditText}</span>
        <span class="wc-nero-ai-image-optimizer-image-status pending">Pending</span>
        <button class="wc-nero-ai-image-optimizer-remove-btn" aria-label="Remove">×</button>
      </div>`;
  }

  function renderPagination(totalItems) {
    var totalPages = Math.ceil(totalItems / pageSize) || 1;
    var $pagination = $(".wc-nero-ai-image-optimizer-pagination");
    var $pagesWrap = $(".wc-nero-ai-image-optimizer-pagination-pages");

    if (totalPages <= 1) {
      $pagination.hide();
      $pagesWrap.empty();
      return;
    }

    if (currentPage > totalPages) currentPage = totalPages;

    $pagesWrap.empty();
    for (var p = 1; p <= totalPages; p++) {
      var pageBtn = $(
        '<button type="button" class="wc-nero-ai-image-optimizer-pagination-page" />'
      )
        .text(p)
        .attr("data-page", p);
      if (p === currentPage) pageBtn.addClass("active");
      $pagesWrap.append(pageBtn);
    }

    $("#wc-nero-ai-pagination-prev").prop("disabled", currentPage === 1);
    $("#wc-nero-ai-pagination-next").prop(
      "disabled",
      currentPage === totalPages
    );

    $pagination.show();
  }

  function renderSelectedImages() {
    var grid = $(".wc-nero-ai-image-optimizer-images-grid");
    grid.empty();

    var list = getList();
    if (list.length === 0) {
      $(".wc-nero-ai-image-optimizer-empty-state").show();
      grid.hide();
      $("#wc-nero-ai-image-optimizer-start-processing")
        .prop("disabled", true)
        .hide();
      $("#wc-nero-ai-image-optimizer-clear-all").hide();
      $("#wc-nero-ai-start-over").hide();
      // Remove completed-state action buttons when list becomes empty
      $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
      $("#wc-nero-ai-image-optimizer-select-images")
        .removeClass("wc-nero-ai-image-optimizer-btn-secondary")
        .addClass("wc-nero-ai-image-optimizer-btn-primary");
      renderPagination(0);
    } else {
      $(".wc-nero-ai-image-optimizer-empty-state").hide();
      grid.show();

      // If all current items are terminal and not processing, keep completed header layout
      var allTerminal = !isProcessing && areAllCurrentItemsTerminalForUI();
      if (!allTerminal) {
        updateStartButtonState();
        // Select Images becomes secondary style once there are images; disable during processing
        $("#wc-nero-ai-image-optimizer-select-images")
          .removeClass("wc-nero-ai-image-optimizer-btn-primary")
          .addClass("wc-nero-ai-image-optimizer-btn-secondary")
          .prop("disabled", isProcessing)
          .show();
        $("#wc-nero-ai-image-optimizer-clear-all").hide();
        // Ensure completed-state buttons are removed in non-terminal state
        hideActionTooltip();
        $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
      } else {
        // Completed header mode: hide Select/Start/Clear, show Start Over; Replace/Download handled elsewhere
        $("#wc-nero-ai-image-optimizer-start-processing").hide();
        $(
          "#wc-nero-ai-image-optimizer-select-images, #wc-nero-ai-image-optimizer-clear-all"
        ).hide();
        ensureStartOverButton();
        $("#wc-nero-ai-start-over")
          .prop("disabled", isProcessing || isActionWorking)
          .show();
        // Re-evaluate action bar for current list (after deletion, etc.)
        if (typeof maybeRenderActionBar === "function") {
          maybeRenderActionBar();
        }
      }

      var start = (currentPage - 1) * pageSize;
      var end = start + pageSize;
      var pageItems = list.slice(start, end);
      pageItems.forEach(function (attachment) {
        grid.append(buildItemHtml(attachment));
        // Apply cached status if any
        var cached = statusMap[attachment.id];
        if (cached) {
          var $status = $(
            `.wc-nero-ai-image-optimizer-image-item[data-id="${attachment.id}"] .wc-nero-ai-image-optimizer-image-status`
          );
          $status
            .text(cached.text)
            .removeClass("pending processing success error")
            .addClass(cached.status);
          // Hide credit badge if this item is already in terminal state
          if (cached.status === "success" || cached.status === "error") {
            $(
              `.wc-nero-ai-image-optimizer-image-item[data-id="${attachment.id}"] .wc-nero-ai-credit-badge`
            ).hide();
          }
        }
        // Ensure remove button stays hidden while processing/actions or for non-terminal statuses
        (function enforceRemoveVisibility() {
          var $row = $(
            `.wc-nero-ai-image-optimizer-image-item[data-id="${attachment.id}"]`
          );
          var $remove = $row.find(".wc-nero-ai-image-optimizer-remove-btn");
          var st = cached && cached.status;
          var nonTerminal = st === "pending" || st === "processing";
          var shouldHide = isProcessing || isActionWorking || nonTerminal;
          $remove.toggle(!shouldHide);
        })();
        // Apply cached meta if any
        var meta = metaMap[attachment.id];
        if (meta) {
          var $row = $(
            `.wc-nero-ai-image-optimizer-image-item[data-id="${attachment.id}"]`
          );
          var $img = $row.find("img");
          if (
            meta.thumb_url ||
            meta.full_url ||
            composedUrlMap[attachment.id]
          ) {
            var baseSrc =
              composedUrlMap[attachment.id] || meta.thumb_url || meta.full_url;
            var newSrc = baseSrc;
            if (
              typeof baseSrc === "string" &&
              baseSrc.indexOf("blob:") !== 0 &&
              baseSrc.indexOf("data:") !== 0
            ) {
              newSrc = addCacheBusting(baseSrc);
            }
            $img.attr("src", newSrc);
            // Update preview url on the row to use higher quality when available
            try {
              $row.attr("data-preview", baseSrc);
            } catch (e) {}
          }
          if (meta.filename) {
            var $name = $row.find(".wc-nero-ai-image-optimizer-image-name");
            $name
              .attr("data-full-name", meta.filename)
              .attr("title", meta.filename)
              .text(meta.filename);
          }
          var parts = [];
          if (typeof meta.filesize === "number")
            parts.push(humanFileSize(meta.filesize));
          if (meta.width && meta.height)
            parts.push(meta.width + "×" + meta.height);
          if (meta.mime) {
            var st2 = meta.mime.split("/")[1] || meta.mime;
            parts.push(st2.toUpperCase());
          }
          var txt = parts.filter(Boolean).join(" • ");
          var $details = $row.find(".wc-nero-ai-image-optimizer-image-details");
          if ($details.length) {
            $details.text(txt);
          } else if (txt) {
            $row
              .find(".wc-nero-ai-image-optimizer-image-info")
              .append(
                '<div class="wc-nero-ai-image-optimizer-image-details">' +
                  txt +
                  "</div>"
              );
          }
        }
      });

      renderPagination(list.length);
      // After (re)rendering, apply color backgrounds for successful items if applicable
      applyColorBackgroundsForCurrentList();
    }

    $(".wc-nero-ai-image-optimizer-files-count").text(
      "Selected Files (" + list.length + ")"
    );

    // Credits banner should appear when there are selected images
    try {
      renderCreditsBanner();
    } catch (e) {}

    // Apply cache busting to all newly rendered images to ensure fresh thumbnails
    try {
      grid.find("img").each(function () {
        var $img = $(this);
        var currentSrc = $img.attr("src");
        if (currentSrc && currentSrc.indexOf("cv=") === -1) {
          var cacheBusted = addCacheBusting(currentSrc);
          $img.attr("src", cacheBusted);
        }
      });
    } catch (e) {}

    // Apply truncation after DOM updated
    applyFilenameTruncation();
    // Avoid re-showing Start button in completed header mode
    if (!(!isProcessing && areAllCurrentItemsTerminalForUI())) {
      updateStartButtonState();
    }
    // Keep Add new Background button state in sync
    updateAddBackgroundButton();
    if (currentMode === "change-bg") maybeShowBgWarning();
    // Refresh per-item credit badges
    updateCreditBadges();
  }

  // Delegated: remove item
  $(document).on(
    "click",
    ".wc-nero-ai-image-optimizer-remove-btn",
    function () {
      var $item = $(this).closest(".wc-nero-ai-image-optimizer-image-item");
      var id = $item.data("id");
      var list = getList().filter(function (img) {
        return img.id !== id;
      });
      setList(list);
      // Free any blob preview held for this item (delay revoke to avoid revoking while UI still referencing)
      try {
        setTimeout(function () {
          revokeComposedUrl(id);
        }, 1500);
      } catch (e) {}
      // If list becomes empty, fully reset UI to initial state
      if (getList().length === 0 && !isProcessing) {
        clearAndResetUI();
        return;
      }
      // Remove row without re-rendering the whole grid
      try {
        $item.remove();
      } catch (e) {}
      // Adjust current page if this deletion causes an empty page while there are previous pages
      try {
        var totalAfter = getList().length;
        var totalPagesAfter = Math.max(1, Math.ceil(totalAfter / pageSize));
        if (currentPage > totalPagesAfter) currentPage = totalPagesAfter;
        var pageStartIndex = (currentPage - 1) * pageSize;
        if (pageStartIndex >= totalAfter && currentPage > 1) {
          currentPage -= 1;
        }
      } catch (e) {}
      // Update header count and pagination only
      try {
        $(".wc-nero-ai-image-optimizer-files-count").text(
          "Selected Files (" + getList().length + ")"
        );
      } catch (e) {}
      renderPagination(getList().length);
      // Keep current page items filled by appending the next item from the list (no reload of others)
      try {
        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;
        var desired = getList().slice(start, end);
        var desiredMap = {};
        for (var i = 0; i < desired.length; i++)
          desiredMap[desired[i].id] = desired[i];
        var $grid = $(".wc-nero-ai-image-optimizer-images-grid");
        var existing = {};
        $grid.find(".wc-nero-ai-image-optimizer-image-item").each(function () {
          existing[$(this).data("id")] = true;
        });
        for (var k in desiredMap) {
          if (!existing[k]) {
            var att = desiredMap[k];
            $grid.append(buildItemHtml(att));
            // Apply cached status
            (function applyStatusAndMeta(attachment) {
              var cached = statusMap[attachment.id];
              if (cached) {
                var $status = $(
                  '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                    attachment.id +
                    '"] .wc-nero-ai-image-optimizer-image-status'
                );
                $status
                  .text(cached.text)
                  .removeClass("pending processing success error")
                  .addClass(cached.status);
                if (cached.status === "success" || cached.status === "error") {
                  $(
                    '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                      attachment.id +
                      '"] .wc-nero-ai-credit-badge'
                  ).hide();
                }
                var $row = $(
                  '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                    attachment.id +
                    '"]'
                );
                var $removeBtn = $row.find(
                  ".wc-nero-ai-image-optimizer-remove-btn"
                );
                var nonTerminal =
                  cached.status === "pending" || cached.status === "processing";
                var shouldHide = isProcessing || isActionWorking || nonTerminal;
                $removeBtn.toggle(!shouldHide);
              }
              // Apply cached meta
              var meta = metaMap[attachment.id];
              if (meta) {
                var $row2 = $(
                  '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                    attachment.id +
                    '"]'
                );
                var $img = $row2.find("img");
                var baseSrc =
                  composedUrlMap[attachment.id] ||
                  meta.thumb_url ||
                  meta.full_url;
                var newSrc = baseSrc;
                if (
                  typeof baseSrc === "string" &&
                  baseSrc.indexOf("blob:") !== 0 &&
                  baseSrc.indexOf("data:") !== 0
                ) {
                  newSrc = addCacheBusting(baseSrc);
                }
                if ($img.length) $img.attr("src", newSrc);
                try {
                  $row2.attr("data-preview", baseSrc);
                } catch (e) {}
                if (meta.filename) {
                  var $name = $row2.find(
                    ".wc-nero-ai-image-optimizer-image-name"
                  );
                  $name
                    .attr("data-full-name", meta.filename)
                    .attr("title", meta.filename)
                    .text(meta.filename);
                  try {
                    applyFilenameTruncation();
                  } catch (e) {}
                }
              }
            })(att);
          }
        }
      } catch (e) {}
      // Keep related UI in sync
      try {
        renderCreditsBanner();
      } catch (e) {}
      try {
        updateAddBackgroundButton();
      } catch (e) {}
      try {
        updateStartButtonState();
      } catch (e) {}
      try {
        renderGlobalActionBar();
      } catch (e) {}
    }
  );

  // Delegated: page number click
  $(document).on(
    "click",
    ".wc-nero-ai-image-optimizer-pagination-page",
    function () {
      var p = parseInt($(this).attr("data-page"), 10);
      if (!isNaN(p)) {
        currentPage = p;
        renderSelectedImages();
      }
    }
  );

  // Prev/Next handlers
  $("#wc-nero-ai-pagination-prev").on("click", function () {
    if (currentPage > 1) {
      currentPage -= 1;
      renderSelectedImages();
    }
  });
  $("#wc-nero-ai-pagination-next").on("click", function () {
    var totalPages = Math.ceil(getList().length / pageSize) || 1;
    if (currentPage < totalPages) {
      currentPage += 1;
      renderSelectedImages();
    }
  });

  // --- Batch Processing Logic ---
  $("#wc-nero-ai-image-optimizer-start-processing").on("click", function (e) {
    e.preventDefault();
    var btn = $(this);
    var apiKey = $("#wc_nero_ai_image_optimizer_api_key").val();
    var errorDiv = $("#wc-nero-ai-batch-error");

    if (!apiKey) {
      errorDiv.text("Invalid API Key. Please enter a valid API Key.").show();
      return;
    } else {
      errorDiv.hide().text("");
    }

    if (getList().length === 0) {
      errorDiv.text("Please select images to process.").show();
      return;
    }

    // reset header buttons for processing state
    $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
    // Hide any previous result banner when starting a new run
    hideResultBanner();
    // Reset last batch cache for a new run
    lastBatchResults = [];
    lastBatchFinished = false;
    $("#wc-nero-ai-image-optimizer-select-images").show();
    $("#wc-nero-ai-image-optimizer-clear-all").hide();
    btn
      .removeClass("wc-nero-ai-image-optimizer-btn-secondary")
      .addClass("wc-nero-ai-image-optimizer-btn-primary");
    isProcessing = true;
    isActionWorking = true;
    ensureStartOverButton();
    $("#wc-nero-ai-start-over").prop("disabled", true);
    $("#wc-nero-ai-image-optimizer-select-images").prop("disabled", true);
    btn.prop("disabled", true).text("Processing...");
    $(".wc-nero-ai-image-optimizer-remove-btn").hide();
    // Disable Add new Background while processing (visible per visibility rules)
    updateAddBackgroundButton();
    // Ensure badges reflect latest credit rule before processing starts
    updateCreditBadges();
    // Hide retry button and re-render action bar for processing state
    renderGlobalActionBar();

    var filesToProcess = getList().map((img) => ({
      id: img.id,
      file_path: img.filename,
    }));
    var maxConcurrency = 3;
    var queue = filesToProcess.slice();
    var running = 0;
    var results = [];
    var pendingTasks = {};
    var total = filesToProcess.length;
    var taskIdList = [];
    var savingInFlight = 0;

    // Fatal API error control (e.g., API key invalid/expired/insufficient credits)
    var fatalApiStopped = false;
    function mapApiErrorMessage(code, fallback) {
      var ERR_MAP = {
        11002: "The API key is invalid",
        11003: "The API key is expired",
        11004: "The remaining credits of API key are insufficient",
      };
      return (code && ERR_MAP[code]) || fallback || "Request failed";
    }
    function abortRemainingWithFatal(apiCode, errMsg) {
      if (fatalApiStopped) return;
      fatalApiStopped = true;
      // Stop creating further tasks by clearing the queue and marking remaining as failed
      var displayMsg = mapApiErrorMessage(apiCode, errMsg);
      // Copy out the pending queue and clear it to prevent startNext from launching more
      var pending = queue.slice();
      queue.length = 0;
      for (var i = 0; i < pending.length; i++) {
        var f = pending[i];
        updateImageStatus(f.id, "error", "Failed");
        results.push({ file: f.file_path, result: displayMsg });
      }
      // Surface a single toast for the fatal reason
      try {
        showToast(displayMsg);
      } catch (e) {}
      updateProgress();
    }

    // Determine whether any visible items are still in non-terminal state
    function hasAnyProcessingVisible() {
      var pendingCount = $(
        ".wc-nero-ai-image-optimizer-image-status.pending"
      ).length;
      var processingCount = $(
        ".wc-nero-ai-image-optimizer-image-status.processing"
      ).length;
      return pendingCount + processingCount > 0;
    }

    // Unified check to decide whether we can call finishProcessing safely
    function shouldFinishNow() {
      var queuesEmpty =
        queue.length === 0 &&
        running === 0 &&
        Object.keys(pendingTasks).length === 0 &&
        taskIdList.length === 0;
      var allTerminal =
        typeof areAllItemsTerminal === "function" && areAllItemsTerminal();
      return queuesEmpty && allTerminal && !hasAnyProcessingVisible();
    }

    function startNext() {
      updateProgress();
      while (running < maxConcurrency && queue.length > 0) {
        var file = queue.shift();
        running++;
        processSingle(file);
      }
    }

    function processSingle(file) {
      updateImageStatus(file.id, "pending", "Pending");
      var postData = {
        action: "wc_nero_ai_process_single_image_task",
        attachment_id: file.id,
        nonce: wcNeroAiImageOptimizerVars.nonce,
      };
      // When in change-bg mode with an image background, instruct backend to use BackgroundChanger
      if (
        currentMode === "change-bg" &&
        selectedBackground &&
        selectedBackground.type === "image" &&
        selectedBackground.value
      ) {
        postData.mode = "change-bg";
        postData.bg_type = "image";
        postData.background_url = selectedBackground.value;
      }
      $.post(wcNeroAiImageOptimizerVars.ajax_url, postData)
        .done(function (response) {
          running--;
          if (response.success && response.data.task_id) {
            pendingTasks[response.data.task_id] = {
              attachmentId: file.id,
              filePath: file.file_path,
            };
            taskIdList.push(response.data.task_id);
            updateImageStatus(file.id, "pending", "Pending");
          } else {
            var errMsg =
              (response && response.data && response.data.message) ||
              "Failed to create task";
            var apiCode =
              (response && response.data && response.data.api_code) || null;
            var ERR_MAP = {
              11002: "The API key is invalid",
              11003: "The API key is expired",
              11004: "The remaining credits of API key are insufficient",
            };
            var displayMsg =
              apiCode && ERR_MAP[apiCode] ? ERR_MAP[apiCode] : errMsg;
            if (apiCode === 11002 || apiCode === 11003 || apiCode === 11004) {
              showToast(displayMsg);
            }
            results.push({
              file: file.file_path,
              result: errMsg,
            });
            updateImageStatus(file.id, "error", "Failed");
            updateProgress();
            // Fatal errors: stop scheduling remaining tasks and mark them failed
            if (apiCode === 11002 || apiCode === 11003 || apiCode === 11004) {
              abortRemainingWithFatal(apiCode, errMsg);
            }
            // Only render action bar when all items are in terminal state
            if (typeof maybeRenderActionBar === "function") {
              maybeRenderActionBar();
            }
          }
          startNext();
        })
        .fail(function () {
          running--;
          results.push({ file: file.file_path, result: "Failed (AJAX error)" });
          updateImageStatus(file.id, "error", "Failed");
          updateProgress();
          // Only render action bar when all items are in terminal state
          if (typeof maybeRenderActionBar === "function") {
            maybeRenderActionBar();
          }
          startNext();
        });
    }

    function pollTasks() {
      if (taskIdList.length === 0) {
        if (shouldFinishNow()) {
          finishProcessing();
        } else {
          // tasks done but UI still finalizing or meta not ready
          setTimeout(pollTasks, 150);
        }
        return;
      }

      const CHUNK_SIZE = 10;
      // Take the next chunk of tasks to process
      const taskChunk = taskIdList.slice(0, CHUNK_SIZE);

      $.post(wcNeroAiImageOptimizerVars.ajax_url, {
        action: "wc_nero_ai_image_optimizer_batch_query_tasks",
        task_ids: taskChunk.join(","),
        nonce: wcNeroAiImageOptimizerVars.nonce,
      })
        .done(function (response) {
          let completedTaskIds = [];

          if (
            response.success &&
            response.data &&
            response.data.data &&
            Array.isArray(response.data.data.tasks)
          ) {
            var tasks = response.data.data.tasks;
            tasks.forEach(function (task) {
              var tid = task.task_id;
              if (!tid || !pendingTasks[tid]) return;

              // Map backend status to four UI states
              if (task.status === "pending") {
                updateImageStatus(
                  pendingTasks[tid].attachmentId,
                  "pending",
                  "Pending"
                );
              } else if (task.status === "running") {
                var percentTxt =
                  typeof task.progress === "number"
                    ? `Processing ${task.progress}%`
                    : "Processing";
                updateImageStatus(
                  pendingTasks[tid].attachmentId,
                  "processing",
                  percentTxt
                );
              }

              if (task.status === "done" || task.status === "failed") {
                var originalTaskData = pendingTasks[tid];
                if (
                  task.status === "done" &&
                  task.result &&
                  task.result.output
                ) {
                  // Mark as 100% processed; if in change-bg mode with a selected background,
                  // we will compose foreground onto background into a new image (data URL),
                  // update preview, and stash the data for Replace/Download.
                  updateImageStatus(
                    originalTaskData.attachmentId,
                    "processing",
                    "Processing 100%"
                  );
                  var composedDataUrl = null;
                  var bgSel =
                    selectedBackground && selectedBackground.value
                      ? selectedBackground
                      : null;
                  results.push({
                    file: originalTaskData.filePath,
                    result: "Ready",
                    attachmentId: originalTaskData.attachmentId,
                    task: task,
                    data_url: composedDataUrl,
                  });
                  // Show preview in UI using task output (or composed data when available)
                  (function () {
                    var id = originalTaskData.attachmentId;
                    var outputUrl = task.result.output;
                    var $row = $(
                      '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                        id +
                        '"]'
                    );
                    var $img = $row.find("img");

                    function applyPreview(src) {
                      var base = src;
                      var previewSrc = base;
                      // Do not append query for blob: or data: URLs
                      if (
                        typeof base === "string" &&
                        base.indexOf("blob:") !== 0 &&
                        base.indexOf("data:") !== 0
                      ) {
                        previewSrc = addCacheBusting(base);
                      }
                      if ($img.length) {
                        $img.attr("src", previewSrc);
                      }
                      // Update row preview source for hover zoom to use latest processed image
                      try {
                        $row.attr("data-preview", base);
                      } catch (e) {}
                    }

                    // If in change-bg mode and a background is selected, compose via canvas
                    if (
                      currentMode === "change-bg" &&
                      bgSel &&
                      bgSel.type !== "image"
                    ) {
                      composeCanvasWithBackground(outputUrl, bgSel)
                        .then(function (res) {
                          // Persist blob and blob URL into current results cache
                          for (var i = 0; i < results.length; i++) {
                            if (results[i] && results[i].attachmentId === id) {
                              results[i].blob_url = res.url;
                              results[i].blob = res.blob;
                              break;
                            }
                          }
                          // Revoke previous blob URL to prevent memory leaks
                          revokeComposedUrl(id);
                          // Remember composed URL for re-render across paginations
                          composedUrlMap[id] = res.url;
                          // Preview and meta probing both use pure blob URL (no query)
                          applyPreview(res.url);
                          try {
                            img.src = addCacheBusting(res.url);
                          } catch (e) {}
                        })
                        .catch(function (err) {
                          applyPreview(outputUrl);
                          try {
                            img.src = addCacheBusting(outputUrl);
                          } catch (e) {}
                        });
                    } else {
                      applyPreview(outputUrl);
                    }

                    // filename change to new extension
                    var ext = (outputUrl.split(".").pop() || "")
                      .split("?")[0]
                      .toLowerCase();
                    var baseName = (originalTaskData.filePath || "").replace(
                      /\.[^.]+$/,
                      ""
                    );
                    var newName = baseName
                      ? baseName + "." + ext
                      : originalTaskData.filePath;
                    var $name = $row.find(
                      ".wc-nero-ai-image-optimizer-image-name"
                    );
                    if ($name.length && newName) {
                      $name
                        .attr("data-full-name", newName)
                        .attr("title", newName)
                        .text(newName);
                      try {
                        applyFilenameTruncation();
                      } catch (e) {}
                    }
                    // update details with width×height and type; try to read dimensions by loading image
                    var subtype = (ext || "").toUpperCase();
                    var updateDetails = function (w, h, sizeBytes) {
                      var parts = [];
                      if (typeof sizeBytes === "number")
                        parts.push(humanFileSize(sizeBytes));
                      if (w && h) parts.push(w + "×" + h);
                      if (subtype) parts.push(subtype);
                      var text = parts.filter(Boolean).join(" • ");
                      var $details = $row.find(
                        ".wc-nero-ai-image-optimizer-image-details"
                      );
                      if ($details.length) {
                        $details.text(text);
                      } else if (text) {
                        $row
                          .find(".wc-nero-ai-image-optimizer-image-info")
                          .append(
                            '<div class="wc-nero-ai-image-optimizer-image-details">' +
                              text +
                              "</div>"
                          );
                      }
                    };
                    var img = new Image();
                    img.onload = function () {
                      // cache meta
                      var chosen = composedUrlMap[id] || outputUrl;
                      metaMap[id] = {
                        thumb_url: chosen,
                        full_url: chosen,
                        filename: newName,
                        width: img.naturalWidth,
                        height: img.naturalHeight,
                        mime: ext ? "image/" + ext : "",
                      };
                      // First update with dimensions; then try to fetch size and update again
                      updateDetails(
                        img.naturalWidth,
                        img.naturalHeight,
                        metaMap[id].filesize
                      );
                      fetchFileSize(chosen, function (size) {
                        if (size && (!metaMap[id] || !metaMap[id].filesize)) {
                          metaMap[id] = metaMap[id] || {};
                          metaMap[id].filesize = size;
                        } else if (size) {
                          metaMap[id].filesize = size;
                        }
                        updateDetails(
                          img.naturalWidth,
                          img.naturalHeight,
                          size
                        );
                      });
                      updateImageStatus(id, "success", "Complete");
                      updateProgress();
                      // Apply frontend color background if needed
                      applyColorBackgroundToId(id);
                      // Only render action bar when all items are in terminal state
                      if (typeof maybeRenderActionBar === "function") {
                        maybeRenderActionBar();
                      }
                    };
                    img.onerror = function () {
                      var chosen = composedUrlMap[id] || outputUrl;
                      metaMap[id] = {
                        thumb_url: chosen,
                        full_url: chosen,
                        filename: newName,
                        mime: ext ? "image/" + ext : "",
                      };
                      updateDetails(null, null, null);
                      fetchFileSize(chosen, function (size) {
                        if (size) {
                          metaMap[id].filesize = size;
                          updateDetails(null, null, size);
                        }
                      });
                      updateImageStatus(id, "success", "Complete");
                      updateProgress();
                      // Apply frontend color background if needed
                      applyColorBackgroundToId(id);
                      // Only render action bar when all items are in terminal state
                      if (typeof maybeRenderActionBar === "function") {
                        maybeRenderActionBar();
                      }
                    };
                    var initial = composedUrlMap[id] || outputUrl;
                    img.src =
                      initial +
                      (initial.indexOf("?") !== -1 ||
                      initial.indexOf("blob:") === 0 ||
                      initial.indexOf("data:") === 0
                        ? ""
                        : "?v=" + Date.now());
                  })();
                } else {
                  results.push({
                    file: originalTaskData.filePath,
                    result: task.error || "Processing Failed",
                  });
                  updateImageStatus(
                    originalTaskData.attachmentId,
                    "error",
                    "Failed"
                  );
                  updateProgress();
                  // Only render action bar when all items are in terminal state
                  if (typeof maybeRenderActionBar === "function") {
                    maybeRenderActionBar();
                  }
                }
                delete pendingTasks[tid];
                completedTaskIds.push(tid);
              }
            });
          }

          // Remove completed tasks from the main list
          if (completedTaskIds.length > 0) {
            taskIdList = taskIdList.filter(function (tid) {
              return completedTaskIds.indexOf(tid) === -1;
            });
          }
        })
        .fail(function (error) {
          // Polling error handling
        })
        .always(function () {
          updateProgress();
          // If there are still tasks, poll again quickly. Otherwise, wait.
          const nextPollTime = taskIdList.length > 0 ? 100 : 1000;
          const stillWorking =
            queue.length > 0 ||
            running > 0 ||
            savingInFlight > 0 ||
            taskIdList.length > 0 ||
            Object.keys(pendingTasks).length > 0;
          if (stillWorking) {
            setTimeout(pollTasks, nextPollTime);
          } else {
            // Only finish when all items have reached terminal state and no Processing 100% remains
            if (shouldFinishNow()) {
              finishProcessing();
            } else {
              // keep polling briefly until UI reaches terminal (e.g., Processing 100% -> Complete)
              setTimeout(pollTasks, 150);
            }
          }
        });
    }

    function updateProgress() {
      var finished = results.length;

      if (finished === total && shouldFinishNow()) {
        finishProcessing();
      }
    }

    function finishProcessing() {
      isProcessing = false;
      isActionWorking = false;
      btn.prop("disabled", false).text("Start Bulk Processing");
      $("#wc-nero-ai-batch-status").text("");
      // Show Start Over and restore controls, but delay Replace/Download until all items are terminal
      ensureStartOverButton();
      $("#wc-nero-ai-start-over")
        .prop("disabled", isProcessing || isActionWorking)
        .show();
      $("#wc-nero-ai-image-optimizer-select-images").prop("disabled", false);
      document.dispatchEvent(new CustomEvent("wc-nero-ai-refresh-credits"));
      $(".wc-nero-ai-image-optimizer-remove-btn").show();
      // Hide credits badges and banner after batch completed
      $(".wc-nero-ai-credit-badge").hide();
      $("#wc-nero-ai-credits-banner").hide();
      // Persist last batch summary for cross-tab rendering
      lastBatchResults = Array.isArray(results) ? results.slice() : [];
      lastBatchFinished = true;
      // Show result banner
      try {
        var successCount = lastBatchResults.filter(function (r) {
          return r && r.result === "Ready";
        }).length;
        renderCompletionBanner(total, successCount);
      } catch (e) {}
      // Render/restore action bar
      if (typeof maybeRenderActionBar === "function") {
        maybeRenderActionBar();
      }
      renderGlobalActionBar();
      // Hide Add new Background after processing finished
      updateAddBackgroundButton();
    }

    // Check if all items are in terminal states (success or error)
    function areAllItemsTerminal() {
      if (results.length !== total) return false;
      for (var i = 0; i < filesToProcess.length; i++) {
        var id = filesToProcess[i].id;
        var st = statusMap[id] && statusMap[id].status;
        if (st !== "success" && st !== "error") return false;
      }
      return true;
    }

    // Render action bar only when all items are terminal
    function maybeRenderActionBar() {
      if (!areAllItemsTerminal()) return;
      renderActionBar();
    }

    function renderActionBar() {
      // In completed state, reorganize header button group to show only three buttons in order: Start Over | Replace All | Download All
      var $group = $(
        ".wc-nero-ai-image-optimizer-files-header .wc-nero-ai-image-optimizer-btn-group"
      );
      if ($group.length === 0) return;

      // Ensure container exists only once
      var $replace = $("#wc-nero-ai-replace-all");
      var $download = $("#wc-nero-ai-download-all");
      if ($replace.length === 0) {
        $replace = $(
          '<button type="button" class="wc-nero-ai-image-optimizer-btn-primary" id="wc-nero-ai-replace-all"><span class="dashicons dashicons-database-import" style="vertical-align:middle;margin-right:6px;"></span>Replace All</button>'
        );
      }
      if ($download.length === 0) {
        $download = $(
          '<button type="button" class="wc-nero-ai-image-optimizer-btn-primary" id="wc-nero-ai-download-all" style="background:#28a745">Download All</button>'
        );
      }

      // Show Start Over (gray), hide Select/Clear and Start Processing
      ensureStartOverButton();
      var $start = $("#wc-nero-ai-image-optimizer-start-processing");
      $start.hide();
      $(
        "#wc-nero-ai-image-optimizer-select-images, #wc-nero-ai-image-optimizer-clear-all"
      ).hide();

      // prevent duplicates then append in order
      $group.find("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
      // Clear any previous appended action bar markup
      $("#wc-nero-ai-action-bar").remove();

      // order: Start Over (already in group via ensureStartOverButton) | Replace All | Download All
      hideActionTooltip();
      // If there are no successful results for CURRENT LIST, do not show Replace/Download buttons
      var list = getList();
      var idSet = {};
      if (Array.isArray(list)) {
        for (var i = 0; i < list.length; i++) {
          idSet[list[i].id] = true;
        }
      }
      var hasReady =
        results &&
        results.some(function (r) {
          return r && r.result === "Ready" && r.task && idSet[r.attachmentId];
        });
      if (hasReady) {
        $group.append($replace);
        $group.append($download);
        if (typeof bindActionTooltips === "function") {
          bindActionTooltips();
        }
      } else {
        $("#wc-nero-ai-replace-all, #wc-nero-ai-download-all").remove();
      }
    }

    function bindActionTooltips() {
      var replaceText = "Replace original images with processed versions";
      var downloadText =
        "Download processed images as new files (keeps originals)";
      $(document)
        .off(
          "mouseenter.wcNeroTip mouseleave.wcNeroTip focus.wcNeroTip blur.wcNeroTip",
          "#wc-nero-ai-replace-all, #wc-nero-ai-download-all"
        )
        .on(
          "mouseenter.wcNeroTip focus.wcNeroTip",
          "#wc-nero-ai-replace-all",
          function () {
            showActionTooltip($(this), replaceText);
          }
        )
        .on(
          "mouseleave.wcNeroTip blur.wcNeroTip",
          "#wc-nero-ai-replace-all",
          function () {
            hideActionTooltip();
          }
        )
        .on(
          "mouseenter.wcNeroTip focus.wcNeroTip",
          "#wc-nero-ai-download-all",
          function () {
            showActionTooltip($(this), downloadText);
          }
        )
        .on(
          "mouseleave.wcNeroTip blur.wcNeroTip",
          "#wc-nero-ai-download-all",
          function () {
            hideActionTooltip();
          }
        );
    }

    // Remove inner-scoped handlers; use global delegated handlers below

    startNext();
    pollTasks();
  });

  // Re-apply truncation on resize
  var debouncedTruncation = debounce(applyFilenameTruncation, 100);
  $(window).on("resize", debouncedTruncation);

  // Initial apply for any pre-rendered content
  applyFilenameTruncation();
  // Ensure initial UI state hides Start Bulk Processing when empty
  renderSelectedImages();
  // Sync Add new Background button on load
  updateAddBackgroundButton();
  // Sync credit badges on load
  updateCreditBadges();

  // Global delegated handlers for Replace/Download using cached results
  $(document).on("click", "#wc-nero-ai-replace-all", function () {
    var list = getList();
    var idSet = {};
    if (Array.isArray(list)) {
      for (var i = 0; i < list.length; i++) {
        idSet[list[i].id] = true;
      }
    }
    var ready = (lastBatchResults || []).filter(function (r) {
      return r && r.result === "Ready" && r.task && idSet[r.attachmentId];
    });
    if (ready.length === 0) return;
    var $btnReplace = $(this);
    var $btnDownload = $("#wc-nero-ai-download-all");
    // Set loading states
    var originalReplaceText = $btnReplace.text();
    $btnReplace.prop("disabled", true).text("Replacing...");
    $btnDownload.prop("disabled", true);
    isActionWorking = true;
    ensureStartOverButton();
    $("#wc-nero-ai-start-over").prop("disabled", true);
    $(".wc-nero-ai-image-optimizer-remove-btn").hide();
    var idx = 0;
    var successCount = 0;
    function next() {
      if (idx >= ready.length) {
        // Restore button states
        $btnReplace.prop("disabled", false).text(originalReplaceText);
        $btnDownload.prop("disabled", false);
        isActionWorking = false;
        ensureStartOverButton();
        $("#wc-nero-ai-start-over").prop(
          "disabled",
          isProcessing || isActionWorking
        );
        $(".wc-nero-ai-image-optimizer-remove-btn").show();
        // Update result banner
        try {
          renderCustomResultBanner(
            "Replacing Complete",
            ready.length,
            successCount,
            "Successfully Replaced"
          );
        } catch (e) {}
        showToast("All images replaced.");
        return;
      }
      var r = ready[idx++];
      var currentId = r.attachmentId;
      updateImageStatus(currentId, "processing", "Replacing...");

      // Prefer canvas-composed blob when available in current results cache
      var composed = null;
      for (var i = 0; i < (lastBatchResults || []).length; i++) {
        var item = lastBatchResults[i];
        if (item && item.attachmentId === r.attachmentId && item.blob) {
          composed = item;
          break;
        }
      }

      if (composed && composed.blob) {
        var fd = new FormData();
        fd.append("action", "wc_nero_ai_save_composed_image");
        fd.append("attachment_id", r.attachmentId);
        fd.append("nonce", wcNeroAiImageOptimizerVars.nonce);
        try {
          var fileObj = new File([composed.blob], "bg-changed.png", {
            type: composed.blob.type || "image/png",
          });
          fd.append("file", fileObj);
        } catch (e) {
          fd.append("file", composed.blob, "bg-changed.png");
        }
        $.ajax({
          url: wcNeroAiImageOptimizerVars.ajax_url,
          method: "POST",
          data: fd,
          processData: false,
          contentType: false,
        })
          .done(function (resp) {
            if (resp && resp.success) {
              successCount++;
              updateImageStatus(currentId, "success", "Replace successfully");
              // Try to refresh the corresponding tile in wp.media
              try {
                refreshWpMediaAttachment(r.attachmentId, resp.data || {});
              } catch (e) {}
              // Also refresh the thumbnail in current plugin interface
              try {
                var $currentThumb = $(
                  '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                    r.attachmentId +
                    '"] img'
                );
                if ($currentThumb.length && resp.data && resp.data.thumb_url) {
                  var cacheBusted = addCacheBusting(resp.data.thumb_url);
                  $currentThumb.attr("src", cacheBusted);
                }
              } catch (e) {}
            } else {
              updateImageStatus(currentId, "error", "Replace failed");
            }
          })
          .fail(function () {
            updateImageStatus(currentId, "error", "Replace failed");
          })
          .always(next);
      } else {
        $.post(wcNeroAiImageOptimizerVars.ajax_url, {
          action: "wc_nero_ai_save_processed_image",
          attachment_id: r.attachmentId,
          task: JSON.stringify(r.task),
          nonce: wcNeroAiImageOptimizerVars.nonce,
        })
          .done(function (resp) {
            if (resp && resp.success) {
              successCount++;
              updateImageStatus(currentId, "success", "Replace successfully");
              // Try to refresh the corresponding tile in wp.media
              try {
                refreshWpMediaAttachment(r.attachmentId, resp.data || {});
              } catch (e) {}
              // Also refresh the thumbnail in current plugin interface
              try {
                var $currentThumb = $(
                  '.wc-nero-ai-image-optimizer-image-item[data-id="' +
                    r.attachmentId +
                    '"] img'
                );
                if ($currentThumb.length && resp.data && resp.data.thumb_url) {
                  var cacheBusted = addCacheBusting(resp.data.thumb_url);
                  $currentThumb.attr("src", cacheBusted);
                }
              } catch (e) {}
            } else {
              updateImageStatus(currentId, "error", "Replace failed");
            }
          })
          .fail(function () {
            updateImageStatus(currentId, "error", "Replace failed");
          })
          .always(next);
      }
    }
    next();
  });

  $(document).on("click", "#wc-nero-ai-download-all", function () {
    var list = getList();
    var idSet = {};
    if (Array.isArray(list)) {
      for (var i = 0; i < list.length; i++) {
        idSet[list[i].id] = true;
      }
    }
    var ready = (lastBatchResults || []).filter(function (r) {
      return r && r.result === "Ready" && r.task && idSet[r.attachmentId];
    });
    if (ready.length === 0) return;
    var modeToSave = currentMode === "change-bg" ? "change-bg" : "remove-bg";
    var idx = 0;
    var $btnDownload = $(this);
    var $btnReplace = $("#wc-nero-ai-replace-all");
    var originalDownloadText = $btnDownload.text();
    // Set loading states
    $btnDownload.prop("disabled", true).text("Downloading...");
    $btnReplace.prop("disabled", true);
    isActionWorking = true;
    ensureStartOverButton();
    $("#wc-nero-ai-start-over").prop("disabled", true);
    $(".wc-nero-ai-image-optimizer-remove-btn").hide();
    var successCount = 0;
    function next() {
      if (idx >= ready.length) {
        // Restore button states
        $btnDownload.prop("disabled", false).text(originalDownloadText);
        $btnReplace.prop("disabled", false);
        isActionWorking = false;
        ensureStartOverButton();
        $("#wc-nero-ai-start-over").prop(
          "disabled",
          isProcessing || isActionWorking
        );
        $(".wc-nero-ai-image-optimizer-remove-btn").show();
        // Update result banner
        try {
          renderCustomResultBanner(
            "Downloading Complete",
            ready.length,
            successCount,
            "Successfully Downloaded"
          );
        } catch (e) {}
        showToast("All images downloaded.");
        return;
      }
      var r = ready[idx++];
      var currentId = r.attachmentId;
      updateImageStatus(currentId, "processing", "Downloading...");

      // Prefer canvas-composed blob when available
      var composed = null;
      for (var i = 0; i < (lastBatchResults || []).length; i++) {
        var item = lastBatchResults[i];
        if (item && item.attachmentId === r.attachmentId && item.blob) {
          composed = item;
          break;
        }
      }

      if (composed && composed.blob) {
        var fd = new FormData();
        fd.append("action", "wc_nero_ai_download_composed_to_folder");
        fd.append("attachment_id", r.attachmentId);
        fd.append("mode", modeToSave);
        fd.append("nonce", wcNeroAiImageOptimizerVars.nonce);
        try {
          var fileObj2 = new File([composed.blob], "bg-changed.png", {
            type: composed.blob.type || "image/png",
          });
          fd.append("file", fileObj2);
        } catch (e) {
          fd.append("file", composed.blob, "bg-changed.png");
        }
        $.ajax({
          url: wcNeroAiImageOptimizerVars.ajax_url,
          method: "POST",
          data: fd,
          processData: false,
          contentType: false,
        })
          .done(function (resp) {
            if (resp && resp.success) {
              successCount++;
              updateImageStatus(currentId, "success", "Download successfully");
            } else {
              updateImageStatus(currentId, "error", "Download failed");
            }
          })
          .fail(function () {
            updateImageStatus(currentId, "error", "Download failed");
          })
          .always(next);
      } else {
        $.post(wcNeroAiImageOptimizerVars.ajax_url, {
          action: "wc_nero_ai_download_processed_to_folder",
          attachment_id: r.attachmentId,
          task: JSON.stringify(r.task),
          mode: modeToSave,
          nonce: wcNeroAiImageOptimizerVars.nonce,
        })
          .done(function (resp) {
            if (resp && resp.success) {
              successCount++;
              updateImageStatus(currentId, "success", "Download successfully");
            } else {
              updateImageStatus(currentId, "error", "Download failed");
            }
          })
          .fail(function () {
            updateImageStatus(currentId, "error", "Download failed");
          })
          .always(next);
      }
    }
    next();
  });

  // Ensure global action bar can be re-rendered when returning to a completed tab
  $(document).on("wc-nero-ai-rerender-actions", function () {
    renderGlobalActionBar();
  });

  // Render or update credits banner positioned above the grid
  function renderCreditsBanner() {
    var list = getList();
    var count = Array.isArray(list) ? list.length : 0;
    var $grid = $(".wc-nero-ai-image-optimizer-images-grid");
    if ($grid.length === 0) return;
    var $banner = $("#wc-nero-ai-credits-banner");
    if ($banner.length === 0) {
      $banner = $(
        '<div id="wc-nero-ai-credits-banner" class="wc-nero-ai-credits-banner" />'
      );
      $grid.before($banner);
    }
    // Hide when completed (all items terminal and not processing)
    var completedHeader = !isProcessing && areAllCurrentItemsTerminalForUI();
    if (count > 0 && !completedHeader) {
      var per = 1;
      if (
        currentMode === "change-bg" &&
        selectedBackground &&
        selectedBackground.value &&
        selectedBackground.type === "image"
      ) {
        per = 2; // Change BG with background image costs 2 credits per task
      }
      var total = count * per;
      var msg =
        total +
        " Credits will be used (Per image: " +
        per +
        " Credit" +
        (per > 1 ? "s" : "") +
        ")";
      $banner.text(msg).show();
      try {
        updateCreditBadges();
      } catch (e) {}
    } else {
      $banner.hide();
    }
  }

  function createPreview(url, pageX, pageY, showChecker) {
    destroyPreview();
    if (!url) return;
    var $p = $(
      '<div class="wc-nero-ai-bg-preview" id="wc-nero-ai-bg-preview"></div>'
    );
    // Add cache busting to background image URL
    var cacheBustedUrl = addCacheBusting(url);

    // Build children: optional checker as background element, and the image above it
    try {
      var img = new Image();
      img.onload = function () {
        var maxSide = 300; // preview box size
        var iw = img.naturalWidth || maxSide;
        var ih = img.naturalHeight || maxSide;
        var scale = Math.min(maxSide / iw, maxSide / ih);
        var dw = Math.max(1, Math.round(iw * scale));
        var dh = Math.max(1, Math.round(ih * scale));

        if (showChecker) {
          var size = 16,
            c1 = "#cfcfcf",
            c2 = "#ffffff";
          var $checker = $('<div class="wc-nero-ai-bg-checker"></div>').css({
            position: "absolute",
            left: "50%",
            top: "50%",
            width: dw + "px",
            height: dh + "px",
            transform: "translate(-50%, -50%)",
            background: `conic-gradient(${c1} 25%, ${c2} 0 50%, ${c1} 0 75%, ${c2} 0)
                         0 0 / ${size * 2}px ${size * 2}px`,
            zIndex: 0,
          });
          $p.append($checker);
        }

        var $img = $('<img class="wc-nero-ai-bg-preview-img" alt="" />')
          .attr("src", cacheBustedUrl)
          .css({
            position: "absolute",
            left: "50%",
            top: "50%",
            width: dw + "px",
            height: dh + "px",
            transform: "translate(-50%, -50%)",
            objectFit: "contain",
            zIndex: 1,
            pointerEvents: "none",
          });
        $p.append($img);
      };
      img.src = cacheBustedUrl;
    } catch (e) {
      // Fallback: simple image-only preview
      var $img = $('<img class="wc-nero-ai-bg-preview-img" alt="" />')
        .attr("src", cacheBustedUrl)
        .css({
          position: "absolute",
          left: "50%",
          top: "50%",
          maxWidth: "100%",
          maxHeight: "100%",
          transform: "translate(-50%, -50%)",
          objectFit: "contain",
          zIndex: 1,
          pointerEvents: "none",
        });
      $p.append($img);
    }

    $("body").append($p);
    movePreview(pageX, pageY);
  }
  function movePreview(pageX, pageY) {
    var $p = $("#wc-nero-ai-bg-preview");
    if (!$p.length) return;
    var w = 300,
      h = 300,
      offset = 16;
    var left = pageX + offset,
      top = pageY + offset;
    var vw = $(window).width(),
      vh = $(window).height();
    var st = $(window).scrollTop(),
      sl = $(window).scrollLeft();
    if (left + w > sl + vw) left = pageX - w - offset;
    if (top + h > st + vh) top = pageY - h - offset;
    $p.css({ left: left + "px", top: top + "px" });
  }
  function destroyPreview() {
    $("#wc-nero-ai-bg-preview").remove();
  }

  // --- Small viewport guard (<= 960px) ---
  function ensureSmallViewportStyles() {
    if (document.getElementById("wc-nero-ai-small-overlay-style")) return;
    var css = [
      "#wc-nero-ai-small-overlay{position:fixed;background:#fff;z-index:99999;display:flex;align-items:center;justify-content:center;padding:24px;box-shadow:0 0 0 9999px rgba(255,255,255,0)}",
      "#wc-nero-ai-small-overlay .inner{max-width:980px;margin:0 auto;text-align:center}",
      "#wc-nero-ai-small-overlay h1{font-size:32px;line-height:1.3;margin:0 0 16px;color:#111}",
      "#wc-nero-ai-small-overlay p{font-size:18px;line-height:1.6;margin:0;color:#333}",
    ].join("");
    var style = document.createElement("style");
    style.id = "wc-nero-ai-small-overlay-style";
    style.type = "text/css";
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);
  }
  function ensureSmallViewportOverlay() {
    ensureSmallViewportStyles();
    var el = document.getElementById("wc-nero-ai-small-overlay");
    if (!el) {
      el = document.createElement("div");
      el.id = "wc-nero-ai-small-overlay";
      el.setAttribute("role", "dialog");
      el.setAttribute("aria-modal", "true");
      el.style.display = "none";
      el.innerHTML =
        '<div class="inner"><h1>📏 Your browser window is too small</h1><p>Please resize your browser window to be at least 960px wide for a better user experience.</p></div>';
      document.body.appendChild(el);
    }
    return el;
  }
  function getPluginContainerRect() {
    // Try to find a stable root element of the plugin area
    var root =
      document.querySelector(".wc-nero-ai-image-optimizer-wrap") ||
      document.querySelector("#wc-nero-ai-image-optimizer") ||
      (function () {
        var el = document.querySelector(".wc-nero-ai-image-optimizer-tabs");
        return el ? el.parentElement : null;
      })() ||
      document.querySelector(".wc-nero-ai-image-optimizer-files-header") ||
      document.querySelector(".wc-nero-ai-image-optimizer-images-grid");
    if (!root) return null;
    try {
      return root.getBoundingClientRect();
    } catch (e) {
      return null;
    }
  }
  function updateSmallViewportOverlay() {
    var isSmall =
      (window.innerWidth || document.documentElement.clientWidth) < 960;
    var overlay = ensureSmallViewportOverlay();
    if (!isSmall) {
      overlay.style.display = "none";
      return;
    }
    var rect = getPluginContainerRect();
    if (!rect) {
      overlay.style.display = "none";
      return;
    }
    overlay.style.display = "flex";
    overlay.style.left = rect.left + "px";
    overlay.style.top = rect.top + "px";
    overlay.style.width = rect.width + "px";
    overlay.style.height = rect.height + "px";
  }
  // Init and bind
  updateSmallViewportOverlay();
  var debouncedOverlay = debounce(updateSmallViewportOverlay, 100);
  $(window).on("resize", debouncedOverlay);
  $(window).on("scroll", debouncedOverlay);

  // Restore hover zoom preview for grid thumbnails
  $(document)
    .off(
      "mouseenter.wcNeroGridPrev mousemove.wcNeroGridPrev mouseleave.wcNeroGridPrev",
      ".wc-nero-ai-image-optimizer-image-item img"
    )
    .on(
      "mouseenter.wcNeroGridPrev",
      ".wc-nero-ai-image-optimizer-image-item img",
      function (e) {
        var $row = $(this).closest(".wc-nero-ai-image-optimizer-image-item");
        var url = $row.attr("data-preview") || $(this).attr("src");
        var isSuccess = $row
          .find(".wc-nero-ai-image-optimizer-image-status")
          .hasClass("success");
        var showChecker = currentMode === "remove-bg" && isSuccess;
        createPreview(url, e.pageX, e.pageY, showChecker);
      }
    )
    .on(
      "mousemove.wcNeroGridPrev",
      ".wc-nero-ai-image-optimizer-image-item img",
      (function () {
        var throttledGridMove = throttle(function (pageX, pageY) {
          movePreview(pageX, pageY);
        }, 32);
        return function (e) {
          throttledGridMove(e.pageX, e.pageY);
        };
      })()
    )
    .on(
      "mouseleave.wcNeroGridPrev",
      ".wc-nero-ai-image-optimizer-image-item img",
      function () {
        destroyPreview();
      }
    );

  // Cleanup on page unload to prevent memory leaks
  $(window).on("beforeunload", function () {
    try {
      destroyPreview();
    } catch (e) {}
    try {
      revokeAllComposedUrls();
    } catch (e) {}
  });
});
