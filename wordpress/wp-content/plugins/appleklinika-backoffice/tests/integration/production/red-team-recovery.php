<?php
// Included only by the disposable internal-network production simulation.
use Appleklinika\BackOffice\Infrastructure\{BarionPaymentReview, WooArtifactReconciliation, WooOrderReview, WooPaymentStockStore};
if (!defined('AK_PRODUCTION_SIMULATION') || !AK_PRODUCTION_SIMULATION || DB_NAME!=='ak_production_fixture') exit(2);
wp_set_current_user(1);
$ok(WooArtifactReconciliation::enabled() && WooOrderReview::reviewEnvironment(), 'Production recovery enabled only under validated provider activation');
$gateway=new WC_Gateway_Barion();
$client=new class {
    public int $id=0; public string $state='Succeeded'; public bool $outage=false;
    public function GetPaymentState($paymentId) {
        if ($this->outage) throw new RuntimeException('OFFLINE provider unavailable');
        return (object)['Errors'=>[], 'PaymentId'=>'fixture-'.$this->id, 'PaymentRequestId'=>(string)$this->id,
            'Total'=>1000, 'Currency'=>'HUF', 'Status'=>$this->state,
            'Transactions'=>[(object)['POSTransactionId'=>(string)$this->id, 'TransactionId'=>'verified-fixture-'.$this->id]]];
    }
};
BarionPaymentReview::capture($client,$gateway);
$r=$newOrder('barion','gls_shipping_method');$rid=$r->get_id();$client->id=$rid;
do_action('woocommerce_checkout_order_processed',$rid,[],$r);
$invoiceBefore=(int)get_option('fixture_invoice_calls',0);
$stockBefore=wc_get_product($p->get_id())->get_stock_quantity();
$client->outage=true;BarionPaymentReview::recheck($r);
$ok(!wc_get_order($rid)->is_paid() && (int)get_option('fixture_invoice_calls',0)===$invoiceBefore,'Provider outage does not fabricate paid/invoice');
$client->outage=false;$client->state='Failed';BarionPaymentReview::recheck($r);
$ok(!wc_get_order($rid)->is_paid(),'Verified unsuccessful payment remains unpaid');
$client->state='Succeeded';$r->update_meta_data('_barion_payment_close',1);$r->save_meta_data();
BarionPaymentReview::recheck($r);BarionPaymentReview::recheck(wc_get_order($rid));
$ok(wc_get_order($rid)->is_paid() && (int)get_option('fixture_invoice_calls',0)===$invoiceBefore+1
    && wc_get_product($p->get_id())->get_stock_quantity()===$stockBefore-1,'Lost callback / interrupted close marker reconciles once with verified provider state');
// Durable invoice exists, but its local completion/event was interrupted.
$r=wc_get_order($rid);$r->update_meta_data('_appleklinika_lifecycle_invoice',['state'=>'generating']);$r->save_meta_data();
for($i=0;$i<3;$i++)do_action(WooArtifactReconciliation::WORK,$rid);
$r=wc_get_order($rid);
$ok(($r->get_meta('_appleklinika_lifecycle_invoice')['state']??'')==='ready'
    && ($r->get_meta(WooArtifactReconciliation::META)['invoice']['state']??'')==='done'
    && (int)get_option('fixture_invoice_calls',0)===$invoiceBefore+1,'Persisted provider result repairs completion/audit with no duplicate invoice');
// Real fulfilment proceeds despite one known SMTP rejection; shipping waits.
update_option('fixture_fail_subject','Rendelésed visszaigazoltuk');
do_action('appleklinika_lifecycle_dispatch',$rid,'paid_invoice');
foreach(['new'=>'start','preparation'=>'start_packing','packing'=>'packing_completed']as$state=>$action)$change->execute($rid,$action,1,$state);
$change->execute($rid,'handed_to_gls',1,'ready_for_shipping');
$beforeMessages=count($mail());
do_action('appleklinika_lifecycle_dispatch',$rid,'carrier_handoff');
$ok(count($mail())===$beforeMessages && $repo->state(wc_get_order($rid))==='handed_to_gls','Failed acceptance holds shipping email without blocking physical handoff');
update_option('fixture_fail_subject','');
do_action('appleklinika_lifecycle_dispatch',$rid,'paid_invoice');do_action('appleklinika_lifecycle_dispatch',$rid,'carrier_handoff');
$messages=$mail();$last=array_slice($messages,-2);
$ok(array_column($last,'subject')===['Rendelésed visszaigazoltuk','Úton van a rendelésed!'],'SMTP recovery releases accepted-order then shipping notification');
$beforeMessages=count($mail());$beforeLabels=(int)get_option('fixture_gls_calls',0);
for($i=0;$i<3;$i++) { do_action('appleklinika_lifecycle_dispatch',$rid,'paid_invoice');do_action('appleklinika_lifecycle_dispatch',$rid,'carrier_handoff');do_action(WooArtifactReconciliation::WORK,$rid); }
$ok(count($mail())===$beforeMessages && (int)get_option('fixture_gls_calls',0)===$beforeLabels,'Repeated recovery creates no email/label duplicates');
// A confirmed late capture cannot take a device reassigned to somebody else.
$device=new WC_Product_Simple();$device->set_name('Offline late-capture device');$device->set_regular_price('1000');$device->set_manage_stock(true);$device->set_stock_quantity(0);$device->update_meta_data('_appleklinika_device_type','iphone');$device->save();
$late=wc_create_order();$late->set_payment_method('barion');$late->add_product($device,1);$late->calculate_totals();$late->set_status('cancelled');$late->save();$client->id=$late->get_id();
$beforeInvoice=(int)get_option('fixture_invoice_calls',0);
BarionPaymentReview::recheck($late);BarionPaymentReview::recheck(wc_get_order($late->get_id()));
$late=wc_get_order($late->get_id());
$ok(!$late->is_paid() && $late->get_meta(WooPaymentStockStore::EXCEPTION) && wc_get_product($device->get_id())->get_stock_quantity()===0
    && (int)get_option('fixture_invoice_calls',0)===$beforeInvoice,'Late capture creates actionable stock exception, no paid state/oversell/invoice');
