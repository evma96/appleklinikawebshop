<?php

declare(strict_types=1);

/**
 * Local green-screen cutout test for Apple Klinika demo product photos.
 *
 * This script intentionally uses only local GD image processing. It does not
 * call external APIs, does not touch WordPress products, and does not import
 * files into the Media Library.
 */

const TARGET_WIDTH = 1400;
const TARGET_HEIGHT = 1900;

$wpContent = is_dir('/var/www/html/wp-content')
    ? '/var/www/html/wp-content'
    : dirname(__DIR__) . '/wordpress/wp-content';

$sourceDir = getenv('AK_GREEN_SOURCE_DIR') ?: $wpContent . '/uploads/ak-green-source/iphone-13-pro';
$outputDir = getenv('AK_TRANSPARENT_OUTPUT_DIR') ?: $wpContent . '/uploads/ak-transparent-output/iphone-13-pro';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is not available.\n");
    exit(1);
}

if (!is_dir($sourceDir)) {
    fwrite(STDERR, "Source directory missing: {$sourceDir}\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory: {$outputDir}\n");
    exit(1);
}

$sources = array_values(array_filter(
    glob($sourceDir . '/*') ?: [],
    static fn (string $file): bool => is_file($file) && preg_match('/\.(jpe?g|png|webp)$/i', $file) === 1
));

sort($sources, SORT_NATURAL | SORT_FLAG_CASE);

if (count($sources) !== 4) {
    fwrite(STDERR, 'Expected exactly 4 source images, found ' . count($sources) . ".\n");
    foreach ($sources as $file) {
        fwrite(STDERR, ' - ' . basename($file) . "\n");
    }
    exit(1);
}

$outputs = [];

foreach ($sources as $index => $source) {
    $number = $index + 1;
    $output = sprintf('%s/iphone-13-pro-transparent-%02d.png', $outputDir, $number);

    echo sprintf("SOURCE|%02d|%s\n", $number, $source);
    $result = createTransparentCutout($source, $output);
    $outputs[] = [
        'source' => $source,
        'output' => $output,
        'metrics' => $result,
    ];

    echo sprintf(
        "OUTPUT|%02d|%s|%dx%d|opaque_ratio=%.4f|greenish_opaque_ratio=%.5f\n",
        $number,
        $output,
        $result['width'],
        $result['height'],
        $result['opaque_ratio'],
        $result['greenish_opaque_ratio']
    );
}

$contactSheet = $outputDir . '/preview-contact-sheet.png';
createContactSheet($outputs, $contactSheet);
echo "CONTACT_SHEET|{$contactSheet}\n";

/**
 * @return array{width:int,height:int,opaque_ratio:float,greenish_opaque_ratio:float}
 */
function createTransparentCutout(string $sourcePath, string $outputPath): array
{
    $source = loadImage($sourcePath);
    imagepalettetotruecolor($source);
    imagesavealpha($source, true);

    $width = imagesx($source);
    $height = imagesy($source);

    $transparent = imagecreatetruecolor($width, $height);
    imagealphablending($transparent, false);
    imagesavealpha($transparent, true);

    $fullTransparent = colorWithAlpha(0, 0, 0, 255);
    imagefilledrectangle($transparent, 0, 0, $width, $height, $fullTransparent);

    $minX = $width;
    $minY = $height;
    $maxX = 0;
    $maxY = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($source, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            $alpha = greenScreenAlpha($r, $g, $b);

            if ($alpha < 245) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }

            if ($alpha < 240) {
                [$r, $g, $b] = suppressGreenSpill($r, $g, $b, $alpha);
            }

            imagesetpixel($transparent, $x, $y, colorWithAlpha($r, $g, $b, $alpha));
        }
    }

    if ($minX >= $maxX || $minY >= $maxY) {
        $minX = 0;
        $minY = 0;
        $maxX = $width - 1;
        $maxY = $height - 1;
    }

    imagedestroy($source);

    $padding = (int) max(50, round(max($width, $height) * 0.035));
    $minX = max(0, $minX - $padding);
    $minY = max(0, $minY - $padding);
    $maxX = min($width - 1, $maxX + $padding);
    $maxY = min($height - 1, $maxY + $padding);

    $cropW = $maxX - $minX + 1;
    $cropH = $maxY - $minY + 1;

    $crop = imagecreatetruecolor($cropW, $cropH);
    imagealphablending($crop, false);
    imagesavealpha($crop, true);
    imagefilledrectangle($crop, 0, 0, $cropW, $cropH, $fullTransparent);
    imagecopy($crop, $transparent, 0, 0, $minX, $minY, $cropW, $cropH);
    imagedestroy($transparent);

    $canvas = imagecreatetruecolor(TARGET_WIDTH, TARGET_HEIGHT);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefilledrectangle($canvas, 0, 0, TARGET_WIDTH, TARGET_HEIGHT, $fullTransparent);

    $maxFitW = (int) round(TARGET_WIDTH * 0.9);
    $maxFitH = (int) round(TARGET_HEIGHT * 0.9);
    $scale = min($maxFitW / $cropW, $maxFitH / $cropH, 1.0);

    $destW = (int) max(1, round($cropW * $scale));
    $destH = (int) max(1, round($cropH * $scale));
    $destX = (int) round((TARGET_WIDTH - $destW) / 2);
    $destY = (int) round((TARGET_HEIGHT - $destH) / 2);

    imagecopyresampled($canvas, $crop, $destX, $destY, 0, 0, $destW, $destH, $cropW, $cropH);
    imagedestroy($crop);

    imagepng($canvas, $outputPath, 6);
    $metrics = measureOutput($canvas);
    imagedestroy($canvas);

    return $metrics;
}

