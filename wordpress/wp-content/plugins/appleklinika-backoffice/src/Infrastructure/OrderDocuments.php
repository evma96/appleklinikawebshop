<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use WC_Order;

/** Read-only access to documents owned by the installed provider plugins. */
final class OrderDocuments
{
    private const INVOICE_PLUGIN = 'integration-for-szamlazzhu-woocommerce/index.php';
    private const GLS_PLUGIN = 'gls-shipping-for-woocommerce/gls-shipping-for-woocommerce.php';

    /** @return array{provider_active:bool,recorded:bool,number:string,available:bool} */
    public function invoice(WC_Order $order): array
    {
        $number = $this->scalarMeta($order, '_wc_szamlazz_invoice');

        return [
            'provider_active' => $this->invoiceActive(),
            'recorded' => $number !== '' || $this->scalarMeta($order, '_wc_szamlazz_invoice_pdf') !== '',
            'number' => $number,
            'available' => $this->filePath($order, 'invoice') !== null,
        ];
    }

    /** @return array{provider_active:bool,recorded:bool,available:bool} */
    public function glsLabel(WC_Order $order): array
    {
        return [
            'provider_active' => $this->glsActive(),
            'recorded' => $this->scalarMeta($order, '_gls_print_label') !== '',
            'available' => $this->filePath($order, 'gls_label') !== null,
        ];
    }

    /** The caller must authorize the employee and verify its order-specific nonce. */
    public function filePath(WC_Order $order, string $document): ?string
    {
        if ($document === 'invoice' && $this->invoiceActive()) {
            if ($this->scalarMeta($order, '_wc_szamlazz_invoice_pdf') === '') {
                return null;
            }

            // The provider's resolver honors its stored year/month subdirectories.
            $provider = \WC_Szamlazz();
            if (! is_object($provider) || ! is_callable([$provider, 'generate_download_link'])) {
                return null;
            }
            $path = $provider->generate_download_link($order, 'invoice', true);
            $uploads = wp_upload_dir(null, false);
            $base = $uploads['basedir'] ?? null;

            return is_string($path) && is_string($base)
                ? $this->localPdf($path, rtrim($base, '/\\') . '/wc_szamlazz')
                : null;
        }

        if ($document === 'gls_label' && $this->glsActive() && defined('GLS_LABELS_DIR')) {
            $filename = $this->scalarMeta($order, '_gls_print_label');
            // Current GLS metadata stores a filename. Legacy URLs are not fetched.
            if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
                return null;
            }

            return $this->localPdf(rtrim((string) GLS_LABELS_DIR, '/\\') . '/' . $filename, (string) GLS_LABELS_DIR);
        }

        return null;
    }

    /** @return list<array{code:string,url:string}> */
    public function trackingLinks(WC_Order $order): array
    {
        if (! $this->glsActive() || ! class_exists('GLS_Shipping_Account_Helper')) {
            return [];
        }

        $country = strtoupper((string) \GLS_Shipping_Account_Helper::get_account_setting('country'));
        if (! in_array($country, \GLS_Shipping_Account_Helper::get_allowed_account_countries(), true)) {
            return [];
        }

        $codes = $order->get_meta('_gls_tracking_codes', true);
        if (! is_array($codes) || $codes === []) {
            $codes = [$this->scalarMeta($order, '_gls_tracking_code')];
        }

        $links = [];
        foreach ($codes as $code) {
            if (! is_scalar($code)) {
                continue;
            }
            $code = trim((string) $code);
            if ($code === '' || ! ctype_digit($code)) {
                continue;
            }
            // Same public tracking destination used by GLS_Shipping_Order.
            $links[$code] = [
                'code' => $code,
                'url' => 'https://gls-group.eu/' . $country . '/en/parcel-tracking/?match=' . rawurlencode($code),
            ];
        }

        return array_values($links);
    }

    private function invoiceActive(): bool
    {
        return $this->pluginActive(self::INVOICE_PLUGIN) && function_exists('WC_Szamlazz');
    }

    private function glsActive(): bool
    {
        return $this->pluginActive(self::GLS_PLUGIN) && class_exists('GLS_Shipping_For_Woo');
    }

    private function pluginActive(string $plugin): bool
    {
        $active = (array) get_option('active_plugins', []);
        $network = function_exists('get_site_option') ? (array) get_site_option('active_sitewide_plugins', []) : [];

        return in_array($plugin, $active, true) || array_key_exists($plugin, $network);
    }

    private function scalarMeta(WC_Order $order, string $key): string
    {
        $value = $order->get_meta($key, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function localPdf(string $path, string $directory): ?string
    {
        foreach ([$path, $directory] as $value) {
            if ($value === '' || str_contains($value, "\0") || str_contains($value, '://') || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $value)) {
                return null;
            }
        }

        $root = realpath($directory);
        $file = realpath($path);
        if ($root === false || $file === false || ! is_dir($root) || ! str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return null;
        }
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf' || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        return $file;
    }
}
