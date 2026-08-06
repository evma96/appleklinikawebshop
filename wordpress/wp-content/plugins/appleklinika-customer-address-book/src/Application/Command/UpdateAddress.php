<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Command;

final class UpdateAddress
{
    /** @param array<string, mixed> $changes */
    public function __construct(
        public readonly int $customerId,
        public readonly string $addressKey,
        public readonly int $expectedVersion,
        public readonly array $changes,
        public readonly bool $defaultBilling = false,
        public readonly bool $defaultShipping = false
    ) {
    }
}
