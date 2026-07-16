<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Benchmark;

final class BenchmarkSeedRegistration
{
    public function __construct(
        public readonly string $manifestHash,
        public readonly string $seedKey,
        public readonly string $label,
        public readonly string $state,
        public readonly ?int $priceBookId,
        public readonly ?int $aggregateVersion,
        public readonly ?string $rulesHash
    ) {
    }
}
