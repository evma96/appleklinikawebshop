<?php

declare(strict_types=1);

use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\CoreSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\PricingSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\PublicRequestSchemaMigration;

return [
    CoreSchemaMigration::class,
    PricingSchemaMigration::class,
    PublicRequestSchemaMigration::class,
];
