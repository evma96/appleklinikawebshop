<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress;

final class MigrationRunner
{
    public function __construct(private readonly \wpdb $database)
    {
    }

    public function run(): void
    {
        $installed = (string) get_option(Schema::OPTION_SCHEMA_VERSION, '0');
        $coreMigration = new CoreSchemaMigration($this->database);
        if (version_compare($installed, APPLEKLINIKA_ADDRESS_BOOK_SCHEMA_VERSION, '>')) {
            throw new \RuntimeException('A telepített címjegyzék-séma újabb a futó pluginnél.');
        }
        if ($installed === APPLEKLINIKA_ADDRESS_BOOK_SCHEMA_VERSION) {
            try {
                $coreMigration->assertComplete();
            } catch (\RuntimeException) {
                $coreMigration->up();
            }
            $coreMigration->assertComplete();
            update_option(Schema::OPTION_PLUGIN_VERSION, APPLEKLINIKA_ADDRESS_BOOK_VERSION, false);
            return;
        }

        $versions = require APPLEKLINIKA_ADDRESS_BOOK_PATH . '/migrations/versions.php';
        if (! is_array($versions)) {
            throw new \RuntimeException('A címjegyzék migrációs konfigurációja érvénytelen.');
        }
        uksort($versions, 'version_compare');
        foreach ($versions as $version => $migrationClass) {
            if (version_compare((string) $version, $installed, '<=') || version_compare((string) $version, APPLEKLINIKA_ADDRESS_BOOK_SCHEMA_VERSION, '>')) {
                continue;
            }
            if (! is_string($migrationClass) || ! class_exists($migrationClass)) {
                throw new \RuntimeException('Ismeretlen címjegyzék-migráció.');
            }
            (new $migrationClass($this->database))->up();
            update_option(Schema::OPTION_SCHEMA_VERSION, (string) $version, false);
            $installed = (string) $version;
        }
        if ($installed !== APPLEKLINIKA_ADDRESS_BOOK_SCHEMA_VERSION) {
            throw new \RuntimeException('Nincs migráció a deklarált címjegyzék-sémaverzióhoz.');
        }
        $coreMigration->assertComplete();
        update_option(Schema::OPTION_PLUGIN_VERSION, APPLEKLINIKA_ADDRESS_BOOK_VERSION, false);
    }
}
