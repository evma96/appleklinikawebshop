<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Migration;

use AppleKlinika\Buyback\Domain\SchemaVersion;

interface Migration
{
    public function version(): SchemaVersion;

    public function up(): void;
}
