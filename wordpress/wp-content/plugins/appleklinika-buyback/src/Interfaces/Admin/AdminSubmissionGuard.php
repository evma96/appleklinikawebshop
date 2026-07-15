<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

final class AdminSubmissionGuard
{
    public function issue(): string
    {
        return wp_generate_uuid4();
    }

    public function consume(string $scope, string $token, int $actorId): bool
    {
        if ($token === '' || $actorId < 1) {
            return false;
        }

        $key = 'ak_bb_submit_' . md5($scope . '|' . $actorId . '|' . $token);
        if (get_transient($key) !== false) {
            return false;
        }

        return set_transient($key, '1', HOUR_IN_SECONDS);
    }
}
