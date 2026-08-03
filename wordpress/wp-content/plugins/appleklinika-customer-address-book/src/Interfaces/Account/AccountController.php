<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Interfaces\Account;

use AppleKlinika\CustomerAddressBook\Application\Command\CreateAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\DeleteAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\SetDefaultAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\UpdateAddress;
use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\LegacyAddressImporter;
use AppleKlinika\CustomerAddressBook\Application\Port\AllowedCountries;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;
use AppleKlinika\CustomerAddressBook\Application\Query\GetCustomerAddress;
use AppleKlinika\CustomerAddressBook\Application\Query\ListCustomerAddresses;

final class AccountController
{
    private const POST_ACTION = 'ak_customer_address_book';

    public function __construct(
        private readonly AddressBookService $service,
        private readonly LegacyAddressImporter $importer,
        private readonly AllowedCountries $countries
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_filter('query_vars', [$this, 'queryVars']);
        add_filter('woocommerce_account_menu_items', [$this, 'menuItems'], 100);
        add_action('woocommerce_account_cimeim_endpoint', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_' . self::POST_ACTION, [$this, 'handlePost']);
        add_action('admin_post_nopriv_' . self::POST_ACTION, [$this, 'requireLogin']);
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint('cimeim', EP_ROOT | EP_PAGES);
    }

    /** @param array<int, string> $variables @return array<int, string> */
    public function queryVars(array $variables): array
    {
        $variables[] = 'cimeim';
        return $variables;
    }

    /** @param array<string, string> $items @return array<string, string> */
    public function menuItems(array $items): array
    {
        $result = [];
        foreach ($items as $key => $label) {
            if ($key === 'edit-account') {
                $result['cimeim'] = 'Címeim';
            }
            $result[$key] = $label;
        }
        if (! isset($result['cimeim'])) {
            $result['cimeim'] = 'Címeim';
        }
        return $result;
    }

    public function assets(): void
    {
        if (! function_exists('is_account_page') || ! is_account_page()) {
            return;
        }
        wp_enqueue_style(
            'appleklinika-customer-address-book',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/css/account-address-book.css',
            [],
            APPLEKLINIKA_ADDRESS_BOOK_VERSION
        );
        wp_enqueue_script(
            'appleklinika-customer-address-book',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/js/account-address-book.js',
            [],
            APPLEKLINIKA_ADDRESS_BOOK_VERSION,
            true
        );
    }

    public function requireLogin(): void
    {
        wp_safe_redirect(wp_login_url($this->url()));
        exit;
    }

    public function render(): void
    {
        $customerId = get_current_user_id();
        if ($customerId <= 0) {
            $this->requireLogin();
        }

        $this->importer->import($customerId);
        $this->renderStoredNotice($customerId);
        $mode = isset($_GET['address-book-action']) ? sanitize_key(wp_unslash((string) $_GET['address-book-action'])) : 'list';
        $key = isset($_GET['address']) ? sanitize_text_field(wp_unslash((string) $_GET['address'])) : '';

        echo '<section class="ak-address-book">';
        try {
            if ($mode === 'add') {
                $this->renderForm(null);
            } elseif ($mode === 'edit' && $key !== '') {
                $this->renderForm($this->service->handleGet(new GetCustomerAddress($customerId, $key)));
            } elseif ($mode === 'delete' && $key !== '') {
                $this->renderDelete($this->service->handleGet(new GetCustomerAddress($customerId, $key)));
            } else {
                $this->renderList($customerId);
            }
        } catch (AddressException $exception) {
            echo '<div class="woocommerce-error" role="alert">' . esc_html($exception->getMessage()) . '</div>';
            $this->renderList($customerId);
        }
        echo '</section>';
    }

    public function handlePost(): void
    {
        $customerId = get_current_user_id();
        if ($customerId <= 0) {
            $this->requireLogin();
        }
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash((string) $_POST['operation'])) : '';
        if (! wp_verify_nonce(
            isset($_POST['_ak_address_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_ak_address_nonce'])) : '',
            'ak_address_' . $operation
        )) {
            wp_die(esc_html('A biztonsági ellenőrzés sikertelen.'), '', ['response' => 403]);
        }

