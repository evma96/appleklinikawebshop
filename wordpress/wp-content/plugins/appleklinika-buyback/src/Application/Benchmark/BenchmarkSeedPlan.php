<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Benchmark;

final class BenchmarkSeedPlan
{
    public function __construct(
        public readonly string $manifestHash,
        public readonly string $manifestVersion,
        public readonly string $seedKey,
        public readonly string $label,
        public readonly int $modelCount,
        public readonly int $configurationCount,
        public readonly int $basePriceRuleCount,
        public readonly int $conditionRuleCount,
        public readonly int $modeAdjustmentCount,
        public readonly int $manualReviewCount,
        public readonly int $hardRejectCount,
        public readonly int $totalRuleCount,
        public readonly ?int $existingPriceBookId
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'manifest_hash' => $this->manifestHash,
            'manifest_version' => $this->manifestVersion,
            'seed_key' => $this->seedKey,
            'label' => $this->label,
            'model_count' => $this->modelCount,
            'configuration_count' => $this->configurationCount,
            'base_price_rule_count' => $this->basePriceRuleCount,
            'condition_rule_count' => $this->conditionRuleCount,
            'mode_adjustment_count' => $this->modeAdjustmentCount,
            'manual_review_count' => $this->manualReviewCount,
            'hard_reject_count' => $this->hardRejectCount,
            'total_rule_count' => $this->totalRuleCount,
            'existing_price_book_id' => $this->existingPriceBookId,
            'planned_action' => $this->existingPriceBookId === null ? 'create_draft' : 'reuse_identical_draft',
        ];
    }
}
