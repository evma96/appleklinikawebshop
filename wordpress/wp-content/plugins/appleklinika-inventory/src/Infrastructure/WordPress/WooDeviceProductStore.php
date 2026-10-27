<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;
use Appleklinika\Inventory\Application\DeviceProduct\DeviceProductStore;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use InvalidArgumentException;
final class WooDeviceProductStore implements DeviceProductStore
{
    private static bool $writeLocked=false;
    public function __construct(private readonly ProductPhotoProcessor $photos=new ProductPhotoProcessor()) {}
    public function catalog(): array { return (new DeviceCatalogRepository())->all(); }
    public function load(int $id): array
    {
        $p=wc_get_product($id);if(!$p||!$p->is_type('simple'))throw new InvalidArgumentException('Csak létező egyszerű Woo készülék frissíthető.');
        $data=[];foreach($p->get_meta_data() as $m){$d=$m->get_data();if(str_starts_with($d['key'],'_appleklinika_'))$data[substr($d['key'],strlen('_appleklinika_'))]=$d['value'];}
        return array_replace($data,['sku'=>$p->get_sku(),'title'=>$p->get_name(),'custom_name'=>$p->get_meta('_appleklinika_custom_name')==='yes',
            'regular_price'=>$p->get_regular_price(),'sale_price'=>$p->get_sale_price(),'stock'=>$p->get_manage_stock()?(int)$p->get_stock_quantity():($p->is_in_stock()?1:0),
            'status'=>$p->get_status()==='auto-draft'?'draft':$p->get_status(),'photos'=>array_values(array_unique(array_filter(array_map('intval',array_merge([$p->get_image_id()],$p->get_gallery_image_ids())))))]);
    }
    public function assertUnique(string $sku,string $imei,string $serial,int $except): void
    {
        if($sku!==''&&!wc_product_has_unique_sku($except,$sku))throw new InvalidArgumentException('Ez a Cikkszám már másik termékhez tartozik.');
        foreach(['imei'=>$imei,'serial_number'=>$serial] as $key=>$value){
            if($value==='')continue;
            $ids=get_posts(['post_type'=>'product','post_status'=>['publish','draft','pending','private','future','trash','auto-draft'],'fields'=>'ids','numberposts'=>1,'post__not_in'=>[$except],
                'meta_key'=>'_appleklinika_'.$key,'meta_value'=>$value]);
            if($ids!==[])throw new InvalidArgumentException($key==='imei'?'Ez az IMEI már másik készülékhez tartozik.':'Ez a sorozatszám már másik készülékhez tartozik.');
        }
    }
    public function validatePhotos(array $ids,int $productId): void
    {
        foreach($ids as $id){
            if(get_post_type($id)!=='attachment'||!wp_attachment_is_image($id))throw new InvalidArgumentException('A kiválasztott fotó nem létező képfájl.');
            $parent=(int)wp_get_post_parent_id($id);
            if($parent!==0&&$parent!==$productId&&get_post_type($parent)==='product')throw new InvalidArgumentException('Másik készülék saját fotója nem másolható át automatikusan.');
            if(!is_file(get_attached_file($id)))throw new InvalidArgumentException('A termékfotó fájlja hiányzik.');
        }
    }
    public function apply(\WC_Product $p,array $plan): void
    {
        foreach($plan['photos'] as $id)$this->photos->sanitizeAttachment($id);
        $type=$plan['inventory']['device_type'];$slug=['apple_watch'=>'apple-watch'][$type]??$type;
        $category=get_term_by('slug',$slug,'product_cat');
        if(!$category)throw new InvalidArgumentException('A készülék Woo-kategóriája hiányzik; adminisztrátori beállítás szükséges.');
        $p->set_name($plan['title']);$p->set_status($plan['status']);
        if(!$p->get_id()||$p->get_slug()===''||!$plan['custom_name'])$p->set_slug(sanitize_title($plan['title']));
        $p->set_sku($plan['sku']);$p->set_regular_price($plan['regular_price']);$p->set_sale_price($plan['sale_price']);
        $p->set_price($plan['sale_price']!==''?$plan['sale_price']:$plan['regular_price']);
        $p->set_manage_stock(true);$p->set_stock_quantity($plan['stock']);$p->set_stock_status($plan['stock']===1?'instock':'outofstock');
        $p->set_sold_individually(true);$p->set_backorders('no');$p->set_catalog_visibility('visible');$p->set_category_ids([(int)$category->term_id]);
        $p->set_image_id($plan['photos'][0]??0);$p->set_gallery_image_ids(array_slice($plan['photos'],1));
        foreach($plan['inventory'] as $key=>$value)$p->update_meta_data('_appleklinika_'.$key,$value);
        foreach(['imei','serial_number'] as $key)$p->update_meta_data('_appleklinika_'.$key,$plan[$key]);
        $p->update_meta_data(PhysicalDevice::MARKER,'yes');$p->update_meta_data('_appleklinika_custom_name',$plan['custom_name']?'yes':'no');
    }
    public function persist(int $id,array $plan): int
    {
        $p=$id?wc_get_product($id):new \WC_Product_Simple();$this->apply($p,$plan);$saved=$p->save();
        foreach($plan['photos'] as $photo)if(!(int)wp_get_post_parent_id($photo))wp_update_post(['ID'=>$photo,'post_parent'=>$saved]);
        return $saved;
    }
    public function beginWrite(): bool
    {
        if (self::$writeLocked) return false;
        // One connection-owned advisory lock serializes all shared entry paths.
        // Product, photo and order records still use native Woo/WordPress storage.
        global $wpdb;
        $key=substr($wpdb->prefix.'appleklinika_device_entry',0,64);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)',$key))!==1) {
            throw new \RuntimeException('Másik készülék mentése folyamatban van. Próbáld újra.');
        }
        self::$writeLocked=true;
        return true;
    }
    public function endWrite(): void
    {
        if (!self::$writeLocked) return;
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',substr($wpdb->prefix.'appleklinika_device_entry',0,64)));
        self::$writeLocked=false;
    }
    public function synchronized(callable $operation): mixed
    {
        $owner=$this->beginWrite();
        try {return $operation();} finally {if($owner)$this->endWrite();}
    }
}
