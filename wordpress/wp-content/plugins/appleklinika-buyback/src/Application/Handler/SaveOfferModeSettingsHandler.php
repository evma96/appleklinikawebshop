<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveOfferModeSettings;
use AppleKlinika\Buyback\Application\Port\OfferModeSettingsStore;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;

final class SaveOfferModeSettingsHandler
{
    public function __construct(private readonly OfferModeSettingsStore $settings)
    {
    }

    public function handle(SaveOfferModeSettings $command): OfferModeConfiguration
    {
        $configuration = OfferModeConfiguration::fromSubmitted($command->modes);
        $this->settings->save($configuration);

        return $configuration;
    }
}
