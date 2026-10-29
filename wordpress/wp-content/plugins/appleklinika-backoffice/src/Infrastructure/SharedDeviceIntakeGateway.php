<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\DeviceIntakeGateway;
use Appleklinika\BackOffice\Domain\{DeviceIntakeSaveMode,DeviceIntakeInventory};
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceIntakeSchema, DeviceProductService, SaveDeviceProductCommand};
use Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceProductStore;

/** Orchestration only: Inventory owns all product rules, mapping, media ordering and persistence. */
final class SharedDeviceIntakeGateway implements DeviceIntakeGateway
{
    public const OPERATION_META = '_akbo_intake_operation';
    private readonly WooDeviceProductStore $store;
    private readonly DeviceProductService $service;
    public function __construct(private readonly \Appleklinika\BackOffice\Application\Port\DevicePhotos $photos = new IntakePhotos())
    {
        if (!class_exists(DeviceProductService::class) || !class_exists(DeviceIntakeSchema::class)) { throw new \RuntimeException('A közös készülékszolgáltatás nem érhető el. Kérd a rendszergazda segítségét.'); }
        $this->store = new WooDeviceProductStore();
        $this->service = SharedDeviceService::create($this->store);
    }

    public function create(array $input, array $files, string $operation, int $employee, string $saveMode='draft'): array
    {
        $mode=DeviceIntakeSaveMode::parse($saveMode);
        return $this->store->synchronized(function () use ($input, $files, $operation, $employee, $mode): array {
            $existing = get_posts(['post_type'=>'product','post_status'=>array_keys(get_post_stati()),'fields'=>'ids','numberposts'=>1,'meta_key'=>self::OPERATION_META,'meta_value'=>$operation]);
            if ($existing) {
                $id=(int)$existing[0];$original=get_post_meta($id,'_akbo_intake_save_mode',true)?:'draft';
                if($original!==$mode->value||get_post_status($id)!==$mode->status())throw new \InvalidArgumentException('Ezt az űrlapot már más mentési móddal elmentették, vagy a termék állapota megváltozott. Nyiss új készülékűrlapot.');
                return $this->summary($id) + ['replayed'=>true];
            }
            $data = (new DeviceIntakeSchema())->validate($input,$mode->status());
            // Validate model, identities and prices before uploading. Final publication
            // validation runs again with the actual saved images inside the transaction.
            $this->service->prepare(new SaveDeviceProductCommand(0, array_replace($data,['status'=>'draft'])));
            $media = $this->photos->ingest($files, $operation);
            $id = 0;
            try {
                // Atomic product + replay marker. Native product/media remain the sole records.
                wc_transaction_query('start', true);
                $id = $this->service->save(new SaveDeviceProductCommand(0, $data + ['photos'=>$media]));
                if(!$id)throw new \RuntimeException('A készülék mentését nem sikerült véglegesíteni.');
                $product=wc_get_product($id);
                DeviceIntakeInventory::assertNewUnit($product->get_manage_stock(),$product->get_stock_quantity(),$product->is_sold_individually(),$product->get_stock_status());
                if (!$id || !add_post_meta($id, self::OPERATION_META, $operation, true)) { throw new \RuntimeException('A készülék mentését nem sikerült véglegesíteni.'); }
                update_post_meta($id,'_akbo_intake_save_mode',$mode->value);
                update_post_meta($id, '_akbo_intake_employee', $employee);
                $result = $this->summary($id);
                wc_transaction_query('commit', true);
            } catch (\Throwable $error) {
                wc_transaction_query('rollback', true);
                if ($id) { clean_post_cache($id); wc_delete_product_transients($id); }
                foreach ($media as $photo) { clean_post_cache($photo); }
                $this->photos->remove($media);
                throw $error;
            }
            // Audit is best-effort after commit: an audit outage must never invite another create.
            try { (new WooOrderBackOfficeRepository())->recordProductIntake($id, $employee); }
            catch (\Throwable) { $result['warning'] = 'A készülék elmentve, de a mai aktivitás frissítése nem sikerült.'; }
            return $result;
        });
    }

    public function summary(int $id): array
    {
        $p = wc_get_product($id);
        if (!$p || $p->get_meta('_appleklinika_physical_device') !== 'yes') { throw new \InvalidArgumentException('A bevételezett készülék nem található.'); }
        $data = $this->store->load($id);
        return ['id'=>$id,'name'=>$p->get_name(),'sku'=>$p->get_sku(),'model'=>(new DeviceIntakeSchema())->models()[$data['device_model']]['name'] ?? $data['device_model'],
            'regular_price'=>$p->get_regular_price(),'sale_price'=>$p->get_sale_price(),'price'=>$p->get_price(),'currency'=>get_woocommerce_currency(),'image_count'=>count($data['photos']),'stock'=>(int)$p->get_stock_quantity(),
            'images'=>array_values(array_map(static fn(int $photo): string => (string)wp_get_attachment_image_url($photo, 'medium'), $data['photos'])),
            'status'=>$p->get_status(),'similar'=>DeviceIntakeSchema::similar($data), 'imei'=>$data['imei'] ?? '', 'serial_number'=>$data['serial_number'] ?? ''];
    }
}
