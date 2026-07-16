<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;

final class BenchmarkRuleCanonicalizer
{
    /** @return array<string, mixed> */
    public static function definition(PricingRuleDefinition $definition): array
    {
        return [
            'rule_code' => $definition->code->code(),
            'rule_kind' => $definition->kind->code(),
            'category' => $definition->category,
            'model_key' => $definition->modelKey,
            'storage_gb' => $definition->storage?->gigabytes(),
            'service_mode' => $definition->serviceMode,
            'condition_key' => $definition->conditionKey,
            'comparison_operator' => $definition->operator?->code(),
            'comparison_value' => $definition->comparisonValue,
            'amount_minor' => $definition->amount?->amount(),
            'multiplier_bps' => $definition->multiplier?->value(),
            'priority' => $definition->priority->value(),
            'enabled' => $definition->enabled,
            'public_label' => $definition->publicLabel,
            'internal_note' => $definition->internalNote,
        ];
    }

    /** @param list<PricingRuleDefinition> $definitions */
    public static function definitionsHash(array $definitions): string
    {
        $rows = array_map(self::definition(...), $definitions);
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['rule_code'], (string) $b['rule_code']));
        return hash('sha256', self::canonicalJson($rows));
    }

    /** @param list<PricingRule> $rules */
    public static function persistedRulesHash(array $rules): string
    {
        return self::definitionsHash(array_map(static fn (PricingRule $rule): PricingRuleDefinition => $rule->definition(), $rules));
    }

    public static function canonicalJson(mixed $value): string
    {
        return json_encode(self::sortValue($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function sortValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::sortValue(...), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortValue($item);
        }
        return $value;
    }
}
