<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;

// Standalone in-memory WooCommerce and provider doubles: no WordPress bootstrap or network.
define('HOUR_IN_SECONDS', 3600);
define('GLS_SHIPPING_ABSPATH', '/provider-files-must-not-be-loaded/');
$GLOBALS['products'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['transient_writes'] = 0;
$GLOBALS['environment'] = 'local';
$GLOBALS['site_home'] = 'http://localhost:18080/';
$GLOBALS['orders'] = [];
$GLOBALS['filters'] = [];
$GLOBALS['backoffice_capability'] = true;

function get_post_meta(int $id, string $key, bool $single): mixed { return $GLOBALS['products'][$id][$key] ?? ''; }
function get_current_user_id(): int { return 81; }
function get_userdata(int $id): object { return (object) ['display_name' => 'QA Employee', 'user_email' => 'qa@example.invalid']; }
function current_user_can(string $capability, mixed ...$arguments): bool { return $capability === 'manage_appleklinika_backoffice' && $GLOBALS['backoffice_capability']; }
function add_filter(string $hook, callable $callback, int $priority, int $acceptedArguments): void { $GLOBALS['filters'][$hook][] = $callback; }
function remove_filter(string $hook, callable $callback, int $priority): void { $GLOBALS['filters'][$hook] = array_values(array_filter($GLOBALS['filters'][$hook] ?? [], static fn ($registered): bool => $registered !== $callback)); }
function apply_filters(string $hook, array $data, array $context): array { foreach ($GLOBALS['filters'][$hook] ?? [] as $callback) { $data = $callback($data, $context); } return $data; }
function current_time(string $type): string|int { return match ($type) { 'mysql' => '2026-09-10 10:15:00', 'Y-m-d' => '2026-09-10', 'Ymd' => '20260910', 'timestamp' => strtotime('2026-09-10 10:15:00'), default => throw new RuntimeException('Unexpected date format.') }; }
function get_transient(string $key): mixed { return $GLOBALS['transients'][$key] ?? false; }
function set_transient(string $key, mixed $value, int $expiry): bool { ++$GLOBALS['transient_writes']; $GLOBALS['transients'][$key] = $value; return true; }
function wp_get_environment_type(): string { return $GLOBALS['environment']; }
function home_url(string $path): string { return $GLOBALS['site_home']; }
function wp_parse_url(string $url, int $component): mixed { return parse_url($url, $component); }
function wc_get_order(int $id): WC_Order|false { return $GLOBALS['orders'][$id] ?? false; }

class RepositoryMetaDouble
{
    public array $metadata = [];
    public function get_meta(string $key, bool $single = true, string $context = 'view'): mixed
    {
        $values = $this->metadata[$key] ?? [];
        return $single ? ($values[0] ?? '') : array_map(static fn ($value): object => (object) ['value' => $value], $values);
    }
    public function update_meta_data(string $key, mixed $value): void { $this->metadata[$key] = [$value]; }
}

class WC_Order_Item_Product extends RepositoryMetaDouble
{
    public function __construct(private string $name, private int $productId = 7) {}
    public function get_name(): string { return $this->name; }
    public function get_product_id(): int { return $this->productId; }
    public function get_variation_id(): int { return 0; }
    public function get_quantity(): int { return 1; }
    public function get_product(): never { throw new RuntimeException('Device display must not load the current product name.'); }
}

class WC_Order extends RepositoryMetaDouble
{
    public array $items = [];
    public array $notes = [];
    public bool $paid = true;
    public bool $noteSucceeds = true;
    public bool $noteThrows = false;
    public bool $probeForeignNotes = false;
    public array $noteAuthors = [];
    public array $foreignNoteProbes = [];
    public string $status = 'processing';
    public string $shippingMethod = 'gls_shipping_method';
    public int $saves = 0;
    public function __construct(private int $id = 1376) {}
    public function get_id(): int { return $this->id; }
    public function get_items(string $type): array { return $this->items; }
    public function get_status(): string { return $this->status; }
    public function is_paid(): bool { return $this->paid; }
    public function get_shipping_methods(): array
    {
        return [new class($this->shippingMethod) {
            public function __construct(private string $id) {}
            public function get_method_id(): string { return $this->id; }
        }];
    }
    public function add_order_note(string $content, bool $customer, bool $employee): int
    {
        if ($this->noteThrows) { throw new RuntimeException('Simulated note persistence failure.'); }
        if (! $this->noteSucceeds) { return 0; }
        $comment = ['comment_post_ID' => $this->id, 'comment_type' => 'order_note', 'comment_content' => $content, 'comment_author' => 'WooCommerce', 'comment_author_email' => 'woocommerce@example.invalid', 'user_id' => 0];
        $context = ['order_id' => $this->id, 'is_customer_note' => $customer];
        if ($this->probeForeignNotes) {
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', array_replace($comment, ['comment_post_ID' => 999]), array_replace($context, ['order_id' => 999]));
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', array_replace($comment, ['comment_content' => 'Unrelated note']), $context);
            $this->foreignNoteProbes[] = apply_filters('woocommerce_new_order_note_data', $comment, array_replace($context, ['is_customer_note' => true]));
        }
        $this->noteAuthors[] = apply_filters('woocommerce_new_order_note_data', $comment, $context);
        $this->notes[] = compact('content', 'customer', 'employee');
        return count($this->notes);
    }
    public function save(): int { ++$this->saves; return $this->id; }
}

class GLS_Shipping_For_Woo {}
class GLS_Shipping_Account_Helper
{
    public static array|false $account = ['client_id' => 'fixture-client', 'username' => 'fixture-user', 'password' => 'fixture-secret', 'mode' => 'sandbox'];
    public static function get_active_account(): array|false { return self::$account; }
    public static function get_account_setting(string $key): string { return 'fixture-phone'; }
}
class GLS_Shipping_Sender_Address_Helper
{
    public static array $sender = ['Name' => 'Fixture Store', 'Street' => 'Fixture Street 1', 'City' => 'Fixture City', 'ZipCode' => '1000', 'CountryIsoCode' => 'HU', 'ContactPhone' => 'fixture-phone'];
    public static function get_default_sender_address(): array { return self::$sender; }
    public static function format_for_api_pickup(array $address, string $phone): array { return $address; }
}

function load_repository_provider_doubles(): void
{
    class GLS_Shipping_API_Data {}
    class GLS_Shipping_API_Service {}
    class GLS_Shipping_Order
    {
        public static int $calls = 0;
        public function generate_single_order_label(int $id): array
        {
            ++self::$calls;
            wc_get_order($id)->update_meta_data('_gls_print_label', 'recorded-fixture-label.pdf');
            return ['success' => true];
        }
    }
}

final class RepositoryOperationsTest
{
    private int $assertions = 0;
    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) { throw new RuntimeException($message); }
    }
    private function rejects(callable $operation, string $message): void
    {
        try { $operation(); } catch (InvalidArgumentException) { $this->assert(true, $message); return; }
        $this->assert(false, $message);
    }
    public function run(): void
    {
        $repository = new WooOrderBackOfficeRepository();
        $order = new WC_Order();
        $item = new WC_Order_Item_Product('Ordered device name');
        $order->items = [$item];
        $GLOBALS['products'][7] = ['_appleklinika_internal_identifier' => 'CURRENT_OTHER_DEVICE', '_appleklinika_battery_health' => '88'];
        $item->metadata[OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = ['ORDERED_IMEI', 'ORDERED_SERIAL', 'ORDERED_IMEI'];
        $order->metadata[OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = ['ORDER_SNAPSHOT'];
        $device = $repository->deviceItems($order)[0];
        $identifier = 'Belső azonosító / IMEI';
        $this->assert($device['name'] === 'Ordered device name', 'Purchased line name remains immutable even if the live product changes.');
        $this->assert($device['details'][$identifier] === 'ORDERED_IMEI / ORDERED_SERIAL', 'Line snapshots override order/live identifiers and preserve exact underscores without duplicate values.');
        $this->assert($device['detail_sources'][$identifier] === 'order', 'Immutable device identifiers declare their order-time source.');
        $this->assert($device['details']['Akkumulátor állapota'] === '88%' && $device['detail_sources']['Akkumulátor állapota'] === 'product', 'Remaining live metadata is explicitly identified as current product data.');
        $item->metadata = ['_appleklinika_battery_health' => ['97'], '_appleklinika_internal_identifier' => ['LEGACY_ITEM_ID']];
        $device = $repository->deviceItems($order)[0];
        $this->assert($device['details'][$identifier] === 'LEGACY_ITEM_ID' && $device['details']['Akkumulátor állapota'] === '97%' && $device['detail_sources']['Akkumulátor állapota'] === 'order', 'Existing item metadata takes precedence over live product data.');
        $item->metadata = ['_appleklinika_serial_number' => ['ITEM_SERIAL']];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ITEM_SERIAL', 'Legacy item serial numbers remain useful after product changes.');
        $item->metadata = [];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ORDER_SNAPSHOT', 'A single-line order can use its order-level device snapshot.');
        $order->items[] = new WC_Order_Item_Product('Second line', 8);
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'CURRENT_OTHER_DEVICE', 'An unassigned order snapshot is not incorrectly attributed to a line in a multi-item order.');
        $this->assert(! isset($repository->deviceItems($order)[1]['details'][$identifier]), 'Missing multi-item identifier data stays missing rather than showing another device.');
        $order->items = [new WC_Order_Item_Product('Deleted product snapshot', 0)];
        $this->assert($repository->deviceItems($order)[0]['details'][$identifier] === 'ORDER_SNAPSHOT', 'Order snapshots remain available after the original product is deleted.');

        $repository->transition($order, 'start', 81);
        $order->probeForeignNotes = true;
        $repository->addInternalNote($order, ' Confidential note text ');
        $history = $repository->history($order);
        $noteEvent = $history[1];
        $this->assert(count($history) === 2 && $history[0]['action'] === 'start', 'Adding a note preserves the existing workflow history.');
        $this->assert($noteEvent === ['order_id' => 1376, 'action' => 'note', 'from' => 'preparation', 'to' => 'preparation', 'user_id' => 81, 'user' => 'QA Employee', 'at' => '2026-09-10 10:15:00'], 'Note activity records employee identity, order, timestamp and unchanged workflow state.');
        $this->assert(! str_contains(json_encode($history), 'Confidential note text'), 'Activity history never duplicates the confidential note body.');
        $this->assert($order->notes[1] === ['content' => '[appleklinika-backoffice-manual] Confidential note text', 'customer' => false, 'employee' => true], 'The note itself remains an internal employee-authored WooCommerce note.');
        $this->assert(! current_user_can('edit_shop_orders') && $order->noteAuthors[1]['comment_author'] === 'QA Employee' && $order->noteAuthors[1]['comment_author_email'] === 'qa@example.invalid' && $order->noteAuthors[1]['user_id'] === 81, 'A user with only the Back Office capability remains the actual WooCommerce comment author and user ID.');
        $this->assert(count($order->foreignNoteProbes) === 3 && array_column($order->foreignNoteProbes, 'comment_author') === ['WooCommerce', 'WooCommerce', 'WooCommerce'], 'The author filter ignores other orders, unrelated note content and customer notes.');
        $this->assert(($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'The temporary author filter is removed immediately after a successful note save.');
        $this->assert(count($repository->todayActivity()) === 2 && $GLOBALS['transient_writes'] === 2, 'Each successful workflow/note action appends once to the existing daily activity storage.');
        $this->assert(FulfilmentWorkflow::employeeDailyCounts($repository->todayActivity())[0]['count'] === 2, 'Daily employee counts include both state changes and internal notes.');
        $this->assert($repository->state($order) === FulfilmentWorkflow::PREPARATION, 'An internal note does not change fulfilment progress.');
        $this->rejects(fn () => $repository->addInternalNote($order, ' '), 'Blank notes are rejected.');
        $order->noteSucceeds = false;
        $this->rejects(fn () => $repository->addInternalNote($order, 'Not saved'), 'A failed WooCommerce note save is not reported as successful.');
        $this->assert(($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'A failed note insert still removes the temporary author filter.');
        $order->noteThrows = true;
        try {
            $repository->addInternalNote($order, 'Throwing save');
            $this->assert(false, 'A throwing note insert must propagate its failure.');
        } catch (RuntimeException $error) {
            $this->assert($error->getMessage() === 'Simulated note persistence failure.' && ($GLOBALS['filters']['woocommerce_new_order_note_data'] ?? []) === [], 'A throwing insert removes the temporary author filter through finally.');
        }
        $order->noteThrows = false;
        $GLOBALS['backoffice_capability'] = false;
        $this->rejects(fn () => $repository->addInternalNote($order, 'Unauthorized note'), 'The actor override never grants access to a user without the Back Office capability.');
        $GLOBALS['backoffice_capability'] = true;
        $this->assert(count($repository->history($order)) === 2 && $GLOBALS['transient_writes'] === 2, 'Rejected/failed notes do not create activity.');
        $GLOBALS['transients']['appleklinika_backoffice_activity_20260910'][] = ['at' => '2026-09-09 23:59:59'];
        $this->assert(count($repository->todayActivity()) === 2, 'Today activity excludes entries from another day.');

        $this->assert(! class_exists('GLS_Shipping_Order') && $repository->glsReadinessMessage() === null, 'Configured frontend GLS readiness does not depend on the admin-only operation class.');
        GLS_Shipping_Account_Helper::$account['password'] = '';
        $this->assert($repository->glsReadinessMessage() !== null, 'Missing real provider credentials disable GLS generation.');
        GLS_Shipping_Account_Helper::$account['password'] = 'fixture-secret';
        GLS_Shipping_Account_Helper::$account['mode'] = 'production';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'Sandbox'), 'Local operation rejects production GLS mode.');
        $GLOBALS['environment'] = 'production';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'Sandbox'), 'A localhost clone remains protected even if WordPress reports production.');
        $GLOBALS['environment'] = 'staging';
        $GLOBALS['site_home'] = 'https://staging.example.invalid/';
        $this->assert(str_contains((string) $repository->glsReadinessMessage(), 'Sandbox'), 'Non-production environments require sandbox independently of hostname.');
        GLS_Shipping_Account_Helper::$account['mode'] = 'sandbox';
        GLS_Shipping_Sender_Address_Helper::$sender['Street'] = '';
        $this->assert($repository->glsReadinessMessage() !== null, 'An incomplete provider-resolved sender address disables generation.');
        GLS_Shipping_Sender_Address_Helper::$sender['Street'] = 'Fixture Street 1';

        // Declared only now: earlier frontend-readiness checks cannot see the admin class.
        load_repository_provider_doubles();
        $order = new WC_Order();
        $GLOBALS['orders'][1376] = $order;
        $this->rejects(fn () => $repository->createGlsLabel($order), 'An invalid fulfilment transition is rejected before calling GLS.');
        $order->update_meta_data(FulfilmentWorkflow::META_KEY, FulfilmentWorkflow::READY_FOR_SHIPPING);
        $order->paid = false;
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Unpaid orders cannot trigger the provider.');
        $order->paid = true;
        $order->shippingMethod = 'local_pickup';
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Pickup orders cannot trigger GLS generation.');
        $order->shippingMethod = 'gls_shipping_method';
        $order->status = 'cancelled';
        $this->rejects(fn () => $repository->createGlsLabel($order), 'Cancelled orders cannot trigger the provider.');
        $order->status = 'processing';
        $this->assert(GLS_Shipping_Order::$calls === 0, 'All ineligible actions were rejected before any provider call.');
        $repository->createGlsLabel($order);
        $this->assert(GLS_Shipping_Order::$calls === 1 && $repository->hasGlsLabel($order), 'An eligible action reuses the installed provider entry point and verifies recorded label metadata.');
        $this->rejects(fn () => $repository->createGlsLabel($order), 'A recorded label prevents duplicate generation even if its file is later unavailable.');
        $this->assert(GLS_Shipping_Order::$calls === 1, 'Duplicate prevention takes effect before the provider call.');

        echo "Back Office repository operations passed: {$this->assertions} assertions.\n";
    }
}

(new RepositoryOperationsTest())->run();
