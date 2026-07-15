<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ServiceMode
{
    public const IN_STORE_INSTANT = 'in_store_instant';
    public const FAST_ONLINE = 'fast_online';
    public const HIGHER_OFFER = 'higher_offer';
    public const TRADE_IN = 'trade_in';

    private const SUPPORTED = [
        self::IN_STORE_INSTANT,
        self::FAST_ONLINE,
        self::HIGHER_OFFER,
        self::TRADE_IN,
    ];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback service mode.');
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

    public function requiresPayout(): bool
    {
        return ! $this->isTradeIn();
    }

    public function isTradeIn(): bool
    {
        return $this->code === self::TRADE_IN;
    }

    public function allowsCourier(): bool
    {
        return in_array($this->code, [self::FAST_ONLINE, self::HIGHER_OFFER], true);
    }

    public function allowsInStoreHandover(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return self::SUPPORTED;
    }
}
