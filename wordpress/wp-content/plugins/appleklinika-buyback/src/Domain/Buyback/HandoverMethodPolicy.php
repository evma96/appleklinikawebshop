<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;

final class HandoverMethodPolicy
{
    public function assertCompatible(ServiceMode $mode, HandoverMethod $method): void
    {
        if ($method->code() === HandoverMethod::COURIER && ! $mode->allowsCourier()) {
            throw new InvalidAggregateOperationException(sprintf(
                'Service mode %s does not currently allow courier handover.',
                $mode->code()
            ));
        }

        if ($method->code() === HandoverMethod::IN_STORE && ! $mode->allowsInStoreHandover()) {
            throw new InvalidAggregateOperationException(sprintf(
                'Service mode %s does not allow in-store handover.',
                $mode->code()
            ));
        }
    }
}
