<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;

/** Explicit owner-authorized LOCAL UI fixture. Never a Google response/cache. */
final class LocalReviewPreview
{
    public static function allowed(): bool
    {
        $home = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
        $request = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $loopback = ['localhost', '127.0.0.1', '[::1]'];
        return wp_get_environment_type() === 'local'
            && getenv('AK_LOCAL_VERIFICATION') === '1'
            && in_array($home, $loopback, true) && in_array($request, $loopback, true);
    }

    public static function read(int $now): ?array
    {
        if (!self::allowed()) { return null; }
        $path = realpath((string) getenv('AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE'));
        $root = realpath(ABSPATH);
        if (!$path || !$root || str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            || !is_file($path) || !is_readable($path) || filesize($path) > 65536) { return null; }
        $fixture = json_decode((string) file_get_contents($path), true);
        if (!is_array($fixture) || ($fixture['fixture'] ?? '') !== 'appleklinika-local-review-qa-v1'
            || !is_array($fixture['response']['reviews'] ?? null)) { return null; }
        foreach ($fixture['response']['reviews'] as $review) {
            if (!is_array($review) || !str_starts_with((string) ($review['reviewer']['displayName'] ?? ''), 'QA ')
                || !str_starts_with((string) ($review['comment'] ?? ''), 'QA-minta:')) { return null; }
        }
        $data = PublicReviews::fromSnapshot(['fetched_at'=>$now,
            'location'=>['metadata'=>['mapsUri'=>PublicReviews::PROFILE_URL]],
            'response'=>$fixture['response']], $now);
        if ($data === null) { return null; }
        $data['qa'] = true;
        // Neutral, generated QA avatar: no real person or external image request.
        foreach ($data['reviews'] as $i => &$review) { $review['qa_avatar'] = $i % 3; }
        unset($review);
        return $data;
    }
}
