<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__).'/src/Domain/PaymentState.php';
require_once dirname(__DIR__).'/src/Infrastructure/WooOrderPayment.php';
use Appleklinika\BackOffice\Domain\PaymentState;
use Appleklinika\BackOffice\Infrastructure\WooOrderPayment;
$n=0; $assert=static function(bool $ok,string $why)use(&$n):void{++$n;if(!$ok){throw new RuntimeException($why);}};
foreach (['gls','pickup','unknown'] as $mode) {
    foreach (['','error','mismatch','Prepared'] as $oldReview) {
        $p=PaymentState::describe('barion','processing',true,$mode,null,false,$oldReview);
        $assert($p['state']==='paid' && !$p['review'] && $p['block']===null,'Authoritative paid state wins; old review cannot re-gate paid order.');
    }
}
foreach (['pending','on-hold'] as $status) {
    foreach (['','Prepared','Started','Waiting','InProgress'] as $provider) {
        $p=PaymentState::describe('barion',$status,false,'gls',null,false,$provider);
        $assert($p['state']==='pending' && !$p['review'] && $p['block']!==null,'Awaiting customer/provider is not manual review and cannot fulfil.');
    }
}
foreach (['error','mismatch','unavailable','Succeeded','Reserved','Authorized','unknown'] as $provider) {
    $p=PaymentState::describe('barion','pending',false,'gls',null,false,$provider);
    $assert($p['review'] && $p['block']!==null,'Unsettled/inconsistent provider result needs review, never a paid override.');
}
foreach (['failed','cancelled','refunded','checkout-draft','trash'] as $status) {
    foreach (['barion','cod'] as $method) {
        $p=PaymentState::describe($method,$status,true,'gls');
        $assert($p['block']!==null && $p['state']!=='paid','Closed/error status cannot be bypassed by a paid flag.');
    }
}
foreach (['gls','pickup'] as $mode) {
    foreach (['on-hold','processing'] as $status) {
        foreach ([true,false] as $paid) {
            $p=PaymentState::describe('cod',$status,$paid,$mode);
            $assert($p['state']==='due' && !$p['review'] && $p['block']===null,'Configured COD can prepare unpaid without fabricating collection.');
        }
    }
}
$assert(PaymentState::describe('cod','processing',true,'pickup',null,true)['state']==='paid','Actual pickup collection can show paid.');
$assert(PaymentState::describe('cod','pending',false,'gls')['block']!==null,'Unsubmitted COD not dispatched.');
$assert(PaymentState::describe('cod','processing',true,'gls','Disabled method')['block']==='Disabled method','Configuration mismatch is explicit, not a payment error.');
foreach (['bacs','cheque'] as $manual) {
    $assert(PaymentState::describe($manual,'on-hold',false,'pickup')['review'],'Only actual incoming manual money requires confirmation.');
    $assert(!PaymentState::describe($manual,'processing',true,'pickup')['review'],'Already paid manual payment is not reconfirmed.');
}
foreach (['Canceled','Expired','Failed'] as $provider) {
    $p=PaymentState::describe('barion','pending',false,'gls',null,false,$provider);
    $assert($p['state']==='failed' && $p['block']!==null,'Provider failure cannot be bypassed.');
}
function get_option($key,$default=[]){return $GLOBALS['settings'] ?? $default;}
final class WC_Order {
    public string $method='cod',$shipping='gls_shipping_method',$status='processing'; public bool $paid=true;
    public function get_payment_method(){return $this->method;} public function get_status(){return $this->status;}
    public function is_paid(){return $this->paid;} public function get_meta($key,$single=true){return '';}
    public function get_shipping_methods(){return [new class($this->shipping){public function __construct(private string $id){}public function get_method_id(){return $this->id;}public function get_instance_id(){return 2;}}];}
}
$order=new WC_Order();
foreach ([[],['enabled'=>'no'],['enabled'=>'yes','enable_for_methods'=>['local_pickup']]] as $settings) {
    $GLOBALS['settings']=$settings;
    $assert(WooOrderPayment::describe($order,'gls')['block']!==null,'Unconfigured COD courier fails closed.');
}
foreach ([[],['gls_shipping_method'],['gls_shipping_method:2']] as $allowed) {
    $GLOBALS['settings']=['enabled'=>'yes','enable_for_methods'=>$allowed];
    $p=WooOrderPayment::describe($order,'gls');
    $assert($p['block']!==null && $p['state']!=='paid','Courier COD stays disabled after the owner clarified the Barion-only launch.');
}
$GLOBALS['settings']=['enabled'=>'yes','enable_for_methods'=>['gls_shipping_method:3']];
$assert(WooOrderPayment::describe($order,'gls')['block']!==null,'Wrong instance is not allowed.');
$order->shipping='local_pickup';$GLOBALS['settings']=['enabled'=>'yes','enable_for_methods'=>['local_pickup']];
$assert(WooOrderPayment::describe($order,'pickup')['block']!==null,'Legacy pickup without installed handover flow is not silently enabled.');
$assert(WooOrderPayment::describe($order,'unknown')['state']==='cod_unknown','Unknown shipping asks for shipping correction, not paid confirmation.');
echo "Payment state: $n assertions passed; no provider requests or writes.\n";
