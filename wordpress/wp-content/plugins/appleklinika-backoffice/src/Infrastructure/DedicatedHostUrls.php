<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

/** Keeps native WordPress login, cookies and application links on one trusted host. */
final class DedicatedHostUrls
{
    public const OPTION = 'appleklinika_backoffice_origin';

    private function __construct(private string $origin, private array $sourceAuthorities)
    {
    }

    public static function register(): void
    {
        $urls = self::forRequest(
            get_option(self::OPTION, ''),
            $_SERVER['HTTP_HOST'] ?? '',
            [get_option('home'), get_option('siteurl')]
        );
        if ($urls === null) {
            return;
        }

        // wp_login_url(), the login POST form, admin-post and logout all use
        // these core URL builders. Do not widen cookies or redirect allowlists.
        foreach (['home_url', 'site_url', 'network_site_url'] as $hook) {
            add_filter($hook, [$urls, 'rewrite']);
        }
    }

    public static function forRequest(mixed $origin, mixed $requestHost, array $sourceUrls): ?self
    {
        if (! is_string($origin) || ! is_string($requestHost)
            || preg_match('/[\s\\\\]/', $origin . $requestHost)) {
            return null;
        }
        $parts = parse_url($origin);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ! preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/i', $parts['host'])
            || (isset($parts['port']) && $parts['port'] < 1)) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if ($scheme !== 'https' && ! ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true))) {
            return null;
        }
        $authority = self::authority($parts);
        // Exact match only. Never trust forwarded hosts, suffixes or return URLs.
        if (strtolower($requestHost) !== $authority) {
            return null;
        }
        $sources = [];
        foreach ($sourceUrls as $source) {
            $parsed = is_string($source) ? parse_url($source) : false;
            if (is_array($parsed) && isset($parsed['host'])) {
                $sources[] = self::authority($parsed);
            }
        }

        return new self($scheme . '://' . $authority, $sources);
    }

    public function rewrite(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || ! in_array(self::authority($parts), $this->sourceAuthorities, true)) {
            return $url;
        }

        return $this->origin . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private static function authority(array $parts): string
    {
        return strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
