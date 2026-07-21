<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class PriceBook
{
    private function __construct(
        private readonly ?PriceBookId $id,
        private readonly PriceBookVersionNumber $versionNumber,
        private string $label,
        private PriceBookStatus $status,
        private readonly CurrencyCode $currency,
        private Money $minimumOffer,
        private int $roundingIncrementMinor,
        private MinimumOfferPolicy $minimumPolicy,
        private readonly PricingActorId $createdBy,
        private AggregateVersion $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $effectiveFrom = null,
        private ?\DateTimeImmutable $effectiveTo = null,
        private ?PricingActorId $activatedBy = null,
        private ?PricingActorId $retiredBy = null,
        private ?\DateTimeImmutable $activatedAt = null,
        private ?\DateTimeImmutable $retiredAt = null
    ) {
        $this->assertLabel($label);
        $this->assertMoney($minimumOffer);
        $this->assertRounding($roundingIncrementMinor);
    }

    public static function createDraft(PriceBookVersionNumber $number, string $label, CurrencyCode $currency, Money $minimumOffer, int $roundingIncrementMinor, MinimumOfferPolicy $policy, PricingActorId $actor, \DateTimeImmutable $at): self
    {
        return new self(null, $number, $label, new PriceBookStatus(PriceBookStatus::DRAFT), $currency, $minimumOffer, $roundingIncrementMinor, $policy, $actor, new AggregateVersion(0), $at, $at);
    }

    public static function reconstitute(?PriceBookId $id, PriceBookVersionNumber $number, string $label, PriceBookStatus $status, CurrencyCode $currency, Money $minimumOffer, int $roundingIncrementMinor, MinimumOfferPolicy $policy, PricingActorId $actor, AggregateVersion $version, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt, ?\DateTimeImmutable $effectiveFrom = null, ?\DateTimeImmutable $effectiveTo = null, ?PricingActorId $activatedBy = null, ?PricingActorId $retiredBy = null, ?\DateTimeImmutable $activatedAt = null, ?\DateTimeImmutable $retiredAt = null): self
    {
        return new self($id, $number, $label, $status, $currency, $minimumOffer, $roundingIncrementMinor, $policy, $actor, $version, $createdAt, $updatedAt, $effectiveFrom, $effectiveTo, $activatedBy, $retiredBy, $activatedAt, $retiredAt);
    }

    public function updateSettings(string $label, Money $minimumOffer, int $roundingIncrementMinor, MinimumOfferPolicy $policy, \DateTimeImmutable $at): void
    {
        $this->assertDraftMutation();
        $this->assertLabel($label);
        $this->assertMoney($minimumOffer);
        $this->assertRounding($roundingIncrementMinor);
        $this->label = $label;
        $this->minimumOffer = $minimumOffer;
        $this->roundingIncrementMinor = $roundingIncrementMinor;
        $this->minimumPolicy = $policy;
        $this->recordMutation($at);
    }

    public function recordRuleMutation(\DateTimeImmutable $at): void
    {
        $this->assertDraftMutation();
        $this->recordMutation($at);
    }

    public function activate(PricingActorId $actor, \DateTimeImmutable $at): void
    {
        if (! $this->status->isDraft()) {
            throw new InvalidAggregateOperationException('Only a draft price book may be activated.');
        }

        $this->assertMutationTime($at);
        $this->status = new PriceBookStatus(PriceBookStatus::ACTIVE);
        $this->activatedBy = $actor;
        $this->activatedAt = $at;
        $this->effectiveFrom = $at;
        $this->effectiveTo = null;
        $this->recordMutation($at);
    }

    public function retire(PricingActorId $actor, \DateTimeImmutable $at): void
    {
        if (! $this->status->isActive()) {
            throw new InvalidAggregateOperationException('Only an active price book may be retired.');
        }

        $this->assertMutationTime($at);
        $this->status = new PriceBookStatus(PriceBookStatus::RETIRED);
        $this->retiredBy = $actor;
        $this->retiredAt = $at;
        $this->effectiveTo = $at;
        $this->recordMutation($at);
    }

    public function assertDraftMutation(): void
    {
        if (! $this->status->isDraft()) {
            throw new InvalidAggregateOperationException('Only draft price books may be modified.');
        }
    }

    public function id(): ?PriceBookId { return $this->id; }
    public function versionNumber(): PriceBookVersionNumber { return $this->versionNumber; }
    public function label(): string { return $this->label; }
    public function status(): PriceBookStatus { return $this->status; }
    public function currency(): CurrencyCode { return $this->currency; }
    public function minimumOffer(): Money { return $this->minimumOffer; }
    public function roundingIncrementMinor(): int { return $this->roundingIncrementMinor; }
    public function minimumPolicy(): MinimumOfferPolicy { return $this->minimumPolicy; }
    public function createdBy(): PricingActorId { return $this->createdBy; }
    public function version(): AggregateVersion { return $this->version; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function effectiveFrom(): ?\DateTimeImmutable { return $this->effectiveFrom; }
    public function effectiveTo(): ?\DateTimeImmutable { return $this->effectiveTo; }
    public function activatedBy(): ?PricingActorId { return $this->activatedBy; }
    public function retiredBy(): ?PricingActorId { return $this->retiredBy; }
    public function activatedAt(): ?\DateTimeImmutable { return $this->activatedAt; }
    public function retiredAt(): ?\DateTimeImmutable { return $this->retiredAt; }

    private function recordMutation(\DateTimeImmutable $at): void
    {
        $this->assertMutationTime($at);
        $this->updatedAt = $at;
        $this->version = $this->version->next();
    }

    private function assertMutationTime(\DateTimeImmutable $at): void
    {
        if ($at < $this->updatedAt) {
            throw new InvalidAggregateOperationException('Price-book mutation time cannot move backwards.');
        }
    }

    private function assertLabel(string $label): void
    {
        if (trim($label) === '' || strlen($label) > 120) {
            throw new InvalidValueObjectException('Price-book label is required and limited to 120 characters.');
        }
    }

    private function assertMoney(Money $money): void
    {
        if ($money->currency() !== $this->currency->code()) {
            throw new InvalidValueObjectException('Price-book amount currency mismatch.');
        }
        if ($money->amount() < 0) {
            throw new InvalidValueObjectException('Price-book minimum offer cannot be negative.');
        }
    }

    private function assertRounding(int $increment): void
    {
        if ($increment < 1 || $increment > 100000000) {
            throw new InvalidValueObjectException('Rounding increment is outside the supported range.');
        }
    }
}
