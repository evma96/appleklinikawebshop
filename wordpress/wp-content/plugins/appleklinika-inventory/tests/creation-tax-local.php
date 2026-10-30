<?php
/** Native Woo creation + actual installed Szamlazz line builder; no provider transport. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__,4).'/wp-load.php';
if (wp_get_environment_type()!=='local' || getenv('AK_LOCAL_VERIFICATION')!=='1' || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Isolated LOCAL only');
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceProductService,SaveDeviceProductCommand};
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooDeviceProductStore,WooProductConditionRepository,DeviceCatalogRepository,WordPressModelSpecifications,ApprovedMixedTaxConfiguration,DeviceInvoiceMapping};
$n=$http=$mail=0;$ids=[];$order=null;$options=[];
$ok=static function($v,$why)use(&$n){++$n;if(!$v)throw new RuntimeException($why);};
add_filter('pre_http_request',static function()use(&$http){++$http;return new WP_Error('offline');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail){++$mail;return false;},PHP_INT_MAX);
$beforeProducts=get_posts(['post_type'=>'product','post_status'=>array_keys(get_post_stati()),'fields'=>'ids','numberposts'=>-1]);sort($beforeProducts);
$classes=WC_Tax::get_tax_class_slugs();$rateIds=[];foreach([ApprovedMixedTaxConfiguration::IPHONE,ApprovedMixedTaxConfiguration::ACCESSORY]as$slug)$rateIds[$slug]=array_keys(WC_Tax::get_rates_for_tax_class($slug));
foreach(['woocommerce_default_country'=>'HU','woocommerce_calc_taxes'=>'yes','woocommerce_prices_include_tax'=>'yes','woocommerce_tax_display_shop'=>'incl','woocommerce_tax_display_cart'=>'incl']as$k=>$v){$options[$k]=get_option($k);update_option($k,$v);}
$service=new DeviceProductService(new WooDeviceProductStore(),new SaveProductConditionHandler(new WooProductConditionRepository(),new DeviceCatalogRepository()),new WordPressModelSpecifications());
$token=substr(wp_generate_uuid4(),0,8);
try {
    $ok(WC_Szamlazz()->get_option('afakulcs')==='','Global invoice tax override absent');
    $id=$service->save(new SaveDeviceProductCommand(0,['device_model'=>'iphone_13_pro','storage_capacity'=>'128_gb','color'=>'sierra_blue','overall_grade'=>'a','status'=>'draft','regular_price'=>'100000','sku'=>'QA-TAX-'.$token,'imei'=>'QA-IMEI-'.$token,'serial_number'=>'QA-SERIAL-'.$token]));$ids[]=$id;$phone=wc_get_product($id);
    $ok($phone->get_tax_class()===ApprovedMixedTaxConfiguration::IPHONE,'Shared DeviceProductService creates explicit native K.AFA class');
    $configuredRates=WC_Tax::get_rates_for_tax_class($phone->get_tax_class());$rate=reset($configuredRates);
    $ok($rate->tax_rate_name==='K.AFA' && (float)$rate->tax_rate===0.0,'Native dedicated zero rate maps to margin label');
    $products=[$phone];
    foreach([['case','Prémium tok','8000'],['glass','Prémium üvegfólia','7000']]as[$group,$name,$price]) {
        $p=new WC_Product_Simple();$p->set_name($name);$p->set_status('draft');$p->set_regular_price($price);$p->update_meta_data('_appleklinika_protection_group',$group);
        if($group==='case')$p->set_tax_class(ApprovedMixedTaxConfiguration::IPHONE); // accidental inherited parent class
        $p->save();$ids[]=$p->get_id();$products[]=$p;
        $ok($p->get_tax_class()===ApprovedMixedTaxConfiguration::ACCESSORY && $p->get_price()===$price,'New accessory is native 27%, gross price unchanged, no inherited margin class');
    }
    $order=wc_create_order();$order->set_created_via('local-red-team-tax');$order->set_billing_country('HU');$order->set_shipping_country('HU');
    foreach($products as$p)$order->add_product($p,1);
    $order->calculate_totals();$order->save();
    $ok(count($order->get_items())===3 && abs((float)$order->get_total()-115000)<0.01,'Three distinct native order lines, gross total 115000');
    $vendor=file_get_contents(WP_PLUGIN_DIR.'/integration-for-szamlazzhu-woocommerce/index.php');$start=strpos($vendor,'//Product items',strpos($vendor,'//Order Items'));$end=strpos($vendor,'//Shipping',$start);
    if($start===false||$end===false)throw new RuntimeException('Vendor boundary changed');$mapping=substr($vendor,$start,$end-$start);
    $xml=(function()use($order,$mapping){$szamla=new WCSzamlazzSimpleXMLElement('<szamla/>');$szamla->addChild('fejlec')->addChild('megjegyzes','');$tetelek=$szamla->addChild('tetelek');$vevo=new WCSzamlazzSimpleXMLElement('<vevo/>');$order_items=$order->get_items();$rounding=0;$invoice_line_items=[];eval($mapping);return $szamla;})->call(WC_Szamlazz());
    $xml=(new DeviceInvoiceMapping())->document($xml,$order,'invoice');
    $ok((string)$xml->fejlec->megjegyzes==='használt cikk - különbözet szerinti szabályozás','Exact approved invoice-level note');
    $lines=[];foreach($xml->tetelek->tetel as$line){$i=count($lines);$gross=(float)$line->bruttoErtek;$net=(float)$line->nettoErtek;$vat=(float)$line->afaErtek;$note=(string)$line->megjegyzes;
        $ok((string)$line->afakulcs===($i===0?'K.AFA':'27'),'Actual vendor per-line tax mapping');
        $ok(abs($gross-[100000,8000,7000][$i])<0.01 && abs($net+$vat-$gross)<0.01,'Native net/VAT/gross correct');
        $ok($i===0 ? str_contains($note,'IMEI: QA-IMEI-'.$token)&&str_contains($note,'Sorozatszám: QA-SERIAL-'.$token) : !str_contains($note,'IMEI:')&&!str_contains($note,'Sorozatszám:'),'Identity only on sold device line');
        $lines[]=['tax'=>(string)$line->afakulcs,'net'=>$net,'vat'=>$vat,'gross'=>$gross];
    }
    $phone->update_meta_data('_appleklinika_imei','changed');$phone->save();$item=array_values($order->get_items())[0];
    (new DeviceInvoiceMapping())->line($xml->tetelek->tetel[0],$item);
    $ok(str_contains((string)$xml->tetelek->tetel[0]->megjegyzes,'QA-IMEI-'.$token),'Sold identity snapshot survives later product edit');
    $phone->set_tax_class('');$phone->save();$service->save(new SaveDeviceProductCommand($id,['regular_price'=>'99000']));
    $ok(wc_get_product($id)->get_tax_class()==='','Existing explicit standard class preserved, no bulk migration');
    foreach(['ipad','macbook','apple_watch']as$type){$p=new WC_Product_Simple();(new ApprovedMixedTaxConfiguration())->defaultForNewDevice($p,$type);$ok($p->get_tax_class()==='','No unapproved tax rule for '.$type);}
    $p=new WC_Product_Simple();$p->set_tax_class(ApprovedMixedTaxConfiguration::ACCESSORY);(new ApprovedMixedTaxConfiguration())->defaultForNewDevice($p,'iphone');$ok($p->get_tax_class()===ApprovedMixedTaxConfiguration::ACCESSORY,'Explicit new-product custom tax remains supported');
    $ok($http===0&&$mail===0,'No provider request or customer email');
} finally {
    if($order)$order->delete(true);foreach($ids as$id){$p=wc_get_product($id);if($p)$p->delete(true);}
    foreach($rateIds as$slug=>$old){foreach(WC_Tax::get_rates_for_tax_class($slug)as$id=>$rate)if(!in_array($id,$old))WC_Tax::_delete_tax_rate($id);if(!in_array($slug,$classes))WC_Tax::delete_tax_class_by('slug',$slug);}
    foreach($options as$k=>$v)update_option($k,$v);
}
$after=get_posts(['post_type'=>'product','post_status'=>array_keys(get_post_stati()),'fields'=>'ids','numberposts'=>-1]);sort($after);$ok($beforeProducts===$after,'All disposable products cleaned, original catalog preserved');
echo json_encode(['assertions'=>$n,'invoice_lines'=>$lines,'provider_calls'=>$http,'email_calls'=>$mail,'cleanup'=>'PASS'],JSON_PRETTY_PRINT),"\n";
