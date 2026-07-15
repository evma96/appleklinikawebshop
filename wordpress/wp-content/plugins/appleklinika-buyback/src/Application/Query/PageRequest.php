<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Query;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PageRequest
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 20
    ) {
        if ($page < 1) {
            throw new InvalidValueObjectException('Page number must be positive.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidValueObjectException('Page size must be between 1 and 100.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
