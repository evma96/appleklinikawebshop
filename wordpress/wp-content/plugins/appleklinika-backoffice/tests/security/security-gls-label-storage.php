<?php
/** Offline regression: real WordPress direct filesystem, fixture provider/order only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', '/usr/src/wordpress/');
define('GLS_LABELS_DIR', sys_get_temp_dir().'/ak-gls-storage-'.getmypid());
function add_action(...$args) {}
function do_action(...$args) { ++$GLOBALS['events']; }
function absint($n) { return abs((int)$n); }
function current_time($format) { return '20261007120000'; }
function mbstring_binary_safe_encoding() {}
function reset_mbstring_encoding() {}
function __($s, ...$args) { return $s; }
class WP_Error {}
class FixtureOrder {
    public array $meta=[];
    public function get_meta($key, ...$args) { return $this->meta[$key]??''; }
    public function update_meta_data($key,$value) { $this->meta[$key]=$value; }
    public function save() {}
}
function wc_get_order($id) { return $GLOBALS['order']; }
class GLS_Shipping_For_Woo {
    public static function get_instance() { return new self(); }
    public function setup_labels_directory() { if (!file_exists(GLS_LABELS_DIR)) mkdir(GLS_LABELS_DIR,0750); }
}
class GLS_Shipping_API_Data {
    public function __construct($id) {}
    public function generate_post_fields(...$args) { return []; }
}
class GLS_Shipping_API_Service {
    public function send_order($args) { ++$GLOBALS['provider_calls']; return ['body'=>$GLOBALS['body']]; }
}
require dirname(__DIR__,3).'/gls-shipping-for-woocommerce/includes/admin/class-gls-shipping-order.php';
$assertions=0;
$assert=static function($value,$why)use(&$assertions){++$assertions;if(!$value)throw new RuntimeException($why);};
$reset=static function(){ $GLOBALS['order']=new FixtureOrder();$GLOBALS['provider_calls']=0;$GLOBALS['events']=0; };
$label='%PDF-1.4'."\nfixture bytes\n%%EOF";
$GLOBALS['body']=['Labels'=>array_values(unpack('C*',$label)),'PrintLabelsInfoList'=>[['ParcelNumber'=>123456789,'ParcelId'=>42]]];
$gls=new GLS_Shipping_Order();$reset();
try {
    $result=$gls->generate_single_order_label(123);
    $assert($result['success']&&$GLOBALS['provider_calls']===1,'One provider call succeeds');
    $path=GLS_LABELS_DIR.'/'.$GLOBALS['order']->meta['_gls_print_label'];
    $assert(file_get_contents($path)===$label,'PDF bytes preserved on local disk');
    $assert((fileperms($path)&0777)===0640,'Label is not world readable');
    $assert($GLOBALS['order']->meta['_gls_tracking_codes']===[123456789]&&$GLOBALS['order']->meta['_gls_parcel_ids']===[42],'Tracking and IDs retained as arrays');
    $assert($GLOBALS['events']===1,'One success hook');
    $repeat=$gls->generate_single_order_label(123);
    $assert(!$repeat['success']&&$GLOBALS['provider_calls']===1,'Successful retry cannot create another parcel');
    unlink($path);rmdir(GLS_LABELS_DIR);file_put_contents(GLS_LABELS_DIR,'blocked directory');
    $reset();$result=$gls->generate_single_order_label(123);
    $assert(!$result['success'],'Local write failure is visible');
    $assert($GLOBALS['order']->get_meta('_gls_print_label')===''&&$GLOBALS['events']===0,'No false stored-label or success event');
    $assert($GLOBALS['order']->meta['_gls_parcel_ids']===[42],'Provider identity survives storage failure');
    $repeat=$gls->generate_single_order_label(123);
    $assert(!$repeat['success']&&$GLOBALS['provider_calls']===1,'Partial-success retry cannot duplicate provider parcel');
    unlink(GLS_LABELS_DIR);$reset();$GLOBALS['body']['Labels']=array_values(unpack('C*','not a PDF'));
    $result=$gls->generate_single_order_label(123);
    $assert(!$result['success']&&$GLOBALS['order']->get_meta('_gls_print_label')==='','Reject non-PDF label');
    $assert($GLOBALS['order']->meta['_gls_parcel_ids']===[42],'Invalid PDF still preserves provider reference');
    foreach (['_gls_print_label'=>'existing.pdf','_gls_tracking_codes'=>[123],'_gls_tracking_code'=>'123','_gls_parcel_ids'=>[42]] as $key=>$value) {
        $reset();$GLOBALS['order']->meta[$key]=$value;$result=$gls->generate_single_order_label(123);
        $assert(!$result['success']&&$GLOBALS['provider_calls']===0,'Block existing '.$key.' before provider');
    }
} finally {
    if(is_dir(GLS_LABELS_DIR)) { foreach(glob(GLS_LABELS_DIR.'/*')as$p)unlink($p);rmdir(GLS_LABELS_DIR); }
    elseif(file_exists(GLS_LABELS_DIR))unlink(GLS_LABELS_DIR);
}
echo "GLS label storage: $assertions assertions passed; provider stubbed.\n";
