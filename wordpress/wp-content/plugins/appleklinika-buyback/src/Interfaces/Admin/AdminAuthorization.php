<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

final class AdminAuthorization
{
    public const NONCE_ACTION = 'ak_buyback_manage_price_books';

    public function assert(string $capability, string $nonce): void
    {
        if (! current_user_can($capability)) {
            throw new \RuntimeException('Nincs jogosultságod az árkönyvek kezeléséhez.');
        }

        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE_ACTION) !== 1) {
            throw new \RuntimeException('A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt és próbáld újra.');
        }
    }
}
