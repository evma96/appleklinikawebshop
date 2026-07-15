<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;

final class Activator
{
    public static function activate(): void
    {
        Requirements::assertSatisfied();

        $capabilities = new CapabilityManager();
        $capabilities->grant();

        try {
            Plugin::migrationRunner()->run();
            update_option(Schema::OPTION_PLUGIN_VERSION, APPLEKLINIKA_BUYBACK_VERSION, false);
        } catch (\Throwable $exception) {
            $capabilities->revoke();
            throw $exception;
        }
    }
}
