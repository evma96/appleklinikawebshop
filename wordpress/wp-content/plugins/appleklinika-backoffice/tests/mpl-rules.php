<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/src/Domain/MplParcelRules.php';
require_once dirname(__DIR__).'/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__).'/src/Domain/FulfilmentWorkflow.php';
use Appleklinika\BackOffice\Domain\{MplParcelRules as R, DeliveryMode as M, FulfilmentWorkflow as F};
$count=0; $check=static function($condition,$message) use (&$count) { if (!$condition) { throw new RuntimeException($message); } ++$count; };
$rates=['home_10'=>2090,'home_20'=>3140,'home_40'=>6300];
foreach ([0=>null,1=>2090.0,10=>2090.0,11=>3140.0,20=>3140.0,21=>6300.0,40=>6300.0,41=>null] as $kg=>$price) { $check(R::price((float)$kg,$rates)===$price,'Weight band '.$kg); }
$check(R::price(10.001,$rates)===3140.0,'No gap after 10 kg');
$check(R::price(20.001,$rates)===6300.0,'No gap after 20 kg');
$check(R::price(1,['home_10'=>-1])===null,'Invalid rate fails closed');
$check(R::eligible('postapont_automata',20,[50,31,35],500000),'Locker boundaries');
$check(!R::eligible('postapont_automata',20.01,[50,31,35]),'Locker overweight');
$check(!R::eligible('postapont_automata',1,[49,40,30]),'Locker dimension cannot be replaced by volume');
$check(!R::eligible('postapont_automata',1,[49,30,30],500001),'Locker value limit');
$check(R::eligible('postapont_posta',30,[120,60,60]),'Post office 30 kg');
$check(!R::eligible('postapont_posta',30.01,[20,20,20]),'Post office overweight');
$check(!R::eligible('postapont_postapont',20.01,[20,20,20]),'Partner point 20 kg');
$check(!R::eligible('home',0,[20,20,20]),'Unknown weight not zero-priced parcel');
$check(!R::eligible('home',1,[]),'Unknown dimensions require catalog preparation');
$check(F::primaryAction(F::READY_FOR_SHIPPING,M::MPL,false)==='create_mpl_label','MPL label action');
$check(F::primaryAction(F::READY_FOR_SHIPPING,M::MPL,true)==='handed_to_carrier','MPL handoff separate');
$check(F::transition(F::READY_FOR_SHIPPING,'create_mpl_label',M::MPL)===F::READY_FOR_SHIPPING,'Label creation is not handoff');
$check(F::transition(F::READY_FOR_SHIPPING,'handed_to_carrier',M::MPL)===F::HANDED_TO_CARRIER,'MPL uses shared state machine');
$check(F::transition(F::HANDED_TO_CARRIER,'delivered',M::MPL)===F::DELIVERED,'Delivery separate closure');
$check(F::primaryAction(F::READY_FOR_SHIPPING,M::GLS,true)==='handed_to_gls','GLS stays unchanged');
foreach ([[M::MPL,'handed_to_gls'],[M::GLS,'handed_to_carrier'],[M::PICKUP,'create_mpl_label']] as [$mode,$action]) {
    try { F::transition(F::READY_FOR_SHIPPING,$action,$mode); $rejected=false; } catch (InvalidArgumentException) { $rejected=true; }
    $check($rejected,'Wrong carrier action rejected');
}
$check(R::eligibleForManualDispatch('home',0,[],120000),'Manual unknown parcel can be priced without inventing weight');
$check(R::eligibleForManualDispatch('postapont_automata',0,[],500000),'Manual locker unknown dimensions require staff review');
$check(!R::eligibleForManualDispatch('postapont_automata',0,[],500001),'Unknown measurements do not erase known locker value limit');
$check(!R::eligibleForManualDispatch('home',41,[]),'Manual known overweight rejected');
$check(!R::eligibleForManualDispatch('postapont_automata',0,[51,0,0]),'Partial known oversize still rejected');
$check(!R::eligibleForManualDispatch('postapont_postapont',21,[]),'Manual partner weight constraint preserved');
$check(!R::eligibleForManualDispatch('other',0,[]),'Unknown service rejected');
$check(!R::eligibleForManualDispatch('home',0,[-1,1,1]),'Invalid known dimensions rejected');
echo "MPL tariff/fulfilment: $count assertions passed.\n";
