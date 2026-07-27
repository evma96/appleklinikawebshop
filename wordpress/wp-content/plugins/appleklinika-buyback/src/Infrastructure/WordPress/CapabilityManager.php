<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

final class CapabilityManager
{
    public const VIEW_PRICE_BOOKS = 'ak_buyback_view_price_books';
    public const EDIT_PRICE_BOOKS = 'ak_buyback_edit_price_books';
    public const CREATE_PRICE_BOOK_DRAFTS = 'ak_buyback_create_price_book_drafts';
    public const CLONE_PRICE_BOOKS = 'ak_buyback_clone_price_books';
    public const ACTIVATE_PRICE_BOOKS = 'ak_buyback_activate_price_books';
    public const DELETE_PRICE_BOOK_DRAFTS = 'ak_buyback_delete_price_book_drafts';
    public const PROTECT_PRICE_BOOKS = 'ak_buyback_protect_price_books';
    public const VIEW_BUYBACK_REQUESTS = 'ak_buyback_view_requests';
    public const MANAGE_BUYBACK_SETTINGS = 'ak_buyback_manage_settings';
    public const VIEW_DIAGNOSTICS = 'ak_buyback_view_diagnostics';

    /** Legacy aggregate capability retained for backwards-compatible administrator access. */
    public const MANAGE_PRICE_BOOKS = 'ak_buyback_manage_price_books';
    public const PRICE_EDITOR_ROLE = 'appleklinika_buyback_price_editor';

    /** @return list<string> */
    public static function priceEditorCapabilities(): array
    {
        return [self::VIEW_PRICE_BOOKS, self::EDIT_PRICE_BOOKS, self::CREATE_PRICE_BOOK_DRAFTS, self::CLONE_PRICE_BOOKS];
    }

    /** @return list<string> */
    private static function administratorCapabilities(): array
    {
        return [
            self::MANAGE_PRICE_BOOKS,
            self::VIEW_PRICE_BOOKS,
            self::EDIT_PRICE_BOOKS,
            self::CREATE_PRICE_BOOK_DRAFTS,
            self::CLONE_PRICE_BOOKS,
            self::ACTIVATE_PRICE_BOOKS,
            self::DELETE_PRICE_BOOK_DRAFTS,
            self::PROTECT_PRICE_BOOKS,
            self::VIEW_BUYBACK_REQUESTS,
            self::MANAGE_BUYBACK_SETTINGS,
            self::VIEW_DIAGNOSTICS,
        ];
    }

    public function grant(): void
    {
        foreach (['administrator', 'shop_manager'] as $roleName) {
            $role = get_role($roleName);
            if (! $role instanceof \WP_Role) {
                continue;
            }
            foreach (self::administratorCapabilities() as $capability) {
                $role->add_cap($capability);
            }
        }

        $editor = get_role(self::PRICE_EDITOR_ROLE);
        if (! $editor instanceof \WP_Role) {
            $editor = add_role(self::PRICE_EDITOR_ROLE, 'Felvásárlási árkezelő', ['read' => true]);
        }
        if ($editor instanceof \WP_Role) {
            foreach (self::priceEditorCapabilities() as $capability) {
                $editor->add_cap($capability);
            }
        }
    }

    public function revoke(): void
    {
        foreach (['administrator', 'shop_manager'] as $roleName) {
            $role = get_role($roleName);
            if ($role instanceof \WP_Role) {
                foreach (self::administratorCapabilities() as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
        remove_role(self::PRICE_EDITOR_ROLE);
    }
}
