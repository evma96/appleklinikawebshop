<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress;

use AppleKlinika\CustomerAddressBook\Application\Port\TransactionManager;

final class WordPressTransactionManager implements TransactionManager
{
    private bool $active = false;

    public function __construct(private readonly \wpdb $database) {}

    public function transactional(callable $operation): mixed
    {
        if ($this->active || $this->database->query('START TRANSACTION') === false) {
            throw new \RuntimeException('A címjegyzék tranzakciója nem indítható.');
        }
        $this->active = true;
        try {
            $result = $operation();
            if ($this->database->query('COMMIT') === false) {
                throw new \RuntimeException('A címjegyzék tranzakciója nem menthető.');
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
