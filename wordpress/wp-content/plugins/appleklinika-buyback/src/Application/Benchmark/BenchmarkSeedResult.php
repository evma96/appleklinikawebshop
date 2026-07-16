<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Benchmark;

final class BenchmarkSeedResult
{
    public function __construct(
        public readonly BenchmarkSeedPlan $plan,
        public readonly int $priceBookId,
        public readonly int $versionNumber,
        public readonly bool $created
    ) {
    }

    /** @return array<string, int|string|bool|null> */
    public function toArray(): array
    {
        return $this->plan->toArray() + [
            'price_book_id' => $this->priceBookId,
            'version_number' => $this->versionNumber,
            'created' => $this->created,
            'status' => 'draft',
            'activated' => false,
        ];
    }
}
