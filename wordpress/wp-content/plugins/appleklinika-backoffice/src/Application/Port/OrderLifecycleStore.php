<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application\Port;

use Appleklinika\BackOffice\Domain\LifecycleOrder;

interface OrderLifecycleStore
{
    public function order(int $id): ?LifecycleOrder;
    public function recordSubmission(int $id): void;
    public function notification(int $id, string $event): array;
    public function recordNotification(int $id, string $event, array $record): void;
    public function invoiceRecord(int $id): array;
    public function invoiceState(int $id, string $state, string $reason = ''): void;
    public function issue(int $id, string $code): void;
}
