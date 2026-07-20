<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

final class LocalDemoSeedResult
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $pageId,
        public readonly int $modelCount,
        public readonly int $configurationCount,
        public readonly int $ruleCount,
        public readonly bool $created
    ) {
    }
}
