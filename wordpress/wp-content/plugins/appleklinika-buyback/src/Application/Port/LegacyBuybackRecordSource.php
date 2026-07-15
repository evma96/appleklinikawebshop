<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Legacy\LegacySourceResult;

interface LegacyBuybackRecordSource
{
    public function read(?int $userId = null): LegacySourceResult;
}
