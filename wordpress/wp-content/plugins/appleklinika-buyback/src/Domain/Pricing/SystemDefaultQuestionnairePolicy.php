<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

/**
 * Central inherited outcomes for commercial questionnaire answers. These
 * rules are calculated in memory and are never written to an owner's book.
 */
final class SystemDefaultQuestionnairePolicy
{
    public const CODE_PREFIX = 'system-questionnaire-default-';

    /** @return list<array{question_key:string,answer_key:string,condition_key:string,comparison_value:int|bool|string,default_action:string,reason:?string}> */
    public function entries(): array
    {
        return [
            $this->entry('network_status', 'locked', 'network_unlocked', false, PricingRuleKind::HARD_REJECT, 'A hálózatfüggő készülék jelenleg nem vásárolható fel automatikusan.'),
            $this->entry('liquid_exposure', 'yes_unknown', 'liquid_damage', true, PricingRuleKind::MANUAL_REVIEW, 'Lehetséges folyadékérintkezés'),
            $this->entry('display_defects', 'touch', 'touch_functional', false, PricingRuleKind::NO_CHANGE, null),
            $this->entry('display_defects', 'yellowing', 'display_yellowing', true, PricingRuleKind::MANUAL_REVIEW, 'Kijelző elszíneződés vagy sárgulás'),
            $this->entry('display_defects', 'deformed', 'display_deformed', true, PricingRuleKind::MANUAL_REVIEW, 'Kijelző deformáció'),
            $this->entry('display_defects', 'pixels', 'display_dead_pixels', true, PricingRuleKind::MANUAL_REVIEW, 'Kijelző pixelhiba'),
            $this->entry('display_defects', 'image_brightness', 'display_image_brightness_functional', false, PricingRuleKind::MANUAL_REVIEW, 'Kijelző kép- vagy fényerőhiba'),
            $this->entry('service_history', 'original_repair', 'replacement_parts', 'original_repair', PricingRuleKind::NO_CHANGE, null),
            $this->entry('service_history', 'used_original', 'replacement_parts', 'used_original', PricingRuleKind::MANUAL_REVIEW, 'Használt eredeti alkatrész'),
            $this->entry('service_history', 'unknown', 'replacement_parts', 'unknown', PricingRuleKind::MANUAL_REVIEW, 'Nem igazolt alkatrész- vagy szervizelőzmény'),
            $this->entry('service_history', 'repair_incomplete', 'replacement_parts', 'repair_incomplete', PricingRuleKind::MANUAL_REVIEW, 'Befejezetlen javítási vagy szervizüzenet'),
            $this->entry('service_history', 'non_original', 'replacement_parts', 'non_original', PricingRuleKind::MANUAL_REVIEW, 'Nem eredeti alkatrész'),
            $this->entry('service_history', 'unsure', 'replacement_parts', 'unsure', PricingRuleKind::MANUAL_REVIEW, 'Bizonytalan szervizelőzmény'),
            $this->entry('other_defects', 'audio', 'audio_functional', false, PricingRuleKind::MANUAL_REVIEW, 'Hanghiba'),
            $this->entry('other_defects', 'front_camera', 'front_camera_functional', false, PricingRuleKind::NO_CHANGE, null),
            $this->entry('other_defects', 'rear_camera', 'rear_camera_functional', false, PricingRuleKind::NO_CHANGE, null),
            $this->entry('other_defects', 'face_id', 'face_id_functional', false, PricingRuleKind::NO_CHANGE, null),
            $this->entry('other_defects', 'camera_lens', 'camera_lens_condition', 'damaged', PricingRuleKind::NO_CHANGE, null),
        ];
    }

    /** @return array{question_key:string,answer_key:string,condition_key:string,comparison_value:int|bool|string,default_action:string,reason:?string}|null */
    public function entryFor(string $questionKey, string $answerKey): ?array
    {
        foreach ($this->entries() as $entry) {
            if ($entry['question_key'] === $questionKey && $entry['answer_key'] === $answerKey) {
                return $entry;
            }
        }
        return null;
    }

    /** @return list<PricingRule> */
    public function inheritedRules(PriceBookId $bookId): array
    {
        $at = new \DateTimeImmutable('@0');
        $rules = [];
        foreach ($this->entries() as $entry) {
            if ($entry['default_action'] === PricingRuleKind::NO_CHANGE) {
                continue;
            }
            $rules[] = PricingRule::create($bookId, new PricingRuleDefinition(
                new PricingRuleCode(self::ruleCode($entry['question_key'], $entry['answer_key'])),
                new PricingRuleKind($entry['default_action']),
                'iphone', null, null, null,
                $entry['condition_key'], new ComparisonOperator(ComparisonOperator::EQUALS), $entry['comparison_value'],
                null, null, new RulePriority(9000), true, $entry['reason'], 'System default questionnaire policy'
            ), $at);
        }
        return $rules;
    }

    public static function isInheritedRule(PricingRule $rule): bool
    {
        return str_starts_with($rule->definition()->code->code(), self::CODE_PREFIX);
    }

    public static function ruleCode(string $questionKey, string $answerKey): string
    {
        return self::CODE_PREFIX . substr(hash('sha256', $questionKey . "\0" . $answerKey), 0, 16);
    }

    /** @return array{question_key:string,answer_key:string,condition_key:string,comparison_value:int|bool|string,default_action:string,reason:?string} */
    private function entry(string $questionKey, string $answerKey, string $conditionKey, int|bool|string $comparisonValue, string $defaultAction, ?string $reason): array
    {
        return [
            'question_key' => $questionKey,
            'answer_key' => $answerKey,
            'condition_key' => $conditionKey,
            'comparison_value' => $comparisonValue,
            'default_action' => $defaultAction,
            'reason' => $reason,
        ];
    }
}
