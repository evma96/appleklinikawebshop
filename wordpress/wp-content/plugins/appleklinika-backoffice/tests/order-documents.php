<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Standalone provider stubs: no WordPress bootstrap, credentials, or network.
final class WC_Order
{
    public function __construct(public array $meta = [])
    {
    }

    public function get_meta(string $key, bool $single = true): mixed
    {
        return $this->meta[$key] ?? '';
    }
}

final class GLS_Shipping_For_Woo
{
}

final class GLS_Shipping_Account_Helper
{
    public static string $country = 'HU';

    public static function get_account_setting(string $key): string
    {
        return self::$country;
    }

    public static function get_allowed_account_countries(): array
    {
        return ['CZ', 'HR', 'HU', 'RO', 'SI', 'SK', 'RS'];
    }
}

final class InvoiceProviderStub
{
    public string|false|null $pathOverride = null;
    public array $calls = [];

    public function generate_download_link(WC_Order $order, string $type, bool $absolute): string|false
    {
        $this->calls[] = [$type, $absolute];

        return $this->pathOverride ?? $GLOBALS['document_test_uploads'] . '/wc_szamlazz/' . $order->get_meta('_wc_szamlazz_invoice_pdf');
    }
}

function WC_Szamlazz(): InvoiceProviderStub
{
    return $GLOBALS['document_test_invoice_provider'];
}

function get_option(string $key, mixed $default = false): mixed
{
    return $key === 'active_plugins' ? $GLOBALS['document_test_active_plugins'] : $default;
}

function get_site_option(string $key, mixed $default = false): mixed
{
    return $key === 'active_sitewide_plugins' ? $GLOBALS['document_test_network_plugins'] : $default;
}

function wp_upload_dir(mixed $time = null, bool $create = true): array
{
    if ($create) {
        throw new RuntimeException('Reading documents must not create upload directories.');
    }

    return ['basedir' => $GLOBALS['document_test_uploads']];
}

require_once dirname(__DIR__) . '/src/Infrastructure/OrderDocuments.php';

use Appleklinika\BackOffice\Infrastructure\OrderDocuments;

