<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionResult;

interface BuybackRequestMailer
{
    /** @param array<string,mixed> $input */
    public function sendCustomer(PublicBuybackSubmissionResult $result, array $input): bool;

    /** @param array<string,mixed> $input */
    public function sendAdmin(PublicBuybackSubmissionResult $result, array $input): bool;
}
