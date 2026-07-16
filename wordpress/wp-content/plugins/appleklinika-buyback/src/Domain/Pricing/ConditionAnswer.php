<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class ConditionAnswer
{
    public readonly int|bool|string $value;

    public function __construct(public readonly string $key, mixed $value)
    {
        $this->value = ConditionDefinition::normalizeInput($key, $value);
    }
}
