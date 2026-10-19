<?php
/** Official API transport with fake credentials and HTTP stubs; never external traffic. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
require $root.'/src/Domain/GoogleReviews/PublicReviews.php';
require $root.'/src/Application/GoogleReviews/ReviewGateway.php';
require $root.'/src/Infrastructure/WordPress/GoogleBusinessReviews.php';
use Appleklinika\Inventory\Infrastructure\WordPress\GoogleBusinessReviews;
use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;
$checks=0;$responses=[];$calls=[];$transients=[];$options=[];
function check($ok,$why){global $checks;++$checks;if(!$ok)throw new RuntimeException($why);}
function is_wp_error($v){return $v instanceof Exception;}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_post($url,$options){global $responses,$calls;$calls[]=['method'=>'POST','url'=>$url,'options'=>$options];return array_shift($responses);}
function wp_remote_get($url,$options){global $responses,$calls;$calls[]=['method'=>'GET','url'=>$url,'options'=>$options];return array_shift($responses);}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;$GLOBALS['ttls'][$k]=$ttl;}function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function get_option($k,$d=0){return $GLOBALS['options'][$k]??$d;}function delete_option($k){unset($GLOBALS['options'][$k]);}
function add_option($k,$v,...$args){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}function update_option($k,$v,...$args){$GLOBALS['options'][$k]=$v;}
function response(array $data,int $code=200){return ['response'=>['code'=>$code],'body'=>json_encode($data)];}
$dir=sys_get_temp_dir().'/ak-google-fixture-'.bin2hex(random_bytes(6));mkdir($dir,0700);mkdir($dir.'/public',0700);define('ABSPATH',$dir.'/public/');
$path=$dir.'/connection.json';putenv('AK_GOOGLE_REVIEWS_CONFIG_FILE='.$path);
try {
 check(!(new GoogleBusinessReviews())->configured(),'Missing private file');
 $fake=['client_id'=>'FAKE-CLIENT','client_secret'=>'FAKE-SECRET','refresh_token'=>'FAKE-REFRESH','account_id'=>'123','location_id'=>'456'];
 file_put_contents($path,json_encode($fake));chmod($path,0600);
 $g=new GoogleBusinessReviews();check($g->configured(),'Supported private config');
 $location=['metadata'=>['mapsUri'=>PublicReviews::PROFILE_URL]];
 $reviews=['averageRating'=>4,'totalReviewCount'=>1,'reviews'=>[]];
 $responses=[response(['access_token'=>'FAKE-ACCESS']),response($location),response($reviews)];
 $snapshot=$g->fetch();check($snapshot['response']===$reviews && $snapshot['location']===$location,'Unmodified response snapshot');
 check(count($calls)===3,'OAuth + identity + reviews only');
 check($calls[0]['url']==='https://oauth2.googleapis.com/token' && $calls[0]['options']['body']['grant_type']==='refresh_token','OAuth refresh supported endpoint');
 check($calls[1]['options']['headers']['Authorization']==='Bearer FAKE-ACCESS','Bearer header, not URL');
 check(str_contains($calls[2]['url'],'/accounts/123/locations/456/reviews?pageSize=6&orderBy=updateTime%20desc'),'Six latest records, no score filtering');
 foreach($calls as $call){check($call['options']['redirection']===0 && $call['options']['sslverify']===true && $call['options']['timeout']===8,'No insecure redirect / TLS bypass');check(!str_contains($call['url'],'FAKE-'),'Credentials absent from URLs');}
 $calls=[];$responses=[response(['access_token'=>'FAKE-ACCESS']),response(['metadata'=>['mapsUri'=>'https://www.google.com/maps?cid=7']])];
 try{$g->fetch();check(false,'Wrong identity accepted');}catch(RuntimeException $e){check($e->getMessage()==='wrong_business' && count($calls)===2,'Wrong identity stops before review request');}
 foreach([401,403,429,500] as $code){$responses=[response(['error'=>'FAKE-UPSTREAM-SECRET'],$code)];try{$g->fetch();check(false,'HTTP error accepted');}catch(RuntimeException $e){check($e->getMessage()==='google_unavailable','Sanitized HTTP failure');}}
 $responses=[new RuntimeException('FAKE-TOKEN')];try{$g->fetch();check(false,'Timeout accepted');}catch(RuntimeException $e){check(!str_contains($e->getMessage(),'FAKE'),'Transport errors not exposed');}
 $g->save($snapshot+['fetched_at'=>time()]);check($g->load()['response']===$reviews,'Scoped cache works');check($GLOBALS['ttls']['ak_google_reviews_public_v1']===86400,'24 hour maximum TTL');
 check($g->lock() && !$g->lock(),'Concurrent lock');$g->unlock();check($g->lock(),'Lock released');$g->unlock();
 $g->status('unavailable');check(!$g->lock(),'Failure backoff');$g->status('ok');check($g->lock(),'Recovery clears backoff');$g->unlock();
 $fake['location_id']='789';file_put_contents($path,json_encode($fake));check((new GoogleBusinessReviews())->load()===[],'Changed identity cannot reuse previous cache');
 $public=$dir.'/public/connection.json';file_put_contents($public,json_encode($fake));putenv('AK_GOOGLE_REVIEWS_CONFIG_FILE='.$public);check(!(new GoogleBusinessReviews())->configured(),'Secret file under webroot rejected');unlink($public);
 echo "Google review transport/cache: $checks assertions PASS; no network or real credentials\n";
} finally {unlink($path);rmdir($dir.'/public');rmdir($dir);putenv('AK_GOOGLE_REVIEWS_CONFIG_FILE');}
