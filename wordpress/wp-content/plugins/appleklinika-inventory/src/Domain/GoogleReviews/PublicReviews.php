<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Domain\GoogleReviews;

/** Public projection only. Never synthesize ratings, names, comments or dates. */
final class PublicReviews
{
    // CID from the existing owner-approved Google Maps embed (0xf8dc8aa6ed1595e7).
    public const CID = '17932360265829291495';
    public const PROFILE_URL = 'https://www.google.com/maps?cid=' . self::CID;
    public const MAX_AGE = 86400;
    public const MAX_REVIEWS = 6;

    public static function googleUrl(mixed $value): string
    {
        if (!is_string($value)) { return ''; }
        $p = parse_url($value);
        return is_array($p) && ($p['scheme'] ?? '') === 'https'
            && in_array($p['host'] ?? '', ['www.google.com', 'maps.google.com', 'search.google.com', 'g.page'], true)
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']) ? $value : '';
    }

    public static function avatarUrl(mixed $value): string
    {
        if (!is_string($value)) { return ''; }
        $p = parse_url($value);
        $host = strtolower((string) ($p['host'] ?? ''));
        return is_array($p) && ($p['scheme'] ?? '') === 'https'
            && ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'))
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']) ? $value : '';
    }

    public static function matchesStore(array $location): bool
    {
        $url = self::googleUrl($location['metadata']['mapsUri'] ?? '');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        return (string) ($query['cid'] ?? '') === self::CID;
    }

    public static function fromSnapshot(array $snapshot, int $now): ?array
    {
        if (isset($snapshot['fixture']) || isset($snapshot['qa'])) { return null; }
        $at = $snapshot['fetched_at'] ?? 0;
        if (!is_int($at) || $at > $now || $at <= $now - self::MAX_AGE
            || !is_array($snapshot['location'] ?? null) || !self::matchesStore($snapshot['location'])
            || !is_array($snapshot['response'] ?? null)) { return null; }
        $data = $snapshot['response'];
        $count = $data['totalReviewCount'] ?? null;
        $rating = $data['averageRating'] ?? null;
        if (!is_int($count) || $count < 0 || ($count > 0 && (!is_numeric($rating) || $rating < 1 || $rating > 5))) { return null; }
        $reviews = [];
        $stars = ['ONE'=>1, 'TWO'=>2, 'THREE'=>3, 'FOUR'=>4, 'FIVE'=>5];
        foreach (array_slice(is_array($data['reviews'] ?? null) ? $data['reviews'] : [], 0, self::MAX_REVIEWS) as $review) {
            if (!is_array($review)) { continue; }
            $name = $review['reviewer']['displayName'] ?? '';
            $score = $stars[$review['starRating'] ?? ''] ?? null;
            if (!is_string($name) || trim($name) === '' || $score === null) { continue; }
            $date = $review['updateTime'] ?? $review['createTime'] ?? '';
            $reviews[] = ['name'=>$name, 'rating'=>$score,
                'avatar'=>self::avatarUrl($review['reviewer']['profilePhotoUrl'] ?? ''),
                'text'=>is_string($review['comment'] ?? null) ? $review['comment'] : '',
                'date'=>is_string($date) && strtotime($date) !== false ? $date : ''];
        }
        return ['rating'=>$count > 0 ? (float) $rating : null, 'count'=>$count,
            'reviews'=>$reviews, 'fetched_at'=>$at, 'profile_url'=>self::PROFILE_URL,
            'write_url'=>self::googleUrl($snapshot['location']['metadata']['newReviewUri'] ?? '')];
    }
}
