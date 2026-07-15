<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidStatusTransitionException;
use AppleKlinika\Buyback\Domain\Shared\ActorType;

final class StatusTransitionPolicy
{
    /** @var array<string, array<string, list<string>>> */
    private const TRANSITIONS = [
        BuybackStatus::DRAFT => [
            BuybackStatus::SUBMITTED => [ActorType::CUSTOMER],
            BuybackStatus::CANCELLED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::SUBMITTED => [
            BuybackStatus::AWAITING_HANDOVER => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::COURIER_REQUESTED => [ActorType::CUSTOMER, ActorType::STAFF],
            BuybackStatus::CANCELLED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::AWAITING_HANDOVER => [
            BuybackStatus::COURIER_REQUESTED => [ActorType::CUSTOMER, ActorType::STAFF],
            BuybackStatus::RECEIVED => [ActorType::STAFF],
            BuybackStatus::CANCELLED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::COURIER_REQUESTED => [
            BuybackStatus::COURIER_BOOKED => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::AWAITING_HANDOVER => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::CANCELLED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::COURIER_BOOKED => [
            BuybackStatus::RECEIVED => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::CANCELLED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::RECEIVED => [
            BuybackStatus::INSPECTION_PENDING => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::INSPECTION_PENDING => [
            BuybackStatus::INSPECTING => [ActorType::STAFF],
        ],
        BuybackStatus::INSPECTING => [
            BuybackStatus::PRELIMINARY_MISMATCH => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::FINAL_OFFER_READY => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::PRELIMINARY_MISMATCH => [
            BuybackStatus::FINAL_OFFER_READY => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::FINAL_OFFER_READY => [
            BuybackStatus::FINAL_OFFER_SENT => [ActorType::STAFF],
        ],
        BuybackStatus::FINAL_OFFER_SENT => [
            BuybackStatus::FINAL_OFFER_ACCEPTED => [ActorType::CUSTOMER, ActorType::STAFF],
            BuybackStatus::FINAL_OFFER_REJECTED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::FINAL_OFFER_ACCEPTED => [
            BuybackStatus::PAYOUT_PENDING => [ActorType::STAFF, ActorType::SYSTEM],
            BuybackStatus::TRADE_IN_PENDING => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::FINAL_OFFER_REJECTED => [
            BuybackStatus::RETURN_REQUESTED => [ActorType::CUSTOMER, ActorType::STAFF],
        ],
        BuybackStatus::RETURN_REQUESTED => [
            BuybackStatus::RETURNING_DEVICE => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::PAYOUT_PENDING => [
            BuybackStatus::PAID => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::TRADE_IN_PENDING => [
            BuybackStatus::TRADE_IN_APPLIED => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::PAID => [
            BuybackStatus::CLOSED => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::TRADE_IN_APPLIED => [
            BuybackStatus::CLOSED => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::RETURNING_DEVICE => [
            BuybackStatus::CLOSED => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::CANCELLED => [
            BuybackStatus::CLOSED => [ActorType::STAFF, ActorType::SYSTEM],
        ],
        BuybackStatus::CLOSED => [],
    ];

    public function assertAllowed(
        BuybackStatus $from,
        BuybackStatus $to,
        ServiceMode $serviceMode,
        TransitionContext $context
    ): void {
        if ($from->equals($to)) {
            $this->reject($from, $to, $context, 'same-status transitions are forbidden');
        }

        $allowedActors = self::TRANSITIONS[$from->code()][$to->code()] ?? null;

        if ($allowedActors === null) {
            $this->reject($from, $to, $context, 'target status is not reachable from current status');
        }

        if (! in_array($context->actorType()->code(), $allowedActors, true)) {
            $this->reject($from, $to, $context, 'actor is not allowed to perform this transition');
        }

        if ($to->code() === BuybackStatus::COURIER_REQUESTED && ! $serviceMode->allowsCourier()) {
            $this->reject($from, $to, $context, 'selected service mode does not permit courier handover');
        }

        if (in_array($to->code(), [
            BuybackStatus::FINAL_OFFER_ACCEPTED,
            BuybackStatus::FINAL_OFFER_REJECTED,
        ], true)) {
            if ($context->isFinalOfferExpired()) {
                $this->reject($from, $to, $context, 'final offer has expired');
            }

            if (
                $context->actorType()->code() === ActorType::STAFF
                && ! $context->acceptanceEvidencePresent()
            ) {
                $this->reject($from, $to, $context, 'staff-recorded decision requires explicit evidence');
            }
        }

        if ($to->code() === BuybackStatus::PAYOUT_PENDING && ! $serviceMode->requiresPayout()) {
            $this->reject($from, $to, $context, 'trade-in requests cannot enter payout processing');
        }

        if ($to->code() === BuybackStatus::TRADE_IN_PENDING && ! $serviceMode->isTradeIn()) {
            $this->reject($from, $to, $context, 'non-trade-in requests cannot enter trade-in processing');
        }

        if ($to->code() === BuybackStatus::PAID && ! $context->settlementReferencePresent()) {
            $this->reject($from, $to, $context, 'paid status requires a settlement reference');
        }

        if ($to->code() === BuybackStatus::TRADE_IN_APPLIED) {
            if (! $context->tradeInCreditReferencePresent()) {
                $this->reject($from, $to, $context, 'trade-in application requires a credit reference');
            }

            if (! $context->linkedWooOrderReferencePresent()) {
                $this->reject($from, $to, $context, 'trade-in application requires a linked Woo order reference');
            }
        }
    }

    /** @return array<string, array<string, list<string>>> */
    public function transitionMatrix(): array
    {
        return self::TRANSITIONS;
    }

    private function reject(
        BuybackStatus $from,
        BuybackStatus $to,
        TransitionContext $context,
        string $reason
    ): never {
        throw InvalidStatusTransitionException::between(
            $from->code(),
            $to->code(),
            $context->actorType()->code(),
            $reason
        );
    }
}
