<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Recovery policy over authoritative facts; no provider or WordPress calls. */
final class OrderReview
{
    public const JOURNAL_KEY = '_appleklinika_shipping_review_operation';

    public static function pending(mixed $journal): bool
    {
        return is_array($journal) && !empty($journal['id']) && ($journal['stage'] ?? '') !== 'complete';
    }

    public static function shippingBlock(array $facts): ?string
    {
        if (!in_array($facts['status'], ['pending','on-hold','processing'], true)) {
            return 'Csak nyitott rendelés átvételi módja javítható. Lezárt, törölt vagy visszatérített rendeléshez külön ügyintézés szükséges.';
        }
        if (!empty($facts['fulfilment_effect'])) { return $facts['fulfilment_effect']; }
        if (!in_array($facts['state'], ['new','preparation','problem'], true) || $facts['carrier_evidence']) {
            return 'Már előkészített csomag vagy szolgáltatói küldeményadat tartozik a rendeléshez. Előbb a meglévő küldeményt és annak szükséges törlését kell egyeztetni; új szállítást nem hozunk létre mellé.';
        }
        if ($facts['mode'] !== DeliveryMode::UNKNOWN && empty($facts['manual_shipping'])) {
            return 'Az átvételi mód már azonosított, de nem egy egyező korábbi Back Office javítás eredménye. Az eredeti rendelési adatot külön rendelésadminisztrációs egyeztetéssel lehet módosítani.';
        }
        if (count($facts['shipping']) > 1 || !$facts['shipping_totals_match']) {
            return 'Több szállítási tétel vagy eltérő szállítási díj/adó miatt a korrekció pénzügyi döntést igényel. A tételeket és díjakat rendelésadminisztrációban kell egyeztetni; ez a művelet nem módosíthat összeget.';
        }
        return null;
    }

    public static function manualPaymentBlock(array $facts): ?string
    {
        if (!in_array($facts['payment'], ['bacs','cheque'], true)) { return 'Ehhez a fizetési módhoz nem engedélyezett kézi fizetetté jelölés.'; }
        if (!in_array($facts['status'], ['pending','on-hold'], true)) { return 'Kézi fizetés csak függő vagy fizetésre váró rendelésnél igazolható.'; }
        if ($facts['paid'] || $facts['date_paid'] || $facts['transaction']) { return 'Már van fizetési adat; ismételt visszaigazolás helyett a meglévő bejegyzést kell egyeztetni.'; }
        if (!$facts['has_items'] || (float)$facts['total'] <= 0) { return 'A fizetési igazoláshoz pozitív összegű, terméktételes rendelés szükséges.'; }
        return null;
    }
}