function greenScreenAlpha(int $r, int $g, int $b): int
{
    $maxOther = max($r, $b);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $saturation = $max > 0 ? ($max - $min) / $max : 0.0;
    $dominance = $g - $maxOther;

    if ($max < 92 && $dominance < 45) {
        return 0;
    }

    if ($g < 55 || ($dominance < 14 && ($g - $r) < 22) || $saturation < 0.12) {
        return 0;
    }

    $dominanceScore = clamp01(($dominance - 14) / 70);
    $saturationScore = clamp01(($saturation - 0.14) / 0.42);
    $brightnessScore = clamp01(($g - 55) / 120);
    $ratioScore = clamp01(($g / max(1, $maxOther) - 1.04) / 0.55);

    $score = max($dominanceScore * 0.55 + $saturationScore * 0.25 + $ratioScore * 0.2, $dominanceScore * $brightnessScore);

    $cyanGreenScore = 0.0;
    if ($g > 86 && $b > 72 && ($g - $r) > 24 && ($b - $r) > 12) {
        $cyanGreenScore = clamp01((($g + $b) / 2 - 96) / 95)
            * clamp01((($g - $r) - 20) / 70)
            * clamp01(($saturation - 0.12) / 0.35);
    }

    $score = max($score, $cyanGreenScore);

    if ($g > 85 && $dominance > 42 && $g > $r * 1.16 && $g > $b * 1.08) {
        $score = max($score, 0.98);
    }

    $score = clamp01($score);

    if ($score >= 0.2) {
        return 255;
    }

    if ($score <= 0.055) {
        return 0;
    }

    return (int) round((($score - 0.055) / 0.145) * 230);
}

/**
 * @return array{0:int,1:int,2:int}
 */
function suppressGreenSpill(int $r, int $g, int $b, int $alpha): array
{
    $maxOther = max($r, $b);
    $dominance = $g - $maxOther;

    if ($dominance <= 8 || $g < 65) {
        return [$r, $g, $b];
    }

    $opacity = 1 - ($alpha / 255);
    $reduction = (int) round(($dominance - 8) * 0.55 * $opacity);
    $g = max(0, $g - $reduction);

    return [$r, $g, $b];
}

function colorWithAlpha(int $r, int $g, int $b, int $alpha255): int
{
    $alpha = (int) round(clamp01($alpha255 / 255) * 127);
    return (($alpha & 0x7F) << 24) | (($r & 0xFF) << 16) | (($g & 0xFF) << 8) | ($b & 0xFF);
}

function clamp01(float $value): float
{
    return max(0.0, min(1.0, $value));
}

function loadImage(string $path): GdImage
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $image = match ($extension) {
        'jpg', 'jpeg' => imagecreatefromjpeg($path),
        'png' => imagecreatefrompng($path),
        'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
        default => false,
    };

    if (!$image instanceof GdImage) {
        throw new RuntimeException("Could not load image: {$path}");
    }

    return $image;
}

/**
 * @return array{width:int,height:int,opaque_ratio:float,greenish_opaque_ratio:float}
 */
