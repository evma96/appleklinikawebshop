<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Exception\PricingRuleNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

final class ToggleDraftPricingRuleHandler
{
    public function __construct(private readonly PriceBookRepository $books, private readonly PricingRuleRepository $rules, private readonly TransactionManager $transactions, private readonly Clock $clock)
    {
    }

    public function handle(ToggleDraftPricingRule $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();

            $ruleId = new PricingRuleId($command->ruleId);
            $rule = $this->rules->getById($ruleId);
            if ($rule === null || ! $rule->priceBookId()->equals($bookId)) {
                throw PricingRuleNotFoundException::forId($ruleId);
            }
            if ($rule->definition()->enabled === $command->enabled) {
                return;
            }

            $rule->toggle($command->enabled, $this->clock->now());
            $this->rules->update($rule, new AggregateVersion($command->expectedRuleVersion));
            $book->recordRuleMutation($this->clock->now());
            $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
        });
    }
}
