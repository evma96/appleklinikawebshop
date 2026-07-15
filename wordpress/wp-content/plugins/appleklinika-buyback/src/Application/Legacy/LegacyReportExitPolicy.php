<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyReportExitPolicy
{
    public function exitCode(LegacyReport $report, bool $strict): int
    {
        if (! $strict) {
            return 0;
        }

        return ($report->invalidCount + $report->needsManualMappingCount) > 0 ? 1 : 0;
    }
}
