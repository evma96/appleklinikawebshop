<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface BuybackMailDiagnosticsReader
{
    /** @return array{configured:bool,host:string,port:string,encryption:string,username:string,from:string,admin:string,missing:list<string>,last_customer:string,last_admin:string} */
    public function summary(): array;
}
