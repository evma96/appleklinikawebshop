<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Exception\DuplicatePricingRuleCodeException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Exception\PricingRuleNotFoundException;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressPricingRuleRepository implements PricingRuleRepository
{
    private readonly string $rulesTable;
    private readonly string $booksTable;

    public function __construct(private readonly \wpdb $database)
    {
        $tables = Schema::tableNames($database);
        $this->rulesTable = $tables[Schema::PRICE_RULES];
        $this->booksTable = $tables[Schema::PRICE_BOOKS];
    }

    public function insert(PricingRule $rule): PricingRule
    {
        $this->assertDraftBook($rule->priceBookId());
        if ($rule->id() !== null) {
            throw new PersistenceException('A persisted pricing rule cannot be inserted again.');
        }
        if (! $this->isCodeUnique($rule->priceBookId(), $rule->definition()->code)) {
            throw new DuplicatePricingRuleCodeException('Pricing rule code already exists in this price book.');
        }

        [$values, $formats] = $this->persistenceValues($rule);
        $values = ['price_book_id' => $rule->priceBookId()->toInt()] + $values;
        array_unshift($formats, '%d');
        $result = $this->database->insert($this->rulesTable, $values, $formats);

        if ($result !== 1) {
            if (! $this->isCodeUnique($rule->priceBookId(), $rule->definition()->code)) {
                throw new DuplicatePricingRuleCodeException('Pricing rule code already exists in this price book.');
            }
            throw new PersistenceException('Could not insert the pricing rule.');
        }

        $persisted = $this->getById(new PricingRuleId((int) $this->database->insert_id));
        if ($persisted === null) {
            throw new PersistenceException('Inserted pricing rule could not be reloaded.');
        }
        return $persisted;
    }

    public function getById(PricingRuleId $id): ?PricingRule
    {
        $row = $this->database->get_row($this->database->prepare(
            "SELECT * FROM `{$this->rulesTable}` WHERE id = %d LIMIT 1",
            $id->toInt()
        ), ARRAY_A);
        return is_array($row) ? $this->toDomain($row) : null;
    }

    public function listForPriceBook(PriceBookId $priceBookId): array
    {
        $rows = $this->database->get_results($this->database->prepare(
            "SELECT * FROM `{$this->rulesTable}` WHERE price_book_id = %d ORDER BY priority ASC, id ASC",
            $priceBookId->toInt()
        ), ARRAY_A);
        if (! is_array($rows)) {
            throw new PersistenceException('Could not list pricing rules.');
        }
        return array_map(fn (array $row): PricingRule => $this->toDomain($row), $rows);
    }

    public function update(PricingRule $rule, AggregateVersion $expectedVersion): void
    {
        $this->assertDraftBook($rule->priceBookId());
        if ($rule->id() === null || $rule->version()->value() !== $expectedVersion->value() + 1) {
            throw new PersistenceException('Pricing-rule update requires one accepted mutation.');
        }
        if (! $this->isCodeUnique($rule->priceBookId(), $rule->definition()->code, $rule->id())) {
            throw new DuplicatePricingRuleCodeException('Pricing rule code already exists in this price book.');
        }

        [$values, $formats] = $this->persistenceValues($rule);
        $result = $this->database->update(
            $this->rulesTable,
            $values,
            ['id' => $rule->id()->toInt(), 'price_book_id' => $rule->priceBookId()->toInt(), 'version' => $expectedVersion->value()],
            $formats,
            ['%d', '%d', '%d']
        );
        if ($result === false) {
            throw new PersistenceException('Could not update the pricing rule.');
        }
        if ($result === 1) {
            return;
        }
        $this->throwMissingOrStale($rule->id(), $rule->priceBookId(), $expectedVersion);
    }

    public function deleteDraftRule(PriceBookId $priceBookId, PricingRuleId $ruleId, AggregateVersion $expectedVersion): void
    {
        $this->assertDraftBook($priceBookId);
        $result = $this->database->delete($this->rulesTable, [
            'id' => $ruleId->toInt(),
            'price_book_id' => $priceBookId->toInt(),
            'version' => $expectedVersion->value(),
        ], ['%d', '%d', '%d']);
        if ($result === false) {
            throw new PersistenceException('Could not delete the pricing rule.');
        }
        if ($result === 1) {
            return;
        }
        $this->throwMissingOrStale($ruleId, $priceBookId, $expectedVersion);
    }

    public function isCodeUnique(PriceBookId $priceBookId, PricingRuleCode $code, ?PricingRuleId $except = null): bool
    {
        if ($except === null) {
            $sql = $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->rulesTable}` WHERE price_book_id = %d AND rule_code = %s",
                $priceBookId->toInt(),
                $code->code()
            );
        } else {
            $sql = $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->rulesTable}` WHERE price_book_id = %d AND rule_code = %s AND id <> %d",
                $priceBookId->toInt(),
                $code->code(),
                $except->toInt()
            );
        }
        return (int) $this->database->get_var($sql) === 0;
    }

    public function countForPriceBook(PriceBookId $priceBookId): int
    {
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->rulesTable}` WHERE price_book_id = %d",
            $priceBookId->toInt()
        ));
    }

    private function assertDraftBook(PriceBookId $id): void
    {
        $status = $this->database->get_var($this->database->prepare(
            "SELECT status FROM `{$this->booksTable}` WHERE id = %d LIMIT 1",
            $id->toInt()
        ));
        if ($status === null) {
            throw PriceBookNotFoundException::forId($id);
        }
        (new \AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus((string) $status))->isDraft()
            ?: throw new \AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException('Only draft price-book rules may be modified.');
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function persistenceValues(PricingRule $rule): array
    {
        $definition = $rule->definition();
        $comparisonJson = $definition->comparisonValue === null ? null : wp_json_encode($definition->comparisonValue, JSON_THROW_ON_ERROR);
        return [[
            'rule_code' => $definition->code->code(),
            'rule_kind' => $definition->kind->code(),
            'category' => $definition->category,
            'model_key' => $definition->modelKey,
            'storage_gb' => $definition->storage?->gigabytes(),
            'service_mode' => $definition->serviceMode,
            'condition_key' => $definition->conditionKey,
            'comparison_operator' => $definition->operator?->code(),
            'comparison_value_json' => $comparisonJson,
            'amount_minor' => $definition->amount?->amount(),
            'multiplier_bps' => $definition->multiplier?->value(),
            'priority' => $definition->priority->value(),
            'is_enabled' => $definition->enabled ? 1 : 0,
            'public_label' => $definition->publicLabel,
            'internal_note' => $definition->internalNote,
            'version' => $rule->version()->value(),
            'created_at' => $this->date($rule->createdAt()),
            'updated_at' => $this->date($rule->updatedAt()),
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']];
    }

    /** @param array<string, mixed> $row */
    private function toDomain(array $row): PricingRule
    {
        $comparison = $row['comparison_value_json'] === null ? null : json_decode((string) $row['comparison_value_json'], true, 512, JSON_THROW_ON_ERROR);
        return PricingRule::reconstitute(
            new PricingRuleId((int) $row['id']),
            new PriceBookId((int) $row['price_book_id']),
            new PricingRuleDefinition(
                new PricingRuleCode((string) $row['rule_code']),
                new PricingRuleKind((string) $row['rule_kind']),
                (string) $row['category'],
                $row['model_key'] === null ? null : (string) $row['model_key'],
                $row['storage_gb'] === null ? null : new StorageCapacity((int) $row['storage_gb']),
                $row['service_mode'] === null ? null : (string) $row['service_mode'],
                $row['condition_key'] === null ? null : (string) $row['condition_key'],
                $row['comparison_operator'] === null ? null : new ComparisonOperator((string) $row['comparison_operator']),
                $comparison,
                $row['amount_minor'] === null ? null : new Money((int) $row['amount_minor'], 'HUF'),
                $row['multiplier_bps'] === null ? null : new BasisPointsMultiplier((int) $row['multiplier_bps']),
                new RulePriority((int) $row['priority']),
                (bool) $row['is_enabled'],
                $row['public_label'] === null ? null : (string) $row['public_label'],
                $row['internal_note'] === null ? null : (string) $row['internal_note']
            ),
            new AggregateVersion((int) $row['version']),
            $this->parseDate((string) $row['created_at']),
            $this->parseDate((string) $row['updated_at'])
        );
    }

    private function throwMissingOrStale(PricingRuleId $ruleId, PriceBookId $bookId, AggregateVersion $expected): never
    {
        $current = $this->getById($ruleId);
        if ($current === null || ! $current->priceBookId()->equals($bookId)) {
            throw PricingRuleNotFoundException::forId($ruleId);
        }
        throw new StaleAggregateVersionException($expected->value(), $current->version()->value());
    }

    private function date(\DateTimeImmutable $date): string { return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function parseDate(string $date): \DateTimeImmutable { return new \DateTimeImmutable($date, new \DateTimeZone('UTC')); }
}
