<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

final class CapabilityManager
{
    public const VIEW_DIAGNOSTICS = 'ak_buyback_view_diagnostics';
    public const MANAGE_PRICE_BOOKS = 'ak_buyback_manage_price_books';

    /**
     * @var array<int, string>
     */
    private const ROLES = ['administrator', 'shop_manager'];

    public function grant(): void
    {
        foreach (self::ROLES as $roleName) {
            $role = get_role($roleName);

            if ($role instanceof \WP_Role) {
                $role->add_cap(self::VIEW_DIAGNOSTICS);
                $role->add_cap(self::MANAGE_PRICE_BOOKS);
            }
        }
    }

    public function revoke(): void
    {
        foreach (self::ROLES as $roleName) {
            $role = get_role($roleName);

            if ($role instanceof \WP_Role) {
                $role->remove_cap(self::VIEW_DIAGNOSTICS);
                $role->remove_cap(self::MANAGE_PRICE_BOOKS);
            }
        }
    }
}
