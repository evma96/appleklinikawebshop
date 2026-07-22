<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

/**
 * Single presentation catalogue for the public condition illustrations.
 *
 * Final assets intentionally do not exist yet. The catalogue keeps their
 * stable WebP contract alongside the existing Apple Klinika demo-SVG fallback
 * so illustration delivery can be completed without another UI rewrite.
 */
final class VisualStateCatalogue
{
    public function __construct(private readonly LocalDemoQuestionnaire $questionnaire)
    {
    }

    /**
     * @return array<string, array{visual_key:string,question_key:string,answer_key:string,question_label:string,answer_label:string,panel:string,view_type:string,expected_path:string,fallback_path:string,alt:string}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->questionnaire->visualStateAnswers() as $state) {
            $visualKey = $state['visual_key'];
            $entries[$visualKey] = [
                'visual_key' => $visualKey,
                'question_key' => $state['question_key'],
                'answer_key' => $state['answer_key'],
                'question_label' => $state['question_label'],
                'answer_label' => $state['answer_label'],
                'panel' => $state['panel'],
                'view_type' => $this->viewType($visualKey),
                'expected_path' => 'assets/images/buyback-states/' . $visualKey . '.webp',
                'fallback_path' => 'assets/images/buyback-states/_demo/' . $this->fallbackName($visualKey) . '.svg',
                'alt' => 'iPhone állapotillusztráció: ' . $state['question_label'] . ' – ' . $state['answer_label'],
            ];
        }

        return $entries;
    }

    /** @return array{visual_key:string,question_key:string,answer_key:string,question_label:string,answer_label:string,panel:string,view_type:string,expected_path:string,fallback_path:string,alt:string} */
    public function fallback(): array
    {
        return [
            'visual_key' => 'device/fallback',
            'question_key' => '',
            'answer_key' => '',
            'question_label' => '',
            'answer_label' => 'Kiválasztott iPhone',
            'panel' => '',
            'view_type' => 'front',
            'expected_path' => 'assets/images/buyback-states/screen/flawless.webp',
            'fallback_path' => 'assets/images/buyback-states/_demo/flawless.svg',
            'alt' => 'iPhone állapotillusztráció',
        ];
    }

    private function viewType(string $visualKey): string
    {
        return match (strtok($visualKey, '/')) {
            'frame' => 'angled-edge',
            'back-glass' => 'rear',
            default => 'front',
        };
    }

    private function fallbackName(string $visualKey): string
    {
        return match (strtok($visualKey, '/')) {
            'frame' => str_ends_with($visualKey, '/damaged') ? 'damaged' : $this->wearTier($visualKey),
            'back-glass' => str_ends_with($visualKey, '/cracked') ? 'damaged' : $this->wearTier($visualKey),
            default => str_ends_with($visualKey, '/cracked') ? 'damaged' : $this->wearTier($visualKey),
        };
    }

    private function wearTier(string $visualKey): string
    {
        return match (true) {
            str_ends_with($visualKey, '/minor-wear') => 'minor-wear',
            str_ends_with($visualKey, '/heavier-wear') => 'heavier-wear',
            str_ends_with($visualKey, '/strongly-worn') => 'strongly-worn',
            default => 'flawless',
        };
    }
}
