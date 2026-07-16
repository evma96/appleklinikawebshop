<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;

final class PriceBookActivationReadinessEvaluator
{
    private const SUPPORTED_IPHONE_STORAGE = [32, 64, 128, 256, 512, 1024];

    public function __construct(private readonly PriceBookValidator $validator = new PriceBookValidator())
    {
    }

    /** @param list<PricingRule> $rules @param list<string> $knownIphoneModelKeys */
    public function evaluate(PriceBook $book, array $rules, array $knownIphoneModelKeys, \DateTimeImmutable $at): PriceBookActivationReadinessReport
    {
        if ($book->id() === null) {
            throw new \InvalidArgumentException('Activation readiness requires a persisted price book.');
        }

        $blocking = $this->validator->validateConfiguration($book, $rules)->issues;
        $warnings = [];
        $enabledBaseCount = 0;
        $enabledAdjustmentCount = 0;
        $baseKeys = [];
        $modeKeys = [];
        $kindCounts = [];
        $validBaseRules = [];

        foreach ($rules as $rule) {
            if (! $rule->priceBookId()->equals($book->id())) {
                $blocking[] = 'rule_price_book_mismatch';
                continue;
            }

            $definition = $rule->definition();
            if (! $definition->enabled) {
                continue;
            }

            $kind = $definition->kind->code();
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

            if ($kind === PricingRuleKind::BASE_PRICE) {
                ++$enabledBaseCount;
                if ($definition->category !== 'iphone') {
                    $blocking[] = 'unsupported_category';
                    continue;
                }
                if ($definition->modelKey === null || ! in_array($definition->modelKey, $knownIphoneModelKeys, true)) {
                    $blocking[] = 'unknown_model_key';
                    continue;
                }
                if ($definition->storage === null || ! in_array($definition->storage->gigabytes(), self::SUPPORTED_IPHONE_STORAGE, true)) {
                    $blocking[] = 'invalid_storage';
                    continue;
                }
                $key = $definition->category . '|' . $definition->modelKey . '|' . $definition->storage->gigabytes();
                $baseKeys[$key] = ($baseKeys[$key] ?? 0) + 1;
                $validBaseRules[] = $rule;
                continue;
            }

            if (in_array($kind, [PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MODE_ADJUSTMENT], true)) {
                ++$enabledAdjustmentCount;
            }
            if ($kind === PricingRuleKind::MODE_ADJUSTMENT && $definition->serviceMode !== null) {
                $modeKeys[$definition->serviceMode] = ($modeKeys[$definition->serviceMode] ?? 0) + 1;
            }
        }

        if ($enabledBaseCount === 0) {
            $blocking[] = 'missing_base_price';
        }
        foreach ($baseKeys as $count) {
            if ($count > 1) {
                $blocking[] = 'duplicate_base_price';
                break;
            }
        }
        foreach ($modeKeys as $mode => $count) {
            if (! in_array($mode, ServiceMode::supportedCodes(), true)) {
                $blocking[] = 'unsupported_service_mode';
            } elseif ($count > 1) {
                $blocking[] = 'duplicate_mode_adjustment';
            }
        }

        foreach (ServiceMode::supportedCodes() as $mode) {
            if (! isset($modeKeys[$mode])) {
                $warnings[] = 'missing_mode_adjustment_' . $mode;
            }
        }
        if (($kindCounts[PricingRuleKind::HARD_REJECT] ?? 0) === 0) {
            $warnings[] = 'no_hard_reject_rules';
        }
        if (($kindCounts[PricingRuleKind::MANUAL_REVIEW] ?? 0) === 0) {
            $warnings[] = 'no_manual_review_rules';
        }
        if (($kindCounts[PricingRuleKind::FIXED_DEDUCTION] ?? 0) === 0) {
            $warnings[] = 'no_fixed_deduction_rules';
        }
        if (($kindCounts[PricingRuleKind::MULTIPLIER] ?? 0) === 0) {
            $warnings[] = 'no_condition_multiplier_rules';
        }

        $blocking = array_values(array_unique($blocking));
        sort($blocking, SORT_STRING);
        $warnings = array_values(array_unique($warnings));
        sort($warnings, SORT_STRING);

        return new PriceBookActivationReadinessReport(
            $book->id(),
            $book->versionNumber(),
            $book->currency(),
            $blocking === [],
            $blocking,
            $warnings,
            $enabledBaseCount,
            SupportedPriceConfiguration::fromEnabledBaseRules($validBaseRules),
            $enabledAdjustmentCount,
            ServiceMode::supportedCodes(),
            $at->setTimezone(new \DateTimeZone('UTC'))
        );
    }
}
