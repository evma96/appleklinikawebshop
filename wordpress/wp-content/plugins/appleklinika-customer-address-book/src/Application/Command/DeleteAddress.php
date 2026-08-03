<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Command;

final class DeleteAddress
{
    /** @param array<string, string> $successorKeys */
    public function __construct(
        public readonly int $customerId,
        public readonly string $addressKey,
        public readonly int $expectedVersion,
        public readonly array $successorKeys = []
    ) {
    }
}
