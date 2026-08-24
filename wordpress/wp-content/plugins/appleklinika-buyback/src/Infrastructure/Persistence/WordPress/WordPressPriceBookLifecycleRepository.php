<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressPriceBookLifecycleRepository implements PriceBookLifecycleRepository
{
    private readonly string $references;
    private readonly string $events;
    private readonly string $books;
    private readonly string $snapshots;
    private readonly string $requestEvents;

    public function __construct(private readonly \wpdb $database)
    {
        $tables = Schema::tableNames($database);
        $this->references = $tables[Schema::PRICE_BOOK_REFERENCES];
        $this->events = $tables[Schema::PRICE_BOOK_LIFECYCLE_EVENTS];
        $this->books = $tables[Schema::PRICE_BOOKS];
        $this->snapshots = $tables[Schema::SNAPSHOTS];
        $this->requestEvents = $tables[Schema::EVENTS];
    }

    public function protectedReferenceFor(CurrencyCode $currency): ?PriceBookId
    {
        $id = $this->database->get_var($this->database->prepare("SELECT price_book_id FROM `{$this->references}` WHERE currency = %s", $currency->code()));
        return $id === null ? null : new PriceBookId((int) $id);
    }

    public function isProtected(PriceBookId $priceBookId): bool
    {
        return (int) $this->database->get_var($this->database->prepare("SELECT COUNT(*) FROM `{$this->references}` WHERE price_book_id = %d", $priceBookId->toInt())) > 0;
    }

    public function moveProtectedReference(CurrencyCode $currency, PriceBookId $priceBookId, int $actorId, \DateTimeImmutable $at): ?PriceBookId
    {
        $previousId = $this->database->get_var($this->database->prepare("SELECT price_book_id FROM `{$this->references}` WHERE currency = %s FOR UPDATE", $currency->code()));
        $previous = $previousId === null ? null : new PriceBookId((int) $previousId);
        $result = $this->database->query($this->database->prepare(
            "INSERT INTO `{$this->references}` (currency, price_book_id, version, changed_by, changed_at) VALUES (%s, %d, 1, %d, %s) ON DUPLICATE KEY UPDATE price_book_id = VALUES(price_book_id), version = version + 1, changed_by = VALUES(changed_by), changed_at = VALUES(changed_at)",
            $currency->code(), $priceBookId->toInt(), $actorId, $at->format('Y-m-d H:i:s')
        ));
        if ($result === false) {
            throw new PersistenceException('A védett referencia-árkönyv mentése nem sikerült.');
        }
        return $previous;
    }

    public function hasEverBeenActive(PriceBookId $priceBookId): bool
    {
        return (int) $this->database->get_var($this->database->prepare("SELECT COUNT(*) FROM `{$this->books}` WHERE id = %d AND (activated_at IS NOT NULL OR effective_from IS NOT NULL)", $priceBookId->toInt())) > 0;
    }

    public function hasLifecycleDependencies(PriceBookId $priceBookId): bool
    {
        return $this->hasExactPriceBookReference($this->snapshots, 'payload_json', $priceBookId)
            || $this->hasExactPriceBookReference($this->requestEvents, 'private_payload_json', $priceBookId);
    }

    public function record(string $eventType, PriceBookId $priceBookId, int $actorId, array $payload, \DateTimeImmutable $at): void
    {
        if ($this->database->insert($this->events, [
            'price_book_id' => $priceBookId->toInt(), 'event_type' => $eventType, 'actor_id' => $actorId,
            'payload_json' => wp_json_encode($payload), 'created_at' => $at->format('Y-m-d H:i:s'),
        ], ['%d', '%s', '%d', '%s', '%s']) !== 1) {
            throw new PersistenceException('Az árkönyv-életciklus esemény naplózása nem sikerült.');
        }
    }

    private function hasExactPriceBookReference(string $table, string $column, PriceBookId $priceBookId): bool
    {
        $pattern = '"price_book_id"[[:space:]]*:[[:space:]]*"?' . $priceBookId->toInt() . '"?[[:space:]]*[,}]';
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` REGEXP %s",
            $pattern
        )) > 0;
    }
}
