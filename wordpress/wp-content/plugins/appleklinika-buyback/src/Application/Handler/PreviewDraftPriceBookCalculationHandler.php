<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Pricing\DraftPriceBookPreview;
use AppleKlinika\Buyback\Application\Query\PreviewDraftPriceBookCalculation;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;

final class PreviewDraftPriceBookCalculationHandler
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly PricingEngine $engine
    ) {
    }

    public function handle(PreviewDraftPriceBookCalculation $query): DraftPriceBookPreview
    {
        $bookId = new PriceBookId($query->priceBookId);
        $book = $this->books->getById($bookId);
        if ($book === null) {
            throw PriceBookNotFoundException::forId($bookId);
        }
        $book->assertDraftMutation();

        $model = new PricingModelKey($query->modelKey);
        $available = false;
        foreach ($this->catalog->iPhoneModels() as $item) {
            if ($item->modelKey === $model->value()) {
                $available = true;
                break;
            }
        }
        if (! $available) {
            throw new DeviceCatalogUnavailableException('A kiválasztott iPhone modell nem érhető el a katalógusban.');
        }

        $answers = ConditionAnswerCollection::fromAssociative($query->conditionAnswers);
        $storage = new StorageCapacity($query->storageGb);
        $rules = $this->rules->listForPriceBook($bookId);
        $results = [];
        foreach (ServiceMode::supportedCodes() as $modeCode) {
            $input = new PricingCalculationInput(new DeviceCategory(DeviceCategory::IPHONE), $model, $storage, $answers, new ServiceMode($modeCode));
            $results[$modeCode] = $this->engine->calculate($book, $rules, $input);
        }

        if (count($results) !== 4) {
            throw new InvalidValueObjectException('Preview must produce exactly four service-mode results.');
        }

        return new DraftPriceBookPreview($book, $model->value(), $storage->gigabytes(), $results);
    }
}
