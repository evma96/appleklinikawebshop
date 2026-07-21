<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

/** Canonical public metadata for the Buyback service modes. */
final class OfferModeDefinition
{
    /** @return array<string,array{label:string,description:string,process:string}> */
    public static function all(): array
    {
        return [
            ServiceMode::IN_STORE_INSTANT => [
                'label' => 'Azonnali személyes felvásárlás',
                'description' => 'Személyes átadás és bevizsgálás után, a lehető leggyorsabb helyi ügyintézéssel.',
                'process' => 'Személyes bevizsgálás',
            ],
            ServiceMode::FAST_ONLINE => [
                'label' => 'Gyors felvásárlás',
                'description' => 'Gyors feldolgozás és kifizetés a készülék beérkezése és bevizsgálása után.',
                'process' => 'Gyors ügyintézés',
            ],
            ServiceMode::HIGHER_OFFER => [
                'label' => 'Magasabb ajánlat',
                'description' => 'Magasabb előzetes összeg hosszabb, rugalmasabb feldolgozási idő mellett.',
                'process' => 'Részletes ellenőrzés',
            ],
            ServiceMode::TRADE_IN => [
                'label' => 'Azonnali beszámítás',
                'description' => 'A bevizsgálás után elfogadott összeg új készülék vásárlásába számítható be.',
                'process' => 'Beszámítás vásárláskor',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
