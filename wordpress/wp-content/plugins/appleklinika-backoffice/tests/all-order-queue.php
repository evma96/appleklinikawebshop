<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Isolated adapter contracts: no WordPress bootstrap, database or provider calls.
require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/PaymentState.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php';

final class AllQueueOrderUtil
{
    public static bool $hpos = true;
    public static function custom_orders_table_usage_is_enabled(): bool { return self::$hpos; }
}
class_alias(AllQueueOrderUtil::class, 'Automattic\\WooCommerce\\Utilities\\OrderUtil');
function get_post_stati(): array { return array_combine(['wc-pending', 'wc-completed', 'wc-cancelled', 'wc-checkout-draft', 'trash', 'auto-draft', 'wc-custom'], ['wc-pending', 'wc-completed', 'wc-cancelled', 'wc-checkout-draft', 'trash', 'auto-draft', 'wc-custom']); }
function wc_get_orders(array $arguments): object { $GLOBALS['all_queue_queries'][] = $arguments; return (object) ['orders' => [], 'total' => 0, 'max_num_pages' => 0]; }
final class WC_Order
{
    public function __construct(private string $status) {}
    public function get_status(): string { return $this->status; }
    public function get_meta(string $key, bool $single = true): mixed { return ''; }
}

$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    ++$count;
    if (! $condition) { throw new RuntimeException($message); }
};
$repository = new Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository();
foreach ([true, false] as $hpos) {
    AllQueueOrderUtil::$hpos = $hpos;
    $GLOBALS['all_queue_queries'] = [];
    $repository->queuePage('all', 3, '737', 'order');
    $args = $GLOBALS['all_queue_queries'][0];
    $assert($args['status'] === ($hpos ? ['all'] : array_values(get_post_stati())), 'All statuses use the correct native HPOS/CPT representation.');
    $assert($args['id'] === 737 && $args['page'] === 3 && $args['limit'] === 25 && ! isset($args['meta_query']) && ! isset($args['payment_method']), 'All-order adapter retains exact search and bounded paging without fulfilment/payment restrictions.');
    $repository->queuePage('', 1, '', '');
    $assert($GLOBALS['all_queue_queries'][1]['status'] === ['processing'] && $GLOBALS['all_queue_queries'][1]['payment_method'] === 'barion' && isset($GLOBALS['all_queue_queries'][1]['meta_query']), 'The default operational queue uses paid Barion and open fulfilment on both stores.');
    $GLOBALS['all_queue_queries'] = [];
    $repository->queueCounts();
    $assert(count($GLOBALS['all_queue_queries']) === 5, 'Dashboard retains five bounded count queries.');
    foreach ($GLOBALS['all_queue_queries'] as $args) {
        $assert($args['status'] === ['processing'] && $args['payment_method'] === 'barion' && $args['limit'] === 1 && $args['return'] === 'ids', 'Each dashboard count shares the operational admission rule without loading order objects.');
    }
}
foreach (['checkout-draft', 'auto-draft', 'trash', 'cancelled', 'failed', 'refunded', 'completed', 'custom'] as $status) {
    $assert($repository->fulfilmentBlockReason(new WC_Order($status)) !== null, 'Discovering a non-operational order does not authorize fulfilment: ' . $status);
}
echo "Back Office all-order queue: {$count} assertions passed.\n";
