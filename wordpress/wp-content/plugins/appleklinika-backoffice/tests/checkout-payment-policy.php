<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/src/Domain/CheckoutPaymentPolicy.php';
require dirname(__DIR__).'/src/Interfaces/CheckoutPaymentHooks.php';
use Appleklinika\BackOffice\Domain\CheckoutPaymentPolicy as P;
use Appleklinika\BackOffice\Interfaces\CheckoutPaymentHooks as H;
function WC(){return $GLOBALS['wc'];}
class WP_Error { public array $errors=[];function add($code,$message){$this->errors[$code]=$message;} }
class WC_Order {function __construct(private string $pay){}function get_payment_method(){return $this->pay;}}
$GLOBALS['wc']=(object)['session'=>new class{public array $data=[];function get($key,$default=null){return $this->data[$key]??$default;}function set($key,$value){$this->data[$key]=$value;}}];
$n=0;$check=static function($ok,$message)use(&$n){++$n;if(!$ok)throw new RuntimeException($message);};
$h=new H();
foreach(['gls_shipping_method:1','gls_shipping_method_parcel_shop:2','gls_shipping_method_parcel_locker:3','ak_mpl_home:4','vp_pont:5','local_pickup:6',''] as $shipping){
 foreach(['barion','cod','bacs','cheque','unknown',''] as $pay){
  $allowed=$pay==='barion';
  $check(P::allows($pay)===$allowed,'Policy '.$shipping.' + '.$pay);
  $errors=new WP_Error();$h->classicValidation(['payment_method'=>$pay,'shipping_method'=>[$shipping]],$errors);
  $check(empty($errors->errors)===$allowed,'Classic validation '.$shipping.' + '.$pay);
  $errors=new WP_Error();$h->orderValidation(new WC_Order($pay),$errors);
  $check(empty($errors->errors)===$allowed,'Persisted order validation '.$shipping.' + '.$pay);
 }
}
$gateways=['barion'=>(object)[],'cod'=>(object)[],'bacs'=>(object)[],'cheque'=>(object)[]];
foreach(['cod','bacs','cheque','unknown','barion'] as $old){
 WC()->session->set('chosen_payment_method',$old);
 $check(array_keys($h->gateways($gateways))===['barion']&&WC()->session->get('chosen_payment_method')==='barion','Old '.$old.' selection cannot survive.');
}
WC()->session->set('chosen_payment_method','cod');
$check($h->gateways(['cod'=>(object)[]])===[]&&WC()->session->get('chosen_payment_method')==='','No Barion: fail closed, never enable a fallback.');
WC()->session->set('chosen_payment_method','barion');
$check($h->gateways([])===[]&&WC()->session->get('chosen_payment_method')==='','Activation/availability veto stays authoritative.');
$settings=$h->disabledSettings(['enabled'=>'yes','title'=>'Staff cash label','description'=>'old','instructions'=>'old','private_legacy_value'=>'preserved']);
$check($settings['enabled']==='no'&&$settings['description']===''&&$settings['instructions']==='','Imported/stale BACS disabled.');
$check($settings['title']==='Staff cash label'&&$settings['private_legacy_value']==='preserved','Historical/admin data preserved.');
$check($h->disabledSettings(false)['enabled']==='no','Absent settings fail closed.');
WC()->session=null;
$check(array_keys($h->gateways($gateways))===['barion'],'Sessionless contexts remain safe.');
echo "Checkout payment policy: $n assertions passed.\n";
