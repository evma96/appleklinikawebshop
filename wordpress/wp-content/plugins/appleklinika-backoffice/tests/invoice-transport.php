<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// PHP 8 permits replacements of functions listed in disable_functions.
// Load the actual vendor class with cURL entry points disabled and replaced:
// no WordPress bootstrap, credentials, or external service calls.
if (!in_array('curl_exec', explode(',', (string) ini_get('disable_functions')), true)) {
    throw new RuntimeException('Run with the cURL functions disabled as specified in Makefile.');
}

define('ABSPATH', __DIR__ . '/');

final class InvoiceTransportProbe
{
    public array $options = [];
    public string $url = '';
    public int $executions = 0;
    public string|false $response = false;
    public string $error = '';
    public array $errors = [];

    public function log_debug_messages(mixed ...$args): void {}
    public function log_error_messages(array $response, string $context): void { $this->errors[] = $response; }
}

function WC_Szamlazz(): InvoiceTransportProbe { return $GLOBALS['transportProbe']; }
function wp_upload_dir(): array { return ['basedir' => $GLOBALS['transportDirectory']]; }
function get_option(string $key): string { return 'isolated-cookie'; }
function sanitize_title(mixed $value): string { return strtolower((string) $value); }
function __(string $message, string $domain): string { return $message; }

// Conditional declarations let the HTTP guard exit before any built-in
// function replacement; direct web requests must remain harmless HTTP 404.
if (!function_exists('curl_init')) {
    function curl_init(string $url): InvoiceTransportProbe
    {
        $probe = WC_Szamlazz();
        $probe->url = $url;
        $probe->options = [];
        return $probe;
    }
    function curl_setopt(InvoiceTransportProbe $probe, int $option, mixed $value): bool
    {
        $probe->options[$option] = $value;
        return true;
    }
    function curl_exec(InvoiceTransportProbe $probe): string|false
    {
        ++$probe->executions;
        return $probe->response;
    }
    function curl_error(InvoiceTransportProbe $probe): string { return $probe->error; }
    function curl_getinfo(InvoiceTransportProbe $probe, int $option): int
    {
        if ($probe->response === false) { return 0; }
        return $option === CURLINFO_HTTP_CODE ? 200 : strpos($probe->response, "\r\n\r\n") + 4;
    }
    function curl_close(InvoiceTransportProbe $probe): void {}
}

require dirname(__DIR__, 2) . '/integration-for-szamlazzhu-woocommerce/includes/class-xml-generator.php';

$GLOBALS['transportProbe'] = new InvoiceTransportProbe();
$GLOBALS['transportDirectory'] = sys_get_temp_dir() . '/akbo-transport-' . bin2hex(random_bytes(8));
$root = $GLOBALS['transportDirectory'];
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) { throw new RuntimeException($message); }
};

try {
    mkdir($root, 0700);
    mkdir($root . '/wc_szamlazz', 0700);
    $transport = new WC_Szamlazz_Xml_Generator();
    $probe = WC_Szamlazz();
    // No real account or order. This XML never leaves the isolated test.
    $xml = '<xmlszamla><beallitasok><szamlaagentkulcs>isolated-test-key</szamlaagentkulcs></beallitasok></xmlszamla>';

    foreach (['SSL certificate problem: unable to get local issuer certificate', 'SSL: no alternative certificate subject name matches target host name', ''] as $error) {
        $probe->error = $error;
        $probe->response = false;
        $before = $probe->executions;
        $result = $transport->generate($xml, 1, 'action-xmlagentxmlfile');
        $assert($probe->options[CURLOPT_SSL_VERIFYPEER] === true && $probe->options[CURLOPT_SSL_VERIFYHOST] === 2, 'Both certificate chain and hostname verification must be enabled on every attempt.');
        $assert($result['error'] === true && $result['http_error'] !== '' && $result['agent_body'] === '', 'Transport failure, including an empty cURL error, must fail closed.');
        $assert($probe->executions === $before + 1, 'Never retry a failed request with TLS verification disabled.');
        $assert(!file_exists($root . '/wc_szamlazz/1.xml'), 'Sensitive temporary XML must also be removed after TLS failure.');
    }

    $probe->error = '';
    $probe->response = "HTTP/1.1 200 OK\r\nszlahu_szamlaszam: ISOLATED-TEST-1\r\n\r\nISOLATED BODY - NOT A DOCUMENT";
    $result = $transport->generate($xml, 1, 'action-xmlagentxmlfile');
    $assert($result['error'] === false && $result['agent_body'] === 'ISOLATED BODY - NOT A DOCUMENT', 'Existing successful provider response parsing remains unchanged.');
    $assert($transport->get_invoice_id($result['header_array']) === 'ISOLATED-TEST-1', 'Existing provider document number parsing remains unchanged.');
    $assert($probe->url === 'https://www.szamlazz.hu/szamla/' && $probe->options[CURLOPT_POST] === true, 'The existing provider endpoint and request method are preserved.');
    $assert($probe->options[CURLOPT_POSTFIELDS]['action-xmlagentxmlfile'] instanceof CurlFile, 'The existing XML upload mechanism is reused.');
    $assert(!file_exists($root . '/wc_szamlazz/1.xml'), 'Successful requests also remove the temporary credential-bearing XML.');
    $assert(count($probe->errors) === 3, 'Transport failures retain the provider error reporting path.');
    echo "Invoice transport: {$assertions} assertions passed.\n";
} finally {
    foreach ([$root . '/wc_szamlazz/1.xml', $root . '/wc_szamlazz/szamlazz_cookie_isolated-cookie.txt'] as $path) {
        if (is_file($path)) { unlink($path); }
    }
    if (is_dir($root . '/wc_szamlazz')) { rmdir($root . '/wc_szamlazz'); }
    if (is_dir($root)) { rmdir($root); }
}
