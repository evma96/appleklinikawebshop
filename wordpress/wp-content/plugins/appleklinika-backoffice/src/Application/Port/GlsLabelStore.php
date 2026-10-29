<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface GlsLabelStore
{
    /** @return array{state:string,mode:string,paid:bool,blocked:?string,recorded:bool,attempt:string} */
    public function snapshot(int $id): array;
    public function recordAttempt(int $id, string $state, string $message = ''): void;
}
