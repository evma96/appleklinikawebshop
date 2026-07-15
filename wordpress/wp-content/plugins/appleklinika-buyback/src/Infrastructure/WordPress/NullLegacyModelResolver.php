<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\LegacyModelResolver;

final class NullLegacyModelResolver implements LegacyModelResolver
{
    public function resolve(string $deviceDisplayName): ?string
    {
        return null;
    }
}
