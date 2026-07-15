<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;

final class LegacyReferenceFactory
{
    public function fromUserMeta(int $ownerUserId, string $legacyRecordId): LegacyReference
    {
        if ($ownerUserId <= 0) {
            throw new \InvalidArgumentException('Legacy owner user ID must be positive.');
        }

        return new LegacyReference(sprintf('user-meta:%d:%s', $ownerUserId, $legacyRecordId));
    }
}
