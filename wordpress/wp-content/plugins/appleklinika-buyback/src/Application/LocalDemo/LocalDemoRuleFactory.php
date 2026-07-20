<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class LocalDemoRuleFactory
{
    private const NOTE = 'LOCAL DEMO';
    private const BASE_NOTE = 'LOCAL DEMO — 50% OF REPRESENTATIVE STOREFRONT PRICE';

    /** @param list<LocalDemoPricePoint> $matrix @return list<PricingRuleDefinition> */
    public function create(array $matrix): array
    {
        $rules = [];
        foreach ($matrix as $point) {
            $rules[] = $this->base($point);
        }

        $rules[] = $this->mode('in_store_instant', 9500, 'Azonnali személyes felvásárlás');
        $rules[] = $this->mode('fast_online', 9000, 'Gyors felvásárlás');
        $rules[] = $this->mode('higher_offer', 10000, 'Magasabb ajánlat');
        $rules[] = $this->mode('trade_in', 10500, 'Azonnali beszámítás');

        $rules[] = $this->deduction('battery-85-89', 'battery_health', ComparisonOperator::BETWEEN, [85, 89], 5000, '85–89%-os akkumulátor');
        $rules[] = $this->deduction('battery-80-84', 'battery_health', ComparisonOperator::BETWEEN, [80, 84], 10000, '80–84%-os akkumulátor');
        $rules[] = $this->manual('battery-below-80', 'battery_health', ComparisonOperator::LESS_THAN, 80, '80% alatti akkumulátor');

        $rules[] = $this->deduction('face-id-not-working', 'face_id_functional', ComparisonOperator::EQUALS, false, 15000, 'A Face ID nem működik');
        $rules[] = $this->deduction('camera-not-working', 'camera_functional', ComparisonOperator::EQUALS, false, 10000, 'A kamera nem működik');
        $rules[] = $this->deduction('charging-not-working', 'charging_functional', ComparisonOperator::EQUALS, false, 8000, 'A töltés nem működik');
        $rules[] = $this->manual('display-not-working', 'display_functional', ComparisonOperator::EQUALS, false, 'A kijelző nem működik');
        $rules[] = $this->manual('touch-not-working', 'touch_functional', ComparisonOperator::EQUALS, false, 'Az érintés nem működik');
        $rules[] = $this->manual('device-not-powering-on', 'powers_on', ComparisonOperator::EQUALS, false, 'A készülék nem kapcsol be');

        $rules[] = $this->manual('liquid-damage', 'liquid_damage', ComparisonOperator::EQUALS, true, 'Folyadékkár gyanúja');
        $rules[] = $this->manual('motherboard-issue', 'motherboard_issue', ComparisonOperator::EQUALS, true, 'Alaplaphiba gyanúja');
        $rules[] = $this->manual('bent-or-dented', 'bent_or_dented', ComparisonOperator::EQUALS, true, 'Hajlott vagy horpadt készülék');
        $rules[] = $this->manual('non-original-parts', 'replacement_parts', ComparisonOperator::EQUALS, 'non_original', 'Nem eredeti cserealkatrész');
        $rules[] = $this->manual('unknown-parts', 'replacement_parts', ComparisonOperator::EQUALS, 'unknown', 'Ismeretlen cserealkatrész-előzmény');

        $rules[] = $this->deduction('screen-good', 'screen_condition', ComparisonOperator::EQUALS, 'good', 7000, 'Jó állapotú kijelző');
        $rules[] = $this->manual('screen-damaged', 'screen_condition', ComparisonOperator::EQUALS, 'damaged', 'Sérült kijelző');
        $rules[] = $this->deduction('frame-good', 'frame_condition', ComparisonOperator::EQUALS, 'good', 4000, 'Jó állapotú keret');
        $rules[] = $this->manual('frame-damaged', 'frame_condition', ComparisonOperator::EQUALS, 'damaged', 'Sérült keret');
        $rules[] = $this->deduction('back-glass-good', 'back_glass_condition', ComparisonOperator::EQUALS, 'good', 5000, 'Jó állapotú hátlap');
        $rules[] = $this->manual('back-glass-damaged', 'back_glass_condition', ComparisonOperator::EQUALS, 'damaged', 'Sérült hátlap');
        $rules[] = $this->deduction('camera-lens-good', 'camera_lens_condition', ComparisonOperator::EQUALS, 'good', 3000, 'Jó állapotú kameralencse');
        $rules[] = $this->manual('camera-lens-damaged', 'camera_lens_condition', ComparisonOperator::EQUALS, 'damaged', 'Sérült kameralencse');

        return $rules;
    }

    private function base(LocalDemoPricePoint $point): PricingRuleDefinition
    {
        return new PricingRuleDefinition(
            new PricingRuleCode('local-demo-base-' . $point->modelKey . '-' . $point->storageGb),
            new PricingRuleKind(PricingRuleKind::BASE_PRICE),
            'iphone',
            $point->modelKey,
            new StorageCapacity($point->storageGb),
            null,
            null,
            null,
            null,
            new Money($point->basePrice, 'HUF'),
            null,
            new RulePriority(100),
            true,
            $point->modelLabel . ' ' . $point->storageGb . ' GB',
            self::BASE_NOTE
        );
    }

    private function mode(string $mode, int $basisPoints, string $label): PricingRuleDefinition
    {
        return new PricingRuleDefinition(new PricingRuleCode('local-demo-mode-' . $mode), new PricingRuleKind(PricingRuleKind::MODE_ADJUSTMENT), 'iphone', null, null, $mode, null, null, null, null, new BasisPointsMultiplier($basisPoints), new RulePriority(500), true, $label, self::NOTE);
    }

    private function deduction(string $code, string $key, string $operator, mixed $value, int $amount, string $label): PricingRuleDefinition
    {
        return new PricingRuleDefinition(new PricingRuleCode('local-demo-' . $code), new PricingRuleKind(PricingRuleKind::FIXED_DEDUCTION), 'iphone', null, null, null, $key, new ComparisonOperator($operator), $value, new Money($amount, 'HUF'), null, new RulePriority(200), true, $label, self::NOTE);
    }

    private function manual(string $code, string $key, string $operator, mixed $value, string $label): PricingRuleDefinition
    {
        return new PricingRuleDefinition(new PricingRuleCode('local-demo-' . $code), new PricingRuleKind(PricingRuleKind::MANUAL_REVIEW), 'iphone', null, null, null, $key, new ComparisonOperator($operator), $value, null, null, new RulePriority(10), true, $label, self::NOTE);
    }
}
