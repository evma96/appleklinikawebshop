<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressTransactionManager implements TransactionManager
{
    private bool $active = false;

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new PersistenceException('Nested buyback transactions are not supported.');
        }

        if ($this->database->query('START TRANSACTION') === false) {
            throw new PersistenceException('Could not start the buyback database transaction.');
        }

        $this->active = true;

        try {
            $result = $operation();

            if ($this->database->query('COMMIT') === false) {
                throw new PersistenceException('Could not commit the buyback database transaction.');
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->database->query('ROLLBACK');
            throw $exception;
        } finally {
            $this->active = false;
        }
    }
}
