<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\DeviceProducts;
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceIntakeSchema,DeviceOfferInput,SaveDeviceProductCommand};
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooDeviceProductStore,DeviceCatalogRepository};
use InvalidArgumentException;

/** Reads native Woo products; the shared Inventory service remains the sole price/condition writer. */
final class WooDeviceProducts implements DeviceProducts
{
    public const STATUSES=['draft'=>'Piszkozat','pending'=>'Ellenőrzésre vár','publish'=>'Közzétéve','private'=>'Privát','future'=>'Időzített'];
    private WooDeviceProductStore $store;
    public function __construct(private readonly array $filters=[]){ $this->store=new WooDeviceProductStore(); }
    public function listing(int $page,string $sku,string $status):array
    {
        $page=max(1,$page);$sku=mb_substr(trim($sku),0,100);
        // The canonical catalogue also contains legacy devices without the new
        // one-unit marker. Discover them from their existing device model/type;
        // never migrate products merely to make them appear in a list.
        $meta=[['key'=>'_appleklinika_device_model','value'=>'','compare'=>'!='],['key'=>'_appleklinika_device_type','value'=>['iphone','ipad','macbook','apple_watch','mac','watch'],'compare'=>'IN'],['relation'=>'OR',['key'=>'_appleklinika_protection_group','compare'=>'NOT EXISTS'],['key'=>'_appleklinika_protection_group','value'=>'']]];
        $family=$this->filters['family']??'';$stock=$this->filters['stock']??'';
        if(isset(DeviceIntakeSchema::FAMILIES[$family]))$meta[]=['key'=>'_appleklinika_device_type','value'=>$family];
        if(in_array($stock,['instock','outofstock'],true))$meta[]=['key'=>'_stock_status','value'=>$stock];
        $args=['post_type'=>'product','post_status'=>isset(self::STATUSES[$status])?[$status]:array_keys(self::STATUSES),'posts_per_page'=>24,'paged'=>$page,'orderby'=>'ID','order'=>'DESC','meta_query'=>$meta,
            'tax_query'=>[['taxonomy'=>'product_type','field'=>'slug','terms'=>['variable','grouped','external'],'operator'=>'NOT IN']],'akbo_device_search'=>$sku];
        if(!current_user_can('edit_others_products'))$args['author']=get_current_user_id();
        global $wpdb;
        $search=static function(string $where,\WP_Query $query)use($sku,$wpdb):string{
            if($sku===''||$query->get('akbo_device_search')!==$sku)return $where;
            $like='%'.$wpdb->esc_like($sku).'%';
            return $where.$wpdb->prepare(" AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} akbo_search WHERE akbo_search.post_id={$wpdb->posts}.ID AND akbo_search.meta_key IN ('_sku','_appleklinika_device_model','_appleklinika_imei','_appleklinika_serial_number','_appleklinika_internal_identifier') AND akbo_search.meta_value LIKE %s))",$like,$like);
        };
        add_filter('posts_where',$search,10,2);
        try{$query=new \WP_Query($args);}finally{remove_filter('posts_where',$search,10);}
        $items=[];
        foreach($query->posts as $post){$p=wc_get_product($post->ID);if($p&&$p->is_type('simple'))$items[]=$this->describe($p);}
        return ['items'=>$items,'total'=>(int)$query->found_posts,'pages'=>(int)$query->max_num_pages,'page'=>$page];
    }
    public function detail(int $id):array
    {
        $p=$this->product($id);$data=$this->store->load($id);
        return $this->describe($p)+['imei'=>$data['imei']??'','serial_number'=>$data['serial_number']??'','images'=>array_map(static fn($photo)=>['id'=>$photo,'url'=>wp_get_attachment_image_url($photo,'medium')],$data['photos']),'version'=>$this->version($data)];
    }
    public function updateOffer(int $id,array $input,string $expected):void
    {
        $this->store->synchronized(function()use($id,$input,$expected):void{
            $p=$this->product($id);
            if($p->get_meta(PhysicalDevice::MARKER)!=='yes')throw new InvalidArgumentException('A korábban rögzített készülék adatait a teljes WooCommerce szerkesztőben ellenőrizheted. A Back Office nem alakítja át automatikusan.');
            if(!current_user_can('edit_product',$id))throw new InvalidArgumentException('Nincs jogosultságod ennek a készüléknek a szerkesztéséhez.');
            if(!in_array($p->get_status(),['draft','pending','publish','private'],true))throw new InvalidArgumentException('Ezt az állapotot a teljes WooCommerce szerkesztőben kezelheted.');
            if(!hash_equals($this->version($this->store->load($id)),$expected))throw new InvalidArgumentException('A készülék időközben megváltozott. Töltsd újra az oldalt a mentés előtt.');
            // The narrow editor must not overwrite component grades that it does not display.
            $patch=array_intersect_key(DeviceOfferInput::validate($input),array_flip(['regular_price','sale_price','overall_grade']));
            $service=SharedDeviceService::create($this->store);
            $service->save(new SaveDeviceProductCommand($id,$patch));
        });
    }
    public function publish(int $id,string $expected):void
    {
        $this->store->synchronized(function()use($id,$expected):void{
            $p=$this->product($id);
            if(!current_user_can('edit_product',$id)||!current_user_can('publish_products'))throw new InvalidArgumentException('Nincs jogosultságod a készülék közzétételéhez.');
            if($p->get_status()!=='draft'||$p->get_meta(PhysicalDevice::MARKER)!=='yes')throw new InvalidArgumentException('Csak a bevételezett piszkozat tehető közzé ezzel a művelettel.');
            if(!hash_equals($this->version($this->store->load($id)),$expected))throw new InvalidArgumentException('A készülék időközben megváltozott. Töltsd újra az oldalt a közzététel előtt.');
            SharedDeviceService::create($this->store)->save(new SaveDeviceProductCommand($id,['status'=>'publish']));
        });
    }
    private function product(int $id): \WC_Product
    {
        $p=wc_get_product($id);
        if(!$p||!$p->is_type('simple')||$p->get_meta('_appleklinika_device_model')===''||!isset(DeviceIntakeSchema::FAMILIES[PhysicalDevice::type((string)$p->get_meta('_appleklinika_device_type'))])||$p->get_meta('_appleklinika_protection_group')!==''||!isset(self::STATUSES[$p->get_status()]))throw new InvalidArgumentException('A készülék nem található.');
        return $p;
    }
    private function version(array $data):string {return hash('sha256',serialize($data));}
    private function describe(\WC_Product $p):array
    {
        $id=$p->get_id();$type=(string)$p->get_meta('_appleklinika_device_type');$key=(string)$p->get_meta('_appleklinika_device_model');
        $model=null;foreach((new DeviceCatalogRepository())->all() as $entry)if($entry['key']===$key){$model=$entry;break;}
        $editable=current_user_can('edit_product',$id);
        return ['id'=>$id,'name'=>$p->get_name(),'sku'=>$p->get_sku(),'family'=>DeviceIntakeSchema::FAMILIES[$type]??$type,'model'=>$model['name']??$key,
            'grade'=>(string)$p->get_meta('_appleklinika_overall_grade'),'condition'=>Grade::options()[$p->get_meta('_appleklinika_overall_grade')]??'Nincs megadva',
            'regular_price'=>$p->get_regular_price(),'sale_price'=>$p->get_sale_price(),'price'=>$p->get_price(),'stock'=>$p->get_stock_quantity(),'manage_stock'=>$p->get_manage_stock(),
            'status'=>$p->get_status(),'status_label'=>self::STATUSES[$p->get_status()]??$p->get_status(),'stock_status'=>$p->get_stock_status(),'offer_editable'=>$p->get_meta(PhysicalDevice::MARKER)==='yes','publishable'=>$editable&&current_user_can('publish_products')&&$p->get_status()==='draft'&&$p->get_meta(PhysicalDevice::MARKER)==='yes','thumbnail'=>wp_get_attachment_image_url($p->get_image_id(),'woocommerce_thumbnail')?:wc_placeholder_img_src(),
            'edit_url'=>$editable?add_query_arg(['view'=>'device-edit','device'=>$id],home_url('/backoffice/')):'','native_edit_url'=>$editable?admin_url('post.php?post='.$id.'&action=edit'):'',
            'preview_url'=>CanonicalRuntime::storefront($p->get_status()==='publish'?get_permalink($id):($editable?(get_preview_post_link($id)??''):'') )];
    }
}
