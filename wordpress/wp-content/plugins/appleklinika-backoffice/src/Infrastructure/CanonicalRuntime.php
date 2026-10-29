<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Explicit environment gate for staff writes in the native Woo TEST runtime.
 * Uses the proven LOCAL configuration contract, without its separate-interface
 * URL/connection adapters. Provider callbacks and background hooks are unchanged.
 */
final class CanonicalRuntime
{
    public static function orderWritesAllowed(): bool
    {
        return defined('AKBO_CANONICAL_WRITES') && AKBO_CANONICAL_WRITES === true
            && defined('AKBO_CANONICAL_WRITE_ENVIRONMENT')
            && in_array(AKBO_CANONICAL_WRITE_ENVIRONMENT, ['local', 'staging', 'production'], true)
            && AKBO_CANONICAL_WRITE_ENVIRONMENT === wp_get_environment_type();
    }

    public static function productWritesAllowed(): bool { return self::orderWritesAllowed(); }
    public static function authorizeProductWrite(): void { self::authorizeWrite(); }
    public static function storefront(string $url): string { return $url; }

    /** Called after existing employee capability and native order nonce checks. */
    public static function authorizeWrite(): void
    {
        if (!self::orderWritesAllowed()) {
            throw new \InvalidArgumentException('A WooCommerce-rendelések módosítása ebben a környezetben nincs kifejezetten engedélyezve.');
        }
    }
}
