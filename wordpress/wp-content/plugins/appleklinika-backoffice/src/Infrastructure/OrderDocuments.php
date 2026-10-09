<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use WC_Order;

/** Read-only access to documents owned by the installed provider plugins. */
final class OrderDocuments
{
    private const INVOICE_PLUGIN = 'integration-for-szamlazzhu-woocommerce/index.php';
    private const GLS_PLUGIN = 'gls-shipping-for-woocommerce/gls-shipping-for-woocommerce.php';

    /** Provider metadata, not a second invoice-status store. No generation or API call. */
    public function invoice(WC_Order $order): array
    {
        $number = $this->scalarMeta($order, '_wc_szamlazz_invoice');
        $recorded = $number !== '' || $this->scalarMeta($order, '_wc_szamlazz_invoice_pdf') !== '';
        $disabled = $this->scalarMeta($order, '_wc_szamlazz_own');
        $void = $this->scalarMeta($order, '_wc_szamlazz_void');
        $receipt = $this->scalarMeta($order, '_wc_szamlazz_receipt');
        $state = $recorded ? 'issued' : ($void !== '' ? 'voided' : ($receipt !== '' ? 'receipt' : ($disabled !== '' ? 'disabled' : 'missing')));

        return [
            'provider_active' => $this->invoiceActive(),
            'recorded' => $recorded,
            'number' => $number,
            'available' => $this->filePath($order, 'invoice') !== null,
            'state' => $state,
            'state_label' => match ($state) {
                'issued' => 'Számla rögzítve',
                'voided' => 'A korábbi számla sztornózva',
                'receipt' => 'Nyugta rögzítve, nem számla',
                'disabled' => 'Számlázás letiltva ennél a rendelésnél',
                default => 'Még nincs elkészült számla',
            },
            'manual' => (bool) $order->get_meta('_wc_szamlazz_invoice_manual', true),
            // This is the provider's paid marker, NOT invoice issue/completion date.
            'paid' => $recorded ? $this->scalarMeta($order, '_wc_szamlazz_completed') : '',
            'void_number' => $void,
            'receipt_number' => $receipt,
            'disabled_reason' => $disabled,
            'generation_block' => $this->invoiceGenerationBlock($order, $recorded, $disabled),
        ];
    }

    private function invoiceGenerationBlock(WC_Order $order, bool $recorded, string $disabled): string
    {
        if ($disabled !== '') {
            return 'A Számlázz.hu bővítményben a rendelés számlázása le van tiltva; ezt a Back Office nem kapcsolja vissza.';
        }
        if ($recorded) {
            return 'Már van rögzített számla; új példány kiállítása nem indítható.';
        }
        if (! $this->invoiceActive()) {
            return 'Kiállítás nem indítható: a Számlázz.hu bővítmény nem aktív.';
        }
        $provider = \WC_Szamlazz();
        if (! is_callable([$provider, 'generate_invoice']) || ! is_callable([$provider, 'get_szamlazz_agent_key'])) {
            return 'A telepített Számlázz.hu bővítmény szükséges kiállítási művelete nem érhető el.';
        }
        if (trim((string) $provider->get_szamlazz_agent_key($order)) === '') {
            return 'Kiállítás nem indítható: ehhez a rendeléshez nincs beállított Számla Agent-kulcs. Igazolt TEST-fiók szükséges.';
        }

        // A shared Agent endpoint and the presence of a key do not prove that
        // the selected account is a TEST account. Inspection never generates.
        return 'A Számlázz.hu közös szolgáltatói végpontot használ; az Agent-kulcs megléte nem igazolja a TEST-fiókot. A kulcshoz tartozó számlázási fiók TEST állapotát ellenőrizni kell; innen kiállítás nem indul.';
    }

    /** Read-only label and parcel evidence; parcel IDs are not tracking numbers. */
    public function glsLabel(WC_Order $order): array
    {
        return [
            'provider_active' => $this->glsActive(),
            'recorded' => $this->scalarMeta($order, '_gls_print_label') !== '',
            'available' => $this->filePath($order, 'gls_label') !== null,
            'tracking_codes' => $this->numericMetaList($order, '_gls_tracking_codes', '_gls_tracking_code'),
            'parcel_ids' => $this->numericMetaList($order, '_gls_parcel_ids'),
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

        if ($document === 'mpl_label' && MplCarrier::matches($order)) {
            $file = $this->scalarMeta($order, '_vp_woo_pont_parcel_pdf');
            $base = wp_upload_dir(null, false)['basedir'] . '/vp-woo-pont-labels';
            return $file !== '' ? $this->localPdf($base . '/' . $file, $base) : null;
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
        if (MplCarrier::matches($order)) { return MplCarrier::tracking($order); }
        if (! $this->glsActive() || ! class_exists('GLS_Shipping_Account_Helper')) {
            return [];
        }

        if (method_exists('GLS_Shipping_Account_Helper', 'get_order_tracking_links')) {
            return \GLS_Shipping_Account_Helper::get_order_tracking_links($order);
        }

        $country = strtoupper((string) \GLS_Shipping_Account_Helper::get_account_setting('country'));
        if (! in_array($country, \GLS_Shipping_Account_Helper::get_allowed_account_countries(), true)) {
            return [];
        }

        $links = [];
        foreach ($this->numericMetaList($order, '_gls_tracking_codes', '_gls_tracking_code') as $code) {
            // Same public tracking destination used by GLS_Shipping_Order.
            $links[$code] = [
                'code' => $code,
                'url' => 'https://gls-group.eu/' . $country . '/en/parcel-tracking/?match=' . rawurlencode($code),
            ];
        }

        return array_values($links);
    }

    /** @return list<string> */
    private function numericMetaList(WC_Order $order, string $key, string $legacyKey = ''): array
    {
        $values = $order->get_meta($key, true);
        $codes = [];
        foreach (is_array($values) ? $values : [] as $value) {
            if (is_scalar($value) && ctype_digit(trim((string) $value))) {
                $codes[] = trim((string) $value);
            }
        }
        $legacy = $legacyKey !== '' ? $this->scalarMeta($order, $legacyKey) : '';
        if ($codes === [] && $legacy !== '' && ctype_digit($legacy)) {
            $codes[] = $legacy;
        }
        return array_values(array_unique($codes));
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
