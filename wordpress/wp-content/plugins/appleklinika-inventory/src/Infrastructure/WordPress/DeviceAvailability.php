<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;
final class DeviceAvailability
{
    public static function active(\WC_Product $p): bool
    {
        return $p->get_status()==='publish'&&$p->get_catalog_visibility()!=='hidden'&&$p->is_in_stock()
            &&(!$p->get_manage_stock()||(float)$p->get_stock_quantity()>0)&&$p->is_purchasable();
    }
    public static function constraint(): array
    {
        return ['relation'=>'OR',
            ['key'=>'_appleklinika_device_model','compare'=>'NOT EXISTS'],
            ['key'=>'_appleklinika_device_model','value'=>'','compare'=>'='],
            ['relation'=>'AND',['key'=>'_stock_status','value'=>'instock'],
                ['relation'=>'OR',['key'=>'_manage_stock','value'=>'yes','compare'=>'!='],['key'=>'_stock','value'=>0,'type'=>'NUMERIC','compare'=>'>']]],
        ];
    }
}
