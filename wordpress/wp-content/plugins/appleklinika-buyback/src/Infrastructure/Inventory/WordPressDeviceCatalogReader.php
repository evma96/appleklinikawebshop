<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Inventory;

use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogConfiguration;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;

final class WordPressDeviceCatalogReader implements DeviceCatalogReader
{
    private readonly \Closure $availability;

    public function __construct(
        private readonly string $optionName = 'appleklinika_device_catalog',
        ?callable $availability = null
    ) {
        $this->availability = \Closure::fromCallable($availability ?? static function (): bool {
            return defined('APPLEKLINIKA_INVENTORY_VERSION')
                || (function_exists('is_plugin_active') && is_plugin_active('appleklinika-inventory/appleklinika-inventory.php'));
        });
    }

    public function iPhoneModels(): array
    {
        if (! ($this->availability)()) {
            throw new DeviceCatalogUnavailableException('Az Apple Klinika készülékkatalógus bővítmény nem aktív.');
        }

        $records = $this->catalogRecords();
        if (! is_array($records)) {
            throw new DeviceCatalogUnavailableException('Az Apple Klinika készülékkatalógus nem érhető el.');
        }

        $items = [];
        foreach ($records as $record) {
            if (! is_array($record) || ($record['type'] ?? '') !== 'iphone') {
                continue;
            }
            $key = (string) ($record['key'] ?? '');
            $label = (string) ($record['name'] ?? '');
            if ($key === '' || $label === '') {
                continue;
            }
            $items[$key] = new DeviceCatalogItem($key, $label);
        }

        uasort($items, static fn (DeviceCatalogItem $a, DeviceCatalogItem $b): int => strnatcasecmp($a->label, $b->label));
        return array_values($items);
    }

    public function iPhoneConfigurations(): array
    {
        if (! class_exists(\Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog::class)) {
            throw new DeviceCatalogUnavailableException('Az Apple Klinika inventory tárhelykatalógusa nem érhető el.');
        }

        $items = [];
        foreach ($this->catalogRecords() as $record) {
            if (! is_array($record) || ($record['type'] ?? '') !== 'iphone') {
                continue;
            }
            $modelKey = (string) ($record['key'] ?? '');
            $label = (string) ($record['name'] ?? '');
            $storageKeys = $record['storage_capacity_keys'] ?? null;
            if ($modelKey === '' || $label === '' || ! is_array($storageKeys) || $storageKeys === []) {
                throw new DeviceCatalogUnavailableException('Az inventory iPhone modellhez hiányzik a modellenkénti tárhely-konfiguráció: ' . ($modelKey !== '' ? $modelKey : 'ismeretlen modell') . '.');
            }

            $seenStorageKeys = [];
            foreach ($storageKeys as $storageKey) {
                if (! is_string($storageKey) || isset($seenStorageKeys[$storageKey])) {
                    throw new DeviceCatalogUnavailableException('Az inventory iPhone modell tárhely-konfigurációja érvénytelen: ' . $modelKey . '.');
                }
                $storageGb = \Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog::gigabytes($storageKey);
                if ($storageGb === null) {
                    throw new DeviceCatalogUnavailableException('Az inventory iPhone modell ismeretlen tárhelyértéket tartalmaz: ' . $modelKey . '.');
                }
                $seenStorageKeys[$storageKey] = true;
                $items[$modelKey . ':' . $storageGb] = new DeviceCatalogConfiguration($modelKey, $label, $storageGb);
            }
        }

        uasort($items, static function (DeviceCatalogConfiguration $left, DeviceCatalogConfiguration $right): int {
            $model = strnatcasecmp($left->modelLabel, $right->modelLabel);
            return $model !== 0 ? $model : $left->storageGb <=> $right->storageGb;
        });
        return array_values($items);
    }

    /** @return array<string,array{label:string,colors:array<string,string>}> */
    public function iPhoneCatalog(): array
    {
        $catalog = [];
        foreach ($this->catalogRecords() as $record) {
            if (! is_array($record) || ($record['type'] ?? '') !== 'iphone') {
                continue;
            }
            $key = (string) ($record['key'] ?? '');
            $label = (string) ($record['name'] ?? '');
            if ($key !== '' && $label !== '') {
                $catalog[$key] = ['label' => $label, 'colors' => is_array($record['colors'] ?? null) ? $record['colors'] : []];
            }
        }
        return $catalog;
    }

    /** @return array<mixed> */
    private function catalogRecords(): array
    {
        if (! ($this->availability)()) {
            throw new DeviceCatalogUnavailableException('Az Apple Klinika készülékkatalógus bővítmény nem aktív.');
        }
        if (class_exists(\Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository::class)) {
            return (new \Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository())->all();
        }
        $records = get_option($this->optionName, null);
        if (! is_array($records)) {
            throw new DeviceCatalogUnavailableException('Az Apple Klinika készülékkatalógus nem érhető el.');
        }
        return $records;
    }

}
