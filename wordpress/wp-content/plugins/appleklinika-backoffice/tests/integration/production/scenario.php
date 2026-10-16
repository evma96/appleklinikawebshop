<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/wp-load.php';
if(PHP_SAPI!=='cli'||DB_NAME!=='ak_production_fixture'||!AK_PRODUCTION_SIMULATION||!WP_HTTP_BLOCK_EXTERNAL||wp_get_environment_type()!=='production')exit(2);
use Appleklinika\BackOffice\Infrastructure\{ProductionReadiness as R, LifecycleConfiguration, WooOrderBackOfficeRepository, WooFulfilmentStore, WooOrderMutex, CashPickup, WooOrderLifecycleStore};
use Appleklinika\BackOffice\Application\ChangeFulfilment;
use Appleklinika\BackOffice\Interfaces\{BackOfficeRouter,FulfilmentAdmin,CashPickupHooks};
$n=0;$ok=static function($pass,$why)use(&$n){++$n;if(!$pass)throw new RuntimeException($why);};
$write=static function($c){file_put_contents(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,json_encode($c));chmod(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,0600);};
$ok(!R::allowed()&&!wp_mail('qa@example.invalid','Blocked','Blocked'),'A: production missing activation sends nothing');
$c=['schema'=>1,'environment'=>'production','enabled'=>false,'activated_at'=>0,'storefront'=>'https://shop.example.invalid','backoffice'=>'https://staff.example.invalid','approved_staff_ids'=>[1],
 'szamlazz_live_account_and_site_license_verified'=>true,'smtp_sender_authorized'=>true,
 'operations'=>['environment'=>'production','storefront'=>'https://shop.example.invalid','backup_destination'=>'independent-box/production','monitor_target'=>'shop.example.invalid','monitor_recipient'=>'operator@example.invalid',
 'recovery_key_independent'=>true,'cron_verified'=>true,'backup_verified_at'=>time(),'restore_verified_at'=>time(),'monitor_verified_at'=>time()]];
