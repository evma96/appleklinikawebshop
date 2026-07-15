<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Port\DomainEventPublisher;
use AppleKlinika\Buyback\Domain\Buyback\Event\BuybackStatusChanged;
use AppleKlinika\Buyback\Domain\Shared\DomainEvent;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressDomainEventStore implements DomainEventPublisher
{
    public const STATUS_CHANGED = 'buyback_status_changed';

    private const FORBIDDEN_PAYLOAD_KEYS = [
        'address', 'email', 'iban', 'imei', 'name', 'phone', 'telephone',
    ];

    private readonly string $table;

    public function __construct(
        private readonly \wpdb $database,
        private readonly WordPressBuybackRequestMapper $mapper
    ) {
        $this->table = Schema::tableNames($database)[Schema::EVENTS];
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            if (! $event instanceof BuybackStatusChanged) {
                throw new PersistenceException('Unsupported buyback domain event type.');
            }

            $this->persistStatusChanged($event);
        }
    }

    private function persistStatusChanged(BuybackStatusChanged $event): void
    {
        if ($event->correlationId() !== null && strlen($event->correlationId()) > 100) {
            throw new PersistenceException('Correlation ID exceeds the persistence limit.');
        }

        $metadata = $event->metadata();
        ksort($metadata);

        foreach (array_keys($metadata) as $key) {
            $normalized = strtolower($key);

            foreach (self::FORBIDDEN_PAYLOAD_KEYS as $forbidden) {
                if (str_contains($normalized, $forbidden)) {
                    throw new PersistenceException('Customer-sensitive metadata cannot be persisted in buyback events.');
                }
            }
        }

        try {
            $payload = $metadata === []
                ? null
                : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            throw new PersistenceException('Could not encode buyback event metadata.', 0, $exception);
        }

        $createdAt = $this->mapper->formatDateTime($event->occurredAt());
        $idempotencyKey = hash('sha256', json_encode([
            self::STATUS_CHANGED,
            $event->requestId()->toInt(),
            $event->fromStatus()->code(),
            $event->toStatus()->code(),
            $event->actorType()->code(),
            $createdAt,
            $event->correlationId(),
            $metadata,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $payloadPlaceholder = $payload === null ? 'NULL' : '%s';
        $correlationPlaceholder = $event->correlationId() === null ? 'NULL' : '%s';
        $arguments = [
            $event->requestId()->toInt(),
            self::STATUS_CHANGED,
            $event->fromStatus()->code(),
            $event->toStatus()->code(),
            $event->actorType()->code(),
        ];

        if ($payload !== null) {
            $arguments[] = $payload;
        }

        if ($event->correlationId() !== null) {
            $arguments[] = $event->correlationId();
        }

        $arguments[] = $idempotencyKey;
        $arguments[] = $createdAt;

        $query = $this->database->prepare(
            "INSERT INTO `{$this->table}`
                (request_id, event_type, from_status, to_status, actor_type, actor_id,
                 public_summary, private_payload_json, correlation_id, idempotency_key, created_at)
             VALUES (%d, %s, %s, %s, %s, NULL, NULL, {$payloadPlaceholder},
                     {$correlationPlaceholder}, %s, %s)
             ON DUPLICATE KEY UPDATE idempotency_key = VALUES(idempotency_key)",
            ...$arguments
        );

        $result = $this->database->query($query);

        if ($result === false) {
            throw new PersistenceException('Could not persist the buyback domain event.');
        }

        if (! $this->idempotencyKeyExists($idempotencyKey)) {
            throw new PersistenceException('Persisted buyback domain event could not be verified.');
        }
    }

    private function idempotencyKeyExists(string $key): bool
    {
        return (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE idempotency_key = %s",
                $key
            )
        ) > 0;
    }
}
