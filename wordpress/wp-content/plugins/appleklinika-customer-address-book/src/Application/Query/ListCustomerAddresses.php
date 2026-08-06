<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Query;

final class ListCustomerAddresses
{
    public function __construct(public readonly int $customerId) {}
}
