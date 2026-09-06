<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 4) . '/wp-load.php';

// Existing local media only; all WC_Product objects below remain unsaved.
$ids = array_map('intval', explode(',', getenv('GALLERY_MEDIA_IDS') ?: '521,522,505'));
foreach ($ids as $id) {
    if (get_post_type($id) !== 'attachment') {
        throw new RuntimeException('Provide three existing attachment IDs through GALLERY_MEDIA_IDS.');
    }
}
$reflection = new ReflectionClass(Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay::class);
$display = $reflection->newInstanceWithoutConstructor();
$product = new WC_Product_Simple();
$product->set_name('Gallery rendering fixture');
$product->set_image_id($ids[0]);
$product->set_gallery_image_ids([$ids[0], $ids[1], $ids[2]]);
$images = $reflection->getMethod('productImages')->invoke($display, $product);
$render = static function (array $data) use ($reflection, $display): string {
    ob_start();
    $reflection->getMethod('renderProductGallery')->invoke($display, $data);
    return (string) ob_get_clean();
};
if (in_array('--fixture', $argv, true)) {
    echo $render($images);
    exit(0);
}
$count = 0;
$check = static function (bool $ok, string $message) use (&$count): void {
    ++$count;
    if (!$ok) throw new RuntimeException($message);
};
$check(count($images) === 3, 'Main image is not duplicated among gallery images.');
foreach ($images as $index => $image) {
    $full = wp_get_attachment_image_src($ids[$index], 'full');
    $check($image['full'] === $full[0], 'Full image URL comes from WP.');
    $check($image['width'] === $full[1] && $image['height'] === $full[2], 'Real intrinsic dimensions retained.');
    $check($image['srcset'] !== '' && str_contains($image['html'], 'sizes='), 'Responsive WP stage markup retained.');
    $check($image['thumb'] === wp_get_attachment_image_url($ids[$index], 'medium'), 'Thumbnail uses bounded uncropped medium source.');
    $check($image['alt'] !== '', 'Useful alt text fallback.');
}
$html = $render($images);
$check(substr_count($html, 'data-gallery-index=') === 3, 'One control per distinct image.');
$check(substr_count($html, 'aria-pressed="true"') === 1, 'One accessible selected thumbnail.');
$check(!str_contains($html, '<dialog') && !str_contains($html, 'appleklinika-lightbox'), 'No eagerly rendered or wpautop-sensitive modal markup.');
$check(str_contains($html, 'data-gallery-open href="' . esc_url($images[0]['full']) . '"'), 'Functional full-image link without JavaScript.');
$single = $render([$images[0]]);
$check(str_contains($single, 'aria-label="Termékképek" hidden'), 'Single-image thumbnail navigation hidden on server render.');
$product->set_image_id(0);$product->set_gallery_image_ids([]);
$placeholder = $reflection->getMethod('productImages')->invoke($display, $product);
$check(count($placeholder) === 1 && $placeholder[0]['full'] !== '', 'Missing media has a valid placeholder, not an empty viewer source.');
$check($product->get_id() === 0, 'No product was persisted.');
echo "Product gallery render tests passed: {$count} assertions; no product/media saved.\n";
