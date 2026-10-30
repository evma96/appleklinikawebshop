<?php
/** Approved mixed tax mapping through the installed vendor's real line builder, no transport. */
declare(strict_types=1);
require dirname(__DIR__, 4) . '/wp-load.php';
if (PHP_SAPI !== 'cli' || wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1') throw new RuntimeException('LOCAL only');
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceInvoiceMapping;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
$count=$http=$mail=0;$products=[];$order=null;$rates=[];$classes=[];$before=[];$accessoryBefore=[];$setupRates=[];$setupClasses=[];
$check=static function(bool $ok,string $label)use(&$count){++$count;if(!$ok)throw new RuntimeException($label);};
add_filter('pre_http_request',static function()use(&$http){++$http;return new WP_Error('blocked');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail){++$mail;return false;},PHP_INT_MAX);
foreach(['woocommerce_default_country'=>'HU','woocommerce_calc_taxes'=>'yes','woocommerce_prices_include_tax'=>'yes','woocommerce_tax_display_shop'=>'incl','woocommerce_tax_display_cart'=>'incl']as$key=>$value){$before[$key]=get_option($key);update_option($key,$value);}
WC()->session=new class extends WC_Session {public function has_session(){return false;}public function set_customer_session_cookie($s){}public function get_session_cookie(){return false;}};
WC()->customer=new WC_Customer(0);WC()->customer->set_billing_country('HU');WC()->customer->set_shipping_country('HU');WC()->cart=new WC_Cart();
try{
 $check(WC_Szamlazz()->get_option('afakulcs')==='','No global VAT override');
 foreach(['ak-qa-margin'=>['AK QA margin','0','K.AFA'],'ak-qa-normal'=>['AK QA normal','27','27%']]as$slug=>$row){
  $result=WC_Tax::create_tax_class($row[0],$slug);if(is_wp_error($result))throw new RuntimeException($result->get_error_message());$classes[]=$slug;
  $rates[]=WC_Tax::_insert_tax_rate(['tax_rate_country'=>'HU','tax_rate_state'=>'','tax_rate'=>$row[1],'tax_rate_name'=>$row[2],'tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>0,'tax_rate_order'=>0,'tax_rate_class'=>$slug]);
 }
 foreach([['LOCAL used iPhone','100000','ak-qa-margin',''],['Prémium tok','8000','ak-qa-normal','case'],['Prémium üvegfólia','7000','ak-qa-normal','glass']]as$spec){
  $p=new WC_Product_Simple();$p->set_name($spec[0]);$p->set_regular_price($spec[1]);$p->set_status('publish');$p->set_catalog_visibility('hidden');$p->set_tax_status('taxable');$p->set_tax_class($spec[2]);
  if($spec[3]===''){$p->update_meta_data('_appleklinika_device_model','iphone_13_pro');$p->update_meta_data('_appleklinika_device_type','iphone');$p->update_meta_data('_appleklinika_imei','LOCAL-QA-IMEI-20261030');$p->update_meta_data('_appleklinika_serial_number','LOCAL-QA-SERIAL-20261030');}
  else $p->update_meta_data('_appleklinika_protection_group',$spec[3]);
  $p->save();$products[]=$p;
 }
 $parent=WC()->cart->add_to_cart($products[0]->get_id());
 foreach(array_slice($products,1)as$p)WC()->cart->add_to_cart($p->get_id(),1,0,[],['_ak_protection_parent_key'=>$parent]);
 WC()->cart->calculate_totals();
 $order=(new Automattic\WooCommerce\StoreApi\Utilities\OrderController())->create_order_from_cart();
 $check((float)$order->get_total()===115000.0,'Gross catalog prices retained without adding 27%: '.json_encode([$order->get_total(),WC()->cart->get_totals(),get_option('woocommerce_default_country')]));
 $check(count($order->get_items())===3,'Three native lines');
 $vendor=file_get_contents(WP_PLUGIN_DIR.'/integration-for-szamlazzhu-woocommerce/index.php');$start=strpos($vendor,'//Product items',strpos($vendor,'//Order Items'));$end=strpos($vendor,'//Shipping',$start);if($start===false||$end===false)throw new RuntimeException('Vendor boundary changed');$mapping=substr($vendor,$start,$end-$start);
 $xml=(function()use($order,$mapping){$szamla=new WCSzamlazzSimpleXMLElement('<szamla/>');$szamla->addChild('fejlec')->addChild('megjegyzes','');$tetelek=$szamla->addChild('tetelek');$vevo=new WCSzamlazzSimpleXMLElement('<vevo/>');$order_items=$order->get_items();$rounding=0;$invoice_line_items=[];eval($mapping);return $szamla;})->call(WC_Szamlazz());
 $xml=(new DeviceInvoiceMapping())->document($xml,$order,'invoice');
 $check((string)$xml->fejlec->megjegyzes==='használt cikk - különbözet szerinti szabályozás','Exact approved lowercase general note');
 $sum=0;$result=[];
 foreach($xml->tetelek->tetel as$i=>$line){$index=count($result);$expectedGross=[100000,8000,7000][$index];$vat=(string)$line->afakulcs;$gross=(float)(string)$line->bruttoErtek;$net=(float)(string)$line->nettoErtek;$tax=(float)(string)$line->afaErtek;$note=(string)$line->megjegyzes;
  $check($vat===($index===0?'K.AFA':'27'),'Per-line VAT label');$check(abs($gross-$expectedGross)<0.01,'Per-line original gross');$check(abs($net+$tax-$gross)<0.01,'Net plus VAT equals gross');
  if($index===0){$check(str_contains($note,'IMEI: LOCAL-QA-IMEI-20261030')&&str_contains($note,'Sorozatszám: LOCAL-QA-SERIAL-20261030'),'Identifiers on exact device line');$check($tax===0.0,'Margin scheme not normal VAT');}
  else{$check(!str_contains($note,'IMEI:')&&!str_contains($note,'Sorozatszám:'),'No device identifier on accessory');$check(abs($net-round($gross/1.27,0))<1.01,'Correct 27 percent net breakdown');}
  $sum+=$gross;$result[]=['vat'=>$vat,'net'=>$net,'vat_amount'=>$tax,'gross'=>$gross];
 }
 $check($sum===(float)$order->get_total(),'Invoice gross equals payment order amount');
 $products[0]->update_meta_data('_appleklinika_imei','changed-after-sale');$products[0]->save();
 $item=array_values($order->get_items())[0];$line=$xml->tetelek->tetel[0];(new DeviceInvoiceMapping())->line($line,$item);
 $check(str_contains((string)$line->megjegyzes,'IMEI: LOCAL-QA-IMEI-20261030')&&!str_contains((string)$line->megjegyzes,'changed-after-sale'),'Immutable sold snapshot');
 $products[0]->update_meta_data('_ak_mixed_invoice_qa','20261030');$products[0]->save();
 foreach(get_posts(['post_type'=>'product','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_appleklinika_protection_group'])as$id){$p=wc_get_product($id);$accessoryBefore[$id]=[$p->get_tax_status(),$p->get_tax_class(),$p->get_price()];}
 foreach([\Appleklinika\Inventory\Infrastructure\WordPress\ApprovedMixedTaxConfiguration::IPHONE,\Appleklinika\Inventory\Infrastructure\WordPress\ApprovedMixedTaxConfiguration::ACCESSORY]as$slug){if(WC_Tax::get_rates_for_tax_class($slug))throw new RuntimeException('Setup test expects disposable classes absent');$setupClasses[]=$slug;}
 $setup=new \Appleklinika\Inventory\Infrastructure\WordPress\ApprovedMixedTaxConfiguration();
 $first=$setup->apply($products[0]->get_id());$second=$setup->apply($products[0]->get_id());
 $check($first===$second,'Explicit approved setup idempotent');
 foreach($setupClasses as$slug){$configured=WC_Tax::get_rates_for_tax_class($slug);$check(count($configured)===1,'One native rate per dedicated class');foreach($configured as$id=>$r)$setupRates[]=$id;}
 foreach($first['accessories']as$id){$p=wc_get_product($id);$check($p->get_tax_class()===\Appleklinika\Inventory\Infrastructure\WordPress\ApprovedMixedTaxConfiguration::ACCESSORY&&$p->get_price()===$accessoryBefore[$id][2],'Every accessory is 27 percent without retail price mutation');}
 $check($http===0&&$mail===0,'No invoice transport/mail attempted');
 echo json_encode(['checks'=>$count,'lines'=>$result,'total'=>$sum],JSON_PRETTY_PRINT),"\n";
}finally{
 foreach($accessoryBefore as$id=>$data){$p=wc_get_product($id);if($p){$p->set_tax_status($data[0]);$p->set_tax_class($data[1]);$p->save();}}
 foreach($setupClasses as$slug){foreach(WC_Tax::get_rates_for_tax_class($slug)as$id=>$r)WC_Tax::_delete_tax_rate($id);WC_Tax::delete_tax_class_by('slug',$slug);}
 WC()->cart->empty_cart(false);if($order)$order->delete(true);foreach($products as$p)$p->delete(true);foreach($rates as$id)WC_Tax::_delete_tax_rate($id);foreach($classes as$slug)WC_Tax::delete_tax_class_by('slug',$slug);foreach($before as$key=>$value)update_option($key,$value);
}
