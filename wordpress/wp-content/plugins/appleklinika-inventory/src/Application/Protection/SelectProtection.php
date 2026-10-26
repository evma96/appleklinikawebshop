<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Application\Protection;

use Appleklinika\Inventory\Domain\Protection\ProtectionSelection;

final class SelectProtection
{
    public function __construct(private readonly ProtectionCatalog $catalog) {}

    /** @return list<int> */
    public function handle(int $deviceId, array $input): array
    {
        $selection = ProtectionSelection::parse($input);
        if ($selection === []) return [];
        $options = $this->catalog->forDevice($deviceId);
        foreach ($selection as $group => $id) {
            $option = $options[$id] ?? null;
            if (!$option || $option['group'] !== $group || !$option['available']) {
                throw new \InvalidArgumentException('A kiválasztott kiegészítő jelenleg nem elérhető ehhez a készülékhez. Kérjük, válassz újra.');
            }
        }
        return array_values($selection);
    }
}
