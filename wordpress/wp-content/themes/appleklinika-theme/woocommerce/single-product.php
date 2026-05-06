<?php
/**
 * Appleklinika single product template.
 *
 * This template intentionally avoids WooCommerce's default
 * content-single-product.php output because the custom product layout is
 * rendered by the Appleklinika Inventory plugin on woocommerce_before_single_product.
 */

defined('ABSPATH') || exit;

get_header('shop');

do_action('woocommerce_before_main_content');

while (have_posts()) {
    the_post();

    echo '<div class="appleklinika-single-product" id="wp--skip-link--target">';
    do_action('woocommerce_before_single_product');
    echo '</div>';
}

do_action('woocommerce_after_main_content');

get_footer('shop');
