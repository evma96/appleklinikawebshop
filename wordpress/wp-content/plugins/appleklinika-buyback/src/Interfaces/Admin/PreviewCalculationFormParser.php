<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Query\PreviewDraftPriceBookCalculation;
use AppleKlinika\Buyback\Domain\Pricing\ConditionDefinition;

final class PreviewCalculationFormParser
{
    /** @param array<string, mixed> $post */
    public function parse(array $post): PreviewDraftPriceBookCalculation
    {
        $bookId = $this->positiveInteger($post, 'price_book_id');
        $storage = $this->positiveInteger($post, 'preview_storage_gb');
        $modelKey = sanitize_key((string) ($post['preview_model_key'] ?? ''));
        if ($modelKey === '') {
            throw new \InvalidArgumentException('iPhone modell kiválasztása szükséges.');
        }

        $rawAnswers = $post['preview_conditions'] ?? null;
        if (! is_array($rawAnswers)) {
            throw new \InvalidArgumentException('A készülékállapot adatai hiányoznak.');
        }

        $unknown = array_diff(array_keys($rawAnswers), ConditionDefinition::keys());
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Ismeretlen készülékállapot-mező.');
        }

        $answers = [];
        foreach (ConditionDefinition::keys() as $key) {
            if (! array_key_exists($key, $rawAnswers)) {
                throw new \InvalidArgumentException('Hiányzó készülékállapot-mező.');
            }
            $raw = is_scalar($rawAnswers[$key]) ? sanitize_text_field((string) $rawAnswers[$key]) : null;
            $answers[$key] = ConditionDefinition::normalizeInput($key, $raw);
        }

        return new PreviewDraftPriceBookCalculation($bookId, $modelKey, $storage, $answers);
    }

    /** @param array<string, mixed> $post */
    private function positiveInteger(array $post, string $key): int
    {
        $value = filter_var($post[$key] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('Pozitív egész szám szükséges.');
        }
        return $value;
    }
}
