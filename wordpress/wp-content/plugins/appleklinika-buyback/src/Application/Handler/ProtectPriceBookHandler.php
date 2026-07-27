<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\ProtectPriceBook;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

final class ProtectPriceBookHandler
{
    public function __construct(private readonly PriceBookRepository $books, private readonly PriceBookLifecycleRepository $lifecycle, private readonly TransactionManager $transactions, private readonly Clock $clock) {}

    /** @return array{id:int,label:string,currency:string,previous_id:?int} */
    public function handle(ProtectPriceBook $command): array
    {
        return $this->transactions->transactional(function () use ($command): array {
            $id = new PriceBookId($command->priceBookId);
            $book = $this->books->getByIdForUpdate($id);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($id);
            }
            if (! hash_equals($book->label(), trim($command->confirmationName))) {
                throw new \InvalidArgumentException('A védett referencia kijelöléséhez írd be pontosan az árkönyv nevét.');
            }
            $at = $this->clock->now();
            $previous = $this->lifecycle->moveProtectedReference($book->currency(), $id, $command->actorId, $at);
            $this->lifecycle->record('protected_reference_changed', $id, $command->actorId, [
                'currency' => $book->currency()->code(),
                'previous_price_book_id' => $previous?->toInt(),
                'new_price_book_id' => $id->toInt(),
            ], $at);
            return ['id' => $id->toInt(), 'label' => $book->label(), 'currency' => $book->currency()->code(), 'previous_id' => $previous?->toInt()];
        });
    }
}
