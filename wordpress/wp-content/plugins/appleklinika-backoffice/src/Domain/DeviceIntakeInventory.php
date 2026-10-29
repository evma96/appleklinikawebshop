<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Creation-only contract. Sold units may subsequently reach zero through Woo stock handling. */
final class DeviceIntakeInventory
{
    public static function assertNewUnit(bool $managed,?int $quantity,bool $individual,string $stockStatus):void
    {
        if(!$managed||$quantity!==1||!$individual||$stockStatus!=='instock')throw new \InvalidArgumentException('Az új fizikai készülék csak egyedi, kezelt, 1 darabos készlettel menthető.');
    }
}