function measureOutput(GdImage $image): array
{
    $width = imagesx($image);
    $height = imagesy($image);
    $total = $width * $height;
    $opaque = 0;
    $greenish = 0;

    for ($y = 0; $y < $height; $y += 3) {
        for ($x = 0; $x < $width; $x += 3) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;

            if ($alpha < 95) {
                $opaque++;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($g > 85 && ($g - max($r, $b)) > 28) {
                    $greenish++;
                }
            }
        }
    }

    $sampled = (int) ceil($width / 3) * (int) ceil($height / 3);

    return [
        'width' => $width,
        'height' => $height,
        'opaque_ratio' => $opaque / max(1, $sampled),
        'greenish_opaque_ratio' => $greenish / max(1, $opaque),
    ];
}

/**
 * @param array<int,array{source:string,output:string,metrics:array<string,mixed>}> $outputs
 */
function createContactSheet(array $outputs, string $path): void
{
    $colW = 430;
    $rowH = 360;
    $headerH = 70;
    $width = $colW * 3;
    $height = $headerH + $rowH * count($outputs);

    $sheet = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($sheet, 255, 255, 255);
    $ink = imagecolorallocate($sheet, 20, 24, 32);
    $muted = imagecolorallocate($sheet, 99, 108, 125);
    imagefilledrectangle($sheet, 0, 0, $width, $height, $white);

    imagestring($sheet, 5, 24, 24, 'Apple Klinika local green removal preview', $ink);
    imagestring($sheet, 4, 24, 48, 'Original | Transparent on light | Transparent on checker', $muted);

    foreach ($outputs as $index => $entry) {
        $y = $headerH + $index * $rowH;
        $original = loadImage($entry['source']);
        $cutout = imagecreatefrompng($entry['output']);

        if (!$cutout instanceof GdImage) {
            throw new RuntimeException('Could not load generated cutout: ' . $entry['output']);
        }

        drawCell($sheet, $original, 0, $y, $colW, $rowH, 'source ' . ($index + 1), false);
        drawCell($sheet, $cutout, $colW, $y, $colW, $rowH, 'transparent on white', false);
        drawCell($sheet, $cutout, $colW * 2, $y, $colW, $rowH, 'transparent on checker', true);

        imagedestroy($original);
        imagedestroy($cutout);
    }

    imagepng($sheet, $path, 6);
    imagedestroy($sheet);
}

function drawCell(GdImage $sheet, GdImage $image, int $x, int $y, int $w, int $h, string $label, bool $checker): void
{
    $border = imagecolorallocate($sheet, 229, 231, 235);
    $bg = imagecolorallocate($sheet, 250, 251, 253);
    $ink = imagecolorallocate($sheet, 20, 24, 32);
    imagefilledrectangle($sheet, $x + 12, $y + 12, $x + $w - 12, $y + $h - 12, $bg);

    if ($checker) {
        drawChecker($sheet, $x + 12, $y + 12, $w - 24, $h - 24);
    }

    imagerectangle($sheet, $x + 12, $y + 12, $x + $w - 12, $y + $h - 12, $border);

    $innerX = $x + 28;
    $innerY = $y + 42;
    $innerW = $w - 56;
    $innerH = $h - 70;

    imagealphablending($sheet, true);
    $srcW = imagesx($image);
    $srcH = imagesy($image);
    $scale = min($innerW / $srcW, $innerH / $srcH);
    $dstW = (int) round($srcW * $scale);
    $dstH = (int) round($srcH * $scale);
    $dstX = $innerX + (int) round(($innerW - $dstW) / 2);
    $dstY = $innerY + (int) round(($innerH - $dstH) / 2);
    imagecopyresampled($sheet, $image, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

    imagestring($sheet, 4, $x + 24, $y + 20, $label, $ink);
}

function drawChecker(GdImage $sheet, int $x, int $y, int $w, int $h): void
{
    $a = imagecolorallocate($sheet, 243, 244, 246);
    $b = imagecolorallocate($sheet, 229, 231, 235);
    $size = 18;

    for ($yy = 0; $yy < $h; $yy += $size) {
        for ($xx = 0; $xx < $w; $xx += $size) {
            $color = ((int) floor($xx / $size) + (int) floor($yy / $size)) % 2 === 0 ? $a : $b;
            imagefilledrectangle(
                $sheet,
                $x + $xx,
                $y + $yy,
                min($x + $w, $x + $xx + $size),
                min($y + $h, $y + $yy + $size),
                $color
            );
        }
    }
}
