<?php
/**
 * Apple Klinika edit account form.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined('ABSPATH') || exit;

$userId = $user instanceof WP_User ? (int) $user->ID : get_current_user_id();
$meta = static function (string $key, string $default = '') use ($userId): string {
    return function_exists('appleklinika_account_user_meta')
        ? appleklinika_account_user_meta($userId, $key, $default)
        : (string) get_user_meta($userId, $key, true);
};
$accountPhone = $meta('ak_account_phone', $meta('billing_phone', $meta('shipping_phone')));

$renderField = static function (array $args): void {
    $id = (string) ($args['id'] ?? '');
    $name = (string) ($args['name'] ?? $id);
    $label = (string) ($args['label'] ?? '');
    $value = (string) ($args['value'] ?? '');
    $type = (string) ($args['type'] ?? 'text');
    $autocomplete = (string) ($args['autocomplete'] ?? '');
    $describedBy = (string) ($args['describedby'] ?? '');
    $required = ! empty($args['required']);
    $class = (string) ($args['class'] ?? '');
    ?>
    <p class="woocommerce-form-row form-row <?php echo esc_attr($class); ?>">
        <label for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($label); ?>
            <?php if ($required) : ?>
                &nbsp;<span class="required" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
        <input
            type="<?php echo esc_attr($type); ?>"
            class="woocommerce-Input woocommerce-Input--text input-text"
            name="<?php echo esc_attr($name); ?>"
            id="<?php echo esc_attr($id); ?>"
            value="<?php echo esc_attr($value); ?>"
            <?php echo $autocomplete !== '' ? 'autocomplete="' . esc_attr($autocomplete) . '"' : ''; ?>
            <?php echo $describedBy !== '' ? 'aria-describedby="' . esc_attr($describedBy) . '"' : ''; ?>
            <?php echo $required ? 'aria-required="true" required' : ''; ?>
        />
    </p>
    <?php
};

do_action('woocommerce_before_edit_account_form');
?>

<section class="ak-account-settings">
    <header class="ak-account-page__header">
        <p class="ak-account-section-kicker">Beállítások</p>
        <h2>Fiók beállítások</h2>
        <p>Személyes adatok, elérhetőség és jelszó egy helyen.</p>
    </header>

<form class="woocommerce-EditAccountForm edit-account ak-account-details-form" action="" method="post" <?php do_action('woocommerce_edit_account_form_tag'); ?>>
    <?php do_action('woocommerce_edit_account_form_start'); ?>

    <section class="ak-account-form-section ak-account-form-section--personal">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Személyes adatok</p>
            <h2>Fiók és elérhetőség</h2>
        </header>

        <div class="ak-account-form-grid">
            <?php
            $renderField([
                'id' => 'account_first_name',
                'label' => 'Keresztnév',
                'value' => $user->first_name,
                'autocomplete' => 'given-name',
                'required' => true,
            ]);
            $renderField([
                'id' => 'account_last_name',
                'label' => 'Vezetéknév',
                'value' => $user->last_name,
                'autocomplete' => 'family-name',
                'required' => true,
            ]);
            $renderField([
                'id' => 'account_display_name',
                'label' => 'Megjelenített név',
                'value' => $user->display_name,
                'required' => true,
                'describedby' => 'account_display_name_description',
                'class' => 'ak-account-form-grid__full',
            ]);
            ?>
            <span id="account_display_name_description" class="ak-account-field-hint ak-account-form-grid__full">Így fog megjelenni a neved a fiókban és az értékelésekben.</span>
            <?php
            $renderField([
                'id' => 'account_email',
                'label' => 'E-mail cím',
                'value' => $user->user_email,
                'type' => 'email',
                'autocomplete' => 'email',
                'required' => true,
                'class' => 'ak-account-form-grid__full',
            ]);
            $renderField([
                'id' => 'account_phone',
                'label' => 'Telefonszám',
                'value' => $accountPhone,
                'type' => 'tel',
                'autocomplete' => 'tel',
                'required' => true,
                'class' => 'ak-account-form-grid__half',
            ]);
            ?>
        </div>

        <?php do_action('woocommerce_edit_account_form_fields'); ?>
    </section>

    <section class="ak-account-form-section ak-account-form-section--addresses">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Mentett címek</p>
            <h2>A címeidet külön kezelheted</h2>
        </header>
        <p>A számlázási, szállítási és céges címadatokat a Címeim oldalon adhatod hozzá vagy módosíthatod.</p>
        <?php if (function_exists('wc_get_account_endpoint_url')) : ?>
            <a class="button" href="<?php echo esc_url(wc_get_account_endpoint_url('cimeim')); ?>">Címeim megnyitása</a>
        <?php endif; ?>
    </section>

    <section class="ak-account-form-section ak-account-form-section--password">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Biztonság</p>
            <h2>Jelszó módosítás</h2>
        </header>

        <p class="woocommerce-form-row form-row">
            <label for="password_current"><?php esc_html_e('Current password (leave blank to leave unchanged)', 'woocommerce'); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
        </p>

        <p class="woocommerce-form-row form-row">
            <label for="password_1"><?php esc_html_e('New password (leave blank to leave unchanged)', 'woocommerce'); ?></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" />
        </p>

        <p class="woocommerce-form-row form-row">
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
</section>

<?php do_action('woocommerce_after_edit_account_form'); ?>
