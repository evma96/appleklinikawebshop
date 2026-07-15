<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\EnvironmentDiagnosticsReader;

final class WordPressEnvironmentDiagnosticsReader implements EnvironmentDiagnosticsReader
{
    public function summary(): array
    {
        $theme = wp_get_theme();

        return [
            'active_theme' => sprintf(
                '%s %s',
                sanitize_text_field((string) $theme->get('Name')),
                sanitize_text_field((string) $theme->get('Version'))
            ),
            'woocommerce_active' => Requirements::isWooCommerceAvailable(),
        ];
    }
}
