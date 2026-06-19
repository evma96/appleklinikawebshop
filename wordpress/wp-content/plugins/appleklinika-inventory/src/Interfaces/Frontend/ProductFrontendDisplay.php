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
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'singleAddToCartText']);
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
        if (is_admin() || ! function_exists('is_product') || ! is_product()) {
            return;
        }

        echo '<style>
            body.single-product main,body.single-product .ak-single-product-page{max-width:1360px;margin:0 auto;padding:32px 24px 72px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111820}
            body.single-product div.product{display:block}
            body.single-product .summary.entry-summary{display:none}
            body.single-product main .woocommerce-breadcrumb,body.single-product main .woocommerce-notices-wrapper{display:none}
            .appleklinika-product-shell,.appleklinika-product-shell *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .appleklinika-product-shell{display:grid;gap:42px}
            .appleklinika-product-shell button{font-family:inherit}
            .appleklinika-product-hero{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(360px,.72fr);gap:56px;align-items:start}
            .appleklinika-product-gallery{min-width:0}
            .appleklinika-product-gallery__stage{position:relative;display:flex;align-items:center;justify-content:center;min-height:560px;border:1px solid #eef1f5;border-radius:28px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);overflow:hidden;box-shadow:0 28px 68px rgba(15,23,42,.08)}
            .appleklinika-product-gallery__stage img{display:block;width:auto;max-width:86%;height:auto;max-height:520px;object-fit:contain;transition:transform .22s ease}
            .appleklinika-product-gallery__stage:hover img{transform:translateY(-3px) scale(1.01)}
            .appleklinika-product-gallery__nav,.appleklinika-product-gallery__zoom{position:absolute;border:0;cursor:pointer}
            .appleklinika-product-gallery__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.92);color:#151b24;font-size:26px;font-weight:850;box-shadow:0 10px 28px rgba(17,24,32,.14)}
            .appleklinika-product-gallery__nav--prev{left:18px}
            .appleklinika-product-gallery__nav--next{right:18px}
            .appleklinika-product-gallery__zoom{right:18px;bottom:18px;min-height:38px;padding:0 14px;border-radius:999px;background:#151b24;color:#fff;font-size:13px;font-weight:800;box-shadow:0 10px 24px rgba(17,24,32,.18)}
            .appleklinika-product-gallery__thumbs{display:flex;justify-content:center;gap:14px;margin-top:22px;overflow-x:auto;padding:4px 2px 8px}
            .appleklinika-product-gallery__thumb{flex:0 0 84px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;padding:9px;cursor:pointer;box-shadow:0 8px 20px rgba(15,23,42,.06);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
            .appleklinika-product-gallery__thumb:hover{transform:translateY(-2px)}
            .appleklinika-product-gallery__thumb.is-selected{border-color:#d6001c;box-shadow:0 0 0 3px rgba(214,0,28,.12),0 12px 28px rgba(15,23,42,.08)}
            .appleklinika-product-gallery__thumb img{display:block;width:100%;aspect-ratio:1/1;object-fit:contain;border-radius:12px;background:#f8fafc}
            .appleklinika-buy-panel{position:sticky;top:24px;display:grid;gap:18px;padding:32px;border:1px solid #e5e7eb;border-radius:28px;background:#fff;box-shadow:0 28px 70px rgba(15,23,42,.1)}
            .admin-bar .appleklinika-buy-panel{top:56px}
            .appleklinika-stock-badge{display:inline-flex;width:max-content;align-items:center;padding:7px 12px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:13px;font-weight:850}
            .appleklinika-stock-badge--out{background:#fff7ed;color:#c2410c}
            .appleklinika-buy-panel h1{margin:0;font-size:34px!important;line-height:1.08!important;letter-spacing:0;color:#111820;font-weight:850}
            .appleklinika-product-lead{margin:0;color:#667085;font-size:15px;line-height:1.55;font-weight:650}
            .appleklinika-price-stack{display:grid;gap:7px;margin:0}
            .appleklinika-price-stack__old{display:block;color:#8a94a6;font-size:16px;font-weight:800;text-decoration:line-through}
            .appleklinika-price-stack__current{display:block;color:#d6001c;font-size:42px;line-height:1;font-weight:900;letter-spacing:0}
            .appleklinika-price-stack__current .amount{color:inherit;font-size:inherit;font-weight:inherit}
            .appleklinika-price-stack__saving{display:inline-flex;width:max-content;padding:5px 10px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:13px;font-weight:850}
            .appleklinika-cart-area{display:grid;gap:10px;margin-top:2px}
            .appleklinika-cart-area form.cart{display:grid;grid-template-columns:132px minmax(0,1fr);gap:12px;align-items:center;margin:0}
            .appleklinika-cart-area .quantity{display:flex;align-items:center}
            .appleklinika-cart-area .quantity input{width:100%;height:54px;border:1px solid #d9e1ea;border-radius:16px;background:#fff;color:#111820;font-size:16px;font-weight:750;text-align:center}
            .appleklinika-cart-area .single_add_to_cart_button{width:100%;min-height:54px;border:0;border-radius:999px;background:#d6001c!important;color:#fff!important;font-size:16px;font-weight:900;box-shadow:0 16px 34px rgba(214,0,28,.24);transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
            .appleklinika-cart-area .single_add_to_cart_button:hover{transform:translateY(-1px);background:#b80018!important;box-shadow:0 18px 38px rgba(214,0,28,.3)}
            .appleklinika-cart-area .stock{display:none}
            .appleklinika-add-feedback{display:none;padding:11px 12px;border-radius:14px;background:#f0fdf4;color:#166534;font-size:13px;font-weight:800}
            .appleklinika-add-feedback.is-visible{display:block}
            .appleklinika-delivery-note{margin:0;padding:14px 16px;border:1px solid #edf0f4;border-radius:18px;background:#fafafa;color:#6d7789;font-size:14px;line-height:1.45}
            .appleklinika-delivery-note strong{color:#1f2735}
            .appleklinika-delivery-note span{color:#166534;font-weight:850}
            .appleklinika-trust-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
            .appleklinika-trust-card{display:flex;align-items:center;gap:10px;min-height:62px;border:1px solid #eceff3;border-radius:18px;background:#fff;padding:12px;box-shadow:0 8px 22px rgba(15,23,42,.05)}
            .appleklinika-trust-card__icon{display:flex;flex:0 0 34px;align-items:center;justify-content:center;width:34px;height:34px;border-radius:12px;background:#fef2f2;color:#d6001c;font-weight:950}
            .appleklinika-trust-card strong{display:block;color:#293243;font-size:13px;line-height:1.2;font-weight:850}
            .appleklinika-trust-card span{display:block;margin-top:3px;color:#6d7789;font-size:12px;line-height:1.25;font-weight:650}
            .appleklinika-product-assurance{padding:4px 0}
            .appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
            .appleklinika-buy-panel > p:empty{display:none!important;margin:0!important}
            .appleklinika-product-data{display:grid;gap:18px;padding:26px;border:1px solid #e5e7eb;border-radius:28px;background:#fff;box-shadow:0 20px 56px rgba(15,23,42,.07)}
            .appleklinika-section-heading{display:grid;gap:4px}
            .appleklinika-section-kicker{color:#d6001c;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
            .appleklinika-section-title{margin:0;color:#111820;font-size:24px;line-height:1.18;font-weight:850}
            .appleklinika-section-text{margin:0;color:#667085;font-size:14px;line-height:1.55;font-weight:650}
            .appleklinika-config-group{display:grid;gap:10px}
            .appleklinika-config-group__title{margin:0;color:#313a4a;font-size:14px;line-height:1.2;font-weight:850}
            .appleklinika-config-group__title span{font-weight:950}
            .appleklinika-config-grid,.appleklinika-config-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;align-items:stretch}
            .appleklinika-config-card{position:relative;display:flex;min-height:88px;height:100%;padding:14px 38px 14px 14px;border:1.5px solid #d8e0eb;border-radius:18px;background:#fff;color:#2e3746;text-align:left;text-decoration:none;cursor:pointer;box-shadow:0 8px 20px rgba(17,24,32,.04);transition:border-color .16s ease,box-shadow .16s ease,background .16s ease,transform .16s ease}
            .appleklinika-config-card:hover{transform:translateY(-1px);border-color:#f3a1ad;background:#fffafa}
            .appleklinika-config-card.is-selected,.appleklinika-config-card.is-active{border-color:#d6001c;background:#fff5f6;box-shadow:0 0 0 3px rgba(214,0,28,.1),0 10px 24px rgba(17,24,32,.05)}
            .appleklinika-config-card.is-selected:after,.appleklinika-config-card.is-active:after{content:"✓";position:absolute;top:12px;right:12px;width:20px;height:20px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#d6001c;color:#fff;font-size:12px;line-height:1;font-weight:950}
            .appleklinika-config-card__image{flex:0 0 54px;width:54px;height:54px;object-fit:contain;border-radius:13px;background:#f7f8fa}
            .appleklinika-config-card__content{display:flex;min-width:0;flex:1;height:100%;flex-direction:column;justify-content:flex-start;gap:7px}
            .appleklinika-config-card__label{display:-webkit-box;min-height:36px;overflow:hidden;color:#263242;font-size:14px;line-height:1.25;font-weight:850;-webkit-box-orient:vertical;-webkit-line-clamp:2}
            .appleklinika-config-card__meta{display:block;overflow:hidden;max-width:100%;margin-top:auto;color:#667085;font-size:12px;line-height:1.2;font-weight:800;white-space:nowrap;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
            .appleklinika-config-card__badge{position:absolute;right:8px;top:-8px;padding:3px 7px;border-radius:999px;background:#d6001c;color:#fff;font-size:10px;font-weight:900}
            .appleklinika-config-card--color{align-items:center;gap:12px}
            .appleklinika-config-card--color .appleklinika-config-card__content{justify-content:center}
            .appleklinika-config-card--color .appleklinika-config-card__label{display:block;min-height:auto;max-height:38px;white-space:normal;-webkit-line-clamp:initial;-webkit-box-orient:initial}
            .appleklinika-config-card--pill .appleklinika-config-card__content{align-items:flex-start}
            .appleklinika-config-card--wide{grid-column:auto}
            .appleklinika-config-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
            .appleklinika-info-card{display:grid;gap:3px;padding:12px;border:1px solid #e1e7ef;border-radius:16px;background:#fafafa}
            .appleklinika-info-card span{color:#6b7585;font-size:11px;font-weight:750}
            .appleklinika-info-card strong{color:#18202b;font-size:13px;line-height:1.25}
            .appleklinika-product-content-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:32px;align-items:start}
            .appleklinika-product-main-info{display:grid;gap:18px}
            .appleklinika-product-panel{padding:26px;border:1px solid #e5e7eb;border-radius:26px;background:#fff;box-shadow:0 18px 48px rgba(15,23,42,.06)}
            .appleklinika-product-panel h2{margin:0 0 14px;color:#111820;font-size:26px;line-height:1.18;font-weight:850}
            .appleklinika-product-panel p{color:#4b5563;font-size:15px;line-height:1.65}
            .appleklinika-product-panel p:first-child{margin-top:0}
            .appleklinika-product-panel p:last-child{margin-bottom:0}
            .appleklinika-spec-table{display:grid;margin-top:10px;border-top:1px solid #eef1f5}
            .appleklinika-spec-row{display:grid;grid-template-columns:minmax(140px,.42fr) minmax(0,1fr);gap:18px;padding:13px 0;border-bottom:1px solid #eef1f5}
            .appleklinika-spec-row span{color:#667085;font-size:13px;font-weight:750}
            .appleklinika-spec-row strong{color:#18202b;font-size:14px;font-weight:850}
            .appleklinika-related-panel{position:sticky;top:24px}
            .admin-bar .appleklinika-related-panel{top:56px}
            .appleklinika-related-list{display:grid;gap:12px}
            .appleklinika-related-card{display:grid;grid-template-columns:64px minmax(0,1fr);gap:12px;align-items:center;padding:10px;border:1px solid #edf0f4;border-radius:18px;background:#fff;text-decoration:none;color:#111820;transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}
            .appleklinika-related-card:hover{transform:translateY(-1px);border-color:#f3a1ad;box-shadow:0 12px 28px rgba(15,23,42,.08)}
            .appleklinika-related-card img{width:64px;height:64px;object-fit:contain;border-radius:14px;background:#f8fafc}
            .appleklinika-related-card strong{display:-webkit-box;overflow:hidden;font-size:13px;line-height:1.25;font-weight:850;-webkit-line-clamp:2;-webkit-box-orient:vertical}
            .appleklinika-related-card span{display:block;margin-top:5px;color:#d6001c;font-size:13px;font-weight:850}
            .appleklinika-review-list{display:grid;gap:12px}
            .appleklinika-review-card{padding:14px;border:1px solid #edf0f4;border-radius:18px;background:#fafafa}
            .appleklinika-review-card strong{display:block;margin-bottom:6px;color:#111820;font-size:14px}
            .appleklinika-review-card p{margin:0;color:#4b5563;font-size:14px;line-height:1.5}
            .appleklinika-empty-note{margin:0;color:#667085;font-size:14px;line-height:1.55}
            .appleklinika-lightbox{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(10,14,20,.88)}
            .appleklinika-lightbox.is-open{display:flex}
            .appleklinika-lightbox__image{max-width:min(920px,90vw);max-height:82vh;border-radius:18px;background:#fff;object-fit:contain}
            .appleklinika-lightbox__close,.appleklinika-lightbox__nav{position:absolute;border:0;border-radius:12px;background:#fff;color:#151b24;font-weight:900;cursor:pointer}
            .appleklinika-lightbox__close{top:16px;right:16px;min-height:40px;padding:0 14px}
            .appleklinika-lightbox__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;font-size:28px}
            .appleklinika-lightbox__nav--prev{left:16px}
            .appleklinika-lightbox__nav--next{right:16px}
            @media (max-width:1100px){body.single-product main{padding:24px 18px 56px}.appleklinika-product-hero,.appleklinika-product-content-grid{grid-template-columns:1fr}.appleklinika-buy-panel,.appleklinika-related-panel{position:static}.appleklinika-product-gallery__stage{min-height:460px}.appleklinika-product-gallery__stage img{max-height:420px}.appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-grid,.appleklinika-config-row{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-info{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media (max-width:640px){body.single-product main{padding:16px 12px 42px}.appleklinika-product-shell{gap:26px}.appleklinika-product-hero{gap:22px}.appleklinika-product-gallery__stage{min-height:0;aspect-ratio:4/3;border-radius:22px}.appleklinika-product-gallery__stage img{max-width:86%;max-height:86%}.appleklinika-product-gallery__thumbs{justify-content:flex-start;gap:8px;margin-top:12px}.appleklinika-product-gallery__thumb{flex-basis:60px;padding:6px;border-radius:14px}.appleklinika-product-gallery__nav{width:34px;height:34px;font-size:22px}.appleklinika-product-gallery__nav--prev{left:10px}.appleklinika-product-gallery__nav--next{right:10px}.appleklinika-product-gallery__zoom{right:10px;bottom:10px;min-height:34px}.appleklinika-buy-panel,.appleklinika-product-data,.appleklinika-product-panel{padding:20px;border-radius:22px}.appleklinika-buy-panel h1{font-size:27px!important}.appleklinika-price-stack__current{font-size:34px}.appleklinika-cart-area form.cart{grid-template-columns:1fr}.appleklinika-config-grid,.appleklinika-config-row,.appleklinika-config-info,.appleklinika-trust-grid{grid-template-columns:1fr}.appleklinika-spec-row{grid-template-columns:1fr;gap:4px}.appleklinika-lightbox__nav{width:36px;height:36px}.appleklinika-lightbox__nav--prev{left:8px}.appleklinika-lightbox__nav--next{right:8px}}
        </style>';
    }

    public function renderScripts(): void
    {
        if (is_admin() || ! function_exists('is_product') || ! is_product()) {
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
        echo '<div class="appleklinika-product-hero">';
        $this->renderProductGallery($images);
        $this->renderBuyPanel($product, $productId);
        echo '</div>';
        echo '<section class="appleklinika-product-assurance" aria-label="Apple Klinika vásárlási biztosítékok">';
        $this->renderTrustCards($productId);
        echo '</section>';
        $this->renderConfigurationPanel($product, $productId, $firstImage, $relatedProducts);
        $this->renderProductInformation($product, $productId, $relatedProducts);
        echo '</section>';
        echo '<div class="appleklinika-lightbox" data-appleklinika-lightbox role="dialog" aria-modal="true" aria-label="Nagyított termékkép">';
        echo '<button class="appleklinika-lightbox__close" type="button" data-appleklinika-lightbox-close>Bezárás</button>';
        echo '<button class="appleklinika-lightbox__nav appleklinika-lightbox__nav--prev" type="button" data-appleklinika-lightbox-direction="-1" aria-label="Előző nagyított kép">‹</button>';
        echo '<img class="appleklinika-lightbox__image" data-appleklinika-lightbox-image src="' . esc_url($firstImage['url']) . '" alt="">';
        echo '<button class="appleklinika-lightbox__nav appleklinika-lightbox__nav--next" type="button" data-appleklinika-lightbox-direction="1" aria-label="Következő nagyított kép">›</button>';
        echo '</div>';
        echo '<script type="application/json" id="appleklinika-product-selector-data">' . wp_json_encode($this->productSelectorPayload($relatedProducts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    }

    /**
     * @param array<int, array{url: string, srcset: string, html: string}> $images
     */
    private function renderProductGallery(array $images): void
    {
        $firstImage = $images[0];

        echo '<div class="appleklinika-product-gallery">';
        echo '<div class="appleklinika-product-gallery__stage">';
        echo $this->imageHtml($firstImage, 'data-appleklinika-stage-image');
        echo '<button class="appleklinika-product-gallery__nav appleklinika-product-gallery__nav--prev" type="button" data-appleklinika-gallery-direction="-1" aria-label="Előző kép">‹</button>';
        echo '<button class="appleklinika-product-gallery__nav appleklinika-product-gallery__nav--next" type="button" data-appleklinika-gallery-direction="1" aria-label="Következő kép">›</button>';
        echo '<button class="appleklinika-product-gallery__zoom" type="button" data-appleklinika-gallery-zoom>Nagyítás</button>';
        echo '</div>';
        echo '<div class="appleklinika-product-gallery__thumbs" aria-label="Termékképek">';

        foreach ($images as $index => $image) {
            $class = $index === 0 ? 'appleklinika-product-gallery__thumb is-selected' : 'appleklinika-product-gallery__thumb';
            echo '<button class="' . esc_attr($class) . '" type="button" data-appleklinika-gallery-thumb data-full="' . esc_url($image['url']) . '" data-srcset="' . esc_attr($image['srcset']) . '" aria-label="' . esc_attr(sprintf('Termékkép %d', $index + 1)) . '">' . $this->imageHtml($image) . '</button>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function renderBuyPanel(\WC_Product $product, int $productId): void
    {
        echo '<aside class="appleklinika-buy-panel" aria-label="Vásárlási panel">';
        echo '<span class="' . esc_attr($this->stockBadgeClass($product)) . '" data-appleklinika-stock-badge>' . esc_html($this->stockLabel($product)) . '</span>';
        echo '<h1 data-appleklinika-product-title>' . esc_html($product->get_name()) . '</h1>';

        $lead = trim(wp_strip_all_tags((string) $product->get_short_description()));

        if ($lead !== '') {
            echo '<p class="appleklinika-product-lead">' . esc_html(wp_trim_words($lead, 24)) . '</p>';
        }

        $this->renderPrice($product);
        echo '<div class="appleklinika-cart-area">';
        woocommerce_template_single_add_to_cart();
        echo '<div class="appleklinika-add-feedback" data-appleklinika-add-feedback>Kosárba téve. A kosár frissült.</div>';
        echo '</div>';
        $this->renderDelivery($product);
        echo '</aside>';
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     */
    private function renderProductInformation(\WC_Product $product, int $productId, array $relatedProducts): void
    {
        echo '<section class="appleklinika-product-content-grid" aria-label="Termék részletei">';
        echo '<div class="appleklinika-product-main-info">';
        $this->renderProductDescriptionPanel($product);
        $this->renderProductSpecsPanel($product, $productId);
        $this->renderReviewsPanel($product);
        echo '</div>';
        $this->renderSimilarProductsPanel($product, $relatedProducts);
        echo '</section>';
    }

    private function renderProductDescriptionPanel(\WC_Product $product): void
    {
        $description = trim((string) $product->get_description());

        if ($description === '') {
            $description = trim((string) $product->get_short_description());
        }

        if ($description === '') {
            return;
        }

        echo '<section class="appleklinika-product-panel" aria-label="Termékleírás">';
        echo '<h2>Termékleírás</h2>';
        echo wp_kses_post(apply_filters('the_content', $description));
        echo '</section>';
    }

    private function renderProductSpecsPanel(\WC_Product $product, int $productId): void
    {
        $rows = $this->productSpecRows($product, $productId);

        if ($rows === []) {
            return;
        }

        echo '<section class="appleklinika-product-panel" aria-label="Termékadatok">';
        echo '<h2>Termékadatok</h2>';
        echo '<div class="appleklinika-spec-table">';

        foreach ($rows as $row) {
            echo '<div class="appleklinika-spec-row">';
            echo '<span>' . esc_html($row['label']) . '</span>';
            echo '<strong>' . esc_html($row['value']) . '</strong>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function productSpecRows(\WC_Product $product, int $productId): array
    {
        $modelKey = $this->conditionRepository->get($productId, 'device_model');
        $rows = [
            ['label' => 'Modell', 'value' => $this->modelLabel($modelKey)],
            ['label' => 'Tárhely', 'value' => $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity'))],
            ['label' => 'Szín', 'value' => $this->colorLabel($modelKey, $this->conditionRepository->get($productId, 'color'))],
            ['label' => 'Állapot', 'value' => $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade'))],
            ['label' => 'Akkumulátor', 'value' => $this->batteryText($productId)],
            ['label' => 'Garancia', 'value' => $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'))],
            ['label' => 'SIM', 'value' => $this->simConfigLabel($this->conditionRepository->get($productId, 'sim_config'))],
            ['label' => 'Tartozékok', 'value' => $this->conditionRepository->get($productId, 'accessories')],
            ['label' => 'Készlet', 'value' => $this->stockLabel($product)],
        ];

        if ($product->get_sku() !== '') {
            $rows[] = ['label' => 'Cikkszám', 'value' => $product->get_sku()];
        }

        foreach ($this->productAttributeRows($product) as $attributeRow) {
            $rows[] = $attributeRow;
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            return trim($row['value']) !== '';
        }));
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function productAttributeRows(\WC_Product $product): array
    {
        $rows = [];

        foreach ($product->get_attributes() as $attribute) {
            if (! $attribute instanceof \WC_Product_Attribute) {
                continue;
            }

            $label = wc_attribute_label($attribute->get_name());
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                : $attribute->get_options();

            if (is_wp_error($values)) {
                continue;
            }

            $value = implode(', ', array_filter(array_map('strval', $values)));

            if ($label !== '' && $value !== '') {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     */
    private function renderSimilarProductsPanel(\WC_Product $product, array $relatedProducts): void
    {
        $products = $this->visibleSimilarProducts($product, $relatedProducts);

        if ($products === []) {
            return;
        }

        echo '<aside class="appleklinika-product-panel appleklinika-related-panel" aria-label="Hasonló termékek">';
        echo '<h2>Hasonló termékek</h2>';
        echo '<div class="appleklinika-related-list">';

        foreach ($products as $relatedProduct) {
            $image = $this->productImages($relatedProduct)[0];
            echo '<a class="appleklinika-related-card" href="' . esc_url(get_permalink($relatedProduct->get_id())) . '">';
            echo $this->imageHtml($image);
            echo '<span>';
            echo '<strong>' . esc_html($relatedProduct->get_name()) . '</strong>';
            echo '<span>' . wp_kses_post($relatedProduct->get_price_html()) . '</span>';
            echo '</span>';
            echo '</a>';
        }

        echo '</div>';
        echo '</aside>';
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     * @return array<int, \WC_Product>
     */
    private function visibleSimilarProducts(\WC_Product $product, array $relatedProducts): array
    {
        $items = array_values(array_filter($relatedProducts, static function (\WC_Product $relatedProduct) use ($product): bool {
            return $relatedProduct->get_id() !== $product->get_id();
        }));

        if (count($items) < 4 && function_exists('wc_get_related_products')) {
            foreach (wc_get_related_products($product->get_id(), 4) as $productId) {
                $relatedProduct = wc_get_product($productId);

                if ($relatedProduct instanceof \WC_Product && $relatedProduct->get_id() !== $product->get_id()) {
                    $items[$relatedProduct->get_id()] = $relatedProduct;
                }
            }
        }

        return array_slice(array_values($items), 0, 4);
    }

    private function renderReviewsPanel(\WC_Product $product): void
    {
        $comments = get_comments([
            'post_id' => $product->get_id(),
            'status' => 'approve',
            'type' => 'review',
            'number' => 3,
        ]);

        echo '<section class="appleklinika-product-panel" aria-label="Vásárlói értékelések">';
        echo '<h2>Vásárlói értékelések</h2>';

        if ($comments === []) {
            echo '<p class="appleklinika-empty-note">Ehhez a termékhez még nincs publikus vásárlói értékelés.</p>';
            echo '</section>';
            return;
        }

        echo '<div class="appleklinika-review-list">';

        foreach ($comments as $comment) {
            echo '<article class="appleklinika-review-card">';
            echo '<strong>' . esc_html($comment->comment_author) . '</strong>';
            echo '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($comment->comment_content), 32)) . '</p>';
            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
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

    public function singleAddToCartText(): string
    {
        return 'Kosárba teszem';
    }

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

        echo '<aside class="appleklinika-product-data" aria-label="Termékválasztó opciók">';
        echo '<div class="appleklinika-section-heading">';
        echo '<span class="appleklinika-section-kicker">Konfiguráció</span>';
        echo '<h2 class="appleklinika-section-title">Válaszd ki a készülékedet</h2>';
        echo '<p class="appleklinika-section-text">Az elérhető opciók a tényleges WooCommerce termékekből és Apple Klinika termékadatokból épülnek.</p>';
        echo '</div>';
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
        echo '<div class="appleklinika-trust-grid" data-appleklinika-trust>';

        foreach ($this->productTrustBlocks($productId) as $block) {
            echo '<div class="appleklinika-trust-card">';
            echo '<span class="appleklinika-trust-card__icon">' . esc_html($block['icon']) . '</span>';
            echo '<div><strong>' . esc_html($block['title']) . '</strong><span>' . esc_html($block['text']) . '</span></div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Central product-page trust copy. Replace this later with an admin-backed content source if the policy text changes.
     *
     * @return array<int, array{icon: string, title: string, text: string}>
     */
    private function productTrustBlocks(int $productId): array
    {
        $warranty = $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'));
        $blocks = [];

        if ($warranty !== '') {
            $blocks[] = [
                'icon' => '1',
                'title' => $warranty . ' garancia',
                'text' => 'Termékadatok alapján',
            ];
        }

        $blocks[] = [
            'icon' => '✓',
            'title' => 'Ellenőrzött készülék',
            'text' => 'Apple Klinika állapotadatokkal',
        ];
        $blocks[] = [
            'icon' => '↺',
            'title' => 'Visszaküldés',
            'text' => 'A visszaküldési tájékoztató szerint',
        ];
        $blocks[] = [
            'icon' => '•',
            'title' => 'Valós termékfotók',
            'text' => 'A feltöltött WooCommerce galériából',
        ];

        return array_slice($blocks, 0, 4);
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
            $label = (string) $availability['availability'];
            $normalized = strtolower($label);

            if ($normalized === 'in stock') {
                return 'Készleten';
            }

            if ($normalized === 'out of stock') {
                return 'Nincs készleten';
            }

            return $label;
        }

        return $product->is_in_stock() ? 'Készleten' : 'Nincs készleten';
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
