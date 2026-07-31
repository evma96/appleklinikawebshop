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
$countries = function_exists('appleklinika_account_country_options') ? appleklinika_account_country_options() : ['HU' => 'Magyarország'];
$meta = static function (string $key, string $default = '') use ($userId): string {
    return function_exists('appleklinika_account_user_meta')
        ? appleklinika_account_user_meta($userId, $key, $default)
        : (string) get_user_meta($userId, $key, true);
};
$isCompany = function_exists('appleklinika_checkout_company_enabled')
    ? appleklinika_checkout_company_enabled($meta('appleklinika_company_purchase', $meta('ak_billing_is_company')))
    : $meta('appleklinika_company_purchase') === '1';
$accountPhone = $meta('ak_account_phone', $meta('billing_phone', $meta('shipping_phone')));
$billingCompany = $meta('appleklinika_company_name', $meta('billing_company'));
$billingTaxNumber = $meta('appleklinika_tax_number', $meta('ak_billing_tax_number'));

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

$renderCountry = static function (string $id, string $label, string $value, string $class = '') use ($countries): void {
    ?>
    <p class="woocommerce-form-row form-row <?php echo esc_attr($class); ?>">
        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <select class="woocommerce-Input input-text ak-account-country-select" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" required aria-required="true">
            <?php foreach ($countries as $countryCode => $countryLabel) : ?>
                <option value="<?php echo esc_attr((string) $countryCode); ?>" <?php selected((string) $countryCode, $value); ?>><?php echo esc_html((string) $countryLabel); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
};

do_action('woocommerce_before_edit_account_form');
?>

<section class="ak-account-settings">
    <header class="ak-account-page__header">
        <p class="ak-account-section-kicker">Beállítások</p>
        <h2>Fiók beállítások</h2>
        <p>Személyes adatok, mentett címek, céges számlázás és jelszó egy helyen.</p>
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

    <section class="ak-account-form-section ak-account-address ak-account-address--shipping">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Szállítási cím</p>
            <h2>Hová küldjük a rendelést?</h2>
        </header>

        <div class="ak-account-form-grid">
            <?php
            $renderCountry('shipping_country', 'Ország', $meta('shipping_country', 'HU'), 'ak-account-form-grid__full');
            $renderField(['id' => 'shipping_postcode', 'label' => 'Irányítószám', 'value' => $meta('shipping_postcode'), 'autocomplete' => 'shipping postal-code', 'required' => true]);
            $renderField(['id' => 'shipping_city', 'label' => 'Város', 'value' => $meta('shipping_city'), 'autocomplete' => 'shipping address-level2', 'required' => true]);
            $renderField(['id' => 'shipping_address_1', 'label' => 'Utca / cím', 'value' => $meta('shipping_address_1'), 'autocomplete' => 'shipping address-line1', 'required' => true, 'class' => 'ak-account-form-grid__full']);
            $renderField(['id' => 'ak_shipping_house_number', 'label' => 'Házszám', 'value' => $meta('ak_shipping_house_number')]);
            $renderField(['id' => 'ak_shipping_floor', 'label' => 'Emelet', 'value' => $meta('ak_shipping_floor')]);
            $renderField(['id' => 'ak_shipping_staircase', 'label' => 'Lépcsőház', 'value' => $meta('ak_shipping_staircase')]);
            $renderField(['id' => 'ak_shipping_door', 'label' => 'Ajtó', 'value' => $meta('ak_shipping_door')]);
            $renderField(['id' => 'shipping_phone', 'label' => 'Telefonszám', 'value' => $meta('shipping_phone', $accountPhone), 'type' => 'tel', 'autocomplete' => 'shipping tel', 'required' => true]);
            ?>
        </div>
    </section>

    <section class="ak-account-form-section ak-account-address ak-account-address--billing <?php echo $isCompany ? 'is-company-enabled' : ''; ?>">
        <header class="ak-account-form-section__header">
            <p class="ak-account-section-kicker">Számlázási adatok</p>
            <h2>Magánszemély vagy céges vásárlás</h2>
        </header>

        <label class="ak-account-company-toggle" for="ak_billing_is_company">
            <input type="checkbox" name="ak_billing_is_company" id="ak_billing_is_company" value="1" <?php checked($isCompany); ?> />
            <span class="ak-account-company-toggle__box" aria-hidden="true"></span>
            <span>Cégként vásárolok</span>
        </label>

        <div class="ak-account-form-grid ak-account-billing-grid">
            <div class="ak-account-billing-personal-row">
                <?php
                $renderField(['id' => 'billing_first_name', 'label' => 'Keresztnév', 'value' => $meta('billing_first_name', $user->first_name), 'autocomplete' => 'billing given-name', 'required' => ! $isCompany]);
                $renderField(['id' => 'billing_last_name', 'label' => 'Vezetéknév', 'value' => $meta('billing_last_name', $user->last_name), 'autocomplete' => 'billing family-name', 'required' => ! $isCompany]);
                ?>
            </div>

            <div class="ak-account-company-fields">
                <?php
                $renderField(['id' => 'billing_company', 'label' => 'Cégnév', 'value' => $billingCompany, 'autocomplete' => 'organization', 'required' => $isCompany]);
                $renderField(['id' => 'ak_billing_tax_number', 'label' => 'Adószám', 'value' => $billingTaxNumber, 'autocomplete' => 'off', 'required' => $isCompany]);
                ?>
            </div>

            <?php
            $renderCountry('billing_country', 'Ország', $meta('billing_country', 'HU'), 'ak-account-form-grid__full');
            $renderField(['id' => 'billing_postcode', 'label' => 'Irányítószám', 'value' => $meta('billing_postcode'), 'autocomplete' => 'billing postal-code', 'required' => true]);
            $renderField(['id' => 'billing_city', 'label' => 'Város', 'value' => $meta('billing_city'), 'autocomplete' => 'billing address-level2', 'required' => true]);
            $renderField(['id' => 'billing_address_1', 'label' => 'Utca / cím', 'value' => $meta('billing_address_1'), 'autocomplete' => 'billing address-line1', 'required' => true, 'class' => 'ak-account-form-grid__full']);
            $renderField(['id' => 'ak_billing_house_number', 'label' => 'Házszám', 'value' => $meta('ak_billing_house_number')]);
            $renderField(['id' => 'ak_billing_floor', 'label' => 'Emelet', 'value' => $meta('ak_billing_floor')]);
            $renderField(['id' => 'ak_billing_staircase', 'label' => 'Lépcsőház', 'value' => $meta('ak_billing_staircase')]);
            $renderField(['id' => 'ak_billing_door', 'label' => 'Ajtó', 'value' => $meta('ak_billing_door')]);
            $renderField(['id' => 'billing_phone', 'label' => 'Telefonszám', 'value' => $meta('billing_phone', $accountPhone), 'type' => 'tel', 'autocomplete' => 'billing tel', 'required' => true]);
            $renderField(['id' => 'billing_email', 'label' => 'Számlázási e-mail', 'value' => $meta('billing_email', $user->user_email), 'type' => 'email', 'autocomplete' => 'billing email', 'required' => true]);
            ?>
        </div>
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
