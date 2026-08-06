<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Interfaces\Privacy;

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\LegacyAddressImporter;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;

/** WordPress privacy bridge for canonical saved-address data only. */
final class AddressBookPrivacyController
{
    private const GROUP_ID = 'appleklinika-customer-address-book';
    private const SESSION_KEY = 'appleklinika_address_book_checkout';
    private const ORDER_META_PREFIX = '_appleklinika_address_book_';

    public function __construct(
        private readonly AddressBookService $service,
        private readonly AddressProjection $projection,
        private readonly \wpdb $database
    ) {
    }

    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerErasers']);
        add_action('woocommerce_privacy_before_remove_order_personal_data', [$this, 'anonymizeOrderReferences']);
    }

    /** @param array<string, array<string, mixed>> $exporters @return array<string, array<string, mixed>> */
    public function registerExporters(array $exporters): array
    {
        $exporters[self::GROUP_ID] = [
            'exporter_friendly_name' => 'Apple Klinika – Mentett címek',
            'callback' => [$this, 'exporter'],
        ];
        return $exporters;
    }

    /** @param array<string, array<string, mixed>> $erasers @return array<string, array<string, mixed>> */
    public function registerErasers(array $erasers): array
    {
        $erasers[self::GROUP_ID] = [
            'eraser_friendly_name' => 'Apple Klinika – Mentett címek',
            'callback' => [$this, 'eraser'],
        ];
        return $erasers;
    }

    /** @return array{data: array<int, array<string, mixed>>, done: bool} */
    public function exporter(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', sanitize_email($emailAddress));
        if (! $user instanceof \WP_User || $page > 1) {
            return ['data' => [], 'done' => true];
        }

        $billing = $this->service->getDefault((int) $user->ID, 'billing');
        $shipping = $this->service->getDefault((int) $user->ID, 'shipping');
        $items = [];
        foreach ($this->service->list((int) $user->ID) as $address) {
            $data = $address->toArray();
            $items[] = [
                'group_id' => self::GROUP_ID,
                'group_label' => 'Apple Klinika – Mentett címek',
                'item_id' => self::GROUP_ID . '-' . hash('sha256', $address->key()),
                'data' => $this->exportFields($address, $billing, $shipping, $data),
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    /** @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool} */
    public function eraser(string $emailAddress, int $page = 1): array
    {
        $response = ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        $user = get_user_by('email', sanitize_email($emailAddress));
        if (! $user instanceof \WP_User || $page > 1) {
            return $response;
        }

        try {
            $customerId = (int) $user->ID;
            $removedAddresses = $this->service->eraseForCustomer($customerId);
            $hadMarker = metadata_exists('user', $customerId, LegacyAddressImporter::USER_META_VERSION);
            delete_user_meta($customerId, LegacyAddressImporter::USER_META_VERSION);
            $this->projection->clear($customerId, 'billing');
            $this->projection->clear($customerId, 'shipping');
            $clearedSession = $this->clearAddressSelectionSession($customerId);
            $response['items_removed'] = $removedAddresses > 0 || $hadMarker || $clearedSession;
        } catch (\Throwable $exception) {
            $response['items_retained'] = true;
            $response['messages'][] = 'A mentett címek törlése nem fejeződött be.';
        }

        return $response;
    }

    public function anonymizeOrderReferences(\WC_Order $order): void
    {
        foreach (['billing', 'shipping'] as $purpose) {
            foreach (['key', 'version', 'mode', 'save', 'default'] as $suffix) {
                $order->delete_meta_data(self::ORDER_META_PREFIX . $purpose . '_' . $suffix);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<int, array{name: string, value: string}> */
    private function exportFields(Address $address, ?Address $billing, ?Address $shipping, array $data): array
    {
        $capabilities = [];
        if ($address->supports('billing')) {
            $capabilities[] = 'Számlázási cím';
        }
        if ($address->supports('shipping')) {
            $capabilities[] = 'Szállítási cím';
        }

        return [
            ['name' => 'Cím elnevezése', 'value' => (string) $data['label']],
            ['name' => 'Felhasználás', 'value' => implode(', ', $capabilities)],
            ['name' => 'Alapértelmezett számlázási cím', 'value' => $billing !== null && $billing->key() === $address->key() ? 'Igen' : 'Nem'],
            ['name' => 'Alapértelmezett szállítási cím', 'value' => $shipping !== null && $shipping->key() === $address->key() ? 'Igen' : 'Nem'],
            ['name' => 'Név', 'value' => trim((string) $data['first_name'] . ' ' . (string) $data['last_name'])],
            ['name' => 'Cégnév', 'value' => (string) $data['company_name']],
            ['name' => 'Adószám', 'value' => (string) $data['tax_number']],
            ['name' => 'Ország', 'value' => (string) $data['country']],
            ['name' => 'Megye / régió', 'value' => (string) $data['state']],
            ['name' => 'Irányítószám', 'value' => (string) $data['postcode']],
            ['name' => 'Város', 'value' => (string) $data['city']],
            ['name' => 'Cím', 'value' => (string) $data['address_1']],
            ['name' => 'Cím kiegészítés', 'value' => (string) $data['address_2']],
            ['name' => 'Házszám', 'value' => (string) $data['house_number']],
            ['name' => 'Lépcsőház', 'value' => (string) $data['staircase']],
            ['name' => 'Emelet', 'value' => (string) $data['floor']],
            ['name' => 'Ajtó', 'value' => (string) $data['door']],
            ['name' => 'Létrehozva', 'value' => (string) $data['created_at']],
            ['name' => 'Utoljára módosítva', 'value' => (string) $data['updated_at']],
        ];
    }

    private function clearAddressSelectionSession(int $customerId): bool
    {
        $table = $this->database->prefix . 'woocommerce_sessions';
        $sessionKey = (string) $customerId;
        $raw = $this->database->get_var($this->database->prepare(
            "SELECT session_value FROM {$table} WHERE session_key = %s",
            $sessionKey
        ));
        if (! is_string($raw)) {
            return false;
        }
        $session = maybe_unserialize($raw);
        if (! is_array($session) || ! array_key_exists(self::SESSION_KEY, $session)) {
            return false;
        }
        unset($session[self::SESSION_KEY]);
        return $this->database->update($table, ['session_value' => maybe_serialize($session)], ['session_key' => $sessionKey]) !== false;
    }
}
