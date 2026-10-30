<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\OrderReviewStore;
use Appleklinika\BackOffice\Domain\{DeliveryMode, FulfilmentWorkflow, OrderQueueQuery, OrderReview};

/** Authoritative WooCommerce shipping/payment repair, not a Back Office override. */
final class WooOrderReview implements OrderReviewStore
{
    public function __construct(private readonly WooOrderBackOfficeRepository $orders) {}

    public static function testEnvironment(): bool
    {
        return wp_get_environment_type() !== 'production'
            && in_array(strtolower((string)parse_url((string)get_option('home',''), PHP_URL_HOST)), ['teszt.appleklinika.com','localhost','127.0.0.1'], true);
    }

    public static function reviewEnvironment(): bool
    {
        return self::testEnvironment() || (wp_get_environment_type() === 'production' && class_exists(ProductionReadiness::class) && ProductionReadiness::allowed());
    }

    public function review(int $id): array
    {
        $order = $this->order($id);
        $facts = $this->facts($order);
        $journal = $order->get_meta(OrderReview::JOURNAL_KEY, true);
        $shippingBlock = self::reviewEnvironment() ? OrderReview::shippingBlock($facts) : 'A rendezési műveletek ebben a környezetben még nincsenek engedélyezve.';
        if (OrderReview::pending($journal)) { $shippingBlock = 'Egy korábbi átvételimód-javítás lezárása hiányzik. Előbb folytasd a mentés és a napló egyeztetését; új javítást ne indíts.'; }
        $choices = $shippingBlock === null ? $this->shippingChoices($order) : [];
        if ($shippingBlock === null && $choices === []) { $shippingBlock = 'Nincs alkalmazható, engedélyezett személyes átvétel vagy GLS címre kézbesítés ehhez a címhez. A WooCommerce szállítási zónát / metódust vagy a hiányzó címet kell rendezni. Csomagpont/automata kiválasztásához külön, valódi pontazonosító szükséges.'; }
        [$action, $message] = $this->paymentReview($order, $facts);
        if (OrderReview::pending($journal)) { $action = ''; $message = $shippingBlock; }
        if (!self::reviewEnvironment()) { $action = ''; $message = 'A fizetés rendezése ebben a környezetben még nincs engedélyezve.'; }
        $previews = [];
        foreach ($choices as $key => $choice) { $previews[$key] = $this->shippingPreview($facts, $choice); }
        $revision = count(array_filter($this->orders->history($order), static fn(array $entry): bool => ($entry['action'] ?? '') === 'resolve_shipping'));
        return ['facts'=>$facts, 'fingerprint'=>hash('sha256', (string)wp_json_encode([$facts, $revision])), 'shipping_block'=>$shippingBlock,
            'choices'=>$choices,'shipping_previews'=>$previews,'payment_action'=>$action,'payment_message'=>$message,
            'pending'=>OrderReview::pending($journal) ? $journal : null];
    }

    /** Read-only consequences of this command; never a quote or a provider request. */
    private function shippingPreview(array $facts, array $choice): array
    {
        $mode = DeliveryMode::fromShippingMethodIds([$choice['method']]);
        return [
            'Jelenlegi WooCommerce átvételi mód: '.self::describeFacts($facts, 'resolve_shipping').'.',
            'WooCommerce átvételi mód: '.$choice['title'].' ['.$choice['method'].':'.$choice['instance'].'].',
            $mode === DeliveryMode::GLS ? 'A rendelés GLS-ként lesz kezelve.' : 'A rendelés személyes átvételként lesz kezelve; GLS-művelet nem jelenik meg.',
            ($facts['shipping'] === [] ? 'Új, 0 összegű szállítási tétel készül.' : 'A meglévő szállítási tétel azonosítója és díja megmarad.'),
            'Változatlan végösszeg: '.wc_format_decimal($facts['total'], 2).' '.$facts['currency'].'. Szállítás nettó: '.wc_format_decimal($facts['shipping_total'], 2).'; adó: '.wc_format_decimal($facts['shipping_tax'], 2).' '.$facts['currency'].'. Nincs új díjszámítás.',
            'A fizetési és számlaadatok nem változnak; ez a mentés nem indít számlázást vagy ügyfélértesítést.',
            $mode === DeliveryMode::GLS ? 'GLS-kérés és címke még nem készül. A normál automatizmus a csomagolás befejezésekor, igazolt fizetés és a szolgáltatói feltételek teljesülése után indulhat.' : 'Ez a mentés nem lépteti előre a feldolgozási állapotot.',
            !$facts['paid'] ? 'A fizetés továbbra sincs rögzítve; a fizetési mód szerinti ellenőrzés / készpénzes átvételi folyamat megmarad.' : 'A feldolgozás következő lépését mentés után a rendelés tényleges állapota alapján újraértékeljük.',
        ];
    }

