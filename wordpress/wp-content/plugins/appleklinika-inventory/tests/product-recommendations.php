<?php
declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress {
    final class WooProductConditionRepository {
        public const META_PREFIX = '_appleklinika_';
        public array $data = [];
        public function get(int $id, string $key): string { return $this->data[$id][$key] ?? ''; }
    }
    final class DeviceCatalogRepository {
        public function all(): array {
            return [
                ['key'=>'phone-a','type'=>'iphone'], ['key'=>'phone-b','type'=>'iphone'],
                ['key'=>'tablet','type'=>'ipad'], ['key'=>'laptop','type'=>'mac'], ['key'=>'watch','type'=>'watch'],
            ];
        }
    }
}
namespace {
    use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;
    use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
    use Appleklinika\Inventory\Infrastructure\WordPress\WooSimilarDevicesQuery;
    final class WC_Product {
        public string $status='publish', $visibility='visible';
        public bool $inStock=true, $purchasable=true;
        public int $quantity=1;
        public function __construct(private int $id) {}
        public function get_id(): int { return $this->id; }
        public function get_status(): string { return $this->status; }
        public function get_catalog_visibility(): string { return $this->visibility; }
        public function is_in_stock(): bool { return $this->inStock; }
        public function is_purchasable(): bool { return $this->purchasable; }
        public function get_manage_stock(): bool { return true; }
        public function get_stock_quantity(): int { return $this->quantity; }
    }
    $queries=[]; $batches=[];
    function wc_get_products(array $args): array {
        global $queries,$batches;
        $queries[]=$args;
        return $batches[$args['page']] ?? [];
    }
    require dirname(__DIR__).'/src/Infrastructure/WordPress/DeviceAvailability.php';
    require dirname(__DIR__).'/src/Infrastructure/WordPress/WooSimilarDevicesQuery.php';
    $repo=new WooProductConditionRepository();
    $query=new WooSimilarDevicesQuery($repo,new DeviceCatalogRepository());
    $count=0;
    $check=static function(bool $ok,string $message) use (&$count):void { ++$count; if(!$ok) throw new RuntimeException($message); };
    $make=static function(int $id,string $model='phone-a',string $type='iphone') use($repo):WC_Product {
        $repo->data[$id]=['device_model'=>$model,'device_type'=>$type]; return new WC_Product($id);
    };
    $ids=static fn(array $items):array=>array_map(static fn(WC_Product $p):int=>$p->get_id(),$items);
    $current=$make(1); $peer=$make(2); $others=[];
    for($i=3;$i<=10;++$i) $others[]=$make($i,'phone-b');
    $batches=[1=>array_merge([$peer],$others)];
    $baseline=serialize([$current,$peer,$others,$repo->data]);
    $items=$query->get($current,[$current,$peer,$peer]);
    $check($ids($items)===[2,3,4,5,6,7],'Sparse model gains six real same-family products, with model peer first.');
    $check(count(array_unique($ids($items)))===6,'Duplicate fallback IDs never create duplicate cards.');
    $check($queries[0]['exclude']===[1],'Current device excluded from lookup.');
    $check($queries[0]['stock_status']==='instock' && $queries[0]['visibility']==='visible','Native stock and visibility restrictions retained.');
    $check($queries[0]['appleklinika_recommendation_scope']['types']===['iphone'],'Fallback is restricted to the same device family.');
    $check($queries[0]['appleklinika_recommendation_scope']['models']===['phone-a','phone-b'],'Model metadata supports new products without category tags.');
    $check(serialize([$current,$peer,$others,$repo->data])===$baseline,'Query never changes product data or inventory.');
    $queries=[];
    $check(count($query->get($current,$others))===6 && $queries===[],'Six existing peers require no fallback query.');
    $batches=[];
    foreach(['hidden','search'] as $visibility){
        $p=$make(20);$p->visibility=$visibility;
        $check($query->get($current,[$p])===[],'Not recommended: '.$visibility);
    }
    foreach(['draft','private'] as $status){
        $p=$make(21);$p->status=$status;
        $check($query->get($current,[$p])===[],'Not recommended: '.$status);
    }
    $p=$make(22);$p->inStock=false;
    $check($query->get($current,[$p])===[],'Out-of-stock devices omitted.');
    $p=$make(23);$p->quantity=0;
    $check($query->get($current,[$p])===[],'Zero-quantity physical device omitted even with a stale stock flag.');
    $p=$make(24);$p->purchasable=false;
    $check($query->get($current,[$p])===[],'Unpurchasable devices omitted.');
    $check($query->get($current,[$make(25,'tablet','ipad'),$make(26,'','accessory')])===[],'Other families and accessories never fill empty cards.');
    $check($ids($query->get($current,[$peer]))===[2],'Only one suitable product remains one, without fake fillers.');
    $check($query->get($make(27,'',''),[$peer])===[],'Unknown device family is not guessed.');
    foreach([['laptop','macbook','mac'],['watch','apple_watch','watch'],['tablet','ipad','ipad']] as [$model,$alias,$type]){
        $queries=[];$device=$make(30,$model,$type);$alternative=$make(31,'',$alias);$batches=[1=>[$alternative,$peer]];
        $check($ids($query->get($device,[]))===[31],$type.': canonical aliases preserve family relevance.');
    }
    $queries=[];$batches=[1=>array_fill(0,24,$make(40,'tablet','ipad')),2=>[$peer]];
    $check($ids($query->get($current,[]))===[2] && count($queries)===2,'Invalid candidates do not prematurely stop bounded pagination.');
    $check($queries[1]['page']===2 && $queries[1]['limit']===24,'Fallback pages are bounded and advance.');
    echo "Product recommendations passed: {$count} assertions; fixtures only, no database or provider calls.\n";
}
