<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Shared;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ActorType
{
    public const CUSTOMER = 'customer';
    public const STAFF = 'staff';
    public const SYSTEM = 'system';

    private const SUPPORTED = [self::CUSTOMER, self::STAFF, self::SYSTEM];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback actor type.');
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
