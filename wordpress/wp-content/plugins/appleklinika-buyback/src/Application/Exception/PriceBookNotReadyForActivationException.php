<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Exception;

use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessReport;

final class PriceBookNotReadyForActivationException extends \RuntimeException
{
    public function __construct(public readonly PriceBookActivationReadinessReport $report)
    {
        parent::__construct('Price book is not ready for activation: ' . implode(', ', $report->blockingIssues));
    }
}
