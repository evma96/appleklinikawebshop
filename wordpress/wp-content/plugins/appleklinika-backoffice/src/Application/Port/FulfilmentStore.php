<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface FulfilmentStore
{
    /** @return array{state:string,mode:string,blocked:?string,label:bool,tracking:bool} */
    public function snapshot(int $id): array;
    public function createLabel(int $id): void;
    public function record(int $id, string $action, string $to, int $actor, string $reason): void;
}
