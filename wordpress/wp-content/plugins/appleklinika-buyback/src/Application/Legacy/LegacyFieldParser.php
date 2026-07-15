<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyFieldParser
{
    public const KNOWN_INSPECTING_LABEL = 'Bevizsgálás alatt';

    public function recordId(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 120) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._-]+$/D', $value) === 1 ? $value : null;
    }

    public function plainText(?string $value, int $maximumBytes = 191): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > $maximumBytes || $value !== strip_tags($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $value);

        return is_string($normalized) && $normalized !== '' ? $normalized : null;
    }

    public function batteryPercentage(?string $value): ?int
    {
        if (preg_match('/^\s*(\d{1,3})\s*%\s*$/u', (string) $value, $matches) !== 1) {
            return null;
        }

        $percentage = (int) $matches[1];

        return $percentage <= 100 ? $percentage : null;
    }

    public function hufAmount(?string $value): ?int
    {
        $value = str_replace(["\u{00A0}", "\u{202F}"], ' ', trim((string) $value));

        if (preg_match('/^-/', $value) === 1) {
            return null;
        }

        if (preg_match('/^(\d[\d ]*)\s*Ft$/iu', $value, $matches) !== 1) {
            return null;
        }

        $digits = str_replace(' ', '', $matches[1]);

        if ($digits === '' || preg_match('/^\d+$/D', $digits) !== 1) {
            return null;
        }

        $amount = filter_var($digits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($amount) ? $amount : null;
    }

    public function status(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');

        return $normalized === mb_strtolower(self::KNOWN_INSPECTING_LABEL, 'UTF-8')
            ? 'inspecting'
            : null;
    }

    public function utcDate(?string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            trim((string) $value),
            new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d') === trim((string) $value) ? $date : null;
    }
}
