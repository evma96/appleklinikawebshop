<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\OfferModeSettingsStore;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;

final class WordPressOfferModeSettingsStore implements OfferModeSettingsStore
{
    public const OPTION_NAME = 'ak_buyback_global_offer_mode_settings';

    public function get(): OfferModeConfiguration
    {
        $stored = get_option(self::OPTION_NAME, null);
        return OfferModeConfiguration::fromStored(is_array($stored) ? $stored : null);
    }

    public function save(OfferModeConfiguration $configuration): void
    {
        update_option(self::OPTION_NAME, $configuration->toStored(), false);
    }
}
