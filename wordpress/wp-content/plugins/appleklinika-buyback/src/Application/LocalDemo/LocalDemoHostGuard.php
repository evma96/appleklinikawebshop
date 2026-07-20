<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

final class LocalDemoHostGuard
{
    private const ALLOWED_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function assertLocal(string $siteUrl, string $homeUrl): void
    {
        foreach ([$siteUrl, $homeUrl] as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (! in_array($host, self::ALLOWED_HOSTS, true)) {
                throw new \RuntimeException('The local buyback demo is restricted to localhost.');
            }
        }
    }
}
