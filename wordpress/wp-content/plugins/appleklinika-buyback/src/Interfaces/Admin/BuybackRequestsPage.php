<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

/** Read-only operational view of immutable public Buyback submissions. */
final class BuybackRequestsPage
{
    public const SLUG = 'appleklinika-buyback-requests';

    public function __construct(private readonly WordPressPublicBuybackRequestStore $store)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page('woocommerce', 'Apple Klinika Buyback – Beérkezett igények', 'Buyback – Beérkezett igények', CapabilityManager::VIEW_BUYBACK_REQUESTS, self::SLUG, [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can(CapabilityManager::VIEW_BUYBACK_REQUESTS)) {
            wp_die(esc_html('Nincs jogosultságod a felvásárlási igények megtekintéséhez.'));
        }
        $detailId = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
        echo '<div class="wrap ak-buyback-admin"><h1>Apple Klinika Felvásárlás – Beérkezett igények</h1>';
        if ($detailId > 0) {
            $this->renderDetail($detailId);
        } else {
            $this->renderList();
        }
        echo '</div>';
    }

    private function renderList(): void
    {
        $rows = $this->store->listRecent();
        if ($rows === []) {
            echo '<p>Még nem érkezett felvásárlási igény.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>Hivatkozás</th><th>Beküldve</th><th>Ügyfél</th><th>Elérhetőség</th><th>Készülék</th><th>Ajánlattípus</th><th>Státusz</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $link = add_query_arg(['page' => self::SLUG, 'request_id' => (int) $row['id']], admin_url('admin.php'));
            $mode = $this->requestIntentLabel($row);
            echo '<tr><td><a href="' . esc_url($link) . '">' . esc_html((string) $row['request_number']) . '</a></td><td>' . esc_html((string) $row['submitted_at']) . '</td><td>' . esc_html((string) $row['customer_name']) . '</td><td>' . esc_html((string) $row['customer_email']) . '<br>' . esc_html((string) $row['customer_phone']) . '</td><td>' . esc_html((string) $row['device_display_name']) . '</td><td>' . esc_html($mode) . '</td><td>' . esc_html((string) $row['status']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function renderDetail(int $id): void
    {
        $detail = $this->store->detail($id);
        $back = add_query_arg('page', self::SLUG, admin_url('admin.php'));
        echo '<p><a href="' . esc_url($back) . '">← Vissza az igényekhez</a></p>';
        if ($detail === null) {
            echo '<div class="notice notice-error"><p>Az igény nem található.</p></div>';
            return;
        }
        $request = $detail['request'];
        echo '<h2>' . esc_html((string) $request['request_number']) . '</h2>';
        echo '<table class="widefat"><tbody>';
        foreach (['customer_name' => 'Név', 'customer_email' => 'E-mail', 'customer_phone' => 'Telefonszám', 'device_display_name' => 'Készülék', 'status' => 'Státusz', 'submitted_at' => 'Beküldve'] as $key => $label) {
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string) ($request[$key] ?? '')) . '</td></tr>';
        }
        echo '<tr><th>Igény típusa</th><td>' . esc_html($this->requestIntentLabel($request)) . '</td></tr>';
        echo '</tbody></table>';
        $snapshot = $detail['snapshot'];
        if ($snapshot !== null) {
            $payload = json_decode((string) $snapshot['payload_json'], true);
            echo '<h2>Rögzített számítási pillanatkép</h2>';
            echo '<p>Checksum: <code>' . esc_html((string) $snapshot['checksum']) . '</code></p>';
            if (is_array($payload) && (($payload['calculation']['status'] ?? $payload['calculation_status'] ?? null) === 'manual_review')) {
                echo '<h3>Személyes bevizsgálás szükséges</h3><p>Ehhez az igényhez nem tartozik kiválasztott ajánlattípus vagy előzetes összeg.</p>';
                $reasons = is_array($payload['calculation']['reasons'] ?? null) ? $payload['calculation']['reasons'] : [];
                if ($reasons !== []) {
                    echo '<h4>Rögzített okok</h4><ul>';
                    foreach ($reasons as $reason) {
                        echo '<li>' . esc_html((string) $reason) . '</li>';
                    }
                    echo '</ul>';
                }
            }
            echo '<pre style="white-space:pre-wrap;max-width:100%;overflow:auto">' . esc_html((string) wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }
        echo '<h2>Eseménytörténet</h2><ul>';
        foreach ($detail['events'] as $event) {
            echo '<li><strong>' . esc_html((string) $event['created_at']) . '</strong> — ' . esc_html((string) $event['event_type']) . ' ' . esc_html((string) ($event['public_summary'] ?? '')) . '</li>';
        }
        echo '</ul>';
    }

    /** @param array<string,mixed> $request */
    private function requestIntentLabel(array $request): string
    {
        $mode = $request['service_mode'] ?? null;
        if ($mode === null || $mode === '') {
            return 'Személyes bevizsgálást kérek';
        }

        return OfferModeDefinition::all()[(string) $mode]['label'] ?? (string) $mode;
    }
}
