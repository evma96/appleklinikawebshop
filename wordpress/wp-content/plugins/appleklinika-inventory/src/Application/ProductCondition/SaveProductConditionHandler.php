<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Application\ProductCondition;

use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class SaveProductConditionHandler
{
    public function __construct(private readonly WooProductConditionRepository $repository)
    {
    }

    public function handle(SaveProductConditionCommand $command): void
    {
        $bodyGrade = $this->gradeValue($command->input, 'body_grade');
        $cameraIslandGrade = $this->gradeValue($command->input, 'camera_island_grade');
        $displayGrade = $this->gradeValue($command->input, 'display_grade');
        $overallGrade = $this->gradeValue($command->input, 'overall_grade');

        $this->repository->save($command->productId, [
            'device_model' => $this->textValue($command->input, 'device_model'),
            'battery_health' => $this->intRangeValue($command->input, 'battery_health', 0, 100),
            'battery_option' => $this->choiceValue($command->input, 'battery_option', ['standard', 'aftermarket_new', 'factory_new'], 'standard'),
            'storage_capacity' => $this->textValue($command->input, 'storage_capacity'),
            'color' => $this->textValue($command->input, 'color'),
            'sim_config' => $this->choiceValue($command->input, 'sim_config', ['dual_esim', 'physical_esim', 'dual_physical'], ''),
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
