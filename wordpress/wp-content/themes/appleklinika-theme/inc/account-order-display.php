<?php

declare(strict_types=1);

/**
 * Creates the semantic amount rows shown on customer order cards.
 *
 * The values are deliberately numeric here; WooCommerce formatting stays in
 * the account templates so this helper has no WordPress or presentation side
 * effects.
 *
 * @return array{state: string, rows: list<array{key: string, label: string, amount: float, emphasis: string}>}
 */
function appleklinika_account_order_price_rows(float $orderTotal, float $refundedTotal = 0.0, ?float $preDiscountTotal = null): array
{
    $orderTotal = max(0.0, $orderTotal);
    $refundedTotal = min($orderTotal, max(0.0, $refundedTotal));
    $hasRefund = $refundedTotal > 0.00001;

    if ($hasRefund) {
        return [
            'state' => 'refunded',
            'rows' => [
                ['key' => 'original', 'label' => 'Eredeti rendelési összeg', 'amount' => $orderTotal, 'emphasis' => 'secondary'],
                ['key' => 'refunded', 'label' => 'Visszatérített összeg', 'amount' => $refundedTotal, 'emphasis' => 'primary'],
                ['key' => 'remaining', 'label' => 'Visszatérítés után', 'amount' => max(0.0, $orderTotal - $refundedTotal), 'emphasis' => 'secondary'],
            ],
        ];
    }

    if ($preDiscountTotal !== null && $preDiscountTotal > $orderTotal + 0.00001) {
        return [
            'state' => 'discounted',
            'rows' => [
                ['key' => 'original', 'label' => 'Eredeti rendelési összeg', 'amount' => $preDiscountTotal, 'emphasis' => 'struck'],
                ['key' => 'paid', 'label' => 'Fizetett rendelési összeg', 'amount' => $orderTotal, 'emphasis' => 'primary'],
            ],
        ];
    }

    return [
        'state' => 'standard',
        'rows' => [
            ['key' => 'total', 'label' => 'Rendelés összege', 'amount' => $orderTotal, 'emphasis' => 'primary'],
        ],
    ];
}
