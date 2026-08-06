<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WordPress;

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\LegacyAddressImporter;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\MigrationRunner;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;
use AppleKlinika\CustomerAddressBook\Interfaces\Account\AccountController;
use AppleKlinika\CustomerAddressBook\Interfaces\Checkout\CheckoutAddressController;
use AppleKlinika\CustomerAddressBook\Interfaces\Cli\MigrateLegacyAddressesCommand;
use AppleKlinika\CustomerAddressBook\Interfaces\Privacy\AddressBookPrivacyController;

final class Plugin
{
    public function __construct(
        private readonly MigrationRunner $migrations,
        private readonly AccountController $account,
        private readonly CheckoutAddressController $checkout,
        private readonly AddressBookPrivacyController $privacy,
        private readonly LegacyAddressImporter $importer
    ) {
    }

    public static function create(): self
    {
        global $wpdb;
        $repository = new WordPressAddressRepository($wpdb);
        $projection = new WooUserMetaProjection();
        $countries = new WooAllowedCountries();
        $service = new AddressBookService(
            $repository,
            new WordPressTransactionManager($wpdb),
            $projection,
            $countries
        );
        $importer = new LegacyAddressImporter($service, $repository);

        return new self(
            new MigrationRunner($wpdb),
            new AccountController($service, $importer, $countries),
            new CheckoutAddressController($service, new \AppleKlinika\CustomerAddressBook\Application\Handler\CheckoutAddressSelection($service, $countries), $projection),
            new AddressBookPrivacyController($service, $projection, $wpdb),
            $importer
        );
    }

    public function register(): void
    {
        try {
            $this->migrations->run();
        } catch (\Throwable $exception) {
            error_log('Apple Klinika address book migration failed: ' . $exception->getMessage());
            add_action('admin_notices', static function () use ($exception): void {
                echo '<div class="notice notice-error"><p>' . esc_html($exception->getMessage()) . '</p></div>';
            });
            return;
        }

        $this->account->register();
        $this->checkout->register();
        $this->privacy->register();
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('ak address-book migrate', new MigrateLegacyAddressesCommand($this->importer));
        }
    }
}
