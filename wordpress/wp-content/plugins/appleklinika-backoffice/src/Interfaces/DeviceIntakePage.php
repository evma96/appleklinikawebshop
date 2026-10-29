<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\SharedDeviceIntakeGateway;
use Appleklinika\Inventory\Application\DeviceProduct\DeviceIntakeSchema;

final class DeviceIntakePage
{
    public function render(): void
    {
        if (!DeviceIntakeController::allowed()) { echo '<p class="akbo-notice akbo-notice--error">A készülékbevételhez termékszerkesztési jogosultság szükséges.</p>'; return; }
        try {
            $gateway = new SharedDeviceIntakeGateway();
            $schema = (new DeviceIntakeSchema())->describe();
            $created = isset($_GET['created']) ? absint($_GET['created']) : 0;
            $saved = $created ? $gateway->summary($created) : null;
        } catch (\Throwable $error) { echo '<p class="akbo-notice akbo-notice--error">'.esc_html($error->getMessage()).'</p>'; return; }
        $config = ['schema'=>$schema, 'endpoint'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('appleklinika_intake'), 'operation'=>DeviceIntakeController::token(),
            'url'=>add_query_arg('view','device-intake',home_url('/backoffice/')), 'saved'=>$saved];
        ?>
        <div id="akbo-intake">
            <p class="akbo-help">Egy készülék, saját azonosítók és fotók, 1 darabos készlettel. A „Mentés és következő készülék” mindig piszkozatot készít.</p>
            <div id="akbo-intake-feedback" role="status" aria-live="polite"></div>
            <section id="akbo-intake-success" class="akbo-card" hidden tabindex="-1"></section>
            <form id="akbo-intake-form" class="akbo-intake-layout" method="post" autocomplete="off">
                <fieldset class="akbo-card akbo-intake-fields">
                    <legend>Készülékadatok</legend>
                    <div class="akbo-intake-grid">
                        <label>Készülékcsalád<select name="device_type" required autofocus><option value="">Válassz…</option><?php foreach ($schema['families'] as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; } ?></select></label>
                        <label>Modell<select name="device_model" required disabled><option value="">Előbb válassz készülékcsaládot</option></select></label>
                    </div>
                    <div id="akbo-intake-context" class="akbo-intake-grid"></div>
                    <div class="akbo-intake-grid">
                        <label>Állapot<select name="overall_grade" required><option value="">Válassz…</option><?php foreach ($schema['grades'] as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; } ?></select></label>
                        <label>Eladási ár (<?php echo esc_html(get_woocommerce_currency()); ?>)<input name="regular_price" type="number" min="0.01" max="9999999999" step="0.01" inputmode="decimal" required></label>
                        <label>Akciós ár (<?php echo esc_html(get_woocommerce_currency()); ?>) – opcionális<input name="sale_price" type="number" min="0" max="9999999999" step="0.01" inputmode="decimal"></label>
                        <label>Akkumulátor állapota (%) – ha ismert<input name="battery_health" type="number" min="0" max="100" step="1" inputmode="numeric"></label>
                    </div>
                    <p class="akbo-intake-name">Terméknév: <strong id="akbo-intake-name">A modell kiválasztása után automatikusan kitöltjük.</strong></p>
                    <label class="akbo-intake-check"><input name="custom_name" type="checkbox"> Egyedi terméknév</label>
                    <label id="akbo-intake-custom" hidden>Terméknév<input name="title" type="text" maxlength="200" disabled></label>
                </fieldset>
                <fieldset class="akbo-card akbo-intake-identity">
                    <legend>Azonosítók</legend>
                    <label>Cikkszám / Boltvezető azonosító<input name="sku" type="text" maxlength="100" required spellcheck="false"></label>
                    <p class="akbo-help">A bolt egyedi készülékazonosítója. Nem módosítjuk automatikusan.</p>
                    <label>IMEI<input name="imei" type="text" maxlength="100" inputmode="numeric" spellcheck="false"></label>
                    <label>Sorozatszám<input name="serial_number" type="text" maxlength="100" spellcheck="false"></label>
                    <p class="akbo-help">Legalább az egyik kötelező. Privát adatok: csak jogosult munkatársak és a kapcsolódó számlázási folyamat használja őket.</p>
                    <p id="akbo-intake-identity-error" class="akbo-intake-error" role="status"></p>
                </fieldset>
                <fieldset class="akbo-card akbo-intake-photos">
                    <legend>Termékfotók</legend>
                    <div id="akbo-intake-drop" class="akbo-intake-drop">
                        <label for="akbo-intake-files">Képek kiválasztása vagy ide húzása</label>
                        <input id="akbo-intake-files" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple>
                        <p class="akbo-help">JPEG, PNG, WebP, HEIC / HEIF · legfeljebb 12 kép, képenként 20 MB, összesen 100 MB.<br>Az első kép lesz a főfotó. Húzással vagy a nyilakkal rendezheted őket. Feltöltés és adatvédelem mentéskor.</p>
                    </div>
                    <ol id="akbo-intake-photo-list" class="akbo-intake-photo-list" aria-label="Rendezett termékfotók"></ol>
                </fieldset>
                <div class="akbo-intake-save">
                    <p class="akbo-help">Egy készülék · készlet: 1 db</p>
                    <button class="akbo-button akbo-button--secondary" type="submit" value="draft">Mentés piszkozatként</button>
                    <?php if(current_user_can('publish_products')): ?><button class="akbo-button akbo-button--secondary" type="submit" value="publish">Mentés és közzététel</button><?php endif; ?>
                    <button class="akbo-button" type="submit" value="next">Mentés és következő készülék</button>
                </div>
            </form>
            <noscript><p class="akbo-notice">A képek rendezéséhez és a biztonságos ismételt bevételezéshez engedélyezd a JavaScriptet.</p></noscript>
        </div>
        <script type="application/json" id="akbo-intake-config"><?php echo wp_json_encode($config, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
        <?php
        $base = dirname(__DIR__, 2);
        echo '<script src="'.esc_url(add_query_arg('ver',(string)filemtime($base.'/assets/device-intake.js'),plugins_url('assets/device-intake.js',$base.'/appleklinika-backoffice.php'))).'" defer></script>';
    }
}
