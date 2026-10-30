<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;
use Appleklinika\BackOffice\Application\Port\PaymentStockStore;
use Appleklinika\BackOffice\Domain\PaymentStockSafety;

/** Admit fulfilment only when the captured payment can still own its physical stock. */
final class AdmitDevicePayment
{
    public function __construct(private PaymentStockStore $store) {}
    public function execute(int $id, string $transaction): void
    {
        try {
            $state = $this->store->lockAndRead($id);
            if ($state === null || $state['paid'] || $state['reduced']) return;
            // A previously flagged capture requires operator/provider reconciliation, never blind retry.
            if ($state['exception'] || PaymentStockSafety::conflict($state['lines'])) {
                throw new \RuntimeException('payment_stock_conflict');
            }
            $this->store->reserve($id);
        } catch (\Throwable $error) {
            $reason = $error->getMessage() === 'payment_stock_conflict' ? 'payment_stock_conflict' : 'payment_stock_recheck_required';
            $this->store->exception($id, $transaction, $reason);
            throw new \RuntimeException('Barion-fizetés: készletegyeztetés szükséges. Automatikus teljesítés leállítva.');
        }
    }
}
