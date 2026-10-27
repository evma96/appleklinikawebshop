<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceInvoiceMapping;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceOrderIdentity;
final class DeviceOrderHooks
{
    public function __construct(private readonly DeviceOrderIdentity $identity=new DeviceOrderIdentity()) {}
    public function register(): void
    {
        add_action('woocommerce_checkout_create_order_line_item',[$this,'checkoutSnapshot'],5,4);
        add_action('woocommerce_new_order_item',[$this,'newItem'],10,3);
        add_filter('woocommerce_order_item_get_formatted_meta_data',[$this,'hidePrivate'],30);
        add_filter('woocommerce_hidden_order_itemmeta',static fn($keys)=>array_unique(array_merge($keys,DeviceOrderIdentity::KEYS)));
        add_action('woocommerce_after_order_itemmeta',[$this,'adminIdentity'],10,3);
        $invoice=new DeviceInvoiceMapping($this->identity);
        add_filter('wc_szamlazz_invoice_line_item',[$invoice,'line'],20,4);
        add_filter('wc_szamlazz_xml',[$invoice,'document'],15,3);
    }
    public function checkoutSnapshot(\WC_Order_Item_Product $item,string $key,array $values,\WC_Order $order): void { $this->identity->capture($item); }
    public function newItem(int $id,\WC_Order_Item $item,int $orderId): void
    {
        if(!$item instanceof \WC_Order_Item_Product)return;
        $this->identity->capture($item);$item->save_meta_data();
    }
    public function hidePrivate(array $metadata): array
    {
        return array_filter($metadata,static fn($meta)=>!in_array($meta->key,DeviceOrderIdentity::KEYS,true));
    }
    public function adminIdentity(int $id,\WC_Order_Item $item,mixed $product): void
    {
        if(!$item instanceof \WC_Order_Item_Product||!current_user_can('manage_woocommerce'))return;
        foreach(['sku'=>'Cikkszám','imei'=>'IMEI','serial_number'=>'Sorozatszám'] as $key=>$label){$value=(string)$item->get_meta('_appleklinika_'.$key);if($value!=='')echo '<p><strong>'.esc_html($label).':</strong> '.esc_html($value).'</p>';}
    }
}
