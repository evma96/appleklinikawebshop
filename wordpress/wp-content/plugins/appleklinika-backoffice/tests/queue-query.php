<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;

final class QueueQueryTest
{
    private int $assertions = 0;

    /** @var array<int, array{status:string,meta:array<string,string>}> */
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
        $this->orders[9]['meta'][OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = 'QA-GLS-IMEI-001';
        $this->orders[19]['meta'][OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY] = 'QA-CLOSED-IMEI-001';

        $query = new OrderQueueQuery();
        $ids = fn (string $queue, string $term = '', string $type = ''): array => $this->matchingIds($query->arguments($queue, 1, $term, $type, true));
        $open = [...range(1, 14), 21, 22];

        $this->assert($ids('') === $open, 'The open worklist excludes physical handover, pickup, legacy completion and terminal WooCommerce statuses.');
        $this->assert($ids('new') === [1, 2, 3, 4, 21], 'Missing, empty and unknown stored states appear in the same NEW queue as their rendered normalized state.');
        $this->assert($ids('preparation') === [5, 6, 7, 22], 'Preparation includes its legacy states and submitted on-hold orders.');
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

        $this->assert($ids('', 'QA-GLS-IMEI-001', 'device') === [9] && $ids('ready_for_shipping', 'QA-GLS-IMEI-001', 'device') === [9], 'Exact device search intersects the default open scope and a selected state queue.');
        $this->assert($ids('payment_pending', 'QA-GLS-IMEI-001', 'device') === [] && $ids('wc_completed', 'QA-CLOSED-IMEI-001', 'device') === [19], 'Device search cannot escape payment filters and also works in the closed-order view.');
        $this->assert($ids('wc_completed', '#18', 'order') === [18] && $ids('', '#18', 'order') === [], 'Exact order search retains the selected open/closed scope.');
        $this->assert($ids('invalid_queue') === $open, 'An unknown queue safely normalizes to the open worklist.');
        $this->assert(FulfilmentWorkflow::operationalOrderStatuses() === ['pending', 'on-hold', 'processing'], 'Read-only closed-order discovery does not authorize mutations on completed orders.');
        $this->assert(FulfilmentWorkflow::actions()['note'] === 'Belső megjegyzés hozzáadása', 'Recorded manual notes have an operational activity label.');

        echo "Back Office queue query passed: {$this->assertions} assertions.\n";
    }

    private function fixture(int $id, string $status, ?string $state): void
    {
        $this->orders[$id] = ['status' => $status, 'meta' => $state === null ? [] : [FulfilmentWorkflow::META_KEY => $state]];
    }

    /** @param array<string,mixed> $arguments @return list<int> */
    private function matchingIds(array $arguments): array
    {
        $ids = [];
        foreach ($this->orders as $id => $order) {
            if (! in_array($order['status'], $arguments['status'], true)
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
