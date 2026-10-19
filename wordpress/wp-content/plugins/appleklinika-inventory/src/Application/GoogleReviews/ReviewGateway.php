<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Application\GoogleReviews;
interface ReviewGateway
{
    public function configured(): bool;
    public function load(): array;
    public function save(array $snapshot): void;
    public function clear(): void;
    public function fetch(): array;
    public function lock(): bool;
    public function unlock(): void;
    public function status(string $status): void;
}
