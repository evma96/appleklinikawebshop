<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Command;

final class CreateAddress
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly int $customerId,
        public readonly array $data,
        public readonly bool $defaultBilling = false,
        public readonly bool $defaultShipping = false
    ) {
    }
}
