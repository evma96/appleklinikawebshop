<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress;

use AppleKlinika\CustomerAddressBook\Application\Port\AddressRepository;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict;

final class WordPressAddressRepository implements AddressRepository
{
    private string $addressesTable;
    private string $defaultsTable;

    public function __construct(private readonly \wpdb $database)
    {
        $tables = Schema::tables($database);
        $this->addressesTable = $tables['addresses'];
        $this->defaultsTable = $tables['defaults'];
    }

    public function lockCustomer(int $customerId): void
    {
        $found = $this->database->get_var($this->database->prepare(
            "SELECT ID FROM {$this->database->users} WHERE ID = %d FOR UPDATE",
            $customerId
        ));
        if ((int) $found !== $customerId) {
            throw new \RuntimeException('Az ügyfél nem található.');
        }
    }

    public function create(Address $address): Address
    {
        $row = $address->toArray();
        unset($row['id']);
        if ($this->database->insert($this->addressesTable, $row) === false) {
            throw new \RuntimeException('A cím nem menthető: ' . $this->database->last_error);
        }
        return $address->withId((int) $this->database->insert_id);
    }

    public function getByKeyForCustomer(string $key, int $customerId, bool $forUpdate = false): ?Address
    {
        $sql = $this->database->prepare(
            "SELECT * FROM {$this->addressesTable} WHERE address_key = %s AND customer_id = %d" . ($forUpdate ? ' FOR UPDATE' : ''),
            $key,
            $customerId
        );
        return $this->one($this->database->get_row($sql, ARRAY_A));
    }

    public function listForCustomer(int $customerId, bool $forUpdate = false): array
    {
        $sql = $this->database->prepare(
            "SELECT * FROM {$this->addressesTable} WHERE customer_id = %d ORDER BY updated_at DESC, id DESC" . ($forUpdate ? ' FOR UPDATE' : ''),
            $customerId
        );
        $rows = $this->database->get_results($sql, ARRAY_A);
        return array_map(static fn (array $row): Address => Address::reconstitute($row), is_array($rows) ? $rows : []);
    }

    public function update(Address $address, int $expectedVersion): Address
    {
        $row = $address->toArray();
        unset($row['id'], $row['address_key'], $row['customer_id'], $row['created_at']);
        $updated = $this->database->update(
            $this->addressesTable,
            $row,
            ['id' => $address->id(), 'customer_id' => $address->customerId(), 'version' => $expectedVersion]
        );
        if ($updated === 0) {
            throw new VersionConflict('A cím időközben megváltozott. Töltsd újra az oldalt.');
        }
        if ($updated === false) {
            throw new \RuntimeException('A cím nem frissíthető: ' . $this->database->last_error);
        }
        return $address;
    }

    public function delete(Address $address): void
    {
        if ($this->database->delete($this->addressesTable, [
            'id' => $address->id(),
            'customer_id' => $address->customerId(),
        ]) !== 1) {
            throw new \RuntimeException('A cím nem törölhető.');
        }
    }

    public function countActiveForCustomer(int $customerId): int
    {
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM {$this->addressesTable} WHERE customer_id = %d AND status = %s",
            $customerId,
            Address::STATUS_ACTIVE
        ));
    }

    public function findByLegacyFingerprint(int $customerId, string $fingerprint): ?Address
    {
        $sql = $this->database->prepare(
            "SELECT * FROM {$this->addressesTable} WHERE customer_id = %d AND legacy_fingerprint = %s LIMIT 1",
            $customerId,
            $fingerprint
        );
        return $this->one($this->database->get_row($sql, ARRAY_A));
    }

    public function getDefault(int $customerId, string $purpose, bool $forUpdate = false): ?Address
    {
        $sql = $this->database->prepare(
            "SELECT a.* FROM {$this->defaultsTable} d INNER JOIN {$this->addressesTable} a ON a.id = d.address_id AND a.customer_id = d.customer_id WHERE d.customer_id = %d AND d.purpose = %s" . ($forUpdate ? ' FOR UPDATE' : ''),
            $customerId,
            $purpose
        );
        return $this->one($this->database->get_row($sql, ARRAY_A));
    }

    public function setDefault(int $customerId, string $purpose, Address $address): void
    {
        if ($address->customerId() !== $customerId || ! $address->canBeDefault($purpose)) {
            throw new \RuntimeException('Érvénytelen alapértelmezett cím.');
        }
        $sql = $this->database->prepare(
            "INSERT INTO {$this->defaultsTable} (customer_id,purpose,address_id,version,updated_at) VALUES (%d,%s,%d,1,%s)
             ON DUPLICATE KEY UPDATE address_id = VALUES(address_id), version = version + 1, updated_at = VALUES(updated_at)",
            $customerId,
            $purpose,
            $address->id(),
            gmdate('Y-m-d H:i:s')
        );
        if ($this->database->query($sql) === false) {
            throw new \RuntimeException('Az alapértelmezett cím nem menthető: ' . $this->database->last_error);
        }
    }

    public function clearDefault(int $customerId, string $purpose): void
    {
        if ($this->database->delete($this->defaultsTable, ['customer_id' => $customerId, 'purpose' => $purpose]) === false) {
            throw new \RuntimeException('Az alapértelmezett cím nem törölhető.');
        }
    }

    /** @param array<string, mixed>|null $row */
    private function one(?array $row): ?Address
    {
        return $row === null ? null : Address::reconstitute($row);
    }
}
