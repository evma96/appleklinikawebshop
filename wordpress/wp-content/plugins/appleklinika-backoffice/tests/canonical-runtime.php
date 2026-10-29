<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$mode=$argv[1]??'';
if($mode==='case'){
 $environment=$argv[2];$flag=$argv[3];$scope=$argv[4];$expected=$argv[5]==='allow';
 function wp_get_environment_type():string{return $GLOBALS['environment'];}
 if($flag!=='absent')define('AKBO_CANONICAL_WRITES',$flag==='true'?true:($flag==='false'?false:$flag));
 if($scope!=='absent')define('AKBO_CANONICAL_WRITE_ENVIRONMENT',$scope);
 require dirname(__DIR__).'/src/Infrastructure/CanonicalRuntime.php';
 $allowed=\Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::orderWritesAllowed();
 if($allowed!==$expected)throw new RuntimeException('Configuration mismatch');
 try{\Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::authorizeWrite();$authorized=true;}catch(InvalidArgumentException){$authorized=false;}
 if($authorized!==$expected)throw new RuntimeException('Command gate mismatch');
 if(\Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::productWritesAllowed()!==$expected)throw new RuntimeException('Product configuration mismatch');
 try{\Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::authorizeProductWrite();$productAuthorized=true;}catch(InvalidArgumentException){$productAuthorized=false;}
 if($productAuthorized!==$expected)throw new RuntimeException('Product command gate mismatch');
 if(\Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::storefront('/native-product/')!=='/native-product/')throw new RuntimeException('Native URL must stay unchanged');
 exit;
}
$cases=[['staging','true','staging','allow'],['staging','absent','absent','deny'],['staging','false','staging','deny'],['staging','1','staging','deny'],['staging','true','absent','deny'],['staging','true','local','deny'],['staging','true','production','deny'],['staging','true','test','deny'],['production','absent','absent','deny'],['production','true','staging','deny'],['production','true','local','deny'],['production','false','production','deny'],['local','true','local','allow']];
foreach($cases as $case){$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' case '.implode(' ',array_map('escapeshellarg',$case));passthru($cmd,$status);if($status!==0)exit($status);}
echo 'Canonical native-runtime gate: '.(count($cases)*5)." assertions passed.\n";
