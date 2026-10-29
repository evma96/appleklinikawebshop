<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Domain\ModelSpecification;

/** Exact known aliases only. Never infer a model from a product title or nearby generation. */
final class ModelIdentity
{
    public static function canonical(string $key): string
    {
        $modelKey = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
        $aliases = [
            'apple_watch_se_2nd_generation' => 'apple_watch_se_2',
            'apple_watch_se2' => 'apple_watch_se_2',
            'apple_watch_se_2nd_gen' => 'apple_watch_se_2',
            'apple_watch_se_3rd_generation' => 'apple_watch_se_3',
            'ipad_9th_generation' => 'ipad_9',
            'ipad_10th_generation' => 'ipad_10',
            'ipad_mini_6th_generation' => 'ipad_mini_6',
            'ipad_air_4th_generation' => 'ipad_air_4',
            'ipad_air_5th_generation' => 'ipad_air_5',
            'ipad_air_11_inch_m2' => 'ipad_air_11_m2',
            'ipad_air_13_inch_m2' => 'ipad_air_13_m2',
            'ipad_air_11_inch_m3' => 'ipad_air_11_m3',
            'ipad_air_13_inch_m3' => 'ipad_air_13_m3',
            'ipad_air_11_inch_m4' => 'ipad_air_11_m4',
            'ipad_air_13_inch_m4' => 'ipad_air_13_m4',
            'ipad_pro_11_inch_3rd_generation' => 'ipad_pro_11_3rd_generation',
            'ipad_pro_12_9_inch_5th_generation' => 'ipad_pro_12_9_5th_generation',
            'ipad_pro_11_inch_m2' => 'ipad_pro_11_m2',
            'ipad_pro_12_9_inch_m2' => 'ipad_pro_12_9_m2',
            'ipad_pro_11_inch_m4' => 'ipad_pro_11_m4',
            'ipad_pro_13_inch_m4' => 'ipad_pro_13_m4',
            'ipad_pro_11_inch_m5' => 'ipad_pro_11_m5',
            'ipad_pro_13_inch_m5' => 'ipad_pro_13_m5',
            'macbook_air_13_inch_m2' => 'macbook_air_m2',
            'macbook_air_15_inch_m2' => 'macbook_air_15_m2',
            'macbook_air_13_inch_m3' => 'macbook_air_13_m3',
            'macbook_air_15_inch_m3' => 'macbook_air_15_m3',
            'macbook_air_13_inch_m4' => 'macbook_air_13_m4',
            'macbook_air_15_inch_m4' => 'macbook_air_15_m4',
            'macbook_pro_13_inch_m2' => 'macbook_pro_13_m2',
            'macbook_pro_14_inch_m2_pro' => 'macbook_pro_14_m2_pro',
            'macbook_pro_16_inch_m2_pro' => 'macbook_pro_16_m2_pro',
            'macbook_pro_14_inch_m3' => 'macbook_pro_14_m3',
            'macbook_pro_14_inch_m3_pro' => 'macbook_pro_14_m3_pro',
            'macbook_pro_14_inch_m3_max' => 'macbook_pro_14_m3_max',
            'macbook_pro_16_inch_m3' => 'macbook_pro_16_m3',
            'macbook_pro_16_inch_m3_pro' => 'macbook_pro_16_m3_pro',
            'macbook_pro_16_inch_m3_max' => 'macbook_pro_16_m3_max',
            'macbook_pro_14_inch_m4' => 'macbook_pro_14_m4',
            'macbook_pro_14_inch_m4_pro' => 'macbook_pro_14_m4_pro',
            'macbook_pro_16_inch_m4_pro' => 'macbook_pro_16_m4_pro',
            'macbook_pro_16_inch_m4_max' => 'macbook_pro_16_m4_max',
            'macbook_pro_14_inch_m5' => 'macbook_pro_14_m5',
            'macbook_pro_14_inch_m5_pro' => 'macbook_pro_14_m5_pro',
            'macbook_pro_16_inch_m5_pro' => 'macbook_pro_16_m5_pro',
        ];
        return $aliases[$modelKey] ?? $modelKey;
    }
}
