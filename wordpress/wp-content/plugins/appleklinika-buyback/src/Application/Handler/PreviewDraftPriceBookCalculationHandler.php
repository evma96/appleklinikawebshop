<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
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
        private readonly PricingEngine $engine,
        private readonly LocalDemoQuestionnaire $questionnaire = new LocalDemoQuestionnaire()
    ) {
    }

    public function handle(PreviewDraftPriceBookCalculation $query): DraftPriceBookPreview
    {
        $bookId = new PriceBookId($query->priceBookId);
        $book = $this->books->getById($bookId);
        if ($book === null) {
            throw PriceBookNotFoundException::forId($bookId);
        }
        $model = new PricingModelKey($query->modelKey);
        $storage = new StorageCapacity($query->storageGb);
        $available = false;
        foreach ($this->catalog->iPhoneConfigurations() as $configuration) {
            if ($configuration->modelKey === $model->value() && $configuration->storageGb === $storage->gigabytes()) {
                $available = true;
                break;
            }
        }
        if (! $available) {
            throw new DeviceCatalogUnavailableException('A kiválasztott iPhone modell nem érhető el a katalógusban.');
        }

        $questions = $this->questionnaire->questions();
        if (array_diff(array_keys($query->questionnaireState), array_keys($questions)) !== []) {
            throw new \InvalidArgumentException('Ismeretlen kérdőívmező.');
        }
        $validationErrors = $this->questionnaire->validate($query->questionnaireState);
        if ($validationErrors !== []) {
            throw new \InvalidArgumentException(implode(' ', array_values($validationErrors)));
        }
        $state = $this->questionnaire->sanitize($query->questionnaireState);
        $catalog = $this->catalog->iPhoneCatalog();
        if ($query->colorKey !== '' && ! isset($catalog[$model->value()]['colors'][$query->colorKey])) {
            throw new \InvalidArgumentException('A kiválasztott szín nem tartozik az iPhone modellhez.');
        }
        $answers = ConditionAnswerCollection::fromAssociative($this->questionnaire->mapToConditions($state));
        $rules = $this->rules->listForPriceBook($bookId);
        $results = [];
        $eligibilityError = $this->questionnaire->eligibilityError($state);
        $manualReasons = $this->questionnaire->manualReviewReasons($state);
        foreach (ServiceMode::supportedCodes() as $modeCode) {
            $input = new PricingCalculationInput(new DeviceCategory(DeviceCategory::IPHONE), $model, $storage, $answers, new ServiceMode($modeCode), $this->questionnaire->affectedPartKeys($state));
            $results[$modeCode] = $eligibilityError !== null
                ? \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult::rejected($book, $input->serviceMode, [$eligibilityError])
                : ($manualReasons !== []
                    ? \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult::manualReview($book, $input->serviceMode, $manualReasons)
                    : $this->engine->calculate($book, $rules, $input));
        }

        if (count($results) !== 4) {
            throw new InvalidValueObjectException('Preview must produce exactly four service-mode results.');
        }

        return new DraftPriceBookPreview($book, $model->value(), $storage->gigabytes(), $results, $state, $query->colorKey);
    }
}
