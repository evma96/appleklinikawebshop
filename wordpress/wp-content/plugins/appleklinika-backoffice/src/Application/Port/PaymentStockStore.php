<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;
interface PaymentStockStore
{
    public function lockAndRead(int $id): ?array;
    public function reserve(int $id): void;
    public function exception(int $id, string $transaction, string $reason): void;
}
