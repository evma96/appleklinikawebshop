<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;
use Appleklinika\BackOffice\Application\Port\DeviceProducts;
final class UpdateDeviceOffer
{
    public function __construct(private readonly DeviceProducts $products) {}
    public function execute(int $id,array $input,string $expected):void
    {
        if($id<1||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new \InvalidArgumentException('Érvénytelen készülékszerkesztési kérés. Nyisd meg újra a készüléket.');
        $this->products->updateOffer($id,array_intersect_key($input,array_flip(['regular_price','sale_price','overall_grade'])),$expected);
    }
}
