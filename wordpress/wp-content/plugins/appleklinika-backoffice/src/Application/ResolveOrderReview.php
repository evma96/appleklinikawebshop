<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\{OrderReviewStore, OrderMutex};

/** Explicit staff command; never called from page rendering or provider callbacks. */
final class ResolveOrderReview
{
    public function __construct(private readonly OrderReviewStore $orders, private readonly OrderMutex $mutex) {}

    public function execute(int $id, string $action, string $expected, string $choice, string $reason, bool $confirmed, string $operation = ''): string
    {
        if (!$confirmed || ($action !== 'reconcile_shipping' && (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 500))) {
            throw new \InvalidArgumentException('A rendezéshez kifejezett megerősítés és 5–500 karakteres indoklás / ellenőrzési forrás szükséges.');
        }
        return $this->mutex->synchronized($id, function () use ($id, $action, $expected, $choice, $reason, $operation): string {
            // Shipping owns a verified write-ahead record and an idempotent audit projection.
            // Payment completion remains on its existing provider/native path.
            if ($action === 'resolve_shipping') { return $this->orders->correctShipping($id, $expected, $choice, trim($reason)); }
            if ($action === 'reconcile_shipping') { return $this->orders->reconcileShipping($id, $operation); }
            $before = $this->orders->review($id);
            if (!hash_equals($before['fingerprint'], $expected)) {
                throw new \InvalidArgumentException('A rendelés adatai közben megváltoztak. Frissítsd az oldalt és ellenőrizd újra.');
            }
            $block = match ($action) {
                'confirm_manual_payment' => $before['payment_action'] === 'confirm_manual_payment' ? null : $before['payment_message'],
                'recheck_barion' => $before['payment_action'] === 'recheck_barion' ? null : $before['payment_message'],
                default => 'Ismeretlen rendezési művelet.',
            };
            if ($block !== null) { throw new \InvalidArgumentException($block); }
            if ($action === 'confirm_manual_payment') {
                $this->orders->confirmPayment($id);
                $result = 'A kézi fizetés beérkezése rögzítve a WooCommerce rendelésen.';
            } else {
                $result = $this->orders->recheckBarion($id);
            }
            $this->orders->record($id, $action, $before['facts'], trim($reason), $result);
            return $result;
        });
    }
}
