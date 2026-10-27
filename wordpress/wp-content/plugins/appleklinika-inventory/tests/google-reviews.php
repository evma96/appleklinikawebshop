<?php
/** Isolated fixtures. No customer reviews, credentials, network or WordPress writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
use Appleklinika\Inventory\Domain\GoogleReviews\PublicReviews;
use Appleklinika\Inventory\Application\GoogleReviews\ReviewGateway;
use Appleklinika\Inventory\Application\GoogleReviews\ReviewService;
require __DIR__ . '/../src/Domain/GoogleReviews/PublicReviews.php';
require __DIR__ . '/../src/Application/GoogleReviews/ReviewGateway.php';
require __DIR__ . '/../src/Application/GoogleReviews/ReviewService.php';
$checks=0;
function check(bool $ok, string $why): void { global $checks; ++$checks; if (!$ok) { throw new RuntimeException($why); } }
$now=1800000000;
$fixture=['fetched_at'=>$now,'location'=>['metadata'=>['mapsUri'=>PublicReviews::PROFILE_URL,'newReviewUri'=>'https://search.google.com/local/writereview?placeid=fixture']],
 'response'=>['averageRating'=>4.2,'totalReviewCount'=>7,'reviews'=>[
 ['reviewer'=>['displayName'=>'FIXTURE — NOT A CUSTOMER','profilePhotoUrl'=>'https://lh3.googleusercontent.com/fixture-photo'],'starRating'=>'TWO','comment'=>str_repeat('Árvíztűrő <script>fixture</script> ',20),'updateTime'=>'2026-10-19T10:00:00Z'],
 ['reviewer'=>['displayName'=>'FIXTURE — NO TEXT'],'starRating'=>'FIVE'],
 ['reviewer'=>['displayName'=>'FIXTURE — INVALID SCORE'],'starRating'=>'INVALID'],
 ]]];
$data=PublicReviews::fromSnapshot($fixture,$now);
check($data['rating']===4.2 && $data['count']===7,'Use API average/count, never recalculate from subset');
check(count($data['reviews'])===2,'Malformed review excluded');
check($data['reviews'][0]['rating']===2,'Low rating retained, no cherry-picking');
check($data['reviews'][0]['text']===$fixture['response']['reviews'][0]['comment'],'Original comment retained');
check($data['reviews'][1]['text']==='' && $data['reviews'][1]['date']==='','No invented comment or date');
check(PublicReviews::fromSnapshot($fixture,$now+86400)===null,'Expiry hard boundary');
check(PublicReviews::fromSnapshot($fixture,$now-1)===null,'Future timestamp rejected');
$wrong=$fixture;$wrong['location']['metadata']['mapsUri']='https://www.google.com/maps?cid=123';
check(PublicReviews::fromSnapshot($wrong,$now)===null,'Wrong store rejected');
foreach(['javascript:alert(1)','https://google.com.evil.invalid','https://person@www.google.com/maps','http://www.google.com','https://www.google.com:8443'] as $url){check(PublicReviews::googleUrl($url)==='','Unsafe destination rejected');}
$zero=$fixture;$zero['response']=['totalReviewCount'=>0,'reviews'=>[]];
check(PublicReviews::fromSnapshot($zero,$now)['rating']===null,'No rating invented for zero reviews');
$bad=$fixture;$bad['response']['averageRating']=9;check(PublicReviews::fromSnapshot($bad,$now)===null,'Invalid aggregate rejected');
final class FakeGateway implements ReviewGateway {
 public array $snapshot=[];public array $next=[];public bool $configured=true;public bool $busy=false;public bool $fail=false;public int $calls=0;public string $state='';
 public function configured():bool{return $this->configured;}public function load():array{return $this->snapshot;}public function save(array $s):void{$this->snapshot=$s;}
 public function clear():void{$this->snapshot=[];}public function fetch():array{++$this->calls;if($this->fail)throw new RuntimeException('fixture secret');return $this->next;}
 public function lock():bool{return !$this->busy;}public function unlock():void{$this->busy=false;}public function status(string $s):void{$this->state=$s;}
}
$g=new FakeGateway;$g->next=$fixture;$service=new ReviewService($g);
check($service->read($now)===null && $g->calls===0,'Rendering cannot call Google');
$service->refresh($now);check($g->calls===1 && $g->state==='ok','Scheduled refresh succeeds');
check($service->read($now)['count']===7 && $g->calls===1,'Cache read makes no request');
$g->fail=true;$service->refresh($now+10);check($g->state==='unavailable' && $service->read($now+10)!==null,'Transient failure retains only fresh cache');
$service->refresh($now+86400);check($service->read($now+86400)===null,'Failure cannot extend expired content');
$g->configured=false;$g->snapshot=$fixture;$calls=$g->calls;$service->refresh($now);check($g->snapshot===[] && $g->calls===$calls && $g->state==='not_configured','Missing configuration clears and performs no request');
$g->configured=true;$g->busy=true;$service->refresh($now);check($g->calls===$calls,'Concurrent refresh prevented');

// Theme presentation uses escaped public data only; no WordPress/bootstrap side effects.
function get_option($k,$d=[]){return $d;}function sanitize_text_field($s){return strip_tags($s);}function sanitize_textarea_field($s){return strip_tags($s);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function get_template_directory_uri(){return '/theme';}function add_action(...$a){}function add_filter(...$a){}function wp_date($f,$ts){return gmdate($f,$ts);}
function is_admin(){return $GLOBALS['surface']==='admin';}function wp_doing_ajax(){return false;}function is_feed(){return false;}function is_404(){return false;}
function is_cart(){return $GLOBALS['surface']==='cart';}function is_checkout(){return $GLOBALS['surface']==='checkout';}function is_account_page(){return $GLOBALS['surface']==='account';}
function get_query_var($s){return $GLOBALS['surface']==='backoffice';}function is_page($s){return false;}function is_front_page(){return $GLOBALS['surface']==='home';}
require __DIR__ . '/../../../themes/appleklinika-theme/inc/google-reviews.php';
foreach(['cart','checkout','account','backoffice','admin'] as $s){$GLOBALS['surface']=$s;check(appleklinika_reviews_surface()==='', $s.' excluded');}
$GLOBALS['surface']='home';check(appleklinika_reviews_surface()==='full','Homepage full');
foreach(['product','archive','search','contact'] as $s){$GLOBALS['surface']=$s;check(appleklinika_reviews_surface()==='compact',$s.' compact');}
$html=appleklinika_render_google_reviews('full',null);
check($html==='', 'No data means no link-only substitute or invented content');
check(!str_contains($html,'ak-reviews__score') && !str_contains($html,'ak-reviews__card') && !str_contains($html,'Írj véleményt'),'Fallback never invents score, card or write destination');
$html=appleklinika_render_google_reviews('full',$data);
check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>'),'Review HTML escaped');
check(str_contains($html,'<details') && str_contains($html,'Tovább olvasom'),'Long text keyboard expansion');
check(str_contains($html,'role="img" aria-label="2,0 / 5 csillag"'),'Accessible review stars');
check(str_contains($html,'2026. 10. 19.') && str_contains($html,'Írj véleményt'),'API date and official write link');
check(substr_count(appleklinika_render_google_reviews('compact',$data),'class="ak-reviews__card"')===2,'Compact includes two source reviews');
$product=file_get_contents(__DIR__.'/../src/Interfaces/Frontend/ProductFrontendDisplay.php');
check(!str_contains($product,'renderReviewsPanel') && !str_contains($product,'ak-single-product__reviews'),'Custom review UI removed');
check(str_contains($product,'renderProductDescriptionPanel($product)') && str_contains($product,'renderProductSpecsPanel($product, $productId)'),'Other product information retained');
$source=file_get_contents(__DIR__.'/../src/Infrastructure/WordPress/GoogleBusinessReviews.php');
check(!str_contains($source,'error_log('),'No upstream credential logging');
check(str_contains($source,"'redirection'=>0") && str_contains($source,"'sslverify'=>true"),'Secure transport');
check($data['reviews'][0]['avatar']==='https://lh3.googleusercontent.com/fixture-photo','API photo retained');
check($data['reviews'][1]['avatar']==='','No invented photo');
foreach (['https://googleusercontent.com.evil.invalid/photo','http://lh3.googleusercontent.com/photo','https://user@lh3.googleusercontent.com/photo','data:image/png;base64,abc','https://localhost/photo'] as $photo) { check(PublicReviews::avatarUrl($photo)==='', 'Unsafe photo URL rejected'); }
check(str_contains($html,'referrerpolicy="no-referrer"') && str_contains($html,'width="44" height="44"'),'Safe dimensioned API avatar');
check(str_contains($html,'role="region"') && str_contains($html,'tabindex="0"'),'Keyboard-scrollable review region');
check(!str_contains(appleklinika_render_google_reviews('full',null),'<section'),'Unavailable data does not reserve an empty block');
check(str_contains($html,'Kevesebb'),'Accessible collapse label');
$logos=appleklinika_render_service_logos();
foreach (['Barion','GLS','MPL','Számlázz.hu'] as $logo) {check(str_contains($logos,'alt="'.$logo.'"'), 'Approved provider logo: '.$logo);}
foreach (['Klarna','Visa','Mastercard','Apple Pay','Google Pay'] as $logo) {check(!str_contains($logos,$logo), 'No inferred provider: '.$logo);}
check(substr_count($logos,'loading="lazy"')===4,'Logos lazy loaded locally');


// Explicitly authorized LOCAL-only visual fixture: fail closed everywhere else.
require __DIR__.'/../src/Infrastructure/WordPress/LocalReviewPreview.php';
use Appleklinika\Inventory\Infrastructure\WordPress\LocalReviewPreview;
function home_url($path='/'){return $GLOBALS['qa_home'].$path;}
function wp_get_environment_type(){return $GLOBALS['qa_environment'];}
$dir=sys_get_temp_dir().'/ak-review-local-'.bin2hex(random_bytes(6));
mkdir($dir,0700);mkdir($dir.'/public',0700);define('ABSPATH',$dir.'/public/');
$path=$dir.'/fixture.json';$oldGuard=getenv('AK_LOCAL_VERIFICATION');$oldPath=getenv('AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE');
$GLOBALS['qa_environment']='local';$GLOBALS['qa_home']='http://localhost:8082';$_SERVER['HTTP_HOST']='localhost:8082';
putenv('AK_LOCAL_VERIFICATION=1');putenv('AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE='.$path);
$qaFixture=['fixture'=>'appleklinika-local-review-qa-v1','response'=>['averageRating'=>4.8,'totalReviewCount'=>128,'reviews'=>[]]];
for($i=1;$i<=8;$i++){$qaFixture['response']['reviews'][]=['reviewer'=>['displayName'=>'QA TEST '.$i],'starRating'=>'THREE','comment'=>'QA-minta: '.str_repeat('Neutral UI test. ',30),'updateTime'=>'2026-10-18T10:00:00Z'];}
try {
 check(LocalReviewPreview::read($now)===null,'Missing fixture fails closed');
 file_put_contents($path,json_encode($qaFixture));
 $qa=LocalReviewPreview::read($now);
 check($qa['qa']===true && count($qa['reviews'])===6,'LOCAL fixture distinctly marked and bounded to six');
 check($qa['reviews'][0]['rating']===3,'QA allows low scores too');
 $html=appleklinika_render_google_reviews('full',$qa);
 check(substr_count($html,'class="ak-reviews__card"')===6,'Full carousel has six cards');
 check(str_contains($html,'LOCAL · QA review data') && str_contains($html,'nem valódi Google-vélemények'),'Compact visible QA badge with explanatory title');
 check(!str_contains($html,'LOCAL VIZUÁLIS TESZT') && !str_contains($html,'QA-minta:'),'No repeated fixture prefix or warning banner in presentation');
 check(str_contains($html,'QA TEST 1') && str_contains($html,'Neutral UI test.'),'Synthetic identity and complete preview comment retained');
 check(str_contains($qa['reviews'][0]['text'],'QA-minta:'),'Presentation does not mutate guarded fixture data');
 check(!str_contains($html,'aria-disabled="true"'),'No inactive preview-only CTA in customer design');
 check(str_contains($html,'Következő vélemények') && str_contains($html,'aria-controls="ak-google-review-cards"'),'Accessible navigation targets the actual card track');
 check(str_contains($html,'ak-reviews__qa-avatar') && !str_contains($html,'https://lh3'),'Neutral LOCAL avatars without remote requests');
 check(!str_contains($html,'A Google legutóbb frissített'),'QA never claims a live Google source');
 check(substr_count(appleklinika_render_google_reviews('compact',$qa),'class="ak-reviews__card"')===2,'QA compact uses same two-card renderer');
 foreach(['production','staging','development','test'] as $environment){
  $GLOBALS['qa_environment']=$environment;
  check(LocalReviewPreview::read($now)===null,$environment.' denies QA reader');
  check(appleklinika_render_google_reviews('full',$qa)==='',$environment.' denies injected QA render');
 }
 // Even a loopback Host header, accidentally mounted fixture and enabled local guard
 // cannot activate QA in TEST or production. Missing live config is not a QA switch.
 foreach(['staging','production'] as $environment){
  $GLOBALS['qa_environment']=$environment;
  foreach(['http://localhost:8082','https://teszt.appleklinika.com','https://appleklinika.com'] as $home){
   $GLOBALS['qa_home']=$home;
   foreach(['localhost:8082','127.0.0.1','teszt.appleklinika.com'] as $host){
    $_SERVER['HTTP_HOST']=$host;
    check(LocalReviewPreview::read($now)===null,'Environment gate wins over host/fixture/local flag');
    check(appleklinika_render_google_reviews('full',$qa)==='','QA full renderer denied under remote environment');
    check(appleklinika_render_google_reviews('compact',$qa)==='','QA compact renderer denied under remote environment');
   }
  }
  $g->configured=false;$g->snapshot=$fixture;
  check($service->read($now)===null && $g->snapshot===[],'Missing Google configuration clears stale snapshot');
  check(appleklinika_render_google_reviews('full',$service->read($now))==='','Missing config exposes no fixture or placeholder');
  check(substr_count(appleklinika_render_service_logos(),'loading="lazy"')===4,'No Google config leaves the independent logo strip intact');
 }
 $GLOBALS['qa_home']='http://localhost:8082';$_SERVER['HTTP_HOST']='localhost:8082';
 $GLOBALS['qa_environment']='local';
 foreach(['appleklinika.com','teszt.appleklinika.com','localhost.evil.invalid',''] as $host){$_SERVER['HTTP_HOST']=$host;check(LocalReviewPreview::read($now)===null,'Non-loopback/missing request host denied');}
 $_SERVER['HTTP_HOST']='localhost:8082';$GLOBALS['qa_home']='https://teszt.appleklinika.com';
 check(LocalReviewPreview::read($now)===null,'Local request cannot activate configured remote site');
 $GLOBALS['qa_home']='http://localhost:8082';putenv('AK_LOCAL_VERIFICATION=0');check(LocalReviewPreview::read($now)===null,'Existing isolation guard required');putenv('AK_LOCAL_VERIFICATION=1');
 $bad=$qaFixture;unset($bad['fixture']);file_put_contents($path,json_encode($bad));check(LocalReviewPreview::read($now)===null,'Explicit fixture marker required');
 $bad=$qaFixture;$bad['response']['reviews'][0]['reviewer']['displayName']='Unmarked identity';file_put_contents($path,json_encode($bad));check(LocalReviewPreview::read($now)===null,'Every QA identity must be marked');
 $bad=$qaFixture;$bad['response']['reviews'][0]['comment']='Unmarked experience claim';file_put_contents($path,json_encode($bad));check(LocalReviewPreview::read($now)===null,'Every QA comment must be marked');
 file_put_contents($path,'not JSON');check(LocalReviewPreview::read($now)===null,'Invalid private fixture rejected');
 file_put_contents($dir.'/public/fixture.json',json_encode($qaFixture));putenv('AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE='.$dir.'/public/fixture.json');check(LocalReviewPreview::read($now)===null,'Public webroot fixture forbidden');unlink($dir.'/public/fixture.json');
 $bad=$fixture;$bad['qa']=true;check(PublicReviews::fromSnapshot($bad,$now)===null,'QA-marked snapshot cannot enter live projection');
 $bad=$fixture;$bad['fixture']='appleklinika-local-review-qa-v1';check(PublicReviews::fromSnapshot($bad,$now)===null,'Fixture-marked snapshot cannot enter live projection');
} finally {
 unlink($path);rmdir($dir.'/public');rmdir($dir);
 putenv($oldGuard===false?'AK_LOCAL_VERIFICATION':'AK_LOCAL_VERIFICATION='.$oldGuard);
 putenv($oldPath===false?'AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE':'AK_GOOGLE_REVIEWS_QA_FIXTURE_FILE='.$oldPath);
}
echo "Google review domain/service/presentation/LOCAL safety: $checks assertions PASS\n";
