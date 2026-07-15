<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyClassification
{
    public const IMPORTABLE = 'importable';
    public const NEEDS_MANUAL_MAPPING = 'needs_manual_mapping';
    public const INVALID = 'invalid';
    public const ALREADY_PRESENT = 'already_present';

    private function __construct()
    {
    }
}
