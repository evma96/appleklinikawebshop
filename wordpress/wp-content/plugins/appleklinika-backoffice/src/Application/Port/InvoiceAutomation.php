<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application\Port;

interface InvoiceAutomation
{
    public function ready(): bool;
    public function run(int $orderId): void;
}
