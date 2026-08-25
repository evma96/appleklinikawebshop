<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;

interface OfferModeSettingsStore
{
    public function get(): OfferModeConfiguration;

    public function save(OfferModeConfiguration $configuration): void;
}
