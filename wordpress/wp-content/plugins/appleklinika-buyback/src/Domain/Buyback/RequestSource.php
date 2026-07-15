<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class RequestSource
{
    public const NATIVE = 'native';
    public const LEGACY_USER_META = 'legacy_user_meta';
    public const QA_FIXTURE = 'qa_fixture';

    private const SUPPORTED = [
        self::NATIVE,
        self::LEGACY_USER_META,
        self::QA_FIXTURE,
    ];

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::SUPPORTED, true)) {
            throw new InvalidValueObjectException('Unsupported buyback request source.');
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
