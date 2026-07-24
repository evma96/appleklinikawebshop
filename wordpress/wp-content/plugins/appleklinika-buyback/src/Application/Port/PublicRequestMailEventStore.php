<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface PublicRequestMailEventStore
{
    /** @return array<string,mixed>|null */
    public function findBySubmissionToken(string $tokenHash): ?array;

    /** @param array<string,mixed> $payload */
    public function recordOperationalEvent(int $requestId, string $type, string $summary, array $payload, string $idempotencyKey, string $createdAt): void;
}
