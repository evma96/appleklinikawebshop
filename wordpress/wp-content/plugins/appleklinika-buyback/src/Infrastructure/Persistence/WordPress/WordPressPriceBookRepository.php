<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Exception\DuplicatePriceBookVersionException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressPriceBookRepository implements PriceBookRepository
{
    private readonly string $table;

    public function __construct(private readonly \wpdb $database, private readonly ?TransactionManager $transactions = null)
    {
        $this->table = Schema::tableNames($database)[Schema::PRICE_BOOKS];
    }

    public function createDraft(PriceBook $priceBook): PriceBook
    {
        $priceBook->assertDraftMutation();
        if ($priceBook->id() !== null) {
            throw new PersistenceException('A persisted price book cannot be inserted again.');
        }

        $result = $this->database->insert($this->table, $this->insertValues($priceBook), [
            '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s',
        ]);

        if ($result !== 1) {
            if ($this->getByVersionNumber($priceBook->versionNumber()) !== null) {
                throw new DuplicatePriceBookVersionException('Price-book version number already exists.');
            }
            throw new PersistenceException('Could not insert the draft price book.');
        }

        $persisted = $this->getById(new PriceBookId((int) $this->database->insert_id));
        if ($persisted === null) {
            throw new PersistenceException('Inserted price book could not be reloaded.');
        }

        return $persisted;
    }

    public function getById(PriceBookId $id): ?PriceBook
    {
        $row = $this->database->get_row($this->database->prepare(
            "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1",
            $id->toInt()
        ), ARRAY_A);

        return is_array($row) ? $this->toDomain($row) : null;
    }

    public function getByIdForUpdate(PriceBookId $id): ?PriceBook
    {
        $this->assertTransactionActive();
        $row = $this->database->get_row($this->database->prepare(
            "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1 FOR UPDATE",
            $id->toInt()
        ), ARRAY_A);

        return is_array($row) ? $this->toDomain($row) : null;
    }

    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook
    {
        $row = $this->database->get_row($this->database->prepare(
            "SELECT * FROM `{$this->table}` WHERE version_number = %d LIMIT 1",
            $number->value()
        ), ARRAY_A);

        return is_array($row) ? $this->toDomain($row) : null;
    }

    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = '';
        $arguments = [];

        if ($status !== null) {
            $where = ' WHERE status = %s';
            $arguments[] = $status->code();
        }

        $totalSql = "SELECT COUNT(*) FROM `{$this->table}`{$where}";
        $total = (int) $this->database->get_var($arguments === [] ? $totalSql : $this->database->prepare($totalSql, ...$arguments));

        $arguments[] = $perPage;
        $arguments[] = ($page - 1) * $perPage;
        $rows = $this->database->get_results($this->database->prepare(
            "SELECT * FROM `{$this->table}`{$where} ORDER BY version_number DESC, id DESC LIMIT %d OFFSET %d",
            ...$arguments
        ), ARRAY_A);

        if (! is_array($rows)) {
            throw new PersistenceException('Could not list price books.');
        }

        return new PriceBookPage(array_map(fn (array $row): PriceBook => $this->toDomain($row), $rows), $total);
    }

    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void
    {
        $priceBook->assertDraftMutation();
        if ($priceBook->id() === null || $priceBook->version()->value() !== $expectedVersion->value() + 1) {
            throw new PersistenceException('Price-book save requires one accepted aggregate mutation.');
        }

        $result = $this->database->update(
            $this->table,
            [
                'label' => $priceBook->label(),
                'minimum_offer_minor' => $priceBook->minimumOffer()->amount(),
                'rounding_increment_minor' => $priceBook->roundingIncrementMinor(),
                'minimum_policy' => $priceBook->minimumPolicy()->code(),
                'version' => $priceBook->version()->value(),
                'updated_at' => $this->date($priceBook->updatedAt()),
            ],
            [
                'id' => $priceBook->id()->toInt(),
                'status' => PriceBookStatus::DRAFT,
                'version' => $expectedVersion->value(),
            ],
            ['%s', '%d', '%d', '%s', '%d', '%s'],
            ['%d', '%s', '%d']
        );

        if ($result === false) {
            throw new PersistenceException('Could not update the draft price book.');
        }
        if ($result === 1) {
            return;
        }

        $current = $this->getById($priceBook->id());
        if ($current === null) {
            throw PriceBookNotFoundException::forId($priceBook->id());
        }
        $current->assertDraftMutation();
        throw new StaleAggregateVersionException($expectedVersion->value(), $current->version()->value());
    }

    public function saveActivated(PriceBook $priceBook, AggregateVersion $expectedVersion): void
    {
        if (! $priceBook->status()->isActive() || $priceBook->activatedBy() === null || $priceBook->activatedAt() === null || $priceBook->effectiveFrom() === null) {
            throw new PersistenceException('Only a fully activated price book may be persisted as active.');
        }
        $this->saveLifecycle($priceBook, $expectedVersion, PriceBookStatus::DRAFT);
    }

    public function saveRetired(PriceBook $priceBook, AggregateVersion $expectedVersion): void
    {
        if (! $priceBook->status()->isRetired() || $priceBook->retiredBy() === null || $priceBook->retiredAt() === null || $priceBook->effectiveTo() === null) {
            throw new PersistenceException('Only a fully retired price book may be persisted as retired.');
        }
        $this->saveLifecycle($priceBook, $expectedVersion, PriceBookStatus::ACTIVE);
    }

    public function nextAvailableVersionNumber(): PriceBookVersionNumber
    {
        $maximum = (int) $this->database->get_var("SELECT COALESCE(MAX(version_number), 0) FROM `{$this->table}`");
        return new PriceBookVersionNumber($maximum + 1);
    }

    public function hasActiveBook(): bool
    {
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE status = %s",
            PriceBookStatus::ACTIVE
        )) > 0;
    }

    public function findCurrentActiveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): array
    {
        return $this->findCurrentActive($currency, $at, false);
    }

    public function findCurrentActiveForCurrencyAtForUpdate(CurrencyCode $currency, \DateTimeImmutable $at): array
    {
        $this->assertTransactionActive();
        return $this->findCurrentActive($currency, $at, true);
    }

    public function countCurrentActiveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): int
    {
        $date = $this->date($at);
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE status = %s AND currency = %s AND effective_from IS NOT NULL AND effective_from <= %s AND (effective_to IS NULL OR %s < effective_to)",
            PriceBookStatus::ACTIVE,
            $currency->code(),
            $date,
            $date
        ));
    }

    /** @return list<PriceBook> */
    private function findCurrentActive(CurrencyCode $currency, \DateTimeImmutable $at, bool $forUpdate): array
    {
        $date = $this->date($at);
        $sql = $this->database->prepare(
            "SELECT * FROM `{$this->table}` WHERE status = %s AND currency = %s AND effective_from IS NOT NULL AND effective_from <= %s AND (effective_to IS NULL OR %s < effective_to) ORDER BY effective_from DESC, id ASC" . ($forUpdate ? ' FOR UPDATE' : ''),
            PriceBookStatus::ACTIVE,
            $currency->code(),
            $date,
            $date
        );
        $rows = $this->database->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            throw new PersistenceException('Could not resolve current active price books.');
        }
        return array_map(fn (array $row): PriceBook => $this->toDomain($row), $rows);
    }

    private function saveLifecycle(PriceBook $book, AggregateVersion $expectedVersion, string $expectedStatus): void
    {
        $this->assertTransactionActive();
        if ($book->id() === null || $book->version()->value() !== $expectedVersion->value() + 1) {
            throw new PersistenceException('Price-book lifecycle save requires one accepted aggregate mutation.');
        }

        $result = $this->database->update(
            $this->table,
            [
                'status' => $book->status()->code(),
                'effective_from' => $this->nullableDate($book->effectiveFrom()),
                'effective_to' => $this->nullableDate($book->effectiveTo()),
                'activated_by' => $book->activatedBy()?->value(),
                'retired_by' => $book->retiredBy()?->value(),
                'version' => $book->version()->value(),
                'updated_at' => $this->date($book->updatedAt()),
                'activated_at' => $this->nullableDate($book->activatedAt()),
                'retired_at' => $this->nullableDate($book->retiredAt()),
            ],
            [
                'id' => $book->id()->toInt(),
                'status' => $expectedStatus,
                'version' => $expectedVersion->value(),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'],
            ['%d', '%s', '%d']
        );

        if ($result === false) {
            throw new PersistenceException('Could not persist the price-book lifecycle transition.');
        }
        if ($result === 1) {
            return;
        }

        $current = $this->getById($book->id());
        if ($current === null) {
            throw PriceBookNotFoundException::forId($book->id());
        }
        if ($current->version()->value() !== $expectedVersion->value()) {
            throw new StaleAggregateVersionException($expectedVersion->value(), $current->version()->value());
        }
        throw new \AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException('Price-book lifecycle state changed before persistence.');
    }

    private function assertTransactionActive(): void
    {
        if ($this->transactions === null || ! $this->transactions->isActive()) {
            throw new PersistenceException('Price-book row locking requires an active database transaction.');
        }
    }

    /** @return array<string, int|string> */
    private function insertValues(PriceBook $book): array
    {
        return [
            'version_number' => $book->versionNumber()->value(),
            'label' => $book->label(),
            'status' => $book->status()->code(),
            'currency' => $book->currency()->code(),
            'minimum_offer_minor' => $book->minimumOffer()->amount(),
            'rounding_increment_minor' => $book->roundingIncrementMinor(),
            'minimum_policy' => $book->minimumPolicy()->code(),
            'created_by' => $book->createdBy()->value(),
            'version' => $book->version()->value(),
            'created_at' => $this->date($book->createdAt()),
            'updated_at' => $this->date($book->updatedAt()),
        ];
    }

    /** @param array<string, mixed> $row */
    private function toDomain(array $row): PriceBook
    {
        return PriceBook::reconstitute(
            new PriceBookId((int) $row['id']),
            new PriceBookVersionNumber((int) $row['version_number']),
            (string) $row['label'],
            new PriceBookStatus((string) $row['status']),
            new CurrencyCode((string) $row['currency']),
            new Money((int) $row['minimum_offer_minor'], (string) $row['currency']),
            (int) $row['rounding_increment_minor'],
            new MinimumOfferPolicy((string) $row['minimum_policy']),
            new PricingActorId((int) $row['created_by']),
            new AggregateVersion((int) $row['version']),
            $this->parseDate((string) $row['created_at']),
            $this->parseDate((string) $row['updated_at']),
            $this->parseNullableDate($row['effective_from']),
            $this->parseNullableDate($row['effective_to']),
            $row['activated_by'] === null ? null : new PricingActorId((int) $row['activated_by']),
            $row['retired_by'] === null ? null : new PricingActorId((int) $row['retired_by']),
            $this->parseNullableDate($row['activated_at']),
            $this->parseNullableDate($row['retired_at'])
        );
    }

    private function date(\DateTimeImmutable $date): string { return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function nullableDate(?\DateTimeImmutable $date): ?string { return $date === null ? null : $this->date($date); }
    private function parseDate(string $date): \DateTimeImmutable { return new \DateTimeImmutable($date, new \DateTimeZone('UTC')); }
    private function parseNullableDate(mixed $date): ?\DateTimeImmutable { return $date === null || $date === '' ? null : $this->parseDate((string) $date); }
}
