<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application\Port;

interface OrderReviewStore
{
    public function review(int $id): array;
    public function correctShipping(int $id, string $expected, string $choice, string $reason): string;
    public function reconcileShipping(int $id, string $operation): string;
    public function confirmPayment(int $id): void;
    public function recheckBarion(int $id): string;
    public function record(int $id, string $action, array $before, string $reason, string $result): void;
}
