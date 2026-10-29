<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\DeviceProduct;

use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use InvalidArgumentException;

/** Shared staff-entry price/condition contract for intake and subsequent offer edits. */
final class DeviceOfferInput
{
    public static function validate(array $input): array
    {
        $grade=PhysicalDevice::text($input['overall_grade']??'');
        if(!isset(Grade::options()[$grade]))throw new InvalidArgumentException('Válassz állapotot.');
        $regular=str_replace(',','.',PhysicalDevice::text($input['regular_price']??''));
        $sale=str_replace(',','.',PhysicalDevice::text($input['sale_price']??''));
        if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$regular)||(float)$regular<=0)throw new InvalidArgumentException('Adj meg nullánál nagyobb, érvényes eladási árat.');
        if($sale!==''){
            if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$sale))throw new InvalidArgumentException('Az akciós ár érvényes, nullánál nem kisebb összeg lehet.');
            if((float)$sale>=(float)$regular)throw new InvalidArgumentException('Az akciós árnak kisebbnek kell lennie a normál árnál.');
        }
        return ['regular_price'=>$regular,'sale_price'=>$sale,'overall_grade'=>$grade,'body_grade'=>$grade,'display_grade'=>$grade,'camera_island_grade'=>$grade];
    }
}
