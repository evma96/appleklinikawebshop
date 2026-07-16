<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface TransactionManager
{
    public function isActive(): bool;

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
