<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

/**
 * @param array<string,array<string,array{action:mixed,value?:mixed}>> $conditions
 * @param array<string,array<string,array{action:mixed,value?:mixed}>> $serviceHistoryComponents
 */
final class SaveDraftQuestionnaireConditions
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly string $modelKey,
        public readonly array $conditions,
        public readonly array $serviceHistoryComponents = []
    ) {
    }
}