    public function nextStep(\WC_Order $order): string
    {
        if (($block = $this->orders->fulfilmentBlockReason($order)) !== null) { return $block; }
        $next = method_exists($this->orders, 'primaryAction') ? $this->orders->primaryAction($order)
            : FulfilmentWorkflow::primaryAction($this->orders->state($order), $this->orders->deliveryMode($order), $this->orders->hasGlsLabel($order));
        if ($next === 'create_label') { return (new GlsAutomation($this->orders))->status($order); }
        return $next === null ? 'Nincs további normál teljesítési lépés.' : (FulfilmentWorkflow::actions()[$next] ?? $next);
    }

    /** Describe saved audit facts, never transient URL input or current provider guesses. */
    public static function describeFacts(array $facts, string $action): string
    {
        if ($action === 'resolve_shipping') {
            return implode(', ', array_map(static fn(array $row): string => ($row['title'] ?? '').' ['.($row['method'] ?? '').':'.($row['instance'] ?? '').']', $facts['shipping'] ?? [])) ?: 'Nincs szállítási tétel';
        }
        return 'WooCommerce: '.($facts['status'] ?? '—').' · '.(!empty($facts['paid']) ? 'fizetettként nyilvántartva' : 'nincs fizetettként nyilvántartva')
            .' · fizetési időpont: '.($facts['date_paid'] ?? 'nincs').' · tranzakció: '.(!empty($facts['transaction']) ? 'rögzítve' : 'nincs');
    }

