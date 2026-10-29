<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;
interface DeviceProducts
{
    public function listing(int $page, string $sku, string $status): array;
    public function detail(int $id): array;
    public function updateOffer(int $id, array $input, string $expected): void;
    public function publish(int $id,string $expected):void;
}
