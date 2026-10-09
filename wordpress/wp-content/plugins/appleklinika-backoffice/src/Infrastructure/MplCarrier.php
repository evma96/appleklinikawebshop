<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Adapter to the maintained provider plugin. No separate shipment/state store. */
final class MplCarrier
{
    public const POINTS = ['postapont_posta','postapont_postapont','postapont_automata'];

    public static function matches(\WC_Order $order): bool
    {
        $ids = array_values(array_unique(array_map(static fn($s) => $s->get_method_id(), $order->get_shipping_methods())));
        return $ids === ['ak_mpl_home'] || ($ids === ['vp_pont'] && in_array($order->get_meta('_vp_woo_pont_provider', true), self::POINTS, true));
    }

    public static function hasLabel(\WC_Order $order): bool
    {
        return self::matches($order) && (string)$order->get_meta('_vp_woo_pont_parcel_id', true) !== ''
            && (string)$order->get_meta('_vp_woo_pont_parcel_pdf', true) !== '';
    }

    public static function tracking(\WC_Order $order): array
    {
        if (!self::hasLabel($order)) { return []; }
        $raw = $order->get_meta('_vp_woo_pont_parcel_number', true);
        $codes = is_array($raw) ? $raw : preg_split('/[,;\s]+/', (string)$raw);
        $links = [];
        foreach (array_unique($codes ?: []) as $code) {
            if (!is_string($code) || !preg_match('/^[A-Z0-9]{8,35}$/D', $code)) { continue; }
            $links[] = ['code'=>$code, 'label'=>'MPL csomagkövetés', 'url'=>'https://www.posta.hu/nyomkovetes/nyitooldal?searchvalue='.rawurlencode($code)];
        }
        return $links;
    }

    public static function readiness(): ?string
    {
        if (!class_exists('VP_Woo_Pont_Pro') || !\VP_Woo_Pont_Pro::is_pro_enabled()) {
            return 'Az MPL címkéhez a Csomagpontok és Címkék bővítmény támogatott PRO/tesztlicence szükséges.';
        }
        if (!LifecycleConfiguration::isTestEnvironment() || get_option('appleklinika_mpl_api_enabled','no') !== 'yes'
            || \VP_Woo_Pont_Helpers::get_option('posta_dev_mode','no') !== 'yes') {
            return 'Az MPL Sandbox címkekészítés még nincs engedélyezve; postai teszthozzáférés szükséges.';
        }
        foreach (['posta_api_key','posta_api_password','posta_customer_code','posta_agreement_code','posta_sender_name','posta_sender_address','posta_sender_city','posta_sender_postcode','posta_sender_phone','posta_sender_email'] as $key) {
            if (!\VP_Woo_Pont_Helpers::get_option($key)) { return 'Hiányos MPL Sandbox feladói/API-beállítás.'; }
        }
        return null;
    }

    public static function createLabel(\WC_Order $order): void
    {
        if (!self::matches($order) || self::hasLabel($order)) { throw new \InvalidArgumentException('Nem készíthető új MPL címke ehhez a rendeléshez.'); }
        if (isset($_POST['provider']) && $_POST['provider'] !== 'posta') { throw new \InvalidArgumentException('Az MPL művelet szolgáltatója nem módosítható.'); }
        if ($reason = self::readiness()) { throw new \InvalidArgumentException($reason); }
        $result = \VP_Woo_Pont()->labels->generate_label($order->get_id(), 'posta');
        $order->read_meta_data(true);
        if (!empty($result['error']) || !self::hasLabel($order)) { throw new \InvalidArgumentException('Az MPL címke nem igazolt. Ismétlés előtt ellenőrizd a szolgáltatói állapotot.'); }
    }
}
