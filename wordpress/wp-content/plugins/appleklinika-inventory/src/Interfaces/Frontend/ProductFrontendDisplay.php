<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Frontend;

use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Domain\ModelSpecification\ModelIdentity;
use Appleklinika\Inventory\Application\ModelSpecification\ModelSpecifications;
use Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications;
use Appleklinika\Inventory\Application\ProductSelector\ModelSelectorOptions;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooSimilarDevicesQuery;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class ProductFrontendDisplay
{

    private bool $hasRendered = false;
    private readonly ModelSpecifications $specifications;

    public function __construct(
        private readonly WooProductConditionRepository $conditionRepository,
        private readonly DeviceCatalogRepository $deviceCatalogRepository,
        ?ModelSpecifications $specifications = null
    ) {
        $this->specifications = $specifications ?? new WordPressModelSpecifications();
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
        $root = dirname(__DIR__, 3);
        $plugin = $root . '/appleklinika-inventory.php';
        wp_enqueue_style('appleklinika-product-gallery', plugins_url('assets/product-gallery.css', $plugin), [], (string) filemtime($root . '/assets/product-gallery.css'));
        wp_enqueue_script('appleklinika-product-gallery', plugins_url('assets/product-gallery.js', $plugin), [], (string) filemtime($root . '/assets/product-gallery.js'), true);
        wp_enqueue_style('appleklinika-smart-selector', plugins_url('assets/smart-product-selector.css', $plugin), [], (string) filemtime($root . '/assets/smart-product-selector.css'));
        wp_enqueue_script('appleklinika-smart-selector', plugins_url('assets/smart-product-selector.js', $plugin), [], (string) filemtime($root . '/assets/smart-product-selector.js'), true);
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
            .appleklinika-product-shell{--ak-product-columns:minmax(0,1.1fr) minmax(380px,410px);--ak-product-column-gap:34px;display:grid;grid-template-columns:minmax(0,1fr);gap:28px;min-width:0}
            .appleklinika-product-shell button{font-family:inherit}
            .appleklinika-product-hero{display:grid;grid-template-columns:var(--ak-product-columns);gap:var(--ak-product-column-gap);align-items:start;min-width:0}
            .appleklinika-buy-panel{position:static;top:auto;display:grid;gap:13px;min-width:0;padding:26px;border:1px solid #e5e7eb;border-radius:22px;background:#fff;box-shadow:0 24px 58px rgba(15,23,42,.1)}
            .admin-bar .appleklinika-buy-panel{top:auto}
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
            .appleklinika-cart-area form.cart{display:grid!important;grid-template-columns:minmax(0,1fr);gap:12px;align-items:center;margin:0!important}
            .appleklinika-cart-area .quantity{display:none!important}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .quantity input{width:100%!important;height:54px!important;border:1px solid #d9e1ea!important;border-radius:16px!important;background:#fff!important;color:#111820!important;font-size:16px!important;font-weight:750!important;text-align:center!important}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .single_add_to_cart_button{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:100%!important;min-height:54px!important;padding:0 22px!important;border:0!important;border-radius:999px!important;background:#d6001c!important;color:#fff!important;font-size:16px!important;font-weight:900!important;line-height:1!important;box-shadow:0 16px 34px rgba(214,0,28,.24)!important;transition:transform .18s ease,box-shadow .18s ease,background .18s ease!important}
            body.single-product .appleklinika-buy-panel .appleklinika-cart-area .single_add_to_cart_button:hover{transform:translateY(-1px);background:#b80018!important;box-shadow:0 18px 38px rgba(214,0,28,.3)!important}
            .appleklinika-cart-area .stock{display:none}
            .appleklinika-add-feedback{display:none;padding:11px 12px;border-radius:14px;background:#f0fdf4;color:#166534;font-size:13px;font-weight:800}
            .appleklinika-add-feedback.is-visible{display:block}
            .appleklinika-trust-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
            .appleklinika-trust-card{display:flex;align-items:flex-start;gap:12px;min-height:62px;border:0;border-bottom:1px solid #edf0f4;background:#fff;padding:16px 0}
            .appleklinika-trust-card:last-child{border-bottom:0;padding-bottom:0}
            .appleklinika-trust-card__icon{display:flex;flex:0 0 36px;align-items:center;justify-content:center;width:36px;height:36px;margin:0;border-radius:12px;background:#f5f7fa;color:#9f2336}
            .appleklinika-trust-card__icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
            .appleklinika-trust-card strong{display:block;color:#293243;font-size:12.5px;line-height:1.2;font-weight:850}
            .appleklinika-trust-card__copy{display:block;margin-top:4px;color:#667085;font-size:13px;line-height:1.5;font-weight:450}
            .appleklinika-product-assurance{padding:0}
            .appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
            .appleklinika-buy-panel > p:empty{display:none!important;margin:0!important}
            .appleklinika-product-column{display:grid;gap:24px;align-content:start;min-width:0}
            .appleklinika-product-below-hero{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:start}
            .appleklinika-below-panel{display:flex;flex-direction:column;gap:24px;min-width:0;padding:24px;border:1px solid #e6ebf2;border-radius:22px;background:#fff;box-shadow:0 6px 20px rgba(15,23,42,.025)}
            .appleklinika-below-panel__heading{display:grid;align-content:start;gap:5px}
            .appleklinika-below-panel__heading h2{margin:0;color:#111820;font-size:21px;line-height:1.25;font-weight:750}
            .appleklinika-below-panel__heading p{margin:0;color:#667085;font-size:13px;line-height:1.5;font-weight:450}
            .appleklinika-below-panel .appleklinika-section-kicker{font-size:11px}
            .appleklinika-below-panel .appleklinika-config-group{gap:7px}
            .appleklinika-below-panel .appleklinika-config-grid,.appleklinika-below-panel .appleklinika-config-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,max(var(--selector-min-width,92px),calc((100% - (var(--selector-columns) - 1)*8px)/var(--selector-columns)))),1fr));gap:8px;max-width:none}
            .appleklinika-below-panel [data-selector-group=condition]{--selector-min-width:180px}
            .appleklinika-below-panel [data-selector-group=storage]{--selector-min-width:104px}
            .appleklinika-below-panel .appleklinika-config-card{min-height:72px;padding:12px 28px 12px 12px;border-radius:14px;box-shadow:none}
            .appleklinika-below-panel .appleklinika-config-card__label{display:block;overflow:visible;min-height:auto;font-size:13px;line-height:1.35;font-weight:650;-webkit-line-clamp:unset}
            .appleklinika-below-panel .appleklinika-config-card__meta{font-size:12px;line-height:1.4;white-space:normal;overflow:visible;text-overflow:clip}
            .appleklinika-below-panel .appleklinika-config-card:disabled{opacity:1;color:#7b8491;background:#f7f8fa;border-style:dashed}
            .appleklinika-below-panel .appleklinika-config-card:disabled .appleklinika-config-card__label{color:#7b8491}
            .appleklinika-below-panel .appleklinika-config-card--color{min-height:76px;padding:9px 7px 8px}
            .appleklinika-below-panel .appleklinika-color-swatch{width:34px;height:34px}
            .appleklinika-below-panel .appleklinika-config-card--color .appleklinika-config-card__label{max-height:none;font-size:11.5px;line-height:1.18;overflow:visible}
            .appleklinika-below-panel .appleklinika-config-card--color.is-selected:after,.appleklinika-below-panel .appleklinika-config-card--color.is-active:after{right:calc(50% - 25px);width:15px;height:15px;font-size:9px}
            .appleklinika-below-panel .appleklinika-config-info{grid-template-columns:1fr;gap:8px}
            .appleklinika-below-panel [data-selector-group=storage] .appleklinika-config-card{width:100%;min-width:0;max-width:100%;height:60px;min-height:60px;padding:9px 12px;align-items:center}
            .appleklinika-below-panel [data-selector-group=storage] .appleklinika-config-card__content{height:auto;gap:3px}
            .appleklinika-below-panel [data-selector-group=storage] .appleklinika-config-card__label{padding-right:20px;white-space:nowrap}
            .appleklinika-below-panel [data-selector-group=storage] .appleklinika-config-card__meta{margin-top:0;white-space:nowrap}
            .appleklinika-below-panel--support .appleklinika-trust-grid{grid-template-columns:1fr;gap:0}
            .appleklinika-below-panel--support .appleklinika-trust-card strong{font-size:14px;line-height:1.45;font-weight:650}
            .appleklinika-product-facts{display:grid;gap:26px}
            .appleklinika-fact-group h3{margin:0 0 16px;color:#253040;font-size:15px;line-height:1.5;font-weight:650}
            .appleklinika-fact-group dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 28px;margin:0}
            .appleklinika-fact-group dl>div{display:grid;align-content:start;gap:4px;font-size:14px;line-height:1.55;min-width:0}
            .appleklinika-fact-group dt{color:#667085;font-size:13px}
            .appleklinika-fact-group dd{margin:0;color:#253040;font-weight:500;overflow-wrap:anywhere}
            .appleklinika-product-reference summary{width:fit-content;padding:10px 0;cursor:pointer;color:#667085;font-size:13px;line-height:1.8}
            .appleklinika-product-reference dl{display:block}
            .appleklinika-fact-group .appleklinika-sku-row dd{font-size:13px;font-weight:400;color:#667085}
            #appleklinika-product-specs{scroll-margin-top:160px}
            .appleklinika-product-shell [hidden]{display:none!important}
            .appleklinika-spec-disclosure{margin-top:20px}
            .appleklinika-spec-disclosure summary{width:fit-content;padding:12px 0;cursor:pointer;color:#9f2336;font-size:14px;line-height:1.5;font-weight:600}
            @media(max-width:640px){.appleklinika-below-panel{padding:20px;gap:22px}.appleklinika-fact-group dl{gap:18px 20px}}
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
            .appleklinika-config-card--color .appleklinika-config-card__label{display:block;min-height:auto;max-height:none;text-align:center;white-space:normal;overflow:visible;-webkit-line-clamp:initial;-webkit-box-orient:initial}
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
            .ak-single-product__lower.ak-single-product__details.appleklinika-product-content-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;min-width:0}
            .ak-single-product__details-main.appleklinika-product-main-info{display:grid;gap:24px;min-width:0}
            .ak-single-product__details .appleklinika-product-panel,.appleklinika-product-column--purchase > .appleklinika-related-panel{padding:24px;border:1px solid #e5eaf2;border-radius:22px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.055)}
            .ak-single-product__details .appleklinika-product-panel h2,.appleklinika-product-column--purchase > .appleklinika-related-panel h2{margin:0 0 14px;color:#111820;font-size:24px;line-height:1.18;font-weight:880;letter-spacing:0}
            .ak-single-product__description p{margin:0 0 12px;color:#4b5563;font-size:15px;line-height:1.7;font-weight:450}
            .ak-single-product__description p:last-child{margin-bottom:0}
            .ak-single-product__description ul,.ak-single-product__description ol{margin:12px 0 0;padding-left:20px;color:#4b5563;font-size:15px;line-height:1.65}
            .appleklinika-technical{margin-top:24px;padding-top:24px;border-top:1px solid #e6ebf2}
            .appleklinika-technical h3{margin:0 0 4px;font-size:18px;font-weight:700}
            .appleklinika-technical__intro{margin:0 0 20px;color:#667085;font-size:13px;line-height:1.5}
            .appleklinika-spec-highlights,.appleklinika-technical-group dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 28px;margin:0}
            .appleklinika-spec-row{min-width:0}
            .appleklinika-spec-row dt{margin:0 0 5px;color:#667085;font-size:13px;line-height:1.5}
            .appleklinika-spec-row dd{margin:0;color:#253040;font-size:14px;line-height:1.6;font-weight:450;overflow-wrap:anywhere}
            .appleklinika-spec-table{display:grid;gap:28px;margin-top:16px}
            .appleklinika-technical-group h4{margin:0 0 18px;padding-bottom:10px;border-bottom:1px solid #edf0f4;color:#253040;font-size:15px;line-height:1.5;font-weight:650}
            .appleklinika-spec-source{margin:0;color:#667085;font-size:13px;line-height:1.6}
            .appleklinika-spec-source a{color:inherit;text-underline-offset:3px}
            @media(max-width:640px){.appleklinika-spec-highlights,.appleklinika-technical-group dl{grid-template-columns:1fr;gap:18px}}
            .ak-single-product__related.appleklinika-related-panel{position:static;padding:22px;border-radius:22px}
            .ak-single-product__related .appleklinika-related-list{display:grid;gap:10px}
            .ak-single-product__related-card.appleklinika-related-card{display:grid;grid-template-columns:60px minmax(0,1fr);gap:14px;align-items:center;padding:14px 0;border:0;border-bottom:1px solid #edf1f6;border-radius:0;background:transparent;text-decoration:none;color:#111820;box-shadow:none;transition:background .16s ease}
            .ak-single-product__related-card.appleklinika-related-card:last-child{border-bottom:0}
            .ak-single-product__related-card.appleklinika-related-card:hover{background:#fffafa}
            .ak-single-product__related-thumb{display:flex;align-items:center;justify-content:center;width:60px;height:76px;border:0;border-radius:0;background:transparent;overflow:hidden}
            .ak-single-product__related-thumb img{display:block;width:100%;height:100%;object-fit:contain;border-radius:0;background:transparent}
            .ak-single-product__related-body{display:grid;gap:6px;min-width:0}
            .ak-single-product__related-title{display:-webkit-box;overflow:hidden;color:#18202b;font-size:14px;line-height:1.4;font-weight:650;-webkit-line-clamp:3;-webkit-box-orient:vertical}
            .ak-single-product__related-price{display:flex;flex-wrap:wrap;gap:4px 8px;align-items:baseline;color:#111820;font-size:13px;line-height:1.2;font-weight:850}
            .ak-single-product__related-price del{color:#8a94a6;font-size:12px;font-weight:750;text-decoration-thickness:1px}
            .ak-single-product__related-price ins{color:#111820;font-size:13.5px;font-weight:900;text-decoration:none}
            .ak-single-product__related-price .amount{color:inherit;font-size:inherit;font-weight:inherit;white-space:nowrap}
            .ak-single-product__related-saving{display:inline-flex;width:max-content;max-width:100%;padding:3px 7px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:11.5px;line-height:1.3;font-weight:650}
            @media (max-width:1100px){.appleklinika-product-column{display:contents}.appleklinika-product-gallery{order:0}.appleklinika-buy-panel{order:1}.appleklinika-product-below-hero{order:2}.appleklinika-below-panel--support{order:3}.appleklinika-product-content-grid{order:4}.appleklinika-related-panel{order:5}}
            @media (max-width:1100px){body.single-product main{padding:24px 18px 56px}.appleklinika-product-hero,.appleklinika-product-below-hero,.appleklinika-product-content-grid,.ak-single-product__lower.ak-single-product__details.appleklinika-product-content-grid{grid-template-columns:minmax(0,1fr)}.appleklinika-buy-panel,.appleklinika-related-panel{position:static}.appleklinika-product-assurance .appleklinika-trust-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-grid,.appleklinika-config-row{grid-template-columns:repeat(2,minmax(0,1fr))}.appleklinika-config-info{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media (max-width:640px){body.single-product main{padding:16px 12px 42px}.appleklinika-product-shell{gap:26px}.appleklinika-product-hero{gap:22px}.appleklinika-buy-panel,.appleklinika-product-data,.appleklinika-product-panel{padding:20px;border-radius:22px}.appleklinika-buy-panel h1{font-size:27px!important}.appleklinika-price-stack__current{font-size:34px}.appleklinika-cart-area form.cart{grid-template-columns:1fr}.appleklinika-config-grid,.appleklinika-config-row,.appleklinika-config-info,.appleklinika-trust-grid{grid-template-columns:1fr}.appleklinika-spec-row{grid-template-columns:1fr;gap:4px}}
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

                // Product image interactions are owned by assets/product-gallery.js.

                // The purchase area is replaced on product selection; its stable owner
                // handles both the initial Woo form and later server-rendered forms.
                document.querySelector(".appleklinika-product-shell")?.addEventListener("submit", function (event) {
                    const form = event.target.closest(".appleklinika-cart-area form.cart");
                    if (!form) return;
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

                const selectorDataNode = document.getElementById("appleklinika-product-selector-data");
                const selectorProducts = selectorDataNode ? JSON.parse(selectorDataNode.textContent || "[]") : [];
                let activeProduct = selectorProducts.find(product => Number(product.id) === Number(document.querySelector(".appleklinika-product-shell")?.dataset.currentProductId)) || null;

                function renderProductGallery(images) {
                    window.AppleklinikaProductGallery?.replace(images);
                }

                function updateProductView(product) {
                    if (!product) return;
                    document.querySelector(".appleklinika-product-shell").dataset.currentProductId = String(product.id);

                    const title = document.querySelector("[data-appleklinika-product-title]");
                    const price = document.querySelector("[data-appleklinika-product-price]");
                    const stock = document.querySelector("[data-appleklinika-stock-badge]");
                    const trust = document.querySelector("[data-appleklinika-trust]");

                    if (title) title.textContent = product.title;
                    if (price && product.priceHtml) price.outerHTML = product.priceHtml;
                    if (stock) {
                        stock.className = product.stockClass;
                        stock.textContent = product.stockLabel;
                        stock.hidden = !product.stockLabel;
                    }
                    if (trust && product.trustHtml) trust.outerHTML = product.trustHtml;
                    // Update presentation fragments alongside the existing selected product.
                    [
                        ["[data-appleklinika-product-lead]", "leadHtml"],
                        [".ak-single-product__description", "descriptionHtml"],
                        ["[data-appleklinika-product-facts]", "factsHtml"],
                        [".appleklinika-cart-area", "purchaseHtml"]
                    ].forEach(function (entry) {
                        const node = document.querySelector(entry[0]);
                        if (node && product[entry[1]]) node.outerHTML = product[entry[1]];
                        else if (node && entry[1] === "descriptionHtml") {
                            node.replaceChildren(); node.hidden = true;
                        }
                    });
                    const batteryMeta = document.querySelector("[data-selector-group=battery] [data-option-value=standard] .appleklinika-config-card__meta");
                    if (batteryMeta) batteryMeta.textContent = product.batteryHealthLabel;
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

                window.AppleklinikaSmartSelector?.mount({
                    products: selectorProducts,
                    initialId: document.querySelector(".appleklinika-product-shell")?.dataset.currentProductId,
                    onSelect: function (product) { activeProduct = product; updateProductView(product); }
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
        \Appleklinika\Inventory\Infrastructure\WordPress\WooProductMetadata::prime($relatedProducts);

        echo '<section class="appleklinika-product-shell" data-appleklinika-product-shell data-current-product-id="' . esc_attr((string) $productId) . '" aria-label="Termék és vásárlási lehetőségek">';
        echo '<div class="appleklinika-product-hero">';
        echo '<div class="appleklinika-product-column appleklinika-product-column--configuration">';
        $this->renderProductGallery($images);
        $this->renderBelowHeroProductArea($product, $productId, $firstImage, $relatedProducts);
        $this->renderProductInformation($product, $productId);
        echo '</div>';
        echo '<div class="appleklinika-product-column appleklinika-product-column--purchase">';
        $this->renderBuyPanel($product, $productId);
        $this->renderProductAssurancePanel($productId);
        $this->renderSimilarProductsPanel($product, $relatedProducts);
        echo '</div></div>';
        echo '</section>';
        echo '<script type="application/json" id="appleklinika-product-selector-data">' . wp_json_encode($this->productSelectorPayload($relatedProducts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    }

    /**
     * @param array<int, array{url: string, full: string, thumb: string, width: int, height: int, alt: string, srcset: string, html: string}> $images
     */
    private function renderProductGallery(array $images): void
    {
        $first = $images[0];
        $data = array_map(static fn (array $image): array => array_diff_key($image, ['html' => true]), $images);
        echo '<div class="appleklinika-product-gallery" data-gallery-images="' . esc_attr((string) wp_json_encode($data)) . '">';
        echo '<a class="appleklinika-product-gallery__stage" data-gallery-open href="' . esc_url($first['full']) . '" aria-label="Termékkép megnyitása nagy méretben">';
        echo $this->imageHtml($first, 'data-appleklinika-stage-image');
        echo '<span class="appleklinika-product-gallery__hint" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4M7.5 10.5h6m-3-3v6"/></svg>Kép nagyítása</span></a>';
        echo '<div class="appleklinika-product-gallery__thumbs" aria-label="Termékképek"' . (count($images) < 2 ? ' hidden' : '') . '>';
        foreach ($images as $index => $image) {
            echo '<button class="appleklinika-product-gallery__thumb" type="button" data-gallery-index="' . $index . '" aria-pressed="' . ($index === 0 ? 'true' : 'false') . '" aria-label="' . esc_attr(sprintf('Termékkép %d', $index + 1)) . '">';
            echo '<img src="' . esc_url($image['thumb']) . '" width="72" height="72" loading="lazy" decoding="async" alt=""></button>';
        }
        echo '</div></div>';
    }

    private function renderBuyPanel(\WC_Product $product, int $productId): void
    {
        echo '<aside class="appleklinika-buy-panel ak-single-product__buybox" aria-label="Vásárlási panel">';
        $stockLabel = $this->stockLabel($product);
        echo '<span class="' . esc_attr($this->stockBadgeClass($product)) . '" data-appleklinika-stock-badge' . ($stockLabel === '' ? ' hidden' : '') . '>' . esc_html($stockLabel) . '</span>';
        echo '<h1 data-appleklinika-product-title>' . esc_html($this->displayProductTitle($product)) . '</h1>';
        $this->renderProductLead($product);

        $this->renderPrice($product);
        $this->renderPurchaseArea($product);
        echo '</aside>';
    }

    private function renderPurchaseArea(\WC_Product $selectedProduct): void
    {
        // Woo's canonical type-specific template owns purchasability and quantity limits.
        // It reads the global product; restore that context after rendering each fragment.
        $previousProduct = $GLOBALS['product'] ?? null;
        echo '<div class="appleklinika-cart-area">';
        // Suppress only public in-stock copy while this Woo fragment is rendered.
        // Native stock, purchase limits and unavailable/backorder states stay intact.
        $stockHtml = static fn (string $html, \WC_Product $product): string =>
            $product->is_in_stock() && !$product->is_on_backorder(1) ? '' : $html;
        add_filter('woocommerce_get_stock_html', $stockHtml, 10, 2);
        try {
            $GLOBALS['product'] = $selectedProduct;
            woocommerce_template_single_add_to_cart();
        } finally {
            remove_filter('woocommerce_get_stock_html', $stockHtml, 10);
            $GLOBALS['product'] = $previousProduct;
        }
        echo '<div class="appleklinika-add-feedback" data-appleklinika-add-feedback>Kosárba téve. A kosár frissült.</div>';
        echo '</div>';
    }

    private function renderProductInformation(\WC_Product $product, int $productId): void
    {
        echo '<section class="ak-single-product__lower ak-single-product__details appleklinika-product-content-grid" aria-label="Termék részletei">';
        echo '<div class="ak-single-product__details-main appleklinika-product-main-info">';
        $this->renderProductDescriptionPanel($product);
        $this->renderProductSpecsPanel($product, $productId);
        echo '</div>';
        echo '</section>';
    }

    private function renderProductDescriptionPanel(\WC_Product $product): void
    {
        $description = $this->isLocalSelectorDemo($product)
            ? $this->localDemoDescription($product)
            : trim((string) $product->get_description());

        if ($description === '' && ! $this->isLocalSelectorDemo($product)) {
            $description = trim((string) $product->get_short_description());
        }

        echo '<section class="ak-single-product__description appleklinika-product-panel" aria-label="Termékleírás"' . ($description === '' ? ' hidden' : '') . '>';
        echo '<h2>Termékleírás</h2>';
        $content = (string) apply_filters('the_content', $description);
        if (preg_match('/\.hei[cf](?:[?"\s]|$)/i', $content)) {
            $content = (new \Appleklinika\Inventory\Infrastructure\WordPress\ProductDescriptionImages())->render($content);
        }
        echo wp_kses_post($content);
        echo '</section>';
    }

    private function renderProductSpecsPanel(\WC_Product $product, int $productId): void
    {
        $rows = $this->productSpecRows($product, $productId);
        $officialSpecs = $this->officialSpecsForProduct($product, $productId);
        $officialRows = $officialSpecs['rows'] ?? [];

        if ($rows === [] && $officialRows === []) {
            return;
        }

        echo '<section id="appleklinika-product-specs" class="ak-single-product__data appleklinika-product-panel" aria-label="Termékadatok">';
        echo '<h2>Termékadatok</h2>';

        if ($rows !== []) {
            $this->renderProductFactGroups($product, $productId);
        }

        if ($officialRows !== []) {
            $this->renderTechnicalSpecs($officialSpecs, $this->productOfficialSpecsModelKey($product, $productId));
        }

        echo '</section>';
    }

    /**
     * @param array<int, array{label: string, value: string}> $rows
     */
    private function renderSpecRows(array $rows): void
    {
        foreach ($rows as $row) {
            echo '<div class="appleklinika-spec-row"><dt>' . esc_html($row['label']) . '</dt><dd>' . esc_html($row['value']) . '</dd></div>';
        }
    }

    /** @param array{rows: array<int, array{label: string, value: string}>, source_url?: string} $specs */
    private function renderTechnicalSpecs(array $specs, string $modelKey): void
    {
        $rows = $this->technicalDisplayRows($specs['rows'], $modelKey);
        $highlightOrder = array_flip(['Kijelző', 'Chip', 'Hátlapi kamera', 'Vezeték nélküli kapcsolat']);
        $highlights = array_values(array_filter($rows, static fn (array $row): bool => isset($highlightOrder[$row['label']])));
        usort($highlights, static fn (array $a, array $b): int => $highlightOrder[$a['label']] <=> $highlightOrder[$b['label']]);
        echo '<section class="appleklinika-technical" data-product-technical><h3>Műszaki adatok</h3>';
        echo '<p class="appleklinika-technical__intro">A modell főbb jellemzői</p>';
        if ($highlights !== []) {
            echo '<dl class="appleklinika-spec-highlights">';
            $this->renderSpecRows($highlights);
            echo '</dl>';
        }
        echo '<details class="appleklinika-spec-disclosure appleklinika-spec-disclosure--all"><summary>Összes műszaki adat</summary><div class="appleklinika-spec-table">';
        $categories = [
            'Kijelző és teljesítmény' => ['Kijelző', 'Felbontás', 'Chip', 'Operációs rendszer'],
            'Kamerák' => ['Hátlapi kamera', 'Előlapi kamera'],
            'Kapcsolatok' => ['Vezeték nélküli kapcsolat', 'SIM'],
            'Akkumulátor és töltés' => ['Akkumulátor', 'Töltés / csatlakozó'],
            'Méret és kialakítás' => ['Méretek', 'Tömeg', 'Vízállóság', 'Szenzorok'],
            'Modellváltozatok' => ['Márka', 'Modell', 'Színek', 'Kapacitások'],
            'További jellemzők' => [],
        ];
        $groups = array_fill_keys(array_keys($categories), []);
        foreach ($rows as $row) {
            $group = 'További jellemzők';
            foreach ($categories as $heading => $labels) {
                if (in_array($row['label'], $labels, true)) { $group = $heading; break; }
            }
            $groups[$group][] = $row;
        }
        foreach ($groups as $heading => $groupRows) {
            if ($groupRows === []) continue;
            $order = array_flip($categories[$heading]);
            usort($groupRows, static fn (array $a, array $b): int => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));
            echo '<section class="appleklinika-technical-group"><h4>' . esc_html($heading) . '</h4><dl>';
            $this->renderSpecRows($groupRows);
            echo '</dl></section>';
        }
        $sourceUrl = trim((string) ($specs['source_url'] ?? ''));
        if ($sourceUrl !== '') {
            echo '<p class="appleklinika-spec-source"><a href="' . esc_url($sourceUrl) . '" target="_blank" rel="noopener noreferrer">A modell gyártói specifikációja</a></p>';
        }
        echo '</div></details></section>';
    }

    /** @param array<int, array{label: string, value: string}> $rows
     *  @return array<int, array{label: string, value: string}> */
    private function technicalDisplayRows(array $rows, string $modelKey): array
    {
        $result = [];
        foreach ($rows as $row) {
            if ($row['label'] === 'Színek') {
                $row['value'] = implode(', ', array_map(function (string $color) use ($modelKey): string {
                    $key = str_replace('-', '_', sanitize_title(trim($color)));
                    return array_key_exists($key, $this->colorDisplayLabelMap()) ? $this->colorDisplayLabel($modelKey, $key) : trim($color);
                }, explode(',', $row['value'])));
            } elseif ($row['label'] === 'Kapacitások') {
                $row['value'] = (string) preg_replace('/(?<=\d)(GB|TB)\b/', ' $1', $row['value']);
            } elseif ($row['label'] === 'SIM') {
                $row['value'] = str_replace(['Dual SIM:', 'Dual eSIM támogatás'], ['Két SIM:', 'Két eSIM támogatása'], $row['value']);
            } elseif ($row['label'] === 'Súly') {
                $row['label'] = 'Tömeg';
            }
            $result[$row['label'] . ':' . $row['value']] = $row;
        }
        return array_values($result);
    }

    /**
     * @return array{rows: array<int, array{label: string, value: string}>, source_url: string, source_title: string, fetched_at: string, model_key: string}
     */
    private function officialSpecsForProduct(\WC_Product $product, int $productId): array
    {
        return $this->officialSpecsForModel($this->productOfficialSpecsModelKey($product, $productId));
    }

    private function officialSpecsForModel(string $modelKey): array
    {
        return $this->specifications->resolve($modelKey);
    }

    private function productOfficialSpecsModelKey(\WC_Product $product, int $productId): string
    {
        // The chosen model always wins over a stale historical profile pointer.
        $modelKey = $this->conditionRepository->get($productId, 'device_model');
        if ($modelKey === '') $modelKey = (string)get_post_meta($productId, '_ak_official_specs_model_key', true);
        return ModelIdentity::canonical($modelKey);
    }

    private function canonicalOfficialSpecsModelKey(string $modelKey): string
    {
        return ModelIdentity::canonical($modelKey);
    }

    /** @return array<int, array{label: string, value: string}> */
    private function productSpecRows(\WC_Product $product, int $productId): array
    {
        $modelKey = $this->conditionRepository->get($productId, 'device_model');
        $rows = [
            ['label' => 'Modell', 'value' => $this->modelLabel($modelKey)],
            ['label' => 'Tárhely', 'value' => $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity'))],
            ['label' => 'Szín', 'value' => $this->colorDisplayLabel($modelKey, $this->conditionRepository->get($productId, 'color'))],
            ['label' => 'Állapot', 'value' => $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade'))],
            ['label' => 'Akkumulátor állapota', 'value' => ($battery = $this->conditionRepository->get($productId, 'battery_health')) !== '' ? $battery . '%' : ''],
            ['label' => 'Garancia', 'value' => $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'))],
            ['label' => 'SIM', 'value' => $this->simConfigLabel($this->conditionRepository->get($productId, 'sim_config'))],
            ['label' => 'Tartozékok', 'value' => $this->conditionRepository->get($productId, 'accessories')],
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
            echo '<span class="ak-single-product__related-title">' . esc_html($this->displayProductTitle($relatedProduct)) . '</span>';
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
        return (new WooSimilarDevicesQuery($this->conditionRepository, $this->deviceCatalogRepository))
            ->get($product, $relatedProducts);
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
        $color = $this->colorDisplayLabel(
            $modelKey,
            $this->conditionRepository->get($productId, 'color')
        );
        $storage = $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity'));
        $condition = $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade'));

        $model = $this->selectorCatalogModel($modelKey);
        $type = $model['type'] ?? $this->conditionRepository->get($productId, 'device_type');
        $compactClass = in_array($type, ['ipad', 'mac', 'macbook', 'watch', 'apple_watch'], true) ? ' appleklinika-product-below-hero--compact-options' : '';
        echo '<section class="ak-single-product__below-hero appleklinika-product-below-hero' . $compactClass . '" aria-label="Készülék opciók és termékinformációk">';

        echo '<div class="ak-single-product__options appleklinika-below-panel appleklinika-below-panel--device">';
        echo '<div class="appleklinika-below-panel__heading">';
        echo '<span class="appleklinika-section-kicker">Állapot és megjelenés</span>';
        echo '<h2>Készülék kiválasztása</h2>';
        echo '<p>Válassz állapotot és színt.</p>';
        echo '</div>';
        $this->renderConditionSelector($product, $relatedProducts, $currentPrice, $condition);
        $this->renderColorSelector($product, $relatedProducts, $modelKey, $color, $fallbackImage);
        echo '</div>';

        if (!in_array($type, ['watch', 'apple_watch'], true)) {
            echo '<div class="appleklinika-below-panel appleklinika-below-panel--technical">';
            echo '<div class="appleklinika-below-panel__heading">';
            echo '<span class="appleklinika-section-kicker">Tárhely és felszereltség</span>';
            echo '<h2>Mennyi helyre van szükséged?</h2>';
            echo '<p>Válassz az elérhető tárhelyek közül.</p>';
            echo '</div>';
            $this->renderStorageSelector($product, $relatedProducts, $currentPrice, $storage);
            echo '</div>';
        }

        echo '</section>';
    }

    private function renderProductAssurancePanel(int $productId): void
    {
        $infoPanel = $this->productInfoPanelContent($productId);

        echo '<div class="appleklinika-below-panel appleklinika-below-panel--support ak-single-product__info-panel">';
        echo '<div class="appleklinika-below-panel__heading">';
        echo '<span class="appleklinika-section-kicker">' . esc_html($infoPanel['kicker']) . '</span>';
        echo '<h2>' . esc_html($infoPanel['title']) . '</h2>';
        echo '<p>' . esc_html($infoPanel['intro']) . '</p>';
        echo '</div>';
        $this->renderTrustCards($productId);
        echo '</div>';
    }

    /**
     * @param array<int, \WC_Product> $relatedProducts
     * @param array{url: string, srcset: string, html: string} $fallbackImage
     */
    private function renderColorSelector(\WC_Product $currentProduct, array $relatedProducts, string $modelKey, string $selectedLabel, array $fallbackImage): void
    {
        $model = $this->selectorCatalogModel($modelKey);
        $labelModelKey = (string) ($model['key'] ?? $modelKey);
        $valid = (new ModelSelectorOptions())->resolve(
            $model,
            $this->officialSpecsForProduct($currentProduct, $currentProduct->get_id())['rows'],
            $this->deviceCatalogRepository->colorOptions()
        )['colors'];
        $labels = array_map(static fn (string $label): string => trim((string) preg_replace('/^(.+?)\s*\([^)]+\)\s*$/u', '$1', $label)), $valid);
        $selectedKey = $this->conditionRepository->get($currentProduct->get_id(), 'color');
        $selectedLabel = $labels[$selectedKey] ?? $this->colorDisplayLabel($labelModelKey, $selectedKey);
        $items = [];

        foreach ($relatedProducts as $product) {
            $colorKey = $this->conditionRepository->get($product->get_id(), 'color');

            if ($colorKey === '' || isset($items[$colorKey])) {
                continue;
            }

            $image = $this->productImages($product)[0] ?? $fallbackImage;
            $items[$colorKey] = [
                'value' => $colorKey,
                'label' => $labels[$colorKey] ?? $this->colorDisplayLabel($labelModelKey, $colorKey),
                'url' => $product->get_id() === $currentProduct->get_id() ? '' : get_permalink($product->get_id()),
                'selected' => $product->get_id() === $currentProduct->get_id(),
                'image' => $image,
                'swatch' => $this->productColorSwatch($colorKey, $this->colorLabel($labelModelKey, $colorKey)),
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

        foreach ($valid as $key => $label) {
            if (isset($items[$key])) continue;
            $items[$key] = [
                'value' => $key,
                'label' => $labels[$key],
                'swatch' => $this->productColorSwatch($key, $label),
                'available' => false,
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

        $modelKey = $this->conditionRepository->get($currentProduct->get_id(), 'device_model');
        $valid = (new ModelSelectorOptions())->resolve(
            $this->selectorCatalogModel($modelKey),
            $this->officialSpecsForProduct($currentProduct, $currentProduct->get_id())['rows']
        )['storage'];
        foreach ($valid as $key => $label) {
            if (!isset($items[$key])) $items[$key] = ['value' => $key, 'label' => $label, 'available' => false];
        }
        $displayOrder = array_flip(['64_gb', '128_gb', '256_gb', '512_gb', '1_tb', '2_tb', '4_tb', '8_tb']);
        uksort($items, static fn (string $a, string $b): int => ($displayOrder[$a] ?? 99) <=> ($displayOrder[$b] ?? 99));
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
                'image' => $this->productImages($product)[0] ?? null,
            ];
        }

        // Keep the configured scale visible even when a grade has no physical device.
        // Existing selector availability still resolves the selected color/storage combination.
        foreach (array_keys(Grade::options()) as $gradeKey) {
            if (isset($items[$gradeKey])) continue;
            $items[$gradeKey] = [
                'value' => $gradeKey,
                'label' => $this->gradeLabel($gradeKey),
                'available' => false,
            ];
        }

        $displayOrder = array_flip(array_keys(Grade::options()));
        uksort($items, static fn (string $a, string $b): int => ($displayOrder[$a] ?? 99) <=> ($displayOrder[$b] ?? 99));
        $this->renderSelectorGroup('condition', 'Esztétikai állapot', $selectedLabel, $items, 'appleklinika-config-grid', false);
    }

    private function renderProductFactGroups(\WC_Product $product, int $productId): void
    {
        $groups = ['Készülék' => [], 'Állapot és felszereltség' => [], 'További jellemzők' => [], 'Cikkszám' => []];
        $seen = [];
        foreach ($this->productSpecRows($product, $productId) as $row) {
            $key = $row['label'] . ':' . $row['value'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $group = in_array($row['label'], ['Modell', 'Tárhely', 'Szín', 'SIM'], true) ? 'Készülék'
                : (in_array($row['label'], ['Állapot', 'Akkumulátor állapota', 'Garancia', 'Tartozékok'], true) ? 'Állapot és felszereltség' : ($row['label'] === 'Cikkszám' ? 'Cikkszám' : 'További jellemzők'));
            if ($row['label'] === 'Állapot') $row['label'] = 'Esztétikai állapot';
            $groups[$group][] = $row;
        }
        echo '<div class="appleklinika-product-facts" data-appleklinika-product-facts>';
        foreach ($groups as $heading => $rows) {
            if ($rows === []) continue;
            $reference = $heading === 'Cikkszám';
            echo $reference ? '<details class="appleklinika-fact-group appleklinika-product-reference"><summary>Cikkszám megtekintése</summary><dl>'
                : '<section class="appleklinika-fact-group"><h3>' . esc_html($heading) . '</h3><dl>';
            foreach ($rows as $row) {
                echo '<div' . ($reference ? ' class="appleklinika-sku-row"' : '') . '><dt' . ($reference ? ' class="screen-reader-text"' : '') . '>' . esc_html($row['label']) . '</dt><dd>' . esc_html($row['value']) . '</dd></div>';
            }
            echo $reference ? '</dl></details>' : '</dl></section>';
        }
        echo '</div>';
    }

    private function isLocalSelectorDemo(\WC_Product $product): bool
    {
        return in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)
            && str_starts_with($product->get_sku(), 'ak-selector-demo-iphone-')
            && $this->conditionRepository->get($product->get_id(), 'device_type') === 'iphone';
    }

    private function displayProductTitle(\WC_Product $product): string
    {
        if (! $this->isLocalSelectorDemo($product)) return $product->get_name();
        $id = $product->get_id();
        $model = $this->conditionRepository->get($id, 'device_model');
        return implode(' · ', array_filter([
            $this->modelLabel($model),
            $this->storageLabel($this->conditionRepository->get($id, 'storage_capacity')),
            $this->colorDisplayLabel($model, $this->conditionRepository->get($id, 'color')),
            explode(' – ', $this->gradeLabel($this->conditionRepository->get($id, 'overall_grade')))[0],
        ])) ?: 'iPhone';
    }

    private function localDemoDescription(\WC_Product $product): string
    {
        $id = $product->get_id();
        $model = $this->conditionRepository->get($id, 'device_model');
        $storage = $this->storageLabel($this->conditionRepository->get($id, 'storage_capacity'));
        $color = $this->colorDisplayLabel($model, $this->conditionRepository->get($id, 'color'));
        $condition = $this->gradeLabel($this->conditionRepository->get($id, 'overall_grade'));
        $battery = $this->conditionRepository->get($id, 'battery_health');
        $sentences = [];
        $intro = $model !== '' ? $this->modelLabel($model) : 'Ez az iPhone';
        if ($storage !== '') $intro .= ', ' . $storage . ' tárhellyel';
        if ($color !== '') $intro .= ', ' . $color . ' színben';
        $sentences[] = $intro . '.';
        if ($condition !== '') $sentences[] = 'Esztétikai besorolása ' . $condition . '.';
        if ($battery !== '') $sentences[] = 'Akkumulátorának állapota ' . $battery . '%.';
        return esc_html(implode(' ', $sentences));
    }

    private function renderProductLead(\WC_Product $product): void
    {
        $id = $product->get_id();
        $lead = $this->isLocalSelectorDemo($product)
            ? implode(' · ', array_filter([$this->gradeLabel($this->conditionRepository->get($id, 'overall_grade')), $this->batteryText($id)]))
            : trim(wp_strip_all_tags((string) $product->get_short_description()));
        echo '<p class="appleklinika-product-lead" data-appleklinika-product-lead' . ($lead === '' ? ' hidden' : '') . '>' . esc_html(wp_trim_words($lead, 24)) . '</p>';
    }

    private function trustIcon(string $icon): string
    {
        $path = [
            '1' => 'M12 3 4 6v6c0 4 8 9 8 9s8-5 8-9V6Z M8 12l3 3 5-6',
            '✓' => 'M9 3h6l2 3h3v14H4V6h3Z M8 12l3 3 5-6',
            '↺' => 'M4 10a8 8 0 1 1 1 9 M4 4v6h6',
            '•' => 'M5 3h14v18H5Z M9 8h6 M9 12h6 M9 16h4',
        ][$icon] ?? '';
        return $path !== '' ? '<svg viewBox="0 0 24 24" focusable="false"><path d="' . $path . '"/></svg>' : esc_html($icon);
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
        echo '<h3 class="appleklinika-config-group__title">' . esc_html($title) . ($group === 'battery' ? ' <span class="screen-reader-text"' : ': <span') . ' data-selected-label data-appleklinika-selected-label>' . esc_html($selectedLabel) . '</span></h3>';
        $columns = $group === 'condition' ? (count($items) === 3 ? 3 : min(2, count($items))) : min(5, count($items));
        $gridStyle = '--selector-columns:' . $columns;
        // Four colors wrap as two balanced pairs on narrow screens, rather than 3 + 1.
        if ($group === 'color' && count($items) === 4) $gridStyle .= ';--selector-min-width:104px';
        echo '<div class="' . esc_attr($containerClass) . '" style="' . esc_attr($gridStyle) . '">';

        foreach ($items as $item) {
            $class = 'appleklinika-config-card' . ($withImage ? ' appleklinika-config-card--color' : '') . ($extraCardClass !== '' ? ' ' . $extraCardClass : '') . (! empty($item['selected']) ? ' is-selected' : '');
            $optionImage = isset($item['image']) && is_array($item['image']) ? (string) $item['image']['url'] : '';
            $optionValue = (string) ($item['value'] ?? sanitize_title((string) $item['label']));
            $extraPrice = (string) ($item['extra_price'] ?? 0);
            $disabled = isset($item['available']) && !$item['available'] ? ' disabled aria-disabled="true" tabindex="-1"' : '';
            echo '<button class="' . esc_attr($class) . '"' . $disabled . ' type="button" data-selector-option data-appleklinika-config-option data-option-value="' . esc_attr($optionValue) . '" data-option-label="' . esc_attr((string) $item['label']) . '" data-image-url="' . esc_url($optionImage) . '" data-option-image="' . esc_url($optionImage) . '" data-product-url="' . esc_url((string) ($item['url'] ?? '')) . '" data-extra-price="' . esc_attr($extraPrice) . '" aria-pressed="' . (! empty($item['selected']) ? 'true' : 'false') . '">';

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

        $canonical = ModelIdentity::canonical($model);
        $keys = [$model, $canonical];
        foreach ($this->deviceCatalogRepository->all() as $entry) {
            if (ModelIdentity::canonical((string) $entry['key']) === $canonical) $keys[] = $entry['key'];
        }
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'stock_status' => 'instock',
            'visibility' => 'visible',
            'orderby' => 'date',
            'order' => 'DESC',
            \Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceQueries::FAMILY => ['models' => array_values(array_unique($keys))],
        ]);
        $byId = [$product->get_id() => $product];

        foreach ($products as $relatedProduct) {
            if ($relatedProduct instanceof \WC_Product && ModelIdentity::canonical($this->conditionRepository->get($relatedProduct->get_id(), 'device_model')) === $canonical && \Appleklinika\Inventory\Infrastructure\WordPress\DeviceAvailability::active($relatedProduct)) {
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
        \Appleklinika\Inventory\Infrastructure\WordPress\WooSelectorConfiguration::prime($products);
        return array_values(array_map(function (\WC_Product $product): array {
            $productId = $product->get_id();
            $images = $this->productImages($product);

            return [
                'id' => $productId,
                'modelKey' => ModelIdentity::canonical($this->conditionRepository->get($productId, 'device_model')),
                'technical' => (new \Appleklinika\Inventory\Infrastructure\WordPress\WooSelectorConfiguration($this->conditionRepository))->forProduct($product),
                'labels' => [
                    'color' => $this->colorDisplayLabel($this->conditionRepository->get($productId, 'device_model'), $this->conditionRepository->get($productId, 'color')),
                    'storage' => $this->storageLabel($this->conditionRepository->get($productId, 'storage_capacity')),
                    'condition' => $this->gradeLabel($this->conditionRepository->get($productId, 'overall_grade')),
                ],
                'available' => $product->is_purchasable() && $product->is_in_stock(),
                'selectorEligible' => \Appleklinika\Inventory\Infrastructure\WordPress\DeviceAvailability::active($product),
                'url' => get_permalink($productId),
                'title' => $this->displayProductTitle($product),
                'leadHtml' => $this->capture(fn () => $this->renderProductLead($product)),
                'descriptionHtml' => $this->capture(fn () => $this->renderProductDescriptionPanel($product)),
                'factsHtml' => $this->capture(fn () => $this->renderProductFactGroups($product, $productId)),
                'batteryHealthLabel' => ($battery = $this->conditionRepository->get($productId, 'battery_health')) !== '' ? $battery . '%' : 'Jelenlegi akkumulátor',
                'salePrice' => $this->numericPrice($product),
                'regularPrice' => (float) ($product->get_regular_price() !== '' ? $product->get_regular_price() : $this->numericPrice($product)),
                'priceHtml' => $this->capture(function () use ($product): void {
                    $this->renderPrice($product);
                }),
                'stockLabel' => $this->stockLabel($product),
                'stockClass' => $this->stockBadgeClass($product),
                'purchaseHtml' => $this->capture(fn () => $this->renderPurchaseArea($product)),
                'trustHtml' => $this->capture(function () use ($productId): void {
                    $this->renderTrustCards($productId);
                }),
                'images' => array_map(static function (array $image): array {
                    return array_diff_key($image, ['html' => true]);
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
     * @return array<int, array{url: string, full: string, thumb: string, width: int, height: int, alt: string, srcset: string, html: string}>
     */
    private function productImages(\WC_Product $product): array
    {
        $imageIds = [];
        $singleProductImageId = $this->singleProductGalleryImageId($product);

        if ($singleProductImageId > 0) {
            $imageIds[] = $singleProductImageId;
        }

        $mainImageId = (int) $product->get_image_id();

        if ($singleProductImageId === 0 && $mainImageId > 0) {
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
                'full' => wc_placeholder_img_src('full'),
                'thumb' => wc_placeholder_img_src('thumbnail'),
                'alt' => $product->get_name(),
                'width' => 600, 'height' => 600,
                'srcset' => '',
                'html' => wc_placeholder_img('woocommerce_single'),
            ]];
        }

        return array_map(static function (int $imageId) use ($product): array {
            $full = wp_get_attachment_image_src($imageId, 'full');
            $alt = (string) get_post_meta($imageId, '_wp_attachment_image_alt', true) ?: $product->get_name();
            return [
                'full' => (string) ($full[0] ?? ''),
                'thumb' => (string) wp_get_attachment_image_url($imageId, 'medium'),
                'width' => (int) ($full[1] ?? 600),
                'height' => (int) ($full[2] ?? 600),
                'alt' => $alt,
                'url' => (string) wp_get_attachment_image_url($imageId, 'woocommerce_single'),
                'srcset' => (string) wp_get_attachment_image_srcset($imageId, 'woocommerce_single'),
                'html' => (string) wp_get_attachment_image($imageId, 'woocommerce_single', false, ['alt' => $alt, 'sizes' => '(max-width: 600px) 92vw, 500px']),
            ];
        }, $imageIds);
    }

    private function singleProductGalleryImageId(\WC_Product $product): int
    {
        $imageId = (int) get_post_meta($product->get_id(), '_ak_single_product_gallery_image_id', true);

        if ($imageId <= 0 || get_post_type($imageId) !== 'attachment') {
            return 0;
        }

        return $imageId;
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
        $color = $this->colorDisplayLabel(
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

    private function renderTrustCards(int $productId): void
    {
        echo '<div class="appleklinika-trust-grid" data-appleklinika-trust>';

        foreach ($this->productInfoPanelContent($productId)['items'] as $block) {
            echo '<div class="appleklinika-trust-card">';
            echo '<span class="appleklinika-trust-card__icon" aria-hidden="true">' . $this->trustIcon($block['icon']) . '</span>';
            echo '<div><strong>' . esc_html($block['title']) . '</strong><span class="appleklinika-trust-card__copy">' . esc_html($block['text']) . '</span></div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Central product-page trust copy. This can be replaced or extended later with an admin-backed content source.
     *
     * @return array{kicker: string, title: string, intro: string, items: array<int, array{icon: string, title: string, text: string}>}
     */
    private function productInfoPanelContent(int $productId): array
    {
        $warranty = $this->warrantyLabel($this->conditionRepository->get($productId, 'warranty_duration'));
        $items = [];

        if ($warranty !== '') {
            $items[] = [
                'icon' => '1',
                'title' => $warranty . ' garancia',
                'text' => 'Termékadatok alapján',
            ];
        }

        $items[] = [
            'icon' => '✓',
            'title' => 'Ellenőrzött készülék',
            'text' => 'Apple Klinika állapotadatokkal',
        ];
        $items[] = [
            'icon' => '↺',
            'title' => 'Visszaküldés',
            'text' => 'A visszaküldési tájékoztató szerint',
        ];
        $items[] = [
            'icon' => '•',
            'title' => 'Átlátható adatok',
            'text' => 'Valós termékadatok egy helyen',
        ];

        $content = [
            'kicker' => 'Biztonságos vásárlás',
            'title' => 'Apple Klinika garanciák',
            'intro' => 'Tudnivalók a vásárláshoz.',
            'items' => array_slice($items, 0, 4),
        ];

        $filtered = apply_filters('appleklinika_single_product_info_panel_content', $content, $productId);

        return is_array($filtered) ? array_merge($content, $filtered) : $content;
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
        if ($product->is_in_stock() && !$product->is_on_backorder(1)) {
            return '';
        }

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

    /** @return array<string,mixed> */
    private function selectorCatalogModel(string $modelKey): array
    {
        if ($modelKey === '') return [];
        $canonical = $this->canonicalOfficialSpecsModelKey($modelKey);
        foreach ($this->deviceCatalogRepository->all() as $model) {
            if ($this->canonicalOfficialSpecsModelKey($model['key']) === $canonical) return $model;
        }
        return [];
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

    private function colorDisplayLabel(string $model, string $color): string
    {
        if ($color === '') {
            return '';
        }

        $rawLabel = trim($this->colorLabel($model, $color));

        if ($rawLabel === '') {
            return $color;
        }

        if (preg_match('/^\(PRODUCT\)\s*RED$/i', $rawLabel) === 1) {
            return '(PRODUCT)RED';
        }

        if (preg_match('/^(.+?)\s*\([^)]+\)\s*$/u', $rawLabel, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        $labelMap = $this->colorDisplayLabelMap();
        $labelKey = str_replace('-', '_', sanitize_title($rawLabel));

        return $labelMap[$color] ?? $labelMap[$labelKey] ?? $rawLabel;
    }

    /**
     * @return array<string, string>
     */
    private function colorDisplayLabelMap(): array
    {
        return [
            'alpine_green' => 'Alpine Green',
            'black' => 'Black',
            'black_titanium' => 'Black Titanium',
            'blue' => 'Blue',
            'blue_titanium' => 'Blue Titanium',
            'cloud_white' => 'Cloud White',
            'coral' => 'Coral',
            'cosmic_orange' => 'Cosmic Orange',
            'deep_blue' => 'Deep Blue',
            'deep_purple' => 'Deep Purple',
            'desert_titanium' => 'Desert Titanium',
            'gold' => 'Gold',
            'gold_titanium' => 'Gold Titanium',
            'graphite' => 'Graphite',
            'green' => 'Green',
            'jet_black' => 'Jet Black',
            'lavender' => 'Lavender',
            'light_gold' => 'Light Gold',
            'midnight' => 'Midnight',
            'midnight_green' => 'Midnight Green',
            'mist_blue' => 'Mist Blue',
            'natural_titanium' => 'Natural Titanium',
            'pacific_blue' => 'Pacific Blue',
            'pink' => 'Pink',
            'product_red' => '(PRODUCT)RED',
            'purple' => 'Purple',
            'red' => 'Red',
            'rose_gold' => 'Rose Gold',
            'sage' => 'Sage',
            'sierra_blue' => 'Sierra Blue',
            'silver' => 'Silver',
            'sky_blue' => 'Sky Blue',
            'slate_titanium' => 'Slate Titanium',
            'soft_pink' => 'Soft Pink',
            'space_black' => 'Space Black',
            'space_gray' => 'Space Gray',
            'starlight' => 'Starlight',
            'teal' => 'Teal',
            'ultramarine' => 'Ultramarine',
            'white' => 'White',
            'white_titanium' => 'White Titanium',
            'yellow' => 'Yellow',
        ];
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
            'light_gold' => ['background' => '#f0d69a', 'border' => '#cfad68'],
            'sierra_blue' => ['background' => '#9aa9b8', 'border' => '#788898'],
            'midnight' => ['background' => '#1f2937', 'border' => '#111827'],
            'space_black' => ['background' => '#1b1b1f', 'border' => '#0d0d10'],
            'space_gray' => ['background' => '#62666f', 'border' => '#4b4f57'],
            'black' => ['background' => '#151515', 'border' => '#0b0b0b'],
            'white' => ['background' => '#f8fafc', 'border' => '#d7dee8'],
            'starlight' => ['background' => '#f3eadf', 'border' => '#d6c6b3'],
            'red' => ['background' => '#bf1d2d', 'border' => '#9f1522'],
            'product_red' => ['background' => '#bf1d2d', 'border' => '#9f1522'],
            'blue' => ['background' => '#4f6f9f', 'border' => '#39567f'],
            'sky_blue' => ['background' => '#9fc4df', 'border' => '#77a4c1'],
            'blue_titanium' => ['background' => '#4d5f73', 'border' => '#344558'],
            'ultramarine' => ['background' => '#556ec7', 'border' => '#3f52a1'],
            'mist_blue' => ['background' => '#b9c9d8', 'border' => '#8ea3b5'],
            'deep_blue' => ['background' => '#223858', 'border' => '#17263d'],
            'green' => ['background' => '#687d66', 'border' => '#516450'],
            'midnight_green' => ['background' => '#46584d', 'border' => '#334139'],
            'teal' => ['background' => '#79b4ac', 'border' => '#58938a'],
            'sage' => ['background' => '#9ba991', 'border' => '#748269'],
            'purple' => ['background' => '#b8a9d9', 'border' => '#9485b9'],
            'deep_purple' => ['background' => '#594f63', 'border' => '#3f3948'],
            'lavender' => ['background' => '#c9bee9', 'border' => '#a89bd1'],
            'pink' => ['background' => '#f3c4cf', 'border' => '#d99aaa'],
            'soft_pink' => ['background' => '#f7cdd7', 'border' => '#dfa9b7'],
            'rose_gold' => ['background' => '#e7b9a7', 'border' => '#c7937f'],
            'yellow' => ['background' => '#f4d76a', 'border' => '#d1b34a'],
            'coral' => ['background' => '#ff8a73', 'border' => '#d86855'],
            'cosmic_orange' => ['background' => '#d36b35', 'border' => '#a94f24'],
            'black_titanium' => ['background' => '#353638', 'border' => '#202123'],
            'white_titanium' => ['background' => '#e7e3dc', 'border' => '#c7c0b6'],
            'natural_titanium' => ['background' => '#b8b2a8', 'border' => '#91897f'],
            'desert_titanium' => ['background' => '#c9a27f', 'border' => '#a17c5d'],
            'slate_titanium' => ['background' => '#686b70', 'border' => '#4f5256'],
            'gold_titanium' => ['background' => '#d7bd8f', 'border' => '#ae956a'],
            'jet_black' => ['background' => '#050505', 'border' => '#000000'],
        ];
    }

    private function storageLabel(string $storage): string
    {
        return [
            '32_gb' => '32 GB',
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
            'dual_esim' => 'Két eSIM',
            'physical_esim' => 'Fizikai SIM + eSIM',
            'dual_physical' => 'Két fizikai SIM',
        ][$simConfig] ?? '';
    }

    private function gradeLabel(string $grade): string
    {
        return str_replace([' - ', 'makulatlan'], [' – ', 'makulátlan'], Grade::options()[$grade] ?? $grade);
    }
}