wp_set_current_user(0);try{BarionPaymentReview::recheck($late);$ok(false,'Anonymous recovery rejected');}catch(InvalidArgumentException){$ok(true,'Anonymous recovery denied before provider lookup');}wp_set_current_user(1);
// Unknown provider result never triggers a second issuance, in real Woo metadata too.
$uncertain=$newOrder('barion','local_pickup');$uncertain->set_status('processing');$uncertain->set_date_paid(time());$uncertain->update_meta_data('_appleklinika_lifecycle_invoice',['state'=>'uncertain']);$uncertain->save();
for($i=0;$i<3;$i++)do_action(WooArtifactReconciliation::WORK,$uncertain->get_id());
$ok((int)get_option('fixture_invoice_calls',0)===$beforeInvoice
    && (wc_get_order($uncertain->get_id())->get_meta(WooArtifactReconciliation::META)['invoice']['state']??'')==='reconciliation','Uncertain issuance requires operator/provider evidence, no automatic duplicate');
$ok(($GLOBALS['fixture_network_attempts']??0)===0,'Recovery scenarios have no external HTTP traffic');

$recover = new \Appleklinika\BackOffice\Infrastructure\ExistingInvoiceRecovery();
$uncertain=wc_get_order($uncertain->get_id());
$beforeReads=(int)get_option('fixture_pdf_reads',0);
wp_set_current_user(0);try{$recover->recover($uncertain->get_id(),'EXISTING-123',(string)$uncertain->get_order_number(),'Provider portal checked');$ok(false,'Unauthorized invoice recovery');}catch(InvalidArgumentException){$ok(true,'Invoice recovery requires authenticated operator');}wp_set_current_user(1);
try{$recover->recover($uncertain->get_id(),'EXISTING-123','wrong-order','Provider portal checked');$ok(false,'Wrong order');}catch(InvalidArgumentException){$ok(true,'Wrong provider order reference refused before PDF query');}
$ok((int)get_option('fixture_pdf_reads',0)===$beforeReads,'Rejected recovery performs no provider read');
$result=$recover->recover($uncertain->get_id(),'EXISTING-123',(string)$uncertain->get_order_number(),'Provider portal order, buyer and gross verified');
$again=$recover->recover($uncertain->get_id(),'EXISTING-123',(string)$uncertain->get_order_number(),'Provider portal order, buyer and gross verified');
$recovered=wc_get_order($uncertain->get_id());
$ok($result==='recovered_existing_pdf' && $again==='already_recovered' && (int)get_option('fixture_pdf_reads',0)===$beforeReads+1
    && (int)get_option('fixture_invoice_calls',0)===$beforeInvoice && $recovered->get_meta('_wc_szamlazz_invoice')==='EXISTING-123','Lost local invoice result recovers existing provider PDF once without issuing another invoice');
$ok(($recovered->get_meta('_appleklinika_invoice_recovery')['actor']??0)===1
    && ($recovered->get_meta('_appleklinika_lifecycle_invoice')['state']??'')==='ready','Recovered document has operator evidence and repaired lifecycle');

$recovered->update_meta_data('_appleklinika_invoice_recovery', ['state'=>'finishing','number'=>'EXISTING-123']);$recovered->save_meta_data();
$recover->recover($recovered->get_id(),'EXISTING-123',(string)$recovered->get_order_number(),'Provider portal order, buyer and gross verified');
$recovered=wc_get_order($recovered->get_id());
$ok(($recovered->get_meta('_appleklinika_invoice_recovery')['state']??'')==='complete'
    && count(array_filter($repo->history($recovered),static fn($row)=>($row['action']??'')==='existing_invoice_recovered'))===1
    && (int)get_option('fixture_pdf_reads',0)===$beforeReads+1,'Interrupted local recovery finalization repairs once without PDF/invoice duplication');
