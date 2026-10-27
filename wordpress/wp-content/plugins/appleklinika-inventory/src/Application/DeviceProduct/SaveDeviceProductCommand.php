<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\DeviceProduct;
final class SaveDeviceProductCommand
{
    /** Updates are patches; creates start empty, never from another device. */
    public function __construct(public readonly int $productId, public readonly array $input) {
        if ($productId<0) throw new \InvalidArgumentException('Érvénytelen Woo termékazonosító.');
    }
}
