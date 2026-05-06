<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Domain\DeviceCatalog\DeviceType;

final class DeviceCatalogRepository
{
    private const OPTION_NAME = 'appleklinika_device_catalog';
    private const VERSION_OPTION_NAME = 'appleklinika_device_catalog_version';
    private const CURRENT_VERSION = 2;

    /**
     * @return array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}>
     */
    public function all(): array
    {
        $catalog = get_option(self::OPTION_NAME);

        if (! is_array($catalog) || $catalog === []) {
            $catalog = $this->defaultCatalog();
            update_option(self::OPTION_NAME, $catalog, false);
            update_option(self::VERSION_OPTION_NAME, self::CURRENT_VERSION, false);
        }

        if ((int) get_option(self::VERSION_OPTION_NAME, 0) < self::CURRENT_VERSION) {
            $catalog = $this->defaultCatalog();
            update_option(self::OPTION_NAME, $catalog, false);
            update_option(self::VERSION_OPTION_NAME, self::CURRENT_VERSION, false);
        }

        return $catalog;
    }

    /**
     * @return array<string, string>
     */
    public function modelOptions(): array
    {
        $options = ['' => 'Válassz modellt'];

        foreach ($this->all() as $device) {
            $options[$device['key']] = $device['name'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function colorOptions(): array
    {
        $options = ['' => 'Válassz színt'];

        foreach ($this->all() as $device) {
            foreach ($device['colors'] as $key => $label) {
                $options[$key] = $label;
            }
        }

        asort($options);

        return $options;
    }

    /**
     * @param array<string, string> $colors
     */
    public function addDevice(string $name, string $type, int $year, array $colors): void
    {
        if ($name === '' || ! DeviceType::isAllowed($type) || $year < 2007) {
            return;
        }

        $catalog = $this->all();
        $catalog[] = $this->deviceData($name, $type, $year, $colors);

        update_option(self::OPTION_NAME, $catalog, false);
    }

    /**
     * @param array<string, string> $colors
     */
    public function updateDevice(string $key, string $name, string $type, int $year, array $colors): void
    {
        if ($key === '' || $name === '' || ! DeviceType::isAllowed($type) || $year < 2007) {
            return;
        }

        $catalog = array_map(function (array $device) use ($key, $name, $type, $year, $colors): array {
            if (($device['key'] ?? '') !== $key) {
                return $device;
            }

            return [
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'year' => $year,
                'colors' => $colors,
            ];
        }, $this->all());

        update_option(self::OPTION_NAME, $catalog, false);
    }

    public function deleteDevice(string $key): void
    {
        if ($key === '') {
            return;
        }

        $catalog = array_values(array_filter($this->all(), static function (array $device) use ($key): bool {
            return ($device['key'] ?? '') !== $key;
        }));

        update_option(self::OPTION_NAME, $catalog, false);
    }

    /**
     * @return array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}>
     */
    private function defaultCatalog(): array
    {
        return [
            $this->device('iPhone XR', 2018, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'blue' => 'Kék (Blue)', 'yellow' => 'Sárga (Yellow)', 'coral' => 'Korall (Coral)', 'product_red' => '(PRODUCT)RED']),
            $this->device('iPhone XS', 2018, ['silver' => 'Ezüst (Silver)', 'space_gray' => 'Asztroszürke (Space Gray)', 'gold' => 'Arany (Gold)']),
            $this->device('iPhone XS Max', 2018, ['silver' => 'Ezüst (Silver)', 'space_gray' => 'Asztroszürke (Space Gray)', 'gold' => 'Arany (Gold)']),
            $this->device('iPhone 11', 2019, ['black' => 'Fekete (Black)', 'green' => 'Zöld (Green)', 'yellow' => 'Sárga (Yellow)', 'purple' => 'Lila (Purple)', 'product_red' => '(PRODUCT)RED', 'white' => 'Fehér (White)']),
            $this->device('iPhone 11 Pro', 2019, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)', 'midnight_green' => 'Éjzöld (Midnight Green)']),
            $this->device('iPhone 11 Pro Max', 2019, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)', 'midnight_green' => 'Éjzöld (Midnight Green)']),
            $this->device('iPhone SE (2nd generation)', 2020, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'product_red' => '(PRODUCT)RED']),
            $this->device('iPhone 12 mini', 2020, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'product_red' => '(PRODUCT)RED', 'green' => 'Zöld (Green)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->device('iPhone 12', 2020, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'product_red' => '(PRODUCT)RED', 'green' => 'Zöld (Green)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->device('iPhone 12 Pro', 2020, ['silver' => 'Ezüst (Silver)', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)', 'pacific_blue' => 'Csendes-óceáni kék (Pacific Blue)']),
            $this->device('iPhone 12 Pro Max', 2020, ['silver' => 'Ezüst (Silver)', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)', 'pacific_blue' => 'Csendes-óceáni kék (Pacific Blue)']),
            $this->device('iPhone 13 mini', 2021, ['product_red' => '(PRODUCT)RED', 'starlight' => 'Csillagfény (Starlight)', 'midnight' => 'Éjfekete (Midnight)', 'blue' => 'Kék (Blue)', 'pink' => 'Rózsaszín (Pink)', 'green' => 'Zöld (Green)']),
            $this->device('iPhone 13', 2021, ['product_red' => '(PRODUCT)RED', 'starlight' => 'Csillagfény (Starlight)', 'midnight' => 'Éjfekete (Midnight)', 'blue' => 'Kék (Blue)', 'pink' => 'Rózsaszín (Pink)', 'green' => 'Zöld (Green)']),
            $this->device('iPhone 13 Pro', 2021, ['silver' => 'Ezüst (Silver)', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)', 'sierra_blue' => 'Hegyi kék (Sierra Blue)', 'alpine_green' => 'Alpesi zöld (Alpine Green)']),
            $this->device('iPhone 13 Pro Max', 2021, ['silver' => 'Ezüst (Silver)', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)', 'sierra_blue' => 'Hegyi kék (Sierra Blue)', 'alpine_green' => 'Alpesi zöld (Alpine Green)']),
            $this->device('iPhone SE (3rd generation)', 2022, ['product_red' => '(PRODUCT)RED', 'starlight' => 'Csillagfény (Starlight)', 'midnight' => 'Éjfekete (Midnight)']),
            $this->device('iPhone 14', 2022, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'product_red' => '(PRODUCT)RED', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)', 'yellow' => 'Sárga (Yellow)']),
            $this->device('iPhone 14 Plus', 2022, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'product_red' => '(PRODUCT)RED', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)', 'yellow' => 'Sárga (Yellow)']),
            $this->device('iPhone 14 Pro', 2022, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)', 'deep_purple' => 'Mélylila (Deep Purple)']),
            $this->device('iPhone 14 Pro Max', 2022, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)', 'deep_purple' => 'Mélylila (Deep Purple)']),
            $this->device('iPhone 15', 2023, ['black' => 'Fekete (Black)', 'blue' => 'Kék (Blue)', 'green' => 'Zöld (Green)', 'yellow' => 'Sárga (Yellow)', 'pink' => 'Rózsaszín (Pink)']),
            $this->device('iPhone 15 Plus', 2023, ['black' => 'Fekete (Black)', 'blue' => 'Kék (Blue)', 'green' => 'Zöld (Green)', 'yellow' => 'Sárga (Yellow)', 'pink' => 'Rózsaszín (Pink)']),
            $this->device('iPhone 15 Pro', 2023, ['black_titanium' => 'Fekete titán (Black Titanium)', 'white_titanium' => 'Fehér titán (White Titanium)', 'blue_titanium' => 'Kék titán (Blue Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)']),
            $this->device('iPhone 15 Pro Max', 2023, ['black_titanium' => 'Fekete titán (Black Titanium)', 'white_titanium' => 'Fehér titán (White Titanium)', 'blue_titanium' => 'Kék titán (Blue Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)']),
            $this->device('iPhone 16', 2024, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'pink' => 'Rózsaszín (Pink)', 'teal' => 'Türkiz (Teal)', 'ultramarine' => 'Ultramarin (Ultramarine)']),
            $this->device('iPhone 16 Plus', 2024, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'pink' => 'Rózsaszín (Pink)', 'teal' => 'Türkiz (Teal)', 'ultramarine' => 'Ultramarin (Ultramarine)']),
            $this->device('iPhone 16 Pro', 2024, ['black_titanium' => 'Fekete titán (Black Titanium)', 'white_titanium' => 'Fehér titán (White Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)', 'desert_titanium' => 'Sivatagi titán (Desert Titanium)']),
            $this->device('iPhone 16 Pro Max', 2024, ['black_titanium' => 'Fekete titán (Black Titanium)', 'white_titanium' => 'Fehér titán (White Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)', 'desert_titanium' => 'Sivatagi titán (Desert Titanium)']),
            $this->device('iPhone 16e', 2025, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)']),
            $this->device('iPhone 17', 2025, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'lavender' => 'Levendula (Lavender)', 'mist_blue' => 'Ködkék (Mist Blue)', 'sage' => 'Zsálya (Sage)']),
            $this->device('iPhone Air', 2025, ['space_black' => 'Asztrofekete (Space Black)', 'cloud_white' => 'Felhőfehér (Cloud White)', 'light_gold' => 'Világos arany (Light Gold)', 'sky_blue' => 'Égszínkék (Sky Blue)']),
            $this->device('iPhone 17 Pro', 2025, ['silver' => 'Ezüst (Silver)', 'cosmic_orange' => 'Kozmikus narancs (Cosmic Orange)', 'deep_blue' => 'Mély kék (Deep Blue)']),
            $this->device('iPhone 17 Pro Max', 2025, ['silver' => 'Ezüst (Silver)', 'cosmic_orange' => 'Kozmikus narancs (Cosmic Orange)', 'deep_blue' => 'Mély kék (Deep Blue)']),
            $this->device('iPhone 17e', 2026, ['black' => 'Fekete (Black)', 'white' => 'Fehér (White)', 'soft_pink' => 'Lágy rózsaszín (Soft Pink)']),
        ];
    }

    /**
     * @param array<string, string> $colors
     * @return array{key: string, name: string, type: string, year: int, colors: array<string, string>}
     */
    private function device(string $name, int $year, array $colors): array
    {
        return $this->deviceData($name, DeviceType::IPHONE, $year, $colors);
    }

    /**
     * @param array<string, string> $colors
     * @return array{key: string, name: string, type: string, year: int, colors: array<string, string>}
     */
    private function deviceData(string $name, string $type, int $year, array $colors): array
    {
        return [
            'key' => $this->slug($name),
            'name' => $name,
            'type' => $type,
            'year' => $year,
            'colors' => $colors,
        ];
    }

    private function slug(string $value): string
    {
        $slug = strtolower(remove_accents($value));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?: $value;

        return trim($slug, '_');
    }
}
