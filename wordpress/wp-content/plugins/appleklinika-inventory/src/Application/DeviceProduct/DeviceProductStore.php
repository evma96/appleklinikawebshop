<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\DeviceProduct;
interface DeviceProductStore
{
    public function load(int $id): array;
    public function catalog(): array;
    public function assertUnique(string $sku, string $imei, string $serial, int $except): void;
    public function validatePhotos(array $ids, int $productId): void;
    public function persist(int $id, array $plan): int;
    public function synchronized(callable $operation): mixed;
}
