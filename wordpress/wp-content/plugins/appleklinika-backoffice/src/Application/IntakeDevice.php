<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;
use Appleklinika\BackOffice\Application\Port\DeviceIntakeGateway;
use Appleklinika\BackOffice\Domain\DeviceIntakeSaveMode;

final class IntakeDevice
{
    public function __construct(private readonly DeviceIntakeGateway $devices) {}
    public function execute(array $input, array $files, string $operation, int $employee, string $saveMode='draft'): array
    {
        if ($employee <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $operation)) {
            throw new \InvalidArgumentException('Érvénytelen bevételezési kérés. Töltsd újra az oldalt.');
        }
        $mode=DeviceIntakeSaveMode::parse($saveMode);
        return $this->devices->create($input, $files, $operation, $employee, $mode->value);
    }
}
