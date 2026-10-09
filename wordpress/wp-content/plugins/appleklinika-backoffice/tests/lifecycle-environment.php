<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/Infrastructure/LifecycleConfiguration.php';
require_once dirname(__DIR__) . '/src/Infrastructure/CashPickup.php';
require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
use Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration as Config;
function home_url($path='') { return $GLOBALS['home'].$path; }
function wp_parse_url($url,$part) { return parse_url($url,$part); }
function wp_get_environment_type() { return $GLOBALS['environment']; }
function get_option($key,$default=null) { return $GLOBALS['options'][$key] ?? $default; }
final class WC_Order {
    public int $marker=0, $created=200;
    public string $payment='barion', $via='checkout', $status='pending';
    public array|string $submitted='';
    public string $shipping='gls_shipping_method';
    public function get_shipping_methods() { return [new class($this->shipping) { public function __construct(private string $method) {} public function get_method_id() { return $this->method; } }]; }
    public function get_created_via() { return $this->via; }
    public function get_status() { return $this->status; }
    public function get_payment_method() { return $this->payment; }
    public function get_meta($key,$single=true) { return $key === '_appleklinika_lifecycle_submitted' ? $this->submitted : $this->marker; }
    public function get_date_created() { return new DateTimeImmutable('@'.$this->created); }
}
$n=0; $assert=static function($ok,$why)use(&$n){++$n;if(!$ok){throw new RuntimeException($why);}};
$GLOBALS['options']=['appleklinika_lifecycle_enabled_at'=>100]; $order=new WC_Order();
foreach (['https://appleklinika.com','https://www.appleklinika.com','https://other.example'] as $home) {
    $GLOBALS['home']=$home; $GLOBALS['options']['home']=$home; $GLOBALS['environment']='staging';
    $assert(!Config::enabled() && !Config::manages($order), 'Unapproved host cannot enable workflow even with staging flag.');
}
$GLOBALS['home']=$GLOBALS['options']['home']='https://teszt.appleklinika.com'; $GLOBALS['environment']='production';
$assert(!Config::enabled(), 'Production flag rejects even the TEST hostname.');
$GLOBALS['environment']='staging';
$assert(Config::enabled() && Config::manages($order), 'Explicit TEST rollout permits new Barion order.');
$order->created=50; $assert(!Config::manages($order), 'Older orders retain their previous lifecycle.');
$order->created=200; $order->payment='bacs'; $assert(!Config::manages($order), 'Unrelated payment methods are not enrolled.');
$order->marker=1; $GLOBALS['options']=['home'=>'https://teszt.appleklinika.com'];
$assert(Config::manages($order), 'An enrolled order never falls back to redundant Woo emails if enrolment is disabled.');
$GLOBALS['home']=$GLOBALS['options']['home']='https://appleklinika.com'; $assert(!Config::manages($order), 'Persisted marker still cannot enable production.');
$GLOBALS['home']='https://backoffice-teszt.appleklinika.com';
$GLOBALS['options']=['home'=>'https://teszt.appleklinika.com','appleklinika_lifecycle_enabled_at'=>100];
$assert(Config::enabled() && Config::manages($order), 'Dedicated staff host retains TEST lifecycle and email suppression.');
$GLOBALS['environment']='production';
$assert(!Config::enabled() && !Config::manages($order), 'A staff URL cannot override the production environment guard.');
$GLOBALS['environment']='staging'; $GLOBALS['options']['home']='https://appleklinika.com';
$assert(!Config::enabled(), 'A TEST-looking request cannot enable a canonical production installation.');
$GLOBALS['options']['home']='https://teszt.appleklinika.com';
$order->marker=0; $order->payment='bacs';
$assert(Config::canSubmit($order) && !Config::manages($order), 'Bank transfer eligible for acknowledgement but not enrolled before submission.');
$order->submitted=['source'=>'validated_checkout'];
$assert(Config::submitted($order) && Config::manages($order), 'Submitted BACS uses paid/invoice checks for later acceptance.');
$order->payment='cod';
$assert(!Config::canSubmit($order) && !Config::manages($order), 'Disabled cash gateway is not silently enrolled into an invented acceptance rule.');
$order->shipping='local_pickup'; $order->submitted='';
$assert(!Config::canSubmit($order), 'Cash pickup rollout is disabled by default.');
$GLOBALS['options']['appleklinika_cash_pickup_enabled']='yes';
$assert(Config::canSubmit($order), 'Explicit cash pickup rollout permits checkout.');
$order->submitted=['source'=>'validated_checkout'];
$assert(Config::manages($order), 'Submitted pickup cash shares managed workflow.');
$order->shipping='gls_shipping_method';
$assert(!Config::canSubmit($order) && !Config::manages($order), 'Courier cash is never enrolled.');
$order->payment='barion';
foreach(['admin','rest-api','import'] as $via) { $order->via=$via; $assert(!Config::canSubmit($order), 'Non-checkout source excluded: '.$via); }
$order->via='store-api'; $order->status='checkout-draft';
$assert(!Config::canSubmit($order), 'Store API draft reads do not acknowledge.');
$order->status='pending'; $assert(Config::canSubmit($order), 'Validated Store API submission is eligible.');
$order->created=50; $assert(!Config::canSubmit($order), 'Historical order cannot receive retroactive acknowledgement.');
unset($GLOBALS['options']['home']);
$assert(!Config::enabled(), 'Missing canonical installation URL fails closed.');
echo "Lifecycle environment: $n assertions passed.\n";
