<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class DeviceCategory
{
    public const IPHONE = 'iphone';

    private const SUPPORTED = [self::IPHONE];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback device category.');
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
