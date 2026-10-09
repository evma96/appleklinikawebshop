<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

final class LifecycleOrder
{
    /** @param list<array{code:string,url:string}> $tracking */
    public function __construct(
        public readonly int $id,
        public readonly bool $managed,
        public readonly bool $paid,
        public readonly string $status,
        public readonly string $invoiceNumber,
        public readonly string $invoicePath,
        public readonly bool $handoffRecorded,
        public readonly array $tracking,
        public readonly bool $submitted = false,
        public readonly bool $submissionEligible = false,
        public readonly bool $cashPickup = false,
        public readonly bool $cashAccepted = false,
    ) {
    }
}
