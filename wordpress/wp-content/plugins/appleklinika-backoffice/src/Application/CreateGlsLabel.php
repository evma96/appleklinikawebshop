<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\{GlsLabelStore, OrderMutex};
use Appleklinika\BackOffice\Domain\{DeliveryMode, FulfilmentWorkflow};

/** Shared by automatic packing completion and the provider's existing manual retry. */
final class CreateGlsLabel
{
    public function __construct(private readonly GlsLabelStore $orders, private readonly OrderMutex $mutex) {}

    public function execute(int $id, callable $provider): array
    {
        return $this->mutex->synchronized($id, function () use ($id, $provider): array {
            $order = $this->orders->snapshot($id);
            if ($order['blocked'] !== null || !$order['paid'] || $order['mode'] !== DeliveryMode::GLS
                || $order['state'] !== FulfilmentWorkflow::READY_FOR_SHIPPING) {
                return ['success' => false, 'error' => 'GLS címke csak lezárt csomagolás és rögzített sikeres fizetés után készíthető.'];
            }
            if ($order['recorded'] || $order['attempt'] === 'ready') {
                return ['success' => false, 'error' => 'Már van GLS csomag vagy címke. A meglévő dokumentumot kell használni vagy helyreállítani; új csomag nem készült.'];
            }
            if (!in_array($order['attempt'], ['', 'retryable'], true)) {
                return ['success' => false, 'error' => 'A korábbi GLS kérés eredménye bizonytalan. Előbb egyeztesd a meglévő csomagot a GLS-sel; új kérés nem indult.'];
            }
            // Durable before the request: a crash/timeout must never cause a blind duplicate.
            $this->orders->recordAttempt($id, 'attempting');
            try {
                $result = $provider();
            } catch (\Throwable) {
                $result = ['success' => false];
            }
            $state = !empty($result['success']) ? 'ready' : (!empty($result['retry_safe']) ? 'retryable' : 'uncertain');
            $this->orders->recordAttempt($id, $state);
            if ($state !== 'ready') {
                return ['success' => false, 'error' => $state === 'retryable'
                    ? 'A GLS címke nem készült el. Ellenőrizd a GLS beállításait és a rendelés szállítási adatait, majd használd a GLS címke létrehozása műveletet az újrapróbáláshoz.'
                    : 'A GLS kérés eredménye bizonytalan vagy a címke mentése hibás. Előbb egyeztesd/helyreállítsd a meglévő csomagot; új kérés nem indítható.'];
            }
            return $result;
        });
    }
}
