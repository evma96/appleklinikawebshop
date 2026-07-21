<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftBatteryBands;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Reconciles only one draft/model's battery-health bands. */
final class SaveDraftBatteryBandsHandler
{
    public const ACTION_NONE = 'none';
    public const ACTION_FIXED = 'fixed';
    public const ACTION_PERCENTAGE = 'percentage';
    public const ACTION_MANUAL_REVIEW = 'manual_review';
    public const ACTION_HARD_REJECT = 'hard_reject';

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly LocalDemoQuestionnaire $questionnaire,
        private readonly DeviceCatalogReader $catalog
    ) {
    }

    public function handle(SaveDraftBatteryBands $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();
            $this->assertSupportedModel($command->modelKey);

            $existing = $this->modelBatteryRules($this->rules->listForPriceBook($bookId), $command->modelKey);
            $submitted = $this->validatedSubmission($command->bands, $existing);
            $changed = false;
            $at = $this->clock->now();

            foreach ($submitted as $item) {
                $existingRule = $item['rule_id'] === null ? null : ($existing[$item['rule_id']] ?? null);
                if ($item['delete']) {
                    if ($existingRule === null || $existingRule->id() === null) {
                        throw new \InvalidArgumentException('Csak a kiválasztott modell meglévő akkumulátorsávja törölhető.');
                    }
                    $this->rules->deleteDraftRule($bookId, $existingRule->id(), $existingRule->version());
                    $changed = true;
                    continue;
                }
                if ($item['action'] === self::ACTION_NONE) {
                    if ($existingRule !== null) {
                        throw new \InvalidArgumentException('Meglévő akkumulátorsáv törléséhez használd a Törlés gombot.');
                    }
                    continue;
                }

                $definition = $this->definition($bookId->toInt(), $command->modelKey, $item);
                if ($existingRule === null) {
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('Az akkumulátorsávhoz tartozó szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
                    }
                    $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                    $changed = true;
                    continue;
                }
                if ($this->sameDefinition($existingRule->definition(), $definition)) {
                    continue;
                }
                if ($existingRule->definition()->code->code() !== $definition->code->code() && ! $this->rules->isCodeUnique($bookId, $definition->code)) {
                    throw new \InvalidArgumentException('Az akkumulátorsávhoz tartozó szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
                }
                $expectedRuleVersion = $existingRule->version();
                $existingRule->update($definition, $at);
                $this->rules->update($existingRule, $expectedRuleVersion);
                $changed = true;
            }

            if ($changed) {
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
            }
        });
    }

    public static function ruleCode(int $priceBookId, string $modelKey, int $minimum, int $maximum, string $action): string
    {
        return 'battery-band-' . $priceBookId . '-' . substr(hash('sha256', $modelKey . "\0" . $minimum . "\0" . $maximum . "\0" . $action), 0, 16);
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return [self::ACTION_NONE, self::ACTION_FIXED, self::ACTION_PERCENTAGE, self::ACTION_MANUAL_REVIEW, self::ACTION_HARD_REJECT];
    }

    /** @param list<PricingRule> $rules @return array<int,PricingRule> */
    private function modelBatteryRules(array $rules, string $modelKey): array
    {
        $matched = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->modelKey !== $modelKey
                || $definition->conditionKey !== 'battery_health'
                || $definition->operator?->code() !== ComparisonOperator::BETWEEN
                || ! in_array($definition->kind->code(), [PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MANUAL_REVIEW, PricingRuleKind::HARD_REJECT], true)
                || ! is_array($definition->comparisonValue)
                || count($definition->comparisonValue) !== 2
                || $rule->id() === null) {
                continue;
            }
            $matched[$rule->id()->toInt()] = $rule;
        }
        return $matched;
    }

    /**
     * @param list<array<string,mixed>> $raw
     * @param array<int,PricingRule> $existing
     * @return list<array{rule_id:?int,minimum:int,maximum:int,action:string,value:?int,delete:bool}>
     */
    private function validatedSubmission(array $raw, array $existing): array
    {
        $bounds = $this->batteryBounds();
        $validated = [];
        $seenRuleIds = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Érvénytelen akkumulátorsáv.');
            }
            $ruleId = $this->optionalRuleId($row['rule_id'] ?? null);
            if ($ruleId !== null) {
                if (! isset($existing[$ruleId]) || isset($seenRuleIds[$ruleId])) {
                    throw new \InvalidArgumentException('Ismeretlen vagy többször beküldött akkumulátorsáv.');
                }
                $seenRuleIds[$ruleId] = true;
            }
            $delete = ($row['delete'] ?? '') === '1';
            if ($delete) {
                $validated[] = ['rule_id' => $ruleId, 'minimum' => $bounds['min'], 'maximum' => $bounds['min'], 'action' => self::ACTION_NONE, 'value' => null, 'delete' => true];
                continue;
            }
            $minimum = $this->percentage($row['minimum'] ?? null, $bounds, 'minimum');
            $maximum = $this->percentage($row['maximum'] ?? null, $bounds, 'maximum');
            if ($minimum > $maximum) {
                throw new \InvalidArgumentException('Az akkumulátorsáv alsó határa nem lehet nagyobb a felső határnál.');
            }
            $action = $row['action'] ?? null;
            if (! is_string($action) || ! in_array($action, self::actions(), true)) {
                throw new \InvalidArgumentException('Érvénytelen akkumulátorsáv-művelet.');
            }
            $validated[] = [
                'rule_id' => $ruleId,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'action' => $action,
                'value' => $this->valueForAction($action, $row['value'] ?? null),
                'delete' => false,
            ];
        }

        $effective = [];
        foreach ($existing as $id => $rule) {
            if (! isset($seenRuleIds[$id])) {
                $effective[] = $this->existingRange($rule);
            }
        }
        foreach ($validated as $item) {
            if (! $item['delete'] && $item['action'] !== self::ACTION_NONE) {
                $effective[] = ['minimum' => $item['minimum'], 'maximum' => $item['maximum']];
            }
        }
        usort($effective, static fn (array $left, array $right): int => [$left['minimum'], $left['maximum']] <=> [$right['minimum'], $right['maximum']]);
        for ($index = 1, $count = count($effective); $index < $count; ++$index) {
            if ($effective[$index]['minimum'] <= $effective[$index - 1]['maximum']) {
                throw new \InvalidArgumentException('Az akkumulátorsávok nem fedhetik át egymást, és azonos sáv kétszer sem szerepelhet.');
            }
        }
        return $validated;
    }

    /** @return array{min:int,max:int} */
    private function batteryBounds(): array
    {
        $question = $this->questionnaire->questions()['battery_health'] ?? null;
        if (! is_array($question) || ($question['type'] ?? null) !== 'range' || ! isset($question['min'], $question['max'])) {
            throw new \RuntimeException('A nyilvános akkumulátor-kérdőív tartománya nem érhető el.');
        }
        return ['min' => (int) $question['min'], 'max' => (int) $question['max']];
    }

    /** @param array{min:int,max:int} $bounds */
    private function percentage(mixed $raw, array $bounds, string $boundary): int
    {
        if (! is_string($raw) && ! is_int($raw)) {
            throw new \InvalidArgumentException('Az akkumulátorsáv ' . $boundary . ' határa egész százalék legyen.');
        }
        $value = (string) $raw;
        if (preg_match('/^(0|[1-9][0-9]{0,2})$/', $value) !== 1) {
            throw new \InvalidArgumentException('Az akkumulátorsáv ' . $boundary . ' határa egész százalék legyen.');
        }
        $number = (int) $value;
        if ($number < $bounds['min'] || $number > $bounds['max']) {
            throw new \InvalidArgumentException('Az akkumulátorsáv határa ' . $bounds['min'] . ' és ' . $bounds['max'] . '% közötti érték legyen.');
        }
        return $number;
    }

    private function optionalRuleId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ((! is_string($raw) && ! is_int($raw)) || preg_match('/^[1-9][0-9]*$/', (string) $raw) !== 1) {
            throw new \InvalidArgumentException('Érvénytelen akkumulátorsáv-azonosító.');
        }
        return (int) $raw;
    }

    private function valueForAction(string $action, mixed $raw): ?int
    {
        if (in_array($action, [self::ACTION_NONE, self::ACTION_MANUAL_REVIEW, self::ACTION_HARD_REJECT], true)) {
            return null;
        }
        if (! is_string($raw) && ! is_int($raw)) {
            throw new \InvalidArgumentException('A levonás értéke nemnegatív egész szám legyen.');
        }
        $value = (string) $raw;
        if (preg_match('/^(0|[1-9][0-9]*)$/', $value) !== 1 || (int) $value > PHP_INT_MAX) {
            throw new \InvalidArgumentException('A levonás értéke nemnegatív egész szám legyen.');
        }
        $number = (int) $value;
        if ($action === self::ACTION_PERCENTAGE && $number > 100) {
            throw new \InvalidArgumentException('A százalékos levonás 0 és 100 közötti egész érték legyen.');
        }
        return $number;
    }

    /** @param array{rule_id:?int,minimum:int,maximum:int,action:string,value:?int,delete:bool} $item */
    private function definition(int $priceBookId, string $modelKey, array $item): PricingRuleDefinition
    {
        $kind = match ($item['action']) {
            self::ACTION_FIXED => PricingRuleKind::FIXED_DEDUCTION,
            self::ACTION_PERCENTAGE => PricingRuleKind::MULTIPLIER,
            self::ACTION_MANUAL_REVIEW => PricingRuleKind::MANUAL_REVIEW,
            self::ACTION_HARD_REJECT => PricingRuleKind::HARD_REJECT,
        };
        $amount = $item['action'] === self::ACTION_FIXED ? new Money($item['value'] ?? 0, 'HUF') : null;
        $multiplier = $item['action'] === self::ACTION_PERCENTAGE ? new BasisPointsMultiplier((100 - ($item['value'] ?? 0)) * 100) : null;
        $label = $item['minimum'] . '–' . $item['maximum'] . '%-os akkumulátor';

        return new PricingRuleDefinition(
            new PricingRuleCode(self::ruleCode($priceBookId, $modelKey, $item['minimum'], $item['maximum'], $item['action'])),
            new PricingRuleKind($kind),
            'iphone',
            $modelKey,
            null,
            null,
            'battery_health',
            new ComparisonOperator(ComparisonOperator::BETWEEN),
            [$item['minimum'], $item['maximum']],
            $amount,
            $multiplier,
            new RulePriority(4000 + $item['minimum']),
            true,
            in_array($kind, [PricingRuleKind::MANUAL_REVIEW, PricingRuleKind::HARD_REJECT], true) ? $label : null,
            null
        );
    }

    /** @return array{minimum:int,maximum:int} */
    private function existingRange(PricingRule $rule): array
    {
        $value = $rule->definition()->comparisonValue;
        return ['minimum' => (int) $value[0], 'maximum' => (int) $value[1]];
    }

    private function sameDefinition(PricingRuleDefinition $left, PricingRuleDefinition $right): bool
    {
        return $left->code->code() === $right->code->code()
            && $left->kind->code() === $right->kind->code()
            && $left->modelKey === $right->modelKey
            && $left->conditionKey === $right->conditionKey
            && $left->operator?->code() === $right->operator?->code()
            && $left->comparisonValue === $right->comparisonValue
            && $left->amount?->amount() === $right->amount?->amount()
            && $left->multiplier?->value() === $right->multiplier?->value()
            && $left->priority->value() === $right->priority->value()
            && $left->enabled === $right->enabled
            && $left->publicLabel === $right->publicLabel;
    }

    private function assertSupportedModel(string $modelKey): void
    {
        foreach ($this->catalog->iPhoneModels() as $model) {
            if ($model->modelKey === $modelKey) {
                return;
            }
        }
        throw new \InvalidArgumentException('A kiválasztott iPhone modell nem szerepel az inventory készülékkatalógusban.');
    }
}
