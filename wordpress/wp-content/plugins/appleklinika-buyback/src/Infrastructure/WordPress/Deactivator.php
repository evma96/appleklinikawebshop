<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

final class Deactivator
{
    public static function deactivate(): void
    {
        (new CapabilityManager())->revoke();
    }
}
