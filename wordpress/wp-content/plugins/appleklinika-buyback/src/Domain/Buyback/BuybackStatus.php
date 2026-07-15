<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BuybackStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const AWAITING_HANDOVER = 'awaiting_handover';
    public const COURIER_REQUESTED = 'courier_requested';
    public const COURIER_BOOKED = 'courier_booked';
    public const RECEIVED = 'received';
    public const INSPECTION_PENDING = 'inspection_pending';
    public const INSPECTING = 'inspecting';
    public const PRELIMINARY_MISMATCH = 'preliminary_mismatch';
    public const FINAL_OFFER_READY = 'final_offer_ready';
    public const FINAL_OFFER_SENT = 'final_offer_sent';
    public const FINAL_OFFER_ACCEPTED = 'final_offer_accepted';
    public const FINAL_OFFER_REJECTED = 'final_offer_rejected';
    public const RETURN_REQUESTED = 'return_requested';
    public const RETURNING_DEVICE = 'returning_device';
    public const PAYOUT_PENDING = 'payout_pending';
    public const PAID = 'paid';
    public const TRADE_IN_PENDING = 'trade_in_pending';
    public const TRADE_IN_APPLIED = 'trade_in_applied';
    public const CANCELLED = 'cancelled';
    public const CLOSED = 'closed';

    private const SUPPORTED = [
        self::DRAFT,
        self::SUBMITTED,
        self::AWAITING_HANDOVER,
        self::COURIER_REQUESTED,
        self::COURIER_BOOKED,
        self::RECEIVED,
        self::INSPECTION_PENDING,
        self::INSPECTING,
        self::PRELIMINARY_MISMATCH,
        self::FINAL_OFFER_READY,
        self::FINAL_OFFER_SENT,
        self::FINAL_OFFER_ACCEPTED,
        self::FINAL_OFFER_REJECTED,
        self::RETURN_REQUESTED,
        self::RETURNING_DEVICE,
        self::PAYOUT_PENDING,
        self::PAID,
        self::TRADE_IN_PENDING,
        self::TRADE_IN_APPLIED,
        self::CANCELLED,
        self::CLOSED,
    ];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback status.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function isTerminal(): bool
    {
        return $this->code === self::CLOSED;
    }

    public function isCustomerEditable(): bool
    {
        return $this->code === self::DRAFT;
    }

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return self::SUPPORTED;
    }
}
