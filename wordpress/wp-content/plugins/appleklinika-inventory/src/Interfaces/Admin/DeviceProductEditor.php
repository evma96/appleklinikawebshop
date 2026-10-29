<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces\Admin;
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceProductService,SaveDeviceProductCommand};
use Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceProductStore;
final class DeviceProductEditor
{
    private array $errors=[];
    private bool $ownsWrite=false;
    public function __construct(private readonly DeviceProductService $service,private readonly WooDeviceProductStore $store) {}
    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data',[$this,'fields']);
        add_filter('wp_insert_post_empty_content',[$this,'allowAutomaticTitle'],10,2);
        add_filter('wp_insert_post_data',[$this,'postData'],20,2);
        add_action('woocommerce_admin_process_product_object',[$this,'saveObject'],20);
        add_action('woocommerce_after_product_object_save',[$this,'finishWrite'],99);
        add_action('shutdown',[$this,'finishWrite']);
        add_action('admin_footer-post.php',[$this,'titleScript']);
        add_action('admin_footer-post-new.php',[$this,'titleScript']);
        add_filter('gettext_woocommerce',[$this,'skuLabel'],10,3);
    }
    private function authorized(int $id): bool
    {
        return isset($_POST['appleklinika_product_condition_nonce'])&&is_string($_POST['appleklinika_product_condition_nonce'])
            &&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['appleklinika_product_condition_nonce'])),'appleklinika_save_product_condition')
            &&current_user_can('edit_product',$id);
    }
    private function input(int $id,string $status): array
    {
        $input=[];
        foreach($_POST as $key=>$value)if(str_starts_with($key,'appleklinika_')&&is_scalar($value))$input[substr($key,strlen('appleklinika_'))]=sanitize_text_field(wp_unslash((string)$value));
        $input['custom_name']=isset($_POST['appleklinika_custom_name']);
        foreach(['sku'=>'_sku','regular_price'=>'_regular_price','sale_price'=>'_sale_price','title'=>'post_title','protection_group'=>'_appleklinika_protection_group'] as $key=>$post){
            if(isset($_POST[$post])&&is_scalar($_POST[$post]))$input[$key]=wp_unslash((string)$_POST[$post]);
        }
        foreach(['regular_price','sale_price'] as $key)if(isset($input[$key]))$input[$key]=wc_format_decimal($input[$key]);
        $current=$id?wc_get_product($id):null;
        if ($current && $current->get_manage_stock() && isset($_POST['_original_stock']) && (int)$_POST['_original_stock'] !== (int)$current->get_stock_quantity()) {
            throw new \InvalidArgumentException('A készlet időközben megváltozott. Töltsd újra a terméket, mielőtt mented.');
        }
        // An empty native stock field is the natural new-device default. Do not restock sold units on edit.
        $input['stock']=isset($_POST['_stock'])&&$_POST['_stock']!==''?wp_unslash($_POST['_stock']):($current&&$current->get_manage_stock()?(int)$current->get_stock_quantity():1);
        $main=isset($_POST['_thumbnail_id'])?(int)$_POST['_thumbnail_id']:(int)get_post_thumbnail_id($id);
        $gallery=isset($_POST['product_image_gallery'])?array_filter(explode(',',sanitize_text_field(wp_unslash($_POST['product_image_gallery'])))):($current?$current->get_gallery_image_ids():[]);
        $input['photos']=array_values(array_filter(array_merge([$main],$gallery)));
        $input['status']=$status==='auto-draft'?'draft':$status;
        return $input;
    }
    public function allowAutomaticTitle(bool $empty,array $post): bool
    {
        return ($post['post_type']??'')==='product'&&$this->authorized((int)($post['ID']??0))&&!empty($_POST['appleklinika_device_model'])?false:$empty;
    }
    public function postData(array $data,array $post): array
    {
        $id=(int)($post['ID']??0);
        if(($data['post_type']??'')!=='product'||!$this->authorized($id)||(empty($_POST['appleklinika_device_model'])&&get_post_meta($id,'_appleklinika_device_model',true)===''))return $data;
        unset($this->errors[$id]);
        try {
            if(!$this->ownsWrite)$this->ownsWrite=$this->store->beginWrite();
            $plan=$this->service->prepare(new SaveDeviceProductCommand($id,$this->input($id,$data['post_status'])));
            $data['post_title']=wp_slash($plan['title']);
            if(!$plan['custom_name']||empty($data['post_name']))$data['post_name']=sanitize_title($plan['title']);
        } catch(\Throwable $error) {
            $this->errors[$id]=$error->getMessage();
            // Invalid entry is retained as an editable draft, never silently published.
            $data['post_status']='draft';
        }
        return $data;
    }
    public function saveObject(\WC_Product $product): void
    {
        $id=$product->get_id();if(!$this->authorized($id)||(empty($_POST['appleklinika_device_model'])&&$product->get_meta('_appleklinika_device_model')===''))return;
        if(isset($this->errors[$id])){
            $this->retainDraft($product);\WC_Admin_Meta_Boxes::add_error($this->errors[$id]);return;
        }
        try {
            if(!$this->ownsWrite)$this->ownsWrite=$this->store->beginWrite();
            $plan=$this->service->prepare(new SaveDeviceProductCommand($id,$this->input($id,$product->get_status())));
            $this->store->apply($product,$plan);
        } catch(\Throwable $error) {
            $this->retainDraft($product);\WC_Admin_Meta_Boxes::add_error($error->getMessage());
        }
    }
    public function finishWrite(): void
    {
        if($this->ownsWrite){$this->store->endWrite();$this->ownsWrite=false;}
    }
    private function retainDraft(\WC_Product $product): void
    {
        $previous=wc_get_product($product->get_id());
        $product->set_status('draft');
        if ($previous && $previous->get_meta('_appleklinika_physical_device')==='yes') {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($previous->get_stock_quantity());
            $product->set_stock_status($previous->get_stock_status());
            $product->set_sold_individually(true);
            $product->set_backorders('no');
        }
    }
    public function fields(): void
    {
        global $product_object;if(!$product_object instanceof \WC_Product)return;
        $profile=$this->service->technicalProfileStatus((string)$product_object->get_meta('_appleklinika_device_model'));
        if ($profile['model_key']!=='') foreach($profile['warnings'] as $warning) {
            echo '<div class="notice notice-warning inline" data-model-spec-warning><p>'.esc_html($warning).'</p></div>';
        }
        echo '<div class="options_group">';
        woocommerce_wp_checkbox(['id'=>'appleklinika_custom_name','label'=>'Egyedi terméknév','value'=>$product_object->get_meta('_appleklinika_custom_name')==='yes'?'yes':'no',
            'description'=>'Kikapcsolva a modell és tárhely adja a nevet. Apple Watchnál a belső tárhely nem kerül a névbe.']);
        foreach(['imei'=>'IMEI','serial_number'=>'Sorozatszám'] as $key=>$label)woocommerce_wp_text_input(['id'=>'appleklinika_'.$key,'label'=>$label,'value'=>$product_object->get_meta('_appleklinika_'.$key),
            'description'=>'Privát készülékazonosító. Nyilvános termékoldalon nem jelenik meg; a saját számlatételen feltüntetjük.']);
        echo '<p class="form-field">Egy készülék = egy Woo-termék, 0 vagy 1 darab. Publikáláshoz Cikkszám, IMEI vagy sorozatszám, ár és saját fotó szükséges. Az Extra védelem csoport készüléknél „Nem kiegészítő”.</p></div>';
    }
    public function skuLabel(string $translated,string $text,string $domain): string
    {
        return is_admin()&&$text==='SKU'?'Cikkszám / Boltvezető azonosító':$translated;
    }
    public function titleScript(): void
    {
        global $post;if(!$post instanceof \WP_Post||$post->post_type!=='product')return;
        $models=[];foreach($this->store->catalog() as $m)$models[$m['key']]=['name'=>$m['name'],'type'=>$m['type']];
        echo '<script>(function(){const models='.wp_json_encode($models).';const title=document.getElementById("title"),custom=document.getElementById("appleklinika_custom_name"),model=document.getElementById("appleklinika_device_model"),storage=document.getElementById("appleklinika_storage_capacity");if(!title||!custom||!model)return;function sync(){const m=models[model.value];title.readOnly=!!m&&!custom.checked;title.setAttribute("aria-readonly",title.readOnly?"true":"false");if(title.readOnly){const s=storage&&storage.value?storage.options[storage.selectedIndex].text:"";title.value=m.name+(m.type!=="watch"&&m.type!=="apple_watch"&&s?" "+s:"");document.getElementById("title-prompt-text")?.classList.add("screen-reader-text");}}[custom,model,storage].filter(Boolean).forEach(e=>e.addEventListener("change",sync));sync();})();</script>';
    }
}
