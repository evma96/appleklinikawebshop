<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftOfferModeModifiers;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
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

/** Reconciles only the four global offer-mode rules of one draft price book. */
final class SaveDraftOfferModeModifiersHandler
{
    public const TYPE_AMOUNT = 'amount';
    public const TYPE_MULTIPLIER = 'multiplier';

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(SaveDraftOfferModeModifiers $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();

            $submitted = $this->validatedSubmission($command->modifiers);
            $existing = $this->existingRules($this->rules->listForPriceBook($bookId));
            $changed = false;
            $at = $this->clock->now();

            foreach ($submitted as $mode => $item) {
                $rule = $existing[$mode] ?? null;
                if ($item['remove']) {
                    if ($rule !== null && $rule->id() !== null) {
                        $this->rules->deleteDraftRule($bookId, $rule->id(), $rule->version());
                        $changed = true;
                    }
                    continue;
                }
                $definition = $this->definition($bookId->toInt(), $mode, $item, $rule);
                if ($rule === null) {
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('Az ajánlattípushoz tartozó szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
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

    public static function ruleCode(int $priceBookId, string $mode): string
    {
        return 'offer-mode-' . $priceBookId . '-' . $mode;
    }

    /** @param list<PricingRule> $rules @return array<string,PricingRule> */
    private function existingRules(array $rules): array
    {
        $existing = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT || ! in_array($definition->serviceMode, OfferModeDefinition::keys(), true)) {
                continue;
            }
            if (isset($existing[$definition->serviceMode])) {
                throw new \RuntimeException('Egy ajánlattípushoz több szabály tartozik. A mentés biztonsági okból leállt.');
            }
            $existing[$definition->serviceMode] = $rule;
        }
        return $existing;
    }

    /** @param list<array<string,mixed>> $raw @return array<string,array{type:string,value:int,remove:bool}> */
    private function validatedSubmission(array $raw): array
    {
        $expected = array_fill_keys(OfferModeDefinition::keys(), true);
        $validated = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Érvénytelen ajánlattípus-beállítás.');
            }
            $mode = $row['mode'] ?? null;
            if (! is_string($mode) || ! isset($expected[$mode]) || isset($validated[$mode])) {
                throw new \InvalidArgumentException('Ismeretlen vagy többször beküldött ajánlattípus.');
            }
            $remove = ($row['remove'] ?? '') === '1' || ($row['value'] ?? null) === '';
            if ($remove) {
                $validated[$mode] = ['type' => self::TYPE_MULTIPLIER, 'value' => BasisPointsMultiplier::ONE, 'remove' => true];
                continue;
            }
            $type = $row['type'] ?? null;
            if (! is_string($type) || ! in_array($type, [self::TYPE_AMOUNT, self::TYPE_MULTIPLIER], true)) {
                throw new \InvalidArgumentException('Az ajánlattípus korrekciós típusa nem támogatott.');
            }
            $value = $this->value($type, $row['value'] ?? null);
            $validated[$mode] = [
                'type' => $type,
                'value' => $value,
                'remove' => $type === self::TYPE_AMOUNT ? $value === 0 : $value === BasisPointsMultiplier::ONE,
            ];
        }
        if (count($validated) !== count($expected) || array_diff_key($expected, $validated) !== []) {
            throw new \InvalidArgumentException('Mind a négy ajánlattípust pontosan egyszer kell elküldeni.');
        }
        return $validated;
    }

    private function value(string $type, mixed $raw): int
    {
        if (! is_string($raw) && ! is_int($raw)) {
            throw new \InvalidArgumentException('A korrekció értéke egész szám legyen.');
        }
        $value = (string) $raw;
        if ($type === self::TYPE_AMOUNT) {
            if (preg_match('/^[+-]?(?:0|[1-9][0-9]*)$/', $value) !== 1) {
                throw new \InvalidArgumentException('A fix korrekció értéke előjeles egész Ft legyen.');
            }
            $normalized = ltrim($value, '+');
            $number = (int) $normalized;
            if ($normalized !== '-0' && (string) $number !== $normalized) {
                throw new \InvalidArgumentException('A fix korrekció értéke kívül esik a támogatott egész tartományon.');
            }
            return $number;
        }
        if (preg_match('/^([+-]?)(0|[1-9][0-9]{0,2})(?:\.([0-9]{1,2}))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('A százalékos korrekció legfeljebb két tizedesjegyű előjeles szám legyen.');
        }
        $delta = ((int) $matches[2]) * 100 + (int) str_pad($matches[3] ?? '', 2, '0');
        if ($matches[1] === '-') {
            $delta *= -1;
        }
        $basisPoints = BasisPointsMultiplier::ONE + $delta;
        if ($basisPoints < 0 || $basisPoints > BasisPointsMultiplier::MAX) {
            throw new \InvalidArgumentException('A százalékos korrekció értéke -100% és +400% közé essen.');
        }
        return $basisPoints;
    }

    /** @param array{type:string,value:int,remove:bool} $item */
    private function definition(int $priceBookId, string $mode, array $item, ?PricingRule $existing): PricingRuleDefinition
    {
        return new PricingRuleDefinition(
            $existing?->definition()->code ?? new PricingRuleCode(self::ruleCode($priceBookId, $mode)),
            new PricingRuleKind(PricingRuleKind::MODE_ADJUSTMENT),
            'iphone',
            null,
            null,
            $mode,
            null,
            null,
            null,
            $item['type'] === self::TYPE_AMOUNT ? new Money($item['value'], 'HUF') : null,
            $item['type'] === self::TYPE_MULTIPLIER ? new BasisPointsMultiplier($item['value']) : null,
            new RulePriority(6000 + array_search($mode, OfferModeDefinition::keys(), true)),
            true,
            null,
            null
        );
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
