<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Domain\DeviceCatalog\DeviceType;

final class DeviceCatalogRepository
{
    private const OPTION_NAME = 'appleklinika_device_catalog';
    private const VERSION_OPTION_NAME = 'appleklinika_device_catalog_version';
    private const CURRENT_VERSION = 7;

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
            $catalog = $this->mergeMissingDefaultDevices($catalog, $this->defaultCatalog());
            $catalog = $this->removeDeprecatedDefaultDevices($catalog);
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
            $this->typedDevice('iPad (7th generation)', DeviceType::IPAD, 2019, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)']),
            $this->typedDevice('iPad Air (3rd generation)', DeviceType::IPAD, 2019, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)']),
            $this->typedDevice('iPad mini (5th generation)', DeviceType::IPAD, 2019, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)']),
            $this->typedDevice('iPad Pro 11-inch (2nd generation)', DeviceType::IPAD, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad Pro 12.9-inch (4th generation)', DeviceType::IPAD, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad (8th generation)', DeviceType::IPAD, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'gold' => 'Arany (Gold)']),
            $this->typedDevice('iPad Air (4th generation)', DeviceType::IPAD, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)', 'rose_gold' => 'Rozéarany (Rose Gold)', 'green' => 'Zöld (Green)', 'sky_blue' => 'Égszínkék (Sky Blue)']),
            $this->typedDevice('iPad Pro 11-inch (3rd generation)', DeviceType::IPAD, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad Pro 12.9-inch (5th generation)', DeviceType::IPAD, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad (9th generation)', DeviceType::IPAD, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad mini (6th generation)', DeviceType::IPAD, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'pink' => 'Rózsaszín (Pink)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad Air (5th generation)', DeviceType::IPAD, 2022, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'pink' => 'Rózsaszín (Pink)', 'purple' => 'Lila (Purple)', 'blue' => 'Kék (Blue)']),
            $this->typedDevice('iPad Pro 11-inch M2', DeviceType::IPAD, 2022, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad Pro 12.9-inch M2', DeviceType::IPAD, 2022, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('iPad (10th generation)', DeviceType::IPAD, 2022, ['silver' => 'Ezüst (Silver)', 'blue' => 'Kék (Blue)', 'pink' => 'Rózsaszín (Pink)', 'yellow' => 'Sárga (Yellow)']),
            $this->typedDevice('iPad Pro 11-inch M4', DeviceType::IPAD, 2024, ['silver' => 'Ezüst (Silver)', 'space_black' => 'Asztrofekete (Space Black)']),
            $this->typedDevice('iPad Pro 13-inch M4', DeviceType::IPAD, 2024, ['silver' => 'Ezüst (Silver)', 'space_black' => 'Asztrofekete (Space Black)']),
            $this->typedDevice('iPad Air 11-inch M2', DeviceType::IPAD, 2024, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad Air 13-inch M2', DeviceType::IPAD, 2024, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad mini A17 Pro', DeviceType::IPAD, 2024, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad A16', DeviceType::IPAD, 2025, ['silver' => 'Ezüst (Silver)', 'blue' => 'Kék (Blue)', 'pink' => 'Rózsaszín (Pink)', 'yellow' => 'Sárga (Yellow)']),
            $this->typedDevice('iPad Air 11-inch M3', DeviceType::IPAD, 2025, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad Air 13-inch M3', DeviceType::IPAD, 2025, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad Pro 11-inch M5', DeviceType::IPAD, 2025, ['silver' => 'Ezüst (Silver)', 'space_black' => 'Asztrofekete (Space Black)']),
            $this->typedDevice('iPad Pro 13-inch M5', DeviceType::IPAD, 2025, ['silver' => 'Ezüst (Silver)', 'space_black' => 'Asztrofekete (Space Black)']),
            $this->typedDevice('iPad Air 11-inch M4', DeviceType::IPAD, 2026, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('iPad Air 13-inch M4', DeviceType::IPAD, 2026, ['space_gray' => 'Asztroszürke (Space Gray)', 'starlight' => 'Csillagfény (Starlight)', 'blue' => 'Kék (Blue)', 'purple' => 'Lila (Purple)']),
            $this->typedDevice('MacBook Air M1', DeviceType::MAC, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'gold' => 'Arany (Gold)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 13-inch M1', DeviceType::MAC, 2020, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M1 Pro', DeviceType::MAC, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M1 Max', DeviceType::MAC, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M1 Pro', DeviceType::MAC, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M1 Max', DeviceType::MAC, 2021, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Air 13-inch M2', DeviceType::MAC, 2022, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 13-inch M2', DeviceType::MAC, 2022, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M2 Pro', DeviceType::MAC, 2023, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M2 Max', DeviceType::MAC, 2023, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M2 Pro', DeviceType::MAC, 2023, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M2 Max', DeviceType::MAC, 2023, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Air 15-inch M2', DeviceType::MAC, 2023, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M3', DeviceType::MAC, 2023, ['space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M3 Pro', DeviceType::MAC, 2023, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M3 Max', DeviceType::MAC, 2023, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M3 Pro', DeviceType::MAC, 2023, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M3 Max', DeviceType::MAC, 2023, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Air 13-inch M3', DeviceType::MAC, 2024, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Air 15-inch M3', DeviceType::MAC, 2024, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'space_gray' => 'Asztroszürke (Space Gray)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M4', DeviceType::MAC, 2024, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M4 Pro', DeviceType::MAC, 2024, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M4 Max', DeviceType::MAC, 2024, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M4 Pro', DeviceType::MAC, 2024, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M4 Max', DeviceType::MAC, 2024, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Air 13-inch M4', DeviceType::MAC, 2025, ['sky_blue' => 'Égszínkék (Sky Blue)', 'silver' => 'Ezüst (Silver)', 'starlight' => 'Csillagfény (Starlight)', 'midnight' => 'Éjfekete (Midnight)']),
            $this->typedDevice('MacBook Air 15-inch M4', DeviceType::MAC, 2025, ['sky_blue' => 'Égszínkék (Sky Blue)', 'silver' => 'Ezüst (Silver)', 'starlight' => 'Csillagfény (Starlight)', 'midnight' => 'Éjfekete (Midnight)']),
            $this->typedDevice('MacBook Pro 14-inch M5', DeviceType::MAC, 2025, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M5 Pro', DeviceType::MAC, 2026, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 14-inch M5 Max', DeviceType::MAC, 2026, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M5 Pro', DeviceType::MAC, 2026, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('MacBook Pro 16-inch M5 Max', DeviceType::MAC, 2026, ['space_black' => 'Asztrofekete (Space Black)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('Apple Watch SE (2nd generation)', DeviceType::WATCH, 2022, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'silver' => 'Ezüst (Silver)']),
            $this->typedDevice('Apple Watch Series 8', DeviceType::WATCH, 2022, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'silver' => 'Ezüst (Silver)', 'product_red' => '(PRODUCT)RED', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)']),
            $this->typedDevice('Apple Watch Ultra', DeviceType::WATCH, 2022, ['natural_titanium' => 'Natúr titán (Natural Titanium)']),
            $this->typedDevice('Apple Watch Series 9', DeviceType::WATCH, 2023, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)', 'silver' => 'Ezüst (Silver)', 'pink' => 'Rózsaszín (Pink)', 'product_red' => '(PRODUCT)RED', 'graphite' => 'Grafit (Graphite)', 'gold' => 'Arany (Gold)', 'space_black' => 'Asztrofekete (Space Black)']),
            $this->typedDevice('Apple Watch Ultra 2', DeviceType::WATCH, 2023, ['natural_titanium' => 'Natúr titán (Natural Titanium)', 'black_titanium' => 'Fekete titán (Black Titanium)']),
            $this->typedDevice('Apple Watch Series 10', DeviceType::WATCH, 2024, ['jet_black' => 'Koromfekete (Jet Black)', 'rose_gold' => 'Rozéarany (Rose Gold)', 'silver' => 'Ezüst (Silver)', 'slate_titanium' => 'Palatitán (Slate Titanium)', 'gold_titanium' => 'Arany titán (Gold Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)']),
            $this->typedDevice('Apple Watch SE (3rd generation)', DeviceType::WATCH, 2025, ['midnight' => 'Éjfekete (Midnight)', 'starlight' => 'Csillagfény (Starlight)']),
            $this->typedDevice('Apple Watch Series 11', DeviceType::WATCH, 2025, ['jet_black' => 'Koromfekete (Jet Black)', 'rose_gold' => 'Rozéarany (Rose Gold)', 'silver' => 'Ezüst (Silver)', 'space_gray' => 'Asztroszürke (Space Gray)', 'slate_titanium' => 'Palatitán (Slate Titanium)', 'gold_titanium' => 'Arany titán (Gold Titanium)', 'natural_titanium' => 'Natúr titán (Natural Titanium)']),
            $this->typedDevice('Apple Watch Ultra 3', DeviceType::WATCH, 2025, ['natural_titanium' => 'Natúr titán (Natural Titanium)', 'black_titanium' => 'Fekete titán (Black Titanium)']),
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
    private function typedDevice(string $name, string $type, int $year, array $colors): array
    {
        return $this->deviceData($name, $type, $year, $colors);
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

    /**
     * @param array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}> $catalog
     * @param array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}> $defaults
     * @return array<int, array{key: string, name: string, type: string, year: int, colors: array<string, string>}>
     */
    private function mergeMissingDefaultDevices(array $catalog, array $defaults): array
    {
        $existingKeys = [];
        $defaultByKey = [];

        foreach ($defaults as $device) {
            if (isset($device['key'])) {
                $defaultByKey[(string) $device['key']] = $device;
            }
        }

        foreach ($catalog as $index => $device) {
            if (isset($device['key'])) {
                $existingKeys[(string) $device['key']] = true;
            }

            $defaultDevice = $defaultByKey[(string) ($device['key'] ?? '')] ?? null;
            if ($defaultDevice === null) {
                continue;
            }

            $catalog[$index]['colors'] = array_merge(
                $defaultDevice['colors'],
                is_array($device['colors'] ?? null) ? $device['colors'] : []
            );
        }

        foreach ($defaults as $device) {
            if (isset($existingKeys[$device['key']])) {
                continue;
            }

            $catalog[] = $device;
            $existingKeys[$device['key']] = true;
        }

        return array_values($catalog);
    }

    /**
     * @param array<int, array{key?: string, name?: string, type?: string, year?: int, colors?: array<string, string>}> $catalog
     * @return array<int, array{key?: string, name?: string, type?: string, year?: int, colors?: array<string, string>}>
     */
    private function removeDeprecatedDefaultDevices(array $catalog): array
    {
        $deprecatedKeys = [
            'ipad_air_5',
            'ipad_mini_6',
            'macbook_air_m2',
            'macbook_pro_16_inch_m3',
        ];

        return array_values(array_filter($catalog, static function (array $device) use ($deprecatedKeys): bool {
            return ! in_array((string) ($device['key'] ?? ''), $deprecatedKeys, true);
        }));
    }

    /**
     * @return array<string, array{case_sizes: array<int, string>, case_materials: array<int, string>, connectivity: array<int, string>, colors_by_material: array<string, array<int, string>>}>
     */
    public function watchOptionsByModel(): array
    {
        return [
            'apple_watch_se_2nd_generation' => [
                'case_sizes' => ['40_mm', '44_mm'],
                'case_materials' => ['aluminium'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['midnight', 'starlight', 'silver'],
                ],
            ],
            'apple_watch_series_8' => [
                'case_sizes' => ['41_mm', '45_mm'],
                'case_materials' => ['aluminium', 'stainless_steel'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['midnight', 'starlight', 'silver', 'product_red'],
                    'stainless_steel' => ['silver', 'graphite', 'gold'],
                ],
            ],
            'apple_watch_ultra' => [
                'case_sizes' => ['49_mm'],
                'case_materials' => ['titanium'],
                'connectivity' => ['gps_cellular'],
                'colors_by_material' => [
                    'titanium' => ['natural_titanium'],
                ],
            ],
            'apple_watch_series_9' => [
                'case_sizes' => ['41_mm', '45_mm'],
                'case_materials' => ['aluminium', 'stainless_steel'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['midnight', 'starlight', 'silver', 'pink', 'product_red'],
                    'stainless_steel' => ['silver', 'gold', 'graphite', 'space_black'],
                ],
            ],
            'apple_watch_ultra_2' => [
                'case_sizes' => ['49_mm'],
                'case_materials' => ['titanium'],
                'connectivity' => ['gps_cellular'],
                'colors_by_material' => [
                    'titanium' => ['natural_titanium', 'black_titanium'],
                ],
            ],
            'apple_watch_series_10' => [
                'case_sizes' => ['42_mm', '46_mm'],
                'case_materials' => ['aluminium', 'titanium'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['jet_black', 'rose_gold', 'silver'],
                    'titanium' => ['slate_titanium', 'gold_titanium', 'natural_titanium'],
                ],
            ],
            'apple_watch_se_3rd_generation' => [
                'case_sizes' => ['40_mm', '44_mm'],
                'case_materials' => ['aluminium'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['midnight', 'starlight'],
                ],
            ],
            'apple_watch_series_11' => [
                'case_sizes' => ['42_mm', '46_mm'],
                'case_materials' => ['aluminium', 'titanium'],
                'connectivity' => ['gps', 'gps_cellular'],
                'colors_by_material' => [
                    'aluminium' => ['jet_black', 'rose_gold', 'silver', 'space_gray'],
                    'titanium' => ['slate_titanium', 'gold_titanium', 'natural_titanium'],
                ],
            ],
            'apple_watch_ultra_3' => [
                'case_sizes' => ['49_mm'],
                'case_materials' => ['titanium'],
                'connectivity' => ['gps_cellular'],
                'colors_by_material' => [
                    'titanium' => ['natural_titanium', 'black_titanium'],
                ],
            ],
        ];
    }

    private function slug(string $value): string
    {
        $slug = strtolower(remove_accents($value));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?: $value;

        return trim($slug, '_');
    }
}
