<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;
use Appleklinika\Inventory\Application\GoogleReviews\ReviewGateway;
use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;

final class GoogleBusinessReviews implements ReviewGateway
{
    private const CACHE = 'ak_google_reviews_public_v1';
    private const LOCK = 'ak_google_reviews_refresh_lock';
    private array $config;
    public function __construct()
    {
        $this->config = [];
        $path = defined('AK_GOOGLE_REVIEWS_CONFIG_FILE') ? constant('AK_GOOGLE_REVIEWS_CONFIG_FILE') : getenv('AK_GOOGLE_REVIEWS_CONFIG_FILE');
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) { return; }
        $real = realpath($path);
        // A secret file must be outside the publicly served WordPress directory.
        if ($real === false || str_starts_with($real, rtrim((string) realpath(ABSPATH), '/') . '/')) { return; }
        $json = json_decode((string) file_get_contents($real), true);
        if (!is_array($json)) { return; }
        foreach (['client_id','client_secret','refresh_token','account_id','location_id'] as $key) {
            if (!is_string($json[$key] ?? null) || trim($json[$key]) === '') { return; }
        }
        if (!ctype_digit($json['account_id']) || !ctype_digit($json['location_id'])) { return; }
        $this->config = $json;
    }
    public function configured(): bool { return $this->config !== []; }
    private function identity(): string { return hash('sha256', ($this->config['account_id'] ?? '') . ':' . ($this->config['location_id'] ?? '')); }
    public function load(): array
    {
        $cached = get_transient(self::CACHE);
        return is_array($cached) && ($cached['identity'] ?? '') === $this->identity() ? $cached : [];
    }
    public function save(array $snapshot): void
    {
        // Raw, unmodified Google payload; bounded to six reviews and 24 hours.
        set_transient(self::CACHE, $snapshot + ['identity'=>$this->identity()], PublicReviews::MAX_AGE);
    }
    public function clear(): void { delete_transient(self::CACHE); }
    public function lock(): bool
    {
        if (get_transient('ak_google_reviews_backoff')) { return false; }
        $old = (int) get_option(self::LOCK, 0);
        if ($old > 0 && $old < time() - 120) { delete_option(self::LOCK); }
        return add_option(self::LOCK, time(), '', false);
    }
    public function unlock(): void { delete_option(self::LOCK); }
    public function status(string $status): void
    {
        update_option('ak_google_reviews_status', ['state'=>$status, 'checked_at'=>time()], false);
        if ($status === 'unavailable') { set_transient('ak_google_reviews_backoff', 1, 900); }
        else { delete_transient('ak_google_reviews_backoff'); }
    }
    private function json(mixed $response): array
    {
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new \RuntimeException('google_unavailable'); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) { throw new \RuntimeException('invalid_response'); }
        return $data;
    }
    public function fetch(): array
    {
        if (!$this->configured()) { throw new \RuntimeException('not_configured'); }
        $options = ['timeout'=>8, 'redirection'=>0, 'limit_response_size'=>131072, 'sslverify'=>true];
        $auth = $this->json(wp_remote_post('https://oauth2.googleapis.com/token', $options + ['body'=>[
            'client_id'=>$this->config['client_id'], 'client_secret'=>$this->config['client_secret'],
            'refresh_token'=>$this->config['refresh_token'], 'grant_type'=>'refresh_token',
        ]]));
        if (!is_string($auth['access_token'] ?? null) || $auth['access_token'] === '') { throw new \RuntimeException('oauth_unavailable'); }
        $options['headers'] = ['Authorization'=>'Bearer ' . $auth['access_token']];
        $location = $this->json(wp_remote_get('https://mybusinessbusinessinformation.googleapis.com/v1/locations/' . $this->config['location_id'] . '?readMask=metadata', $options));
        if (!PublicReviews::matchesStore($location)) { throw new \RuntimeException('wrong_business'); }
        $response = $this->json(wp_remote_get('https://mybusiness.googleapis.com/v4/accounts/' . $this->config['account_id'] . '/locations/' . $this->config['location_id'] . '/reviews?pageSize=6&orderBy=updateTime%20desc', $options));
        return ['location'=>$location, 'response'=>$response];
    }
}
