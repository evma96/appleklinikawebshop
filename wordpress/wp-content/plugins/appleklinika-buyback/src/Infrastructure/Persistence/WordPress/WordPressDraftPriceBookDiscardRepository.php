<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Port\DraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressDraftPriceBookDiscardRepository implements DraftPriceBookDiscardRepository
{
    private readonly string $priceBooks;
    private readonly string $priceRules;
    private readonly string $snapshots;
    private readonly string $events;

    public function __construct(private readonly \wpdb $database)
    {
        $tables = Schema::tableNames($database);
        $this->priceBooks = $tables[Schema::PRICE_BOOKS];
        $this->priceRules = $tables[Schema::PRICE_RULES];
        $this->snapshots = $tables[Schema::SNAPSHOTS];
        $this->events = $tables[Schema::EVENTS];
    }

    public function hasBusinessReferences(PriceBookId $priceBookId): bool
    {
        $referenceNeedle = '%"price_book_id"%' . $priceBookId->toInt() . '%';
        $snapshotReferences = (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->snapshots}` WHERE payload_json LIKE %s",
            $referenceNeedle
        ));
        if ($snapshotReferences > 0) {
            return true;
        }

        $eventReferences = (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->events}` WHERE private_payload_json LIKE %s",
            $referenceNeedle
        ));
        if ($eventReferences > 0) {
            return true;
        }

        // The current request schema deliberately has no price-book field or foreign key.
        return false;
    }

    public function discardDraftWithRules(PriceBookId $priceBookId): void
    {
        $ruleResult = $this->database->delete($this->priceRules, ['price_book_id' => $priceBookId->toInt()], ['%d']);
        if ($ruleResult === false) {
            throw new PersistenceException('Could not remove draft price-book rules.');
        }

        $bookResult = $this->database->delete(
            $this->priceBooks,
            ['id' => $priceBookId->toInt(), 'status' => PriceBookStatus::DRAFT],
            ['%d', '%s']
        );
        if ($bookResult !== 1) {
            throw new PersistenceException('Could not remove the draft price book.');
        }
    }
}
