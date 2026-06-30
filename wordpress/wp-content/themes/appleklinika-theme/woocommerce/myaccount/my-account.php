<?php
/**
 * Apple Klinika My Account shell.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined('ABSPATH') || exit;
?>

<section class="ak-account ak-account-shell" aria-labelledby="ak-account-title">
    <header class="ak-account-hero">
        <p class="ak-account-crumb">Fiókom / <?php echo esc_html(appleklinika_account_breadcrumb_label()); ?></p>
        <h1 id="ak-account-title"><?php echo esc_html(appleklinika_account_page_title()); ?></h1>
    </header>

    <div class="ak-account-layout">
        <?php
        /**
         * My Account navigation.
         *
         * @since 2.6.0
         */
        do_action('woocommerce_account_navigation');
        ?>

        <div class="woocommerce-MyAccount-content ak-account-content ak-account-card">
            <?php
            /**
             * My Account content.
             *
             * @since 2.6.0
             */
            do_action('woocommerce_account_content');
            ?>
        </div>
    </div>
</section>