        try {
            $key = isset($_POST['address_key']) ? sanitize_text_field(wp_unslash((string) $_POST['address_key'])) : '';
            $version = isset($_POST['version']) ? absint($_POST['version']) : 0;
            if ($operation === 'create') {
                $this->service->handleCreate(new CreateAddress($customerId, $this->postedAddress(), isset($_POST['default_billing']), isset($_POST['default_shipping'])));
                $message = 'A cím sikeresen elmentve.';
            } elseif ($operation === 'update') {
                $this->service->handleUpdate(new UpdateAddress(
                    $customerId,
                    $key,
                    $version,
                    $this->postedAddress(),
                    isset($_POST['default_billing']),
                    isset($_POST['default_shipping'])
                ));
                $message = 'A cím módosításai elmentve.';
            } elseif ($operation === 'delete') {
                $this->service->handleDelete(new DeleteAddress($customerId, $key, $version, [
                    'billing' => isset($_POST['successor_billing']) ? sanitize_text_field(wp_unslash((string) $_POST['successor_billing'])) : '',
                    'shipping' => isset($_POST['successor_shipping']) ? sanitize_text_field(wp_unslash((string) $_POST['successor_shipping'])) : '',
                ]));
                $message = 'A cím véglegesen törölve.';
            } elseif ($operation === 'set_default') {
                $this->service->handleSetDefault(new SetDefaultAddress(
                    $customerId,
                    $key,
                    $version,
                    isset($_POST['purpose']) ? sanitize_key(wp_unslash((string) $_POST['purpose'])) : ''
                ));
                $message = 'Az alapértelmezett cím frissítve.';
            } else {
                throw new AddressException('Ismeretlen művelet.');
            }
            $this->storeNotice($customerId, $message, 'success');
            wp_safe_redirect($this->url());
        } catch (\Throwable $exception) {
            $this->storeNotice($customerId, $exception->getMessage(), 'error');
            $fallback = isset($key) && $key !== '' && in_array($operation, ['update', 'delete'], true)
                ? add_query_arg(['address-book-action' => $operation === 'update' ? 'edit' : 'delete', 'address' => $key], $this->url())
                : $this->url();
            wp_safe_redirect($fallback);
        }
        exit;
    }

    private function renderList(int $customerId): void
    {
        $addresses = $this->service->handleList(new ListCustomerAddresses($customerId));
        $billing = $this->service->getDefault($customerId, 'billing');
        $shipping = $this->service->getDefault($customerId, 'shipping');
        echo '<header class="ak-address-book__header"><div><p class="ak-address-book__kicker">Mentett címek</p><h2>Címeim</h2><p>Itt kezelheted a számlázási és szállítási címeidet.</p></div>';
        echo '<a class="button ak-address-book__add" href="' . esc_url(add_query_arg('address-book-action', 'add', $this->url())) . '">Új cím hozzáadása</a></header>';
        if ($addresses === []) {
            echo '<div class="ak-address-book__empty"><h3>Még nincs mentett címed</h3><p>Adj hozzá egy címet, hogy később gyorsabban választhass.</p></div>';
            return;
        }
        echo '<div class="ak-address-book__grid">';
        foreach ($addresses as $address) {
            $data = $address->toArray();
            $isBilling = $billing !== null && $billing->id() === $address->id();
            $isShipping = $shipping !== null && $shipping->id() === $address->id();
            echo '<article class="ak-address-card">';
            echo '<div class="ak-address-card__heading"><h3>' . esc_html((string) $data['label']) . '</h3><div class="ak-address-card__badges">';
            if ($address->supports('billing')) echo '<span>Számlázási</span>';
            if ($address->supports('shipping')) echo '<span>Szállítási</span>';
            if ($isBilling) echo '<span class="is-default">Alapértelmezett számlázási</span>';
            if ($isShipping) echo '<span class="is-default">Alapértelmezett szállítási</span>';
            if ($address->status() === Address::STATUS_NEEDS_REVIEW) echo '<span class="is-review">Ellenőrzést igényel</span>';
            echo '</div></div>';
            $name = (string) ($data['company_name'] ?: trim($data['last_name'] . ' ' . $data['first_name']));
            echo '<p class="ak-address-card__name">' . esc_html($name) . '</p>';
            echo '<p class="ak-address-card__preview">' . esc_html(trim($data['postcode'] . ' ' . $data['city'] . ', ' . $data['address_1'] . ' ' . $data['house_number'])) . '</p>';
            echo '<div class="ak-address-card__actions">';
            echo '<a class="button" href="' . esc_url(add_query_arg(['address-book-action' => 'edit', 'address' => $address->key()], $this->url())) . '">Szerkesztés</a>';
            if ($address->canBeDefault('billing') && ! $isBilling) $this->renderDefaultForm($address, 'billing');
            if ($address->canBeDefault('shipping') && ! $isShipping) $this->renderDefaultForm($address, 'shipping');
            echo '<a class="button ak-address-card__delete" href="' . esc_url(add_query_arg(['address-book-action' => 'delete', 'address' => $address->key()], $this->url())) . '">Törlés</a>';
            echo '</div></article>';
        }
        echo '</div>';
    }

    private function renderDefaultForm(Address $address, string $purpose): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::POST_ACTION) . '"><input type="hidden" name="operation" value="set_default">';
        echo '<input type="hidden" name="address_key" value="' . esc_attr($address->key()) . '"><input type="hidden" name="version" value="' . esc_attr((string) $address->version()) . '">';
        echo '<input type="hidden" name="purpose" value="' . esc_attr($purpose) . '">';
        wp_nonce_field('ak_address_set_default', '_ak_address_nonce');
        echo '<button class="button" type="submit">Alapértelmezett ' . ($purpose === 'billing' ? 'számlázási' : 'szállítási') . '</button></form>';
    }

    private function renderForm(?Address $address): void
    {
        $data = $address?->toArray() ?? ['label' => '', 'capabilities' => Address::BOTH, 'first_name' => '', 'last_name' => '', 'company_name' => '', 'tax_number' => '', 'country' => 'HU', 'state' => '', 'postcode' => '', 'city' => '', 'address_1' => '', 'address_2' => '', 'house_number' => '', 'staircase' => '', 'floor' => '', 'door' => '', 'phone' => '', 'email' => ''];
        $operation = $address === null ? 'create' : 'update';
        echo '<header class="ak-address-book__header"><div><p class="ak-address-book__kicker">Címadatok</p><h2>' . ($address === null ? 'Új cím' : 'Cím szerkesztése') . '</h2></div></header>';
        echo '<form class="ak-address-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::POST_ACTION) . '"><input type="hidden" name="operation" value="' . esc_attr($operation) . '">';
        if ($address !== null) {
            echo '<input type="hidden" name="address_key" value="' . esc_attr($address->key()) . '"><input type="hidden" name="version" value="' . esc_attr((string) $address->version()) . '">';
        }
        wp_nonce_field('ak_address_' . $operation, '_ak_address_nonce');
        echo '<div class="ak-address-form__grid">';
        $this->field('label', 'Cím elnevezése', (string) $data['label'], true, 'text', 80);
        echo '<fieldset class="ak-address-form__purposes"><legend>Felhasználás</legend>';
        echo '<label><input type="checkbox" name="purpose_billing" value="1" ' . checked(((int) $data['capabilities'] & Address::BILLING) > 0, true, false) . '> Számlázási</label>';
        echo '<label><input type="checkbox" name="purpose_shipping" value="1" ' . checked(((int) $data['capabilities'] & Address::SHIPPING) > 0, true, false) . '> Szállítási</label></fieldset>';
        $this->field('first_name', 'Keresztnév', (string) $data['first_name']);
        $this->field('last_name', 'Vezetéknév', (string) $data['last_name']);
        $this->field('company_name', 'Cégnév', (string) $data['company_name']);
        $this->field('tax_number', 'Magyar adószám', (string) $data['tax_number']);
        echo '<label>Ország<select name="country" required>';
        foreach ($this->countries->all() as $code => $label) echo '<option value="' . esc_attr($code) . '" ' . selected($data['country'], $code, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label>';
        $this->field('state', 'Megye / állam', (string) $data['state']);
        $this->field('postcode', 'Irányítószám', (string) $data['postcode'], true);
        $this->field('city', 'Város', (string) $data['city'], true);
        $this->field('address_1', 'Utca / cím', (string) $data['address_1'], true);
        $this->field('address_2', 'Cím második sora', (string) $data['address_2']);
        $this->field('house_number', 'Házszám', (string) $data['house_number']);
        $this->field('staircase', 'Lépcsőház', (string) $data['staircase']);
        $this->field('floor', 'Emelet', (string) $data['floor']);
        $this->field('door', 'Ajtó', (string) $data['door']);
        $this->field('phone', 'Telefonszám', (string) $data['phone'], false, 'tel');
        $this->field('email', 'E-mail cím', (string) $data['email'], false, 'email');
        echo '<label class="ak-address-form__check"><input type="checkbox" name="default_billing" value="1"> Legyen alapértelmezett számlázási cím</label>';
        echo '<label class="ak-address-form__check"><input type="checkbox" name="default_shipping" value="1"> Legyen alapértelmezett szállítási cím</label>';
        echo '</div><div class="ak-address-form__actions"><a class="button" href="' . esc_url($this->url()) . '">Mégse</a><button class="button" type="submit">Cím mentése</button></div></form>';
    }

    private function renderDelete(Address $address): void
    {
        $customerId = $address->customerId();
        echo '<div class="ak-address-book__confirm"><h2>Biztosan törlöd ezt a címet?</h2><p>A törlés végleges, de a korábbi rendelések adatai nem változnak.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::POST_ACTION) . '"><input type="hidden" name="operation" value="delete">';
        echo '<input type="hidden" name="address_key" value="' . esc_attr($address->key()) . '"><input type="hidden" name="version" value="' . esc_attr((string) $address->version()) . '">';
        wp_nonce_field('ak_address_delete', '_ak_address_nonce');
        foreach (['billing', 'shipping'] as $purpose) {
            $default = $this->service->getDefault($customerId, $purpose);
            $alternatives = array_filter($this->service->list($customerId), static fn (Address $item): bool => $item->key() !== $address->key() && $item->canBeDefault($purpose));
            if ($default === null || $default->key() !== $address->key() || $alternatives === []) continue;
            echo '<label>Új alapértelmezett ' . ($purpose === 'billing' ? 'számlázási' : 'szállítási') . ' cím<select name="successor_' . esc_attr($purpose) . '" required><option value="">Válassz címet</option>';
            foreach ($alternatives as $candidate) echo '<option value="' . esc_attr($candidate->key()) . '">' . esc_html((string) $candidate->toArray()['label']) . '</option>';
            echo '</select></label>';
        }
        echo '<div class="ak-address-form__actions"><a class="button" href="' . esc_url($this->url()) . '">Mégse</a><button class="button ak-address-card__delete" type="submit">Cím végleges törlése</button></div></form></div>';
    }

    private function field(string $name, string $label, string $value, bool $required = false, string $type = 'text', int $maxlength = 255): void
    {
        echo '<label>' . esc_html($label) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" maxlength="' . esc_attr((string) $maxlength) . '" ' . ($required ? 'required' : '') . '></label>';
    }

    /** @return array<string, mixed> */
    private function postedAddress(): array
    {
        $fields = ['label','first_name','last_name','company_name','tax_number','country','state','postcode','city','address_1','address_2','house_number','staircase','floor','door','phone','email'];
        $data = [];
        foreach ($fields as $field) {
            $raw = isset($_POST[$field]) ? wp_unslash((string) $_POST[$field]) : '';
            $data[$field] = $field === 'email' ? sanitize_email($raw) : sanitize_text_field($raw);
        }
        $data['country'] = strtoupper((string) $data['country']);
        $data['phone'] = preg_replace('/[^0-9+() .\/-]/', '', (string) $data['phone']) ?: '';
        $data['capabilities'] = (isset($_POST['purpose_billing']) ? Address::BILLING : 0) | (isset($_POST['purpose_shipping']) ? Address::SHIPPING : 0);
        $data['status'] = Address::STATUS_ACTIVE;
        $data['source'] = Address::SOURCE_ACCOUNT;
        return $data;
    }

    private function url(): string
    {
        return wc_get_account_endpoint_url('cimeim');
    }

    private function storeNotice(int $customerId, string $message, string $type): void
    {
        set_transient('ak_address_book_notice_' . $customerId, [
            'message' => sanitize_text_field($message),
            'type' => $type === 'success' ? 'success' : 'error',
        ], 120);
    }

    private function renderStoredNotice(int $customerId): void
    {
        $key = 'ak_address_book_notice_' . $customerId;
        $notice = get_transient($key);
        delete_transient($key);
        if (! is_array($notice) || ! isset($notice['message'], $notice['type'])) {
            return;
        }
        $class = $notice['type'] === 'success' ? 'woocommerce-message' : 'woocommerce-error';
        echo '<div class="' . esc_attr($class) . '" role="' . ($notice['type'] === 'success' ? 'status' : 'alert') . '">';
        echo esc_html((string) $notice['message']);
        echo '</div>';
    }
}
