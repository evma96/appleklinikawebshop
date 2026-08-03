<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Port;

interface TransactionManager
{
    public function transactional(callable $operation): mixed;
}
