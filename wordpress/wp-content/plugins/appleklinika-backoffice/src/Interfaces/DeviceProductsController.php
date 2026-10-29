<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;
use Appleklinika\BackOffice\Application\{ListDeviceProducts,UpdateDeviceOffer,PublishDevice};
use Appleklinika\BackOffice\Infrastructure\WooDeviceProducts;

final class DeviceProductsController
{
    public static function authorize():void
    {
        if(!DeviceIntakeController::allowed())wp_die('A készülékek megtekintéséhez termékszerkesztési jogosultság szükséges.','Hozzáférés megtagadva',['response'=>403]);
        nocache_headers();
    }
    public function listing():array
    {
        self::authorize();
        return (new ListDeviceProducts(new WooDeviceProducts(['family'=>$this->text($_GET,'family'),'stock'=>$this->text($_GET,'stock')])))->execute(absint($_GET['device_page']??1),$this->text($_GET,'sku'),$this->text($_GET,'state'));
    }
    public function edit(int $id):array
    {
        self::authorize();
        if(!current_user_can('edit_product',$id))wp_die('Nincs jogosultságod ennek a készüléknek a szerkesztéséhez.','Hozzáférés megtagadva',['response'=>403]);
        $products=new WooDeviceProducts();
        try{$device=$products->detail($id);}catch(\InvalidArgumentException){wp_die('A készülék nem található.','Készülék nem található',['response'=>404]);}
        $result=['device'=>$device,'values'=>$device,'error'=>''];
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $publish=$this->text($_POST,'product_action')==='publish';
            if($publish&&!current_user_can('publish_products'))wp_die('Nincs jogosultságod a készülék közzétételéhez.','Hozzáférés megtagadva',['response'=>403]);
            $publish?check_admin_referer('akbo_device_publish_'.$id,'_akbo_publish_nonce'):check_admin_referer('akbo_device_edit_'.$id);
            $input=[];foreach(['regular_price','sale_price','overall_grade'] as $key)$input[$key]=$this->text($_POST,$key);
            if(!$publish)$result['values']=array_replace($device,$input);
            // Never replace a stale form token with the current version after a rejected save.
            $result['device']['version']=$this->text($_POST,'expected');
            try{
                \Appleklinika\BackOffice\Infrastructure\CanonicalRuntime::authorizeProductWrite();
                if($publish)(new PublishDevice($products))->execute($id,$this->text($_POST,'expected'));
                else (new UpdateDeviceOffer($products))->execute($id,$input,$this->text($_POST,'expected'));
                wp_safe_redirect(add_query_arg(['view'=>'device-edit','device'=>$id,'updated'=>1],home_url('/backoffice/')));exit;
            }catch(\InvalidArgumentException $error){$result['status']=422;$result['error']=$error->getMessage();}
            catch(\Throwable){$result['status']=503;$result['error']='A mentés nem fejeződött be. Töltsd újra az oldalt, és ellenőrizd a készülék adatait.';}
        }
        return $result;
    }
    private function text(array $input,string $key):string {return is_string($input[$key]??null)?wp_unslash($input[$key]):'';}
}
