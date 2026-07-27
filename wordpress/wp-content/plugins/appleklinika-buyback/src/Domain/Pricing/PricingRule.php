<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

final class PricingRule
{
    private function __construct(
        private readonly ?PricingRuleId $id,
        private readonly PriceBookId $priceBookId,
        private PricingRuleDefinition $definition,
        private AggregateVersion $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(PriceBookId $bookId, PricingRuleDefinition $definition, \DateTimeImmutable $at): self
    {
        return new self(null, $bookId, $definition, new AggregateVersion(0), $at, $at);
    }

    public static function reconstitute(PricingRuleId $id, PriceBookId $bookId, PricingRuleDefinition $definition, AggregateVersion $version, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self($id, $bookId, $definition, $version, $createdAt, $updatedAt);
    }

    public function update(PricingRuleDefinition $definition, \DateTimeImmutable $at): void
    {
        $this->assertTime($at);
        $this->definition = $definition;
        $this->recordMutation($at);
    }

    public function toggle(bool $enabled, \DateTimeImmutable $at): void
    {
        if ($this->definition->enabled === $enabled) {
            return;
        }

        $this->update(new PricingRuleDefinition(
            $this->definition->code,
            $this->definition->kind,
            $this->definition->category,
            $this->definition->modelKey,
            $this->definition->storage,
            $this->definition->serviceMode,
            $this->definition->conditionKey,
            $this->definition->operator,
            $this->definition->comparisonValue,
            $this->definition->amount,
            $this->definition->multiplier,
            $this->definition->priority,
            $enabled,
            $this->definition->publicLabel,
            $this->definition->internalNote,
            $this->definition->affectedComponentKey
        ), $at);
    }

    public function id(): ?PricingRuleId { return $this->id; }
    public function priceBookId(): PriceBookId { return $this->priceBookId; }
    public function definition(): PricingRuleDefinition { return $this->definition; }
    public function version(): AggregateVersion { return $this->version; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function recordMutation(\DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
        $this->version = $this->version->next();
    }

    private function assertTime(\DateTimeImmutable $at): void
    {
        if ($at < $this->updatedAt) {
            throw new InvalidAggregateOperationException('Pricing-rule mutation time cannot move backwards.');
        }
    }
}
