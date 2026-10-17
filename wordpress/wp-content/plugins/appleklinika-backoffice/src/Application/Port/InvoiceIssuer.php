<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application\Port;

interface InvoiceIssuer
{
    public function blockReason(int $id): ?string;
    public function issue(int $id): void;
}
