<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Application\ProductCondition;

use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class SaveProductConditionHandler
{
    public function __construct(
        private readonly WooProductConditionRepository $repository,
        private readonly DeviceCatalogRepository $deviceCatalogRepository
    )
    {
    }

    public function handle(SaveProductConditionCommand $command): void
    {
        $deviceType = $this->choiceValue($command->input, 'device_type', ['iphone', 'ipad', 'macbook', 'apple_watch'], '');
        $deviceModel = $this->textValue($command->input, 'device_model');
        $connectivity = $this->normalizedConnectivity($command->input, $deviceType, $deviceModel);
        $caseSize = $this->normalizedWatchChoice($command->input, $deviceType, $deviceModel, 'case_size', ['40_mm', '41_mm', '42_mm', '44_mm', '45_mm', '46_mm', '49_mm']);
        $caseMaterial = $this->normalizedWatchChoice($command->input, $deviceType, $deviceModel, 'case_material', ['aluminium', 'stainless_steel', 'titanium']);
        $color = $this->normalizedColor($command->input, $deviceType, $deviceModel, $caseMaterial);
        $bodyGrade = $this->gradeValue($command->input, 'body_grade');
        $cameraIslandGrade = $this->gradeValue($command->input, 'camera_island_grade');
        $displayGrade = $this->gradeValue($command->input, 'display_grade');
        $overallGrade = $this->gradeValue($command->input, 'overall_grade');

        $this->repository->save($command->productId, [
            'device_type' => $deviceType,
            'device_model' => $deviceModel,
            'battery_health' => $this->intRangeValue($command->input, 'battery_health', 0, 100),
            'battery_option' => $this->choiceValue($command->input, 'battery_option', ['standard', 'aftermarket_new', 'factory_new'], 'standard'),
            'storage_capacity' => $this->choiceValue($command->input, 'storage_capacity', ['64_gb', '128_gb', '256_gb', '512_gb', '1_tb', '2_tb', '4_tb', '8_tb'], ''),
            'color' => $color,
            'sim_config' => $this->choiceValue($command->input, 'sim_config', ['dual_esim', 'physical_esim', 'dual_physical'], ''),
            'connectivity' => $connectivity,
            'screen_size' => $this->choiceValue($command->input, 'screen_size', ['13_inch', '14_inch', '15_inch', '16_inch'], ''),
            'processor_chip' => $this->choiceValue($command->input, 'processor_chip', ['m1', 'm1_pro', 'm1_max', 'm2', 'm2_pro', 'm2_max', 'm3', 'm3_pro', 'm3_max', 'm4', 'm4_pro', 'm4_max', 'm5', 'm5_pro', 'm5_max'], ''),
            'ram_size' => $this->choiceValue($command->input, 'ram_size', ['8_gb', '16_gb', '18_gb', '24_gb', '32_gb', '36_gb', '48_gb', '64_gb', '96_gb', '128_gb'], ''),
            'cycle_count' => '',
            'case_size' => $caseSize,
            'case_material' => $caseMaterial,
            'strap' => $this->textValue($command->input, 'strap'),
            'warranty_duration' => $this->textValue($command->input, 'warranty_duration'),
            'accessories' => $this->textareaValue($command->input, 'accessories'),
            'short_device_description' => $this->textareaValue($command->input, 'short_device_description'),
            'internal_identifier' => $this->textValue($command->input, 'internal_identifier'),
            'body_grade' => $bodyGrade->value(),
            'camera_island_grade' => $cameraIslandGrade->value(),
            'display_grade' => $displayGrade->value(),
            'overall_grade' => $overallGrade->value(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function stringValue(array $input, string $key): string
    {
        if (! isset($input[$key])) {
            return Grade::B;
        }

        return strtolower(preg_replace('/[^a-z0-9_]/', '', (string) $input[$key]) ?: Grade::B);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function gradeValue(array $input, string $key): Grade
    {
        try {
            return new Grade($this->stringValue($input, $key));
        } catch (\InvalidArgumentException) {
            return new Grade(Grade::B);
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function textValue(array $input, string $key): string
    {
        return isset($input[$key]) ? $this->cleanText((string) $input[$key]) : '';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function textareaValue(array $input, string $key): string
    {
        return isset($input[$key]) ? $this->cleanTextarea((string) $input[$key]) : '';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function intRangeValue(array $input, string $key, int $minimum, int $maximum): string
    {
        if (! isset($input[$key]) || $input[$key] === '') {
            return '';
        }

        $value = (int) $input[$key];

        return (string) max($minimum, min($maximum, $value));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $allowed
     */
    private function choiceValue(array $input, string $key, array $allowed, string $default): string
    {
        $value = $this->textValue($input, $key);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function normalizedConnectivity(array $input, string $deviceType, string $deviceModel): string
    {
        $value = $this->choiceValue($input, 'connectivity', ['wifi', 'wifi_cellular', 'gps', 'gps_cellular'], '');

        if ($deviceType === 'ipad') {
            return in_array($value, ['wifi', 'wifi_cellular'], true) ? $value : '';
        }

        if ($deviceType !== 'apple_watch') {
            return '';
        }

        $allowed = $this->watchAllowedValues($deviceModel, 'connectivity', ['gps', 'gps_cellular']);

        return $this->constrainedValue($value, $allowed);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function normalizedColor(array $input, string $deviceType, string $deviceModel, string $caseMaterial): string
    {
        $value = $this->textValue($input, 'color');

        if ($deviceType !== 'apple_watch') {
            return $value;
        }

        if ($value === '') {
            return '';
        }

        return in_array($value, $this->watchAllowedColors($deviceModel, $caseMaterial), true) ? $value : '';
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $fallback
     */
    private function normalizedWatchChoice(array $input, string $deviceType, string $deviceModel, string $key, array $fallback): string
    {
        if ($deviceType !== 'apple_watch') {
            return '';
        }

        return $this->constrainedValue($this->textValue($input, $key), $this->watchAllowedValues($deviceModel, $key . 's', $fallback));
    }

    /**
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function watchAllowedValues(string $deviceModel, string $key, array $fallback): array
    {
        $options = $this->deviceCatalogRepository->watchOptionsByModel();

        return $options[$deviceModel][$key] ?? $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function watchAllowedColors(string $deviceModel, string $caseMaterial): array
    {
        $options = $this->deviceCatalogRepository->watchOptionsByModel();
        $colorsByMaterial = $options[$deviceModel]['colors_by_material'] ?? [];

        if ($caseMaterial !== '' && isset($colorsByMaterial[$caseMaterial])) {
            return $colorsByMaterial[$caseMaterial];
        }

        return array_values(array_unique(array_merge(...array_values($colorsByMaterial ?: [[]]))));
    }

    /**
     * @param array<int, string> $allowed
     */
    private function constrainedValue(string $value, array $allowed): string
    {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return count($allowed) === 1 ? (string) $allowed[0] : '';
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
    }

    private function cleanTextarea(string $value): string
    {
        $withoutTags = strip_tags($value);

        return trim(preg_replace("/[ \t]+/", ' ', $withoutTags) ?: '');
    }
}
