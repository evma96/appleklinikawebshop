<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\InvoiceIssuer;
use Appleklinika\BackOffice\Application\Port\OrderMutex;

/** Invoice commands deliberately have no fulfilment/shipping dependency. */
final class GenerateInvoice
{
    public function __construct(private readonly InvoiceIssuer $issuer, private readonly OrderMutex $mutex) {}

    public function execute(int $id): void
    {
        $this->mutex->synchronized($id, function () use ($id): void {
            $reason = $this->issuer->blockReason($id);
            if ($reason !== null) {
                throw new \InvalidArgumentException($reason);
            }
            $this->issuer->issue($id);
        });
    }
}
