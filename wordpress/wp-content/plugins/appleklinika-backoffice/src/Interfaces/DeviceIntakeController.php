<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Application\IntakeDevice;
use Appleklinika\BackOffice\Domain\DeviceIntakeSaveMode;
use Appleklinika\BackOffice\Infrastructure\{IntakePhotos, SharedDeviceIntakeGateway};

final class DeviceIntakeController
{
    public static function allowed(): bool
    {
        return (current_user_can(BackOfficeRouter::CAPABILITY) || current_user_can('manage_options')) && current_user_can('edit_products');
    }

    public function register(): void
    {
        add_action('wp_ajax_appleklinika_intake_device', [$this, 'create']);
        add_action('wp_ajax_appleklinika_intake_identity', [$this, 'identity']);
        add_action('appleklinika_intake_cleanup', [new IntakePhotos(), 'cleanupAbandoned']);
        // A second interface must not install housekeeping against the shared store on a GET.
        if ((!defined('AKBO_CANONICAL_RUNTIME') || !AKBO_CANONICAL_RUNTIME) && !wp_next_scheduled('appleklinika_intake_cleanup')) { wp_schedule_event(time()+HOUR_IN_SECONDS, 'daily', 'appleklinika_intake_cleanup'); }
    }

    public static function token(): string
    {
        $id = wp_generate_uuid4();
        return $id.'.'.wp_hash('akbo-intake:'.get_current_user_id().':'.$id);
    }

    private function authorize(): void
    {
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !self::allowed()) { wp_send_json_error(['message'=>'Nincs jogosultságod a készülékbevételhez.'], 403); }
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > IntakePhotos::MAX_TOTAL_BYTES + 1048576) { wp_send_json_error(['message'=>'A fotók összes mérete legfeljebb 100 MB lehet. Válassz kevesebb képet.'], 413); }
        if (!check_ajax_referer('appleklinika_intake', 'nonce', false)) { wp_send_json_error(['message'=>'A munkamenet lejárt. Nyisd meg újra a készülékbevételt.'], 403); }
    }

    public function identity(): void
    {
        $this->authorize();
        try {
            $read = static fn(string $key): string => \Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice::text(wp_unslash($_POST[$key] ?? ''));
            (new \Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceProductStore())->assertUnique($read('sku'), $read('imei'), $read('serial_number'), 0);
            wp_send_json_success();
        } catch (\InvalidArgumentException $error) { wp_send_json_error(['message'=>$error->getMessage()], 422); }
    }

    public function create(): void
    {
        $this->authorize();
        try {
            $mode=DeviceIntakeSaveMode::parse($_POST['save_mode']??'draft');
            if($mode===DeviceIntakeSaveMode::Publish&&!current_user_can('publish_products'))wp_send_json_error(['message'=>'Nincs jogosultságod a készülék közzétételéhez.'],403);
            \Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::authorizeProductWrite();
            $token = is_string($_POST['operation'] ?? null) ? wp_unslash($_POST['operation']) : '';
            $parts = explode('.', $token);
            if (count($parts) !== 2 || !preg_match('/^[a-f0-9-]{36}$/D', $parts[0]) || !hash_equals(wp_hash('akbo-intake:'.get_current_user_id().':'.$parts[0]), $parts[1])) {
                throw new \InvalidArgumentException('Érvénytelen bevételezési kérés. Töltsd újra az oldalt.');
            }
            $operation = hash('sha256', get_current_user_id().':'.$token);
            $input = json_decode(is_string($_POST['device'] ?? null) ? wp_unslash($_POST['device']) : '', true);
            if (!is_array($input)) { throw new \InvalidArgumentException('A készülékadatok nem olvashatók.'); }
            $upload = $_FILES['photos'] ?? [];
            $files = [];
            foreach (is_array($upload['tmp_name'] ?? null) ? array_keys($upload['tmp_name']) : [] as $key) {
                $files[] = ['tmp_name'=>$upload['tmp_name'][$key], 'error'=>$upload['error'][$key] ?? UPLOAD_ERR_NO_FILE];
            }
            $result = (new IntakeDevice(new SharedDeviceIntakeGateway()))->execute($input, $files, $operation, get_current_user_id(),$mode->value);
            wp_send_json_success(['device'=>$result, 'next_operation'=>self::token()]);
        } catch (\InvalidArgumentException $error) { wp_send_json_error(['message'=>$error->getMessage()], 422); }
        catch (\RuntimeException $error) { wp_send_json_error(['message'=>$error->getMessage()], 503); }
        catch (\Throwable) { wp_send_json_error(['message'=>'A mentés nem fejeződött be. Próbáld újra ugyanazzal az űrlappal; sikeres korábbi mentésből nem készül másolat.'], 500); }
    }
}
