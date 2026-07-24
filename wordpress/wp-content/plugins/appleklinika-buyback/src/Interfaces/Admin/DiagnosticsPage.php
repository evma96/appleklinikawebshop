<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Diagnostics\DiagnosticsReport;
use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsQuery;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\LocalDemo\VisualStateCatalogue;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

final class DiagnosticsPage
{
    public const SLUG = 'appleklinika-buyback';

    public function __construct(private readonly GetDiagnosticsHandler $handler)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            'Apple Klinika Buyback diagnosztika',
            'Buyback – Diagnosztika',
            CapabilityManager::VIEW_DIAGNOSTICS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function canView(): bool
    {
        return current_user_can(CapabilityManager::VIEW_DIAGNOSTICS);
    }

    public function render(): void
    {
        if (! $this->canView()) {
            wp_die(esc_html('Nincs jogosultságod a buyback diagnosztika megtekintéséhez.'));
        }

        $report = $this->handler->handle(new GetDiagnosticsQuery());

        echo '<div class="wrap">';
        echo '<h1>Apple Klinika Buyback diagnosztika</h1>';
        echo '<nav class="nav-tab-wrapper">';
        echo '<a class="nav-tab nav-tab-active" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Diagnosztika</a>';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=' . PriceBooksPage::SLUG)) . '">Árkönyvek</a>';
        echo '</nav>';
        echo '<p>Ez az oldal kizárólag olvasási célú rendszerállapotot mutat. Nem tartalmaz importálási vagy módosítási műveletet.</p>';

        $this->renderSystemTable($report);
        $this->renderMailTable($report);
        $this->renderPricingTable($report);
        $this->renderVisualStateGallery();
        $this->renderSchemaTable($report);
        $this->renderLegacyTable($report);

        echo '</div>';
    }

