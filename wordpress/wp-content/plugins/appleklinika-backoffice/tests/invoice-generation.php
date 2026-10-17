<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure {
    final class WooOrderBackOfficeRepository {
        public function recordDocumentAccess(\WC_Order $order, string $action): void {
            $GLOBALS['invoice_audit'][] = [$order->get_id(), $action, \get_current_user_id()];
        }
        public function fulfilmentBlockReason(\WC_Order $order): never { throw new \RuntimeException('Invoice must not consult fulfilment.'); }
    }
    final class LifecycleConfiguration {
        public static function manages(\WC_Order $order): bool { return $GLOBALS['invoice_managed']; }
    }
    final class SzamlazzAutomation {
        public function ready(): bool { return $GLOBALS['invoice_automation_ready']; }
    }
    final class WooOrderLifecycleStore {}
    final class WooCustomerMailer {}
}
namespace Appleklinika\BackOffice\Application {
    final class OrderLifecycle {
        public function __construct(...$dependencies) {}
        public function paymentConfirmed(int $id): void {
            ++$GLOBALS['invoice_lifecycle_calls'];
            $GLOBALS['invoice_order']->meta['_appleklinika_lifecycle_invoice'] = ['state' => 'generating'];
            \WC_Szamlazz()->generate_invoice($id, 'invoice');
        }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    spl_autoload_register(static function ($class) {
        $prefix = 'Appleklinika\\BackOffice\\';
        if (str_starts_with($class, $prefix)) { require_once dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
    });
    final class WC_Order {
        public array $meta = [];
        public bool $paid = true;
        public string $status = 'processing';
        public string $shipping = 'unknown';
        public function get_total(): string { return '1000'; }
        public function get_payment_method(): string { return 'barion'; }
        public function get_items(): array { return []; }
        public function get_id(): int { return 529; }
        public function get_meta($key, $single = true): mixed { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value): void { $this->meta[$key] = $value; }
        public function save_meta_data(): void {}
        public function is_paid(): bool { return $this->paid; }
        public function get_status(): string { return $this->status; }
        public function get_shipping_methods(): never { throw new RuntimeException('Invoice must not inspect shipping.'); }
    }
    final class Provider {
        public bool $autoDisabled = false;
        public function get_option($key, $default = false): mixed { return $default; }
        public function check_payment_method_options($method, $key): bool { return $this->autoDisabled; }
        public int $calls = 0;
        public string $key = 'offline-secret';
        public string $result = 'success';
        public function get_szamlazz_agent_key($order): string {
            return $_POST['account'] ?? $this->key;
        }
        public function generate_download_link($order, $type, $absolute): string { return $GLOBALS['invoice_root'].'/wc_szamlazz/'.$order->get_meta('_wc_szamlazz_invoice_pdf'); }
        public function generate_invoice($id, $type): array {
            ++$this->calls;
            if ($_POST !== [] || $type !== 'invoice' || ($GLOBALS['invoice_order']->meta['_appleklinika_lifecycle_invoice']['state'] ?? '') !== 'generating') {
                throw new RuntimeException('Unsafe provider invocation.');
            }
            $xml = (object) ['beallitasok' => (object) ['szamlaagentkulcs' => $this->result === 'wrong_account' ? 'unverified' : $this->key]];
            foreach ($GLOBALS['invoice_filters']['wc_szamlazz_xml'] ?? [] as $filter) { $xml = $filter($xml, $GLOBALS['invoice_order'], 'invoice'); }
            if ($this->result === 'error') { return ['error' => true, 'messages' => ['Hiányzó számlázási cím <b>hiba</b> ' . $this->key], 'xml' => 'NEVER EXPOSE XML']; }
            if ($this->result === 'exception') { throw new RuntimeException('NEVER EXPOSE EXCEPTION '.$this->key); }
            $GLOBALS['invoice_order']->meta['_wc_szamlazz_invoice'] = 'OFFLINE-529';
            $GLOBALS['invoice_order']->meta['_wc_szamlazz_invoice_pdf'] = $this->result === 'missing_pdf' ? 'absent.pdf' : 'fixture.pdf';
            return ['error' => false];
        }
    }
    function WC_Szamlazz(): Provider { return $GLOBALS['invoice_provider']; }
    function wc_get_order($id): mixed { return $id === 529 ? $GLOBALS['invoice_order'] : false; }
    function get_option($key, $default = false): mixed { return $GLOBALS['invoice_options'][$key] ?? $default; }
    function wp_get_environment_type(): string { return $GLOBALS['invoice_environment']; }
    function wp_upload_dir($time = null, $create = false): array { return ['basedir'=>$GLOBALS['invoice_root']]; }
    function get_current_user_id(): int { return $GLOBALS['invoice_user']; }
    function current_user_can($cap): bool { return $GLOBALS['invoice_capability']; }
    function current_time($format): string { return '2026-10-16 12:00:00'; }
    function status_header($status): void {}
    function add_filter($name, $callback, $priority, $count): void { $GLOBALS['invoice_filters'][$name][] = $callback; }
    function remove_filter($name, $callback, $priority): void { $GLOBALS['invoice_filters'][$name] = array_values(array_filter($GLOBALS['invoice_filters'][$name], static fn($item) => $item !== $callback)); }
    function is_user_logged_in(): bool { return get_current_user_id()>0; }
    function absint($value): int { return abs((int)$value); }
    function wp_unslash($value): mixed { return $value; }
    function sanitize_key($value): string { return preg_replace('/[^a-z0-9_]/', '', strtolower($value)); }
    function sanitize_text_field($value): string { return trim(strip_tags($value)); }
    function check_admin_referer($action): void { if (!$GLOBALS['invoice_nonce'] || $action !== 'appleklinika_backoffice_generate_invoice_529') { throw new RuntimeException('nonce rejected'); } }
    function auth_redirect(): never { throw new RuntimeException('auth rejected'); }
    function wp_die($message, $title, $options): never { throw new RuntimeException('access rejected'); }
    function home_url($path): string { return 'https://backoffice-teszt.appleklinika.com'.$path; }
    function add_query_arg($args, $url): string { return $url.'?'.implode('&', array_map(static fn($key,$value)=>$key.'='.$value, array_keys($args), $args)); }
    function wp_safe_redirect($url): void { echo json_encode(['url'=>$url,'calls'=>WC_Szamlazz()->calls,'audit'=>$GLOBALS['invoice_audit']]); }

    $invoice_root = sys_get_temp_dir().'/akbo-invoice-unit-'.bin2hex(random_bytes(6));
    mkdir($invoice_root.'/wc_szamlazz', 0700, true);
    file_put_contents($invoice_root.'/wc_szamlazz/fixture.pdf', "%PDF-1.4\nOFFLINE FIXTURE NOT AN INVOICE\n%%EOF");
    register_shutdown_function(static function () use ($invoice_root) {
        unlink($invoice_root.'/wc_szamlazz/fixture.pdf'); rmdir($invoice_root.'/wc_szamlazz'); rmdir($invoice_root);
    });
    $invoice_provider = new Provider();
    $invoice_order = new WC_Order();
    $invoice_environment = 'staging'; $invoice_managed = false; $invoice_automation_ready = true;
    $invoice_user = 42; $invoice_capability = true; $invoice_nonce = true; $invoice_audit = []; $invoice_filters = []; $invoice_lifecycle_calls = 0;
    $invoice_options = ['home'=>'https://teszt.appleklinika.com','active_plugins'=>['integration-for-szamlazzhu-woocommerce/index.php'],'appleklinika_szamlazz_test_agent_sha256'=>hash('sha256', $invoice_provider->key)];
    $_POST = [];
    $wpdb = new class {
        public string $prefix = 'offline_';
        public function prepare($sql, $name): string { return $sql; }
        public function get_var($sql): string { return '1'; }
    };
    $issuer = new \Appleklinika\BackOffice\Infrastructure\SzamlazzInvoice();
    $command = new \Appleklinika\BackOffice\Application\GenerateInvoice($issuer, new \Appleklinika\BackOffice\Infrastructure\WooOrderMutex());
    if (($argv[1] ?? '') === 'prg') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['order_id'=>529,'backoffice_action'=>'generate_invoice','confirm_recovery'=>'1','queue'=>'all','s'=>'Kovács','search_type'=>'customer','queue_page'=>3,'redirect_to'=>'https://evil.example/','account'=>'unverified','paid'=>'true'];
        if (($argv[2] ?? '') === 'blocked') { $invoice_order->meta['_wc_szamlazz_own'] = 'disabled'; }
        if (($argv[2] ?? '') === 'unconfirmed') { unset($_POST['confirm_recovery']); }
        (new \Appleklinika\BackOffice\Interfaces\BackOfficeRouter(new \Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository()))->handleAction();
    }
    $assertions = 0;
    $assert = static function ($condition, $message) use (&$assertions) { ++$assertions; if (!$condition) { throw new RuntimeException($message); } };
    $reject = static function (callable $operation, string $contains) use ($assert) {
        try { $operation(); $assert(false, 'Expected rejection: '.$contains); }
        catch (InvalidArgumentException $error) { $assert(str_contains($error->getMessage(), $contains), $error->getMessage()); }
    };
    foreach (['unknown','pickup','gls'] as $mode) {
        $invoice_order->shipping = $mode;
        $assert($issuer->generationBlock($invoice_order) === null, 'Shipping mode must not affect invoice eligibility.');
    }
    $invoice_order->paid = false;
    $assert($issuer->generationBlock($invoice_order) === null, 'Provider manual generation must not gain an invented paid prerequisite.');
    $assert($invoice_provider->calls === 0, 'Eligibility reads must not issue/preview invoices.');
    $_POST = ['account'=>'evil','paid'=>'false','doc_type'=>'electronic','note'=>'injected'];
    $command->execute(529);
    $assert($invoice_provider->calls === 1 && $invoice_order->get_meta('_wc_szamlazz_invoice') === 'OFFLINE-529', 'Unknown/unpaid legacy order uses the real provider contract once.');
    $assert($_POST['account'] === 'evil' && $invoice_filters['wc_szamlazz_xml'] === [], 'Provider overrides are isolated and globals/filters restored.');
    $assert($invoice_audit === [[529,'invoice_generation',42],[529,'invoice_generated',42]], 'Start/result audit contains real order and employee.');
    $reject(fn()=>$command->execute(529), 'Már van');
    $assert($invoice_provider->calls === 1, 'Duplicate POST never regenerates a recorded invoice.');
    foreach (['_wc_szamlazz_own'=>'tiltva','_wc_szamlazz_receipt'=>'nyugta','_wc_szamlazz_type_receipt'=>'nyugta'] as $meta=>$expected) {
        $invoice_order->meta = [$meta=>'yes'];
        $reject(fn()=>$command->execute(529), $expected);
    }
    foreach (['generating','failed','uncertain'] as $state) {
        $invoice_order->meta = ['_appleklinika_lifecycle_invoice'=>['state'=>$state]];
        $reject(fn()=>$command->execute(529), 'egyeztetni');
    }
    $invoice_order->meta = []; $invoice_managed = true;
    $reject(fn()=>$command->execute(529), 'fizetés');
    $invoice_order->paid = true; $invoice_automation_ready = false;
    $reject(fn()=>$command->execute(529), 'automatika');
    $invoice_automation_ready = true; $command->execute(529);
    $assert($invoice_lifecycle_calls === 1, 'Managed orders reuse the installed lifecycle instead of bypassing its guard.');
    $invoice_managed = false; $invoice_order->meta = [];
    foreach (['error','exception','wrong_account','missing_pdf'] as $result) {
        $invoice_order->meta = []; $invoice_provider->result = $result;
        $reject(fn()=>$command->execute(529), $result === 'missing_pdf' ? 'PDF' : 'egyeztet');
        $serialized = json_encode($invoice_order->meta);
        $assert(!str_contains($serialized, 'offline-secret') && !str_contains($serialized, 'NEVER EXPOSE'), 'No raw XML, exception, or credential is recorded.');
        $assert($invoice_order->meta['_appleklinika_lifecycle_invoice']['state'] === ($result === 'missing_pdf' ? 'document_missing' : 'uncertain'), 'Failed/uncertain outcome is durable; no automatic retry.');
    }
    $invoice_order->meta = []; $invoice_provider->result = 'success';
    foreach (['production','staging'] as $env) {
        $invoice_environment = $env; $invoice_options['appleklinika_szamlazz_test_agent_sha256'] = '';
        $reject(fn()=>$command->execute(529), 'TEST');
    }
    $invoice_options['appleklinika_szamlazz_test_agent_sha256'] = hash('sha256', $invoice_provider->key);
    $invoice_options['home'] = 'https://appleklinika.com';
    $reject(fn()=>$command->execute(529), 'TEST');
    $invoice_options['home'] = 'https://teszt.appleklinika.com';
    $invoice_capability = false;
    $reject(fn()=>$command->execute(529), 'jogosultság');
    $router = new \Appleklinika\BackOffice\Interfaces\BackOfficeRouter(new \Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository());
    $_POST = ['order_id'=>529,'backoffice_action'=>'generate_invoice'];
    try { $router->handleAction(); $assert(false, 'Capability must be required.'); }
    catch (RuntimeException $e) { $assert($e->getMessage() === 'access rejected', 'Router denies capability before generation.'); }
    $invoice_capability = true; $invoice_nonce = false;
    try { $router->handleAction(); $assert(false, 'Nonce must be required.'); }
    catch (RuntimeException $e) { $assert($e->getMessage() === 'nonce rejected', 'Router verifies the order/action nonce before generation.'); }
    foreach (['success','blocked','unconfirmed'] as $mode) {
        $output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' prg '.$mode);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        parse_str(parse_url($result['url'], PHP_URL_QUERY), $query);
        $assert($query['order']==529 && $query['queue']==='all' && $query['s']==='Kovács' && $query['search_type']==='customer' && $query['queue_page']==3, 'Successful and rejected invoice PRG preserve allowed context.');
        $assert(!str_contains($result['url'],'evil.example') && !isset($query['account']) && $result['calls']===($mode==='success'?1:0), 'PRG cannot accept a return URL or provider override, and rejected requests never call the provider.');
        $assert($query['notice_type']===($mode==='success'?'success':'error'), 'PRG reflects the actual invoice result.');
    }
    $invoice_order->meta = []; $invoice_order->paid = false; $invoice_managed = true;
    $assert($issuer->automationStatus($invoice_order)['state'] === 'waiting_payment', 'Managed unpaid order waits for the real payment trigger.');
    $invoice_order->paid = true;
    $assert($issuer->automationStatus($invoice_order)['state'] === 'waiting', 'Paid without result is observed, never silently generated on GET.');
    $invoice_managed = false;
    $assert($issuer->automationStatus($invoice_order)['state'] === 'unmanaged', 'Legacy orders are not falsely labelled automatic in progress.');
    $invoice_managed = true; $invoice_provider->autoDisabled = true;
    $assert($issuer->automationStatus($invoice_order)['state'] === 'disabled' && $issuer->generationBlock($invoice_order) !== null, 'Recovery cannot bypass the provider automatic opt-out on managed orders.');
    $invoice_provider->autoDisabled = false;
    $issuer->rememberFailure($invoice_order, ['agent_error_code'=>'57','agent_error'=>'Invalid billing <b>address</b> offline-secret','xml'=>'NEVER EXPOSE XML','password'=>'NEVER EXPOSE PASSWORD']);
    $status = $issuer->automationStatus($invoice_order);
    $assert($status['state'] === 'failed' && str_contains($status['reason'],'Invalid billing'), 'Actual provider failure is observable even without a lifecycle attempt.');
    $assert(!str_contains(json_encode($invoice_order->meta),'NEVER EXPOSE') && !str_contains(json_encode($status),'offline-secret') && !str_contains(json_encode($status),'<b>'), 'Error observer stores only sanitized diagnostics, no response bodies or secrets.');
    $assert(str_contains($issuer->generationBlock($invoice_order),'egyeztetni'), 'A previous provider error cannot be retried blindly.');
    echo "Back Office invoice independence passed: {$assertions} assertions.\n";
}
