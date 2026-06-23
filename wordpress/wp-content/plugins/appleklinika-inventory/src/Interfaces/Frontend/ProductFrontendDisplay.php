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
            body.single-product main,body.single-product .ak-single-product-page{max-width:1360px;margin:0 auto;padding:24px 24px 68px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111820}
            body.single-product div.product{display:block}
            body.single-product .summary.entry-summary{display:none}
            body.single-product main .woocommerce-breadcrumb,body.single-product main .woocommerce-notices-wrapper{display:none}
            .appleklinika-product-shell,.appleklinika-product-shell *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .appleklinika-product-shell{display:grid;gap:28px}
            .appleklinika-product-shell button{font-family:inherit}
            .appleklinika-product-hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(380px,410px);gap:34px;align-items:start}
            .appleklinika-product-gallery{min-width:0}
            .appleklinika-product-gallery__stage{position:relative;display:flex;align-items:center;justify-content:center;min-height:468px;border:1px solid #e7edf5;border-radius:22px;background:radial-gradient(circle at 50% 42%,#fff 0%,#f8fafc 58%,#eef2f7 100%);overflow:hidden;box-shadow:0 22px 52px rgba(15,23,42,.08)}
            .appleklinika-product-gallery__stage img{display:block;width:auto;max-width:94%;height:auto;max-height:442px;object-fit:contain;filter:drop-shadow(0 18px 30px rgba(15,23,42,.13));transition:transform .22s ease}
            .appleklinika-product-gallery__stage:hover img{transform:translateY(-4px) scale(1.018)}
            .appleklinika-product-gallery__nav,.appleklinika-product-gallery__zoom{position:absolute;border:0;cursor:pointer}
            .appleklinika-product-gallery__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.92);color:#151b24;font-size:26px;font-weight:850;box-shadow:0 10px 28px rgba(17,24,32,.14)}
            .appleklinika-product-gallery__nav--prev{left:18px}
            .appleklinika-product-gallery__nav--next{right:18px}
            .appleklinika-product-gallery__zoom{right:18px;bottom:18px;min-height:38px;padding:0 14px;border-radius:999px;background:#151b24;color:#fff;font-size:13px;font-weight:800;box-shadow:0 10px 24px rgba(17,24,32,.18)}
            .appleklinika-product-gallery__thumbs{display:flex;justify-content:center;gap:10px;margin-top:14px;overflow-x:auto;padding:3px 2px 5px}
            .appleklinika-product-gallery__thumb{flex:0 0 68px;border:1px solid #e2e8f0;border-radius:15px;background:#fff;padding:7px;cursor:pointer;box-shadow:0 8px 18px rgba(15,23,42,.052);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
            .appleklinika-product-gallery__thumb:hover{transform:translateY(-2px)}
            .appleklinika-product-gallery__thumb.is-selected{border-color:#d6001c;box-shadow:0 0 0 3px rgba(214,0,28,.12),0 12px 28px rgba(15,23,42,.08)}
            .appleklinika-product-gallery__thumb img{display:block;width:100%;aspect-ratio:1/1;object-fit:contain;border-radius:12px;background:#f8fafc}
            .appleklinika-buy-panel{position:sticky;top:24px;display:grid;gap:13px;padding:26px;border:1px solid #e5e7eb;border-radius:22px;background:#fff;box-shadow:0 24px 58px rgba(15,23,42,.1)}
            .admin-bar .appleklinika-buy-panel{top:56px}
            .appleklinika-stock-badge{display:inline-flex;width:max-content;align-items:center;padding:7px 12px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:13px;font-weight:850}
            .appleklinika-stock-badge--out{background:#fff7ed;color:#c2410c}
            .appleklinika-buy-panel h1{margin:0;font-size:30px!important;line-height:1.08!important;letter-spacing:0;color:#111820;font-weight:850}
            .appleklinika-product-lead{margin:0;color:#667085;font-size:14px;line-height:1.48;font-weight:650}
            .appleklinika-price-stack{display:grid;gap:6px;margin:2px 0}
            .appleklinika-price-stack__old{display:block;color:#8a94a6;font-size:16px;font-weight:800;text-decoration:line-through}
            .appleklinika-price-stack__current{display:block;color:#d6001c;font-size:42px;line-height:1;font-weight:900;letter-spacing:0}
            .appleklinika-price-stack__current .amount{color:inherit;font-size:inherit;font-weight:inherit}
            .appleklinika-price-stack__saving{display:inline-flex;width:max-content;padding:5px 10px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:13px;font-weight:850}
            .appleklinika-cart-area{display:grid;gap:10px;margin-top:2px}
            .appleklinika-cart-area form.cart{display:grid!important;grid-template-columns:128px minmax(0,1fr);gap:12px;align-items:center;margin:0!important}
            .appleklinika-cart-area .quantity{display:flex;align-items:center}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .quantity input{width:100%!important;height:54px!important;border:1px solid #d9e1ea!important;border-radius:16px!important;background:#fff!important;color:#111820!important;font-size:16px!important;font-weight:750!important;text-align:center!important}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .single_add_to_cart_button{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:100%!important;min-height:54px!important;padding:0 22px!important;border:0!important;border-radius:999px!important;background:#d6001c!important;color:#fff!important;font-size:16px!important;font-weight:900!important;line-height:1!important;box-shadow:0 16px 34px rgba(214,0,28,.24)!important;transition:transform .18s ease,box-shadow .18s ease,background .18s ease!important}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .single_add_to_cart_button:hover{transform:translateY(-1px);background:#b80018!important;box-shadow:0 18px 38px rgba(214,0,28,.3)!important}
            .appleklinika-cart-area .stock{display:none}
            .appleklinika-add-feedback{display:none;padding:11px 12px;border-radius:14px;background:#f0fdf4;color:#166534;font-size:13px;font-weight:800}
            .appleklinika-add-feedback.is-visible{display:block}
            .appleklinika-delivery-note{margin:0;padding:12px 14px;border:1px solid #edf0f4;border-radius:15px;background:#fafafa;color:#6d7789;font-size:13px;line-height:1.4}
            .appleklinika-delivery-note strong{color:#1f2735}
            .appleklinika-delivery-note span{color:#166534;font-weight:850}
            .appleklinika-trust-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
            .appleklinika-trust-card{display:flex;align-items:center;gap:10px;min-height:62px;border:1px solid #eceff3;border-radius:17px;background:#fff;padding:11px 13px;box-shadow:0 9px 22px rgba(15,23,42,.052)}
            .appleklinika-trust-card__icon{display:flex;flex:0 0 32px;align-items:center;justify-content:center;width:32px;height:32px;border-radius:11px;background:#fef2f2;color:#d6001c;font-weight:950}
            .appleklinika-trust-card strong{display:block;color:#293243;font-size:12.5px;line-height:1.2;font-weight:850}
            .appleklinika-trust-card span{display:block;margin-top:3px;color:#6d7789;font-size:12px;line-height:1.25;font-weight:650}
            .appleklinika-product-assurance{padding:0}
            .appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
            .appleklinika-buy-panel > p:empty{display:none!important;margin:0!important}
            .appleklinika-product-below-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(280px,.84fr);gap:22px;align-items:start}
            .appleklinika-below-panel{display:grid;gap:16px;min-width:0;padding:20px;border:1px solid #e6ebf2;border-radius:24px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.06)}
            .appleklinika-below-panel__heading{display:grid;gap:5px}
            .appleklinika-below-panel__heading h2{margin:0;color:#111820;font-size:21px;line-height:1.16;font-weight:850}
            .appleklinika-below-panel__heading p{margin:0;color:#667085;font-size:13px;line-height:1.45;font-weight:650}
            .appleklinika-below-panel .appleklinika-section-kicker{font-size:10.5px}
            .appleklinika-below-panel .appleklinika-config-group{gap:7px}
            .appleklinika-below-panel .appleklinika-config-grid,.appleklinika-below-panel .appleklinika-config-row{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .appleklinika-below-panel .appleklinika-color-swatch-grid{grid-template-columns:repeat(auto-fit,minmax(72px,1fr));max-width:none}
            .appleklinika-below-panel .appleklinika-config-card{min-height:58px;padding:9px 28px 9px 10px;border-radius:14px}
            .appleklinika-below-panel .appleklinika-config-card__label{min-height:auto;font-size:12px;line-height:1.18}
            .appleklinika-below-panel .appleklinika-config-card__meta{font-size:10.5px}
            .appleklinika-below-panel .appleklinika-config-card--color{min-height:76px;padding:9px 7px 8px}
            .appleklinika-below-panel .appleklinika-color-swatch{width:34px;height:34px}
            .appleklinika-below-panel .appleklinika-config-card--color.is-selected:after,.appleklinika-below-panel .appleklinika-config-card--color.is-active:after{right:calc(50% - 25px);width:15px;height:15px;font-size:9px}
            .appleklinika-below-panel .appleklinika-config-info{grid-template-columns:1fr;gap:8px}
            .appleklinika-below-panel--support .appleklinika-trust-grid{grid-template-columns:1fr}
            .appleklinika-compact-spec-table{display:grid;border-top:1px solid #eef1f5}
            .appleklinika-compact-spec-row{display:grid;grid-template-columns:minmax(92px,.45fr) minmax(0,1fr);gap:10px;padding:10px 0;border-bottom:1px solid #eef1f5}
            .appleklinika-compact-spec-row span{color:#667085;font-size:12px;font-weight:750}
            .appleklinika-compact-spec-row strong{color:#18202b;font-size:12.5px;line-height:1.25;font-weight:850}
            .appleklinika-product-data{display:grid;gap:14px;padding:22px;border:1px solid #e6ebf2;border-radius:24px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.065)}
            .appleklinika-section-heading{display:grid;gap:5px;padding-bottom:2px}
            .appleklinika-section-heading > p:empty{display:none!important;margin:0!important}
            .appleklinika-section-kicker{color:#d6001c;font-size:11px;font-weight:900;letter-spacing:.09em;text-transform:uppercase}
            .appleklinika-section-title{margin:0;color:#111820;font-size:22px;line-height:1.15;font-weight:850}
            .appleklinika-section-text{max-width:680px;margin:0;color:#667085;font-size:13px;line-height:1.45;font-weight:650}
            .appleklinika-config-group{display:grid;gap:8px}
            .appleklinika-config-group__title{margin:0;color:#313a4a;font-size:12.5px;line-height:1.2;font-weight:850}
            .appleklinika-config-group__title span{font-weight:950}
            .appleklinika-config-grid,.appleklinika-config-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:stretch}
            .appleklinika-config-card{position:relative;display:flex;min-height:68px;height:100%;padding:10px 32px 10px 10px;border:1.5px solid #dbe3ee;border-radius:15px;background:#fff;color:#2e3746;text-align:left;text-decoration:none;cursor:pointer;box-shadow:0 7px 18px rgba(17,24,32,.035);transition:border-color .16s ease,box-shadow .16s ease,background .16s ease,transform .16s ease}
            .appleklinika-config-card:hover{transform:translateY(-1px);border-color:#f3a1ad;background:#fffafa}
            .appleklinika-config-card.is-selected,.appleklinika-config-card.is-active{border-color:#d6001c;background:#fff6f7;box-shadow:0 0 0 2px rgba(214,0,28,.09),0 9px 22px rgba(17,24,32,.045)}
            .appleklinika-config-card.is-selected:after,.appleklinika-config-card.is-active:after{content:"✓";position:absolute;top:10px;right:10px;width:18px;height:18px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#d6001c;color:#fff;font-size:11px;line-height:1;font-weight:950}
            .appleklinika-config-card.is-unavailable,.appleklinika-config-card:disabled{opacity:.44;border-color:#d7dde7;background:#f8fafc;color:#8a94a6;cursor:not-allowed;box-shadow:none}
            .appleklinika-config-card.is-unavailable:hover,.appleklinika-config-card:disabled:hover{transform:none;border-color:#d7dde7;background:#f8fafc;box-shadow:none}
            .appleklinika-config-card.is-unavailable:after,.appleklinika-config-card:disabled:after{display:none}
            .appleklinika-config-card__image{display:block;align-self:center;flex:0 0 44px;width:44px;height:44px;object-fit:contain;border-radius:11px;background:#f7f8fa}
            .appleklinika-config-card__content{display:flex;min-width:0;flex:1;height:100%;flex-direction:column;justify-content:flex-start;gap:4px}
            .appleklinika-config-card__label{display:-webkit-box;min-height:29px;overflow:hidden;color:#263242;font-size:12.5px;line-height:1.18;font-weight:850;-webkit-box-orient:vertical;-webkit-line-clamp:2}
            .appleklinika-config-card__meta{display:block;overflow:hidden;max-width:100%;margin-top:auto;color:#697386;font-size:11px;line-height:1.2;font-weight:800;white-space:nowrap;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
            .appleklinika-config-card__badge{position:absolute;right:7px;top:-7px;padding:3px 7px;border-radius:999px;background:#d6001c;color:#fff;font-size:9px;font-weight:900}
            .appleklinika-config-card--color{align-items:center;gap:10px}
            .appleklinika-color-swatch-grid{grid-template-columns:repeat(auto-fit,minmax(96px,1fr));max-width:640px}
            .appleklinika-config-card--color{display:grid;justify-items:center;align-content:start;min-height:86px;padding:10px 8px 9px;border-radius:16px;gap:7px}
            .appleklinika-config-card--color .appleklinika-config-card__content{justify-content:center}
            .appleklinika-config-card--color .appleklinika-config-card__content{align-items:center;width:100%;height:auto}
            .appleklinika-config-card--color .appleklinika-config-card__label{display:block;min-height:auto;max-height:32px;text-align:center;white-space:normal;-webkit-line-clamp:initial;-webkit-box-orient:initial}
            .appleklinika-color-swatch{display:block;width:40px;height:40px;border:1px solid var(--appleklinika-swatch-border,#cbd5e1);border-radius:50%;background:var(--appleklinika-swatch,#e5e7eb);box-shadow:inset 0 0 0 1px rgba(255,255,255,.45),0 7px 16px rgba(15,23,42,.08)}
            .appleklinika-color-swatch--unknown{background:linear-gradient(135deg,#f8fafc,#d8dee8)}
            .appleklinika-config-card--color.is-selected .appleklinika-color-swatch,.appleklinika-config-card--color.is-active .appleklinika-color-swatch{box-shadow:0 0 0 3px #fff,0 0 0 5px #d6001c,inset 0 0 0 1px rgba(255,255,255,.45),0 9px 18px rgba(214,0,28,.16)}
            .appleklinika-config-card--color.is-selected:after,.appleklinika-config-card--color.is-active:after{top:8px;right:calc(50% - 29px);width:16px;height:16px;font-size:10px}
            .appleklinika-config-card--pill .appleklinika-config-card__content{align-items:flex-start}
            .appleklinika-config-card--wide{grid-column:auto}
            .appleklinika-config-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
            .appleklinika-info-card{display:grid;min-height:50px;gap:3px;padding:9px 10px;border:1px solid #e3e9f1;border-radius:14px;background:#fbfcfd}
            .appleklinika-info-card span{color:#6b7585;font-size:10.5px;font-weight:750}
            .appleklinika-info-card strong{color:#18202b;font-size:12.5px;line-height:1.22}
            .ak-single-product__lower.ak-single-product__details.appleklinika-product-content-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:30px;align-items:start}
            .ak-single-product__details-main.appleklinika-product-main-info{display:grid;gap:18px}
            .ak-single-product__details .appleklinika-product-panel{padding:24px;border:1px solid #e5eaf2;border-radius:22px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.055)}
            .ak-single-product__details .appleklinika-product-panel h2{margin:0 0 14px;color:#111820;font-size:24px;line-height:1.18;font-weight:880;letter-spacing:0}
            .ak-single-product__description p{margin:0 0 12px;color:#4b5563;font-size:15px;line-height:1.68;font-weight:520}
            .ak-single-product__description p:last-child{margin-bottom:0}
            .ak-single-product__description ul,.ak-single-product__description ol{margin:12px 0 0;padding-left:20px;color:#4b5563;font-size:15px;line-height:1.65}
            .ak-single-product__data .appleklinika-spec-table{display:grid;margin-top:8px;border-top:1px solid #eef2f7}
            .ak-single-product__data .appleklinika-spec-row{display:grid;grid-template-columns:minmax(132px,.38fr) minmax(0,1fr);gap:18px;align-items:start;padding:14px 0;border-bottom:1px solid #eef2f7}
            .ak-single-product__data .appleklinika-spec-row span{color:#6b7585;font-size:13px;font-weight:750}
            .ak-single-product__data .appleklinika-spec-row strong{color:#18202b;font-size:14px;line-height:1.35;font-weight:850}
            .ak-single-product__reviews .appleklinika-review-list{display:grid;gap:12px}
            .ak-single-product__reviews .appleklinika-review-card{padding:15px;border:1px solid #edf1f6;border-radius:16px;background:#fbfcfd}
            .ak-single-product__reviews .appleklinika-review-card strong{display:block;margin-bottom:6px;color:#111820;font-size:14px;line-height:1.25;font-weight:850}
            .ak-single-product__reviews .appleklinika-review-card p{margin:0;color:#4b5563;font-size:14px;line-height:1.5}
            .ak-single-product__reviews .appleklinika-empty-note{margin:0;padding:14px 15px;border:1px solid #edf1f6;border-radius:16px;background:#fbfcfd;color:#667085;font-size:14px;line-height:1.55;font-weight:650}
            .ak-single-product__related.appleklinika-related-panel{position:sticky;top:24px;padding:22px;border-radius:22px}
            .admin-bar .ak-single-product__related.appleklinika-related-panel{top:56px}
            .ak-single-product__related .appleklinika-related-list{display:grid;gap:10px}
            .ak-single-product__related-card.appleklinika-related-card{display:grid;grid-template-columns:60px minmax(0,1fr);gap:12px;align-items:center;padding:10px;border:1px solid #edf1f6;border-radius:16px;background:#fff;text-decoration:none;color:#111820;box-shadow:0 8px 20px rgba(15,23,42,.035);transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease,background .16s ease}
            .ak-single-product__related-card.appleklinika-related-card:hover{transform:translateY(-1px);border-color:#f2a6b0;background:#fffafa;box-shadow:0 12px 28px rgba(15,23,42,.075)}
            .ak-single-product__related-thumb{display:flex;align-items:center;justify-content:center;width:60px;height:60px;border:1px solid #eef2f7;border-radius:13px;background:#f8fafc;overflow:hidden}
            .ak-single-product__related-thumb img{display:block;width:100%;height:100%;object-fit:cover;border-radius:10px;background:#f8fafc}
            .ak-single-product__related-body{display:grid;gap:6px;min-width:0}
            .ak-single-product__related-title{display:-webkit-box;overflow:hidden;color:#18202b;font-size:13.5px;line-height:1.25;font-weight:850;-webkit-line-clamp:2;-webkit-box-orient:vertical}
            .ak-single-product__related-price{display:flex;flex-wrap:wrap;gap:4px 8px;align-items:baseline;color:#111820;font-size:13px;line-height:1.2;font-weight:850}
            .ak-single-product__related-price del{color:#8a94a6;font-size:12px;font-weight:750;text-decoration-thickness:1px}
            .ak-single-product__related-price ins{color:#111820;font-size:13.5px;font-weight:900;text-decoration:none}
            .ak-single-product__related-price .amount{color:inherit;font-size:inherit;font-weight:inherit;white-space:nowrap}
            .ak-single-product__related-saving{display:inline-flex;width:max-content;max-width:100%;padding:3px 7px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:10.5px;line-height:1.1;font-weight:850}
            .appleklinika-lightbox{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(10,14,20,.88)}
            .appleklinika-lightbox.is-open{display:flex}
            .appleklinika-lightbox__image{max-width:min(920px,90vw);max-height:82vh;border-radius:18px;background:#fff;object-fit:contain}
            .appleklinika-lightbox__close,.appleklinika-lightbox__nav{position:absolute;border:0;border-radius:12px;background:#fff;color:#151b24;font-weight:900;cursor:pointer}
            .appleklinika-lightbox__close{top:16px;right:16px;min-height:40px;padding:0 14px}
            .appleklinika-lightbox__nav{top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;font-size:28px}
            .appleklinika-lightbox__nav--prev{left:16px}
            .appleklinika-lightbox__nav--next{right:16px}
            @media (max-width:1100px){body.single-product main{padding:24px 18px 56px}.appleklinika-product-hero,.appleklinika-product-below-hero,.appleklinika-product-content-grid{grid-template-columns:1fr}.appleklinika-buy-panel,.appleklinika-related-panel{position:static}.appleklinika-product-gallery__stage{min-height:460px}.appleklinika-product-gallery__stage img{max-height:420px}.appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-grid,.appleklinika-config-row{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-info{grid-template-columns:repeat(2,minmax(0,1fr))}}
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
                const productBackedSelectorGroups = ["color", "storage", "condition"];

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

                function hasAvailableProductForOption(groupName, optionValue, selectedValues) {
                    if (productBackedSelectorGroups.indexOf(groupName) === -1) {
                        return true;
                    }

                    return selectorProducts.some(function (product) {
                        if (!product.values || product.values[groupName] !== optionValue) {
                            return false;
                        }

                        return productBackedSelectorGroups.every(function (otherGroup) {
                            if (otherGroup === groupName) {
                                return true;
                            }

                            return !selectedValues[otherGroup] || product.values[otherGroup] === selectedValues[otherGroup];
                        });
                    });
                }

                function setOptionAvailability(option, available) {
                    option.disabled = !available;
                    option.classList.toggle("is-unavailable", !available);
                    option.setAttribute("aria-disabled", available ? "false" : "true");
                    if (available) {
                        option.removeAttribute("tabindex");
                    } else {
                        option.setAttribute("tabindex", "-1");
                    }
                }

                function updateSelectorAvailability() {
                    if (!selectorProducts.length) {
                        return;
                    }

                    const selectedValues = selectedSelectorValues();

                    document.querySelectorAll("[data-selector-group]").forEach(function (group) {
                        const groupName = group.dataset.selectorGroup || "";

                        if (productBackedSelectorGroups.indexOf(groupName) === -1) {
                            return;
                        }

                        group.querySelectorAll("[data-selector-option]").forEach(function (option) {
                            const optionValue = option.dataset.optionValue || "";
                            setOptionAvailability(option, hasAvailableProductForOption(groupName, optionValue, selectedValues));
                        });
                    });
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
                    updateSelectorAvailability();
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
                        if (option.disabled || option.getAttribute("aria-disabled") === "true") return;
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
                            updateSelectorAvailability();
                            return;
                        }

                        updateProductView(findMatchingProduct(selectedSelectorValues()));
                    });
                });

                updateSelectorAvailability();
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
        $this->renderBelowHeroProductArea($product, $productId, $firstImage, $relatedProducts);
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
        echo '<section class="ak-single-product__lower ak-single-product__details appleklinika-product-content-grid" aria-label="Termék részletei">';
        echo '<div class="ak-single-product__details-main appleklinika-product-main-info">';
        $this->renderProductDescriptionPanel($product);
        $this->renderProductSpecsPanel($product, $productId);
        $this->renderReviewsPanel($product);
        echo '</div>';
        echo '<div class="ak-single-product__details-side appleklinika-product-side-info">';
        $this->renderSimilarProductsPanel($product, $relatedProducts);
        echo '</div>';
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

        echo '<section class="ak-single-product__description appleklinika-product-panel" aria-label="Termékleírás">';
        echo '<h2>Termékleírás</h2>';
        echo wp_kses_post(apply_filters('the_content', $description));
        echo '</section>';
    }

    private function renderProductSpecsPanel(\WC_Product $product, int $productId): void
    {
        $rows = array_values(array_filter($this->productSpecRows($product, $productId), static function (array $row): bool {
            return $row['label'] !== 'Tartozékok';
        }));

        if ($rows === []) {
            return;
        }

        echo '<section class="ak-single-product__data appleklinika-product-panel" aria-label="Termékadatok">';
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

        echo '<aside class="ak-single-product__related appleklinika-product-panel appleklinika-related-panel" aria-label="Hasonló termékek">';
        echo '<h2>Hasonló termékek</h2>';
        echo '<div class="appleklinika-related-list">';

        foreach ($products as $relatedProduct) {
            $image = $this->productImages($relatedProduct)[0];
            echo '<a class="ak-single-product__related-card appleklinika-related-card" href="' . esc_url(get_permalink($relatedProduct->get_id())) . '">';
            echo '<span class="ak-single-product__related-thumb">' . $this->imageHtml($image) . '</span>';
            echo '<span class="ak-single-product__related-body">';
            echo '<span class="ak-single-product__related-title">' . esc_html($relatedProduct->get_name()) . '</span>';
            echo '<span class="ak-single-product__related-price">' . wp_kses_post($relatedProduct->get_price_html()) . '</span>';

            $saving = $this->savingAmount($relatedProduct);

            if ($saving > 0) {
                echo '<span class="ak-single-product__related-saving">' . esc_html(sprintf('Megtakarítás: %s', wp_strip_all_tags(wc_price($saving)))) . '</span>';
            }

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

        echo '<section class="ak-single-product__reviews appleklinika-product-panel" aria-label="Vásárlói értékelések">';
        echo '<h2>Vásárlói értékelések</h2>';

        if ($comments === []) {
            echo '<p class="appleklinika-empty-note">Ehhez a termékhez még nincsenek publikus vásárlói értékelések.</p>';
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
    private function renderBelowHeroProductArea(\WC_Product $product, int $productId, array $fallbackImage, array $relatedProducts): void
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

        echo '<section class="ak-single-product__below-hero appleklinika-product-below-hero" aria-label="Készülék opciók és termékinformációk">';

        echo '<div class="ak-single-product__options appleklinika-below-panel appleklinika-below-panel--device">';
        echo '<div class="appleklinika-below-panel__heading">';
        echo '<span class="appleklinika-section-kicker">Állapot és megjelenés</span>';
        echo '<h2>Készülék kiválasztása</h2>';
        echo '<p>Csak az aktuális készletben elérhető, valós termékadatokból választhatsz.</p>';
        echo '</div>';
        $this->renderConditionSelector($product, $relatedProducts, $currentPrice, $condition);
        $this->renderColorSelector($product, $relatedProducts, $modelKey, $color, $fallbackImage);
        $this->renderBatterySelector($battery);
        echo '</div>';

        echo '<div class="appleklinika-below-panel appleklinika-below-panel--technical">';
        echo '<div class="appleklinika-below-panel__heading">';
        echo '<span class="appleklinika-section-kicker">Kapacitás és adatok</span>';
        echo '<h2>Tárhely és műszaki alapok</h2>';
        echo '<p>A fontos adatok röviden, a részletes termékadatok lentebb maradnak.</p>';
        echo '</div>';
        $this->renderStorageSelector($product, $relatedProducts, $currentPrice, $storage);
        $this->renderCompactProductMeta($product, $productId);
        $this->renderCompactInfoCards($warranty, $accessories, $this->stockLabel($product), $simConfig);
        echo '</div>';

        echo '<div class="appleklinika-below-panel appleklinika-below-panel--support">';
        echo '<div class="appleklinika-below-panel__heading">';
        echo '<span class="appleklinika-section-kicker">Biztonságos vásárlás</span>';
        echo '<h2>Apple Klinika garanciák</h2>';
        echo '<p>Valós termékadatok, ellenőrzött készülék és átlátható vásárlási információk.</p>';
        echo '</div>';
        $this->renderTrustCards($productId);
        echo '</div>';

        echo '</section>';
    }

    private function renderCompactProductMeta(\WC_Product $product, int $productId): void
    {
        $wantedLabels = ['Modell', 'Cikkszám'];
        $rows = array_values(array_filter($this->productSpecRows($product, $productId), static function (array $row) use ($wantedLabels): bool {
            return in_array($row['label'], $wantedLabels, true);
        }));

        if ($rows === []) {
            return;
        }

        echo '<section class="appleklinika-config-group" aria-label="Rövid termékadatok">';
        echo '<h2 class="appleklinika-config-group__title">Rövid termékadatok</h2>';
        echo '<div class="appleklinika-compact-spec-table">';

        foreach ($rows as $row) {
            echo '<div class="appleklinika-compact-spec-row">';
            echo '<span>' . esc_html($row['label']) . '</span>';
            echo '<strong>' . esc_html($row['value']) . '</strong>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
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
                'swatch' => $this->productColorSwatch($colorKey, $this->colorLabel($modelKey, $colorKey)),
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
                'swatch' => $this->productColorSwatch('current', $selectedLabel),
                'meta' => '',
            ];
        }

        $this->renderSelectorGroup('color', 'Szín', $selectedLabel, $items, 'appleklinika-config-grid appleklinika-color-swatch-grid', true);
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
        $cards = array_values(array_filter([
            ['label' => 'Garancia', 'value' => $warranty],
            ['label' => 'SIM', 'value' => $simConfig],
            ['label' => 'Tartozékok', 'value' => $accessories],
            ['label' => 'Készlet', 'value' => $stock],
        ], static function (array $card): bool {
            return trim($card['value']) !== '';
        }));

        if ($cards === []) {
            return;
        }

        echo '<section class="appleklinika-config-group" aria-label="Kiegészítő információk">';
        echo '<div class="appleklinika-config-info">';

        foreach ($cards as $card) {
            $this->renderInfoCard($card['label'], $card['value']);
        }

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

            if ($withImage && isset($item['swatch']) && is_array($item['swatch'])) {
                $swatchClass = ! empty($item['swatch']['known']) ? 'appleklinika-color-swatch' : 'appleklinika-color-swatch appleklinika-color-swatch--unknown';
                $swatchStyle = '--appleklinika-swatch:' . (string) $item['swatch']['background'] . ';--appleklinika-swatch-border:' . (string) $item['swatch']['border'] . ';';
                echo '<span class="' . esc_attr($swatchClass) . '" style="' . esc_attr($swatchStyle) . '" aria-hidden="true"></span>';
            } elseif ($withImage && isset($item['image']) && is_array($item['image'])) {
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

    /**
     * @return array{background: string, border: string, known: bool}
     */
    private function productColorSwatch(string $color, string $label): array
    {
        $map = $this->productColorSwatchMap();
        $candidates = array_filter([
            $color,
            str_replace('-', '_', sanitize_title($color)),
            str_replace('-', '_', sanitize_title($label)),
        ]);

        foreach ($candidates as $candidate) {
            if (isset($map[$candidate])) {
                return $map[$candidate] + ['known' => true];
            }
        }

        return [
            'background' => '#e5e7eb',
            'border' => '#cbd5e1',
            'known' => false,
        ];
    }

    /**
     * @return array<string, array{background: string, border: string}>
     */
    private function productColorSwatchMap(): array
    {
        return [
            'alpine_green' => ['background' => '#5f7167', 'border' => '#48584f'],
            'graphite' => ['background' => '#3d4046', 'border' => '#24272c'],
            'silver' => ['background' => '#f2f3f5', 'border' => '#c8d0da'],
            'gold' => ['background' => '#ead7b8', 'border' => '#c8aa78'],
            'sierra_blue' => ['background' => '#9aa9b8', 'border' => '#788898'],
            'midnight' => ['background' => '#1f2937', 'border' => '#111827'],
            'space_gray' => ['background' => '#62666f', 'border' => '#4b4f57'],
            'black' => ['background' => '#151515', 'border' => '#0b0b0b'],
            'white' => ['background' => '#f8fafc', 'border' => '#d7dee8'],
            'red' => ['background' => '#bf1d2d', 'border' => '#9f1522'],
            'blue' => ['background' => '#4f6f9f', 'border' => '#39567f'],
            'green' => ['background' => '#687d66', 'border' => '#516450'],
            'purple' => ['background' => '#b8a9d9', 'border' => '#9485b9'],
            'pink' => ['background' => '#f3c4cf', 'border' => '#d99aaa'],
            'yellow' => ['background' => '#f4d76a', 'border' => '#d1b34a'],
        ];
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
