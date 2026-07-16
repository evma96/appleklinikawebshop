<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Benchmark;

use AppleKlinika\Buyback\Application\Benchmark\BenchmarkSeedRegistration;
use AppleKlinika\Buyback\Application\Exception\BenchmarkSeedReservationExistsException;
use AppleKlinika\Buyback\Application\Port\BenchmarkSeedRegistry;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressBenchmarkSeedRegistry implements BenchmarkSeedRegistry
{
    private const PREFIX = 'appleklinika_buyback_seed_';

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function find(string $seedKey): ?BenchmarkSeedRegistration
    {
        $value = $this->database->get_var($this->database->prepare(
            "SELECT option_value FROM `{$this->database->options}` WHERE option_name = %s LIMIT 1",
            $this->optionName($seedKey)
        ));
        if ($value === null) {
            return null;
        }
        $data = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['seed_key'] ?? null) !== $seedKey) {
            throw new PersistenceException('Benchmark seed registry entry is corrupt.');
        }
        return new BenchmarkSeedRegistration(
            (string) ($data['manifest_hash'] ?? ''),
            $seedKey,
            (string) ($data['label'] ?? ''),
            (string) ($data['state'] ?? ''),
            isset($data['price_book_id']) ? (int) $data['price_book_id'] : null,
            isset($data['aggregate_version']) ? (int) $data['aggregate_version'] : null,
            isset($data['rules_hash']) ? (string) $data['rules_hash'] : null
        );
    }

    public function reserve(string $manifestHash, string $seedKey, string $label): void
    {
        $value = $this->json([
            'manifest_hash' => $manifestHash,
            'seed_key' => $seedKey,
            'label' => $label,
            'state' => 'pending',
            'price_book_id' => null,
            'aggregate_version' => null,
            'rules_hash' => null,
        ]);
        $result = $this->database->insert($this->database->options, [
            'option_name' => $this->optionName($seedKey),
            'option_value' => $value,
            'autoload' => 'no',
        ], ['%s', '%s', '%s']);
        if ($result === 1) {
            return;
        }
        if ($this->find($seedKey) !== null) {
            throw new BenchmarkSeedReservationExistsException('Benchmark seed key is already registered.');
        }
        throw new PersistenceException('Benchmark seed reservation could not be created.');
    }

    public function complete(string $seedKey, int $priceBookId, int $aggregateVersion, string $rulesHash): void
    {
        $current = $this->find($seedKey);
        if ($current === null || $current->state !== 'pending') {
            throw new PersistenceException('Benchmark seed reservation is not pending.');
        }
        $value = $this->json([
            'manifest_hash' => $current->manifestHash,
            'seed_key' => $current->seedKey,
            'label' => $current->label,
            'state' => 'complete',
            'price_book_id' => $priceBookId,
            'aggregate_version' => $aggregateVersion,
            'rules_hash' => $rulesHash,
        ]);
        $result = $this->database->update(
            $this->database->options,
            ['option_value' => $value],
            ['option_name' => $this->optionName($seedKey)],
            ['%s'],
            ['%s']
        );
        if ($result !== 1) {
            throw new PersistenceException('Benchmark seed reservation could not be completed.');
        }
    }

    private function optionName(string $seedKey): string
    {
        if (preg_match('/^[a-z0-9._-]{3,120}$/', $seedKey) !== 1) {
            throw new PersistenceException('Benchmark seed key is invalid.');
        }
        return self::PREFIX . hash('sha256', $seedKey);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
