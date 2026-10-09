<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\CustomerNotification;
use Appleklinika\BackOffice\Domain\DeliveryMode;

/** Read-only Woo order snapshot for the customer email views. Never sends or saves. */
final class LifecycleEmailPresentation
{
    public function forOrder(\WC_Order $order, string $event): array
    {
        $accepted = $event === CustomerNotification::PAID;
        $cash = CashPickup::matches($order);
        $paid = $accepted && !$cash;
        $received = $event === CustomerNotification::RECEIVED;
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
            'accepted' => $accepted,
            'cash' => $cash,
            'received' => $received,
            'greeting' => $name !== '' ? 'Kedves ' . $name . '!' : 'Kedves Vásárlónk!',
            'number' => $order->get_order_number(),
            'date' => $date ? wc_format_datetime($date, $received ? 'Y. F j. H:i' : 'Y. F j.') : '',
            'intro' => $received ? 'Rendelésed beérkezett az Apple Klinikához. Ez az automatikus értesítés kizárólag a rendelés beérkezését jelzi; nem jelenti annak elfogadását vagy végleges visszaigazolását, és önmagában nem hozza létre a szerződést. A választott fizetési mód szerinti feldolgozást követően külön e-mailben tájékoztatunk a rendelés elfogadásáról.' : ($accepted && $cash ? 'Rendelésed elfogadtuk és visszaigazoltuk. A készüléket félretettük, és megkezdtük az előkészítést. A vételárat készpénzben, személyes átvételkor fizeted ki az üzletben.' : ($paid ? 'Rendelésed elfogadtuk és visszaigazoltuk. A fizetésed sikeresen megérkezett. Megkezdjük rendelésed teljesítését.' : 'A rendelésed csomagját átadtuk a GLS futárszolgálatnak. A szállítás állapotát az alábbi linken követheted.')),
            'preheader' => $received ? 'Automatikus átvételi értesítés – a rendelés elfogadásáról külön tájékoztatunk.' : ($accepted && $cash ? 'Elfogadott rendelés – fizetés készpénzben, személyes átvételkor.' : ($paid ? 'Sikeres fizetés, a számlád a levél mellékletében.' : 'Csomagod már a GLS-nél van. Itt találod a nyomkövetési adatait.')),
            'items' => $items,
            'totals' => $order->get_order_item_totals(),
            'payment_instructions' => $received && $order->get_payment_method() === 'bacs' ? $this->bankInstructions($order) : '',
            'payment' => $order->get_payment_method_title(),
            'shipping' => $order->get_shipping_method(),
            'delivery_title' => $pickup ? 'Személyes átvétel' : ($parcelPoint ? 'Csomagpont / automata' : 'Szállítási cím'),
            'delivery_address' => $pickup ? esc_html($storeAddress) : ($parcelPoint ? '' : $order->get_formatted_shipping_address()),
            'delivery_note' => $received ? 'A kiválasztott szállítási vagy átvételi módot rögzítettük. A teljesítésről a rendelés elfogadása után tájékoztatunk.' : ($pickup
                ? (!$cash ? 'Az átvétel előtt ellenőrizd a rendelésed állapotát a fiókodban. Ott jelezzük, amikor átvehető.' : ((new WooOrderBackOfficeRepository())->state($order) === 'ready_for_pickup' ? 'Rendelésed az üzletben átvehető. Kérjük, a rendelési számot hozd magaddal.' : ((new WooOrderBackOfficeRepository())->state($order) === 'picked_up' ? 'A személyes átvételt rögzítettük.' : 'Rendelésed előkészítés alatt áll. Az aktuális állapotot a fiókodban ellenőrizheted; ott jelezzük, amikor átvehető.')))
                : ($parcelPoint ? 'A kiválasztott átvételi pont adatait a rendelésednél találod.' : ($paid ? 'A csomag GLS-nek történő átadásakor külön értesítést küldünk a nyomkövetési adatokkal.' : 'A kézbesítés aktuális állapotát a GLS nyomkövetése mutatja.'))),
            'billing_address' => $order->get_formatted_billing_address(),
            'invoice_number' => is_scalar($order->get_meta('_wc_szamlazz_invoice', true)) ? (string) $order->get_meta('_wc_szamlazz_invoice', true) : '',
            'tracking' => $accepted || $received ? [] : (new OrderDocuments())->trackingLinks($order),
            'account_url' => ($received || $cash) && !$order->get_customer_id() ? '' : $this->storefrontUrl($order->get_view_order_url(), 'home'),
            'logo_url' => $this->storefrontUrl(plugins_url('appleklinika-inventory/assets/brand/appleklinika-logo.jpg'), 'siteurl'),
        ];
    }

    /** Preserve configured BACS instructions while replacing its on-hold acknowledgement. */
    private function bankInstructions(\WC_Order $order): string
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways['bacs'] ?? null;
        if (! $gateway instanceof \WC_Gateway_BACS) { return ''; }
        // Native rendering only: no status mutation, provider request or invented bank details.
        $status = static fn ($value, $candidate) => $candidate === $order ? $order->get_status() : $value;
        add_filter('woocommerce_bacs_email_instructions_order_status', $status, 10, 2);
        ob_start();
        try {
            $gateway->email_instructions($order, false);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            remove_filter('woocommerce_bacs_email_instructions_order_status', $status, 10);
        }
    }

    /** Staff-host routing must not send customers to the staff login domain. */
    private function storefrontUrl(string $url, string $option): string
    {
        $requestBase = $option === 'home' ? home_url('/') : site_url('/');
        $canonicalBase = (string) get_option($option, '');
        if ($canonicalBase !== '' && str_starts_with($url, $requestBase)) {
            return rtrim($canonicalBase, '/') . '/' . substr($url, strlen($requestBase));
        }
        return $url;
    }

    public static function plain(string $html): string
    {
        return trim(wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
}
