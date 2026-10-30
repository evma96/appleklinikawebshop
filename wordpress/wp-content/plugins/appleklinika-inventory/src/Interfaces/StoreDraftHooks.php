<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces;

use Appleklinika\Inventory\Application\Checkout\ReconcileProtectionDraft;
use Appleklinika\Inventory\Infrastructure\WordPress\StoreCartMutex;
use Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils;

final class StoreDraftHooks
{
    private ?\WP_Error $lockError = null;
    public function __construct(private readonly StoreCartMutex $mutex = new StoreCartMutex(),
        private readonly ReconcileProtectionDraft $reconcile = new ReconcileProtectionDraft()) {}

    public function register(): void
    {
        add_filter('woocommerce_session_handler', [$this, 'beforeSession'], PHP_INT_MAX);
        add_filter('rest_request_before_callbacks', [$this, 'lock'], -100, 3);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'repair']);
        add_filter('rest_request_after_callbacks', [$this, 'after'], 100, 3);
        // Woo writes the session at priority 20. Unlock only after that write.
        add_action('shutdown', [$this->mutex, 'release'], PHP_INT_MAX);
    }

    /** Lock BEFORE Woo loads session data, not merely before the REST callback. */
    public function beforeSession(string $handler): string
    {
        $route = is_string($_GET['rest_route'] ?? null) ? wp_unslash($_GET['rest_route']) : '';
        if ($route === '') {
            $path = (string)wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $prefix = '/' . rest_get_url_prefix();
            $pos = strpos($path, $prefix . '/');
            if ($pos !== false) $route = substr($path, $pos + strlen($prefix));
        }
        $request = new \WP_REST_Request('GET', $route);
        $request->set_header('Cart-Token', $_SERVER['HTTP_CART_TOKEN'] ?? '');
        $result = $this->lock(null, null, $request);
        if (is_wp_error($result)) $this->lockError = $result;
        return $handler;
    }

    private function isCart(\WP_REST_Request $request): bool
    {
        return (bool)preg_match('#^/wc/store/v[0-9]+/(?:cart(?:/|$)|checkout/?$)#', $request->get_route());
    }

    public function lock($response, $handler, \WP_REST_Request $request)
    {
        if ($response !== null || !$this->isCart($request)) return $response;
        if ($this->lockError) return $this->lockError;
        $token = (string)$request->get_header('Cart-Token');
        $customer = '';
        if ($token !== '' && CartTokenUtils::validate_cart_token($token)) {
            $customer = (string)CartTokenUtils::get_cart_token_payload($token)['user_id'];
        } elseif (is_user_logged_in()) {
            $customer = (string)get_current_user_id();
        } else {
            $cookie = (new \WC_Session_Handler())->get_session_cookie();
            if ($cookie) $customer = (string)$cookie[0];
        }
        // A visitor without a session cannot reference a shared checkout draft yet.
        if ($customer !== '' && !$this->mutex->acquire($customer)) {
            return new \WP_Error('ak_cart_busy', 'A kosár frissítése folyamatban van. Kérjük, próbáld újra.', ['status' => 409]);
        }
        return $response;
    }

    public function repair(\WC_Order $order): void
    {
        if (\WC()->cart instanceof \WC_Cart) $this->reconcile->execute($order, \WC()->cart);
    }

    public function after($response, $handler, \WP_REST_Request $request)
    {
        if (!is_wp_error($response) && (!$response instanceof \WP_REST_Response || $response->get_status() < 400)
            && $this->isCart($request) && \WC()->session && \WC()->cart instanceof \WC_Cart) {
            $order = wc_get_order((int)\WC()->session->get('store_api_draft_order', 0));
            if ($order) $this->repair($order);
        }
        return $response;
    }
}
