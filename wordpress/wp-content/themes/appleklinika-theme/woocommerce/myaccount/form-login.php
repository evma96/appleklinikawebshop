<?php
/**
 * Branded My Account login/register entry.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if (! defined('ABSPATH')) {
    exit;
}

$registrationEnabled = 'yes' === get_option('woocommerce_enable_myaccount_registration');

do_action('woocommerce_before_customer_login_form');
?>

<section class="ak-account-auth<?php echo $registrationEnabled ? ' ak-account-auth--with-register' : ''; ?>" aria-labelledby="ak-account-auth-title">
    <div class="ak-account-auth__card ak-account-auth__card--login">
        <p class="ak-account-auth__eyebrow">Apple Klinika fiók</p>
        <h1 id="ak-account-auth-title">Belépés a fiókomba</h1>
        <p class="ak-account-auth__lead">Jelentkezz be, hogy elérd a rendeléseidet, kedvelt termékeidet és fiókadataidat.</p>

        <form class="woocommerce-form woocommerce-form-login login ak-account-auth__form" method="post" novalidate>
            <?php do_action('woocommerce_login_form_start'); ?>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-auth__field">
                <label for="username">
                    E-mail cím vagy felhasználónév&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
                </label>
                <input
                    type="text"
                    class="woocommerce-Input woocommerce-Input--text input-text"
                    name="username"
                    id="username"
                    autocomplete="username"
                    value="<?php echo (! empty($_POST['username']) && is_string($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>"
                    required
                    aria-required="true"
                />
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-auth__field">
                <label for="password">
                    Jelszó&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
                </label>
                <input
                    class="woocommerce-Input woocommerce-Input--text input-text"
                    type="password"
                    name="password"
                    id="password"
                    autocomplete="current-password"
                    required
                    aria-required="true"
                />
            </p>

            <?php do_action('woocommerce_login_form'); ?>

            <div class="ak-account-auth__meta">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme ak-account-auth__remember">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                    <span>Emlékezz rám</span>
                </label>
                <a class="ak-account-auth__forgot" href="<?php echo esc_url(wp_lostpassword_url()); ?>">Elfelejtetted a jelszavad?</a>
            </div>

            <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>

            <button type="submit" class="woocommerce-button button woocommerce-form-login__submit ak-account-auth__submit<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" name="login" value="<?php esc_attr_e('Log in', 'woocommerce'); ?>">
                Belépés
            </button>

            <?php do_action('woocommerce_login_form_end'); ?>
        </form>
    </div>

    <?php if ($registrationEnabled) : ?>
        <div class="ak-account-auth__card ak-account-auth__card--register">
            <p class="ak-account-auth__eyebrow">Új vásárló vagy?</p>
            <h2>Fiók létrehozása</h2>
            <p class="ak-account-auth__lead">Hozz létre fiókot, hogy gyorsabban elérd a rendeléseidet és kedvenceidet.</p>

            <form method="post" class="woocommerce-form woocommerce-form-register register ak-account-auth__form" <?php do_action('woocommerce_register_form_tag'); ?>>
                <?php do_action('woocommerce_register_form_start'); ?>

                <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-auth__field">
                        <label for="reg_username">
                            Felhasználónév&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
                        </label>
                        <input
                            type="text"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="username"
                            id="reg_username"
                            autocomplete="username"
                            value="<?php echo (! empty($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>"
                            required
                            aria-required="true"
                        />
                    </p>
                <?php endif; ?>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-auth__field">
                    <label for="reg_email">
                        E-mail cím&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
                    </label>
                    <input
                        type="email"
                        class="woocommerce-Input woocommerce-Input--text input-text"
                        name="email"
                        id="reg_email"
                        autocomplete="email"
                        value="<?php echo (! empty($_POST['email'])) ? esc_attr(wp_unslash($_POST['email'])) : ''; ?>"
                        required
                        aria-required="true"
                    />
                </p>

                <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide ak-account-auth__field">
                        <label for="reg_password">
                            Jelszó&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
                        </label>
                        <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
                    </p>
                <?php else : ?>
                    <p class="ak-account-auth__generated-password">A jelszó beállításához szükséges linket e-mailben küldjük el.</p>
                <?php endif; ?>

                <?php do_action('woocommerce_register_form'); ?>

                <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>

                <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit ak-account-auth__submit<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>">
                    Regisztráció
                </button>

                <?php do_action('woocommerce_register_form_end'); ?>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php do_action('woocommerce_after_customer_login_form'); ?>
