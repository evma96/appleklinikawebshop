<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface DeviceIntakeGateway
{
    public function create(array $input, array $files, string $operation, int $employee, string $saveMode='draft'): array;
}
