<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Application\ProductCondition;

final class SaveProductConditionCommand
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $input
    ) {
    }
}
