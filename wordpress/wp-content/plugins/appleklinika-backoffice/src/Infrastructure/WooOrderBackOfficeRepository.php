<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\DeliveryMode;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use InvalidArgumentException;
use WC_Order;
use WC_Order_Item_Product;

final class WooOrderBackOfficeRepository
{
    private const HISTORY_META_KEY = '_appleklinika_backoffice_history';
    private const DAILY_ACTIVITY_TRANSIENT_PREFIX = 'appleklinika_backoffice_activity_';
    private const DELIVERY_MODE_META_KEY = '_appleklinika_backoffice_delivery_mode';
    private const MANUAL_NOTE_MARKER = '[appleklinika-backoffice-manual]';

    private readonly OrderQueueQuery $queueQuery;

    public function __construct(?OrderQueueQuery $queueQuery = null)
    {
        $this->queueQuery = $queueQuery ?? new OrderQueueQuery();
    }

    /** @return object{orders:list<WC_Order>,total:int,max_num_pages:int} */
    public function queuePage(string $queue, int $page, string $term, string $searchType): object
    {
        return wc_get_orders($this->queueQuery->arguments(
            $queue,
            $page,
            $term,
            $searchType,
            $this->usesHpos()
        ));
    }

    public function queuePageNumber(int $page): int
    {
        return $this->queueQuery->page($page);
    }

    /** @return array<string, int> */
    public function queueCounts(): array
    {
        $counts = [];
        foreach (['new', 'preparation', 'packing', 'ready_for_shipping', 'problem'] as $queue) {
            $arguments = $this->queueQuery->arguments($queue, 1, '', '', $this->usesHpos());
            $arguments['limit'] = 1;
            $arguments['return'] = 'ids';
            $result = wc_get_orders($arguments);
            $counts[$queue] = (int) $result->total;
        }

        return $counts;
    }

    public function searchType(string $term, string $requestedType): string
    {
        return $this->queueQuery->searchType($term, $requestedType);
    }

    public function captureDeviceIdentifierSnapshot(WC_Order_Item_Product $item, string $cartItemKey, array $values, WC_Order $order): void
    {
        $productId = $item->get_variation_id() ?: $item->get_product_id();
        if ($productId <= 0) {
            return;
        }

        if ((string) $order->get_meta(OrderQueueQuery::PRIMARY_ITEM_NAME_META_KEY, true) === '') {
            $order->update_meta_data(OrderQueueQuery::PRIMARY_ITEM_NAME_META_KEY, $item->get_name());
        }

        foreach (['_appleklinika_internal_identifier', '_appleklinika_serial_number'] as $metaKey) {
            $identifier = trim(sanitize_text_field((string) get_post_meta($productId, $metaKey, true)));
            if ($identifier === '') {
                continue;
            }

            $item->add_meta_data(OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY, $identifier, false);
            $order->add_meta_data(OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY, $identifier, false);
        }
    }

    public function captureQueueShippingSnapshot(WC_Order $order): void
    {
        $hasShippingSnapshot = (string) $order->get_meta(OrderQueueQuery::SHIPPING_METHOD_META_KEY, true) !== '';
        $hasDeliveryMode = DeliveryMode::isSupported((string) $order->get_meta(self::DELIVERY_MODE_META_KEY, true));
        if ($hasShippingSnapshot && $hasDeliveryMode) {
            return;
        }

        if (! $hasShippingSnapshot) {
            $methods = $order->get_shipping_methods();
            $names = array_map(static fn ($method): string => $method->get_name(), $methods);
            $order->update_meta_data(OrderQueueQuery::SHIPPING_METHOD_META_KEY, implode(', ', $names));
        }
        $order->update_meta_data(self::DELIVERY_MODE_META_KEY, DeliveryMode::fromShippingMethodIds($this->shippingMethodIds($order)));
        $order->save();
    }

    public function state(WC_Order $order): string
    {
        return FulfilmentWorkflow::stateForDeliveryMode(
            (string) $order->get_meta(FulfilmentWorkflow::META_KEY, true),
            $this->deliveryMode($order)
        );
    }

