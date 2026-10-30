<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Domain/PaymentStockSafety.php';
require dirname(__DIR__).'/src/Application/Port/PaymentStockStore.php';
require dirname(__DIR__).'/src/Application/AdmitDevicePayment.php';
use Appleklinika\BackOffice\Domain\PaymentStockSafety as Policy;
$count=0;$check=static function($ok)use(&$count){++$count;if(!$ok)throw new RuntimeException('Stock safety assertion '.$count);};
$line=['exists'=>true,'managed'=>true,'backorders'=>false,'required'=>1,'stock'=>1,'held_elsewhere'=>0];
$check(!Policy::conflict([$line]));foreach(['exists'=>false,'managed'=>false,'backorders'=>true,'required'=>2,'stock'=>0,'held_elsewhere'=>1]as$key=>$value)$check(Policy::conflict([array_replace($line,[$key=>$value])]));
$store=new class implements Appleklinika\BackOffice\Application\Port\PaymentStockStore {
 public ?array $state;public int $reservations=0;public array $exceptions=[];public bool $fail=false;
 public function lockAndRead(int $id):?array{return $this->state;}
 public function reserve(int $id):void{if($this->fail)throw new RuntimeException('native_conflict');$this->reservations++;}
 public function exception(int $id,string $transaction,string $reason):void{$this->exceptions[]=$reason;}
};$app=new Appleklinika\BackOffice\Application\AdmitDevicePayment($store);
$store->state=['paid'=>false,'reduced'=>false,'exception'=>false,'lines'=>[$line]];$app->execute(1,'fixture');$check($store->reservations===1);
foreach(['paid','reduced']as$key){$store->state[$key]=true;$app->execute(1,'fixture');$check($store->reservations===1);$store->state[$key]=false;}
$store->state['exception']=true;try{$app->execute(1,'fixture');$check(false);}catch(RuntimeException){$check(end($store->exceptions)==='payment_stock_conflict');}
$store->state['exception']=false;$store->fail=true;try{$app->execute(1,'fixture');$check(false);}catch(RuntimeException){$check(end($store->exceptions)==='payment_stock_recheck_required');}
$store->state=null;$app->execute(1,'fixture');$check($store->reservations===1);
echo "Payment stock safety: $count assertions passed; no database/network.\n";
