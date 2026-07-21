<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Calculates non-persisted examples through the same PricingEngine as offers. */
final class OfferModeExampleCalculator
{
    public function __construct(
        private readonly PricingEngine $engine,
        private readonly LocalDemoQuestionnaire $questionnaire = new LocalDemoQuestionnaire()
    ) {
    }

    /** @return array<int, int> corrected value => final value */
    public function examples(string $mode, ?PricingRuleDefinition $modifier): array
    {
        return [
            50000 => $this->calculate(50000, $mode, $modifier),
            300000 => $this->calculate(300000, $mode, $modifier),
        ];
    }

    private function calculate(int $baseAmount, string $mode, ?PricingRuleDefinition $modifier): int
    {
        $at = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $bookId = new PriceBookId(900001);
        $book = PriceBook::reconstitute(
            $bookId,
            new PriceBookVersionNumber(1),
            'Offer-mode example',
            new PriceBookStatus(PriceBookStatus::DRAFT),
            new CurrencyCode('HUF'),
            new Money(0, 'HUF'),
            1,
            new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW),
            new PricingActorId(1),
            new AggregateVersion(0),
            $at,
            $at
        );
        $rules = [new PricingRuleDefinition(
            new PricingRuleCode('offer-example-base-' . $baseAmount),
            new PricingRuleKind(PricingRuleKind::BASE_PRICE),
            DeviceCategory::IPHONE,
            'offer_example_iphone',
            new StorageCapacity(128),
            null,
            null,
            null,
            null,
            new Money($baseAmount, 'HUF'),
            null,
            new RulePriority(10),
            true,
            null,
            null
        )];
        if ($modifier !== null) {
            $rules[] = $modifier;
        }
        $pricingRules = array_map(
            static fn (PricingRuleDefinition $definition, int $index): PricingRule => PricingRule::reconstitute(
                new PricingRuleId($index + 1),
                $bookId,
                $definition,
                new AggregateVersion(0),
                $at,
                $at
            ),
            $rules,
            array_keys($rules)
        );
        $result = $this->engine->calculate(
            $book,
            $pricingRules,
            new PricingCalculationInput(
                new DeviceCategory(DeviceCategory::IPHONE),
                new PricingModelKey('offer_example_iphone'),
                new StorageCapacity(128),
                ConditionAnswerCollection::fromAssociative($this->questionnaire->mapToConditions($this->questionnaire->defaults())),
                new ServiceMode($mode)
            )
        );

        return $result->finalAmount?->amount() ?? 0;
    }
}
