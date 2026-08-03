<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Query;

final class GetCustomerAddress
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $addressKey
    ) {
    }
}
