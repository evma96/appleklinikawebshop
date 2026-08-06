<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Command;

final class SetDefaultAddress
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $addressKey,
        public readonly int $expectedVersion,
        public readonly string $purpose
    ) {
    }
}
