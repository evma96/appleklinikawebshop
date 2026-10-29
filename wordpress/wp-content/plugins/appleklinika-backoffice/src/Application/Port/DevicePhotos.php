<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface DevicePhotos
{
    public function ingest(array $files, string $operation): array;
    public function remove(array $ids): void;
}
