<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Domain\DeviceProduct;
use InvalidArgumentException;
use Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog;
final class PhysicalDevice
{
    public const MARKER = '_appleklinika_physical_device';
    public const NOTE = 'használt cikk - különbözet szerinti szabályozás';
    public static function type(string $type): string { return ['mac'=>'macbook','watch'=>'apple_watch'][$type] ?? $type; }
    public static function text(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Érvénytelen szöveges készülékadat.');
        $value=trim((string)$value);
        if (strlen($value)>500 || preg_match('/[<>\x00-\x1f]/u',$value)) throw new InvalidArgumentException('A készülékadat érvénytelen karaktert tartalmaz.');
        return $value;
    }
    public static function title(array $data, array $model): string
    {
        if (!empty($data['custom_name'])) {
            $title=self::text($data['title']??'');
            if ($title==='') throw new InvalidArgumentException('Az egyedi terméknév nem lehet üres.');
            return $title;
        }
        $name=(string)$model['name'];
        $storage=StorageCapacityCatalog::options()[$data['storage_capacity']??'']??'';
        return trim($name . (self::type((string)$model['type'])!=='apple_watch' && $storage!=='' ? ' '.$storage : ''));
    }
    public static function assertNotAccessory(string $model, string $group): void
    {
        if ($model!=='' && $group!=='') throw new InvalidArgumentException('A készülék nem lehet Extra védelem kiegészítő. Válaszd a „Nem kiegészítő” értéket.');
    }
    public static function assertPublication(array $data): void
    {
        if (($data['sku']??'') === '') throw new InvalidArgumentException('A publikáláshoz add meg a Cikkszám / Boltvezető azonosítót.');
        if (($data['imei']??'') === '' && ($data['serial_number']??'') === '') throw new InvalidArgumentException('A publikáláshoz adj meg IMEI-t vagy sorozatszámot.');
        if ((float)($data['regular_price']??0) <= 0) throw new InvalidArgumentException('A publikáláshoz adj meg érvényes árat.');
        if (empty($data['photos'])) throw new InvalidArgumentException('A publikáláshoz tölts fel legalább egy termékfotót.');
        if (empty($data['color']) || !in_array($data['overall_grade']??'', ['a_plus','a','b','c'],true)) throw new InvalidArgumentException('A publikáláshoz válassz színt és állapotot.');
        if (($data['device_type']??'') !== 'apple_watch' && empty($data['storage_capacity'])) throw new InvalidArgumentException('A publikáláshoz válassz tárhelyet.');
    }
    public static function quantity(mixed $quantity): int
    {
        if (!in_array($quantity,[0,1,'0','1'],true)) throw new InvalidArgumentException('Egy fizikai készülék készlete csak 0 vagy 1 lehet.');
        return (int)$quantity;
    }
    public static function photos(array $ids): array
    {
        $result=[];
        foreach($ids as $id){
            if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new InvalidArgumentException('Érvénytelen termékfotó.');
            if(!in_array((int)$id,$result,true))$result[]=(int)$id;
        }
        return $result;
    }
    public static function invoiceIdentity(string $imei,string $serial): string
    {
        return implode("\n",array_filter([$imei!==''?'IMEI: '.$imei:'',$serial!==''?'Sorozatszám: '.$serial:'']));
    }
}
