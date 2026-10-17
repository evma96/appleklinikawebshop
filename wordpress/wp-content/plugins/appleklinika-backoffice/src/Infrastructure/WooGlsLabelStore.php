<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\GlsLabelStore;

final class WooGlsLabelStore implements GlsLabelStore
{
    public const META = '_appleklinika_gls_label_attempt';

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

    public function recordAttempt(int $id, string $state): void
    {
        $order = wc_get_order($id);
        $order->update_meta_data(self::META, ['state' => $state, 'at' => gmdate('c'), 'actor' => get_current_user_id()]);
        $order->save_meta_data();
        if ($state !== 'attempting') {
            $order->add_order_note(match ($state) {
                'ready' => 'GLS címke elkészült. Futárátadás még nem történt.',
                'retryable' => 'GLS címkekészítés sikertelen; a kérés biztonságosan újrapróbálható az adatok/beállítások javítása után.',
                default => 'GLS címkekészítés bizonytalan; szolgáltatói egyeztetés szükséges, automatikus újrapróbálás tiltva.',
            }, false);
        }
    }
}
