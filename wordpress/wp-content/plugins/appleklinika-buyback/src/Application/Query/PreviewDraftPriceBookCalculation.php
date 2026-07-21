<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Query;

final class PreviewDraftPriceBookCalculation
{
    /** @param array<string, mixed> $questionnaireState */
    public function __construct(
        public readonly int $priceBookId,
        public readonly string $modelKey,
        public readonly int $storageGb,
        public readonly array $questionnaireState,
        public readonly string $colorKey = ''
    ) {
    }
}
