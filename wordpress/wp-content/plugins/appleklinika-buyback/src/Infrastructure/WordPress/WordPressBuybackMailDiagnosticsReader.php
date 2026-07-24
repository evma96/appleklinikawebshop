<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\BuybackMailDiagnosticsReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;

final class WordPressBuybackMailDiagnosticsReader implements BuybackMailDiagnosticsReader
{
    public function __construct(
        private readonly BuybackSmtpConfiguration $configuration,
        private readonly WordPressPublicBuybackRequestStore $store
    ) {
    }

    public function summary(): array
    {
        return array_merge($this->configuration->diagnostics(), $this->store->recentMailStatuses());
    }
}
