<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface EnvironmentDiagnosticsReader
{
    /**
     * @return array{active_theme: string, woocommerce_active: bool}
     */
    public function summary(): array;
}
