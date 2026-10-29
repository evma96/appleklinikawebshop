<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Read-only operational meaning of authoritative payment facts. Never marks an order paid. */
final class PaymentState
{
    /** Native Woo query constraints for unfinished, paid Barion store work.
     * Woo's processing status is paid; completed orders belong to history.
     * No shipping condition or separate Back Office payment marker is involved.
     * @return array{payment_method:string,status:list<string>}
     */
    public static function operationalQueueArguments(): array
    {
        return ['payment_method' => 'barion', 'status' => ['processing']];
    }

    /** @return array{state:string,label:string,block:?string,review:bool} */
    public static function describe(string $method, string $status, bool $paid, string $mode, ?string $codBlock = null, bool $cashRecorded = false, string $barionState = ''): array
    {
        $result = static fn(string $state, string $label, ?string $block = null, bool $review = false): array => compact('state','label','block','review');
        if (in_array($status, ['cancelled','failed','refunded','checkout-draft','trash'], true)) {
            $label = match ($status) {
                'cancelled' => 'Törölt / megszakított rendelés', 'failed' => 'Sikertelen fizetés',
                'refunded' => 'Visszatérítve', 'checkout-draft' => 'Befejezetlen pénztári piszkozat', default => 'Törölt rendelés',
            };
            return $result($status, $label, $label . ' — feldolgozás nem indítható.');
        }
        // COD processing is not a receipt of money. Its fulfilment eligibility is separate.
        if ($method === 'cod') {
            if ($mode === DeliveryMode::UNKNOWN) {
                return $result('cod_unknown', 'Utánvét / átvételkori fizetés – átvételi mód hiányzik', 'Az átvételi módot kell rendezni; fizetést nem kell előre igazolni.');
            }
            if ($codBlock !== null) { return $result('configuration', 'Fizetési mód beállítása ellenőrzendő', $codBlock, true); }
            if ($mode === DeliveryMode::PICKUP && $cashRecorded && $paid) { return $result('paid', 'Készpénzfizetés rögzítve'); }
            if (!in_array($status, ['on-hold','processing','completed'], true)) {
                return $result('pending', 'Rendelés visszaigazolására vár', 'A készpénzes rendelés beadása még nem zárult le.');
            }
            return $result('due', $mode === DeliveryMode::PICKUP ? 'Fizetés személyes átvételkor' : 'Utánvét – fizetés kézbesítéskor');
        }
        if ($paid) { return $result('paid', 'Fizetés: Sikeres'); }
        if ($method === 'barion') {
            if (in_array($barionState, ['unavailable','error','mismatch','unknown','Succeeded','Reserved','Authorized'], true)) {
                return $result('provider_review', 'Barion – egyeztetés szükséges', 'A Barion és a WooCommerce fizetési eredményét egyeztetni kell. Kézi fizetetté jelölés nem végezhető.', true);
            }
            if (in_array($barionState, ['Canceled','Expired','Failed'], true)) {
                return $result('failed', 'Sikertelen / lejárt Barion-fizetés', 'Az ügyfélnek új, sikeres Barion-fizetés szükséges.');
            }
            return $result('pending', 'Barion fizetésre vár', 'A Barion-fizetés még nem fejeződött be. A sikeres fizetést a Barion igazolja vissza; munkatársi fizetésigazolás nem szükséges.');
        }
        if (in_array($method, ['bacs','cheque'], true)) {
            return $result('manual', 'Befizetés beérkezésére vár', 'A befizetés tényleges beérkezését kell ellenőrizni és igazolni.', true);
        }
        return $result('unknown', 'Fizetési adat ellenőrzendő', 'A fizetési mód vagy a fizetés eredménye nem azonosítható biztonságosan.', true);
    }
}