foreach(R::identities()as$name=>$values)$c['approved_fingerprints'][$name]=R::fingerprint($name,$values);
$write($c);$report=R::report(true);file_put_contents('/control/preflight.json',json_encode($report,JSON_PRETTY_PRINT));
$ok($report['ready'] && $report['activation']==='OFF','Prelaunch capabilities and actual empty Woo database ready');
$c['launch_evidence']=R::launchEvidence();$c['activated_at']=time();$c['enabled']=true;$write($c);
$ok(R::allowed(),'C: explicit production fixture activation only');
$buyer=wp_insert_user(['user_login'=>'fixture_buyer','user_pass'=>wp_generate_password(40),'user_email'=>'buyer@example.invalid','role'=>'customer']);
$p=new WC_Product_Simple();$p->set_name('Offline fixture device');$p->set_regular_price('1000');$p->set_manage_stock(true);$p->set_stock_quantity(5);$p->save();
$newOrder=static function($method,$shipping)use($buyer,$p){$o=wc_create_order(['customer_id'=>$buyer]);$o->set_created_via('checkout');$o->set_payment_method($method);$o->set_billing_email('buyer@example.invalid');$o->set_billing_first_name('Ágnes');$o->add_product($p,1);$s=new WC_Order_Item_Shipping();$s->set_method_id($shipping);$s->set_method_title($shipping);$o->add_item($s);$o->calculate_totals();$o->save();return $o;};
$mail=static fn()=>array_values(array_filter(get_option('fixture_mail',[]),static fn($m)=>$m['to']==='buyer@example.invalid'));
$o=$newOrder('barion','gls_shipping_method');$id=$o->get_id();
$bad=$c;unset($bad['approved_fingerprints']['gls']);$write($bad);
do_action('woocommerce_checkout_order_processed',$id,[],$o);
try{$o->payment_complete('blocked');$ok(false,'B: missing capability must stop before payment');}catch(RuntimeException){$ok(true,'B: blocked before payment');}
$ok(!$o->is_paid()&&wc_get_product($p->get_id())->get_stock_quantity()===5&&$mail()===[]&&(int)get_option('fixture_invoice_calls',0)===0,'B: no partial mail/invoice/stock effect');
$write($c);
for($i=0;$i<3;$i++)do_action('woocommerce_checkout_order_processed',$id,[],$o);
$ok(count($mail())===1&&$mail()[0]['subject']==='Rendelésed beérkezett','C: one acknowledgement');
$ok($mail()[0]['attachments']===[]&&!str_contains($mail()[0]['message'],'Fizetve'),'C: acknowledgement unpaid and no PDF');
// Run the REAL Barion IPN handler in a separate offline PHP process, including its exit paths.
for($i=0;$i<3;$i++){
 $proc=proc_open([PHP_BINARY,'/fixtures/callback.php',(string)$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
 $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 $ok(proc_close($proc)===0&&!str_contains($errors,'Fatal error'),'C: native Barion callback/replay exits safely');
}
wp_cache_flush();$fresh=wc_get_order($id);
$ok($fresh->has_status('processing')&&$fresh->is_paid(),'C: real Woo paid state');
$ok((int)get_option('fixture_invoice_calls',0)===1,'C: real invoice adapter invoked fake provider once');
for($i=0;$i<3;$i++){do_action('woocommerce_payment_complete',$id);do_action('appleklinika_lifecycle_dispatch',$id,'paid_invoice');}
$ok(count($mail())===2&&$mail()[1]['subject']==='Rendelésed visszaigazoltuk'&&count($mail()[1]['attachments'])===1,'C: one acceptance with PDF');
$ok(wc_get_product($p->get_id())->get_stock_quantity()===4,'C: actual Woo stock deducted exactly once');
$repo=new WooOrderBackOfficeRepository();$change=new ChangeFulfilment(new WooFulfilmentStore($repo),new WooOrderMutex());
foreach(['new'=>'start','preparation'=>'start_packing','packing'=>'packing_completed']as$state=>$action)$change->execute($id,$action,1,$state);
$change->execute($id,'create_label',1,'ready_for_shipping');
try{$change->execute($id,'create_label',1,'ready_for_shipping');$ok(false,'Duplicate label');}catch(InvalidArgumentException){$ok(true,'C: label retry rejected');}
$ok(count($mail())===2&&(int)get_option('fixture_gls_calls',0)===1,'C: packing/label silent; one fake GLS parcel');
do_action('appleklinika_lifecycle_dispatch',$id,'carrier_handoff');$ok(count($mail())===2,'No handoff mail from label alone');
$change->execute($id,'handed_to_gls',1,'ready_for_shipping');
for($i=0;$i<3;$i++)do_action('appleklinika_lifecycle_dispatch',$id,'carrier_handoff');
$ok(count($mail())===3&&$mail()[2]['subject']==='Úton van a rendelésed!'&&str_contains($mail()[2]['message'],'12345678'),'C: single actual handoff email/tracking');
$ok(wc_get_order($id)->has_status('processing'),'Completed remains separate after handoff');
$change->execute($id,'delivered',1,'handed_to_gls');
$change->execute($id,'correct',1,'delivered','ready_for_shipping','Offline correction');
$change->execute($id,'handed_to_gls',1,'ready_for_shipping');do_action('appleklinika_lifecycle_dispatch',$id,'carrier_handoff');
$ok(count($mail())===3&&(int)get_option('fixture_invoice_calls',0)===1&&(int)get_option('fixture_gls_calls',0)===1,'C: correction/replay has no duplicate effects');
wp_set_current_user(1);ob_start();(new FulfilmentAdmin($change,$repo))->render(wc_get_order($id));$admin=ob_get_clean();
$ok(str_contains($admin,'name="operation"')&&!str_contains($admin,'teljesítés – TEST'),'C: production Woo-admin correction UI available without TEST label');
$history=$repo->history(wc_get_order($id));$ok(in_array('correct',array_column($history,'action'),true),'C: audit records correction');
wp_set_current_user((int)$buyer);add_filter('woocommerce_is_account_page','__return_true');ob_start();(new BackOfficeRouter($repo,$change))->renderCustomerProgress(wc_get_order($id));$html=ob_get_clean();
$ok($html !== '' && !str_contains($html,'Offline correction') && (new \Appleklinika\BackOffice\Infrastructure\OrderDocuments())->trackingLinks(wc_get_order($id))[0]['code']==='12345678','C: account shares tracking, hides private correction');
// Cash uses the same actual Woo stock and payment hooks; provider boundaries remain fakes.
$cash=$newOrder('cod','local_pickup');$cid=$cash->get_id();do_action('woocommerce_checkout_order_processed',$cid,[],$cash);
WC()->initialize_session();WC()->initialize_cart();
$gateway=WC()->payment_gateways()->payment_gateways()['cod'];$gateway->process_payment($cid);
$ok(!wc_get_order($cid)->is_paid()&&wc_get_order($cid)->has_status('on-hold'),'D: cash remains unpaid');
$ok((int)get_option('fixture_invoice_calls',0)===1&&wc_get_product($p->get_id())->get_stock_quantity()===3,'D: one reservation, no cash invoice yet');
$change->execute($cid,'accept_cash_pickup',1,'new');do_action('appleklinika_lifecycle_dispatch',$cid,'paid_invoice');
$messages=$mail();$accepted=end($messages);
$ok(count($mail())===5&&$accepted['attachments']===[]&&!str_contains($accepted['message'],'Fizetve')&&!str_contains($accepted['message'],'fizetésed sikeresen'),'D: explicit acceptance, no false paid/PDF claim');
$ok(!wc_get_order($cid)->is_paid()&&(int)get_option('fixture_invoice_calls',0)===1,'D: acceptance is not payment');
$change->execute($cid,'prepare_pickup',1,'preparation');$change->execute($cid,'record_cash_pickup',1,'ready_for_pickup');
for($i=0;$i<3;$i++){do_action('woocommerce_payment_complete',$cid);do_action('appleklinika_lifecycle_dispatch',$cid,'paid_invoice');}
$ok(CashPickup::paymentRecorded(wc_get_order($cid))&&wc_get_order($cid)->is_paid()&&(int)get_option('fixture_invoice_calls',0)===2,'D: exactly one invoice only at recorded pickup payment');
$ok(count($mail())===5&&wc_get_product($p->get_id())->get_stock_quantity()===3,'D: no duplicate acceptance or stock');
WC()->session->set('chosen_shipping_methods',['gls_shipping_method:1']);$gateways=apply_filters('woocommerce_available_payment_gateways',['barion'=>new stdClass(),'bacs'=>new stdClass(),'cod'=>$gateway]);
$ok(isset($gateways['barion'])&&!isset($gateways['bacs'])&&!isset($gateways['cod']),'E: courier Barion only');
WC()->session->set('chosen_shipping_methods',['local_pickup:2']);$gateways=apply_filters('woocommerce_available_payment_gateways',['barion'=>new stdClass(),'bacs'=>new stdClass(),'cod'=>$gateway]);
$ok(isset($gateways['cod'])&&!isset($gateways['bacs']),'E: pickup cash available, BACS absent');
$ok(($GLOBALS['fixture_network_attempts']??0)===0,'No real HTTP attempt from the lifecycle');
$dirty=R::report(true);$ok(!$dirty['ready'] && $dirty['counts']['products']===1 && $dirty['counts']['orders']===2 && $dirty['counts']['customers']===1,'Actual post-launch Woo data refuses a new empty-launch certificate');
$c['enabled']=false;$write($c);$ok(!R::allowed()&&!wp_mail('buyer@example.invalid','Emergency blocked','Blocked'),'Emergency OFF stops mail after activation');
file_put_contents('/control/results.json',json_encode(['assertions'=>$n,'mail_count'=>count($mail()),'invoices'=>get_option('fixture_invoice_calls'),'gls'=>get_option('fixture_gls_calls'),'network_attempts'=>$GLOBALS['fixture_network_attempts']??0],JSON_PRETTY_PRINT));
echo "Offline production Woo/HPOS lifecycle: $n assertions passed; real Woo state/stock/IPN handler; fake providers; no delivery claim.\n";
