<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Domain\ProductCondition;

/** Shared by condition normalization and staff intake. Values match the existing product contract. */
final class DeviceFieldOptions
{
    public const SIM = ['dual_esim'=>'Két eSIM', 'physical_esim'=>'Fizikai SIM + eSIM', 'dual_physical'=>'Két fizikai SIM'];
    public const SCREEN = ['13_inch'=>'13 hüvelyk','14_inch'=>'14 hüvelyk','15_inch'=>'15 hüvelyk','16_inch'=>'16 hüvelyk'];
    public const CHIP = ['m1'=>'M1','m1_pro'=>'M1 Pro','m1_max'=>'M1 Max','m2'=>'M2','m2_pro'=>'M2 Pro','m2_max'=>'M2 Max','m3'=>'M3','m3_pro'=>'M3 Pro','m3_max'=>'M3 Max','m4'=>'M4','m4_pro'=>'M4 Pro','m4_max'=>'M4 Max','m5'=>'M5','m5_pro'=>'M5 Pro','m5_max'=>'M5 Max'];
    public const RAM = ['8_gb'=>'8 GB','16_gb'=>'16 GB','18_gb'=>'18 GB','24_gb'=>'24 GB','32_gb'=>'32 GB','36_gb'=>'36 GB','48_gb'=>'48 GB','64_gb'=>'64 GB','96_gb'=>'96 GB','128_gb'=>'128 GB'];
    public const CONNECTIVITY = ['wifi'=>'Wi-Fi','wifi_cellular'=>'Wi-Fi + Cellular','gps'=>'GPS','gps_cellular'=>'GPS + Cellular'];
    public const CASE_SIZE = ['40_mm'=>'40 mm','41_mm'=>'41 mm','42_mm'=>'42 mm','44_mm'=>'44 mm','45_mm'=>'45 mm','46_mm'=>'46 mm','49_mm'=>'49 mm'];
    public const CASE_MATERIAL = ['aluminium'=>'Alumínium','stainless_steel'=>'Rozsdamentes acél','titanium'=>'Titán'];
}
