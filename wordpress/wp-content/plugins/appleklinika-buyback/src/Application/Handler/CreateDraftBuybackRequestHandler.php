<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\CreateDraftBuybackRequest;
use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\RequestNumberGenerator;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;

final class CreateDraftBuybackRequestHandler
{
    public function __construct(
        private readonly BuybackRequestRepository $repository,
        private readonly RequestNumberGenerator $requestNumberGenerator,
        private readonly Clock $clock,
        private readonly TransactionManager $transactionManager
    ) {
    }

    public function handle(CreateDraftBuybackRequest $command): BuybackRequest
    {
        $newRequest = new NewBuybackRequest(
            $this->requestNumberGenerator->generate(),
            $command->customerId,
            $command->category,
            $command->modelKey,
            $command->deviceDisplayName,
            $command->serviceMode,
            $command->handoverMethod,
            $command->source,
            $command->legacyReference,
            $this->clock->now(),
            $command->demoMarker
        );

        return $this->transactionManager->transactional(
            fn (): BuybackRequest => $this->repository->insert($newRequest)
        );
    }
}
