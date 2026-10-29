<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface ArtifactReconciliationStore
{
    /** Current facts: eligible, complete, evidence, attempt, block. */
    public function facts(int $id, string $kind): array;
    public function journal(int $id, string $kind): array;
    public function save(int $id, string $kind, array $record): void;
    public function generate(int $id, string $kind): void;
    public function audit(int $id, string $kind, string $result, string $key): void;
}
