<?php

declare(strict_types=1);

use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\CoreSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\PricingSchemaMigration;

return [
    CoreSchemaMigration::class,
    PricingSchemaMigration::class,
];
