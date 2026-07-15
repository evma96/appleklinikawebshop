<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Query;

use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BuybackRequestPage
{
    /** @var list<BuybackRequest> */
    private readonly array $items;

    /**
     * @param list<BuybackRequest> $items
     */
    public function __construct(array $items, public readonly int $total)
    {
        if ($total < 0 || count($items) > $total) {
            throw new InvalidValueObjectException('Buyback request page totals are invalid.');
        }

        foreach ($items as $item) {
            if (! $item instanceof BuybackRequest) {
                throw new InvalidValueObjectException('Buyback request page accepts aggregates only.');
            }
        }

        $this->items = array_values($items);
    }

    /** @return list<BuybackRequest> */
    public function items(): array
    {
        return $this->items;
    }
}
