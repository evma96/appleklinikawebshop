<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\TransitionContext;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

final class TransitionBuybackRequest
{
    public function __construct(
        public readonly BuybackRequestId $requestId,
        public readonly BuybackStatus $targetStatus,
        public readonly AggregateVersion $expectedVersion,
        public readonly TransitionContext $context
    ) {
    }
}
