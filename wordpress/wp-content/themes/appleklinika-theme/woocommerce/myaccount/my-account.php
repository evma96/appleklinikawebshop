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

<section class="ak-account ak-account-shell" aria-label="Fiókom">
    <div class="ak-account-layout">
        <?php
        /**
         * My Account navigation.
         *
         * @since 2.6.0
         */
        do_action('woocommerce_account_navigation');
        ?>

        <main class="woocommerce-MyAccount-content ak-account-content ak-account-card" aria-label="<?php echo esc_attr(appleklinika_account_page_title()); ?>">
            <p class="ak-account-crumb">Fiókom / <?php echo esc_html(appleklinika_account_breadcrumb_label()); ?></p>
            <?php
            /**
             * My Account content.
             *
             * @since 2.6.0
             */
            do_action('woocommerce_account_content');
            ?>
        </main>
    </div>
</section>
