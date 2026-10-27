<?php
/** Test helper: scoped in-memory capabilities, no persistent user changes. */
declare(strict_types=1);
return static function(int $id,callable $check):void {
require_once WC_ABSPATH.'includes/admin/class-wc-admin-meta-boxes.php';
require_once WC_ABSPATH.'includes/admin/meta-boxes/class-wc-meta-box-product-data.php';
if(wc_get_product($id)->get_meta('_ak_device_foundation_qa')!=='yes')exit(1);
$capsFilter=static function($caps,$cap,$user,$args)use($id){return in_array($cap,['edit_post','edit_product'],true)&&($args[0]??0)===$id?['exist']:$caps;};
add_filter('map_meta_cap',$capsFilter,10,4);
$userFilter=static function($all){$all['exist']=true;return $all;};
add_filter('user_has_cap',$userFilter);
$previousErrors=WC_Admin_Meta_Boxes::$meta_box_errors;
try {
$baseline=wc_get_product($id);$stock=$baseline->get_stock_quantity();
$post=[
 'appleklinika_product_condition_nonce'=>wp_create_nonce('appleklinika_save_product_condition'),
 'appleklinika_device_type'=>'iphone','appleklinika_device_model'=>'iphone_13_pro','appleklinika_storage_capacity'=>'128_gb',
 'appleklinika_color'=>'sierra_blue','appleklinika_overall_grade'=>'a','appleklinika_imei'=>'QA-IMEI-PHONE-001','appleklinika_serial_number'=>'QA-SERIAL-PHONE-001',
 '_sku'=>$baseline->get_sku(),'_regular_price'=>'139990','_sale_price'=>'','_thumbnail_id'=>$baseline->get_image_id(),
 'product_image_gallery'=>implode(',',$baseline->get_gallery_image_ids()),'_appleklinika_protection_group'=>'','product-type'=>'simple','_stock'=>'1',
 'post_title'=>'','post_status'=>'publish','original_post_title'=>$baseline->get_name()
];
$run=static function(array $overrides)use($post,$id):void {
 $_POST=array_replace($post,$overrides);
 wp_update_post(['ID'=>$id,'post_title'=>$_POST['post_title'],'post_status'=>$_POST['post_status']]);
 WC_Meta_Box_Product_Data::save($id,get_post($id));
 $_POST=[];
};
$run([]);$p=wc_get_product($id);
$check($p->get_name()==='iPhone 13 Pro 128 GB','Native admin generates blank automatic title');
$check($p->get_status()==='publish'&&$p->get_manage_stock()&&$p->is_sold_individually()&&$p->get_stock_quantity()===1,'Native editor enforces device defaults without hidden inputs');
$check($p->get_meta('_appleklinika_imei')==='QA-IMEI-PHONE-001','Native editor saves private IMEI');
$run(['appleklinika_custom_name'=>'yes','post_title'=>'iPhone 13 Pro – egyedi próbanév']);
$check(wc_get_product($id)->get_name()==='iPhone 13 Pro – egyedi próbanév','Native editor custom title preserved');
$run(['_stock'=>'2']);$p=wc_get_product($id);
$check($p->get_status()==='draft'&&$p->get_stock_quantity()===1,'Invalid native stock gives editable draft, no corrupted stock');
$check(count(WC_Admin_Meta_Boxes::$meta_box_errors)>0,'Native validation error visible');
$run([]);$check(wc_get_product($id)->get_status()==='publish','Valid retry publishes');
$run(['_appleklinika_protection_group'=>'case']);$p=wc_get_product($id);
$check($p->get_status()==='draft'&&$p->get_meta('_appleklinika_protection_group')==='','Native device/accessory conflict blocks publication');
$run(['appleklinika_device_model'=>'','_appleklinika_protection_group'=>'case']);
$check(wc_get_product($id)->get_status()==='draft'&&wc_get_product($id)->get_meta('_appleklinika_protection_group')==='','Clearing model in form cannot bypass device/accessory guard');
$run([]);
wc_update_product_stock($id,0);$run(['_original_stock'=>'1']);$p=wc_get_product($id);
$check($p->get_stock_quantity()===0&&$p->get_status()==='draft','Stale native editor cannot restock a sold device');
$run(['_original_stock'=>'0','_stock'=>'0']);$check(wc_get_product($id)->get_stock_quantity()===0,'Native valid edit preserves sold stock zero');
$run(['_original_stock'=>'0','_stock'=>'1']);
$check(wc_get_product($id)->get_stock_quantity()===1&&wc_get_product($id)->get_status()==='publish','Explicit restock restores device');
$search=WC_Data_Store::load('product')->search_products($baseline->get_sku());
$check(in_array($id,$search,true),'Native Woo admin SKU search finds physical device');
} finally {
    $_POST=[];
    remove_filter('map_meta_cap',$capsFilter,10);
    remove_filter('user_has_cap',$userFilter);
    WC_Admin_Meta_Boxes::$meta_box_errors=$previousErrors;
}
};
