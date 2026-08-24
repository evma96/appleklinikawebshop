<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

/** Canonical public metadata for the Buyback service modes. */
final class OfferModeDefinition
{
    /** @return array<string,array{label:string,description:string,process:string,badge?:string}> */
    public static function all(): array
    {
        return [
            ServiceMode::IN_STORE_INSTANT => [
                'label' => 'Személyes felvásárlás (készpénz)',
                'description' => 'Személyes átadás és bevizsgálás után, a lehető leggyorsabb helyi ügyintézéssel.',
                'process' => 'Személyes bevizsgálás',
            ],
            ServiceMode::FAST_ONLINE => [
                'label' => 'Gyorsított felvásárlás (beérkezéstől 1–3 nap)',
                'description' => 'Gyors feldolgozás és kifizetés a készülék beérkezése és bevizsgálása után.',
                'process' => 'Gyors ügyintézés',
            ],
            ServiceMode::HIGHER_OFFER => [
                'label' => 'Normál felvásárlás (magasabb ár, beérkezéstől 5–10 nap)',
                'description' => 'Magasabb előzetes összeg hosszabb, rugalmasabb feldolgozási idő mellett.',
                'process' => 'Részletes ellenőrzés',
            ],
            ServiceMode::TRADE_IN => [
                'label' => 'Személyes beszámítás másik készülékbe',
                'description' => 'A bevizsgálás után elfogadott összeg új készülék vásárlásába számítható be.',
                'process' => 'Beszámítás vásárláskor',
                'badge' => 'LEGJOBB ÁR',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
