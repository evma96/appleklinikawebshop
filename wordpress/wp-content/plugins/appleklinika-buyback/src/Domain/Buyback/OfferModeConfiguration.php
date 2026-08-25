<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

/** Global customer-facing configuration for the immutable service-mode keys. */
final class OfferModeConfiguration
{
    /** @var array<string,array{label:string,description:string,process:string,badge?:string,enabled:bool}> */
    private array $modes;

    /** @param array<string,mixed>|null $stored */
    private function __construct(?array $stored = null)
    {
        $savedModes = is_array($stored['modes'] ?? null) ? $stored['modes'] : [];
        $this->modes = [];

        foreach (OfferModeDefinition::all() as $key => $default) {
            $saved = is_array($savedModes[$key] ?? null) ? $savedModes[$key] : [];
            $label = self::usableString($saved['label'] ?? null, 191) ?? $default['label'];
            $description = self::usableString($saved['description'] ?? null, 1000) ?? $default['description'];
            $this->modes[$key] = array_merge($default, [
                'label' => $label,
                'description' => $description,
                'enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
            ]);
        }
    }

    public static function defaults(): self
    {
        return new self();
    }

    /** @param array<string,mixed>|null $stored */
    public static function fromStored(?array $stored): self
    {
        return new self($stored);
    }

    /** @param array<string,mixed> $submitted */
    public static function fromSubmitted(array $submitted): self
    {
        $normalized = ['modes' => []];
        foreach (OfferModeDefinition::keys() as $key) {
            $input = is_array($submitted[$key] ?? null) ? $submitted[$key] : [];
            $label = self::requiredString($input['label'] ?? null, 191, 'Minden ajánlattípushoz adj meg címet.');
            $description = self::requiredString($input['description'] ?? null, 1000, 'Minden ajánlattípushoz adj meg leírást.');
            $normalized['modes'][$key] = [
                'label' => $label,
                'description' => $description,
                'enabled' => ($input['enabled'] ?? false) === true || ($input['enabled'] ?? '') === '1',
            ];
        }

        $configuration = new self($normalized);
        if ($configuration->enabled() === []) {
            throw new \InvalidArgumentException('Legalább egy ajánlattípusnak engedélyezve kell maradnia.');
        }

        return $configuration;
    }

    /** @return array<string,array{label:string,description:string,process:string,badge?:string,enabled:bool}> */
    public function all(): array
    {
        return $this->modes;
    }

    /** @return array<string,array{label:string,description:string,process:string,badge?:string,enabled:bool}> */
    public function enabled(): array
    {
        return array_filter($this->modes, static fn (array $mode): bool => $mode['enabled']);
    }

    public function isEnabled(string $key): bool
    {
        return ($this->modes[$key]['enabled'] ?? false) === true;
    }

    /** @return array{modes:array<string,array{label:string,description:string,enabled:bool}>} */
    public function toStored(): array
    {
        $modes = [];
        foreach ($this->modes as $key => $mode) {
            $modes[$key] = [
                'label' => $mode['label'],
                'description' => $mode['description'],
                'enabled' => $mode['enabled'],
            ];
        }

        return ['modes' => $modes];
    }

    private static function usableString(mixed $value, int $maximum): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' || mb_strlen($value) > $maximum ? null : $value;
    }

    private static function requiredString(mixed $value, int $maximum, string $message): string
    {
        $string = self::usableString($value, $maximum);
        if ($string === null) {
            throw new \InvalidArgumentException($message);
        }

        return $string;
    }
}
