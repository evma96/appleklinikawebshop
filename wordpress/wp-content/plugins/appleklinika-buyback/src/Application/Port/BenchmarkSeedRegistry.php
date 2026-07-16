<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Benchmark\BenchmarkSeedRegistration;

interface BenchmarkSeedRegistry
{
    public function find(string $seedKey): ?BenchmarkSeedRegistration;

    public function reserve(string $manifestHash, string $seedKey, string $label): void;

    public function complete(string $seedKey, int $priceBookId, int $aggregateVersion, string $rulesHash): void;
}
