<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BenchmarkModelAliasNormalizer
{
    /** @var array<string, string> */
    private array $aliases = [];

    /** @param array<string, list<string>> $aliasesByModelKey */
    public function __construct(array $aliasesByModelKey)
    {
        foreach ($aliasesByModelKey as $modelKey => $aliases) {
            foreach (array_merge([$modelKey], $aliases) as $alias) {
                $normalized = self::normalizeLabel($alias);
                if (isset($this->aliases[$normalized]) && $this->aliases[$normalized] !== $modelKey) {
                    throw new InvalidValueObjectException("Benchmark model alias is ambiguous: {$alias}.");
                }
                $this->aliases[$normalized] = $modelKey;
            }
        }
    }

    public function resolve(string $sourceLabel): ?string
    {
        return $this->aliases[self::normalizeLabel($sourceLabel)] ?? null;
    }

    public static function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = str_replace(['–', '—', '_'], '-', $label);
        $label = preg_replace('/\b(apple|gb|gbyte|gigabyte)\b/u', ' ', $label) ?? $label;
        $label = preg_replace('/[^a-z0-9]+/u', ' ', $label) ?? $label;
        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }
}
