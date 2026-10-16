<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Final checks at transports that bypass WordPress HTTP (Barion / Szamla Agent). */
final class ProductionTransportGuard
{
    public static function barion(string $url, string $key): void
    {
        if (!ProductionReadiness::production()) { return; }
        ProductionReadiness::assertAllowed('barion');
        $settings = (array)get_option('woocommerce_barion_settings', []);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'api.barion.com'
            || !hash_equals((string)($settings['poskey'] ?? ''),$key)) {
            throw new \RuntimeException('Production Barion request identity rejected.');
        }
    }
    public static function gls(string $url): void
    {
        if (!ProductionReadiness::production()) { return; }
        ProductionReadiness::assertAllowed('gls');
        $country = strtolower((string)\GLS_Shipping_Account_Helper::get_account_setting('country'));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($country,['hu','hr','cz','sk','si','ro','rs'],true)
            || strtolower((string)parse_url($url, PHP_URL_HOST)) !== 'api.mygls.' . $country) {
            throw new \RuntimeException('Production GLS request identity rejected.');
        }
    }

    public static function invoice(string $xml): void
    {
        if (!ProductionReadiness::production()) { return; }
        ProductionReadiness::assertAllowed('invoice');
        $previous = libxml_use_internal_errors(true);
        try { $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $key = $doc === false ? '' : (string)$doc->beallitasok->szamlaagentkulcs;
        if ($key === '' || !ProductionReadiness::pinned('szamlazz',[$key]) || str_contains($xml,'<!DOCTYPE')) {
            throw new \RuntimeException('Production Agent request identity rejected.');
        }
    }
}
