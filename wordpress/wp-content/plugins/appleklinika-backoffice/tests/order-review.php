<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure {
    final class WooOrderBackOfficeRepository {
        public function state(\WC_Order $o): string { return $o->state; }
        public function deliveryMode(\WC_Order $o): string { return \Appleklinika\BackOffice\Domain\DeliveryMode::fromShippingMethodIds(array_map(fn($s)=>$s->method,$o->shipping)); }
        public function recordReview($o,$a,$b,$after,$why,$result): void { $GLOBALS['audits'][]=[$a,$b,$after,$why,\get_current_user_id()]; }
        public function history($o): array { return $o->meta['_appleklinika_backoffice_history'] ?? []; }
        public function fulfilmentBlockReason($o): ?string { return !$o->paid ? 'Fizetés ellenőrzése szükséges.' : ($this->deliveryMode($o) === 'unknown' ? 'Átvételi mód ellenőrzése szükséges.' : null); }
        public function hasGlsLabel($o): bool { return false; }
        public function glsReadinessMessage(): ?string { return null; }
        public function recordShippingReview($o,$j,$after): void {
            if (($GLOBALS['review_failure']??'')==='before_audit') throw new \RuntimeException('fixture audit failure');
            $history=$this->history($o);$found=false;
            foreach($history as $entry)if(($entry['review_operation']??'')===$j['id'])$found=true;
            if(!$found){$history[]=['action'=>'resolve_shipping','review_operation'=>$j['id'],'user'=>$j['user'],'at'=>$j['at'],'review'=>['before'=>$j['before'],'after'=>$after,'reason'=>$j['reason'],'result'=>'Saved']];$o->meta['_appleklinika_backoffice_history']=$history;$this->recordReview($o,'resolve_shipping',$j['before'],$after,$j['reason'],'Saved');}
            if (($GLOBALS['review_failure']??'')==='after_audit') throw new \RuntimeException('fixture projection failure');
        }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('AKBO_CANONICAL_WRITES', true);
    define('AKBO_CANONICAL_WRITE_ENVIRONMENT', 'staging');
    spl_autoload_register(static function ($class) {
        $prefix='Appleklinika\\BackOffice\\';
        if (str_starts_with($class,$prefix)) { require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; }
    });
    class WC_Order_Item_Shipping {
        public string $method='free_shipping'; public int $instance=1; public string $title='Old free'; public string $total='0'; public string $tax='0';
        public function get_id(): int { return 96; }
        public function get_method_id(): string { return $this->method; }
        public function get_instance_id(): int { return $this->instance; }
        public function get_name(): string { return $this->title; }
        public function get_total(): string { return $this->total; }
        public function get_total_tax(): string { return $this->tax; }
        public function set_method_id($s): void {$this->method=$s;}
        public function set_instance_id($s): void {$this->instance=$s;}
        public function set_method_title($s): void {$this->title=$s;}
        public function set_total($s): void {$this->total=$s;}
        public function save(): void {}
    }
    class WC_Order {
        public string $status='on-hold'; public string $state='new'; public string $payment='bacs'; public bool $paid=false; public mixed $date=null;
        public string $transaction=''; public array $meta=[]; public array $shipping=[]; public string $total='1000'; public string $shipTotal='0'; public bool $items=true; public int $payments=0;
        public function get_id(): int {return 537;}
        public function get_order_number(): string {return '537';}
        public function get_status(): string {return $this->status;}
        public function get_payment_method(): string {return $this->payment;}
        public function is_paid(): bool {return $this->paid;}
        public function get_date_paid(): mixed {return $this->date;}
        public function get_transaction_id(): string {return $this->transaction;}
        public function get_total(): string {return $this->total;}
        public function get_currency(): string {return 'HUF';}
        public function get_shipping_total(): string {return $this->shipTotal;}
        public function get_shipping_tax(): string {return '0';}
        public function get_shipping_methods(): array {return $this->shipping;}
        public function get_items(): array {return $this->items ? [new class {public function get_product(): object {return new \stdClass();} public function get_quantity(): int {return 1;} public function get_total(): string {return '1000';}}] : [];}
        public function get_address($kind): array {return ['country'=>'HU','city'=>'Szeged','postcode'=>'6722','address_1'=>'Test street'];}
        public function get_meta($k,$single=true): mixed {return $this->meta[$k]??'';}
        public function update_meta_data($k,$v): void {if(($GLOBALS['fail_meta_key']??'')===$k)return;$this->meta[$k]=$v;}
        public function read_meta_data($force=false): void {}
        public function save_meta_data(): void {}
        public function save(): void {if(($GLOBALS['review_failure']??'')==='after_canonical')throw new \RuntimeException('fixture canonical interruption');}
        public function add_item($i): void {$this->shipping[]=$i;}
        public function payment_complete($transaction=''): void {++$this->payments;$this->paid=true;$this->date=new \DateTimeImmutable('2026-10-19');$this->status='processing';$this->transaction=$transaction;}
    }
    class Method {
        public array $countries=['HU'];
        public string $method_title='Configured';
        public function __construct(public string $id, public int $instance_id, public string $title, public bool $zone=true,public string $enabled='yes'){}
        public function supports($s): bool {return $this->zone;}
        public function get_title(): string {return $this->title;}
        public function is_available($package): bool {return true;}
        public function get_option($key,$default=[]): mixed {return $key==='supported_countries'?$this->countries:$default;}
    }
    class WC_Shipping_Zones {
        public static function get_zone_matching_package($p): object {return new class { public function get_shipping_methods($enabled): array {return $GLOBALS['zone_methods'];}};}
    }
    class WC_Gateway_Barion {
        public int $completions=0;
        public function get_option($key): string {return $GLOBALS['barion_env'];}
        public function get_barion_payment_id($o): string {return $o->meta['Barion paymentId']??'';}
        public function payment_complete($o,$txn): void {++$this->completions;$o->payment_complete($txn);}
    }
    function WC(): object {return new class {
        public function shipping(): object {return new class {public function get_shipping_methods(): array {return $GLOBALS['global_methods'];}};}
        public function payment_gateways(): object {return new class {public function payment_gateways(): array {return [];}};}
    };}
    function wc_get_order($id): mixed {return $id===537?$GLOBALS['order']:false;}
    function get_current_user_id(): int {return 81;}
    function get_userdata($id): object {return (object)['display_name'=>'QA Employee'];}
    function current_user_can($cap): bool {return $GLOBALS['cap'];}
    function get_option($key,$default=''): mixed {return $key==='home'?'https://teszt.appleklinika.com':$default;}
    function wp_get_environment_type(): string {return $GLOBALS['env'];}
    function wp_json_encode($v): string {return json_encode($v);}
    function wc_format_decimal($v,$p=2): string {return number_format((float)$v,$p,'.','');}
    function current_time($f): string {return '2026-10-19 12:00:00';}
    function has_filter($hook): bool {return $GLOBALS['custom_callback'];}
    function is_user_logged_in(): bool {return true;}
    function status_header($status): void {}
    function wp_die($message,...$args): never {throw new \RuntimeException('access rejected');}
    function check_admin_referer($action): void {if (!$GLOBALS['nonce']) throw new \RuntimeException('nonce rejected');}
    function absint($v): int {return abs((int)$v);}
    function wp_unslash($v): mixed {return $v;}
    function sanitize_key($v): string {return preg_replace('/[^a-z0-9_]/','',strtolower($v));}
    function sanitize_text_field($v): string {return trim(strip_tags($v));}
    function sanitize_textarea_field($v): string {return trim(strip_tags($v));}
    function esc_html($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
    function esc_attr($v): string {return esc_html($v);}
    function esc_url($v): string {return esc_html($v);}
    function admin_url($v): string {return 'https://backoffice-teszt.appleklinika.com/wp-admin/'.$v;}
    function wp_nonce_field($v): void {echo '<input name="_wpnonce" value="'.esc_attr($v).'">';}
    function home_url($path): string {return 'https://teszt.appleklinika.com'.$path;}
    function add_query_arg($args,$url): string {return $url.'?'.implode('&',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($args),$args));}
    function wp_safe_redirect($url): void {echo json_encode(['url'=>$url,'payments'=>$GLOBALS['order']->payments,'shipping'=>array_map(fn($s)=>$s->method,$GLOBALS['order']->shipping),'audits'=>count($GLOBALS['audits'])]);}
    $wpdb = new class {public string $prefix='offline_';public function prepare($sql,...$args): string{return $sql;}public function get_var($sql): string{return '1';}};
    $cap=true;$env='staging';$barion_env='test';$custom_callback=false;$audits=[];
    $zone_methods=[new Method('local_pickup',27,'Current pickup'),new Method('local_pickup',28,'Disabled pickup',true,'no')];
    $global_methods=[new Method('gls_shipping_method',0,'Current GLS',false),new Method('gls_shipping_method_zones',0,'Unconfigured prototype'),new Method('gls_shipping_method_parcel_shop',0,'Needs point',false)];
    $order=new WC_Order();
    $repo=new \Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository();
    $store=new \Appleklinika\BackOffice\Infrastructure\WooOrderReview($repo);
    $nonce=true;
    if (($argv[1] ?? '') === 'prg') {
        $mode=$argv[2];
        $_POST=['order_id'=>537,'backoffice_action'=>$mode==='payment'?'confirm_manual_payment':'resolve_shipping','review_expected'=>$store->review(537)['fingerprint'],'review_reason'=>'Offline confirmed evidence','shipping_choice'=>'local_pickup:27','confirm_review'=>$mode==='unconfirmed'?'0':'1','queue'=>'all','s'=>'Kovács','search_type'=>'customer','queue_page'=>3,'return_url'=>'https://evil.example','redirect_to'=>'https://evil.example'];
        $_SERVER['REQUEST_METHOD']=$mode==='get'?'GET':'POST';
        if ($mode==='stale') {$_POST['review_expected']='stale';}
        (new \Appleklinika\BackOffice\Interfaces\BackOfficeRouter($repo))->handleAction();
        throw new \RuntimeException('PRG must exit');
    }
    $mutex=new class implements \Appleklinika\BackOffice\Application\Port\OrderMutex {public function synchronized(int $id,callable $c): mixed {return $c();}};
    $command=new \Appleklinika\BackOffice\Application\ResolveOrderReview($store,$mutex);
    $count=0;$assert=static function($yes,$message)use(&$count){++$count;if(!$yes)throw new \RuntimeException($message);};
    $reject=static function($f,$contains)use($assert){try{$f();$assert(false,'Expected rejection '.$contains);}catch(\InvalidArgumentException $e){$assert(str_contains($e->getMessage(),$contains),$e->getMessage());}};
    $run=static function($action,$choice='', $expected=null,$confirmed=true,$why='Offline QA evidence')use($store,$command){return $command->execute(537,$action,$expected??$store->review(537)['fingerprint'],$choice,$why,$confirmed);};
    $view=$store->review(537);
    $assert(array_keys($view['choices'])===['local_pickup:27','gls_shipping_method:0'],'Only real enabled applicable instances; no prototype/obsolete/point methods.');
    $global_methods[0]->countries=[];$assert(array_keys($store->review(537)['choices'])===['local_pickup:27'],'Global GLS without a configured country cannot be selected.');$global_methods[0]->countries=['HU'];
    $assert($order->payments===0 && $order->shipping===[],'Read model has no mutation/provider call.');
    $reject(fn()=>$run('resolve_shipping','local_pickup:27',null,false),'megerősítés');
    $reject(fn()=>$run('resolve_shipping','local_pickup:27',null,true,'x'),'indoklás');
    $reject(fn()=>$run('resolve_shipping','https://evil.example/'),'szállítási mód');
    $reject(fn()=>$run('resolve_shipping','local_pickup:2'),'szállítási mód');
    $stale=$view['fingerprint'];$order->total='1200';$reject(fn()=>$run('resolve_shipping','local_pickup:27',$stale),'megváltoztak');$order->total='1000';
    $order->meta['_wc_szamlazz_invoice']='EXISTING-INVOICE';
    $run('resolve_shipping','local_pickup:27');
    $assert(count($order->shipping)===1 && $order->shipping[0]->method==='local_pickup' && $order->shipping[0]->instance===27,'Missing shipping item repaired authoritatively.');
    $assert($order->total==='1000' && !$order->paid && $order->payments===0,'Shipping repair leaves totals/payment/provider generation unchanged.');
    $assert($order->meta['_wc_szamlazz_invoice']==='EXISTING-INVOICE','Existing invoice remains independent of shipping repair.');
    $assert($order->meta[\Appleklinika\BackOffice\Domain\OrderQueueQuery::SHIPPING_METHOD_META_KEY]==='Current pickup','Queue snapshot reflects repaired canonical item.');
    $assert($audits[0][0]==='resolve_shipping' && $audits[0][1]['shipping']===[] && $audits[0][2]['shipping'][0]['instance']===27 && $audits[0][4]===81,'Before/after/reason/employee audit contract.');
    $run('resolve_shipping','gls_shipping_method:0',null,true,'Second explicitly verified method');
    $assert($order->shipping[0]->method==='gls_shipping_method' && count($repo->history($order))===2,'A second manual correction preserves both histories.');
    $order=new WC_Order();$order->shipping=[new WC_Order_Item_Shipping()];$order->shipping[0]->total='200';$order->shipTotal='200';
    $run('resolve_shipping','gls_shipping_method:0');
    $assert($order->shipping[0]->get_id()===96 && $order->shipping[0]->method==='gls_shipping_method' && $order->shipping[0]->total==='200' && $order->shipTotal==='200','Existing item repaired in place with existing price.');
    foreach (['_gls_parcel_ids','_gls_tracking_codes','_gls_print_label','_vp_woo_pont_parcel_pdf'] as $key) {
        $order=new WC_Order();$order->meta[$key]='existing';$assert($store->review(537)['choices']===[],'Provider evidence prevents shipping reassignment '.$key);
    }
    foreach (['attempting','generating','uncertain','ready','unknown_future_state'] as $state) {$order=new WC_Order();$order->meta['_appleklinika_gls_attempt']=['state'=>$state];$assert($store->review(537)['choices']===[],'Unresolved provider attempt blocks shipping reassignment.');}
    foreach (['packing','ready_for_shipping','handed_to_gls','picked_up'] as $state) {$order=new WC_Order();$order->state=$state;$assert($store->review(537)['shipping_block']!==null,'Late stage cannot be reassigned.');}
    foreach (['completed','failed','cancelled','refunded','checkout-draft','trash'] as $status) {$order=new WC_Order();$order->status=$status;$assert($store->review(537)['shipping_block']!==null,'Non-open order cannot be repaired.');}
    $order=new WC_Order();$order->shipTotal='200';$assert($store->review(537)['choices']===[],'Missing item with financial discrepancy fails closed.');
    $order->shipTotal='0';$order->shipping=[new WC_Order_Item_Shipping(),new WC_Order_Item_Shipping()];$assert($store->review(537)['choices']===[],'Multiple shipping lines need explicit financial reconciliation.');
    $order=new WC_Order();$saved=$zone_methods;$zone_methods=[];$global_methods=[];$assert($store->review(537)['shipping_block']!==null,'No configured method gives precise blocker.');$zone_methods=$saved;
    foreach (['bacs','cheque'] as $method) {$order=new WC_Order();$order->payment=$method;$assert($store->review(537)['payment_action']==='confirm_manual_payment','Native manual method allowed.');$run('confirm_manual_payment');$assert($order->paid && $order->date && $order->transaction==='' && $order->payments===1,'Native completion sets paid/date without invented transaction.');$reject(fn()=>$run('confirm_manual_payment'),'rögzítve');$assert($order->payments===1,'Repeated payment is not completed twice.');}
    $order=new WC_Order();$order->payment='other_gateway';$reject(fn()=>$run('confirm_manual_payment'),'szolgáltató');
    $order->payment='cod';$assert($store->review(537)['payment_action']==='','COD never gets generic mark-paid action.');
    $order=new WC_Order();$cap=false;$reject(fn()=>$store->review(537),'jogosultság');$cap=true;$env='production';$reject(fn()=>$run('confirm_manual_payment'),'környezetben');$env='staging';
    $gateway=new WC_Gateway_Barion();$client=new class {public int $calls=0;public mixed $response;public function GetPaymentState($id): mixed {++$this->calls;return $this->response;}};
    \Appleklinika\BackOffice\Infrastructure\BarionPaymentReview::capture($client,$gateway);
    $order=new WC_Order();$order->payment='barion';$order->status='pending';
    $assert($store->review(537)['payment_action']==='' && str_contains($store->review(537)['payment_message'],'fizetésazonosító'),'Barion missing ID explained without fabricated lookup.');
    $order->meta['Barion paymentId']='provider-id';$assert($store->review(537)['payment_action']==='recheck_barion' && $client->calls===0,'Barion read model uses no external request.');
    $response=(object)['PaymentId'=>'provider-id','PaymentRequestId'=>'537','Total'=>1000,'Currency'=>'HUF','Status'=>'Started','Errors'=>[], 'Transactions'=>[(object)['POSTransactionId'=>'537','TransactionId'=>'REAL-PROVIDER-TXN']]];
    foreach (['Started','Expired','Canceled','Failed','Reserved'] as $state) {$response->Status=$state;$client->response=clone $response;$result=$run('recheck_barion');$assert(str_contains($result,$state) && !$order->paid && $gateway->completions===0,'Nonfinal provider state is precise and never manually paid.');}
    $response->Status='Succeeded';$response->Total=999;$client->response=clone $response;$assert(str_contains($run('recheck_barion'),'nem egyezik') && !$order->paid,'Mismatched provider amount rejected.');$response->Total=1000;
    $response->PaymentId='foreign';$client->response=clone $response;$assert(str_contains($run('recheck_barion'),'nem egyezik'),'Foreign provider ID rejected.');$response->PaymentId='provider-id';
    $custom_callback=true;$client->response=clone $response;$assert(str_contains($run('recheck_barion'),'egyedi') && !$order->paid,'Existing custom callback policy not bypassed.');$custom_callback=false;
    $order->meta['_barion_payment_close']=1; // Simulate interruption after the native callback marker, before payment_complete.
    $barion_env='live';$reject(fn()=>$run('recheck_barion'),'igazolt');$barion_env='test';
    $result=$run('recheck_barion');$assert($order->paid && $order->transaction==='REAL-PROVIDER-TXN' && $gateway->completions===1 && str_contains($result,'rögzítette'),'Interrupted closed-marker payment recovers once after independent exact provider evidence.');
    $reject(fn()=>$run('recheck_barion'),'rögzítve');$assert($gateway->completions===1,'No repeated completion after success.');
    $router=new \Appleklinika\BackOffice\Interfaces\BackOfficeRouter($repo);
    $_POST=['order_id'=>537,'backoffice_action'=>'resolve_shipping'];
    $cap=false;
    try {$router->handleAction();$assert(false,'Capability required.');} catch (\RuntimeException $e) {$assert($e->getMessage()==='access rejected','Capability checked before recovery.');}
    $cap=true;$nonce=false;
    try {$router->handleAction();$assert(false,'Nonce required.');} catch (\RuntimeException $e) {$assert($e->getMessage()==='nonce rejected','Order/action nonce required.');}
    $nonce=true;
    foreach (['shipping','payment','unconfirmed','stale','get'] as $mode) {
        $result=json_decode(shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' prg '.$mode),true,512,JSON_THROW_ON_ERROR);
        parse_str(parse_url($result['url'],PHP_URL_QUERY),$query);
        $assert($query['order']==537 && $query['queue']==='all' && $query['s']==='Kovács' && $query['search_type']==='customer' && $query['queue_page']==3,'Recovery PRG retains validated worklist context.');
        $success=in_array($mode,['shipping','payment'],true);
        $assert($query['notice_type']===($success?'success':'error') && $result['audits']===($success?1:0),'Only completed actions produce success and audit.');
        $assert($result['payments']===($mode==='payment'?1:0) && $result['shipping']===($mode==='shipping'?['local_pickup']:[]),'Rejected/GET requests never mutate shipping/payment.');
        $assert(!str_contains($result['url'],'evil.example'),'Recovery never accepts an external return URL.');
    }
    $order=new WC_Order();
    ob_start();
    (new \ReflectionMethod($router,'renderReview'))->invoke($router,$order,['queue'=>'all','s'=>'Kovács','search_type'=>'customer','queue_page'=>3]);
    $html=ob_get_clean();
    $assert(str_contains($html,'value="local_pickup:27"') && !str_contains($html,'local_pickup:2"'),'Rendered choice uses the actual configured instance.');
    $assert(str_contains($html,'value=""') && str_contains($html,'name="confirm_review" value="1" required') && str_contains($html,'name="review_reason"'),'No preselected method; explicit evidence and confirmation are required.');
    $assert(str_contains($html,'name="queue_page" value="3"') && str_contains($html,'name="review_expected"'),'Review form preserves page context and concurrency fingerprint.');
    $assert(str_contains($html,'appleklinika_backoffice_resolve_shipping_537') && str_contains($html,'appleklinika_backoffice_confirm_manual_payment_537'),'Each rendered command has its own order-scoped nonce.');
    $assert($order->payments===0 && $order->shipping===[],'Rendering recovery forms cannot mutate order data.');
    $cashForm=(new \ReflectionMethod($router,'actionForm'))->invoke($router,$order,'record_cash_pickup','Payment','button',[]);
    $assert(str_contains($cashForm,'name="confirm_cash_payment" value="1" required'),'Existing cash handoff requires explicit payment/physical-handoff confirmation.');
    $assert(str_contains($html,'Belső indoklás (csak munkatársaknak)') && str_contains($html,'Nem ügyfélüzenet'),'Reason is explicitly internal, with its actual destinations.');
    $assert(str_contains($html,'minden korábbi döntés megmarad') && str_contains($html,'Nincs egykattintásos visszavonás'),'Shipping is a new audited correction, not a payment undo.');
    $assert(str_contains($html,'data-review-choice="local_pickup:27"') && str_contains($html,'1000.00 HUF') && str_contains($html,'ügyfélértesítés elindulhat'),'Calculated shipping preview and distinct payment side effects.');
    $global_methods=[new Method('gls_shipping_method',0,'Actual GLS <script>',false)];
    $order->total='5678';$order->shipTotal='1990';$order->shipping=[new WC_Order_Item_Shipping()];$order->shipping[0]->total='1990';
    $previews=$store->review(537)['shipping_previews'];
    $gls=implode(' ', $previews['gls_shipping_method:0']);$pickup=implode(' ', $previews['local_pickup:27']);
    $assert(str_contains($gls,'Actual GLS <script>') && str_contains($gls,'5678.00 HUF') && str_contains($gls,'1990.00'),'Preview uses actual title, total and preserved nonzero shipping amount.');
    $assert(str_contains($gls,'GLS-ként') && str_contains($gls,'csomagolás befejezésekor') && str_contains($gls,'fizetés továbbra sincs'),'GLS preview does not promise immediate label/payment.');
    $assert(str_contains($pickup,'személyes átvételként') && str_contains($pickup,'GLS-művelet nem jelenik meg'),'Pickup has no GLS path.');
    ob_start();(new \ReflectionMethod($router,'renderReview'))->invoke($router,$order,[]);$html=ob_get_clean();
    $assert(!str_contains($html,'Actual GLS <script>') && str_contains($html,'Actual GLS &lt;script&gt;'),'Preview titles are escaped.');
    $order->paid=true;
    $assert(!str_contains(implode(' ', $store->review(537)['shipping_previews']['gls_shipping_method:0']),'fizetés továbbra sincs'),'Paid preview reflects actual payment state.');
    $before=$store->review(537)['facts'];$run('resolve_shipping','gls_shipping_method:0');$after=$store->review(537)['facts'];
    $order->meta['_appleklinika_backoffice_history']=[['action'=>'resolve_shipping','from'=>'new','to'=>'new','user'=>'Employee <script>','at'=>'2026-10-19 12:00:00','review'=>['before'=>$before,'after'=>$after,'reason'=>'Internal <script> evidence','result'=>'Saved canonical method']]];
    $_GET=['notice'=>'Forged employee / result'];
    ob_start();(new \ReflectionMethod($router,'renderLastReview'))->invoke($router,$order);$html=ob_get_clean();
    $assert(str_contains($html,'free_shipping:1') && str_contains($html,'gls_shipping_method:0'),'Confirmation uses saved before/after methods.');
    $assert(str_contains($html,'Employee &lt;script&gt;') && str_contains($html,'2026-10-19 12:00:00') && str_contains($html,'Internal &lt;script&gt; evidence') && !str_contains($html,'Forged'),'Actor/time/reason come only from escaped persisted audit.');
    $assert(str_contains($html,\Appleklinika\BackOffice\Domain\FulfilmentWorkflow::actions()['start']),'Confirmation reuses normal next action.');
    $order->paid=false;ob_start();(new \ReflectionMethod($router,'renderLastReview'))->invoke($router,$order);$html=ob_get_clean();
    $assert(str_contains($html,'Fizetés ellenőrzése szükséges.'),'Later payment changes update current next step without rewriting old review.');
    $description=$store::describeFacts(['status'=>'processing','paid'=>true,'date_paid'=>'2026-10-19','transaction'=>true],'confirm_manual_payment');
    $assert(str_contains($description,'processing') && str_contains($description,'2026-10-19') && str_contains($description,'tranzakció: rögzítve'),'Payment review describes real persisted status/date/transaction evidence.');
    $assert($order->payments===0,'All previews and audit rendering remain read-only.');
    $order->paid=true;$order->state='ready_for_shipping';
    $assert(str_contains($store->nextStep($order),'GLS címke még hiányzik'),'Review result reuses GLS observation, not a normal manual-generation instruction.');
    // Write-ahead evidence, duplicate requests and deterministic interruption recovery.
    $order=new WC_Order();$initial=$store->review(537)['fingerprint'];
    $fail_meta_key=\Appleklinika\BackOffice\Domain\OrderReview::JOURNAL_KEY;
    $reject(fn()=>$run('resolve_shipping','local_pickup:27',$initial),'nem indult');
    $assert($order->shipping===[] && $repo->history($order)===[],'No canonical change without verified durable intent.');$fail_meta_key='';
    foreach(['after_canonical','before_audit','after_audit'] as $failure){
        $order=new WC_Order();$initial=$store->review(537)['fingerprint'];$review_failure=$failure;
        $reject(fn()=>$run('resolve_shipping','local_pickup:27',$initial),'lezárása nem igazolt');
        $journal=$order->get_meta(\Appleklinika\BackOffice\Domain\OrderReview::JOURNAL_KEY);
        $assert($journal['stage']==='prepared' && $journal['user_id']===81 && $journal['reason']==='Offline QA evidence','Interrupted write retains actor/reason/source/target evidence.');
        $assert($store->review(537)['pending']!==null && $store->review(537)['choices']===[] && $store->review(537)['payment_action']==='','Pending correction blocks another correction/manual payment.');
        ob_start();(new \ReflectionMethod($router,'renderReview'))->invoke($router,$order,[]);$pendingHtml=ob_get_clean();
        $assert(str_contains($pendingHtml,'name="review_operation"') && str_contains($pendingHtml,'Mentés és napló egyeztetése'),'Pending evidence and explicit protected reconciliation are visible.');
        $review_failure='';$run('resolve_shipping','local_pickup:27',$initial);
        $assert(count($repo->history($order))===1 && $order->get_meta(\Appleklinika\BackOffice\Domain\OrderReview::JOURNAL_KEY)['stage']==='complete','Same request finalizes exactly one correction.');
        $run('resolve_shipping','local_pickup:27',$initial);
        $assert(count($repo->history($order))===1,'Completed duplicate does not append audit.');
    }
    $order=new WC_Order();$initial=$store->review(537)['fingerprint'];$review_failure='before_audit';
    $reject(fn()=>$run('resolve_shipping','gls_shipping_method:0',$initial),'lezárása nem igazolt');$review_failure='';
    $journal=$order->get_meta(\Appleklinika\BackOffice\Domain\OrderReview::JOURNAL_KEY);
    $order->shipping[0]->title='Concurrent change';
    $reject(fn()=>$command->execute(537,'reconcile_shipping','','','',true,$journal['id']),'eltér');
    $assert($order->shipping[0]->title==='Concurrent change' && $repo->history($order)===[],'Conflicting/partial state cannot be overwritten during reconciliation.');
    $order=new WC_Order();$first=$store->review(537)['fingerprint'];$run('resolve_shipping','local_pickup:27',$first);
    $second=$store->review(537)['fingerprint'];$run('resolve_shipping','gls_shipping_method:0',$second,true,'New evidence for GLS');
    $run('resolve_shipping','local_pickup:27',$first);
    $assert($order->shipping[0]->method==='gls_shipping_method' && count($repo->history($order))===2,'Old duplicate cannot undo a newer correction.');
    $order->meta['_gls_parcel_ids']=['real-fixture-parcel'];
    $assert($store->review(537)['choices']===[] && str_contains($store->review(537)['shipping_block'],'fuvarozó'),'Provider evidence blocks even previously manual shipping.');
    $reject(fn()=>$run('resolve_shipping','local_pickup:27'),'fuvarozó');
    unset($order->meta['_gls_parcel_ids']);$history=$repo->history($order);$history[]=['action'=>'handed_to_gls'];$order->meta['_appleklinika_backoffice_history']=$history;
    $assert($store->review(537)['choices']===[],'Historical fulfilment side effects remain blocking even if current provider metadata is missing.');
    echo "Back Office order review: {$count} assertions passed; no external calls.\n";
}
