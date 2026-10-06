<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application\Port;

interface OrderMutex
{
    public function synchronized(int $id, callable $operation): mixed;
}
