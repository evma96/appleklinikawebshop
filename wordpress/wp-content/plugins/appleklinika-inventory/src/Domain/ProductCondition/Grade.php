<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Domain\ProductCondition;

final class Grade
{
    public const A_PLUS = 'a_plus';
    public const A = 'a';
    public const B = 'b';
    public const C = 'c';

    private const VALUES = [
        self::A_PLUS,
        self::A,
        self::B,
        self::C,
    ];

    public function __construct(private readonly string $value)
    {
        if (! in_array($value, self::VALUES, true)) {
            throw new \InvalidArgumentException('Invalid product condition grade.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::A_PLUS => 'A+ - makulatlan',
            self::A => 'A - kiváló',
            self::B => 'B - jó, látható használati nyomokkal',
            self::C => 'C - erősen használt',
        ];
    }

    public function value(): string
    {
        return $this->value;
    }
}
