<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Application\Protection;

interface ProtectionCatalog
{
    /** @return array<int,array{id:int,group:string,name:string,available:bool,price_html:string}> */
    public function forDevice(int $deviceId): array;
}
