<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ConditionDefinition
{
    /** @var array<string, string> */
    private const TYPES = [
        'battery_health' => 'integer',
        'powers_on' => 'boolean',
        'display_functional' => 'boolean',
        'touch_functional' => 'boolean',
        'face_id_functional' => 'boolean',
        'camera_functional' => 'boolean',
        'charging_functional' => 'boolean',
        'liquid_damage' => 'boolean',
        'motherboard_issue' => 'boolean',
        'screen_condition' => 'string',
        'frame_condition' => 'string',
        'back_glass_condition' => 'string',
        'camera_lens_condition' => 'string',
        'bent_or_dented' => 'boolean',
        'replacement_parts' => 'boolean',
    ];

    /** @return list<string> */
    public static function keys(): array { return array_keys(self::TYPES); }

    public static function typeFor(string $key): string
    {
        if (! isset(self::TYPES[$key])) {
            throw new InvalidValueObjectException('Unsupported pricing condition key.');
        }

        return self::TYPES[$key];
    }

    public static function assertValid(string $key, ComparisonOperator $operator, mixed $value): void
    {
        if (! isset(self::TYPES[$key])) {
            throw new InvalidValueObjectException('Unsupported pricing condition key.');
        }

        $values = in_array($operator->code(), [ComparisonOperator::BETWEEN, ComparisonOperator::IN], true)
            ? $value
            : [$value];

        if (! is_array($values) || $values === []) {
            throw new InvalidValueObjectException('Comparison value must not be empty.');
        }

        if ($operator->code() === ComparisonOperator::BETWEEN && count($values) !== 2) {
            throw new InvalidValueObjectException('Between comparison requires exactly two values.');
        }

        foreach ($values as $item) {
            if (self::TYPES[$key] === 'boolean' && ! is_bool($item)) {
                throw new InvalidValueObjectException('Boolean condition requires boolean comparison values.');
            }

            if (self::TYPES[$key] === 'integer' && (! is_int($item) || $item < 0 || $item > 100)) {
                throw new InvalidValueObjectException('Battery-health comparison requires an integer between 0 and 100.');
            }

            if (self::TYPES[$key] === 'string' && (! is_string($item) || trim($item) === '' || strlen($item) > 64)) {
                throw new InvalidValueObjectException('Condition comparison string is invalid.');
            }
        }

        if (self::TYPES[$key] === 'boolean' && ! in_array($operator->code(), [ComparisonOperator::EQUALS, ComparisonOperator::NOT_EQUALS, ComparisonOperator::IN], true)) {
            throw new InvalidValueObjectException('Boolean conditions support only equality comparisons.');
        }
    }
}
