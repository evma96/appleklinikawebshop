<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Reuses the client exposed by the installed gateway's supported init hook. */
final class BarionPaymentReview
{
    private static ?object $client = null;
    private static ?object $gateway = null;

    public static function capture(object $client, object $gateway): void
    {
        if ($gateway instanceof \WC_Gateway_Barion && is_callable([$client, 'GetPaymentState'])) {
            self::$client = $client;
            self::$gateway = $gateway;
        }
    }

    public static function block(\WC_Order $order): ?string
    {
        WC()->payment_gateways()->payment_gateways();
        if (!self::$client || !self::$gateway) { return 'A telepített Barion kapcsolat nem érhető el. A meglévő Barion bővítmény / fiók beállítását kell helyreállítani; kézi fizetetté jelölés nincs.'; }
        $valid = ProductionReadiness::production()
            ? ProductionReadiness::allowed() && self::$gateway->get_option('environment') === 'live'
            : WooOrderReview::testEnvironment() && self::$gateway->get_option('environment') === 'test';
        if (!$valid) { return 'A Barion újraellenőrzéséhez igazolt, az adott környezethez engedélyezett kapcsolat szükséges.'; }
        if (!self::$gateway->get_barion_payment_id($order)) { return 'Nincs Barion fizetésazonosító a rendelésen. Az ügyfélnek a rendelés fizetési oldalán valódi Barion-fizetést kell indítania; kézi fizetetté jelölés nem helyettesítheti.'; }
        return null;
    }

    public static function recheck(\WC_Order $order): string
    {
        if (!current_user_can('manage_appleklinika_backoffice') && !current_user_can('manage_options')) {
            throw new \InvalidArgumentException('Nincs rendelés-egyeztetési jogosultság.');
        }
        return (new WooOrderMutex())->synchronized($order->get_id(), static fn() => self::recheckLocked($order));
    }

    private static function recheckLocked(\WC_Order $order): string
    {
        if (($block = self::block($order)) !== null) { throw new \InvalidArgumentException($block); }
        $id = (string)self::$gateway->get_barion_payment_id($order);
        try { $response = self::$client->GetPaymentState($id); }
        catch (\Throwable) { return self::remember($order, 'unavailable', 'A Barion állapota nem kérdezhető le. Kapcsolati hiba; fizetési adat nem változott, később újraellenőrizhető.'); }
        if (!is_object($response) || !empty($response->Errors)) {
            $codes = [];
            foreach ((array)($response->Errors ?? []) as $error) {
                $code = $error->ErrorCode ?? '';
                if (is_string($code) && preg_match('/^[A-Za-z0-9_.-]{1,80}$/D', $code)) { $codes[] = $code; }
            }
            return self::remember($order, 'error', 'Barion lekérdezési hiba' . ($codes ? ': '.implode(', ', array_slice($codes, 0, 3)) : '') . '. A kapcsolatot/fiókot kell ellenőrizni; a rendelést nem jelöltük fizetettnek.');
        }
        $fresh = wc_get_order($order->get_id());
        if (!$fresh instanceof \WC_Order || $fresh->get_payment_method() !== 'barion'
            || (string)self::$gateway->get_barion_payment_id($fresh) !== $id
            || (string)($response->PaymentId ?? '') !== $id
            || !in_array((string)($response->PaymentRequestId ?? ''), [(string)$fresh->get_id(), (string)$fresh->get_order_number()], true)
            || (string)($response->Currency ?? '') !== $fresh->get_currency()
            || !is_numeric($response->Total ?? null)
            || wc_format_decimal($response->Total, 2) !== wc_format_decimal($fresh->get_total(), 2)) {
            return self::remember($order, 'mismatch', 'A Barion válasz rendelésazonosítója, összege vagy pénzneme nem egyezik. Szolgáltatói egyeztetés szükséges; fizetési adat nem változott.');
        }
        $state = (string)($response->Status ?? '');
        if ($state !== 'Succeeded') {
            $message = match ($state) {
                'Prepared','Started','InProgress','Waiting' => 'A fizetés még nem fejeződött be. Az ügyfélnek a Barion-fizetést kell befejeznie, utána újraellenőrizhető.',
                'Canceled','Expired','Failed' => 'A fizetés sikertelen, megszakított vagy lejárt. Az ügyfélnek új, sikeres Barion-fizetés szükséges.',
                'Reserved','Authorized' => 'A foglalás/engedélyezés nem lezárt fizetés. A Barionban a fizetés lezárását kell rendezni.',
                default => 'A Barion állapota nem igazol lezárt fizetést. Szolgáltatói egyeztetés szükséges.',
            };
            $state = preg_match('/^[A-Za-z]{1,30}$/D', $state) ? $state : 'unknown';
            return self::remember($fresh, $state, 'Barion: '.$state.'. '.$message);
        }
        if ($fresh->is_paid()) { return self::remember($fresh, $state, 'Barion: Succeeded. A WooCommerce fizetés már rögzítve; új visszaigazolás nem történt.'); }
        if (has_filter('woocommerce_barion_custom_callback_handler')) {
            return self::remember($fresh, $state, 'Barion: Succeeded, de egyedi visszahívás-kezelő aktív. Annak feldolgozását kell egyeztetni; a Back Office nem kerüli meg az egyedi fizetési szabályokat.');
        }
        if (!in_array($fresh->get_status(), ['pending','failed','cancelled'], true)) {
            return self::remember($fresh, $state, 'Barion: Succeeded, de a rendelés állapota vagy a szolgáltató lezárási jelzője miatt a telepített Barion-kezelő nem enged biztonságos ismétlést. A visszahívás és a rendelés fizetési naplójának szolgáltatói egyeztetése szükséges.');
        }
        $transaction = '';
        foreach ((array)($response->Transactions ?? []) as $row) {
            if ((string)($row->POSTransactionId ?? '') === (string)$fresh->get_id() && is_string($row->TransactionId ?? null)) { $transaction = $row->TransactionId; break; }
        }
        if ($transaction === '') { return self::remember($fresh, $state, 'Barion: Succeeded, de nincs a rendeléshez illeszkedő valódi tranzakcióazonosító. Szolgáltatói egyeztetés szükséges.'); }
        // A closed callback marker alone is not payment evidence. The existing
        // native completion path still runs the shared stock/reservation admission.
        // Interrupted/late payments can only proceed after this independent check.
        try { self::$gateway->payment_complete($fresh, $transaction); }
        catch (\Throwable) {
            return self::remember(wc_get_order($fresh->get_id()), 'reconciliation_required',
                'A Barion igazolja a fizetést, de a készlet vagy a feldolgozás egyeztetést igényel. Ne indíts új fizetést vagy számlát; ellenőrizd a rendelés kivételnaplóját.');
        }
        $fresh = wc_get_order($fresh->get_id());
        if ($fresh->is_paid()) { $fresh->update_meta_data('_barion_payment_close', 1); $fresh->save_meta_data(); }
        return self::remember($fresh, $state, $fresh->is_paid()
            ? 'Barion: Succeeded. A szolgáltatótól ellenőrzött fizetést a meglévő Barion-integráció rögzítette.'
            : 'Barion: Succeeded, de a WooCommerce visszaigazolás nem teljes. A visszahívás naplójának egyeztetése szükséges; új fizetést nem indítottunk.');
    }

    private static function remember(\WC_Order $order, string $state, string $message): string
    {
        $order->update_meta_data('_appleklinika_barion_review', ['state'=>$state,'message'=>$message,'at'=>current_time('mysql'),'actor'=>get_current_user_id()]);
        $order->save_meta_data();
        return $message;
    }
}
