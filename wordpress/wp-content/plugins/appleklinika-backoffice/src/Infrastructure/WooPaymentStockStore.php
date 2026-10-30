<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;
use Appleklinika\BackOffice\Application\Port\PaymentStockStore;

final class WooPaymentStockStore implements PaymentStockStore
{
    public const EXCEPTION = '_appleklinika_payment_stock_exception';
    private static array $locks = [];
    private static bool $shutdown = false;

    public function lockAndRead(int $id): ?array
    {
        $order = wc_get_order($id);
        if (!$order instanceof \WC_Order || $order->get_payment_method() !== 'barion') return null;
        $requirements = $this->requirements($order);
        if (!$requirements) return null; // Non-device orders and in-store cash are unchanged.
        $this->lock('order:' . $id);
        ksort($requirements, SORT_NUMERIC);
        foreach ($requirements as $productId => $quantity) $this->lock('product:' . $productId);
        $order = wc_get_order($id);
        $lines = [];
        foreach ($requirements as $productId => $quantity) {
            clean_post_cache($productId);
            $product = wc_get_product($productId);
            if ($product) $product->get_data_store()->read($product);
            $lines[] = ['exists'=>(bool)$product,'managed'=>$product && $product->managing_stock(),
                'backorders'=>$product && $product->backorders_allowed(),'stock'=>$product ? (float)$product->get_stock_quantity() : 0,
                'held_elsewhere'=>$product ? (float)wc_get_held_stock_quantity($product,$id) : 0,'required'=>$quantity];
        }
        return ['paid'=>$order->is_paid(),'reduced'=>(bool)$order->get_data_store()->get_stock_reduced($id),
            'exception'=>(bool)$order->get_meta(self::EXCEPTION),'lines'=>$lines];
    }

    private function requirements(\WC_Order $order): array
    {
        $result=[];
        foreach ($order->get_items() as $item) {
            $product=$item->get_product();
            $type=(string)($product ? $product->get_meta('_appleklinika_device_type') : $item->get_meta('_appleklinika_device_type'));
            if (!in_array($type,['iphone','ipad','macbook','apple_watch'],true)) continue;
            $id=$product ? $product->get_stock_managed_by_id() : $item->get_product_id();
            $result[$id]=($result[$id]??0)+(float)$item->get_quantity();
        }
        return $result;
    }

    private function lock(string $resource): void
    {
        global $wpdb;
        $key='akpaid:'.substr(hash('sha256',$wpdb->prefix.':'.$resource),0,56);
        if(isset(self::$locks[$key]))return;
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)',$key))!==1)throw new \RuntimeException('lock_busy');
        self::$locks[$key]=true;
        if(!self::$shutdown){self::$shutdown=true;add_action('shutdown',[self::class,'release'],PHP_INT_MAX);}
    }

    public static function release(): void
    {
        global $wpdb;
        foreach(array_keys(self::$locks)as$key)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));
        self::$locks=[];
    }

    public function reserve(int $id): void
    {
        // Native atomic reservation also competes safely with independent checkout sessions.
        (new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock())->reserve_stock_for_order(wc_get_order($id),10);
    }

    public function exception(int $id,string $transaction,string $reason): void
    {
        $order=wc_get_order($id);
        if(!$order instanceof \WC_Order||$order->is_paid()||$order->get_meta(self::EXCEPTION))return;
        $order->update_meta_data(self::EXCEPTION,['reason'=>$reason,'transaction'=>$transaction,'at'=>gmdate('c')]);
        $order->update_meta_data('_appleklinika_lifecycle_last_issue',$reason);
        $order->save_meta_data();
        $order->add_order_note('Barion-fizetés visszaigazolva, de a készülék készlete nem biztosítható. Automatikus teljesítés és számlázás leállítva. A Barion-tranzakciót és a készletet egyeztetni kell; szükség esetén szolgáltatói visszatérítés szükséges. Ne jelöld kézzel teljesíthetőnek.',false);
        wc_get_logger()->error($reason,['source'=>'appleklinika-order-safety','order_id'=>$id]);
    }
}
