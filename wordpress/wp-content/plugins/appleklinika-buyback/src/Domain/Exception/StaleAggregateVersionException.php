<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Exception;

final class StaleAggregateVersionException extends BuybackDomainException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(sprintf(
            'Buyback aggregate version mismatch: expected %d, actual %d.',
            $expected,
            $actual
        ));
    }
}
