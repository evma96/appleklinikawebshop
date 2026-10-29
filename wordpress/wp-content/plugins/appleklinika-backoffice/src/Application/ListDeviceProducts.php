<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;
use Appleklinika\BackOffice\Application\Port\DeviceProducts;
final class ListDeviceProducts
{
    public function __construct(private readonly DeviceProducts $products) {}
    public function execute(int $page=1,string $sku='',string $status=''):array
    {
        return $this->products->listing(max(1,$page),mb_substr(trim($sku),0,100),$status);
    }
}
