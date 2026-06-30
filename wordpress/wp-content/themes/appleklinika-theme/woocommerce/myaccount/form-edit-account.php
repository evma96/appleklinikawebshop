<?php
/**
 * Apple Klinika edit account form.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_edit_account_form');
?>

<form class="woocommerce-EditAccountForm edit-account ak-account-details-form" action="" method="post" <?php do_action('woocommerce_edit_account_form_tag'); ?>>
    <?php do_action('woocommerce_edit_account_form_start'); ?>

    <section class="ak-account-form-section ak-account-form-section--personal">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Személyes adatok</p>
            <h2>Név és megjelenítés</h2>
        </header>

        <div class="ak-account-form-grid">
            <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                <label for="account_first_name"><?php esc_html_e('First name', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr($user->first_name); ?>" aria-required="true" />
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                <label for="account_last_name"><?php esc_html_e('Last name', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr($user->last_name); ?>" aria-required="true" />
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-form-grid__full">
                <label for="account_display_name"><?php esc_html_e('Display name', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr($user->display_name); ?>" aria-required="true" />
                <span id="account_display_name_description"><em><?php esc_html_e('This will be how your name will be displayed in the account section and in reviews', 'woocommerce'); ?></em></span>
            </p>
        </div>
    </section>

    <section class="ak-account-form-section ak-account-form-section--login">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Bejelentkezési adatok</p>
            <h2>E-mail cím</h2>
        </header>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_email"><?php esc_html_e('Email address', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr($user->user_email); ?>" aria-required="true" />
        </p>

        <?php do_action('woocommerce_edit_account_form_fields'); ?>
    </section>

    <section class="ak-account-form-section ak-account-form-section--password">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Biztonság</p>
            <h2>Jelszó változtatás</h2>
        </header>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_current"><?php esc_html_e('Current password (leave blank to leave unchanged)', 'woocommerce'); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_1"><?php esc_html_e('New password (leave blank to leave unchanged)', 'woocommerce'); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" />
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_2"><?php esc_html_e('Confirm new password', 'woocommerce'); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" />
        </p>
    </section>

    <?php do_action('woocommerce_edit_account_form'); ?>

    <p class="ak-account-form-actions">
        <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
        <button type="submit" class="woocommerce-Button button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" name="save_account_details" value="<?php esc_attr_e('Save changes', 'woocommerce'); ?>">Módosítások mentése</button>
        <input type="hidden" name="action" value="save_account_details" />
    </p>

    <?php do_action('woocommerce_edit_account_form_end'); ?>
</form>

<?php do_action('woocommerce_after_edit_account_form'); ?>
