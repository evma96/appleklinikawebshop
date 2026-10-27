<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__) . '/src/Interfaces/Frontend/ProductFrontendDisplay.php');
$galleryStyles = (string) file_get_contents(dirname(__DIR__) . '/assets/product-gallery.css');
$assertions = [
    str_contains($source, 'appleklinika-product-gallery__stage')
        && str_contains($source, 'appleklinika-buy-panel')
        && str_contains($source, 'appleklinika-product-below-hero'),
    str_contains($galleryStyles, 'width: min(500px, 100%); aspect-ratio: 1')
        && str_contains($galleryStyles, 'object-fit: contain'),
    str_contains($source, 'appleklinika-product-column--configuration')
        && str_contains($source, 'appleklinika-product-column--purchase')
        && str_contains($source, 'appleklinika-product-column{display:contents}')
        && ! str_contains($source, 'renderBatterySelector('),
];

$messages = [
    'The product template retains the gallery, purchase panel, and lower information structures.',
    'The gallery uses a stable neutral canvas with non-distorting contain presentation.',
    'Desktop configuration and purchase columns retain single components and release their wrappers on mobile without a battery choice.',
];

foreach ($assertions as $index => $assertion) {
    if (! $assertion) {
        fwrite(STDERR, "FAIL: {$messages[$index]}\n");
        exit(1);
    }
}

echo 'Inventory product frontend structure tests passed: ' . count($assertions) . " assertions.\n";
