<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\TransitionBuybackRequest;
use AppleKlinika\Buyback\Application\Exception\BuybackRequestNotFoundException;
use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\DomainEventPublisher;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\StatusTransitionPolicy;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;

final class TransitionBuybackRequestHandler
{
    public function __construct(
        private readonly BuybackRequestRepository $repository,
        private readonly TransactionManager $transactionManager,
        private readonly DomainEventPublisher $eventPublisher,
        private readonly StatusTransitionPolicy $transitionPolicy
    ) {
    }

    public function handle(TransitionBuybackRequest $command): BuybackRequest
    {
        $request = $this->transactionManager->transactional(function () use ($command): BuybackRequest {
            $request = $this->repository->getById($command->requestId);

            if ($request === null) {
                throw BuybackRequestNotFoundException::forId($command->requestId);
            }

            if (! $request->version()->equals($command->expectedVersion)) {
                throw new StaleAggregateVersionException(
                    $command->expectedVersion->value(),
                    $request->version()->value()
                );
            }

            $request->transitionTo($command->targetStatus, $this->transitionPolicy, $command->context);
            $events = $request->pendingEvents();

            $this->repository->save($request, $command->expectedVersion);
            $this->eventPublisher->publish(...$events);

            return $request;
        });

        $request->releasePendingEvents();

        return $request;
    }
}
