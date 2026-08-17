<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Interfaces/BackOfficeRouter.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\DeliveryMode;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use Appleklinika\BackOffice\Interfaces\BackOfficeRouter;

final class FulfilmentWorkflowTest
{
    private int $assertions = 0;

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    public function run(): void
    {
        $state = FulfilmentWorkflow::NEW;
        foreach (['start', 'start_packing', 'packing_completed', 'create_label', 'handed_to_gls'] as $action) {
            $state = FulfilmentWorkflow::transition($state, $action);
        }
        $this->assert($state === FulfilmentWorkflow::HANDED_TO_GLS, 'The normal fulfilment path reaches GLS handover without a manual completion step.');
        $this->assert(FulfilmentWorkflow::transition(FulfilmentWorkflow::PACKED, 'problem') === FulfilmentWorkflow::PROBLEM, 'A problem can be raised during packing.');
        $this->assert(FulfilmentWorkflow::transition(FulfilmentWorkflow::PROBLEM, 'resume') === FulfilmentWorkflow::PREPARATION, 'A problem order can return to preparation.');

        try {
            FulfilmentWorkflow::transition(FulfilmentWorkflow::NEW, 'complete');
            $this->assert(false, 'An invalid transition must fail.');
        } catch (InvalidArgumentException) {
            $this->assert(true, 'An invalid transition is rejected.');
        }

        try {
            FulfilmentWorkflow::transition(FulfilmentWorkflow::HANDED_TO_GLS, 'handed_to_gls');
            $this->assert(false, 'Repeated GLS handover must fail.');
        } catch (InvalidArgumentException) {
            $this->assert(true, 'Repeated GLS handover is rejected.');
        }

        $queueQuery = new OrderQueueQuery();
        $newQueue = $queueQuery->arguments('new', 2, '', '', true);
        $this->assert($newQueue['limit'] === OrderQueueQuery::PAGE_SIZE && $newQueue['paginate'] === true, 'Queue queries have a bounded page size and request total pagination data.');
        $this->assert($newQueue['page'] === 2, 'Queue queries can address pages beyond the first page.');
        $this->assert($newQueue['orderby'] === 'date ID' && $newQueue['order'] === 'DESC', 'Queue pagination has a deterministic creation-date and order-ID sort for timestamp ties.');
        $this->assert($newQueue['status'] === FulfilmentWorkflow::operationalOrderStatuses(), 'Checkout drafts and terminal WooCommerce statuses are excluded at query level.');
        $this->assert($newQueue['meta_query']['relation'] === 'OR' && $newQueue['meta_query'][0]['compare'] === 'NOT EXISTS', 'A queue-only query keeps its existing meta-query structure without an unnecessary outer group.');
        $this->assert($queueQuery->page(0) === 1 && $queueQuery->page(PHP_INT_MAX) === OrderQueueQuery::MAX_PAGE, 'Invalid queue pages are bounded safely.');

        $deviceSearch = $queueQuery->arguments('problem', 3, '356789012345678', 'device', true);
        $this->assert($deviceSearch['page'] === 3 && $deviceSearch['meta_query']['relation'] === 'AND', 'Device searches remain paginated and combine queue filtering in the database query.');
        $this->assert($deviceSearch['meta_query'][0]['relation'] === 'OR', 'A combined queue and device search preserves the queue OR group inside the outer AND group.');
        $this->assert($deviceSearch['meta_query'][1]['key'] === OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY && $deviceSearch['meta_query'][1]['compare'] === '=', 'Device identifier search uses an exact order snapshot lookup.');
        $deviceOnlySearch = $queueQuery->arguments('', 1, 'AK-DEMO-13PRO-079', 'device', true);
        $this->assert($deviceOnlySearch['meta_query']['relation'] === 'AND' && $deviceOnlySearch['meta_query'][0]['key'] === OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY, 'A single device condition remains inside an HPOS-compatible meta-query group.');
        $preparationQueue = $queueQuery->arguments('preparation', 1, '', '', true);
        $this->assert($preparationQueue['meta_query']['relation'] === 'OR' && $preparationQueue['meta_query'][0]['compare'] === 'IN' && $preparationQueue['meta_query'][0]['value'] === FulfilmentWorkflow::queueStates()['preparation'], 'Multi-state queue-only queries keep their group boundary and use one equivalent HPOS-native IN condition.');
        $readyQueue = $queueQuery->arguments('ready_for_shipping', 1, '', '', true);
        $this->assert($readyQueue['meta_query']['relation'] === 'OR' && $readyQueue['meta_query'][0]['compare'] === 'IN' && $readyQueue['meta_query'][0]['value'] === FulfilmentWorkflow::queueStates()['ready_for_shipping'], 'The shipping-ready queue avoids multiple joins for alternative values of the same state key.');
        $readyDeviceSearch = $queueQuery->arguments('ready_for_shipping', 1, 'QA-GLS-IMEI-001', 'device', true);
        $this->assert($readyDeviceSearch['meta_query']['relation'] === 'AND' && $readyDeviceSearch['meta_query'][0]['relation'] === 'OR' && $readyDeviceSearch['meta_query'][0][0]['compare'] === 'IN' && $readyDeviceSearch['meta_query'][1]['key'] === OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY, 'Shipping-ready plus device search keeps the ready-state OR group intact inside one explicit AND group.');

        $nameSearch = $queueQuery->arguments('', 4, 'Teszt Elek', 'customer', true);
        $this->assert($nameSearch['page'] === 4 && isset($nameSearch['field_query']), 'Customer search is a paged HPOS field query, not an in-memory order loop.');
        $orderSearch = $queueQuery->arguments('', 1, '#529', 'order', true);
        $this->assert(($orderSearch['id'] ?? 0) === 529, 'Order-number search uses WooCommerce\'s HPOS-compatible ID constraint.');
        $this->assert(OrderQueueQuery::PRIMARY_ITEM_NAME_META_KEY !== '' && OrderQueueQuery::SHIPPING_METHOD_META_KEY !== '', 'New queue rows can use order-time item and shipping snapshots without loading product details per row.');

        $this->assert(FulfilmentWorkflow::state(FulfilmentWorkflow::STARTED) === FulfilmentWorkflow::PREPARATION && FulfilmentWorkflow::state(FulfilmentWorkflow::LABEL_CREATED) === FulfilmentWorkflow::READY_FOR_SHIPPING, 'Old workflow states normalize safely to the simplified workflow.');
        $this->assert(FulfilmentWorkflow::customerProgressState(FulfilmentWorkflow::LABEL_CREATED) === FulfilmentWorkflow::READY_FOR_SHIPPING, 'Internal label creation maps to the safe public shipping-ready stage.');
        $this->assert(FulfilmentWorkflow::customerProgressState(FulfilmentWorkflow::PROBLEM, [['to' => FulfilmentWorkflow::PACKED], ['to' => FulfilmentWorkflow::PROBLEM]]) === FulfilmentWorkflow::READY_FOR_SHIPPING, 'A problem state keeps the customer at the last safe public stage.');
        $this->assert(! in_array('Problémás', FulfilmentWorkflow::customerProgressLabels(), true), 'Customer progress labels do not expose the internal problem state.');
        $this->assert(FulfilmentWorkflow::primaryAction(FulfilmentWorkflow::READY_FOR_SHIPPING, DeliveryMode::GLS, false) === 'create_label' && FulfilmentWorkflow::primaryAction(FulfilmentWorkflow::READY_FOR_SHIPPING, DeliveryMode::GLS, true) === 'handed_to_gls', 'The GLS primary action distinguishes label creation from physical GLS handover.');

        $pickupState = FulfilmentWorkflow::NEW;
        foreach (['start', 'prepare_pickup', 'picked_up'] as $action) {
            $pickupState = FulfilmentWorkflow::transition($pickupState, $action, DeliveryMode::PICKUP);
        }
        $this->assert($pickupState === FulfilmentWorkflow::PICKED_UP, 'Personal pickup follows new, preparation, ready-for-pickup, and picked-up states.');
        $this->assert(FulfilmentWorkflow::transition(FulfilmentWorkflow::READY_FOR_PICKUP, 'problem', DeliveryMode::PICKUP) === FulfilmentWorkflow::PROBLEM && FulfilmentWorkflow::transition(FulfilmentWorkflow::PROBLEM, 'resume', DeliveryMode::PICKUP) === FulfilmentWorkflow::PREPARATION, 'A pickup problem returns safely to its preparation path.');
        $this->assert(FulfilmentWorkflow::primaryAction(FulfilmentWorkflow::PREPARATION, DeliveryMode::PICKUP) === 'prepare_pickup' && FulfilmentWorkflow::primaryAction(FulfilmentWorkflow::READY_FOR_PICKUP, DeliveryMode::PICKUP) === 'picked_up', 'Pickup orders never receive GLS workflow actions.');
        $this->assert(FulfilmentWorkflow::customerProgressLabels(DeliveryMode::PICKUP)[FulfilmentWorkflow::READY_FOR_PICKUP] === 'Átvételre előkészítve' && FulfilmentWorkflow::customerProgressState(FulfilmentWorkflow::PROBLEM, [['to' => FulfilmentWorkflow::READY_FOR_PICKUP]], DeliveryMode::PICKUP) === FulfilmentWorkflow::READY_FOR_PICKUP, 'Pickup customer progress remains delivery-mode aware and keeps problems internal.');
        $this->assert(DeliveryMode::fromShippingMethodIds(['local_pickup']) === DeliveryMode::PICKUP && DeliveryMode::fromShippingMethodIds(['gls_shipping_method']) === DeliveryMode::GLS && DeliveryMode::fromShippingMethodIds(['flat_rate']) === DeliveryMode::UNKNOWN, 'Delivery mode comes from allowlisted canonical WooCommerce method IDs, never translated titles.');

        $dailyCounts = FulfilmentWorkflow::employeeDailyCounts([
            ['user_id' => 8, 'user' => 'Martin'],
            ['user_id' => 8, 'user' => 'Martin'],
            ['user_id' => 12, 'user' => 'Peter'],
        ]);
        $this->assert($dailyCounts[0] === ['user_id' => 8, 'user' => 'Martin', 'count' => 2] && $dailyCounts[1]['count'] === 1, 'Today activity groups actions by recorded employee identity.');

        $worklistContext = BackOfficeRouter::normalizeWorklistContext([
            'queue' => 'packing',
            's' => 'Kovács',
            'search_type' => 'customer',
            'queue_page' => '3',
        ]);
        $this->assert($worklistContext === ['queue' => 'packing', 's' => 'Kovács', 'search_type' => 'customer', 'queue_page' => 3], 'Order links and PRG flows preserve the validated queue, search, search type, and page context.');
        $invalidContext = BackOfficeRouter::normalizeWorklistContext([
            'queue' => 'not_a_queue',
            's' => '<b>Kovács</b>',
            'search_type' => 'javascript',
            'queue_page' => PHP_INT_MAX,
            'return_url' => 'https://evil.example/redirect',
        ]);
        $this->assert($invalidContext === ['s' => 'Kovács', 'queue_page' => OrderQueueQuery::MAX_PAGE], 'Invalid context is normalized and arbitrary external return URLs are ignored.');

        $repository = file_get_contents(dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php');
        $router = file_get_contents(dirname(__DIR__) . '/src/Interfaces/BackOfficeRouter.php');
        $this->assert(is_string($repository) && str_contains($repository, "'user_id' => \$userId") && str_contains($repository, "'order_id' => \$order->get_id()") && str_contains($repository, "checkout-draft"), 'Workflow history records both employee ID and order ID, while checkout drafts have an explicit operational block.');
        $customerProgressStart = is_string($router) ? strpos($router, 'public function renderCustomerProgress') : false;
        $customerProgressEnd = is_string($router) ? strpos($router, 'public function enqueueCustomerProgressStyle') : false;
        $customerProgress = $customerProgressStart !== false && $customerProgressEnd !== false ? substr($router, $customerProgressStart, $customerProgressEnd - $customerProgressStart) : '';
        $this->assert(str_contains($customerProgress, '$order->get_user_id() !== get_current_user_id()') && ! str_contains($customerProgress, 'Back Office belső megjegyzés') && ! str_contains($customerProgress, "['user']"), 'Customer progress is restricted to the order owner and does not render internal notes or employee names.');
        $this->assert(is_string($router) && str_contains($router, 'fulfilmentBlockReason') && str_contains($router, 'Rendelés állapota frissítve:') && str_contains($router, 'notice_type'), 'Blocked actions and successful state changes both have visible Post/Redirect/Get feedback.');
        $this->assert(is_string($router) && str_contains($router, 'orderUrl($order->get_id(), $worklistContext)') && str_contains($router, 'renderWorklistContextInputs($worklistContext)') && str_contains($router, 'worklistContext($_POST)'), 'List-to-detail links, state-change PRG, and internal-note PRG all carry the validated worklist context.');
        $this->assert(is_string($repository) && str_contains($repository, 'MANUAL_NOTE_MARKER') && str_contains($router, 'isManualInternalNote($content)'), 'Manual internal notes have a dedicated marker, so workflow system notes remain out of the notes section.');
        $this->assert(is_string($router) && str_contains($router, 'if ($deliveryMode === DeliveryMode::GLS)') && str_contains($router, 'Rendelési lap nyomtatása') && str_contains($router, 'GLS kapcsolat nincs konfigurálva ebben a környezetben.'), 'Pickup details omit the GLS panel, printing is delivery-neutral, and unavailable GLS readiness is explicit.');
        $this->assert(is_string($router) && str_contains($router, '$action === \'handed_to_gls\' && ! $this->orders->hasGlsLabel($order)'), 'A GLS handover is rejected server-side until a real GLS label exists.');
        $this->assert(is_string($router) && str_contains($router, 'rawurlencode((string) $value)') && ! str_contains($router, "'notice' => rawurlencode"), 'Worklist context values, including #order searches, are encoded safely through PRG URLs.');

        echo "Back Office fulfilment workflow passed: {$this->assertions} assertions.\n";
    }
}

(new FulfilmentWorkflowTest())->run();
