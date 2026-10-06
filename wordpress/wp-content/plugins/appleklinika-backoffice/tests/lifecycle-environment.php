<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/Infrastructure/LifecycleConfiguration.php';
use Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration as Config;
function home_url($path='') { return $GLOBALS['home'].$path; }
function wp_parse_url($url,$part) { return parse_url($url,$part); }
function wp_get_environment_type() { return $GLOBALS['environment']; }
function get_option($key,$default=null) { return $GLOBALS['options'][$key] ?? $default; }
final class WC_Order {
    public int $marker=0, $created=200;
    public string $payment='barion';
    public function get_payment_method() { return $this->payment; }
    public function get_meta($key,$single=true) { return $this->marker; }
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
unset($GLOBALS['options']['home']);
$assert(!Config::enabled(), 'Missing canonical installation URL fails closed.');
echo "Lifecycle environment: $n assertions passed.\n";
