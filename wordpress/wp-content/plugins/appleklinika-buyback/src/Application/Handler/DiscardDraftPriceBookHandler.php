<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\DiscardDraftPriceBook;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Exception\PriceBookHasBusinessReferencesException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\DraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

final class DiscardDraftPriceBookHandler
{
    public const CONFIRMATION_TOKEN = 'TÖRLÉS';

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly DraftPriceBookDiscardRepository $discardRepository,
        private readonly TransactionManager $transactions,
        private readonly ?PriceBookLifecycleRepository $lifecycle = null,
        private readonly ?Clock $clock = null
    ) {
    }

    /** @return array{id:int,label:string,deleted_rule_count:int} */
    public function handle(DiscardDraftPriceBook $command): array
    {
        return $this->transactions->transactional(function () use ($command): array {
            $id = new PriceBookId($command->priceBookId);
            $book = $this->books->getByIdForUpdate($id);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($id);
            }
            $book->assertDraftMutation();

            if (! hash_equals(self::CONFIRMATION_TOKEN, trim($command->confirmationName))) {
                throw new \InvalidArgumentException('A végleges törléshez írd be pontosan ezt: ' . self::CONFIRMATION_TOKEN . '.');
            }

            if ($this->lifecycle?->isProtected($id)) {
                throw new \InvalidArgumentException('Védett referencia-árkönyv nem törölhető.');
            }
            if ($this->lifecycle?->hasEverBeenActive($id)) {
                throw new \InvalidArgumentException('Korábban aktivált árkönyv az előzmények miatt nem törölhető.');
            }
            if ($this->lifecycle?->hasLifecycleDependencies($id)) {
                throw new PriceBookHasBusinessReferencesException('Az árkönyvre mentett igény vagy snapshot hivatkozik.');
            }

            if ($this->discardRepository->hasBusinessReferences($id)) {
                throw PriceBookHasBusinessReferencesException::forId($id);
            }

            $deletedRuleCount = $this->discardRepository->discardDraftWithRules($id);
            if ($this->lifecycle !== null && $this->clock !== null) {
                $this->lifecycle->record('draft_deleted', $id, $command->actorId, ['label' => $book->label(), 'deleted_rule_count' => $deletedRuleCount], $this->clock->now());
            }
            return ['id' => $id->toInt(), 'label' => $book->label(), 'deleted_rule_count' => $deletedRuleCount];
        });
    }
}