    private function facts(\WC_Order $order): array
    {
        $shipping = [];
        foreach ($order->get_shipping_methods() as $item) {
            $shipping[] = ['item_id'=>$item->get_id(),'method'=>$item->get_method_id(),'instance'=>(int)$item->get_instance_id(),
                'title'=>$item->get_name(),'total'=>$item->get_total(),'tax'=>$item->get_total_tax()];
        }
        $carrier = false;
        foreach (['_gls_print_label','_gls_parcel_ids','_gls_tracking_codes','_gls_tracking_code','_vp_woo_pont_parcel_id','_vp_woo_pont_parcel_pdf','_vp_woo_pont_parcel_number','_vp_woo_pont_label','_vp_woo_pont_tracking_number'] as $key) { $carrier = $carrier || (bool)$order->get_meta($key,true); }
        $attempt = $order->get_meta('_appleklinika_gls_attempt',true);
        $carrier = $carrier || (is_array($attempt) && !empty($attempt['state']) && !in_array($attempt['state'], ['blocked','retryable'],true));
        $history = $this->orders->history($order);
        $manual = false;
        $effect = $carrier ? 'Már van fuvarozói dokumentum, csomagadat vagy nem tisztázott szolgáltatói kísérlet. Előbb a GLS / fuvarozó nyilvántartásában kell egyeztetni és szükség esetén törölni a küldeményt, majd rendelésadminisztrációban rendezni az adatot. Ez az űrlap nem töröl küldeményt.' : '';
        foreach ($history as $entry) {
            if (($entry['action'] ?? '') === 'resolve_shipping') { $manual = ($entry['review']['after']['shipping'] ?? null) === $shipping; }
            if ($effect === '' && in_array($entry['action'] ?? '', ['start_packing','packing_completed','prepare_pickup','handed_to_gls','handed_to_carrier','delivered','picked_up','record_cash_pickup','accept_cash_pickup','gls_label_generated','create_label','create_mpl_label'], true)) {
                $effect = 'A korábbi előzményekben már csomagolás, átvételi elfogadás/előkészítés vagy fuvarozói művelet szerepel. A fizikai csomagot, az ügyfélértesítést és a szolgáltatói állapotot külön kell egyeztetni; egyszerű módváltás nem biztonságos.';
            }
        }
        if ($order->get_meta('_appleklinika_cash_payment',true)) { $effect = 'Már rögzített készpénzes átadás tartozik a rendeléshez. A fizetést és átadást külön rendelésadminisztrációban kell egyeztetni.'; }
        return ['status'=>$order->get_status(),'state'=>$this->orders->state($order),'mode'=>$this->orders->deliveryMode($order),
            'payment'=>$order->get_payment_method(),'paid'=>$order->is_paid(),'date_paid'=>$order->get_date_paid()?->format('c'),
            'transaction'=>$order->get_transaction_id() !== '', 'has_items'=>$order->get_items() !== [],
            'total'=>$order->get_total(),'currency'=>$order->get_currency(),'shipping_total'=>$order->get_shipping_total(),
            'shipping_tax'=>$order->get_shipping_tax(),'shipping'=>$shipping,'carrier_evidence'=>$carrier,'manual_shipping'=>$manual,'fulfilment_effect'=>$effect,
            'shipping_totals_match'=>wc_format_decimal(array_sum(array_column($shipping,'total')),2) === wc_format_decimal($order->get_shipping_total(),2)
                && wc_format_decimal(array_sum(array_column($shipping,'tax')),2) === wc_format_decimal($order->get_shipping_tax(),2),
            // Changes to authoritative addresses invalidate an already-open confirmation form.
            'address_fingerprint'=>hash('sha256',(string)wp_json_encode([$order->get_address('billing'),$order->get_address('shipping')]))];
    }

    private function shippingChoices(\WC_Order $order): array
    {
        $address = $order->get_address('shipping');
        if (empty($address['country'])) { $address = $order->get_address('billing'); }
        $package = ['destination'=>$address,'contents'=>[],'contents_cost'=>(float)$order->get_total(),'applied_coupons'=>[]];
        foreach ($order->get_items() as $item) {
            if ($item->get_product()) { $package['contents'][]=['data'=>$item->get_product(),'quantity'=>$item->get_quantity(),'line_total'=>$item->get_total()]; }
        }
        $zone = \WC_Shipping_Zones::get_zone_matching_package($package);
        $methods = array_values($zone->get_shipping_methods(true));
        foreach (WC()->shipping()->get_shipping_methods() as $method) {
            if (!$method->supports('shipping-zones')) { $methods[] = $method; }
        }
        $choices = [];
        foreach ($methods as $method) {
            if ($method->enabled !== 'yes') { continue; }
            $mode = DeliveryMode::fromShippingMethodIds([(string)$method->id]);
            if (!in_array($mode,[DeliveryMode::PICKUP,DeliveryMode::GLS],true)) { continue; }
            // Point/locker selection needs provider-specific location data, not a guessed home address.
            if ($mode === DeliveryMode::GLS && (str_contains($method->id,'parcel_shop') || str_contains($method->id,'parcel_locker'))) { continue; }
            if ($order->get_payment_method() === 'cod' && $mode !== DeliveryMode::PICKUP) { continue; }
            if (!$method->is_available($package)) { continue; }
            $countries = $method->get_option('supported_countries', []);
            // The legacy global GLS method only emits a rate for configured countries.
            // Zone instances instead inherit the matching WooCommerce zone.
            if ($mode === DeliveryMode::GLS && !$method->supports('shipping-zones')
                && (!is_array($countries) || !in_array($address['country'] ?? '', $countries, true))) { continue; }
            if ($mode === DeliveryMode::GLS && (empty($address['country']) || empty($address['city']) || empty($address['postcode']) || empty($address['address_1']))) { continue; }
            $key = $method->id . ':' . (int)$method->instance_id;
            $choices[$key] = ['method'=>$method->id,'instance'=>(int)$method->instance_id,'title'=>$method->get_title() ?: $method->method_title,
                'label'=>($method->get_title() ?: $method->method_title).' — '.$method->id.' / '.(int)$method->instance_id];
        }
        return $choices;
    }