    private function renderVisualStateGallery(): void
    {
        $catalogue = new VisualStateCatalogue(new LocalDemoQuestionnaire());

        echo '<h2>Publikus állapotillusztrációk</h2>';
        echo '<p>Csak olvasási ellenőrző nézet. A végleges WebP fájlok a megadott útvonalra helyezhetők; hiányuk esetén a publikus felület biztonságos átmeneti illusztrációt használ.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Állapot</th><th>Forrás</th><th>Nézet</th><th>Végleges fájl</th><th>Jelenlegi asset</th><th>Állapot</th></tr></thead><tbody>';

        foreach ($catalogue->entries() as $entry) {
            $exists = is_file(APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['expected_path']);
            $fallbackExists = is_file(APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['fallback_path']);
            echo '<tr>';
            echo '<td>' . esc_html($entry['answer_label']) . '</td>';
            echo '<td>' . esc_html($entry['question_label'] . ' → ' . $entry['answer_label']) . '</td>';
            echo '<td>' . esc_html($this->viewTypeLabel($entry['view_type'])) . '</td>';
            echo '<td><code>' . esc_html($entry['expected_path']) . '</code></td>';
            echo '<td><code>' . esc_html($exists ? $entry['expected_path'] : $entry['fallback_path']) . '</code></td>';
            echo '<td>' . esc_html($exists ? 'Végleges fájl elérhető' : ($fallbackExists ? 'Átmeneti fallback aktív' : 'Hiányzik')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderPricingTable(DiagnosticsReport $report): void
    {
        echo '<h2>Aktív HUF árkönyv</h2>';
        if ($report->pricing['status'] === 'corrupt_multiple_active') {
            echo '<div class="notice notice-error inline"><p>Több egyidejűleg aktív HUF árkönyv található. Az élő árkönyv nem oldható fel biztonságosan.</p></div>';
        }
        echo '<table class="widefat striped"><tbody>';
        $status = match ($report->pricing['status']) {
            'active' => 'Aktív',
            'corrupt_multiple_active' => 'Hibás: több aktív árkönyv',
            default => 'Nincs aktív HUF árkönyv',
        };
        $this->row('Állapot', $status);
        $this->row('Árkönyv ID', $report->pricing['book_id'] === null ? '–' : (string) $report->pricing['book_id']);
        $this->row('Verzió', $report->pricing['version_number'] === null ? '–' : 'v' . $report->pricing['version_number']);
        $this->row('Megnevezés', $report->pricing['label'] ?? '–');
        $this->row('Aktív szabályok', (string) $report->pricing['active_rule_count']);
        $this->row('Támogatott modell/tárhely párok', (string) $report->pricing['supported_configuration_count']);
        $this->row('Hatályos ettől', $report->pricing['effective_from'] ?? '–');
        echo '</tbody></table>';
    }

    private function renderSystemTable(DiagnosticsReport $report): void
    {
        echo '<h2>Rendszer</h2>';
        echo '<table class="widefat striped"><tbody>';
        $this->row('Plugin verzió', $report->pluginVersion);
        $this->row('Kód séma verzió', $report->codeSchemaVersion);
        $this->row('Telepített séma verzió', $report->installedSchemaVersion);
        $this->row('Migráció állapota', $report->migrationStatus);
        $this->row('Aktív téma', $report->environment['active_theme']);
        $this->row('WooCommerce aktív', $report->environment['woocommerce_active'] ? 'Igen' : 'Nem');
        echo '</tbody></table>';
    }

    private function renderMailTable(DiagnosticsReport $report): void
    {
        echo '<h2>Buyback e-mail kézbesítés</h2>';
        if (! $report->mail['configured']) {
            echo '<div class="notice notice-warning inline"><p>Az SMTP nincs teljesen konfigurálva. A felvásárlási igény mentése nem sérül, de e-mail értesítés nem kerül elküldésre. Hiányzó vagy hibás értékek: ' . esc_html(implode(', ', $report->mail['missing'])) . '.</p></div>';
        }
        echo '<table class="widefat striped"><tbody>';
        $this->row('SMTP konfigurálva', $report->mail['configured'] ? 'Igen' : 'Nem');
        $this->row('SMTP host', $report->mail['host']);
        $this->row('Port', $report->mail['port']);
        $this->row('Titkosítás', $report->mail['encryption']);
        $this->row('SMTP felhasználó', $report->mail['username']);
        $this->row('Feladó', $report->mail['from']);
        $this->row('Admin címzett', $report->mail['admin']);
        $this->row('Utolsó ügyfélértesítés', $report->mail['last_customer']);
        $this->row('Utolsó admin értesítés', $report->mail['last_admin']);
        echo '</tbody></table>';
    }

    private function renderSchemaTable(DiagnosticsReport $report): void
    {
        echo '<h2>Phase 1A táblák</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Tábla</th><th>Létezik</th><th>Sorok</th><th>Hiányzó oszlopok</th><th>Hiányzó indexek</th></tr></thead><tbody>';

        foreach ($report->tables as $table) {
            echo '<tr>';
            echo '<td><code>' . esc_html($table['name']) . '</code></td>';
            echo '<td>' . esc_html($table['exists'] ? 'Igen' : 'Nem') . '</td>';
            echo '<td>' . esc_html((string) $table['row_count']) . '</td>';
            echo '<td>' . esc_html($table['missing_columns'] === [] ? 'Nincs' : implode(', ', $table['missing_columns'])) . '</td>';
            echo '<td>' . esc_html($table['missing_indexes'] === [] ? 'Nincs' : implode(', ', $table['missing_indexes'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderLegacyTable(DiagnosticsReport $report): void
    {
        echo '<h2>Legacy rekordok – csak olvasás</h2>';
        echo '<table class="widefat striped"><tbody>';
        $this->row('Legacy meta kulcs létezik', $report->legacy['meta_key_exists'] ? 'Igen' : 'Nem');
        $this->row('Érintett felhasználók', (string) $report->legacy['user_count']);
        $this->row('Legacy rekordok', (string) $report->legacy['record_count']);
        $this->row(
            'Ismert QA demó rekord felismerve',
            $report->legacy['known_demo_detected'] ? 'Igen' : 'Nem'
        );
        echo '</tbody></table>';

        if ($report->legacy['records'] === []) {
            return;
        }

        echo '<h3>Biztonságosan megjeleníthető referenciák</h3>';
        echo '<table class="widefat striped"><thead><tr><th>Rekordazonosító</th><th>Marker</th></tr></thead><tbody>';

        foreach ($report->legacy['records'] as $record) {
            echo '<tr><td><code>' . esc_html($record['id']) . '</code></td><td><code>' . esc_html($record['marker']) . '</code></td></tr>';
        }

        echo '</tbody></table>';
    }

    private function row(string $label, string $value): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }

    private function viewTypeLabel(string $viewType): string
    {
        return match ($viewType) {
            'angled-edge' => 'Döntött él / keret',
            'rear' => 'Hátlap',
            default => 'Elölnézet / kijelző',
        };
    }
}
