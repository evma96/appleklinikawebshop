<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

interface PricingRuleRepository
{
    public function insert(PricingRule $rule): PricingRule;

    public function getById(PricingRuleId $id): ?PricingRule;

    /** @return list<PricingRule> */
    public function listForPriceBook(PriceBookId $priceBookId): array;

    public function update(PricingRule $rule, AggregateVersion $expectedVersion): void;

    public function deleteDraftRule(PriceBookId $priceBookId, PricingRuleId $ruleId, AggregateVersion $expectedVersion): void;

    public function isCodeUnique(PriceBookId $priceBookId, PricingRuleCode $code, ?PricingRuleId $except = null): bool;

    public function countForPriceBook(PriceBookId $priceBookId): int;
}
