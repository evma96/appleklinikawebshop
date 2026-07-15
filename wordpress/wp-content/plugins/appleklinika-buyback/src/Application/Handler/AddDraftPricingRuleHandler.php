<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\DuplicatePricingRuleCodeException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

final class AddDraftPricingRuleHandler
{
    public function __construct(private readonly PriceBookRepository $books, private readonly PricingRuleRepository $rules, private readonly TransactionManager $transactions, private readonly Clock $clock)
    {
    }

    public function handle(AddDraftPricingRule $command): PricingRule
    {
        return $this->transactions->transactional(function () use ($command): PricingRule {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);

            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }

            $book->assertDraftMutation();
            if (! $this->rules->isCodeUnique($bookId, $command->definition->code)) {
                throw new DuplicatePricingRuleCodeException('Pricing rule code already exists in this price book.');
            }

            $rule = $this->rules->insert(PricingRule::create($bookId, $command->definition, $this->clock->now()));
            $book->recordRuleMutation($this->clock->now());
            $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));

            return $rule;
        });
    }
}
