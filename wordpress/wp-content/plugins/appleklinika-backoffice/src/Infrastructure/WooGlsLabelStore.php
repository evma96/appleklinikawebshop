<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\GlsLabelStore;

final class WooGlsLabelStore implements GlsLabelStore
{
    public const META = '_appleklinika_gls_attempt';

    public function snapshot(int $id): array
    {
        ProductionReadiness::assertAllowed('gls');
        $order = wc_get_order($id);
        if (!$order instanceof \WC_Order) { throw new \InvalidArgumentException('A rendelés nem található.'); }
        $repo = new WooOrderBackOfficeRepository();
        $recorded = false;
        foreach (['_gls_print_label', '_gls_parcel_ids', '_gls_tracking_codes', '_gls_tracking_code'] as $key) {
            $recorded = $recorded || !empty($order->get_meta($key, true));
        }
        $attempt = $order->get_meta(self::META, true);
        return ['state' => $repo->state($order), 'mode' => $repo->deliveryMode($order),
            'paid' => $order->is_paid() && $order->get_date_paid() !== null,
            'blocked' => $repo->fulfilmentBlockReason($order), 'recorded' => $recorded,
            'attempt' => is_array($attempt) ? (string) ($attempt['state'] ?? '') : ''];
    }

    public function recordAttempt(int $id, string $state, string $message = ''): void
    {
        $order = wc_get_order($id);
        $account = class_exists('GLS_Shipping_Account_Helper') ? \GLS_Shipping_Account_Helper::get_active_account() : [];
        foreach (['username','password','client_id'] as $key) {
            $secret = (string)($account[$key] ?? '');
            if ($secret !== '') { $message = str_replace([$secret,rawurlencode($secret),htmlspecialchars($secret,ENT_QUOTES)], '[védett adat]', $message); }
        }
        $message = mb_substr(trim(strip_tags($message)),0,600);
        $order->update_meta_data(self::META, ['state' => $state, 'at' => gmdate('c'), 'actor' => get_current_user_id(), 'message'=>$message]);
        $order->save_meta_data();
        $fresh = wc_get_order($id); $fresh->read_meta_data(true);
        if (($fresh->get_meta(self::META,true)['state'] ?? '') !== $state) { throw new \RuntimeException('GLS attempt evidence was not persisted.'); }
        if ($state !== 'attempting') {
            $order->add_order_note(match ($state) {
                'ready' => 'GLS címke elkészült. Futárátadás még nem történt.',
                'retryable' => 'GLS címkekészítés sikertelen; a kérés biztonságosan újrapróbálható az adatok/beállítások javítása után.',
                default => 'GLS címkekészítés bizonytalan; szolgáltatói egyeztetés szükséges, automatikus újrapróbálás tiltva.',
            }, false);
        }
    }
}
