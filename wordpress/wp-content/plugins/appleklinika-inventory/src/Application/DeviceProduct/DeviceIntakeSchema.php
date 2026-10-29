<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\DeviceProduct;

use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use Appleklinika\Inventory\Domain\ProductCondition\{DeviceFieldOptions as Options, Grade, StorageCapacityCatalog};
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use InvalidArgumentException;

/** Staff-entry completeness is stricter than the shared editor's incomplete drafts.
 * All model constraints and titles come from Inventory, never from the Back Office.
 */
final class DeviceIntakeSchema
{
    public const REUSABLE = ['device_type','device_model','storage_capacity','color','sim_config','connectivity','screen_size','processor_chip','ram_size','case_size','case_material'];
    public const PER_DEVICE = ['sku','imei','serial_number','overall_grade','battery_health','regular_price','sale_price','custom_name','title'];
    public const FAMILIES = ['iphone'=>'iPhone','ipad'=>'iPad','macbook'=>'Mac','apple_watch'=>'Apple Watch'];

    public function __construct(private readonly DeviceCatalogRepository $catalog = new DeviceCatalogRepository()) {}

    public function models(): array
    {
        $models = [];
        foreach ($this->catalog->all() as $model) {
            $type = PhysicalDevice::type((string)$model['type']);
            if (!isset(self::FAMILIES[$type])) { continue; }
            $fields = [];
            if ($type !== 'apple_watch') {
                $storage = StorageCapacityCatalog::options();
                if ($type === 'iphone') { $storage = array_intersect_key($storage, array_flip($model['storage_capacity_keys'] ?? [])); }
                $fields['storage_capacity'] = $this->choice('Tárhely', $storage);
            }
            $watch = $this->catalog->watchOptionsByModel()[$model['key']] ?? [];
            if ($type === 'apple_watch') {
                $fields['case_size'] = $this->choice('Tokméret', array_intersect_key(Options::CASE_SIZE, array_flip($watch['case_sizes'] ?? array_keys(Options::CASE_SIZE))));
                $fields['case_material'] = $this->choice('Tok anyaga', array_intersect_key(Options::CASE_MATERIAL, array_flip($watch['case_materials'] ?? array_keys(Options::CASE_MATERIAL))));
            }
            $fields['color'] = $this->choice('Szín', $model['colors']);
            if ($type === 'iphone') { $fields['sim_config'] = $this->choice('SIM-kialakítás', Options::SIM); }
            if ($type === 'ipad' || $type === 'apple_watch') {
                $keys = $type === 'ipad' ? ['wifi','wifi_cellular'] : ($watch['connectivity'] ?? ['gps','gps_cellular']);
                $fields['connectivity'] = $this->choice('Kapcsolat', array_intersect_key(Options::CONNECTIVITY, array_flip($keys)));
            }
            if ($type === 'macbook') {
                $screen = Options::SCREEN; $chip = Options::CHIP;
                if (preg_match('/(13|14|15|16)-inch/', $model['name'], $match)) { $screen = array_intersect_key($screen, [$match[1].'_inch'=>true]); }
                if (preg_match('/\b(M[1-5](?: Pro| Max)?)\b/', $model['name'], $match)) { $chip = array_intersect_key($chip, [strtolower(str_replace(' ', '_', $match[1]))=>true]); }
                $fields['screen_size'] = $this->choice('Kijelzőméret', $screen);
                $fields['processor_chip'] = $this->choice('Processzor', $chip);
                $fields['ram_size'] = $this->choice('Memória', Options::RAM);
            }
            $names = ['' => PhysicalDevice::title([], $model)];
            foreach (array_keys($fields['storage_capacity']['options'] ?? []) as $key) { $names[$key] = PhysicalDevice::title(['storage_capacity'=>$key], $model); }
            $models[$model['key']] = ['name'=>$model['name'], 'type'=>$type, 'fields'=>$fields, 'names'=>$names, 'colors_by_material'=>$watch['colors_by_material'] ?? []];
        }
        return $models;
    }

    public function describe(): array
    {
        return ['families'=>self::FAMILIES, 'models'=>$this->models(), 'grades'=>Grade::options(), 'reusable'=>self::REUSABLE, 'per_device'=>self::PER_DEVICE];
    }

    /** Ignore arbitrary stock/status/media/meta keys: the normal intake owns only these fields. */
    public function validate(array $input,string $status='draft'): array
    {
        if(!in_array($status,['draft','publish'],true))throw new InvalidArgumentException('Érvénytelen közzétételi állapot.');
        $data = [];
        $keys = array_merge(self::REUSABLE, ['overall_grade','regular_price','sale_price','sku','imei','serial_number','battery_health','title']);
        foreach ($keys as $key) { $data[$key] = PhysicalDevice::text($input[$key] ?? ''); }
        $model = $this->models()[$data['device_model']] ?? null;
        if (!$model || $model['type'] !== $data['device_type']) { throw new InvalidArgumentException('Válassz a készülékcsaládhoz tartozó modellt.'); }
        foreach ($model['fields'] as $key=>$field) {
            if (!array_key_exists($data[$key], $field['options'])) { throw new InvalidArgumentException($field['label'].': válassz a modellhez tartozó értéket.'); }
        }
        if ($model['colors_by_material'] !== [] && !in_array($data['color'], $model['colors_by_material'][$data['case_material']] ?? [], true)) { throw new InvalidArgumentException('A szín nem tartozik a kiválasztott tokanyaghoz.'); }
        foreach (self::REUSABLE as $key) { if (!in_array($key, ['device_type','device_model'], true) && !isset($model['fields'][$key])) { unset($data[$key]); } }
        if ($data['sku'] === '') { throw new InvalidArgumentException('Add meg a Cikkszám / Boltvezető azonosítót.'); }
        if ($data['imei'] === '' && $data['serial_number'] === '') { throw new InvalidArgumentException('Adj meg IMEI-t vagy sorozatszámot.'); }
        $data = array_replace($data, DeviceOfferInput::validate($data));
        if ($data['battery_health'] !== '' && (!ctype_digit($data['battery_health']) || (int)$data['battery_health'] > 100)) { throw new InvalidArgumentException('Az akkumulátor állapota 0 és 100 közötti egész szám lehet.'); }
        $data['custom_name'] = filter_var($input['custom_name'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$data['custom_name']) { unset($data['title']); }
        return $data + ['stock'=>1, 'status'=>$status];
    }

    public static function similar(array $input): array { return array_intersect_key($input, array_flip(self::REUSABLE)); }
    private function choice(string $label, array $options): array { return ['label'=>$label, 'options'=>$options, 'required'=>true]; }
}
