<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\CustomerNotification;
use Appleklinika\BackOffice\Domain\DeliveryMode;

/** Read-only Woo order snapshot for the two customer email views. Never sends or saves. */
final class LifecycleEmailPresentation
{
    public function forOrder(\WC_Order $order, string $event): array
    {
        $paid = $event === CustomerNotification::PAID;
        $methods = [];
        foreach ($order->get_shipping_methods() as $method) {
            $methods[] = $method->get_method_id();
        }
        $pickup = DeliveryMode::fromShippingMethodIds($methods) === DeliveryMode::PICKUP;
        $parcelPoint = count(array_filter($methods, static fn ($id) => str_contains($id, 'parcel_shop') || str_contains($id, 'parcel_locker'))) > 0;
        $contact = function_exists('appleklinika_contact_content') ? appleklinika_contact_content() : [];
        $storeAddress = trim(implode(' ', array_filter([
            $contact['postcode'] ?? get_option('woocommerce_store_postcode', ''),
            $contact['city'] ?? get_option('woocommerce_store_city', ''),
            $contact['address'] ?? get_option('woocommerce_store_address', ''),
        ])));
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            $details = [];
            foreach ($item->get_formatted_meta_data() as $meta) {
                $details[] = ['label' => self::plain($meta->display_key), 'value' => self::plain($meta->display_value)];
            }
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $order->get_formatted_line_subtotal($item),
                'details' => $details,
            ];
        }
        $date = $order->get_date_created();
        $name = trim($order->get_billing_first_name());
        return [
            'paid' => $paid,
            'greeting' => $name !== '' ? 'Kedves ' . $name . '!' : 'Kedves Vásárlónk!',
            'number' => $order->get_order_number(),
            'date' => $date ? wc_format_datetime($date, 'Y. F j.') : '',
            'intro' => $paid ? 'A fizetésed sikeresen megérkezett. Megkezdjük rendelésed feldolgozását.' : 'A rendelésed csomagját átadtuk a GLS futárszolgálatnak. A szállítás állapotát az alábbi linken követheted.',
            'preheader' => $paid ? 'Sikeres fizetés, a számlád a levél mellékletében.' : 'Csomagod már a GLS-nél van. Itt találod a nyomkövetési adatait.',
            'items' => $items,
            'totals' => $order->get_order_item_totals(),
            'payment' => $order->get_payment_method_title(),
            'shipping' => $order->get_shipping_method(),
            'delivery_title' => $pickup ? 'Személyes átvétel' : ($parcelPoint ? 'Csomagpont / automata' : 'Szállítási cím'),
            'delivery_address' => $pickup ? esc_html($storeAddress) : ($parcelPoint ? '' : $order->get_formatted_shipping_address()),
            'delivery_note' => $pickup
                ? 'Az átvétel előtt ellenőrizd a rendelésed állapotát a fiókodban. Ott jelezzük, amikor átvehető.'
                : ($parcelPoint ? 'A kiválasztott átvételi pont adatait a rendelésednél találod.' : ($paid ? 'A csomag GLS-nek történő átadásakor külön értesítést küldünk a nyomkövetési adatokkal.' : 'A kézbesítés aktuális állapotát a GLS nyomkövetése mutatja.')),
            'billing_address' => $order->get_formatted_billing_address(),
            'invoice_number' => is_scalar($order->get_meta('_wc_szamlazz_invoice', true)) ? (string) $order->get_meta('_wc_szamlazz_invoice', true) : '',
            'tracking' => $paid ? [] : (new OrderDocuments())->trackingLinks($order),
            'account_url' => $order->get_view_order_url(),
            'logo_url' => plugins_url('appleklinika-inventory/assets/brand/appleklinika-logo.jpg'),
        ];
    }

    public static function plain(string $html): string
    {
        return trim(wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
}
