<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessEvaluator;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessReport;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;

final class PriceBookActivationReadinessService
{
    public function __construct(private readonly DeviceCatalogReader $catalog, private readonly PriceBookActivationReadinessEvaluator $evaluator)
    {
    }

    /** @param list<PricingRule> $rules */
    public function evaluate(PriceBook $book, array $rules, \DateTimeImmutable $at): PriceBookActivationReadinessReport
    {
        try {
            $modelKeys = array_map(static fn (DeviceCatalogItem $item): string => $item->modelKey, $this->catalog->iPhoneModels());
            $configurationKeys = [];
            foreach ($this->catalog->iPhoneConfigurations() as $configuration) {
                $configurationKeys[$configuration->modelKey . '|' . $configuration->storageGb] = true;
            }
            return $this->evaluator->evaluate($book, $rules, $modelKeys, $configurationKeys, $at);
        } catch (DeviceCatalogUnavailableException $exception) {
            $report = $this->evaluator->evaluate($book, $rules, [], [], $at);
            $issues = array_values(array_unique([...$report->blockingIssues, 'catalog_unavailable']));
            sort($issues, SORT_STRING);
            return new PriceBookActivationReadinessReport(
                $report->priceBookId,
                $report->versionNumber,
                $report->currency,
                false,
                $issues,
                $report->warnings,
                $report->enabledBasePriceCount,
                $report->supportedConfigurations,
                $report->enabledAdjustmentCount,
                $report->supportedServiceModes,
                $report->validatedAt
            );
        }
    }
}
