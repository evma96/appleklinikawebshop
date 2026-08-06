<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Domain\AddressBook;

final class Address
{
    public const BILLING = 1;
    public const SHIPPING = 2;
    public const BOTH = 3;
    public const STATUS_ACTIVE = 'active';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const SOURCE_LEGACY = 'legacy';
    public const SOURCE_ACCOUNT = 'account';
    public const SOURCE_CHECKOUT = 'checkout';

    private const LIMITS = [
        'address_key' => 64,
        'label' => 80,
        'first_name' => 100,
        'last_name' => 100,
        'company_name' => 200,
        'tax_number' => 32,
        'state' => 100,
        'postcode' => 32,
        'city' => 100,
        'address_1' => 255,
        'address_2' => 255,
        'house_number' => 50,
        'staircase' => 50,
        'floor' => 50,
        'door' => 50,
        'phone' => 50,
        'email' => 190,
    ];

    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
        $this->assertValid();
    }

    /** @param array<string, mixed> $data */
    public static function create(int $customerId, string $addressKey, array $data): self
    {
        $now = gmdate('Y-m-d H:i:s');

        return new self(array_merge(self::defaults(), $data, [
            'id' => 0,
            'address_key' => trim($addressKey),
            'customer_id' => $customerId,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(array_merge(self::defaults(), $row));
    }

    /** @param array<string, mixed> $changes */
    public function updated(array $changes): self
    {
        unset($changes['id'], $changes['address_key'], $changes['customer_id'], $changes['created_at'], $changes['version']);

        return new self(array_merge($this->data, $changes, [
            'version' => $this->version() + 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));
    }

    public function withId(int $id): self
    {
        return new self(array_merge($this->data, ['id' => $id]));
    }

    public function id(): int { return (int) $this->data['id']; }
    public function key(): string { return (string) $this->data['address_key']; }
    public function customerId(): int { return (int) $this->data['customer_id']; }
    public function version(): int { return (int) $this->data['version']; }
    public function status(): string { return (string) $this->data['status']; }
    public function capabilities(): int { return (int) $this->data['capabilities']; }
    public function supports(string $purpose): bool
    {
        $flag = $purpose === 'billing' ? self::BILLING : ($purpose === 'shipping' ? self::SHIPPING : 0);
        return $flag > 0 && ($this->capabilities() & $flag) === $flag;
    }

    public function canBeDefault(string $purpose): bool
    {
        return $this->status() === self::STATUS_ACTIVE && $this->supports($purpose);
    }

    /** @return array<string, mixed> */
    public function toArray(): array { return $this->data; }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return [
            'id' => 0, 'address_key' => '', 'customer_id' => 0, 'label' => '', 'capabilities' => 0,
            'first_name' => '', 'last_name' => '', 'company_name' => '', 'tax_number' => '',
            'country' => 'HU', 'state' => '', 'postcode' => '', 'city' => '', 'address_1' => '',
            'address_2' => '', 'house_number' => '', 'staircase' => '', 'floor' => '', 'door' => '',
            'phone' => '', 'email' => '', 'status' => self::STATUS_ACTIVE, 'version' => 1,
            'source' => self::SOURCE_ACCOUNT, 'legacy_fingerprint' => null, 'created_at' => '',
            'updated_at' => '', 'last_used_at' => null,
        ];
    }

    private function assertValid(): void
    {
        if ((int) $this->data['id'] < 0 || (int) $this->data['customer_id'] <= 0) {
            throw new AddressException('Érvénytelen cím- vagy ügyfélazonosító.');
        }

        $this->data['label'] = trim((string) $this->data['label']);
        $this->data['country'] = strtoupper(trim((string) $this->data['country']));
        $this->data['email'] = strtolower(trim((string) $this->data['email']));
        $this->data['phone'] = trim((string) $this->data['phone']);

        if ($this->data['address_key'] === '' || ! preg_match('/^[A-Za-z0-9_-]{20,64}$/', (string) $this->data['address_key'])) {
            throw new AddressException('A nyilvános címkulcs érvénytelen.');
        }
        if ($this->data['label'] === '') {
            throw new AddressException('A cím elnevezése kötelező.');
        }
        if (! in_array((int) $this->data['capabilities'], [self::BILLING, self::SHIPPING, self::BOTH], true)) {
            throw new AddressException('Legalább egy címfelhasználást válassz.');
        }
        if (! preg_match('/^[A-Z]{2}$/', (string) $this->data['country'])) {
            throw new AddressException('Az országkód érvénytelen.');
        }
        if (! in_array((string) $this->data['status'], [self::STATUS_ACTIVE, self::STATUS_NEEDS_REVIEW], true)) {
            throw new AddressException('A cím állapota érvénytelen.');
        }
        if (! in_array((string) $this->data['source'], [self::SOURCE_LEGACY, self::SOURCE_ACCOUNT, self::SOURCE_CHECKOUT], true)) {
            throw new AddressException('A cím forrása érvénytelen.');
        }
        if ((int) $this->data['version'] < 1) {
            throw new AddressException('A cím verziója érvénytelen.');
        }

        foreach (self::LIMITS as $field => $maximum) {
            if (mb_strlen((string) ($this->data[$field] ?? '')) > $maximum) {
                throw new AddressException('A(z) ' . $field . ' mező túl hosszú.');
            }
        }

        if ($this->status() === self::STATUS_ACTIVE) {
            if ((string) $this->data['postcode'] === '' || (string) $this->data['city'] === '' || (string) $this->data['address_1'] === '') {
                throw new AddressException('Az irányítószám, a város és a cím megadása kötelező.');
            }
            if ((string) $this->data['company_name'] === '' && ((string) $this->data['first_name'] === '' || (string) $this->data['last_name'] === '')) {
                throw new AddressException('A név vagy a cégnév megadása kötelező.');
            }
            if ($this->supports('billing') && (string) $this->data['country'] === 'HU' && (string) $this->data['company_name'] !== '') {
                if (! preg_match('/^\d{8}-[1-5]-\d{2}$/', (string) $this->data['tax_number'])) {
                    throw new AddressException('Magyar céges számlázási címhez érvényes adószám szükséges.');
                }
            }
        }
    }
}
