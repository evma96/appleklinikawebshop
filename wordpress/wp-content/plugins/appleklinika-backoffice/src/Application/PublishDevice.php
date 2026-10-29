<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;
use Appleklinika\BackOffice\Application\Port\DeviceProducts;

final class PublishDevice
{
    public function __construct(private readonly DeviceProducts $products){}
    public function execute(int $id,string $expected):void
    {
        if($id<1||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new \InvalidArgumentException('Érvénytelen közzétételi kérés. Nyisd meg újra a készüléket.');
        $this->products->publish($id,$expected);
    }
}
