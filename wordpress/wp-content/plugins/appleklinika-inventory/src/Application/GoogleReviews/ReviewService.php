<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Application\GoogleReviews;
use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;

final class ReviewService
{
    public function __construct(private ReviewGateway $gateway) {}

    /** Page rendering never performs a remote request. */
    public function read(int $now): ?array
    {
        if (!$this->gateway->configured()) { $this->gateway->clear(); return null; }
        $data = PublicReviews::fromSnapshot($this->gateway->load(), $now);
        if ($data === null) { $this->gateway->clear(); }
        return $data;
    }

    /** Scheduled refresh only; retain an unexpired snapshot during transient failure. */
    public function refresh(int $now): void
    {
        if (!$this->gateway->configured()) { $this->gateway->clear(); $this->gateway->status('not_configured'); return; }
        if (!$this->gateway->lock()) { return; }
        try {
            $this->read($now);
            $snapshot = $this->gateway->fetch() + ['fetched_at'=>$now];
            if (PublicReviews::fromSnapshot($snapshot, $now) === null) { throw new \RuntimeException('invalid_public_data'); }
            $this->gateway->save($snapshot);
            $this->gateway->status('ok');
        } catch (\Throwable $error) {
            // Never record upstream bodies, tokens or credential-bearing exceptions.
            $this->gateway->status('unavailable');
        } finally { $this->gateway->unlock(); }
    }
}
