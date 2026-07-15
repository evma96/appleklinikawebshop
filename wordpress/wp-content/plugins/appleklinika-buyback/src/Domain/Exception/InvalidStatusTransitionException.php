<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Exception;

final class InvalidStatusTransitionException extends BuybackDomainException
{
    public static function between(string $from, string $to, string $actor, string $reason): self
    {
        return new self(sprintf(
            'Buyback transition %s -> %s by %s was rejected: %s.',
            $from,
            $to,
            $actor,
            $reason
        ));
    }
}
