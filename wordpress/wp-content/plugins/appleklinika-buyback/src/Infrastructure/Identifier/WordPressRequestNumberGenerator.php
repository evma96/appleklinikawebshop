<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Identifier;

use AppleKlinika\Buyback\Application\Exception\RequestNumberGenerationException;
use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\RequestNumberGenerator;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;

final class WordPressRequestNumberGenerator implements RequestNumberGenerator
{
    private readonly \Closure $randomBytes;

    public function __construct(
        private readonly BuybackRequestRepository $repository,
        private readonly Clock $clock,
        private readonly int $maximumAttempts = 5,
        ?\Closure $randomBytes = null
    ) {
        if ($maximumAttempts < 1 || $maximumAttempts > 20) {
            throw new \InvalidArgumentException('Request-number attempt limit must be between 1 and 20.');
        }

        $this->randomBytes = $randomBytes ?? static fn (int $length): string => random_bytes($length);
    }

    public function generate(): RequestNumber
    {
        for ($attempt = 0; $attempt < $this->maximumAttempts; ++$attempt) {
            $bytes = ($this->randomBytes)(3);

            if (! is_string($bytes) || strlen($bytes) !== 3) {
                throw new RequestNumberGenerationException('Request-number randomness source returned invalid data.');
            }

            $candidate = new RequestNumber(sprintf(
                'AKB-%s-%s',
                $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd'),
                strtoupper(bin2hex($bytes))
            ));

            if (! $this->repository->existsByRequestNumber($candidate)) {
                return $candidate;
            }
        }

        throw new RequestNumberGenerationException('Could not generate a unique buyback request number.');
    }
}
