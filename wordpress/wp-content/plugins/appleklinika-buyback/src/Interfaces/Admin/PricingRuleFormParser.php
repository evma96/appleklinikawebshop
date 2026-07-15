<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\ConditionDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class PricingRuleFormParser
{
    /** @param array<string, mixed> $input */
    public function parse(array $input): PricingRuleDefinition
    {
        $kind = new PricingRuleKind($this->text($input, 'rule_kind'));
        $conditionKey = $this->nullableText($input, 'condition_key');
        $operatorText = $this->nullableText($input, 'comparison_operator');
        $amountRaw = trim((string) ($input['amount_minor'] ?? ''));
        $multiplierRaw = trim((string) ($input['multiplier_percent'] ?? ''));
        $amount = $this->nullableInteger($input, 'amount_minor');
        $multiplier = $this->nullablePercentageBasisPoints($input, 'multiplier_percent');

        if ($kind->code() === PricingRuleKind::BASE_PRICE) {
            $conditionKey = null;
            $operatorText = null;
        }

        if ($kind->code() === PricingRuleKind::MODE_ADJUSTMENT) {
            $conditionKey = null;
            $operatorText = null;
            $adjustmentType = $this->text($input, 'adjustment_type');
            if ($adjustmentType === 'amount') {
                if ($multiplierRaw !== '') {
                    throw new \InvalidArgumentException('A módosítás egyszerre nem tartalmazhat fix összeget és szorzót.');
                }
                $multiplier = null;
            } elseif ($adjustmentType === 'multiplier') {
                if ($amountRaw !== '') {
                    throw new \InvalidArgumentException('A módosítás egyszerre nem tartalmazhat fix összeget és szorzót.');
                }
                $amount = null;
            } else {
                throw new \InvalidArgumentException('Ismeretlen módosítási típus.');
            }
        }

        $operator = $operatorText === null ? null : new ComparisonOperator($operatorText);
        $comparison = $conditionKey === null || $operator === null
            ? null
            : $this->comparisonValue($conditionKey, $operator, $this->text($input, 'comparison_value'));

        return new PricingRuleDefinition(
            new PricingRuleCode($this->text($input, 'rule_code')),
            $kind,
            'iphone',
            $kind->code() === PricingRuleKind::BASE_PRICE ? $this->text($input, 'model_key') : null,
            $kind->code() === PricingRuleKind::BASE_PRICE ? new StorageCapacity($this->integer($input, 'storage_gb')) : null,
            $kind->code() === PricingRuleKind::MODE_ADJUSTMENT ? $this->text($input, 'service_mode') : null,
            $conditionKey,
            $operator,
            $comparison,
            $amount === null ? null : new Money($amount, 'HUF'),
            $multiplier === null ? null : new BasisPointsMultiplier($multiplier),
            new RulePriority($this->integer($input, 'priority', 100)),
            isset($input['is_enabled']) && (string) $input['is_enabled'] === '1',
            $this->nullableText($input, 'public_label'),
            $this->nullableTextarea($input, 'internal_note')
        );
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key): string
    {
        return sanitize_text_field((string) ($input[$key] ?? ''));
    }

    /** @param array<string, mixed> $input */
    private function nullableText(array $input, string $key): ?string
    {
        $value = $this->text($input, $key);
        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $input */
    private function nullableTextarea(array $input, string $key): ?string
    {
        $value = sanitize_textarea_field((string) ($input[$key] ?? ''));
        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $input */
    private function integer(array $input, string $key, ?int $default = null): int
    {
        $raw = trim((string) ($input[$key] ?? ''));
        if ($raw === '' && $default !== null) {
            return $default;
        }
        if (preg_match('/^-?\d+$/', $raw) !== 1) {
            throw new \InvalidArgumentException('Egész szám szükséges: ' . $key);
        }
        return (int) $raw;
    }

    /** @param array<string, mixed> $input */
    private function nullableInteger(array $input, string $key): ?int
    {
        return trim((string) ($input[$key] ?? '')) === '' ? null : $this->integer($input, $key);
    }

    /** @param array<string, mixed> $input */
    private function nullablePercentageBasisPoints(array $input, string $key): ?int
    {
        $raw = str_replace(',', '.', trim((string) ($input[$key] ?? '')));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $raw, $parts) !== 1) {
            throw new \InvalidArgumentException('A szorzó százalékos értéke hibás.');
        }
        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    private function comparisonValue(string $key, ComparisonOperator $operator, string $raw): mixed
    {
        $values = in_array($operator->code(), [ComparisonOperator::BETWEEN, ComparisonOperator::IN], true)
            ? array_map('trim', explode(',', $raw))
            : [$raw];

        $typed = array_map(function (string $value) use ($key): mixed {
            return match (ConditionDefinition::typeFor($key)) {
                'boolean' => match (strtolower($value)) {
                    '1', 'true', 'igen' => true,
                    '0', 'false', 'nem' => false,
                    default => throw new \InvalidArgumentException('A logikai összehasonlítás értéke Igen vagy Nem lehet.'),
                },
                'integer' => preg_match('/^\d+$/', $value) === 1
                    ? (int) $value
                    : throw new \InvalidArgumentException('Az összehasonlítás egész számot vár.'),
                default => sanitize_text_field($value),
            };
        }, $values);

        return in_array($operator->code(), [ComparisonOperator::BETWEEN, ComparisonOperator::IN], true) ? $typed : $typed[0];
    }
}