    private function paymentReview(\WC_Order $order, array $facts): array
    {
        if ($facts['paid'] && $facts['payment'] !== 'cod') { return ['', 'A fizetés már rögzítve; nincs ismételt fizetésigazolási teendő.']; }
        if ($facts['payment'] === 'barion') {
            $block = BarionPaymentReview::block($order);
            $record = $order->get_meta('_appleklinika_barion_review',true);
            $last = is_array($record) && is_string($record['message'] ?? null) ? $record['message'].' ('.$record['at'].') ' : '';
            return [$block === null ? 'recheck_barion' : '', $last.($block ?? 'A Barion tényleges fizetési állapota újraellenőrizhető. A munkatárs nem jelölheti kézzel fizetettnek.')];
        }
        if (in_array($facts['payment'], ['bacs','cheque'],true)) {
            $block = OrderReview::manualPaymentBlock($facts);
            return [$block === null ? 'confirm_manual_payment' : '', $block ?? 'Csak a teljes összeg tényleges jóváírásának / befizetésének ellenőrzése után igazold. A WooCommerce payment_complete művelete rögzíti a fizetési időpontot és indítja a meglévő automatizmusokat.'];
        }
        if ($facts['payment'] === 'cod') {
            $payment = WooOrderPayment::describe($order, $facts['mode']);
            return ['', $payment['block'] ?? $payment['label'] . '. Előkészíthető előzetes fizetésigazolás nélkül.'];
        }
        return ['', 'A fizetési mód nem támogat biztonságos Back Office visszaigazolást ('.$facts['payment'].'). A megfelelő fizetési szolgáltató igazolása vagy a hibás fizetésimód-adat kivételes rendelésadminisztrációs javítása szükséges. Tranzakcióazonosítót nem találunk ki.'];
    }

    public function correctShipping(int $id, string $expected, string $choice, string $reason): string
    {
        $order = $this->order($id);
        if (!self::reviewEnvironment()) { throw new \InvalidArgumentException('A rendezés ebben a környezetben nincs engedélyezve.'); }
        $operation = hash('sha256', (string)wp_json_encode([$id, get_current_user_id(), $expected, $choice, $reason]));
        $journal = $order->get_meta(OrderReview::JOURNAL_KEY, true);
        if (is_array($journal) && ($journal['id'] ?? '') === $operation) { return $this->reconcileShipping($id, $operation); }
        if ($this->completedCorrection($order, $operation) !== null) { return 'Ez a javítás már korábban rögzült; ismételt módosítás nem történt.'; }
        $review = $this->review($id);
        if (!hash_equals($review['fingerprint'], $expected)) { throw new \InvalidArgumentException('A rendelés adatai közben megváltoztak. Frissítsd az oldalt és ellenőrizd újra.'); }
        if ($review['shipping_block'] !== null || !isset($review['choices'][$choice])) { throw new \InvalidArgumentException($review['shipping_block'] ?? 'A szállítási mód már nem alkalmazható.'); }
        $selected = $review['choices'][$choice];
        if ($this->matchesTarget($review['facts'], $review['facts'], $selected)) { throw new \InvalidArgumentException('Ez a szállítási mód már a rendelésen szerepel; nincs mentendő módosítás.'); }
        $user = get_userdata(get_current_user_id());
        $journal = ['id'=>$operation,'stage'=>'prepared','before'=>$review['facts'],'target'=>$selected,'choice'=>$choice,
            'user_id'=>get_current_user_id(),'user'=>$user ? $user->display_name : 'Ismeretlen munkatárs','at'=>current_time('mysql'),'reason'=>$reason];
        try { $this->saveJournal($id, $journal); }
        catch (\Throwable) { throw new \InvalidArgumentException('A javítás előzetes auditbejegyzését nem sikerült ellenőrzötten menteni. A szállítási adat módosítása nem indult el. Frissítsd az oldalt.'); }
        return $this->reconcileShipping($id, $operation);
    }

