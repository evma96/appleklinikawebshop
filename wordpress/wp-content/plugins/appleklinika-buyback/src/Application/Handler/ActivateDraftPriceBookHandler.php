<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\ActivateDraftPriceBook;
use AppleKlinika\Buyback\Application\Exception\InvalidActivationConfirmationException;
use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotReadyForActivationException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookActivationLock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class ActivateDraftPriceBookHandler
{
    private const LOCK_TIMEOUT_SECONDS = 2;

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly PriceBookActivationReadinessService $readiness,
        private readonly PriceBookActivationLock $lock,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly ?PriceBookLifecycleRepository $lifecycle = null
    ) {
    }

    public function handle(ActivateDraftPriceBook $command): PriceBook
    {
        $currency = new CurrencyCode('HUF');
        $this->lock->acquire($currency, self::LOCK_TIMEOUT_SECONDS);
        $failure = null;

        try {
            return $this->transactions->transactional(function () use ($command, $currency): PriceBook {
                $id = new PriceBookId($command->priceBookId);
                $target = $this->books->getByIdForUpdate($id);
                if ($target === null) {
                    throw PriceBookNotFoundException::forId($id);
                }
                if ($target->version()->value() !== $command->expectedVersion) {
                    throw new StaleAggregateVersionException($command->expectedVersion, $target->version()->value());
                }
                if (! hash_equals($target->label(), trim($command->confirmation))) {
                    throw new InvalidActivationConfirmationException('A aktiválás megerősítéséhez írd be pontosan az árkönyv nevét.');
                }
                if ($this->lifecycle?->isProtected($id)) {
                    throw new \InvalidArgumentException('Védett referencia-árkönyv nem aktiválható közvetlenül. Készíts róla másolatot.');
                }

                $target->assertDraftMutation();
                $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
                $report = $this->readiness->evaluate($target, $this->rules->listForPriceBook($id), $at);
                if (! $report->ready) {
                    throw new PriceBookNotReadyForActivationException($report);
                }

                $active = $this->books->findCurrentActiveForCurrencyAtForUpdate($currency, $at);
                if (count($active) > 1) {
                    throw new MultipleActivePriceBooksException('Multiple current active HUF price books prevent activation.');
                }

                $actor = new PricingActorId($command->actorId);
                $previousPayload = null;
                if ($active !== []) {
                    $previous = $active[0];
                    if ($previous->id()?->equals($id)) {
                        throw new PersistenceException('The target draft unexpectedly matches the active price book.');
                    }
                    $previousVersion = $previous->version();
                    $previous->retire($actor, $at);
                    $this->books->saveRetired($previous, $previousVersion);
                    $previousPayload = ['id' => $previous->id()?->toInt(), 'label' => $previous->label()];
                }

                $targetVersion = new AggregateVersion($command->expectedVersion);
                $target->activate($actor, $at);
                $this->books->saveActivated($target, $targetVersion);

                if ($this->books->countCurrentActiveForCurrencyAt($currency, $at) !== 1) {
                    throw new PersistenceException('Activation must commit exactly one current active HUF price book.');
                }

                if ($this->lifecycle !== null) {
                    $this->lifecycle->record('price_book_activated', $id, $command->actorId, [
                        'currency' => $currency->code(),
                        'new_active' => ['id' => $id->toInt(), 'label' => $target->label()],
                        'previous_active' => $previousPayload,
                    ], $at);
                }

                return $target;
            });
        } catch (\Throwable $exception) {
            $failure = $exception;
            throw $exception;
        } finally {
            try {
                $this->lock->release($currency);
            } catch (\Throwable $releaseFailure) {
                if ($failure === null) {
                    throw $releaseFailure;
                }
            }
        }
    }
}
