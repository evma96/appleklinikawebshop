<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class HandoverMethod
{
    public const IN_STORE = 'in_store';
    public const COURIER = 'courier';

    private const SUPPORTED = [self::IN_STORE, self::COURIER];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback handover method.');
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

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return self::SUPPORTED;
    }
}