    public function deliveryMode(WC_Order $order): string
    {
        $stored = (string) $order->get_meta(self::DELIVERY_MODE_META_KEY, true);
        if (DeliveryMode::isSupported($stored)) {
            return $stored;
        }

        return DeliveryMode::fromShippingMethodIds($this->shippingMethodIds($order));
    }

    public function deliveryModeLabel(WC_Order $order): string
    {
        return DeliveryMode::label($this->deliveryMode($order));
    }

    /** @return list<array{product_id:int,name:string,quantity:int,details:array<string,string>,detail_sources:array<string,string>}> */
    public function deviceItems(WC_Order $order): array
    {
        $items = [];
        $lineItems = $order->get_items('line_item');

        foreach ($lineItems as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $productId = $item->get_variation_id() ?: $item->get_product_id();
            $details = [];
            $sources = [];
            foreach ($this->deviceMetaLabels() as $key => $label) {
                $value = trim((string) $item->get_meta('_appleklinika_' . $key, true));
                if ($key === 'internal_identifier') {
                    $snapshots = $this->deviceIdentifierSnapshots($item);
                    if ($snapshots === [] && $value !== '') {
                        $snapshots = [$value];
                    }
                    if ($snapshots === []) {
                        $serial = trim((string) $item->get_meta('_appleklinika_serial_number', true));
                        $snapshots = $serial === '' ? [] : [$serial];
                    }
                    // Order-level snapshots do not identify a particular line in a multi-item order.
                    if ($snapshots === [] && count($lineItems) === 1) {
                        $snapshots = $this->deviceIdentifierSnapshots($order);
                    }
                    $value = implode(' / ', $snapshots);
                }
                $source = 'order';
                if ($value === '' && $productId > 0) {
                    $value = (string) get_post_meta($productId, '_appleklinika_' . $key, true);
                    $source = 'product';
                }
                if ($value !== '') {
                    $details[$label] = $key === 'internal_identifier' ? $value : $this->humanValue($key, $value);
                    $sources[$label] = $source;
                }
            }

            $items[] = [
                'product_id' => $productId,
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'details' => $details,
                'detail_sources' => $sources,
            ];
        }

        return $items;
    }

