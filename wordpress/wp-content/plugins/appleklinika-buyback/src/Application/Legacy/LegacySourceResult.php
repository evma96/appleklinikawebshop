<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacySourceResult
{
    /** @param list<LegacyBuybackRecord> $records */
    public function __construct(
        public readonly int $usersScanned,
        public readonly array $records
    ) {
    }
}
