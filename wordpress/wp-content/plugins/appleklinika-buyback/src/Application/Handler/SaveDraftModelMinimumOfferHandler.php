<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftModelMinimumOffer;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Saves one explicit model threshold without copying the price-book default. */
final class SaveDraftModelMinimumOfferHandler
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(SaveDraftModelMinimumOffer $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();
            $this->assertKnownModel($command->modelKey);
            if ($command->amountMinor !== null && $command->amountMinor < 0) {
                throw new \InvalidArgumentException('A saját minimum nem lehet negatív.');
            }

            $existing = $this->existingRule($bookId, $command->modelKey);
            $at = $this->clock->now();
            $changed = false;

            if ($command->amountMinor === null) {
                if ($existing !== null && $existing->id() !== null) {
                    $this->rules->deleteDraftRule($bookId, $existing->id(), $existing->version());
                    $changed = true;
                }
            } elseif ($existing === null) {
                $definition = $this->newDefinition($command->modelKey, $command->amountMinor);
                if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                    throw new \InvalidArgumentException('A modell minimumszabálya már létezik. Frissítsd az oldalt és próbáld újra.');
                }
                $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                $changed = true;
            } elseif ($existing->definition()->amount?->amount() !== $command->amountMinor || ! $existing->definition()->enabled) {
                $definition = $existing->definition();
                $expectedRuleVersion = $existing->version();
                $existing->update(new PricingRuleDefinition(
                    $definition->code,
                    $definition->kind,
                    $definition->category,
                    $definition->modelKey,
                    $definition->storage,
                    $definition->serviceMode,
                    $definition->conditionKey,
                    $definition->operator,
                    $definition->comparisonValue,
                    new Money($command->amountMinor, 'HUF'),
                    $definition->multiplier,
                    $definition->priority,
                    true,
                    $definition->publicLabel,
                    $definition->internalNote,
                    $definition->affectedComponentKey
                ), $at);
                $this->rules->update($existing, $expectedRuleVersion);
                $changed = true;
            }

            if ($changed) {
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
            }
        });
    }

    public static function ruleCode(string $modelKey): string
    {
        return 'model-minimum-' . substr(hash('sha256', $modelKey), 0, 16);
    }

    private function assertKnownModel(string $modelKey): void
    {
        try {
            foreach ($this->catalog->iPhoneModels() as $model) {
                if ($model->modelKey === $modelKey) {
                    return;
                }
            }
        } catch (DeviceCatalogUnavailableException $exception) {
            throw new \RuntimeException('Az Apple Klinika készülékkatalógus nem érhető el; a modellminimum nem menthető.', 0, $exception);
        }
        throw new \InvalidArgumentException('Az inventory katalógusban nem szereplő iPhone modellhez nem menthető saját minimum.');
    }

    private function existingRule(PriceBookId $bookId, string $modelKey): ?PricingRule
    {
        $matches = array_values(array_filter(
            $this->rules->listForPriceBook($bookId),
            static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MINIMUM_OFFER
                && $rule->definition()->modelKey === $modelKey
        ));
        if (count($matches) > 1) {
            throw new \RuntimeException('Ehhez a modellhez több minimumszabály tartozik. A mentés biztonsági okból leállt.');
        }
        return $matches[0] ?? null;
    }

    private function newDefinition(string $modelKey, int $amount): PricingRuleDefinition
    {
        return new PricingRuleDefinition(
            new PricingRuleCode(self::ruleCode($modelKey)),
            new PricingRuleKind(PricingRuleKind::MINIMUM_OFFER),
            'iphone',
            $modelKey,
            null,
            null,
            null,
            null,
            null,
            new Money($amount, 'HUF'),
            null,
            new RulePriority(90),
            true,
            null,
            'Model-specific automatic-offer minimum'
        );
    }
}
