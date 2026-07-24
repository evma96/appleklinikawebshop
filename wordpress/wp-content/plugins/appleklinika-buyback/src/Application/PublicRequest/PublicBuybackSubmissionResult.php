<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\PublicRequest;

final class PublicBuybackSubmissionResult
{
    public function __construct(
        public readonly string $requestNumber,
        public readonly string $device,
        public readonly ?string $serviceMode,
        public readonly ?int $amountMinor,
        public readonly bool $manualReview,
        public readonly bool $alreadySubmitted = false,
        /** @var list<string> */ public readonly array $manualReviewReasons = []
    ) {
    }
}
