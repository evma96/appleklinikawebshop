<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__) . '/src/Interfaces/Frontend/ProductFrontendDisplay.php');
$assertions = [
    str_contains($source, 'appleklinika-product-gallery__stage')
        && str_contains($source, 'appleklinika-buy-panel')
        && str_contains($source, 'appleklinika-product-below-hero'),
    str_contains($source, 'width:min(500px,100%);aspect-ratio:1/1')
        && str_contains($source, 'appleklinika-product-gallery--phone-portrait .appleklinika-product-gallery__stage img{width:auto;height:86%'),
    str_contains($source, 'appleklinika-product-below-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(280px,.84fr);gap:22px;align-items:start}')
        && ! str_contains($source, '.appleklinika-below-panel{display:grid;align-content:start;gap:16px;min-width:0;height:100%'),
];

$messages = [
    'The product template retains the gallery, purchase panel, and lower information structures.',
    'The gallery uses a neutral intrinsic canvas with an explicit portrait-phone treatment.',
    'Lower information cards use natural content height instead of equal-height stretching.',
];

foreach ($assertions as $index => $assertion) {
    if (! $assertion) {
        fwrite(STDERR, "FAIL: {$messages[$index]}\n");
        exit(1);
    }
}

echo 'Inventory product frontend structure tests passed: ' . count($assertions) . " assertions.\n";