    public function reconcileShipping(int $id, string $operation): string
    {
        $order = $this->order($id);
        if (!self::reviewEnvironment()) { throw new \InvalidArgumentException('A rendezés ebben a környezetben nincs engedélyezve.'); }
        $journal = $order->get_meta(OrderReview::JOURNAL_KEY, true);
        if (!preg_match('/^[a-f0-9]{64}$/D', $operation) || !is_array($journal) || ($journal['id'] ?? '') !== $operation) { throw new \InvalidArgumentException('Az egyeztetendő javítás már nem aktuális. Frissítsd a rendelést.'); }
        if (($journal['stage'] ?? '') === 'complete') { return 'Ez a javítás és a naplója már rögzült; ismételt módosítás nem történt.'; }
        try {
            $completed = $this->completedCorrection($order, $operation);
            if ($completed === null) {
                $facts = $this->facts($order);
                if (!$this->matchesTarget($facts, $journal['before'], $journal['target'])) {
                    // Only an exact unchanged source may be replayed. Partial/conflicting writes fail closed.
                    if ($facts !== $journal['before']) { throw new \InvalidArgumentException('A rendelés eltér az eredeti és a tervezett állapottól. Részleges mentés vagy más módosítás miatt külön rendelésadminisztrációs egyeztetés szükséges; nem írunk felül adatot.'); }
                    $block = OrderReview::shippingBlock($facts);
                    $choices = $this->shippingChoices($order);
                    if ($block !== null || ($choices[$journal['choice']] ?? null) !== $journal['target']) { throw new \InvalidArgumentException($block ?? 'Az eredeti szállítási mód konfigurációja megváltozott. Külön egyeztetés szükséges; a régi döntést nem alkalmazzuk vakon.'); }
                    $this->applyShipping($order, $journal['target']);
                    $order = $this->order($id);
                    $facts = $this->facts($order);
                    if (!$this->matchesTarget($facts, $journal['before'], $journal['target'])) { throw new \RuntimeException('Unverified canonical shipping write.'); }
                }
                // A crash after the canonical item save must also repair this derived snapshot.
                $order->update_meta_data(OrderQueueQuery::SHIPPING_METHOD_META_KEY, $journal['target']['title']);
                $order->save_meta_data();
                if ($this->order($id)->get_meta(OrderQueueQuery::SHIPPING_METHOD_META_KEY, true) !== $journal['target']['title']) { throw new \RuntimeException('Unverified queue snapshot.'); }
            }
            $this->orders->recordShippingReview($this->order($id), $journal, $completed['review']['after'] ?? $this->facts($this->order($id)));
            $journal['stage'] = 'complete';
            $this->saveJournal($id, $journal);
            return 'Az átvételi mód javítása és a naplózása ellenőrzötten lezárult. A díjak és a végösszeg nem változtak.';
        } catch (\InvalidArgumentException $e) { throw $e; }
        catch (\Throwable) { throw new \InvalidArgumentException('A javítás előzetes bejegyzése megvan, de a teljes mentés / naplózás lezárása nem igazolt. A szállítási adat már megváltozhatott. Ne indíts új javítást: használd a „Mentés és napló egyeztetése” műveletet.'); }
    }

