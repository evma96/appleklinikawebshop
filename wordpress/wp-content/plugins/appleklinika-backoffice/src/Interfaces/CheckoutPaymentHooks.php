<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Domain\CheckoutPaymentPolicy as Policy;

/** Woo-native configuration, availability and validation adapters; no payment processing. */
final class CheckoutPaymentHooks
{
    private const ERROR = 'A webshopos rendelés kizárólag online, Barionnal fizethető ki, személyes átvétel esetén is.';

    public function register(): void
    {
        foreach (['option_', 'default_option_'] as $prefix) {
            add_filter($prefix . 'woocommerce_bacs_settings', [$this, 'disabledSettings']);
        }
        // Keep the registered COD gateway/settings for staff cash recording and activation checks.
        // The webshop allowlist below excludes it regardless of its legacy enabled setting.
        add_filter('woocommerce_available_payment_gateways', [$this, 'gateways'], 100);
        add_action('woocommerce_after_checkout_validation', [$this, 'classicValidation'], 100, 2);
        add_action('woocommerce_checkout_validate_order_before_payment', [$this, 'orderValidation'], 100, 2);
        add_action('wp_enqueue_scripts', [$this, 'blocksScript']);
    }

    public function disabledSettings(mixed $settings): array
    {
        return array_replace((array) $settings, ['enabled' => 'no', 'description' => '', 'instructions' => '']);
    }

    public function gateways(array $gateways): array
    {
        foreach (array_keys($gateways) as $payment) {
            if (!Policy::allows($payment)) { unset($gateways[$payment]); }
        }
        if (WC()->session) {
            $chosen = (string) WC()->session->get('chosen_payment_method', '');
            if ($chosen !== '' && !isset($gateways[$chosen])) {
                WC()->session->set('chosen_payment_method', isset($gateways['barion']) ? 'barion' : '');
            }
        }
        return $gateways;
    }

    public function classicValidation(array $data, \WP_Error $errors): void
    {
        if (!Policy::allows((string) ($data['payment_method'] ?? ''))) {
            $errors->add('appleklinika_payment_shipping', self::ERROR);
        }
    }

    public function orderValidation(\WC_Order $order, \WP_Error $errors): void
    {
        if (!Policy::allows($order->get_payment_method())) {
            $errors->add('appleklinika_payment_shipping', self::ERROR);
        }
    }

    public function blocksScript(): void
    {
        if (!is_checkout()) { return; }
        wp_enqueue_script('appleklinika-checkout-payments', plugins_url('../../assets/checkout-payments.js', __FILE__), ['wc-blocks-registry'],
            (string) filemtime(dirname(__DIR__, 2) . '/assets/checkout-payments.js'), true);
    }
}
