<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\DiscardDraftPriceBook;
use AppleKlinika\Buyback\Application\Exception\PriceBookHasBusinessReferencesException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\DraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

final class DiscardDraftPriceBookHandler
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly DraftPriceBookDiscardRepository $discardRepository,
        private readonly TransactionManager $transactions
    ) {
    }

    public function handle(DiscardDraftPriceBook $command): void
    {
        if (! hash_equals(DiscardDraftPriceBook::CONFIRMATION, $command->confirmation)) {
            throw new \InvalidArgumentException('A módosítás végleges elvetését meg kell erősíteni.');
        }

        $this->transactions->transactional(function () use ($command): void {
            $id = new PriceBookId($command->priceBookId);
            $book = $this->books->getByIdForUpdate($id);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($id);
            }
            $book->assertDraftMutation();

            if ($this->discardRepository->hasBusinessReferences($id)) {
                throw PriceBookHasBusinessReferencesException::forId($id);
            }

            $this->discardRepository->discardDraftWithRules($id);
        });
    }
}
