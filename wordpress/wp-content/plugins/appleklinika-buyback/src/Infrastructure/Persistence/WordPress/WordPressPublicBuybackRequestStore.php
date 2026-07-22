<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

/** Persistence adapter for the public request fields, immutable snapshot and operational event log. */
final class WordPressPublicBuybackRequestStore
{
    private readonly string $requests;
    private readonly string $snapshots;
    private readonly string $events;

    public function __construct(private readonly \wpdb $database)
    {
        $tables = Schema::tableNames($database);
        $this->requests = $tables[Schema::REQUESTS];
        $this->snapshots = $tables[Schema::SNAPSHOTS];
        $this->events = $tables[Schema::EVENTS];
    }

    /** @return array<string,mixed>|null */
    public function findBySubmissionToken(string $tokenHash): ?array
    {
        $row = $this->database->get_row($this->database->prepare(
            "SELECT id, request_number, device_display_name, service_mode FROM `{$this->requests}` WHERE submission_token_hash = %s LIMIT 1",
            $tokenHash
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function savePublicData(
        BuybackRequestId $requestId,
        string $tokenHash,
        string $name,
        string $email,
        string $phone,
        ?string $note,
        string $submittedAt
    ): void {
        $result = $this->database->update(
            $this->requests,
            [
                'submission_token_hash' => $tokenHash,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'customer_note' => $note,
                'submitted_at' => $submittedAt,
            ],
            ['id' => $requestId->toInt()],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== 1) {
            throw new PersistenceException('Could not persist the public buyback request data.');
        }
    }

    /** @param array<string,mixed> $payload */
    public function saveInitialSnapshot(BuybackRequestId $requestId, array $payload, string $createdAt): void
    {
        ksort($payload);
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new PersistenceException('Could not encode the immutable buyback snapshot.', 0, $exception);
        }

        $result = $this->database->insert(
            $this->snapshots,
            [
                'request_id' => $requestId->toInt(),
                'snapshot_type' => 'public_submission',
                'schema_version' => '1.0',
                'payload_json' => $json,
                'created_by_type' => 'customer',
                'created_by_id' => null,
                'checksum' => hash('sha256', $json),
                'created_at' => $createdAt,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result !== 1) {
            throw new PersistenceException('Could not persist the immutable buyback snapshot.');
        }
    }

    /** @param array<string,mixed> $payload */
    public function recordOperationalEvent(int $requestId, string $type, string $summary, array $payload, string $idempotencyKey, string $createdAt): void
    {
        $result = $this->database->query($this->database->prepare(
            "INSERT INTO `{$this->events}` (request_id, event_type, actor_type, public_summary, private_payload_json, idempotency_key, created_at)
             VALUES (%d, %s, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE idempotency_key = VALUES(idempotency_key)",
            $requestId,
            $type,
            'system',
            $summary,
            wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $idempotencyKey,
            $createdAt
        ));

        if ($result === false) {
            throw new PersistenceException('Could not persist the buyback operational event.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function listRecent(): array
    {
        $rows = $this->database->get_results(
            "SELECT id, request_number, customer_name, customer_email, customer_phone, device_display_name, model_key, service_mode, status, submitted_at
             FROM `{$this->requests}` WHERE submitted_at IS NOT NULL ORDER BY submitted_at DESC, id DESC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return array{request:array<string,mixed>,snapshot:array<string,mixed>|null,events:list<array<string,mixed>>}|null */
    public function detail(int $requestId): ?array
    {
        $request = $this->database->get_row($this->database->prepare("SELECT * FROM `{$this->requests}` WHERE id = %d LIMIT 1", $requestId), ARRAY_A);
        if (! is_array($request)) {
            return null;
        }
        $snapshot = $this->database->get_row($this->database->prepare(
            "SELECT payload_json, checksum, created_at FROM `{$this->snapshots}` WHERE request_id = %d AND snapshot_type = %s ORDER BY id ASC LIMIT 1",
            $requestId,
            'public_submission'
        ), ARRAY_A);
        $events = $this->database->get_results($this->database->prepare(
            "SELECT event_type, from_status, to_status, actor_type, public_summary, created_at FROM `{$this->events}` WHERE request_id = %d ORDER BY id ASC",
            $requestId
        ), ARRAY_A);
        return ['request' => $request, 'snapshot' => is_array($snapshot) ? $snapshot : null, 'events' => is_array($events) ? $events : []];
    }
}
