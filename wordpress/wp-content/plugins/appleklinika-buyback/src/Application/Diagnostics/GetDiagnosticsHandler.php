<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Diagnostics;

use AppleKlinika\Buyback\Application\Port\EnvironmentDiagnosticsReader;
use AppleKlinika\Buyback\Application\Port\LegacyDiagnosticsReader;
use AppleKlinika\Buyback\Application\Port\SchemaDiagnosticsReader;

final class GetDiagnosticsHandler
{
    public function __construct(
        private readonly SchemaDiagnosticsReader $schemaReader,
        private readonly EnvironmentDiagnosticsReader $environmentReader,
        private readonly LegacyDiagnosticsReader $legacyReader,
        private readonly string $pluginVersion,
        private readonly string $codeSchemaVersion
    ) {
    }

    public function handle(GetDiagnosticsQuery $query): DiagnosticsReport
    {
        return new DiagnosticsReport(
            $this->pluginVersion,
            $this->codeSchemaVersion,
            $this->schemaReader->installedVersion(),
            $this->schemaReader->migrationStatus(),
            $this->schemaReader->tables(),
            $this->environmentReader->summary(),
            $this->legacyReader->summary()
        );
    }
}
