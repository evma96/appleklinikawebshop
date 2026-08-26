<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftModelOfferModeOverrides;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Saves only explicit model/mode overrides; inherited defaults intentionally have no rule. */
final class SaveDraftModelOfferModeOverridesHandler
{
    public const SETTING_INHERIT = 'inherit';
    public const SETTING_CUSTOM = 'custom';

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(SaveDraftModelOfferModeOverrides $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();
            $this->assertKnownModel($command->modelKey);

            $submitted = $this->validatedSubmission($command->overrides);
            $existing = $this->existingRules($bookId, $command->modelKey);
            $changed = false;
            $at = $this->clock->now();

            foreach ($submitted as $mode => $item) {
                $rule = $existing[$mode] ?? null;
                if ($item['inherit']) {
                    if ($rule !== null && $rule->id() !== null) {
                        $this->rules->deleteDraftRule($bookId, $rule->id(), $rule->version());
                        $changed = true;
                    }
                    continue;
                }

                $definition = $this->definition($command->modelKey, $mode, $item, $rule);
                if ($rule === null) {
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('A modellhez és ajánlattípushoz tartozó szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
                    }
                    $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                    $changed = true;
                    continue;
                }
                if ($this->sameDefinition($rule->definition(), $definition)) {
                    continue;
                }
                $expectedRuleVersion = $rule->version();
                $rule->update($definition, $at);
                $this->rules->update($rule, $expectedRuleVersion);
                $changed = true;
            }

            if ($changed) {
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
            }
        });
    }

    public static function ruleCode(string $modelKey, string $mode): string
    {
        return 'model-offer-mode-' . substr(hash('sha256', $modelKey . '|' . $mode), 0, 16);
    }

    /** @param list<array<string,mixed>> $raw @return array<string,array{type:string,value:int,inherit:bool}> */
    private function validatedSubmission(array $raw): array
    {
        $expected = array_fill_keys(OfferModeDefinition::keys(), true);
        $validated = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Érvénytelen modellenkénti ajánlattípus-beállítás.');
            }
            $mode = $row['mode'] ?? null;
            if (! is_string($mode) || ! isset($expected[$mode]) || isset($validated[$mode])) {
                throw new \InvalidArgumentException('Ismeretlen vagy többször beküldött ajánlattípus.');
            }
            $setting = $row['setting'] ?? self::SETTING_INHERIT;
            if (! is_string($setting) || ! in_array($setting, [self::SETTING_INHERIT, self::SETTING_CUSTOM], true)) {
                throw new \InvalidArgumentException('A modellenkénti beállítás módja nem támogatott.');
            }
            if ($setting === self::SETTING_INHERIT) {
                $validated[$mode] = ['type' => SaveDraftOfferModeModifiersHandler::TYPE_MULTIPLIER, 'value' => BasisPointsMultiplier::ONE, 'inherit' => true];
                continue;
            }
            $type = $row['type'] ?? null;
            if (! is_string($type) || ! in_array($type, [SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT, SaveDraftOfferModeModifiersHandler::TYPE_MULTIPLIER], true)) {
                throw new \InvalidArgumentException('Az ajánlattípus korrekciós típusa nem támogatott.');
            }
            $validated[$mode] = [
                'type' => $type,
                'value' => SaveDraftOfferModeModifiersHandler::parseValue($type, $row['value'] ?? null),
                'inherit' => false,
            ];
        }
        if (count($validated) !== count($expected) || array_diff_key($expected, $validated) !== []) {
            throw new \InvalidArgumentException('Mind a négy ajánlattípust pontosan egyszer kell elküldeni.');
        }
        return $validated;
    }

    /** @return array<string,PricingRule> */
    private function existingRules(PriceBookId $bookId, string $modelKey): array
    {
        $existing = [];
        foreach ($this->rules->listForPriceBook($bookId) as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT || $definition->modelKey !== $modelKey || ! in_array($definition->serviceMode, OfferModeDefinition::keys(), true)) {
                continue;
            }
            if (isset($existing[$definition->serviceMode])) {
                throw new \RuntimeException('Ehhez a modellhez és ajánlattípushoz több szabály tartozik. A mentés biztonsági okból leállt.');
            }
            $existing[$definition->serviceMode] = $rule;
        }
        return $existing;
    }

    /** @param array{type:string,value:int,inherit:bool} $item */
    private function definition(string $modelKey, string $mode, array $item, ?PricingRule $existing): PricingRuleDefinition
    {
        return new PricingRuleDefinition(
            $existing?->definition()->code ?? new PricingRuleCode(self::ruleCode($modelKey, $mode)),
            new PricingRuleKind(PricingRuleKind::MODE_ADJUSTMENT),
            'iphone',
            $modelKey,
            null,
            $mode,
            null,
            null,
            null,
            $item['type'] === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? new Money($item['value'], 'HUF') : null,
            $item['type'] === SaveDraftOfferModeModifiersHandler::TYPE_MULTIPLIER ? new BasisPointsMultiplier($item['value']) : null,
            new RulePriority(6100 + array_search($mode, OfferModeDefinition::keys(), true)),
            true,
            null,
            'Model-specific offer-mode adjustment override'
        );
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
            throw new \RuntimeException('Az Apple Klinika készülékkatalógus nem érhető el; a modellenkénti korrekció nem menthető.', 0, $exception);
        }
        throw new \InvalidArgumentException('Az inventory katalógusban nem szereplő iPhone modellhez nem menthető saját ajánlattípus-korrekció.');
    }

    private function sameDefinition(PricingRuleDefinition $left, PricingRuleDefinition $right): bool
    {
        return $left->kind->code() === $right->kind->code()
            && $left->modelKey === $right->modelKey
            && $left->serviceMode === $right->serviceMode
            && $left->amount?->amount() === $right->amount?->amount()
            && $left->multiplier?->value() === $right->multiplier?->value()
            && $left->enabled === $right->enabled;
    }
}
