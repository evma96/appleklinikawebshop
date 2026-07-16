<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\NoActivePriceBookException;
use AppleKlinika\Buyback\Application\Port\ActivePriceBookResolver;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration;

final class RepositoryActivePriceBookResolver implements ActivePriceBookResolver
{
    public function __construct(private readonly PriceBookRepository $books, private readonly PricingRuleRepository $rules)
    {
    }

    public function resolveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): ResolvedActivePriceBook
    {
        $active = $this->books->findCurrentActiveForCurrencyAt($currency, $at);
        if ($active === []) {
            throw new NoActivePriceBookException('No current active price book exists for ' . $currency->code() . '.');
        }
        if (count($active) !== 1) {
            throw new MultipleActivePriceBooksException('Multiple current active price books exist for ' . $currency->code() . '.');
        }

        $book = $active[0];
        $rules = array_values(array_filter(
            $this->rules->listForPriceBook($book->id()),
            static fn ($rule): bool => $rule->definition()->enabled && $rule->priceBookId()->equals($book->id())
        ));

        return new ResolvedActivePriceBook(
            $book,
            $rules,
            SupportedPriceConfiguration::fromEnabledBaseRules($rules),
            $at->setTimezone(new \DateTimeZone('UTC'))
        );
    }
}
