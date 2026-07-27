<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;
use AppleKlinika\Buyback\Interfaces\Admin\BuybackRequestsPage;

/** Limits the restricted price editor to its sole Buyback admin screen. */
final class RestrictedPriceEditorAdminAccess
{
    public function register(): void
    {
        add_filter('login_redirect', [$this, 'redirectAfterLogin'], 100, 3);
        add_filter('woocommerce_prevent_admin_access', [$this, 'allowOnlyPriceBookScreen']);
    }

    public function redirectAfterLogin(string $redirectTo, string $requestedRedirectTo, mixed $user): string
    {
        if (! $user instanceof \WP_User || ! $this->isRestrictedPriceEditor($user)) {
            return $redirectTo;
        }

        return admin_url('admin.php?page=' . PriceBooksPage::SLUG);
    }

    public function allowOnlyPriceBookScreen(bool $preventAccess): bool
    {
        if (! $preventAccess || ! $this->isRestrictedPriceEditor(wp_get_current_user())) {
            return $preventAccess;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return ! in_array($page, [PriceBooksPage::SLUG, DiagnosticsPage::SLUG, BuybackRequestsPage::SLUG], true);
    }

    private function isRestrictedPriceEditor(\WP_User $user): bool
    {
        return in_array(CapabilityManager::PRICE_EDITOR_ROLE, $user->roles, true)
            && user_can($user, CapabilityManager::VIEW_PRICE_BOOKS);
    }
}