    private function saveJournal(int $id, array $journal): void
    {
        $order = $this->order($id);
        $order->update_meta_data(OrderReview::JOURNAL_KEY, $journal);
        $order->save_meta_data();
        if ($this->order($id)->get_meta(OrderReview::JOURNAL_KEY, true) !== $journal) { throw new \RuntimeException('Unverified correction journal.'); }
    }

    private function completedCorrection(\WC_Order $order, string $operation): ?array
    {
        foreach ($this->orders->history($order) as $entry) {
            if (($entry['action'] ?? '') === 'resolve_shipping' && ($entry['review_operation'] ?? '') === $operation) { return $entry; }
        }
        return null;
    }

    private function matchesTarget(array $actual, array $before, array $target): bool
    {
        foreach (['total','currency','shipping_total','shipping_tax','address_fingerprint'] as $key) {
            if (($actual[$key] ?? null) !== ($before[$key] ?? null)) { return false; }
        }
        if (count($actual['shipping']) !== 1) { return false; }
        $row = $actual['shipping'][0];
        $old = $before['shipping'][0] ?? null;
        return $row['method'] === $target['method'] && $row['instance'] === $target['instance'] && $row['title'] === $target['title']
            && ($old === null || $row['item_id'] === $old['item_id'])
            && wc_format_decimal($row['total'], 2) === wc_format_decimal($old['total'] ?? 0, 2)
            && wc_format_decimal($row['tax'], 2) === wc_format_decimal($old['tax'] ?? 0, 2);
    }

    private function applyShipping(\WC_Order $order, array $selected): void
    {
        $items = array_values($order->get_shipping_methods());
        $item = $items[0] ?? new \WC_Order_Item_Shipping();
        $item->set_method_id($selected['method']);
        $item->set_instance_id($selected['instance']);
        $item->set_method_title($selected['title']);
        if ($items === []) { $item->set_total('0'); $order->add_item($item); }
        else { $item->save(); }
        $order->update_meta_data(OrderQueueQuery::SHIPPING_METHOD_META_KEY, $selected['title']);
        $order->save(); // Intentionally no calculate_totals/rates: agreed money/taxes remain untouched.
    }

    public function confirmPayment(int $id): void
    {
        if (!self::reviewEnvironment()) { throw new \InvalidArgumentException('A rendezés ebben a környezetben nincs engedélyezve.'); }
        $order = $this->order($id);
        if (($block = OrderReview::manualPaymentBlock($this->facts($order))) !== null) { throw new \InvalidArgumentException($block); }
        $order->payment_complete(); // Native date/status/stock/hooks; no fabricated transaction identifier.
        $fresh = wc_get_order($id);
        if (!$fresh instanceof \WC_Order || !$fresh->is_paid() || !$fresh->get_date_paid()) { throw new \InvalidArgumentException('A WooCommerce nem igazolta a fizetés teljes rögzítését. A fizetési napló egyeztetése szükséges, ne ismételd meg vakon.'); }
    }

    public function recheckBarion(int $id): string { return BarionPaymentReview::recheck($this->order($id)); }

    public function record(int $id, string $action, array $before, string $reason, string $result): void
    {
        $order = $this->order($id);
        $this->orders->recordReview($order, $action, $before, $this->facts($order), $reason, $result);
    }

    private function order(int $id): \WC_Order
    {
        if (get_current_user_id() < 1 || (!current_user_can('manage_appleklinika_backoffice') && !current_user_can('manage_options'))) { throw new \InvalidArgumentException('A rendezéshez Back Office jogosultság szükséges.'); }
        $order = wc_get_order($id);
        if (!$order instanceof \WC_Order) { throw new \InvalidArgumentException('A rendelés nem található.'); }
        $order->read_meta_data(true); // Verify durable metadata, not this request's dirty/cache copy.
        return $order;
    }
}
