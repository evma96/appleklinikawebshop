<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\Exception\BuybackRequestNotFoundException;
use AppleKlinika\Buyback\Application\Exception\DuplicateBuybackRequestException;
use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Query\BuybackRequestPage;
use AppleKlinika\Buyback\Application\Query\PageRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressBuybackRequestRepository implements BuybackRequestRepository
{
    private readonly string $table;

    public function __construct(
        private readonly \wpdb $database,
        private readonly WordPressBuybackRequestMapper $mapper
    ) {
        $this->table = Schema::tableNames($database)[Schema::REQUESTS];
    }

    public function insert(NewBuybackRequest $request): BuybackRequest
    {
        if ($this->existsByRequestNumber($request->requestNumber)) {
            throw new DuplicateBuybackRequestException('Buyback request number already exists.');
        }

        if (
            $request->legacyReference !== null
            && $this->existsByLegacyReference($request->legacyReference)
        ) {
            throw new DuplicateBuybackRequestException('Buyback legacy reference already exists.');
        }

        $result = $this->database->insert(
            $this->table,
            $this->mapper->insertValues($request),
            [
                '%s', '%d', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%d', '%s', '%s',
            ]
        );

        if ($result !== 1) {
            if ($this->existsByRequestNumber($request->requestNumber)) {
                throw new DuplicateBuybackRequestException('Buyback request number already exists.');
            }

            if (
                $request->legacyReference !== null
                && $this->existsByLegacyReference($request->legacyReference)
            ) {
                throw new DuplicateBuybackRequestException('Buyback legacy reference already exists.');
            }

            throw new PersistenceException('Could not insert the buyback request.');
        }

        $id = new BuybackRequestId((int) $this->database->insert_id);
        $persisted = $this->getById($id);

        if ($persisted === null) {
            throw new PersistenceException('Inserted buyback request could not be reloaded.');
        }

        return $persisted;
    }

    public function getById(BuybackRequestId $id): ?BuybackRequest
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1",
                $id->toInt()
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->mapper->toDomain($row) : null;
    }

    public function getByRequestNumber(RequestNumber $requestNumber): ?BuybackRequest
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` WHERE request_number = %s LIMIT 1",
                $requestNumber->value()
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->mapper->toDomain($row) : null;
    }

    public function save(BuybackRequest $request, AggregateVersion $expectedVersion): void
    {
        if ($request->version()->value() !== $expectedVersion->value() + 1) {
            throw new PersistenceException('Buyback save requires exactly one accepted aggregate mutation.');
        }

        $result = $this->database->update(
            $this->table,
            $this->mapper->updateValues($request),
            [
                'id' => $request->id()->toInt(),
                'version' => $expectedVersion->value(),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s'],
            ['%d', '%d']
        );

        if ($result === false) {
            throw new PersistenceException('Could not update the buyback request.');
        }

        if ($result === 1) {
            return;
        }

        $current = $this->getById($request->id());

        if ($current === null) {
            throw BuybackRequestNotFoundException::forId($request->id());
        }

        throw new StaleAggregateVersionException(
            $expectedVersion->value(),
            $current->version()->value()
        );
    }

    public function findByCustomer(CustomerId $customerId, PageRequest $page): BuybackRequestPage
    {
        return $this->findPage('customer_id', $customerId->toInt(), '%d', $page);
    }

    public function findByStatus(BuybackStatus $status, PageRequest $page): BuybackRequestPage
    {
        return $this->findPage('status', $status->code(), '%s', $page);
    }

    public function existsByRequestNumber(RequestNumber $requestNumber): bool
    {
        return (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE request_number = %s",
                $requestNumber->value()
            )
        ) > 0;
    }

    public function existsByLegacyReference(LegacyReference $legacyReference): bool
    {
        return (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE legacy_reference = %s",
                $legacyReference->value()
            )
        ) > 0;
    }

    private function findPage(string $column, int|string $value, string $format, PageRequest $page): BuybackRequestPage
    {
        if (! in_array($column, ['customer_id', 'status'], true)) {
            throw new PersistenceException('Unsupported buyback repository query field.');
        }

        $total = (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE `{$column}` = {$format}",
                $value
            )
        );

        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}`
                 WHERE `{$column}` = {$format}
                 ORDER BY updated_at DESC, created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $value,
                $page->perPage,
                $page->offset()
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            throw new PersistenceException('Could not load a buyback request page.');
        }

        return new BuybackRequestPage(
            array_map(fn (array $row): BuybackRequest => $this->mapper->toDomain($row), $rows),
            $total
        );
    }
}
