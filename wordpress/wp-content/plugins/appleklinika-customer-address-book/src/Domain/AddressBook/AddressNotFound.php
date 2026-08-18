<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Domain\AddressBook;

/** An address is not selectable by the current customer. */
final class AddressNotFound extends AddressException
{
}
