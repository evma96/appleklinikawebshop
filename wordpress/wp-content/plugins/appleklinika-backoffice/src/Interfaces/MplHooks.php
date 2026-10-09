<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;
use Appleklinika\BackOffice\Domain\MplParcelRules;
use Appleklinika\BackOffice\Infrastructure\{MplCarrier,MplPackage,MplHomeShipping};

/** Thin Woo/plugin adapters; carrier rules and transport stay outside hooks. */
final class MplHooks
{
    public function register(): void
    {
        add_filter('render_block_data', [$this,'checkoutBlock']);
        add_action('wp_enqueue_scripts', [$this,'checkoutStyles'], 100);
        add_filter('pre_http_request', [$this,'providerGuard'], 90, 3);
        add_filter('vp_woo_pont_import_database_providers', static fn() => ['postapont']);
        add_filter('vp_woo_pont_shipping_cost_based_on_gross_total', '__return_true');
        add_filter('woocommerce_shipping_methods', static function($methods) { $methods['ak_mpl_home']=MplHomeShipping::class; return $methods; });
        add_filter('vp_woo_pont_provider_costs', [$this,'pointCosts'], 20);
        add_filter('woocommerce_package_rates', [$this,'rates'], 30, 2);
        add_action('woocommerce_checkout_validate_order_before_payment', [$this,'validate'], 30, 2);
        add_filter('vp_woo_pont_get_carrier_from_order', static fn($provider,$order) => MplCarrier::matches($order) ? 'posta' : $provider, 10, 2);
        // The existing three-email lifecycle remains the only customer lifecycle sender.
        foreach (['shipped','pickup','delivery'] as $event) {
            add_filter('woocommerce_email_enabled_vp_woo_pont_order_'.$event, static fn($enabled,$order) => $order instanceof \WC_Order && MplCarrier::matches($order) ? false : $enabled, 10, 2);
        }
    }
    public function checkoutStyles(): void
    {
        if (!is_checkout()) { return; }
        // Vendor assumes only its own pickup method when no new-style locations exist.
        // Keep the already configured legacy in-store pickup visible beside MPL.
        wp_add_inline_style('vp-woo-pont-picker-block', '#pickup-options .wc-block-components-local-pickup-rates-control{display:block!important} #shipping-method .wc-block-checkout__shipping-method-option-price{font-size:inherit!important} #shipping-method .wc-block-checkout__shipping-method-option-price:after{content:none!important}');
    }
    /** Mount the maintained vendor block in its supported native pickup parent. */
    public function checkoutBlock(array $block): array
    {
        if (($block['blockName'] ?? '') !== 'woocommerce/checkout-pickup-options-block') { return $block; }
        foreach ($block['innerBlocks'] ?? [] as $child) {
            if (($child['blockName'] ?? '') === 'vp-woo-pont/pont-picker-block') { return $block; }
        }
        $children = parse_blocks('<!-- wp:vp-woo-pont/pont-picker-block --><div class="wp-block-vp-woo-pont-pont-picker-block"></div><!-- /wp:vp-woo-pont/pont-picker-block -->');
        $block['innerBlocks'][] = $children[0];
        $content = $block['innerContent'] ?? [];
        if (!$content) { $content = ['<div class="wp-block-woocommerce-checkout-pickup-options-block">','</div>']; }
        // The stock block is empty; keep its wrapper and insert one React child.
        if (count($content) === 1 && is_string($content[0])) {
            $end = strrpos($content[0], '</div>');
            if ($end === false) { return $block; }
            $content = [substr($content[0],0,$end),null,substr($content[0],$end)];
        } else { array_splice($content, max(0,count($content)-1), 0, [null]); }
        $block['innerContent'] = $content;
        return $block;
    }
    public function providerGuard($result, array $args, string $url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['core.api.posta.hu','sandbox.api.posta.hu'], true)) { return $result; }
        if ($host !== 'sandbox.api.posta.hu' || get_option('appleklinika_mpl_api_enabled','no') !== 'yes') {
            return new \WP_Error('ak_mpl_provider_disabled','Az MPL szolgáltatói művelet ebben a TEST környezetben nincs engedélyezve.');
        }
        return $result;
    }
    public function pointCosts(array $costs): array
    {
        $parcel = MplPackage::fromContents(WC()->cart ? WC()->cart->get_cart() : []);
        foreach (MplCarrier::POINTS as $service) {
            if (!MplParcelRules::eligible($service,$parcel['kg'],$parcel['dimensions'],$parcel['value'])) { unset($costs[$service]); }
        }
        return $costs;
    }
    public function rates(array $rates, array $package): array
    {
        $point = WC()->session ? WC()->session->get('selected_vp_pont') : null;
        $parcel = MplPackage::fromContents($package['contents'] ?? []);
        $available = array_filter(MplCarrier::POINTS, static fn($s) => MplParcelRules::eligible($s,$parcel['kg'],$parcel['dimensions'],$parcel['value']));
        foreach ($rates as $id=>$rate) {
            if ($rate->get_method_id() !== 'vp_pont') { continue; }
            if (($package['destination']['country'] ?? '') !== 'HU' || !$available || ($point && !in_array($point['provider'] ?? '',$available,true))) { unset($rates[$id]); continue; }
            // Vendor returns zero until a point is selected if several providers are enabled.
            $costs = \VP_Woo_Pont_Helpers::calculate_shipping_costs();
            if (!$point && $costs) { $min = array_reduce($costs, static fn($a,$b) => $a === null || $b['net'] < $a['net'] ? $b : $a); $rate->set_cost($min['net']); $rate->set_taxes($min['tax'] ?? []); }
        }
        return $rates;
    }
    public function validate(\WC_Order $order, \WP_Error $errors): void
    {
        if (!MplCarrier::matches($order)) { return; }
        $parcel=MplPackage::fromContents(WC()->cart->get_cart());
        $service = in_array($order->get_meta('_vp_woo_pont_provider', true), MplCarrier::POINTS, true) ? $order->get_meta('_vp_woo_pont_provider', true) : 'home';
        if (!MplParcelRules::eligible($service,$parcel['kg'],$parcel['dimensions'],$parcel['value'])) {
            $errors->add('ak_mpl_package','A csomag súlya vagy mérete ehhez az MPL szolgáltatáshoz nem megfelelő. Válassz másik szállítási módot.');
        }
    }
}
