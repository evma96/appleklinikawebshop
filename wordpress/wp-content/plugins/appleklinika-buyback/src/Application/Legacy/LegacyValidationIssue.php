<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyValidationIssue
{
    public const INVALID = 'invalid';
    public const MANUAL = 'manual';

    public function __construct(
        public readonly string $code,
        public readonly string $severity
    ) {
        if (! in_array($severity, [self::INVALID, self::MANUAL], true)) {
            throw new \InvalidArgumentException('Unsupported legacy validation issue severity.');
        }
    }
}