    public function queuePrimaryItem(WC_Order $order): string
    {
        $snapshot = (string) $order->get_meta(OrderQueueQuery::PRIMARY_ITEM_NAME_META_KEY, true);
        if ($snapshot !== '') {
            return $snapshot;
        }

        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                return $item->get_name();
            }
        }

        return 'Nincs termékadat';
    }

    public function queueShippingMethod(WC_Order $order): string
    {
        $snapshot = (string) $order->get_meta(OrderQueueQuery::SHIPPING_METHOD_META_KEY, true);
        if ($snapshot !== '') {
            return $snapshot;
        }

        return $this->shippingMethodName($order);
    }

    public function fulfilmentBlockReason(WC_Order $order): ?string
    {
        $status = $order->get_status();
        if ($status === 'checkout-draft') {
            return 'Ez még befejezetlen pénztári piszkozat, nem beadott rendelés.';
        }
        if (in_array($status, ['cancelled', 'failed', 'refunded', 'trash'], true)) {
            return 'Ez a rendelés nem dolgozható fel a jelenlegi WooCommerce állapota miatt.';
        }
        if (! in_array($status, FulfilmentWorkflow::operationalOrderStatuses(), true)) {
            return 'Ez a rendelés nem szerepel a nyitott operatív munkalistában.';
        }
        if (! $order->is_paid()) {
            return 'A rendelés még nem dolgozható fel: a fizetés ellenőrzése szükséges.';
        }
        if (! DeliveryMode::isSupported($this->deliveryMode($order))) {
            return 'Az átvételi mód nem azonosítható biztonságosan a WooCommerce szállítási metódusából. Ellenőrzés szükséges.';
        }

        return null;
    }

    public function glsReadinessMessage(): ?string
    {
        if (! defined('GLS_SHIPPING_ABSPATH') || ! class_exists('GLS_Shipping_For_Woo') || ! class_exists('GLS_Shipping_Account_Helper') || ! class_exists('GLS_Shipping_Sender_Address_Helper')) {
            return 'GLS kapcsolat nincs konfigurálva ebben a környezetben.';
        }

        $account = \GLS_Shipping_Account_Helper::get_active_account();
        if (! is_array($account) || trim((string) ($account['client_id'] ?? '')) === '' || trim((string) ($account['username'] ?? '')) === '' || trim((string) ($account['password'] ?? '')) === '') {
            return 'GLS kapcsolat nincs konfigurálva ebben a környezetben.';
        }

        $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $localOrTest = wp_get_environment_type() !== 'production' || in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if ($localOrTest && ($account['mode'] ?? '') !== 'sandbox') {
            return 'Helyi és tesztkörnyezetben GLS címke kizárólag Sandbox kapcsolattal készíthető.';
        }

        $sender = \GLS_Shipping_Sender_Address_Helper::format_for_api_pickup(
            \GLS_Shipping_Sender_Address_Helper::get_default_sender_address(),
            (string) \GLS_Shipping_Account_Helper::get_account_setting('phone_number')
        );
        foreach (['Name', 'Street', 'City', 'ZipCode', 'CountryIsoCode', 'ContactPhone'] as $field) {
            if (trim((string) ($sender[$field] ?? '')) === '') {
                return 'A GLS feladóadatai hiányosak. A feladási cím és telefonszám beállítása szükséges.';
            }
        }

        return null;
    }

    public function canCreateGlsLabel(): bool
    {
        return $this->glsReadinessMessage() === null;
    }

    /** @return list<array{order_id?:int,action:string,from:string,to:string,user_id?:int,user:string,at:string}> */
    public function history(WC_Order $order): array
    {
        $history = $order->get_meta(self::HISTORY_META_KEY, true);

        return is_array($history) ? $history : [];
    }

    public function transition(WC_Order $order, string $action, int $userId): void
    {
        $from = $this->state($order);
        $to = FulfilmentWorkflow::transition($from, $action, $this->deliveryMode($order));
        $user = get_userdata($userId);
        $userName = $user ? $user->display_name : 'Ismeretlen felhasználó';
        $history = $this->history($order);
        $activity = [
            'order_id' => $order->get_id(),
            'action' => $action,
            'from' => $from,
            'to' => $to,
            'user_id' => $userId,
            'user' => $userName,
            'at' => current_time('mysql'),
        ];
        $history[] = $activity;

        $order->update_meta_data(FulfilmentWorkflow::META_KEY, $to);
        $order->update_meta_data(self::HISTORY_META_KEY, $history);
        $order->add_order_note(sprintf(
            'Back Office: %s (%s → %s). Munkatárs: %s.',
            FulfilmentWorkflow::actions()[$action],
            FulfilmentWorkflow::labels()[$from],
            FulfilmentWorkflow::labels()[$to],
            $userName
        ), false, true);
        $order->save();
        $this->recordTodayActivity($activity);
    }

    /** @return list<array{order_id:int,action:string,from:string,to:string,user_id:int,user:string,at:string}> */
    public function todayActivity(): array
    {
        $activity = get_transient($this->dailyActivityTransientKey());
        if (! is_array($activity)) {
            return [];
        }

        $today = current_time('Y-m-d');
        return array_values(array_filter($activity, static fn (mixed $entry): bool => is_array($entry) && str_starts_with((string) ($entry['at'] ?? ''), $today)));
    }

    public function addInternalNote(WC_Order $order, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A belső megjegyzés nem lehet üres.');
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        if ($userId <= 0 || ! $user || (! current_user_can('manage_appleklinika_backoffice') && ! current_user_can('manage_options'))) {
            throw new InvalidArgumentException('Belső megjegyzéshez Back Office jogosultság szükséges.');
        }
        $userName = $user ? $user->display_name : 'Ismeretlen felhasználó';
        $content = self::MANUAL_NOTE_MARKER . ' ' . $note;
        $orderId = $order->get_id();
        $authorApplied = false;
        // WooCommerce's default author selection requires edit_shop_orders. Attribute
        // this one authorized Back Office note without granting that broader capability.
        $noteAuthor = static function (array $data, array $context) use ($orderId, $content, $userId, $user, &$authorApplied): array {
            if ($authorApplied || get_current_user_id() !== $userId
                || (int) ($context['order_id'] ?? 0) !== $orderId
                || ! empty($context['is_customer_note'])
                || (int) ($data['comment_post_ID'] ?? 0) !== $orderId
                || ($data['comment_type'] ?? '') !== 'order_note'
                || ($data['comment_content'] ?? '') !== $content) {
                return $data;
            }

            $authorApplied = true;
            $data['comment_author'] = $user->display_name;
            $data['comment_author_email'] = $user->user_email;
            $data['user_id'] = $userId;
            return $data;
        };
        add_filter('woocommerce_new_order_note_data', $noteAuthor, 10, 2);
        try {
            $noteId = $order->add_order_note($content, false, true);
        } finally {
            remove_filter('woocommerce_new_order_note_data', $noteAuthor, 10);
        }
        if (! $noteId) {
            throw new InvalidArgumentException('A belső megjegyzést nem sikerült menteni.');
        }
        $state = $this->state($order);
        $activity = [
            'order_id' => $order->get_id(),
            'action' => 'note',
            'from' => $state,
            'to' => $state,
            'user_id' => $userId,
            'user' => $userName,
            'at' => current_time('mysql'),
        ];
        $history = $this->history($order);
        $history[] = $activity;
        $order->update_meta_data(self::HISTORY_META_KEY, $history);
        $order->save();
        $this->recordTodayActivity($activity);
    }

    public function isManualInternalNote(string $content): bool
    {
        return str_starts_with($content, self::MANUAL_NOTE_MARKER)
            || str_starts_with($content, 'Back Office belső megjegyzés');
    }

    public function manualInternalNoteContent(string $content): string
    {
        if (str_starts_with($content, self::MANUAL_NOTE_MARKER)) {
            return trim(substr($content, strlen(self::MANUAL_NOTE_MARKER)));
        }

        return (string) preg_replace('/^Back Office belső megjegyzés(?: \([^)]*\))?:\s*/u', '', $content);
    }

    public function hasGlsLabel(WC_Order $order): bool
    {
        return (string) $order->get_meta('_gls_print_label', true) !== '';
    }

    /** @return list<string> */
    public function trackingCodes(WC_Order $order): array
    {
        $codes = $order->get_meta('_gls_tracking_codes', true);
        if (is_array($codes)) {
            return array_values(array_filter(array_map('strval', $codes)));
        }

        $legacy = (string) $order->get_meta('_gls_tracking_code', true);
        return $legacy === '' ? [] : [$legacy];
    }

    public function createGlsLabel(WC_Order $order): void
    {
        // Reject an ineligible workflow before loading or invoking the provider.
        FulfilmentWorkflow::transition($this->state($order), 'create_label', $this->deliveryMode($order));
        $blockReason = $this->fulfilmentBlockReason($order);
        if ($blockReason !== null) {
            throw new InvalidArgumentException($blockReason);
        }
        if ($this->hasGlsLabel($order)) {
            throw new InvalidArgumentException('Ehhez a rendeléshez már létezik GLS címke; új címke nem készült.');
        }
        $readiness = $this->glsReadinessMessage();
        if ($readiness !== null) {
            throw new InvalidArgumentException($readiness);
        }

        // The active plugin only includes these operation classes in wp-admin.
        // Reuse its own implementation for the authorized front-end operation.
        foreach ([
            'GLS_Shipping_API_Data' => 'includes/api/class-gls-shipping-api-data.php',
            'GLS_Shipping_API_Service' => 'includes/api/class-gls-shipping-api-service.php',
            'GLS_Shipping_Order' => 'includes/admin/class-gls-shipping-order.php',
        ] as $class => $relativePath) {
            if (! class_exists($class)) {
                $path = GLS_SHIPPING_ABSPATH . $relativePath;
                if (! is_readable($path)) {
                    throw new InvalidArgumentException('A GLS bővítmény címkekészítő funkciója nem érhető el.');
                }
                require_once $path;
            }
        }

        $gls = new \GLS_Shipping_Order();
        $result = $gls->generate_single_order_label($order->get_id());
        if (empty($result['success'])) {
            throw new InvalidArgumentException((string) ($result['error'] ?? 'A GLS címke létrehozása nem sikerült.'));
        }

        $order = wc_get_order($order->get_id());
        if (! $order instanceof WC_Order || ! $this->hasGlsLabel($order)) {
            throw new InvalidArgumentException('A GLS nem adott vissza menthető címkét; a teljesítési állapot nem változott.');
        }
    }

    /** @return list<string> */
    private function deviceIdentifierSnapshots(WC_Order|WC_Order_Item_Product $source): array
    {
        $identifiers = [];
        foreach ($source->get_meta(OrderQueueQuery::DEVICE_IDENTIFIER_META_KEY, false, 'edit') as $meta) {
            $value = $meta->value;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $identifiers[] = trim((string) $value);
            }
        }

        return array_values(array_unique($identifiers));
    }

    /** @return array<string, string> */
    private function deviceMetaLabels(): array
    {
        return [
            'device_type' => 'Készüléktípus',
            'device_model' => 'Modellazonosító',
            'storage_capacity' => 'Tárhely',
            'color' => 'Szín',
            'overall_grade' => 'Állapot',
            'sim_config' => 'SIM konfiguráció',
            'connectivity' => 'Kapcsolat',
            'battery_health' => 'Akkumulátor állapota',
            'internal_identifier' => 'Belső azonosító / IMEI',
        ];
    }

    private function humanValue(string $key, string $value): string
    {
        $labels = [
            'overall_grade' => ['a' => 'A', 'b' => 'B', 'c' => 'C'],
            'storage_capacity' => ['64_gb' => '64 GB', '128_gb' => '128 GB', '256_gb' => '256 GB', '512_gb' => '512 GB', '1_tb' => '1 TB', '2_tb' => '2 TB'],
            'sim_config' => ['dual_esim' => 'Dual eSIM', 'physical_esim' => 'Fizikai + eSIM', 'dual_physical' => 'Dual fizikai'],
            'connectivity' => ['wifi' => 'Wi-Fi', 'wifi_cellular' => 'Wi-Fi + Cellular', 'gps' => 'GPS', 'gps_cellular' => 'GPS + Cellular'],
        ];

        if ($key === 'battery_health' && is_numeric($value)) {
            return $value . '%';
        }

        return $labels[$key][$value] ?? str_replace('_', ' ', $value);
    }

    private function usesHpos(): bool
    {
        return class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    private function shippingMethodName(WC_Order $order): string
    {
        $methods = $order->get_shipping_methods();
        if ($methods === []) {
            return '—';
        }

        return implode(', ', array_map(static fn ($method): string => $method->get_name(), $methods));
    }

    /** @return list<string> */
    private function shippingMethodIds(WC_Order $order): array
    {
        return array_values(array_filter(array_map(
            static fn ($method): string => (string) $method->get_method_id(),
            $order->get_shipping_methods()
        )));
    }

    /** @param array{order_id:int,action:string,from:string,to:string,user_id:int,user:string,at:string} $activity */
    private function recordTodayActivity(array $activity): void
    {
        $key = $this->dailyActivityTransientKey();
        $entries = get_transient($key);
        $entries = is_array($entries) ? $entries : [];
        $entries[] = $activity;
        set_transient($key, $entries, $this->secondsUntilTomorrow());
    }

    private function dailyActivityTransientKey(): string
    {
        return self::DAILY_ACTIVITY_TRANSIENT_PREFIX . current_time('Ymd');
    }

    private function secondsUntilTomorrow(): int
    {
        $tomorrow = strtotime('tomorrow', current_time('timestamp'));
        return max(HOUR_IN_SECONDS, $tomorrow - current_time('timestamp') + HOUR_IN_SECONDS);
    }
}
