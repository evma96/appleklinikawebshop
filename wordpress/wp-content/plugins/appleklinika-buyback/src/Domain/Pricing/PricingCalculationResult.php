<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class PricingCalculationResult
{
    /**
     * @param list<PricingBreakdownLine> $breakdown
     * @param list<PricingMatchedRule> $matchedRules
     * @param list<string> $reasonCodes
     */
    private function __construct(
        public readonly PricingOutcome $outcome,
        public readonly PriceBookId $priceBookId,
        public readonly PriceBookVersionNumber $priceBookVersion,
        public readonly ServiceMode $serviceMode,
        public readonly CurrencyCode $currency,
        public readonly ?Money $baseAmount,
        public readonly ?Money $amountAfterFixedDeductions,
        public readonly ?Money $amountAfterConditionMultipliers,
        public readonly ?Money $amountAfterModeAdjustment,
        public readonly ?Money $rawAmountBeforeMinimumAndRounding,
        public readonly ?Money $finalAmount,
        public readonly array $breakdown,
        public readonly array $matchedRules,
        public readonly array $reasonCodes,
        public readonly string $calculatorVersion
    ) {
    }

    /**
     * @param list<PricingBreakdownLine> $breakdown
     * @param list<PricingMatchedRule> $matchedRules
     */
    public static function offered(PriceBook $book, ServiceMode $mode, Money $base, Money $afterDeductions, Money $afterMultipliers, Money $afterMode, Money $raw, Money $final, array $breakdown, array $matchedRules, string $calculatorVersion): self
    {
        return new self(new PricingOutcome(PricingOutcome::OFFERED), $book->id(), $book->versionNumber(), $mode, $book->currency(), $base, $afterDeductions, $afterMultipliers, $afterMode, $raw, $final, $breakdown, $matchedRules, [], $calculatorVersion);
    }

    /** @param list<PricingMatchedRule> $matchedRules @param list<PricingBreakdownLine> $breakdown @param list<string> $reasons */
    public static function manualReview(PriceBook $book, ServiceMode $mode, array $reasons, array $matchedRules = [], array $breakdown = [], string $calculatorVersion = PricingEngine::VERSION): self
    {
        return self::nonOffer(PricingOutcome::MANUAL_REVIEW, $book, $mode, $reasons, $matchedRules, $breakdown, $calculatorVersion);
    }

    /** @param list<PricingMatchedRule> $matchedRules @param list<PricingBreakdownLine> $breakdown @param list<string> $reasons */
    public static function rejected(PriceBook $book, ServiceMode $mode, array $reasons, array $matchedRules = [], array $breakdown = [], string $calculatorVersion = PricingEngine::VERSION): self
    {
        return self::nonOffer(PricingOutcome::REJECTED, $book, $mode, $reasons, $matchedRules, $breakdown, $calculatorVersion);
    }

    /** @param list<string> $issues */
    public static function configurationError(PriceBook $book, ServiceMode $mode, array $issues, string $calculatorVersion = PricingEngine::VERSION): self
    {
        return self::nonOffer(PricingOutcome::CONFIGURATION_ERROR, $book, $mode, $issues, [], [], $calculatorVersion);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->code(),
            'price_book_id' => $this->priceBookId->toInt(),
            'price_book_version' => $this->priceBookVersion->value(),
            'service_mode' => $this->serviceMode->code(),
            'currency' => $this->currency->code(),
            'base_amount' => $this->baseAmount?->amount(),
            'after_deductions' => $this->amountAfterFixedDeductions?->amount(),
            'after_multipliers' => $this->amountAfterConditionMultipliers?->amount(),
            'after_mode' => $this->amountAfterModeAdjustment?->amount(),
            'raw_amount' => $this->rawAmountBeforeMinimumAndRounding?->amount(),
            'final_amount' => $this->finalAmount?->amount(),
            'breakdown' => array_map(static fn (PricingBreakdownLine $line): array => $line->toArray(), $this->breakdown),
            'matched_rules' => array_map(static fn (PricingMatchedRule $rule): array => $rule->toArray(), $this->matchedRules),
            'reason_codes' => $this->reasonCodes,
            'calculator_version' => $this->calculatorVersion,
        ];
    }

    /** @param list<string> $reasons @param list<PricingMatchedRule> $matchedRules @param list<PricingBreakdownLine> $breakdown */
    private static function nonOffer(string $outcome, PriceBook $book, ServiceMode $mode, array $reasons, array $matchedRules, array $breakdown, string $calculatorVersion): self
    {
        $reasons = array_values(array_unique($reasons));
        return new self(new PricingOutcome($outcome), $book->id(), $book->versionNumber(), $mode, $book->currency(), null, null, null, null, null, null, $breakdown, $matchedRules, $reasons, $calculatorVersion);
    }
}
