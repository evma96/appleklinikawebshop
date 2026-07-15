<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ComparisonOperator
{
    public const EQUALS = 'equals';
    public const NOT_EQUALS = 'not_equals';
    public const LESS_THAN = 'less_than';
    public const LESS_OR_EQUAL = 'less_or_equal';
    public const GREATER_THAN = 'greater_than';
    public const GREATER_OR_EQUAL = 'greater_or_equal';
    public const BETWEEN = 'between';
    public const IN = 'in';

    /** @return list<string> */
    public static function supported(): array
    {
        return [self::EQUALS, self::NOT_EQUALS, self::LESS_THAN, self::LESS_OR_EQUAL, self::GREATER_THAN, self::GREATER_OR_EQUAL, self::BETWEEN, self::IN];
    }

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::supported(), true)) {
            throw new InvalidValueObjectException('Unsupported comparison operator.');
        }
    }

    public function code(): string { return $this->code; }
}
