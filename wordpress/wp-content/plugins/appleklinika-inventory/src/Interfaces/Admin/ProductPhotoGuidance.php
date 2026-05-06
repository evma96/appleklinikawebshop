<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Admin;

final class ProductPhotoGuidance
{
    public function register(): void
    {
        add_action('add_meta_boxes_product', [$this, 'addMetaBox']);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'appleklinika_product_photo_guidance',
            'Appleklinika photo checklist',
            [$this, 'render'],
            'product',
            'side',
            'default'
        );
    }

    public function render(): void
    {
        echo '<p><strong>Recommended 4-photo flow</strong></p>';
        echo '<ol>';
        echo '<li>Main product image: front/display.</li>';
        echo '<li>Gallery image: back housing.</li>';
        echo '<li>Gallery image: sides/camera island.</li>';
        echo '<li>Gallery image: visible wear or accessories.</li>';
        echo '</ol>';
        echo '<p>The WooCommerce product image controls the main storefront image. Gallery images support the product detail view.</p>';
    }
}
