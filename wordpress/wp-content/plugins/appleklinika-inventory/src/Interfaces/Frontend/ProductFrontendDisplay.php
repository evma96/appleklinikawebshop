<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Frontend;

use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class ProductFrontendDisplay
{
    private bool $hasRendered = false;

    public function __construct(
        private readonly WooProductConditionRepository $conditionRepository,
        private readonly DeviceCatalogRepository $deviceCatalogRepository
    ) {
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueWooCommerceScripts']);
        add_action('wp_head', [$this, 'renderStyles']);
        add_action('wp_footer', [$this, 'renderScripts']);
        add_action('woocommerce_before_single_product', [$this, 'removeDefaultProductBlocks'], 1);
        add_action('woocommerce_before_single_product', [$this, 'renderDynamicProductPage'], 20);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addBatteryExtraToCartItem'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'renderBatteryExtraCartItemData'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyBatteryExtraCartPrice']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addBatteryExtraToOrderItem'], 10, 4);
        add_shortcode('appleklinika_single_product', [$this, 'renderSingleProductShortcode']);
    }

    public function enqueueWooCommerceScripts(): void
    {
        if (! is_product()) {
            return;
        }

        wp_enqueue_script('wc-add-to-cart');
        wp_enqueue_script('wc-cart-fragments');
    }

    public function removeDefaultProductBlocks(): void
    {
        remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);
        remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
    }

    public function renderStyles(): void
    {
        if (is_admin()) {
            return;
        }

        echo '<style>
            body.single-product main,body.single-product .ak-single-product-page{max-width:1120px;margin:0 auto;padding:12px 12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#18202b}
            body.single-product div.product{display:block}
            body.single-product .summary.entry-summary{display:none}
            body.single-product .woocommerce-tabs{margin-top:14px}
            body.single-product main .woocommerce-breadcrumb,body.single-product main .woocommerce-notices-wrapper{display:none}
            .appleklinika-product-shell,.appleklinika-product-shell *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .appleklinika-product-shell{display:grid;grid-template-columns:minmax(250px,.78fr) minmax(300px,1fr) minmax(300px,.82fr);gap:14px;align-items:start}
            .appleklinika-product-shell button{font-family:inherit}
            .appleklinika-product-data{display:grid;gap:10px;align-self:start}
            .appleklinika-config-group{display:grid;gap:6px}
            .appleklinika-config-group__title{margin:0;color:#313a4a;font-size:13px;line-height:1.2;font-weight:850}
            .appleklinika-config-group__title span{font-weight:950}
            .appleklinika-config-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;align-items:stretch}
            .appleklinika-config-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;align-items:stretch}
            .appleklinika-config-card{position:relative;display:flex;min-height:72px;height:100%;padding:10px 34px 10px 10px;border:1.5px solid #d8e0eb;border-radius:8px;background:#fff;color:#2e3746;text-align:left;text-decoration:none;cursor:pointer;box-shadow:0 2px 7px rgba(17,24,32,.032);transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
            .appleklinika-config-card:hover{border-color:#9ebcff;background:#fbfdff}
            .appleklinika-config-card.is-selected,.appleklinika-config-card.is-active{border-color:#286ff0;background:#f7faff;box-shadow:0 0 0 2px rgba(40,111,240,.1)}
            .appleklinika-config-card.is-selected:after,.appleklinika-config-card.is-active:after{content:"✓";position:absolute;top:8px;right:8px;width:16px;height:16px;display:flex;align-items:center;justify-content:center;color:#286ff0;font-size:12px;line-height:1;font-weight:950}
            .appleklinika-config-card__image{flex:0 0 46px;width:46px;height:46px;object-fit:contain;border-radius:7px;background:#f7f8fa}
            .appleklinika-config-card__content{display:flex;min-width:0;flex:1;height:100%;flex-direction:column;justify-content:flex-start;gap:5px}
            .appleklinika-config-card__label{display:-webkit-box;min-height:34px;overflow:hidden;color:#263242;font-size:14px;line-height:1.18;font-weight:900;-webkit-box-orient:vertical;-webkit-line-clamp:2}
            .appleklinika-config-card__meta{display:block;overflow:hidden;max-width:100%;margin-top:auto;color:#667085;font-size:12px;line-height:1.15;font-weight:850;letter-spacing:0;white-space:nowrap;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
            .appleklinika-config-card__badge{position:absolute;right:5px;top:-7px;padding:1px 5px;border-radius:999px;background:#35bd68;color:#fff;font-size:9px;font-weight:900}
            .appleklinika-config-card--color{align-items:center;gap:9px;min-height:74px}
            .appleklinika-config-card--color .appleklinika-config-card__content{justify-content:center}
            .appleklinika-config-card--color .appleklinika-config-card__label{display:block;min-height:auto;max-height:34px;overflow:hidden;white-space:normal;-webkit-line-clamp:initial;-webkit-box-orient:initial}
            .appleklinika-config-card--pill{display:flex;min-height:72px;min-width:0;align-items:stretch;justify-content:center;text-align:left}
            .appleklinika-config-card--pill .appleklinika-config-card__content{align-items:flex-start}
            .appleklinika-config-card--wide{display:flex;grid-column:1/-1;min-height:66px;align-items:stretch;text-align:left}
            .appleklinika-config-info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
            .appleklinika-info-card{display:grid;gap:1px;padding:7px;border:1px solid #e1e7ef;border-radius:8px;background:#fff;box-shadow:0 2px 7px rgba(17,24,32,.03)}
            .appleklinika-info-card span{color:#6b7585;font-size:10.5px;font-weight:750}
            .appleklinika-info-card strong{color:#18202b;font-size:11.5px;line-height:1.22}
            .appleklinika-product-gallery{position:static;max-width:520px}
            .admin-bar .appleklinika-product-gallery{top:auto}
            .appleklinika-product-gallery__stage{position:relative;display:flex;align-items:center;justify-content:center;height:320px;border-radius:9px;background:#f7f8fa;border:1px solid #edf0f4;overflow:hidden}
            .appleklinika-product-gallery__stage img{width:auto;max-width:84%;height:auto;max-height:290px;object-fit:contain;display:block}
            .appleklinika-product-gallery__nav,.appleklinika-product-gallery__zoom{position:absolute;border:0;cursor:pointer}
            .appleklinika-product-gallery__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#fff;color:#151b24;font-size:20px;font-weight:900;box-shadow:0 3px 10px rgba(17,24,32,.12)}
            .appleklinika-product-gallery__nav--prev{left:8px}
            .appleklinika-product-gallery__nav--next{right:8px}
            .appleklinika-product-gallery__zoom{right:8px;bottom:8px;min-height:30px;padding:0 9px;border-radius:8px;background:#151b24;color:#fff;font-size:12px;font-weight:850}
            .appleklinika-product-gallery__thumbs{display:flex;gap:7px;margin-top:8px;overflow-x:auto;padding-bottom:2px}
            .appleklinika-product-gallery__thumb{flex:0 0 64px;border:2px solid #e1e7ef;border-radius:8px;background:#fff;padding:4px;cursor:pointer}
            .appleklinika-product-gallery__thumb.is-selected{border-color:#286ff0;box-shadow:0 0 0 3px rgba(40,111,240,.12)}
            .appleklinika-product-gallery__thumb img{width:100%;aspect-ratio:1/1;object-fit:contain;border-radius:6px;display:block;background:#f7f8fa}
            .appleklinika-buy-panel{max-width:430px;margin-left:auto}
            .appleklinika-stock-badge{display:inline-flex;align-items:center;margin-bottom:6px;padding:3px 7px;border-radius:6px;background:#fff0d7;color:#c26d00;font-size:12px;font-weight:800}
            .appleklinika-stock-badge--out{background:#ffe6e4;color:#b42318}
            .appleklinika-buy-panel h1{margin:0 0 7px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif!important;font-size:24px!important;line-height:1.12!important;letter-spacing:0;color:#111820;font-weight:850}
            .appleklinika-price-stack{margin-bottom:11px}
            .appleklinika-price-stack__old{display:block;color:#8490a3;font-size:14px;font-weight:800;text-decoration:line-through}
            .appleklinika-price-stack__current{display:block;margin-top:2px;color:#e2473d;font-size:28px;line-height:1;font-weight:950;letter-spacing:0}
            .appleklinika-price-stack__current .amount{color:inherit;font-size:inherit;font-weight:inherit}
            .appleklinika-price-stack__saving{display:inline-flex;margin-top:7px;padding:3px 7px;border-radius:6px;background:#e5f6e9;color:#32874b;font-size:12px;font-weight:850}
            .appleklinika-options{display:grid;gap:10px;margin-bottom:12px}
            .appleklinika-option-group__head{display:flex;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:6px}
            .appleklinika-option-group h2{margin:0;font-size:14px;line-height:1.2;color:#252d3d}
            .appleklinika-option-group__selected{font-weight:900}
            .appleklinika-color-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .appleklinika-color-card{min-height:60px;border:2px solid #286ff0;border-radius:9px;background:#f7faff;padding:5px;text-align:center;font-size:12px;font-weight:850;color:#30394a;box-shadow:0 0 0 3px rgba(40,111,240,.1)}
            .appleklinika-color-card img{width:30px;height:30px;object-fit:contain;border-radius:6px;display:block;margin:0 auto 4px;background:#fff}
            .appleklinika-storage-row{display:flex;flex-wrap:wrap;gap:8px}
            .appleklinika-storage-pill{min-width:78px;border:2px solid #286ff0;border-radius:9px;background:#286ff0;color:#fff;padding:7px 11px;font-size:13px;font-weight:850}
            .appleklinika-condition-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .appleklinika-condition-card{min-height:56px;border:2px solid #286ff0;border-radius:9px;background:#f7faff;padding:8px;color:#2f3848;text-align:left;box-shadow:0 0 0 3px rgba(40,111,240,.1)}
            .appleklinika-condition-card strong{display:block;font-size:13px;margin-bottom:4px}
            .appleklinika-condition-card span{display:block;color:#748097;font-size:12px;font-weight:800}
            .appleklinika-cart-area{margin-top:4px}
            .appleklinika-cart-area form.cart{display:flex;align-items:center;gap:8px;margin:0}
            .appleklinika-cart-area .quantity input{height:42px;border-radius:8px;border:1px solid #d9e1ea}
            .appleklinika-cart-area .single_add_to_cart_button{width:100%;min-height:42px;border:0;border-radius:9px;background:#35bd68!important;color:#fff!important;font-size:15px;font-weight:950;box-shadow:0 6px 14px rgba(53,189,104,.18)}
            .appleklinika-cart-area .stock{margin:10px 0 0;color:#6d7789}
            .appleklinika-delivery-note{margin:9px 0 11px;color:#6d7789;font-size:13px;line-height:1.4}
            .appleklinika-delivery-note strong{color:#1f2735}
            .appleklinika-delivery-note span{color:#35a969;font-weight:900}
            .appleklinika-trust-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .appleklinika-trust-card{display:flex;align-items:center;gap:8px;min-height:48px;border:1px solid #e1e7ef;border-radius:9px;background:#fff;padding:8px;box-shadow:0 3px 10px rgba(17,24,32,.04)}
            .appleklinika-trust-card__icon{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#f0f5ff;color:#286ff0;font-weight:950}
            .appleklinika-trust-card strong{display:block;font-size:13px;color:#293243}
            .appleklinika-trust-card span{display:block;margin-top:2px;color:#6d7789;font-size:12px}
            .appleklinika-product-details{margin-top:18px;padding-top:14px;border-top:1px solid #e1e7ef}
            .appleklinika-product-details .woocommerce-tabs{margin-top:0}
            .appleklinika-product-details .tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px!important;padding:0!important;list-style:none!important}
            .appleklinika-product-details .tabs li{margin:0!important;border:1px solid #dfe6ef!important;border-radius:8px!important;background:#fff!important}
            .appleklinika-product-details .tabs li a{display:block;padding:8px 11px!important;color:#232b3a!important;font-size:13px;font-weight:850;text-decoration:none}
            .appleklinika-product-details .panel{padding:12px!important;border:1px solid #e1e7ef;border-radius:9px;background:#fff;font-size:14px;line-height:1.5}
            .appleklinika-product-details .panel h2{margin:0 0 8px;font-size:17px;line-height:1.2}
            .appleklinika-product-details table.shop_attributes{font-size:13px}
            .appleklinika-add-feedback{display:none;margin-top:10px;padding:9px 10px;border-radius:8px;background:#e5f6e9;color:#277a43;font-size:13px;font-weight:850}
            .appleklinika-add-feedback.is-visible{display:block}
            .appleklinika-lightbox{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(10,14,20,.88)}
            .appleklinika-lightbox.is-open{display:flex}
            .appleklinika-lightbox__image{max-width:min(920px,90vw);max-height:82vh;border-radius:10px;background:#fff;object-fit:contain}
            .appleklinika-lightbox__close,.appleklinika-lightbox__nav{position:absolute;border:0;border-radius:9px;background:#fff;color:#151b24;font-weight:900;cursor:pointer}
            .appleklinika-lightbox__close{top:16px;right:16px;min-height:36px;padding:0 12px}
            .appleklinika-lightbox__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;font-size:26px}
            .appleklinika-lightbox__nav--prev{left:16px}
            .appleklinika-lightbox__nav--next{right:16px}
            @media (max-width:980px){.appleklinika-product-shell{grid-template-columns:1fr;gap:14px}.appleklinika-product-gallery{max-width:none;order:1}.appleklinika-buy-panel{max-width:none;margin:0;order:2}.appleklinika-product-data{order:3;padding:0 12px}.appleklinika-product-gallery__stage{height:290px}.appleklinika-product-gallery__stage img{max-height:260px}}
            @media (max-width:640px){body.single-product main{padding:8px 0}.appleklinika-product-shell{gap:12px}.appleklinika-product-gallery{padding:0 12px}.appleklinika-product-gallery__stage{height:auto;aspect-ratio:4/3;border-radius:10px}.appleklinika-product-gallery__stage img{width:auto;max-width:88%;height:auto;max-height:88%;object-fit:contain}.appleklinika-product-gallery__thumbs{gap:7px;margin-top:7px}.appleklinika-product-gallery__thumb{flex-basis:52px;padding:4px;border-radius:8px}.appleklinika-product-gallery__nav{width:28px;height:28px;font-size:19px}.appleklinika-buy-panel{padding:0 12px}.appleklinika-buy-panel h1{font-size:21px!important}.appleklinika-price-stack{margin-bottom:11px}.appleklinika-price-stack__old{font-size:13px}.appleklinika-price-stack__current{font-size:26px}.appleklinika-price-stack__saving{font-size:12px}.appleklinika-options{gap:11px;margin-bottom:12px}.appleklinika-color-grid,.appleklinika-condition-grid,.appleklinika-config-grid,.appleklinika-config-info{grid-template-columns:1fr}.appleklinika-config-row{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-storage-pill{min-width:0;flex:1 1 calc(50% - 8px)}.appleklinika-trust-grid{grid-template-columns:1fr}.appleklinika-product-details{padding:14px 12px 0}.appleklinika-lightbox__nav{width:34px;height:34px}.appleklinika-lightbox__nav--prev{left:8px}.appleklinika-lightbox__nav--next{right:8px}}
        </style>';
    }

    public function renderScripts(): void
    {
        if (is_admin()) {
            return;
        }

        echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
                function showAddFeedback() {
                    const feedback = document.querySelector("[data-appleklinika-add-feedback]");
                    if (feedback) {
                        feedback.classList.add("is-visible");
                    }
                }

                if (window.jQuery) {
                    window.jQuery(document.body).on("added_to_cart", showAddFeedback);
                }

                function showGalleryImage(gallery, index) {
                    const stageImage = gallery.querySelector("[data-appleklinika-stage-image]");
                    const thumbs = Array.from(gallery.querySelectorAll("[data-appleklinika-gallery-thumb]"));
                        if (!stageImage || thumbs.length === 0) return;
                    const currentIndex = (index + thumbs.length) % thumbs.length;
                    gallery.dataset.currentIndex = String(currentIndex);
                    const selected = thumbs[currentIndex];
                        stageImage.src = selected.dataset.full;
                        if (selected.dataset.srcset) {
                            stageImage.srcset = selected.dataset.srcset;
                        } else {
                            stageImage.removeAttribute("srcset");
                        }
                        thumbs.forEach(function (item) { item.classList.remove("is-selected"); });
                        selected.classList.add("is-selected");
                }

                document.querySelectorAll(".appleklinika-product-gallery").forEach(function (gallery) {
                    if (gallery.dataset.galleryBound === "1") return;
                    gallery.dataset.galleryBound = "1";
                    gallery.dataset.currentIndex = "0";

                    function openLightbox() {
                        const stageImage = gallery.querySelector("[data-appleklinika-stage-image]");
                        const lightbox = document.querySelector("[data-appleklinika-lightbox]");
                        const lightboxImage = lightbox ? lightbox.querySelector("[data-appleklinika-lightbox-image]") : null;
                        if (!lightbox || !lightboxImage || !stageImage) return;
                        lightboxImage.src = stageImage.src;
                        lightbox.dataset.currentIndex = gallery.dataset.currentIndex || "0";
                        lightbox.classList.add("is-open");
                    }

                    gallery.addEventListener("click", function (event) {
                        const thumb = event.target.closest("[data-appleklinika-gallery-thumb]");
                        if (!thumb || !gallery.contains(thumb)) return;
                        const thumbs = Array.from(gallery.querySelectorAll("[data-appleklinika-gallery-thumb]"));
                        showGalleryImage(gallery, thumbs.indexOf(thumb));
                    });
                    gallery.querySelectorAll("[data-appleklinika-gallery-direction]").forEach(function (button) {
                        button.addEventListener("click", function () {
                            showGalleryImage(gallery, Number(gallery.dataset.currentIndex || "0") + Number(button.dataset.appleklinikaGalleryDirection || "0"));
                        });
                    });
                    gallery.querySelector("[data-appleklinika-gallery-zoom]")?.addEventListener("click", openLightbox);
                });

                document.querySelectorAll("[data-appleklinika-lightbox-direction]").forEach(function (button) {
                    button.addEventListener("click", function () {
                        const lightbox = button.closest("[data-appleklinika-lightbox]");
                        const image = lightbox ? lightbox.querySelector("[data-appleklinika-lightbox-image]") : null;
                        const gallery = document.querySelector(".appleklinika-product-gallery");
                        const stageImage = gallery ? gallery.querySelector("[data-appleklinika-stage-image]") : null;
                        const thumbs = gallery ? Array.from(gallery.querySelectorAll("[data-appleklinika-gallery-thumb]")) : [];
                        if (!lightbox || !image || !stageImage || thumbs.length === 0) return;
                        const next = (Number(lightbox.dataset.currentIndex || "0") + Number(button.dataset.appleklinikaLightboxDirection || "0") + thumbs.length) % thumbs.length;
                        lightbox.dataset.currentIndex = String(next);
                        gallery.dataset.currentIndex = String(next);
                        const selected = thumbs[next];
                        image.src = selected.dataset.full;
                        stageImage.src = selected.dataset.full;
                        if (selected.dataset.srcset) {
                            stageImage.srcset = selected.dataset.srcset;
                        } else {
                            stageImage.removeAttribute("srcset");
                        }
                        thumbs.forEach(function (item) { item.classList.remove("is-selected"); });
                        selected.classList.add("is-selected");
                    });
                });

                document.querySelectorAll("[data-appleklinika-lightbox-close]").forEach(function (button) {
                    button.addEventListener("click", function () {
                        button.closest("[data-appleklinika-lightbox]")?.classList.remove("is-open");
                    });
                });

                document.addEventListener("keydown", function (event) {
                    if (event.key !== "Escape") return;
                    document.querySelector("[data-appleklinika-lightbox].is-open")?.classList.remove("is-open");
                });

                document.querySelectorAll(".appleklinika-cart-area form.cart").forEach(function (form) {
                    form.addEventListener("submit", function (event) {
                        const button = form.querySelector(".single_add_to_cart_button");
                        const productId = button ? button.value : "";
                        const ajaxUrl = "' . esc_js($this->addToCartUrl()) . '";
                        if (!productId || !ajaxUrl) return;
                        event.preventDefault();
                        const quantity = form.querySelector("input.qty")?.value || "1";
                        const data = new FormData(form);
                        data.append("product_id", productId);
                        data.set("quantity", quantity);
                        button.classList.add("loading");
                        fetch(ajaxUrl, {
                            method: "POST",
                            body: data,
                            credentials: "same-origin"
                        }).then(function (response) {
                            return response.json();
                        }).then(function (response) {
                            if (response.error && response.product_url) {
                                window.location = response.product_url;
                                return;
                            }
                            if (response.fragments) {
                                Object.keys(response.fragments).forEach(function (selector) {
                                    document.querySelectorAll(selector).forEach(function (node) {
                                        node.outerHTML = response.fragments[selector];
                                    });
                                });
                            }
                            document.body.dispatchEvent(new CustomEvent("added_to_cart", { detail: response }));
                            showAddFeedback();
                        }).catch(function () {
                            form.submit();
                        }).finally(function () {
                            button.classList.remove("loading");
                        });
                    });
                });

                const selectorDataNode = document.getElementById("appleklinika-product-selector-data");
                const selectorProducts = selectorDataNode ? JSON.parse(selectorDataNode.textContent || "[]") : [];

                function selectedSelectorValues() {
                    const values = {};
                    document.querySelectorAll("[data-selector-group]").forEach(function (group) {
                        const selected = group.querySelector("[data-selector-option].is-selected");
                        if (selected) {
                            values[group.dataset.selectorGroup] = selected.dataset.optionValue || "";
                        }
                    });

                    return values;
                }

                function findMatchingProduct(values) {
                    const exact = selectorProducts.find(function (product) {
                        return product.values
                            && product.values.color === values.color
                            && product.values.storage === values.storage
                            && product.values.condition === values.condition;
                    });

                    return exact || null;
                }

                function renderProductGallery(images) {
                    const gallery = document.querySelector(".appleklinika-product-gallery");
                    const stageImage = gallery ? gallery.querySelector("[data-appleklinika-stage-image]") : null;
                    const thumbs = gallery ? gallery.querySelector(".appleklinika-product-gallery__thumbs") : null;
                    const lightboxImage = document.querySelector("[data-appleklinika-lightbox-image]");

                    if (!gallery || !stageImage || !thumbs || !images || images.length === 0) {
                        return;
                    }

                    const first = images[0];
                    gallery.dataset.currentIndex = "0";
                    stageImage.src = first.url;
                    if (first.srcset) {
                        stageImage.srcset = first.srcset;
                    } else {
                        stageImage.removeAttribute("srcset");
                    }
                    if (lightboxImage) {
                        lightboxImage.src = first.url;
                    }
                    thumbs.innerHTML = images.map(function (image, index) {
                        return "<button class=\"appleklinika-product-gallery__thumb" + (index === 0 ? " is-selected" : "") + "\" type=\"button\" data-appleklinika-gallery-thumb data-full=\"" + image.url + "\" data-srcset=\"" + (image.srcset || "") + "\" aria-label=\"Termékkép " + (index + 1) + "\"><img src=\"" + image.url + "\" alt=\"\"></button>";
                    }).join("");
                }

                function updateProductView(product) {
                    if (!product) return;

                    const title = document.querySelector("[data-appleklinika-product-title]");
                    const price = document.querySelector("[data-appleklinika-product-price]");
                    const stock = document.querySelector("[data-appleklinika-stock-badge]");
                    const delivery = document.querySelector("[data-appleklinika-delivery]");
                    const trust = document.querySelector("[data-appleklinika-trust]");
                    const form = document.querySelector(".appleklinika-cart-area form.cart");

                    if (title) title.textContent = product.title;
                    if (price && product.priceHtml) price.outerHTML = product.priceHtml;
                    if (stock) {
                        stock.className = product.stockClass;
                        stock.textContent = product.stockLabel;
                    }
                    if (delivery && product.deliveryHtml) delivery.outerHTML = product.deliveryHtml;
                    if (trust && product.trustHtml) trust.outerHTML = product.trustHtml;
                    if (form) {
                        form.action = product.url;
                        form.querySelectorAll("[name=\"add-to-cart\"], [name=\"product_id\"]").forEach(function (input) {
                            input.value = String(product.id);
                        });
                        const button = form.querySelector(".single_add_to_cart_button");
                        if (button) {
                            button.value = String(product.id);
                        }
                    }
                    renderProductGallery(product.images);
                    updateBatteryExtra();
                    if (window.history && product.url) {
                        window.history.replaceState({ productId: product.id }, "", product.url);
                    }
                }

                function selectedBatteryExtra() {
                    const selected = document.querySelector("[data-selector-group=\"battery\"] [data-selector-option].is-selected");
                    return selected ? {
                        value: selected.dataset.optionValue || "standard",
                        label: selected.dataset.optionLabel || "Standard",
                        price: Number(selected.dataset.extraPrice || "0")
                    } : { value: "standard", label: "Standard", price: 0 };
                }

                function formatFt(amount) {
                    return String(Math.round(Number(amount) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " Ft";
                }

                function updateBatteryExtra() {
                    const extra = selectedBatteryExtra();
                    const price = document.querySelector("[data-appleklinika-product-price]");
                    const form = document.querySelector(".appleklinika-cart-area form.cart");
                    const activeProduct = findMatchingProduct(selectedSelectorValues()) || selectorProducts[0] || null;

                    if (form) {
                        let input = form.querySelector("[name=\"appleklinika_battery_extra\"]");
                        if (!input) {
                            input = document.createElement("input");
                            input.type = "hidden";
                            input.name = "appleklinika_battery_extra";
                            form.appendChild(input);
                        }
                        input.value = extra.value;
                    }

                    if (!price || !activeProduct) return;
                    const sale = Number(activeProduct.salePrice || "0") + extra.price;
                    const regular = Number(activeProduct.regularPrice || "0") + extra.price;
                    const saving = Math.max(0, regular - sale);
                    price.innerHTML = ""
                        + (regular > sale ? "<span class=\"appleklinika-price-stack__old\">" + formatFt(regular) + "</span>" : "")
                        + "<span class=\"appleklinika-price-stack__current\"><span class=\"woocommerce-Price-amount amount\">" + formatFt(sale) + "</span></span>"
                        + (saving > 0 ? "<span class=\"appleklinika-price-stack__saving\">Megtakarítás: " + formatFt(saving) + "</span>" : "");
                }

                document.querySelectorAll("[data-selector-group]").forEach(function (group) {
                    group.addEventListener("click", function (event) {
                        const option = event.target.closest("[data-selector-option]");
                        if (!option || !group.contains(option)) return;
                        const options = Array.from(group.querySelectorAll("[data-selector-option]"));
                        options.forEach(function (item) {
                            item.classList.remove("is-selected", "is-active");
                            item.setAttribute("aria-pressed", "false");
                        });
                        option.classList.add("is-selected", "is-active");
                        option.setAttribute("aria-pressed", "true");

                        const selected = group.querySelector("[data-selected-label]");
                        if (selected) {
                            selected.textContent = option.dataset.optionLabel || option.textContent.trim();
                        }

                        const imageUrl = option.dataset.imageUrl || "";
                        const stageImage = document.querySelector("[data-appleklinika-stage-image]");
                        if (imageUrl && stageImage) {
                            stageImage.src = imageUrl;
                            stageImage.removeAttribute("srcset");
                        }

                        if (group.dataset.selectorGroup === "battery") {
                            updateBatteryExtra();
                            return;
                        }

                        updateProductView(findMatchingProduct(selectedSelectorValues()));
                    });
                });

                updateBatteryExtra();
            });
        </script>';
    }

    public function renderDynamicProductPage(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        if ($this->hasRendered) {
            return;
        }

        $this->hasRendered = true;

        $productId = $product->get_id();
        $images = $this->productImages($product);
        $firstImage = $images[0];
        $relatedProducts = $this->relatedProducts($product);

        echo '<section class="appleklinika-product-shell" data-appleklinika-product-shell aria-label="Product purchase layout">';
        $this->renderConfigurationPanel($product, $productId, $firstImage, $relatedProducts);
        echo '<div class="appleklinika-product-gallery">';
        echo '<div class="appleklinika-product-gallery__stage">';
        echo $this->imageHtml($firstImage, 'data-appleklinika-stage-image');
        echo '<button class="appleklinika-product-gallery__nav appleklinika-product-gallery__nav--prev" type="button" data-appleklinika-gallery-direction="-1" aria-label="Előző kép">‹</button>';
        echo '<button class="appleklinika-product-gallery__nav appleklinika-product-gallery__nav--next" type="button" data-appleklinika-gallery-direction="1" aria-label="Következő kép">›</button>';
        echo '<button class="appleklinika-product-gallery__zoom" type="button" data-appleklinika-gallery-zoom>Nagyítás</button>';
        echo '</div>';
        echo '<div class="appleklinika-product-gallery__thumbs" aria-label="Product thumbnails">';

        foreach ($images as $index => $image) {
            $class = $index === 0 ? 'appleklinika-product-gallery__thumb is-selected' : 'appleklinika-product-gallery__thumb';
            echo '<button class="' . esc_attr($class) . '" type="button" data-appleklinika-gallery-thumb data-full="' . esc_url($image['url']) . '" data-srcset="' . esc_attr($image['srcset']) . '" aria-label="' . esc_attr(sprintf(__('Product image %d', 'woocommerce'), $index + 1)) . '">' . $this->imageHtml($image) . '</button>';
        }

        echo '</div>';
        echo '</div>';

        echo '<aside class="appleklinika-buy-panel">';
        echo '<span class="' . esc_attr($this->stockBadgeClass($product)) . '" data-appleklinika-stock-badge>' . esc_html($this->stockLabel($product)) . '</span>';
        echo '<h1 data-appleklinika-product-title>' . esc_html($product->get_name()) . '</h1>';
        $this->renderPrice($product);
        echo '<div class="appleklinika-cart-area">';
        woocommerce_template_single_add_to_cart();
        echo '<div class="appleklinika-add-feedback" data-appleklinika-add-feedback>Kosárba téve. A kosár frissült.</div>';
        echo '</div>';
        $this->renderDelivery($product);
        $this->renderTrustCards($productId);
        echo '</aside>';
        echo '</section>';
        echo '<section class="appleklinika-product-details" aria-label="Termékinformációk">';
        woocommerce_output_product_data_tabs();
        echo '</section>';
        echo '<div class="appleklinika-lightbox" data-appleklinika-lightbox role="dialog" aria-modal="true" aria-label="Nagyított termékkép">';
        echo '<button class="appleklinika-lightbox__close" type="button" data-appleklinika-lightbox-close>Bezárás</button>';
        echo '<button class="appleklinika-lightbox__nav appleklinika-lightbox__nav--prev" type="button" data-appleklinika-lightbox-direction="-1" aria-label="Előző nagyított kép">‹</button>';
        echo '<img class="appleklinika-lightbox__image" data-appleklinika-lightbox-image src="' . esc_url($firstImage['url']) . '" alt="">';
        echo '<button class="appleklinika-lightbox__nav appleklinika-lightbox__nav--next" type="button" data-appleklinika-lightbox-direction="1" aria-label="Következő nagyított kép">›</button>';
        echo '</div>';
        echo '<script type="application/json" id="appleklinika-product-selector-data">' . wp_json_encode($this->productSelectorPayload($relatedProducts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    }

    public function renderSingleProductShortcode(): string
    {
        if (! is_product()) {
            return '';
        }

        $this->removeDefaultProductBlocks();

        ob_start();
        $this->renderDynamicProductPage();

        return (string) ob_get_clean();
    }

    /**
     * @param array{url: string, srcset: string, html: string} $fallbackImage
     */
    /**
     * @param array<int, \WC_Product> $relatedProducts
     * @param array{url: string, srcset: string, html: string} $fallbackImage
     */
    private function renderConfigurationPanel(\WC_Product $product, int $productId, array $fallbackImage, array $relatedProducts): void
    {
        $currentPrice = $this->numericPrice($product);
        $modelKey = $this->conditionRepository->get($productId, 'device_model');
        $color = $this->colorLabel(
            $modelKey,
            $this->conditionRepository->get($productId, 'color')
        );
        $storage = $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity'));
        $condition = $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade'));
        $battery = $this->conditionRepository->get($productId, 'battery_health');
        $warranty = $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'));
        $accessories = $this->conditionRepository->get($productId, 'accessories');
        $simConfig = $this->simConfigLabel($this->conditionRepository->get($productId, 'sim_config'));

        echo '<aside class="appleklinika-product-data" aria-label="Termékadatok">';
        $this->renderColorSelector($product, $relatedProducts, $modelKey, $color, $fallbackImage);
        $this->renderStorageSelector($product, $relatedProducts, $currentPrice, $storage);
        $this->renderConditionSelector($product, $relatedProducts, $currentPrice, $condition);
        $this->renderBatterySelector($battery);
        $this->renderCompactInfoCards($warranty, $accessories, $this->stockLabel($product), $simConfig);

        echo '</aside>';
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     * @param array{url: string, srcset: string, html: string} $fallbackImage
     */
    private function renderColorSelector(\WC_Product $currentProduct, array $relatedProducts, string $modelKey, string $selectedLabel, array $fallbackImage): void
    {
        $items = [];

        foreach ($relatedProducts as $product) {
            $colorKey = $this->conditionRepository->get($product->get_id(), 'color');

            if ($colorKey === '' || isset($items[$colorKey])) {
                continue;
            }

            $image = $this->productImages($product)[0] ?? $fallbackImage;
            $items[$colorKey] = [
                'value' => $colorKey,
                'label' => $this->colorLabel($modelKey, $colorKey),
                'url' => $product->get_id() === $currentProduct->get_id() ? '' : get_permalink($product->get_id()),
                'selected' => $product->get_id() === $currentProduct->get_id(),
                'image' => $image,
                'meta' => '',
            ];
        }

        if ($items === [] && $selectedLabel !== '') {
            $items['current'] = [
                'value' => 'current',
                'label' => $selectedLabel,
                'url' => '',
                'selected' => true,
                'image' => $fallbackImage,
                'meta' => '',
            ];
        }

        $this->renderSelectorGroup('color', 'Szín', $selectedLabel, $items, 'appleklinika-config-grid', true);
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     */
    private function renderStorageSelector(\WC_Product $currentProduct, array $relatedProducts, float $currentPrice, string $selectedLabel): void
    {
        $items = [];

        foreach ($relatedProducts as $product) {
            $storageKey = $this->conditionRepository->get($product->get_id(), 'storage_capacity');

            if ($storageKey === '' || isset($items[$storageKey])) {
                continue;
            }

            $items[$storageKey] = [
                'value' => $storageKey,
                'label' => $this->storageLabel($storageKey),
                'url' => $product->get_id() === $currentProduct->get_id() ? '' : get_permalink($product->get_id()),
                'selected' => $product->get_id() === $currentProduct->get_id(),
                'meta' => $this->priceDifferenceLabel($this->numericPrice($product) - $currentPrice),
                'image' => $this->productImages($product)[0] ?? null,
            ];
        }

        if ($items === [] && $selectedLabel !== '') {
            $items['current'] = ['value' => 'current', 'label' => $selectedLabel, 'url' => '', 'selected' => true, 'meta' => ''];
        }

        $this->renderSelectorGroup('storage', 'Tárhely', $selectedLabel, $items, 'appleklinika-config-row', false, 'appleklinika-config-card--pill');
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     */
    private function renderConditionSelector(\WC_Product $currentProduct, array $relatedProducts, float $currentPrice, string $selectedLabel): void
    {
        $items = [];

        foreach ($relatedProducts as $product) {
            $gradeKey = $this->conditionRepository->get($product->get_id(), 'overall_grade');

            if ($gradeKey === '' || isset($items[$gradeKey])) {
                continue;
            }

                $items[$gradeKey] = [
                    'value' => $gradeKey,
                    'label' => $this->gradeLabel($gradeKey),
                    'url' => $product->get_id() === $currentProduct->get_id() ? '' : get_permalink($product->get_id()),
                    'selected' => $product->get_id() === $currentProduct->get_id(),
                    'meta' => $this->priceDifferenceLabel($this->numericPrice($product) - $currentPrice),
                    'popular' => $gradeKey === Grade::A,
                    'image' => $this->productImages($product)[0] ?? null,
                ];
        }

        if (count($items) < 2) {
            $currentGrade = $this->conditionRepository->get($currentProduct->get_id(), 'overall_grade');

            foreach (Grade::options() as $gradeKey => $label) {
                if (isset($items[$gradeKey])) {
                    continue;
                }

                $items[$gradeKey] = [
                    'value' => $gradeKey,
                    'label' => $label,
                    'url' => '',
                    'selected' => $gradeKey === $currentGrade,
                    'meta' => '',
                    'popular' => $gradeKey === Grade::A,
                ];
            }
        }

        $this->renderSelectorGroup('condition', 'Esztétikai állapot', $selectedLabel, $items, 'appleklinika-config-grid', false);
    }

    private function renderBatterySelector(string $batteryHealth): void
    {
        $items = [
            'standard' => [
                'value' => 'standard',
                'label' => 'Standard',
                'url' => '',
                'selected' => true,
                'meta' => $batteryHealth !== '' ? $batteryHealth . '%' : 'Jelenlegi akkumulátor',
                'extra_price' => 0,
            ],
            'aftermarket_new' => [
                'value' => 'aftermarket_new',
                'label' => 'Új utángyártott akkumulátor',
                'url' => '',
                'selected' => false,
                'meta' => '+15.000 Ft',
                'extra_price' => 15000,
            ],
            'factory_new' => [
                'value' => 'factory_new',
                'label' => 'Új gyári akkumulátor',
                'url' => '',
                'selected' => false,
                'meta' => '+30.000 Ft',
                'extra_price' => 30000,
            ],
        ];

        $this->renderSelectorGroup('battery', 'Akkumulátor extra', 'Standard', $items, 'appleklinika-config-grid', false, 'appleklinika-config-card--wide');
    }

    private function renderCompactInfoCards(string $warranty, string $accessories, string $stock, string $simConfig): void
    {
        echo '<section class="appleklinika-config-group" aria-label="Kiegészítő információk">';
        echo '<div class="appleklinika-config-info">';
        $this->renderInfoCard('Garancia', $warranty);
        $this->renderInfoCard('SIM', $simConfig);
        $this->renderInfoCard('Tartozékok', $accessories);
        $this->renderInfoCard('Készlet', $stock);
        echo '</div>';
        echo '</section>';
    }

    private function renderInfoCard(string $label, string $value): void
    {
        if ($value === '') {
            return;
        }

        echo '<div class="appleklinika-info-card">';
        echo '<span>' . esc_html($label) . '</span>';
        echo '<strong>' . esc_html($value) . '</strong>';
        echo '</div>';
    }

    /**
     * @param array<string, array<string, mixed>> $items
     */
    private function renderSelectorGroup(string $group, string $title, string $selectedLabel, array $items, string $containerClass, bool $withImage, string $extraCardClass = ''): void
    {
        if ($items === []) {
            return;
        }

        echo '<section class="appleklinika-config-group" data-selector-group="' . esc_attr($group) . '" data-appleklinika-config-group="' . esc_attr($group) . '" aria-label="' . esc_attr($title) . '">';
        echo '<h2 class="appleklinika-config-group__title">' . esc_html($title) . ': <span data-selected-label data-appleklinika-selected-label>' . esc_html($selectedLabel) . '</span></h2>';
        echo '<div class="' . esc_attr($containerClass) . '">';

        foreach ($items as $item) {
            $class = 'appleklinika-config-card' . ($withImage ? ' appleklinika-config-card--color' : '') . ($extraCardClass !== '' ? ' ' . $extraCardClass : '') . (! empty($item['selected']) ? ' is-selected' : '');
            $optionImage = isset($item['image']) && is_array($item['image']) ? (string) $item['image']['url'] : '';
            $optionValue = (string) ($item['value'] ?? sanitize_title((string) $item['label']));
            $extraPrice = (string) ($item['extra_price'] ?? 0);
            echo '<button class="' . esc_attr($class) . '" type="button" data-selector-option data-appleklinika-config-option data-option-value="' . esc_attr($optionValue) . '" data-option-label="' . esc_attr((string) $item['label']) . '" data-image-url="' . esc_url($optionImage) . '" data-option-image="' . esc_url($optionImage) . '" data-product-url="' . esc_url((string) ($item['url'] ?? '')) . '" data-extra-price="' . esc_attr($extraPrice) . '" aria-pressed="' . (! empty($item['selected']) ? 'true' : 'false') . '">';

            if ($withImage && isset($item['image']) && is_array($item['image'])) {
                echo '<img class="appleklinika-config-card__image" src="' . esc_url((string) $item['image']['url']) . '" alt="">';
            }

            echo '<span class="appleklinika-config-card__content">';
            echo '<span class="appleklinika-config-card__label">' . esc_html((string) $item['label']) . '</span>';

            if (! empty($item['meta'])) {
                echo '<span class="appleklinika-config-card__meta">' . esc_html((string) $item['meta']) . '</span>';
            }
            echo '</span>';

            if (! empty($item['popular'])) {
                echo '<span class="appleklinika-config-card__badge">Népszerű</span>';
            }

            echo '</button>';
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * @return array<int, \WC_Product>
     */
    private function relatedProducts(\WC_Product $product): array
    {
        $model = $this->conditionRepository->get($product->get_id(), 'device_model');

        if ($model === '' || ! function_exists('wc_get_products')) {
            return [$product];
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 300,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => WooProductConditionRepository::META_PREFIX . 'device_model',
            'meta_value' => $model,
        ]);
        $byId = [$product->get_id() => $product];

        foreach ($products as $relatedProduct) {
            if ($relatedProduct instanceof \WC_Product) {
                $byId[$relatedProduct->get_id()] = $relatedProduct;
            }
        }

        return array_values($byId);
    }

    /**
     * @param array<int, \WC_Product> $products
     * @return array<int, array<string, mixed>>
     */
    private function productSelectorPayload(array $products): array
    {
        return array_values(array_map(function (\WC_Product $product): array {
            $productId = $product->get_id();
            $images = $this->productImages($product);

            return [
                'id' => $productId,
                'url' => get_permalink($productId),
                'title' => $product->get_name(),
                'salePrice' => $this->numericPrice($product),
                'regularPrice' => (float) ($product->get_regular_price() !== '' ? $product->get_regular_price() : $this->numericPrice($product)),
                'priceHtml' => $this->capture(function () use ($product): void {
                    $this->renderPrice($product);
                }),
                'stockLabel' => $this->stockLabel($product),
                'stockClass' => $this->stockBadgeClass($product),
                'deliveryHtml' => $this->capture(function () use ($product): void {
                    $this->renderDelivery($product);
                }),
                'trustHtml' => $this->capture(function () use ($productId): void {
                    $this->renderTrustCards($productId);
                }),
                'images' => array_map(static function (array $image): array {
                    return [
                        'url' => $image['url'],
                        'srcset' => $image['srcset'],
                    ];
                }, $images),
                'values' => [
                    'color' => $this->conditionRepository->get($productId, 'color'),
                    'storage' => $this->conditionRepository->get($productId, 'storage_capacity'),
                    'condition' => $this->conditionRepository->get($productId, 'overall_grade'),
                ],
            ];
        }, $products));
    }

    private function capture(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    private function numericPrice(\WC_Product $product): float
    {
        return (float) ($product->get_sale_price() !== '' ? $product->get_sale_price() : $product->get_price());
    }

    private function priceDifferenceLabel(float $difference): string
    {
        if (abs($difference) < 1) {
            return '';
        }

        $prefix = $difference > 0 ? '+' : '-';

        return $prefix . number_format(abs($difference), 0, '', ' ') . ' Ft';
    }

    /**
     * @return array<int, array{url: string, srcset: string, html: string}>
     */
    private function productImages(\WC_Product $product): array
    {
        $imageIds = [];
        $mainImageId = $product->get_image_id();

        if ($mainImageId > 0) {
            $imageIds[] = $mainImageId;
        }

        foreach ($product->get_gallery_image_ids() as $galleryImageId) {
            if (! in_array($galleryImageId, $imageIds, true)) {
                $imageIds[] = $galleryImageId;
            }
        }

        if ($imageIds === []) {
            return [[
                'url' => wc_placeholder_img_src('woocommerce_single'),
                'srcset' => '',
                'html' => wc_placeholder_img('woocommerce_single'),
            ]];
        }

        return array_map(static function (int $imageId): array {
            return [
                'url' => (string) wp_get_attachment_image_url($imageId, 'woocommerce_single'),
                'srcset' => (string) wp_get_attachment_image_srcset($imageId, 'woocommerce_single'),
                'html' => (string) wp_get_attachment_image($imageId, 'woocommerce_single'),
            ];
        }, $imageIds);
    }

    /**
     * @param array{url: string, srcset: string, html: string} $image
     */
    private function imageHtml(array $image, string $extraAttribute = ''): string
    {
        if ($image['html'] === '') {
            return '<img ' . $extraAttribute . ' src="' . esc_url($image['url']) . '" alt="">';
        }

        if ($extraAttribute === '') {
            return $image['html'];
        }

        return preg_replace('/^<img /', '<img ' . $extraAttribute . ' ', $image['html'], 1) ?: $image['html'];
    }

    private function renderPrice(\WC_Product $product): void
    {
        echo '<div class="appleklinika-price-stack" data-appleklinika-product-price>';

        if ($product->is_on_sale() && $product->get_regular_price() !== '') {
            echo '<span class="appleklinika-price-stack__old">' . wp_kses_post(wc_price((float) $product->get_regular_price())) . '</span>';
        }

        echo '<span class="appleklinika-price-stack__current">' . wp_kses_post($product->get_price_html()) . '</span>';

        $saving = $this->savingAmount($product);

        if ($saving > 0) {
            echo '<span class="appleklinika-price-stack__saving">' . esc_html(sprintf('Megtakarítás: %s', wp_strip_all_tags(wc_price($saving)))) . '</span>';
        }

        echo '</div>';
    }

    private function savingAmount(\WC_Product $product): float
    {
        if (! $product->is_on_sale()) {
            return 0.0;
        }

        $regular = (float) $product->get_regular_price();
        $sale = (float) $product->get_sale_price();

        return $regular > $sale ? $regular - $sale : 0.0;
    }

    /**
     * @param array{url: string, srcset: string, html: string} $image
     */
    private function renderProductOptions(int $productId, array $image): void
    {
        $color = $this->colorLabel(
            $this->conditionRepository->get($productId, 'device_model'),
            $this->conditionRepository->get($productId, 'color')
        );
        $storage = $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity'));
        $condition = $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade'));

        if ($color === '' && $storage === '' && $condition === '') {
            return;
        }

        echo '<div class="appleklinika-options">';

        if ($color !== '') {
            echo '<section class="appleklinika-option-group" aria-label="Szín">';
            echo '<div class="appleklinika-option-group__head"><h2>Szín: <span class="appleklinika-option-group__selected">' . esc_html($color) . '</span></h2></div>';
            echo '<div class="appleklinika-color-grid"><div class="appleklinika-color-card">' . $this->imageHtml($image) . '<span>' . esc_html($color) . '</span></div></div>';
            echo '</section>';
        }

        if ($storage !== '') {
            echo '<section class="appleklinika-option-group" aria-label="Tárhely">';
            echo '<div class="appleklinika-option-group__head"><h2>Tárhely: <span class="appleklinika-option-group__selected">' . esc_html($storage) . '</span></h2></div>';
            echo '<div class="appleklinika-storage-row"><span class="appleklinika-storage-pill">' . esc_html($storage) . '</span></div>';
            echo '</section>';
        }

        if ($condition !== '') {
            echo '<section class="appleklinika-option-group" aria-label="Állapot">';
            echo '<div class="appleklinika-option-group__head"><h2>Esztétikai állapot: <span class="appleklinika-option-group__selected">' . esc_html($condition) . '</span></h2></div>';
            echo '<div class="appleklinika-condition-grid"><div class="appleklinika-condition-card"><strong>' . esc_html($condition) . '</strong><span>' . esc_html($this->batteryText($productId)) . '</span></div></div>';
            echo '</section>';
        }

        echo '</div>';
    }

    private function renderDelivery(\WC_Product $product): void
    {
        $availability = $product->get_availability();
        $message = isset($availability['availability']) && $availability['availability'] !== ''
            ? $availability['availability']
            : $this->stockLabel($product);

        echo '<p class="appleklinika-delivery-note" data-appleklinika-delivery><strong>Készletinformáció:</strong> <span>' . esc_html($message) . '</span></p>';
    }

    private function renderTrustCards(int $productId): void
    {
        $warranty = $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'));

        echo '<div class="appleklinika-trust-grid" data-appleklinika-trust>';

        if ($warranty !== '') {
            echo '<div class="appleklinika-trust-card"><span class="appleklinika-trust-card__icon">✓</span><div><strong>' . esc_html($warranty . ' garancia') . '</strong><span>Termékadatok alapján</span></div></div>';
        }

        echo '<div class="appleklinika-trust-card"><span class="appleklinika-trust-card__icon">↺</span><div><strong>Visszaküldés</strong><span>A végleges szabályzat alapján</span></div></div>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public function addBatteryExtraToCartItem(array $cartItemData, int $productId): array
    {
        $extra = isset($_POST['appleklinika_battery_extra']) ? sanitize_key((string) wp_unslash($_POST['appleklinika_battery_extra'])) : 'standard';
        $price = $this->batteryExtraPrice($extra);

        if ($price <= 0) {
            return $cartItemData;
        }

        $product = wc_get_product($productId);
        $cartItemData['appleklinika_battery_extra'] = $extra;
        $cartItemData['appleklinika_battery_extra_label'] = $this->batteryExtraLabel($extra);
        $cartItemData['appleklinika_battery_extra_price'] = $price;
        $cartItemData['appleklinika_base_price'] = $product instanceof \WC_Product ? $this->numericPrice($product) : 0;
        $cartItemData['unique_key'] = md5((string) microtime(true) . $extra . $productId);

        return $cartItemData;
    }

    /**
     * @param array<int, array<string, string>> $itemData
     * @param array<string, mixed> $cartItem
     * @return array<int, array<string, string>>
     */
    public function renderBatteryExtraCartItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['appleklinika_battery_extra_label'])) {
            return $itemData;
        }

        $itemData[] = [
            'key' => 'Akkumulátor extra',
            'value' => (string) $cartItem['appleklinika_battery_extra_label'],
        ];

        return $itemData;
    }

    public function applyBatteryExtraCartPrice(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cartItem) {
            if (empty($cartItem['appleklinika_battery_extra_price']) || ! isset($cartItem['data']) || ! $cartItem['data'] instanceof \WC_Product) {
                continue;
            }

            $basePrice = (float) ($cartItem['appleklinika_base_price'] ?? $cartItem['data']->get_price());
            $cartItem['data']->set_price($basePrice + (float) $cartItem['appleklinika_battery_extra_price']);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public function addBatteryExtraToOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        if (empty($values['appleklinika_battery_extra_label'])) {
            return;
        }

        $item->add_meta_data('Akkumulátor extra', (string) $values['appleklinika_battery_extra_label'], true);
    }

    private function addToCartUrl(): string
    {
        if (class_exists('\WC_AJAX')) {
            return \WC_AJAX::get_endpoint('add_to_cart');
        }

        return add_query_arg('wc-ajax', 'add_to_cart', home_url('/'));
    }

    private function stockBadgeClass(\WC_Product $product): string
    {
        return $product->is_in_stock() ? 'appleklinika-stock-badge' : 'appleklinika-stock-badge appleklinika-stock-badge--out';
    }

    private function stockLabel(\WC_Product $product): string
    {
        $availability = $product->get_availability();

        if (isset($availability['availability']) && $availability['availability'] !== '') {
            return $availability['availability'];
        }

        return $product->is_in_stock() ? __('In stock', 'woocommerce') : __('Out of stock', 'woocommerce');
    }

    private function batteryText(int $productId): string
    {
        $batteryHealth = $this->conditionRepository->get($productId, 'battery_health');

        return $batteryHealth !== '' ? 'Akkumulátor: ' . $batteryHealth . '%' : '';
    }

    private function batteryExtraLabel(string $extra): string
    {
        return [
            'standard' => 'Standard',
            'aftermarket_new' => 'Új utángyártott akkumulátor',
            'factory_new' => 'Új gyári akkumulátor',
        ][$extra] ?? 'Standard';
    }

    private function batteryExtraPrice(string $extra): float
    {
        return [
            'aftermarket_new' => 15000.0,
            'factory_new' => 30000.0,
        ][$extra] ?? 0.0;
    }

    private function modelLabel(string $model): string
    {
        if ($model === '') {
            return '';
        }

        foreach ($this->deviceCatalogRepository->all() as $device) {
            if ($device['key'] === $model) {
                return $device['name'];
            }
        }

        return $model;
    }

    private function colorLabel(string $model, string $color): string
    {
        if ($color === '') {
            return '';
        }

        foreach ($this->deviceCatalogRepository->all() as $device) {
            if ($device['key'] === $model) {
                return $device['colors'][$color] ?? $color;
            }
        }

        return $color;
    }

    private function storageLabel(string $storage): string
    {
        return [
            '64_gb' => '64 GB',
            '128_gb' => '128 GB',
            '256_gb' => '256 GB',
            '512_gb' => '512 GB',
            '1_tb' => '1 TB',
            '2_tb' => '2 TB',
        ][$storage] ?? $storage;
    }

    private function warrantyLabel(string $warranty): string
    {
        return [
            '3_months' => '3 hónap',
            '6_months' => '6 hónap',
            '12_months' => '12 hónap',
            '24_months' => '24 hónap',
            '36_months' => '36 hónap',
        ][$warranty] ?? $warranty;
    }

    private function simConfigLabel(string $simConfig): string
    {
        return [
            'dual_esim' => 'Dual eSIM',
            'physical_esim' => 'Fizikai + eSIM',
            'dual_physical' => 'Dual fizikai',
        ][$simConfig] ?? '';
    }

    private function gradeLabel(string $grade): string
    {
        return Grade::options()[$grade] ?? $grade;
    }
}
