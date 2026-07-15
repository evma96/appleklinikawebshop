<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface LegacyModelResolver
{
    public function resolve(string $deviceDisplayName): ?string;
}
