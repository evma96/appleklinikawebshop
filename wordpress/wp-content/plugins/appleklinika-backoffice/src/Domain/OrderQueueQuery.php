<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

final class OrderQueueQuery
{
    public const PAGE_SIZE = 25;
    public const MAX_PAGE = 10000;
    public const DEVICE_IDENTIFIER_META_KEY = '_appleklinika_backoffice_device_identifier';
    public const PRIMARY_ITEM_NAME_META_KEY = '_appleklinika_backoffice_primary_item_name';
    public const SHIPPING_METHOD_META_KEY = '_appleklinika_backoffice_shipping_method';

    /** @return array<string, mixed> */
    public function arguments(string $queue, int $page, string $term, string $searchType, bool $hposEnabled): array
    {
        $arguments = [
            'type' => 'shop_order',
            'limit' => self::PAGE_SIZE,
            'page' => $this->page($page),
            'paginate' => true,
            // Orders created in the same second need an immutable tie-breaker so
            // offset pagination cannot repeat or skip them between pages.
            'orderby' => 'date ID',
            'order' => 'DESC',
            'return' => 'objects',
            'status' => FulfilmentWorkflow::operationalOrderStatuses(),
        ];

        $queueMetaQuery = $this->queueMetaQuery($queue);
        $term = trim($term);
        $searchType = $this->searchType($term, $searchType);
        $deviceMetaQuery = [];

        if ($searchType === 'device' && $term !== '') {
            $deviceMetaQuery = [
                'key' => self::DEVICE_IDENTIFIER_META_KEY,
                'value' => $term,
                'compare' => '=',
            ];
        }

        if ($queueMetaQuery !== [] && $deviceMetaQuery !== []) {
            $arguments['meta_query'] = [
                'relation' => 'AND',
                $queueMetaQuery,
                $deviceMetaQuery,
            ];
        } elseif ($queueMetaQuery !== []) {
            $arguments['meta_query'] = $queueMetaQuery;
        } elseif ($deviceMetaQuery !== []) {
            // HPOS expects an outer meta-query group even when there is a single clause.
            $arguments['meta_query'] = [
                'relation' => 'AND',
                $deviceMetaQuery,
            ];
        }

        if ($term === '') {
            return $arguments;
        }

        if ($searchType === 'order') {
            $orderId = max(0, (int) ltrim($term, '#'));
            $arguments['id'] = $orderId > 0 ? $orderId : 0;
            return $arguments;
        }

        if ($searchType === 'email') {
            $arguments['billing_email'] = $term;
            return $arguments;
        }

        if ($hposEnabled && $searchType === 'phone') {
            $arguments['field_query'] = [[
                'field' => 'billing_phone',
                'value' => $term,
                'compare' => 'LIKE',
            ]];
            return $arguments;
        }

        if ($hposEnabled && $searchType === 'customer') {
            $arguments['field_query'] = $this->customerNameQuery($term);
            return $arguments;
        }

        if ($searchType === 'phone') {
            $arguments['billing_phone'] = $term;
        } elseif ($searchType === 'customer') {
            $arguments['billing_first_name'] = $term;
        }

        return $arguments;
    }

    public function page(int $page): int
    {
        return min(self::MAX_PAGE, max(1, $page));
    }

    public function searchType(string $term, string $requestedType): string
    {
        $requestedType = strtolower((string) preg_replace('/[^a-z0-9_]/', '', $requestedType));
        if (in_array($requestedType, ['order', 'customer', 'email', 'phone', 'device'], true)) {
            return $requestedType;
        }

        $term = trim($term);
        if ($term === '') {
            return '';
        }
        if (str_starts_with($term, '#')) {
            return 'order';
        }
        if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if ($digits === $term && strlen($digits) >= 10) {
            return 'device';
        }
        if ($digits !== '' && strlen($digits) >= 7) {
            return 'phone';
        }

        return 'customer';
    }

    /** @return array<string, mixed> */
    private function queueMetaQuery(string $queue): array
    {
        if ($queue === '') {
            return [];
        }

        $states = FulfilmentWorkflow::queueStates()[$queue] ?? [];
        if ($queue === 'new') {
            $stateQuery = [
                'relation' => 'OR',
                [
                    'key' => FulfilmentWorkflow::META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ];

            $stateQuery[] = [
                'key' => FulfilmentWorkflow::META_KEY,
                'value' => FulfilmentWorkflow::NEW,
                'compare' => '=',
            ];
            return $stateQuery;
        }

        return $this->stateMetaQuery($states);
    }

    /** @param list<string> $states @return array<string|int, mixed> */
    private function stateMetaQuery(array $states): array
    {
        $query = ['relation' => 'OR'];
        if (count($states) > 1) {
            $query[] = [
                'key' => FulfilmentWorkflow::META_KEY,
                'value' => $states,
                'compare' => 'IN',
            ];
            return $query;
        }

        foreach ($states as $state) {
            $query[] = [
                'key' => FulfilmentWorkflow::META_KEY,
                'value' => $state,
                'compare' => '=',
            ];
        }

        return $query;
    }

    /** @return array<string|int, mixed> */
    private function customerNameQuery(string $term): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', $term) ?: []));
        $query = [
            'relation' => 'OR',
            [
                'field' => 'billing_first_name',
                'value' => $term,
                'compare' => 'LIKE',
            ],
            [
                'field' => 'billing_last_name',
                'value' => $term,
                'compare' => 'LIKE',
            ],
        ];

        if (count($parts) === 2) {
            $query[] = [
                'relation' => 'AND',
                [
                    'field' => 'billing_first_name',
                    'value' => $parts[0],
                    'compare' => 'LIKE',
                ],
                [
                    'field' => 'billing_last_name',
                    'value' => $parts[1],
                    'compare' => 'LIKE',
                ],
            ];
            $query[] = [
                'relation' => 'AND',
                [
                    'field' => 'billing_first_name',
                    'value' => $parts[1],
                    'compare' => 'LIKE',
                ],
                [
                    'field' => 'billing_last_name',
                    'value' => $parts[0],
                    'compare' => 'LIKE',
                ],
            ];
        }

        return $query;
    }
}
