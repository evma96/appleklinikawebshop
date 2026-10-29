<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/PaymentState.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;

final class QueueQueryTest
{
    private int $assertions = 0;

    /** @var array<int, array{status:string,payment_method:string,meta:array<string,string>}> */
    private array $orders = [];

    public function run(): void
    {
        // These fixtures cover the stored values, not only their normalized labels.
        foreach ([
            1 => null, 2 => '', 3 => 'unrecognised_legacy_value', 4 => 'new',
            5 => 'preparation', 6 => 'started', 7 => 'device_checked', 8 => 'packing',
            9 => 'ready_for_shipping', 10 => 'ready_for_pickup', 11 => 'packed',
            12 => 'documents_ready', 13 => 'label_created', 14 => 'problem',
            15 => 'handed_to_gls', 16 => 'picked_up', 17 => 'completed',
        ] as $id => $state) {
            $this->fixture($id, 'processing', $state);
        }
        $this->fixture(18, 'completed', null);
        $this->fixture(19, 'completed', 'handed_to_gls');
        $this->fixture(20, 'completed', 'packing');
        $this->fixture(21, 'pending', 'new');
        $this->fixture(22, 'on-hold', 'preparation');
        $this->fixture(23, 'failed', 'preparation');
        $this->fixture(24, 'cancelled', 'new');
        $this->fixture(25, 'refunded', 'completed');
        $this->fixture(26, 'checkout-draft', null);
        $this->fixture(27, 'pending', 'handed_to_gls');
        $this->fixture(28, 'on-hold', 'picked_up');
        $this->fixture(29, 'trash', 'new');
        $this->fixture(30, 'auto-draft', null);
        $this->fixture(31, 'custom-order-status', 'unrecognised_legacy_value');
        foreach (['bacs', 'cod', 'cheque', ''] as $offset => $method) {
            $this->fixture(32 + $offset, 'processing', 'new', $method);
        }
        // Provider/private markers cannot substitute for native Woo paid status.
        $this->orders[21]['meta']['_barion_payment_status'] = 'Succeeded';
        $this->orders[9]['meta'][OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = 'QA-GLS-IMEI-001';
        $this->orders[19]['meta'][OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = 'QA-CLOSED-IMEI-001';

        $query = new OrderQueueQuery();
        $ids = fn (string $queue, string $term = '', string $type = ''): array => $this->matchingIds($query->arguments($queue, 1, $term, $type, true));
        $open = range(1, 14);

        $this->assert(FulfilmentWorkflow::queueLabels()['all'] === 'Összes rendelés', 'The all-order queue is an explicit diagnostic option, distinct from paid operational work.');
        $this->assert($ids('all') === range(1, 35), 'All includes every payment method, order status and fulfilment state, including drafts, trash and custom statuses.');
        $all = $query->arguments('all', 2, '', '', true);
        $this->assert($all['status'] === ['all'] && ! isset($all['meta_query']) && ! isset($all['payment_method']), 'All removes payment, status and fulfilment restrictions, not just the open-state filter.');
        $this->assert($all['type'] === 'shop_order' && $all['limit'] === 25 && $all['page'] === 2 && $all['paginate'] === true && $all['orderby'] === 'date ID', 'All still queries orders only with bounded deterministic pagination.');
        $this->assert($ids('all', '#24', '') === [24] && $ids('all', '26', '') === [26] && $ids('all', '999999', '') === [], 'Automatic exact-ID search works for cancelled orders and drafts without broadening unknown matches.');
        $this->assert($ids('all', 'QA-CLOSED-IMEI-001', 'device') === [19], 'Exact device search remains active in the all-order scope.');
        $this->assert(isset($query->arguments('all', 1, 'Teszt Elek', 'customer', true)['field_query']) && $query->arguments('all', 1, 'qa@example.test', 'email', true)['billing_email'] === 'qa@example.test', 'All retains customer and email search constraints.');

        $this->assert($ids('') === $open, 'Operational work excludes unpaid Barion, legacy payment methods, physical handover and terminal Woo statuses.');
        $this->assert($ids('new') === [1, 2, 3, 4], 'Paid missing, empty and unknown stored states appear in the same NEW queue as their rendered normalized state.');
        $this->assert($ids('preparation') === [5, 6, 7], 'Preparation includes paid legacy states but excludes on-hold orders.');
        $this->assert($ids('packing') === [8], 'Packing excludes WooCommerce-completed orders even when old Back Office metadata still says packing.');
        $this->assert($ids('ready_for_shipping') === [9, 10, 11, 12, 13], 'The ready queue includes GLS, pickup and all supported legacy ready states.');
        $this->assert($ids('problem') === [14], 'The problem queue has its own exact state scope.');
        $this->assert($ids('handed_to_gls') === [15, 16, 17, 19, 27, 28], 'Recorded physical handover remains discoverable after WooCommerce completion and excludes refunded orders.');
        $this->assert($ids('wc_completed') === [18, 19, 20], 'The closed-order view finds WooCommerce completion independently of absent or stale Back Office state.');
        $this->assert($ids('payment_pending') === [21] && $ids('payment_on_hold') === [22], 'Payment filters use the real pending/on-hold status and retain the open-state boundary.');

        $partition = [];
        foreach (['new', 'preparation', 'packing', 'ready_for_shipping', 'problem'] as $queue) {
            $partition = [...$partition, ...$ids($queue)];
        }
        sort($partition);
        $this->assert($partition === $open && count(array_unique($partition)) === count($open), 'Dashboard state queues partition the open worklist exactly once, including legacy and unknown states.');

        foreach ([true, false] as $hpos) {
            foreach (['', 'new', 'preparation', 'packing', 'ready_for_shipping', 'problem'] as $queue) {
                $args = $query->arguments($queue, 2, 'QA-GLS-IMEI-001', 'device', $hpos);
                $this->assert($args['payment_method'] === 'barion' && $args['status'] === ['processing'] && $args['page'] === 2 && isset($args['meta_query']), 'Every operational query/count uses native paid Barion constraints, retaining search and pagination on both stores.');
            }
        }
        foreach (['gls', 'pickup'] as $mode) {
            $this->orders[21]['meta']['_appleklinika_backoffice_delivery_mode'] = $mode;
            $this->assert($ids('', '21', 'order') === [], 'Pending is diagnostic-only regardless of delivery mode or provider marker.');
            $this->orders[21]['status'] = 'processing';
            $this->assert($ids('', '21', 'order') === [21] && in_array(21, $ids('new'), true), 'The same native Woo order is automatically admitted after payment without a Back Office confirmation marker.');
            $this->orders[21]['status'] = 'pending';
        }

        $this->assert($ids('', 'QA-GLS-IMEI-001', 'device') === [9] && $ids('ready_for_shipping', 'QA-GLS-IMEI-001', 'device') === [9], 'Exact device search intersects the default open scope and a selected state queue.');
        $this->assert($ids('payment_pending', 'QA-GLS-IMEI-001', 'device') === [] && $ids('wc_completed', 'QA-CLOSED-IMEI-001', 'device') === [19], 'Device search cannot escape payment filters and also works in the closed-order view.');
        $this->assert($ids('wc_completed', '#18', 'order') === [18] && $ids('', '#18', 'order') === [], 'Exact order search retains the selected open/closed scope.');
        $this->assert($ids('invalid_queue') === $open, 'An unknown queue safely normalizes to the open worklist.');
        $this->assert(FulfilmentWorkflow::operationalOrderStatuses() === ['pending', 'on-hold', 'processing'], 'Read-only closed-order discovery does not authorize mutations on completed orders.');
        $this->assert(FulfilmentWorkflow::actions()['note'] === 'Belső megjegyzés hozzáadása', 'Recorded manual notes have an operational activity label.');

        echo "Back Office queue query passed: {$this->assertions} assertions.\n";
    }

    private function fixture(int $id, string $status, ?string $state, string $method = 'barion'): void
    {
        $this->orders[$id] = ['status' => $status, 'payment_method' => $method, 'meta' => $state === null ? [] : [FulfilmentWorkflow::META_KEY => $state]];
    }

    /** @param array<string,mixed> $arguments @return list<int> */
    private function matchingIds(array $arguments): array
    {
        $ids = [];
        foreach ($this->orders as $id => $order) {
            if ((! in_array('all', $arguments['status'], true) && ! in_array($order['status'], $arguments['status'], true))
                || (isset($arguments['payment_method']) && $arguments['payment_method'] !== $order['payment_method'])
                || (isset($arguments['id']) && $arguments['id'] !== $id)
                || (isset($arguments['meta_query']) && ! $this->matchesMeta($arguments['meta_query'], $order['meta']))) {
                continue;
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * A test-only evaluator for native metadata predicates. Runtime integration
     * tests separately verify that WooCommerce/HPOS executes the same conditions.
     *
     * @param array<string|int,mixed> $query
     * @param array<string,string> $meta
     */
    private function matchesMeta(array $query, array $meta): bool
    {
        if (isset($query['key'])) {
            $exists = array_key_exists($query['key'], $meta);
            if ($query['compare'] === 'NOT EXISTS') {
                return ! $exists;
            }
            if (! $exists) {
                return false;
            }
            return match ($query['compare']) {
                '=' => $meta[$query['key']] === $query['value'],
                'IN' => in_array($meta[$query['key']], $query['value'], true),
                'NOT IN' => ! in_array($meta[$query['key']], $query['value'], true),
                default => throw new RuntimeException('The query uses an unsupported test predicate.'),
            };
        }
        $matches = [];
        foreach ($query as $key => $clause) {
            if (is_int($key)) {
                $matches[] = $this->matchesMeta($clause, $meta);
            }
        }
        return ($query['relation'] ?? 'AND') === 'OR' ? in_array(true, $matches, true) : ! in_array(false, $matches, true);
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new QueueQueryTest())->run();
