<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ConditionAnswerCollection
{
    /** @var array<string, int|bool|string> */
    private readonly array $answers;

    /** @param list<ConditionAnswer> $answers */
    public function __construct(array $answers)
    {
        $normalized = [];
        foreach ($answers as $answer) {
            if (isset($normalized[$answer->key])) {
                throw new InvalidValueObjectException('Duplicate condition answer key.');
            }
            $normalized[$answer->key] = $answer->value;
        }

        foreach (ConditionDefinition::keys() as $key) {
            if (ConditionDefinition::isRequired($key) && ! array_key_exists($key, $normalized)) {
                throw new InvalidValueObjectException('Missing required condition answer: ' . $key);
            }
        }

        ksort($normalized);
        $this->answers = $normalized;
    }

    /** @param array<string, mixed> $answers */
    public static function fromAssociative(array $answers): self
    {
        $items = [];
        foreach ($answers as $key => $value) {
            $items[] = new ConditionAnswer((string) $key, $value);
        }
        return new self($items);
    }

    public function get(string $key): int|bool|string
    {
        if (! array_key_exists($key, $this->answers)) {
            throw new InvalidValueObjectException('Condition answer is missing.');
        }
        return $this->answers[$key];
    }

    /** @return array<string, int|bool|string> */
    public function all(): array
    {
        return $this->answers;
    }
}
