<?php

declare(strict_types=1);

use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\CoreSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\PricingSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\PublicRequestSchemaMigration;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\ManualReviewRequestSchemaMigration;

return [
    CoreSchemaMigration::class,
    PricingSchemaMigration::class,
    PublicRequestSchemaMigration::class,
    ManualReviewRequestSchemaMigration::class,
];
