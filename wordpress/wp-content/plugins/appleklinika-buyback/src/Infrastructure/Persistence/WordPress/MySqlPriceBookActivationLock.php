<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Exception\PriceBookActivationBusyException;
use AppleKlinika\Buyback\Application\Port\PriceBookActivationLock;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class MySqlPriceBookActivationLock implements PriceBookActivationLock
{
    /** @var array<string, true> */
    private array $held = [];

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function acquire(CurrencyCode $currency, int $timeoutSeconds): void
    {
        if ($timeoutSeconds < 0 || $timeoutSeconds > 10) {
            throw new \InvalidArgumentException('Activation lock timeout must be between 0 and 10 seconds.');
        }
        $key = $this->key($currency);
        if (isset($this->held[$key])) {
            throw new PriceBookActivationBusyException('The activation lock is already held by this operation.');
        }

        $result = $this->database->get_var($this->database->prepare('SELECT GET_LOCK(%s, %d)', $key, $timeoutSeconds));
        if ((int) $result !== 1) {
            throw new PriceBookActivationBusyException('Another price-book activation is already in progress.');
        }
        $this->held[$key] = true;
    }

    public function release(CurrencyCode $currency): void
    {
        $key = $this->key($currency);
        if (! isset($this->held[$key])) {
            return;
        }
        $result = $this->database->get_var($this->database->prepare('SELECT RELEASE_LOCK(%s)', $key));
        unset($this->held[$key]);
        if ((int) $result !== 1) {
            throw new PersistenceException('Could not release the price-book activation lock.');
        }
    }

    private function key(CurrencyCode $currency): string
    {
        return 'appleklinika_buyback_pricebook_activation_' . $currency->code();
    }
}
