<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ConditionDefinition
{
    public const TYPE_INTEGER = 'integer';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_ENUM = 'enum';

    /** @var list<string> */
    private const NUMERIC_OPERATORS = [
        ComparisonOperator::EQUALS,
        ComparisonOperator::LESS_THAN,
        ComparisonOperator::LESS_OR_EQUAL,
        ComparisonOperator::GREATER_THAN,
        ComparisonOperator::GREATER_OR_EQUAL,
        ComparisonOperator::BETWEEN,
        ComparisonOperator::IN,
    ];

    /** @var list<string> */
    private const EQUALITY_OPERATORS = [
        ComparisonOperator::EQUALS,
        ComparisonOperator::NOT_EQUALS,
        ComparisonOperator::IN,
    ];

    /** @var array<string, string> */
    private const COSMETIC_VALUES = [
        'like_new' => 'Újszerű',
        'excellent' => 'Kiváló',
        'very_good' => 'Nagyon jó',
        'good' => 'Jó',
        'damaged' => 'Sérült',
    ];

    /** @var array<string, string> */
    private const REPLACEMENT_VALUES = [
        'none_known' => 'Nem ismert cserealkatrész',
        'original_repair' => 'Eredeti alkatrészes javítás',
        'non_original' => 'Nem eredeti alkatrész',
        'unknown' => 'Nem ismert',
    ];

    /** @var array<string, array{type: string, label: string, required: bool, operators: list<string>, values: array<string, string>}> */
    private const DEFINITIONS = [
        'battery_health' => ['type' => self::TYPE_INTEGER, 'label' => 'Akkumulátor állapota (%)', 'required' => true, 'operators' => self::NUMERIC_OPERATORS, 'values' => []],
        'powers_on' => ['type' => self::TYPE_BOOLEAN, 'label' => 'Bekapcsol', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'display_functional' => ['type' => self::TYPE_BOOLEAN, 'label' => 'A kijelző működik', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'touch_functional' => ['type' => self::TYPE_BOOLEAN, 'label' => 'Az érintés működik', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'face_id_functional' => ['type' => self::TYPE_BOOLEAN, 'label' => 'A Face ID működik', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'camera_functional' => ['type' => self::TYPE_BOOLEAN, 'label' => 'A kamerák működnek', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'charging_functional' => ['type' => self::TYPE_BOOLEAN, 'label' => 'A töltés működik', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'liquid_damage' => ['type' => self::TYPE_BOOLEAN, 'label' => 'Folyadékkár nyoma', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'motherboard_issue' => ['type' => self::TYPE_BOOLEAN, 'label' => 'Alaplaphiba', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'screen_condition' => ['type' => self::TYPE_ENUM, 'label' => 'Kijelző állapota', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => self::COSMETIC_VALUES],
        'frame_condition' => ['type' => self::TYPE_ENUM, 'label' => 'Keret állapota', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => self::COSMETIC_VALUES],
        'back_glass_condition' => ['type' => self::TYPE_ENUM, 'label' => 'Hátlap állapota', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => self::COSMETIC_VALUES],
        'camera_lens_condition' => ['type' => self::TYPE_ENUM, 'label' => 'Kameralencse állapota', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => self::COSMETIC_VALUES],
        'bent_or_dented' => ['type' => self::TYPE_BOOLEAN, 'label' => 'Hajlott vagy horpadt', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => []],
        'replacement_parts' => ['type' => self::TYPE_ENUM, 'label' => 'Cserealkatrészek', 'required' => true, 'operators' => self::EQUALITY_OPERATORS, 'values' => self::REPLACEMENT_VALUES],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return array<string, array{type: string, label: string, required: bool, operators: list<string>, values: array<string, string>}> */
    public static function all(): array
    {
        return self::DEFINITIONS;
    }

    public static function typeFor(string $key): string
    {
        return self::definition($key)['type'];
    }

    public static function labelFor(string $key): string
    {
        return self::definition($key)['label'];
    }

    public static function isRequired(string $key): bool
    {
        return self::definition($key)['required'];
    }

    /** @return list<string> */
    public static function allowedOperators(string $key): array
    {
        return self::definition($key)['operators'];
    }

    /** @return array<string, string> */
    public static function allowedValues(string $key): array
    {
        return self::definition($key)['values'];
    }

    public static function normalizeInput(string $key, mixed $value): int|bool|string
    {
        $definition = self::definition($key);

        if ($definition['type'] === self::TYPE_INTEGER) {
            if (is_string($value) && preg_match('/^\d{1,3}$/', $value) === 1) {
                $value = (int) $value;
            }
            if (! is_int($value) || $value < 0 || $value > 100) {
                throw new InvalidValueObjectException('Battery health must be an integer between 0 and 100.');
            }
            return $value;
        }

        if ($definition['type'] === self::TYPE_BOOLEAN) {
            if (is_bool($value)) {
                return $value;
            }
            if ($value === '1' || $value === 1 || $value === 'true') {
                return true;
            }
            if ($value === '0' || $value === 0 || $value === 'false') {
                return false;
            }
            throw new InvalidValueObjectException('Boolean condition value must be true or false.');
        }

        if (! is_string($value) || ! array_key_exists($value, $definition['values'])) {
            throw new InvalidValueObjectException('Unsupported condition enum value.');
        }

        return $value;
    }

    public static function assertValid(string $key, ComparisonOperator $operator, mixed $value): void
    {
        $definition = self::definition($key);
        if (! in_array($operator->code(), $definition['operators'], true)) {
            throw new InvalidValueObjectException('Comparison operator is not supported by this condition.');
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
            self::assertNormalizedValue($definition, $item);
        }
    }

    /** @return array{type: string, label: string, required: bool, operators: list<string>, values: array<string, string>} */
    private static function definition(string $key): array
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new InvalidValueObjectException('Unsupported pricing condition key.');
        }
        return self::DEFINITIONS[$key];
    }

    /** @param array{type: string, label: string, required: bool, operators: list<string>, values: array<string, string>} $definition */
    private static function assertNormalizedValue(array $definition, mixed $value): void
    {
        if ($definition['type'] === self::TYPE_BOOLEAN && ! is_bool($value)) {
            throw new InvalidValueObjectException('Boolean condition requires a normalized boolean value.');
        }
        if ($definition['type'] === self::TYPE_INTEGER && (! is_int($value) || $value < 0 || $value > 100)) {
            throw new InvalidValueObjectException('Battery health requires an integer between 0 and 100.');
        }
        if ($definition['type'] === self::TYPE_ENUM && (! is_string($value) || ! array_key_exists($value, $definition['values']))) {
            throw new InvalidValueObjectException('Condition value is outside the canonical enum.');
        }
    }
}
