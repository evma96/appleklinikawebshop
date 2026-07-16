<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class BenchmarkManifest
{
    public const SCHEMA_VERSION = '1.0.0';

    /** @param list<array{path: string, sha256: string}> $sourceSnapshots
     *  @param list<PricingRuleDefinition> $rules
     *  @param array<string, array<string, mixed>> $evidenceByRule
     *  @param array<string, mixed> $rawData
     */
    private function __construct(
        public readonly string $manifestVersion,
        public readonly \DateTimeImmutable $generatedAt,
        public readonly string $methodologyVersion,
        public readonly array $sourceSnapshots,
        public readonly string $seedKey,
        public readonly string $label,
        public readonly int $roundingIncrementMinor,
        public readonly int $minimumOfferMinor,
        public readonly string $minimumPolicy,
        public readonly array $rules,
        public readonly array $evidenceByRule,
        private readonly array $rawData
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        self::requireString($data, 'schema_version', self::SCHEMA_VERSION);
        $manifestVersion = self::requireString($data, 'manifest_version');
        $methodologyVersion = self::requireString($data, 'generator_methodology_version');
        $generatedAt = new \DateTimeImmutable(self::requireString($data, 'generated_at'));
        if ($generatedAt->getOffset() !== 0) {
            throw new InvalidValueObjectException('Benchmark manifest generated_at must be UTC.');
        }

        $sourceSnapshots = self::sourceSnapshots($data['source_snapshots'] ?? null);
        $priceBook = self::requireArray($data, 'price_book');
        $seedKey = self::requireString($priceBook, 'seed_key');
        if (preg_match('/^[a-z0-9._-]{3,120}$/', $seedKey) !== 1) {
            throw new InvalidValueObjectException('Benchmark seed key is invalid.');
        }
        $label = self::requireString($priceBook, 'label');
        if (strlen($label) > 120) {
            throw new InvalidValueObjectException('Benchmark price-book label is too long.');
        }
        self::requireString($priceBook, 'currency', 'HUF');
        self::requireString($priceBook, 'status', 'draft');
        $rounding = self::requirePositiveInteger($priceBook, 'rounding_increment_minor');
        $minimum = self::requirePositiveInteger($priceBook, 'minimum_offer_minor');
        $minimumPolicy = self::requireString($priceBook, 'minimum_policy');
        new MinimumOfferPolicy($minimumPolicy);

        $ruleRows = $data['rules'] ?? null;
        if (! is_array($ruleRows) || ! array_is_list($ruleRows) || $ruleRows === []) {
            throw new InvalidValueObjectException('Benchmark manifest requires at least one pricing rule.');
        }

        $definitions = [];
        $evidenceByRule = [];
        $codes = [];
        $baseConfigurations = [];

        foreach ($ruleRows as $index => $row) {
            if (! is_array($row)) {
                throw new InvalidValueObjectException("Benchmark rule {$index} must be an object.");
            }
            $evidence = self::requireArray($row, 'evidence');
            $definition = self::definition($row, $evidence, $manifestVersion);
            $code = $definition->code->code();
            if (isset($codes[$code])) {
                throw new InvalidValueObjectException("Duplicate benchmark rule code: {$code}.");
            }
            $codes[$code] = true;
            self::assertEvidence($definition, $evidence);

            if ($definition->kind->code() === PricingRuleKind::BASE_PRICE) {
                $configuration = implode('|', [$definition->category, $definition->modelKey, $definition->storage?->gigabytes()]);
                if (isset($baseConfigurations[$configuration])) {
                    throw new InvalidValueObjectException("Duplicate benchmark base configuration: {$configuration}.");
                }
                $baseConfigurations[$configuration] = true;
            }

            $definitions[] = $definition;
            $evidenceByRule[$code] = $evidence;
        }

        usort($definitions, static function (PricingRuleDefinition $a, PricingRuleDefinition $b): int {
            return [$a->priority->value(), $a->code->code()] <=> [$b->priority->value(), $b->code->code()];
        });

        return new self(
            $manifestVersion,
            $generatedAt,
            $methodologyVersion,
            $sourceSnapshots,
            $seedKey,
            $label,
            $rounding,
            $minimum,
            $minimumPolicy,
            $definitions,
            $evidenceByRule,
            $data
        );
    }

    public function hash(): string
    {
        return hash('sha256', BenchmarkRuleCanonicalizer::canonicalJson($this->rawData));
    }

    public function rulesHash(): string
    {
        return BenchmarkRuleCanonicalizer::definitionsHash($this->rules);
    }

    /** @return list<string> */
    public function modelKeys(): array
    {
        $models = [];
        foreach ($this->rules as $definition) {
            if ($definition->kind->code() === PricingRuleKind::BASE_PRICE && $definition->modelKey !== null) {
                $models[$definition->modelKey] = true;
            }
        }
        $keys = array_keys($models);
        sort($keys);
        return $keys;
    }

    /** @return list<string> */
    public function configurationKeys(): array
    {
        $keys = [];
        foreach ($this->rules as $definition) {
            if ($definition->kind->code() === PricingRuleKind::BASE_PRICE) {
                $keys[] = implode('|', [$definition->category, $definition->modelKey, $definition->storage?->gigabytes()]);
            }
        }
        sort($keys);
        return $keys;
    }

    public function countKind(string $kind): int
    {
        return count(array_filter(
            $this->rules,
            static fn (PricingRuleDefinition $definition): bool => $definition->kind->code() === $kind
        ));
    }

    /** @param array<string, mixed> $row
     *  @param array<string, mixed> $evidence
     */
    private static function definition(array $row, array $evidence, string $manifestVersion): PricingRuleDefinition
    {
        $code = new PricingRuleCode(self::requireString($row, 'rule_code'));
        $kind = new PricingRuleKind(self::requireString($row, 'rule_kind'));
        $category = self::requireString($row, 'category', 'iphone');
        $modelKey = self::optionalString($row, 'model_key');
        $storage = isset($row['storage_gb']) ? new StorageCapacity(self::requirePositiveInteger($row, 'storage_gb')) : null;
        $serviceMode = self::optionalString($row, 'service_mode');
        $conditionKey = self::optionalString($row, 'condition_key');
        $operator = isset($row['comparison_operator']) && $row['comparison_operator'] !== null
            ? new ComparisonOperator(self::requireString($row, 'comparison_operator'))
            : null;
        $comparisonValue = $row['comparison_value'] ?? null;
        $amount = isset($row['amount_minor']) && $row['amount_minor'] !== null
            ? new Money(self::requireInteger($row, 'amount_minor'), 'HUF')
            : null;
        $multiplier = isset($row['multiplier_bps']) && $row['multiplier_bps'] !== null
            ? new BasisPointsMultiplier(self::requireInteger($row, 'multiplier_bps'))
            : null;
        $priority = new RulePriority(self::requireInteger($row, 'priority'));
        $enabled = $row['enabled'] ?? null;
        if (! is_bool($enabled) || ! $enabled) {
            throw new InvalidValueObjectException('Benchmark manifest rules must be enabled.');
        }

        $metadata = [
            'benchmark_manifest' => $manifestVersion,
            'confidence' => (string) ($evidence['confidence'] ?? 'unknown'),
            'observations' => array_values(array_map('strval', is_array($evidence['observations'] ?? null) ? $evidence['observations'] : [])),
            'sources' => array_values(array_map('strval', is_array($evidence['sources'] ?? null) ? $evidence['sources'] : [])),
        ];

        return new PricingRuleDefinition(
            $code,
            $kind,
            $category,
            $modelKey,
            $storage,
            $serviceMode,
            $conditionKey,
            $operator,
            $comparisonValue,
            $amount,
            $multiplier,
            $priority,
            true,
            self::optionalString($row, 'public_label'),
            BenchmarkRuleCanonicalizer::canonicalJson($metadata)
        );
    }

    /** @param array<string, mixed> $evidence */
    private static function assertEvidence(PricingRuleDefinition $definition, array $evidence): void
    {
        $kind = $definition->kind->code();
        $eligible = match ($kind) {
            PricingRuleKind::BASE_PRICE => BenchmarkEvidencePolicy::basePriceEligible($evidence),
            PricingRuleKind::MODE_ADJUSTMENT => BenchmarkEvidencePolicy::modeAdjustmentEligible($evidence),
            PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER => BenchmarkEvidencePolicy::monetaryConditionEligible($evidence),
            PricingRuleKind::HARD_REJECT => BenchmarkEvidencePolicy::reviewOrRejectEligible($evidence, true),
            PricingRuleKind::MANUAL_REVIEW => BenchmarkEvidencePolicy::reviewOrRejectEligible($evidence, false),
            default => false,
        };

        if (! $eligible) {
            throw new InvalidValueObjectException("Benchmark evidence threshold is not met for {$definition->code->code()}.");
        }
    }

    /** @return list<array{path: string, sha256: string}> */
    private static function sourceSnapshots(mixed $rows): array
    {
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new InvalidValueObjectException('Benchmark manifest source snapshots are required.');
        }
        $snapshots = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidValueObjectException('Benchmark source snapshot reference must be an object.');
            }
            $path = self::requireString($row, 'path');
            $hash = strtolower(self::requireString($row, 'sha256'));
            if (str_starts_with($path, '/') || str_contains($path, '..') || preg_match('/^[a-zA-Z0-9._\/-]+\.json$/', $path) !== 1) {
                throw new InvalidValueObjectException('Benchmark source snapshot path is unsafe.');
            }
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new InvalidValueObjectException('Benchmark source snapshot SHA-256 is invalid.');
            }
            $snapshots[] = ['path' => $path, 'sha256' => $hash];
        }
        return $snapshots;
    }

    /** @param array<string, mixed> $data */
    private static function requireArray(array $data, string $key): array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            throw new InvalidValueObjectException("Benchmark manifest {$key} must be an object.");
        }
        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private static function requireString(array $data, string $key, ?string $expected = null): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidValueObjectException("Benchmark manifest {$key} is required.");
        }
        $value = trim($value);
        if ($expected !== null && $value !== $expected) {
            throw new InvalidValueObjectException("Benchmark manifest {$key} must be {$expected}.");
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        if (! isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
            return null;
        }
        return self::requireString($data, $key);
    }

    /** @param array<string, mixed> $data */
    private static function requireInteger(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value)) {
            throw new InvalidValueObjectException("Benchmark manifest {$key} must be an integer.");
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function requirePositiveInteger(array $data, string $key): int
    {
        $value = self::requireInteger($data, $key);
        if ($value < 1) {
            throw new InvalidValueObjectException("Benchmark manifest {$key} must be positive.");
        }
        return $value;
    }
}
