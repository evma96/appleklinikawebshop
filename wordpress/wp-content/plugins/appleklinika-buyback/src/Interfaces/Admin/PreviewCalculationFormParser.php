<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Query\PreviewDraftPriceBookCalculation;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;

final class PreviewCalculationFormParser
{
    public function __construct(private readonly LocalDemoQuestionnaire $questionnaire)
    {
    }

    /** @param array<string, mixed> $post */
    public function parse(array $post): PreviewDraftPriceBookCalculation
    {
        $bookId = $this->positiveInteger($post, 'price_book_id');
        $storage = $this->positiveInteger($post, 'preview_storage_gb');
        $modelKey = sanitize_key((string) ($post['preview_model_key'] ?? ''));
        if ($modelKey === '') {
            throw new \InvalidArgumentException('iPhone modell kiválasztása szükséges.');
        }

        $rawAnswers = $post['preview_questionnaire'] ?? null;
        if (! is_array($rawAnswers)) {
            throw new \InvalidArgumentException('A kérdőív válaszai hiányoznak.');
        }

        $questions = $this->questionnaire->questions();
        $unknown = array_diff(array_keys($rawAnswers), array_keys($questions));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Ismeretlen kérdőívmező.');
        }

        $errors = $this->questionnaire->validate($rawAnswers);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', array_values($errors)));
        }

        $colorKey = sanitize_key((string) ($post['preview_color_key'] ?? ''));
        return new PreviewDraftPriceBookCalculation($bookId, $modelKey, $storage, $this->questionnaire->sanitize($rawAnswers), $colorKey);
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
