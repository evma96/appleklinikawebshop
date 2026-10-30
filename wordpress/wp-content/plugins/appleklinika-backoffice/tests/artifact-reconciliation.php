<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function($c){$p='Appleklinika\\BackOffice\\';if(str_starts_with($c,$p)){require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($p))).'.php';}});
use Appleklinika\BackOffice\Application\ReconcileArtifacts;
use Appleklinika\BackOffice\Application\Port\{ArtifactReconciliationStore,OrderMutex};
final class ArtifactMemory implements ArtifactReconciliationStore {
 public array $f=[], $r=[], $events=[], $calls=[], $outcome=[]; public bool $saveFails=false, $auditFails=false;
 public function __construct(){foreach(['invoice','gls']as$k){$this->f[$k]=['eligible'=>true,'complete'=>false,'evidence'=>false,'attempt'=>'','block'=>null];$this->calls[$k]=0;}}
 public function facts(int $id,string $k):array{return $this->f[$k];}
 public function journal(int $id,string $k):array{return $this->r[$k]??[];}
 public function save(int $id,string $k,array $r):void{if($this->saveFails)throw new RuntimeException('save failure');$this->r[$k]=$r;}
 public function generate(int $id,string $k):void{
  if(($this->r[$k]['state']??'')!=='running')throw new RuntimeException('No durable intent');
  ++$this->calls[$k];$result=$this->outcome[$k]??'done';
  if($result==='done'){$this->f[$k]['complete']=true;$this->f[$k]['evidence']=true;$this->f[$k]['attempt']='ready';}
  else{$this->f[$k]['attempt']=$result;if($result==='uncertain')throw new RuntimeException('lost response');}
 }
 public function audit(int $id,string $k,string $result,string $key):void{if($this->auditFails)throw new RuntimeException('audit unavailable');$this->events[$key]=$result;}
}
final class ArtifactLock implements OrderMutex {public function synchronized(int $id,callable $f):mixed{return $f();}}
$n=0;$ok=static function($v,$m)use(&$n){++$n;if(!$v)throw new RuntimeException($m);};
$new=static function(){ $s=new ArtifactMemory();return [$s,new ReconcileArtifacts($s,new ArtifactLock())];};
[$s,$app]=$new();$app->execute(1,1000);
$ok($s->calls===['invoice'=>1,'gls'=>1],'Both eligible artifacts recover when original events never fired.');
for($i=0;$i<5;$i++)$app->execute(1,2000+$i);
$ok($s->calls===['invoice'=>1,'gls'=>1]&&count($s->events)===2,'Repeated worker never duplicates artifacts or audit.');
foreach(['invoice','gls']as$kind){
 [$s,$app]=$new();$s->f[$kind]['complete']=true;$s->f[$kind]['evidence']=true;$app->execute(1,1000);$ok($s->calls[$kind]===0,'Existing '.$kind.' never regenerated.');
 [$s,$app]=$new();$s->f[$kind]['evidence']=true;$app->execute(1,1000);$ok($s->calls[$kind]===0&&$s->r[$kind]['state']==='reconciliation','Partial '.$kind.' evidence never duplicated.');
 [$s,$app]=$new();$s->f[$kind]['eligible']=false;$app->execute(1,1000);$ok($s->calls[$kind]===0&&!isset($s->r[$kind]),'Ineligible is not a failed operation.');
 foreach(['generating','attempting','failed','uncertain','ready','document_missing']as$a){[$s,$app]=$new();$s->f[$kind]['attempt']=$a;$app->execute(1,1000);$ok($s->calls[$kind]===0,'Unresolved '.$kind.' '.$a.' never auto-replayed.');}
 [$s,$app]=$new();$s->f[$kind]['block']='Provider not configured';$app->execute(1,1000);$ok($s->calls[$kind]===0&&$s->r[$kind]['state']==='blocked','Preflight block sends no provider request.');
 $s->f[$kind]['block']=null;$app->execute(1,1300);$ok($s->calls[$kind]===1,'Fixed preflight configuration is eventually recovered.');
 [$s,$app]=$new();$s->outcome[$kind]='retryable';$app->execute(1,1000);$app->execute(1,1100);$ok($s->calls[$kind]===1,'Known safe rejection observes delay.');
 $s->outcome[$kind]='done';$app->execute(1,1300);$app->execute(1,1600);$ok($s->calls[$kind]===2&&$s->r[$kind]['state']==='done','Explicit safe transient rejection recovers exactly once.');
 [$s,$app]=$new();$s->outcome[$kind]='retryable';foreach([1000,1300,1900,3000,6000]as$t)$app->execute(1,$t);$ok($s->calls[$kind]===3&&$s->r[$kind]['state']==='exhausted','Repeated rejection is capped and actionable.');
 [$s,$app]=$new();$s->outcome[$kind]='uncertain';$app->execute(1,1000);$app->execute(1,2000);$ok($s->calls[$kind]===1&&$s->r[$kind]['state']==='reconciliation','Lost response never blindly retries.');
 [$s,$app]=$new();$s->r[$kind]=['state'=>'running','attempts'=>1];$app->execute(1,2000);$ok($s->calls[$kind]===0,'Interrupted journal fails closed.');
}
[$s,$app]=$new();$s->saveFails=true;try{$app->execute(1,1000);}catch(RuntimeException){}$ok(array_sum($s->calls)===0,'Journal failure prevents external operation.');
[$s,$app]=$new();$s->auditFails=true;try{$app->execute(1,1000);}catch(RuntimeException){}$s->auditFails=false;$app->execute(1,2000);$app->execute(1,2300);
$ok($s->calls===['invoice'=>1,'gls'=>1]&&count($s->events)===2,'Audit failure recovered without repeating successful provider operation.');
[$s,$app]=$new();foreach(['invoice','gls'] as $k){$s->f[$k]['complete']=true;$s->f[$k]['eligible']=false;}$app->execute(1,1000);
$ok($s->r===[]&&$s->events===[]&&array_sum($s->calls)===0,'Unmanaged historical documents are untouched.');
[$s,$app]=$new();foreach(['invoice','gls'] as $k){$s->f[$k]['complete']=true;$s->f[$k]['attempt']='ready';}$app->execute(1,1000);
$ok($s->r===[]&&$s->events===[],'Completed historical documents without interrupted work are not backfilled.');
echo "Artifact reconciliation: $n assertions passed; no network or database.\n";
