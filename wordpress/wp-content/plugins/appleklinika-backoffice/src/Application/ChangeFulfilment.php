<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\FulfilmentStore;
use Appleklinika\BackOffice\Application\Port\OrderMutex;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;

final class ChangeFulfilment
{
    public function __construct(private readonly FulfilmentStore $orders, private readonly OrderMutex $mutex) {}

    public function execute(int $id, string $action, int $actor, string $expected, string $target = '', string $reason = ''): string
    {
        return $this->mutex->synchronized($id, function () use ($id, $action, $actor, $expected, $target, $reason): string {
            $order = $this->orders->snapshot($id);
            if ($order['blocked'] !== null) {
                throw new \InvalidArgumentException($order['blocked']);
            }
            if ($expected !== $order['state']) {
                throw new \InvalidArgumentException('A rendelés állapota közben megváltozott. Frissítsd az oldalt.');
            }
            $cash = !empty($order['cash']);
            $accepted = !empty($order['cash_accepted']);
            if (in_array($action, ['accept_cash_pickup', 'record_cash_pickup'], true) && (!$cash || $actor <= 0)) {
                throw new \InvalidArgumentException('Ez a művelet csak készpénzes személyes átvételnél végezhető el munkatársként.');
            }
            if ($cash && !$accepted && !in_array($action, ['accept_cash_pickup', 'problem', 'correct'], true)) {
                throw new \InvalidArgumentException('Először ellenőrizd a készletet és fogadd el a készpénzes rendelést.');
            }
            if ($cash && $action === 'picked_up') {
                throw new \InvalidArgumentException('Készpénzes átvételnél a fizetést és átadást együtt rögzítő művelet szükséges.');
            }
            if ($action === 'accept_cash_pickup' && $accepted) {
                throw new \InvalidArgumentException('A rendelés elfogadása már megtörtént.');
            }
            if ($action === 'correct') {
                if ($cash && !$accepted && !in_array($target, [FulfilmentWorkflow::NEW, FulfilmentWorkflow::PROBLEM], true)) {
                    throw new \InvalidArgumentException('Állapotjavítás nem helyettesíti a készpénzes rendelés elfogadását.');
                }
                $allowed = array_keys(FulfilmentWorkflow::customerProgressLabels($order['mode']));
                $allowed = array_diff($allowed, [FulfilmentWorkflow::HANDED_TO_GLS, FulfilmentWorkflow::DELIVERED, FulfilmentWorkflow::PICKED_UP]);
                $allowed[] = FulfilmentWorkflow::PROBLEM;
                if (! in_array($target, $allowed, true) || trim($reason) === '') {
                    throw new \InvalidArgumentException('Javításhoz válassz belső állapotot és adj meg indoklást. Átadást a külön művelettel rögzíts.');
                }
                $to = $target;
            } else {
                $to = FulfilmentWorkflow::transition($order['state'], $action, $order['mode']);
                if ($action === 'accept_cash_pickup') { $this->orders->verifyCashReservation($id); }
                if ($action === 'record_cash_pickup') { $this->orders->recordCashPayment($id, $actor); }
                if ($action === 'create_label') {
                    if ($order['label']) {
                        throw new \InvalidArgumentException('Ehhez a rendeléshez már létezik GLS címke.');
                    }
                    $this->orders->createLabel($id);
                }
                if ($action === 'handed_to_gls' && (! $order['label'] || ! $order['tracking'])) {
                    throw new \InvalidArgumentException('GLS-átadáshoz elkészült címke és érvényes csomagszám szükséges.');
                }
            }
            $this->orders->record($id, $action, $to, $actor, $reason);
            return $to;
        });
    }
}
