<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Diagnostics;

use AppleKlinika\Buyback\Application\Port\EnvironmentDiagnosticsReader;
use AppleKlinika\Buyback\Application\Port\LegacyDiagnosticsReader;
use AppleKlinika\Buyback\Application\Port\SchemaDiagnosticsReader;
use AppleKlinika\Buyback\Application\Port\ActivePriceBookResolver;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\NoActivePriceBookException;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;

final class GetDiagnosticsHandler
{
    public function __construct(
        private readonly SchemaDiagnosticsReader $schemaReader,
        private readonly EnvironmentDiagnosticsReader $environmentReader,
        private readonly LegacyDiagnosticsReader $legacyReader,
        private readonly string $pluginVersion,
        private readonly string $codeSchemaVersion,
        private readonly ActivePriceBookResolver $activePriceBookResolver,
        private readonly Clock $clock
    ) {
    }

    public function handle(GetDiagnosticsQuery $query): DiagnosticsReport
    {
        $pricing = [
            'status' => 'none',
            'book_id' => null,
            'version_number' => null,
            'label' => null,
            'active_rule_count' => 0,
            'supported_configuration_count' => 0,
            'effective_from' => null,
        ];
        try {
            $resolved = $this->activePriceBookResolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $this->clock->now());
            $pricing = [
                'status' => 'active',
                'book_id' => $resolved->priceBook->id()?->toInt(),
                'version_number' => $resolved->priceBook->versionNumber()->value(),
                'label' => $resolved->priceBook->label(),
                'active_rule_count' => count($resolved->enabledRules),
                'supported_configuration_count' => count($resolved->supportedConfigurations),
                'effective_from' => $resolved->priceBook->effectiveFrom()?->format(DATE_ATOM),
            ];
        } catch (NoActivePriceBookException $exception) {
            // A missing live price book is an expected pre-launch state.
        } catch (MultipleActivePriceBooksException $exception) {
            $pricing['status'] = 'corrupt_multiple_active';
        }

        return new DiagnosticsReport(
            $this->pluginVersion,
            $this->codeSchemaVersion,
            $this->schemaReader->installedVersion(),
            $this->schemaReader->migrationStatus(),
            $this->schemaReader->tables(),
            $this->environmentReader->summary(),
            $this->legacyReader->summary(),
            $pricing
        );
    }
}
