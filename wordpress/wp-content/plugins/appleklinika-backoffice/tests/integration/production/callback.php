<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/wp-load.php';
if(PHP_SAPI!=='cli'||DB_NAME!=='ak_production_fixture'||!AK_PRODUCTION_SIMULATION||!WP_HTTP_BLOCK_EXTERNAL)exit(2);
require '/source/wordpress/wp-content/plugins/pay-via-barion-for-woocommerce/includes/class-wc-gateway-barion-ipn-handler.php';
class PaymentStatus {const Succeeded='Succeeded';}
$id=(int)($argv[1]??0);$_GET=['paymentId'=>'fixture-'.$id];
$client=new class($id){function __construct(private int $id){}function GetPaymentState($paymentId){
 \Appleklinika\BackOffice\Infrastructure\ProductionTransportGuard::barion('https://api.barion.com/v2/Payment/GetPaymentState','fixture-live-key');
 return (object)['Errors'=>[],'PaymentRequestId'=>(string)$this->id,'Status'=>'Succeeded','Transactions'=>[(object)['POSTransactionId'=>(string)$this->id,'TransactionId'=>'fixture-tx-'.$this->id]]];
}};
(new WC_Gateway_Barion_IPN_Handler($client,new WC_Gateway_Barion()))->check_barion_ipn();
