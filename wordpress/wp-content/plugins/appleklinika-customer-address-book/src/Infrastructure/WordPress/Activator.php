<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WordPress;

use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\MigrationRunner;

final class Activator
{
    public static function activate(): void
    {
        Requirements::assertSatisfied();
        global $wpdb;
        (new MigrationRunner($wpdb))->run();
        add_rewrite_endpoint('cimeim', EP_ROOT | EP_PAGES);
        flush_rewrite_rules(false);
    }
}
