<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\ProductionReadiness;

/** Thin entry points; policy and readiness live outside hooks. */
final class ProductionHooks
{
    public function register(): void
    {
        if (!ProductionReadiness::production()) { return; }
        add_filter('pre_wp_mail', [$this,'mail'], PHP_INT_MAX);
        add_filter('pre_http_request', [$this,'http'], PHP_INT_MAX, 3);
        add_filter('woocommerce_available_payment_gateways', [$this,'gateways'], PHP_INT_MAX);
        add_action('woocommerce_checkout_process', [$this,'checkout'], -9999);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this,'checkout'], -9999);
        add_action('woocommerce_pre_payment_complete', [$this,'payment'], -9999);
        add_filter('woocommerce_can_reduce_order_stock', [$this,'stock'], PHP_INT_MAX);
        add_filter('wc_szamlazz_get_option', [$this,'providerOption'], PHP_INT_MAX, 2);
    }
    public function mail(mixed $previous): mixed
    {
        try { ProductionReadiness::assertAllowed('mail'); return $previous; }
        catch (\RuntimeException) { return false; }
    }
    public function http(mixed $previous, array $args, string $url): mixed
    {
        $host = strtolower((string)parse_url($url,PHP_URL_HOST));
        if (preg_match('/(^|\.)(mygls\.[a-z]+|posta\.hu|barion\.com|szamlazz\.hu)$/',$host)) {
            try {
                ProductionReadiness::assertAllowed('provider_http');
                if (preg_match('/test|sandbox|posta\.hu/',$host)) { throw new \RuntimeException('Manual MPL / production transport restriction.'); }
            } catch (\RuntimeException) { return new \WP_Error('appleklinika_production_blocked','Production provider request blocked; run the readiness command.'); }
        }
        return $previous;
    }
    public function gateways(array $gateways): array
    {
        // No third payment path can appear through a stale/imported Woo option.
        return ProductionReadiness::allowed() ? array_intersect_key($gateways,array_flip(['barion','cod'])) : [];
    }
    public function checkout(): void { ProductionReadiness::assertAllowed('checkout'); }
    public function payment(): void { ProductionReadiness::assertAllowed('payment'); }
    public function stock(bool $allowed): bool { return ProductionReadiness::allowed() && $allowed; }
    public function providerOption(mixed $value, string $key): mixed { return $key === 'debug' ? 'no' : $value; }
}
