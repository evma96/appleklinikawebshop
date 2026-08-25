<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Command\SaveOfferModeSettings;
use AppleKlinika\Buyback\Application\Handler\SaveOfferModeSettingsHandler;
use AppleKlinika\Buyback\Application\Port\OfferModeSettingsStore;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

final class OfferModeSettingsPage
{
    public const SLUG = 'appleklinika-buyback-offer-settings';

    public function __construct(
        private readonly OfferModeSettingsStore $settings,
        private readonly SaveOfferModeSettingsHandler $save,
        private readonly AdminAuthorization $authorization
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'handlePost']);
    }

    public function registerMenu(): void
    {
        add_submenu_page('woocommerce', 'Apple Klinika Buyback – Ajánlattípusok', 'Buyback – Ajánlattípusok', CapabilityManager::MANAGE_BUYBACK_SETTINGS, self::SLUG, [$this, 'render']);
    }

    public function handlePost(): void
    {
        if ((string) ($_POST['ak_buyback_offer_settings_action'] ?? '') !== 'save') {
            return;
        }

        $nonce = sanitize_text_field((string) wp_unslash($_POST['_ak_buyback_nonce'] ?? ''));
        try {
            $this->authorization->assert(CapabilityManager::MANAGE_BUYBACK_SETTINGS, $nonce);
            $modes = isset($_POST['offer_modes']) && is_array($_POST['offer_modes']) ? wp_unslash($_POST['offer_modes']) : [];
            $this->save->handle(new SaveOfferModeSettings($modes));
            $this->redirect('success');
        } catch (\Throwable $exception) {
            $this->redirect('error', $exception->getMessage());
        }
    }

    public function render(): void
    {
        if (! current_user_can(CapabilityManager::MANAGE_BUYBACK_SETTINGS)) {
            wp_die(esc_html('Nincs jogosultságod az ajánlattípusok beállításához.'));
        }

        $configuration = $this->settings->get();
        echo '<div class="wrap ak-buyback-admin"><h1>Apple Klinika Felvásárlás – Ajánlattípusok</h1>';
        echo '<p>Az itt megadott címek és leírások minden árkönyvnél és a nyilvános felvásárlási folyamatban egységesen jelennek meg. Az árkorrekciókat ez nem módosítja.</p>';
        $this->renderNotice();
        echo '<form method="post" class="ak-buyback-card">';
        wp_nonce_field(AdminAuthorization::NONCE_ACTION, '_ak_buyback_nonce');
        echo '<input type="hidden" name="ak_buyback_offer_settings_action" value="save">';
        foreach ($configuration->all() as $key => $mode) {
            echo '<fieldset style="margin:1.5rem 0;padding:1rem;border:1px solid #dcdcde"><legend><strong>' . esc_html($mode['label']) . '</strong></legend>';
            echo '<p><label><input type="checkbox" name="offer_modes[' . esc_attr($key) . '][enabled]" value="1" ' . checked($mode['enabled'], true, false) . '> Megjelenjen a vásárlóknak</label></p>';
            echo '<p><label>Cím<br><input class="regular-text" type="text" name="offer_modes[' . esc_attr($key) . '][label]" maxlength="191" value="' . esc_attr($mode['label']) . '" required></label></p>';
            echo '<p><label>Leírás<br><textarea class="large-text" rows="3" maxlength="1000" name="offer_modes[' . esc_attr($key) . '][description]" required>' . esc_textarea($mode['description']) . '</textarea></label></p>';
            echo '</fieldset>';
        }
        echo '<p class="description">Legalább egy ajánlattípusnak engedélyezve kell maradnia. A kikapcsolt típusok korrekciós szabályai megmaradnak, és visszakapcsoláskor újra használatba lépnek.</p>';
        submit_button('Ajánlattípusok beállításainak mentése');
        echo '</form></div>';
    }

    private function redirect(string $result, string $message = ''): never
    {
        $args = ['page' => self::SLUG, 'ak_offer_settings_result' => $result];
        if ($message !== '') {
            $args['ak_offer_settings_message'] = $message;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function renderNotice(): void
    {
        $result = sanitize_key((string) ($_GET['ak_offer_settings_result'] ?? ''));
        if ($result === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>Az ajánlattípusok globális beállításai elmentve.</p></div>';
            return;
        }
        if ($result === 'error') {
            echo '<div class="notice notice-error"><p>' . esc_html((string) ($_GET['ak_offer_settings_message'] ?? 'A mentés nem sikerült.')) . '</p></div>';
        }
    }
}