$testDirectory = sys_get_temp_dir() . '/akbo-document-test-' . bin2hex(random_bytes(8));
$directories = [$testDirectory, $testDirectory . '/wc_szamlazz', $testDirectory . '/wc_szamlazz/2026', $testDirectory . '/wc_szamlazz/2026/09', $testDirectory . '/gls-labels', $testDirectory . '/outside', $testDirectory . '/wc_szamlazz/directory.pdf'];
$createdFiles = [];
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    foreach ($directories as $directory) {
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create isolated document test directory.');
        }
    }
    foreach (['/wc_szamlazz/2026/09/isolated-invoice.pdf', '/gls-labels/isolated-label.pdf', '/outside/outside.pdf', '/wc_szamlazz/not-a-pdf.txt'] as $relative) {
        $path = $testDirectory . $relative;
        // Only an isolated unit-test fixture, never attached to a runtime order.
        file_put_contents($path, "%PDF-1.4\n% ISOLATED TEST STUB - NOT AN INVOICE OR SHIPPING LABEL\n%%EOF\n");
        $createdFiles[] = $path;
    }

    define('GLS_LABELS_DIR', $testDirectory . '/gls-labels');
    $GLOBALS['document_test_uploads'] = $testDirectory;
    $GLOBALS['document_test_invoice_provider'] = new InvoiceProviderStub();
    $GLOBALS['document_test_active_plugins'] = [];
    $GLOBALS['document_test_network_plugins'] = [];
    $providers = ['integration-for-szamlazzhu-woocommerce/index.php', 'gls-shipping-for-woocommerce/gls-shipping-for-woocommerce.php'];
    $documents = new OrderDocuments();
    $order = new WC_Order([
        '_wc_szamlazz_invoice' => 'ISOLATED-TEST',
        '_wc_szamlazz_invoice_pdf' => '2026/09/isolated-invoice.pdf',
        '_gls_print_label' => 'isolated-label.pdf',
        '_gls_tracking_codes' => ['12345678901', '12345678901', 'TEST-NOT-A-PARCEL', ['invalid']],
    ]);

    $assert($documents->invoice($order) === ['provider_active' => false, 'recorded' => true, 'number' => 'ISOLATED-TEST', 'available' => false], 'An inactive invoice provider cannot expose even a present file.');
    $assert($documents->glsLabel($order) === ['provider_active' => false, 'recorded' => true, 'available' => false] && $documents->trackingLinks($order) === [], 'Inactive GLS cannot present working document or tracking actions.');
    $assert(WC_Szamlazz()->calls === [], 'Inactive invoice providers are never invoked.');

    $GLOBALS['document_test_active_plugins'] = $providers;
    $assert($documents->filePath($order, 'invoice') === realpath($testDirectory . '/wc_szamlazz/2026/09/isolated-invoice.pdf'), 'Existing invoice PDFs resolve through the provider including year/month folders.');
    $assert(WC_Szamlazz()->calls === [['invoice', true]], 'Invoice resolution reuses the real absolute-path provider contract.');
    $assert($documents->invoice($order)['available'] && $documents->glsLabel($order)['available'], 'Only existing readable provider PDFs are available.');
    $assert($documents->filePath($order, 'gls_label') === realpath($testDirectory . '/gls-labels/isolated-label.pdf'), 'GLS filename metadata resolves within its actual label directory.');
    $assert($documents->filePath($order, 'arbitrary-document') === null, 'Document types are explicitly allowlisted.');
    $assert($documents->trackingLinks($order) === [['code' => '12345678901', 'url' => 'https://gls-group.eu/HU/en/parcel-tracking/?match=12345678901']], 'Tracking uses the provider destination, deduplicates codes, and rejects invented nonnumeric parcel IDs.');
    GLS_Shipping_Account_Helper::$country = 'external.example';
    $assert($documents->trackingLinks($order) === [], 'A malformed provider country cannot enter a tracking URL.');
    GLS_Shipping_Account_Helper::$country = 'HU';
    $legacyTracking = new WC_Order(['_gls_tracking_code' => '98765432101']);
    $assert($documents->trackingLinks($legacyTracking)[0]['code'] === '98765432101', 'Real legacy tracking metadata remains supported.');

    $missing = new WC_Order(['_wc_szamlazz_invoice' => 'RECORDED-BUT-MISSING', '_wc_szamlazz_invoice_pdf' => 'missing.pdf', '_gls_print_label' => 'missing.pdf']);
    $assert($documents->invoice($missing)['recorded'] && ! $documents->invoice($missing)['available'] && ! $documents->glsLabel($missing)['available'], 'Metadata alone never makes a missing document printable.');
    $assert(! $documents->invoice(new WC_Order())['recorded'] && ! $documents->glsLabel(new WC_Order())['available'], 'Orders without document metadata have no download.');

    foreach (['https://example.invalid/invoice.pdf', 'file://' . $testDirectory . '/outside/outside.pdf', $testDirectory . '/wc_szamlazz/../outside/outside.pdf', $testDirectory . '/outside/outside.pdf', $testDirectory . '/wc_szamlazz/not-a-pdf.txt', $testDirectory . '/wc_szamlazz/directory.pdf', $testDirectory . "/wc_szamlazz/invalid\0.pdf"] as $invalidPath) {
        WC_Szamlazz()->pathOverride = $invalidPath;
        $assert($documents->filePath($order, 'invoice') === null, 'Remote, traversal, outside, non-PDF, directory and null-byte invoice paths are rejected.');
    }
    WC_Szamlazz()->pathOverride = null;
    foreach (['../outside/outside.pdf', 'https://example.invalid/label.pdf', '..\\outside\\outside.pdf'] as $invalidLabel) {
        $assert($documents->filePath(new WC_Order(['_gls_print_label' => $invalidLabel]), 'gls_label') === null, 'GLS filenames cannot be traversal paths or remote URLs.');
    }

    foreach (['/wc_szamlazz/escape.pdf', '/gls-labels/escape.pdf'] as $relative) {
        $link = $testDirectory . $relative;
        if (! symlink($testDirectory . '/outside/outside.pdf', $link)) {
            throw new RuntimeException('Cannot create symlink escape regression fixture.');
        }
        $createdFiles[] = $link;
    }
    $escape = new WC_Order(['_wc_szamlazz_invoice_pdf' => 'escape.pdf', '_gls_print_label' => 'escape.pdf']);
    $assert($documents->filePath($escape, 'invoice') === null && $documents->filePath($escape, 'gls_label') === null, 'Canonical containment rejects provider-directory symlink escapes.');

    $GLOBALS['document_test_active_plugins'] = [];
    $GLOBALS['document_test_network_plugins'] = array_fill_keys($providers, 1);
    $assert($documents->invoice($order)['available'] && $documents->glsLabel($order)['available'], 'Network-active providers are recognized without activating plugins.');

    echo "Back Office order documents passed: {$assertions} assertions.\n";
} finally {
    foreach (array_reverse($createdFiles) as $path) {
        unlink($path);
    }
    foreach (array_reverse($directories) as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
