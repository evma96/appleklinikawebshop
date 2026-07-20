<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftBasePriceMatrix;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogConfiguration;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Atomically reconciles a draft's base-price rules with its displayed matrix. */
final class SaveDraftBasePriceMatrixHandler
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(SaveDraftBasePriceMatrix $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();

            $configurations = $this->configurationMap();
            $amounts = $this->validatedAmounts($command->basePrices, $configurations);
            $existing = $this->existingBaseRules($this->rules->listForPriceBook($bookId), $configurations);
            $changed = false;
            $at = $this->clock->now();

            foreach ($configurations as $key => $configuration) {
                $amount = $amounts[$key];
                $rule = $existing[$key] ?? null;
                if ($amount === null) {
                    if ($rule !== null && $rule->id() !== null) {
                        $this->rules->deleteDraftRule($bookId, $rule->id(), $rule->version());
                        $changed = true;
                    }
                    continue;
                }

                if ($rule === null) {
                    $definition = $this->newDefinition($configuration, $amount);
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('Az alapár-szabály kódja már foglalt. Frissítsd az oldalt és próbáld újra.');
                    }
                    $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                    $changed = true;
                    continue;
                }

                if ($rule->definition()->amount?->amount() === $amount) {
                    continue;
                }

                $definition = $rule->definition();
                $expectedRuleVersion = $rule->version();
                $rule->update(new PricingRuleDefinition(
                    $definition->code,
                    $definition->kind,
                    $definition->category,
                    $definition->modelKey,
                    $definition->storage,
                    $definition->serviceMode,
                    $definition->conditionKey,
                    $definition->operator,
                    $definition->comparisonValue,
                    new Money($amount, 'HUF'),
                    $definition->multiplier,
                    $definition->priority,
                    $definition->enabled,
                    $definition->publicLabel,
                    $definition->internalNote
                ), $at);
                $this->rules->update($rule, $expectedRuleVersion);
                $changed = true;
            }

            if ($changed) {
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
            }
        });
    }

    /** @return array<string,DeviceCatalogConfiguration> */
    private function configurationMap(): array
    {
        try {
            $configurations = $this->catalog->iPhoneConfigurations();
        } catch (DeviceCatalogUnavailableException $exception) {
            throw new \RuntimeException('Az Apple Klinika készülékkatalógus nem érhető el; az alapárak nem menthetők.', 0, $exception);
        }

        $map = [];
        foreach ($configurations as $configuration) {
            $map[$this->key($configuration->modelKey, $configuration->storageGb)] = $configuration;
        }
        if ($map === []) {
            throw new \RuntimeException('Az inventory katalógusban nincs árazható iPhone modell–tárhely konfiguráció.');
        }
        return $map;
    }

    /** @param array<string,array<string,mixed>> $posted @param array<string,DeviceCatalogConfiguration> $configurations @return array<string,?int> */
    private function validatedAmounts(array $posted, array $configurations): array
    {
        $amounts = array_fill_keys(array_keys($configurations), null);
        foreach ($posted as $modelKey => $storages) {
            if (! is_array($storages)) {
                throw new \InvalidArgumentException('Érvénytelen alapár-mátrix beküldés.');
            }
            foreach ($storages as $storage => $rawAmount) {
                $key = $this->key((string) $modelKey, (int) $storage);
                if (! array_key_exists($key, $configurations)) {
                    throw new \InvalidArgumentException('Az inventory katalógusban nem létező modell–tárhely pár nem menthető.');
                }
                $amounts[$key] = $this->amount($rawAmount);
            }
        }
        return $amounts;
    }

    /** @param list<PricingRule> $rules @param array<string,DeviceCatalogConfiguration> $configurations @return array<string,PricingRule> */
    private function existingBaseRules(array $rules, array $configurations): array
    {
        $baseRules = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::BASE_PRICE || $definition->modelKey === null || $definition->storage === null) {
                continue;
            }
            $key = $this->key($definition->modelKey, $definition->storage->gigabytes());
            if (! isset($configurations[$key])) {
                continue;
            }
            if (isset($baseRules[$key])) {
                throw new \RuntimeException('Egy modell–tárhely párhoz több alapár-szabály tartozik. A mátrix mentése biztonsági okból leállt.');
            }
            $baseRules[$key] = $rule;
        }
        return $baseRules;
    }

    private function newDefinition(DeviceCatalogConfiguration $configuration, int $amount): PricingRuleDefinition
    {
        return new PricingRuleDefinition(
            new PricingRuleCode('base-price-' . substr(hash('sha256', $configuration->modelKey), 0, 16) . '-' . $configuration->storageGb),
            new PricingRuleKind(PricingRuleKind::BASE_PRICE),
            'iphone',
            $configuration->modelKey,
            new StorageCapacity($configuration->storageGb),
            null,
            null,
            null,
            null,
            new Money($amount, 'HUF'),
            null,
            new RulePriority(100),
            true,
            $configuration->modelLabel . ' ' . $this->storageLabel($configuration->storageGb),
            null
        );
    }

    private function amount(mixed $raw): ?int
    {
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (! is_string($raw) && ! is_int($raw)) {
            throw new \InvalidArgumentException('Az alapár egész forint összeg legyen.');
        }
        $value = (string) $raw;
        if (preg_match('/^(0|[1-9][0-9]*)$/', $value) !== 1 || (int) $value > PHP_INT_MAX) {
            throw new \InvalidArgumentException('Az alapár nemnegatív, egész forint összeg legyen.');
        }
        return (int) $value;
    }

    private function key(string $modelKey, int $storageGb): string { return $modelKey . ':' . $storageGb; }
    private function storageLabel(int $storageGb): string { return $storageGb % 1024 === 0 ? ($storageGb / 1024) . ' TB' : $storageGb . ' GB'; }
}
