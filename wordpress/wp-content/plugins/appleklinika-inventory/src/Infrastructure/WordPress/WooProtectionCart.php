<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Application\Protection\SelectProtection;

/** Only the explicit add-to-cart request owns accessory creation. No payment/provider lifecycle hooks. */
final class WooProtectionCart
{
    private array $before = [];
    private array $input = [];
    private int $deviceId = 0;
    private bool $appending = false;

    public function __construct(private readonly SelectProtection $selection) {}

    public function prepare(\WC_Cart $cart, int $deviceId, array $input): void
    {
        if ($this->appending) return;
        $this->selection->handle($deviceId, $input);
        $this->before = $cart->get_cart();
        $this->input = $input;
        $this->deviceId = $deviceId;
    }

    public function append(\WC_Cart $cart, int $deviceId, string $parentKey): void
    {
        if ($this->appending || $this->deviceId !== $deviceId) return;
        $this->appending = true;
        try {
            // Re-read current availability and Woo price; validate everything before adding any extra.
            if (!isset($cart->get_cart()[$parentKey]) || (int) $cart->get_cart()[$parentKey]['product_id'] !== $deviceId) {
                throw new \RuntimeException('A készülék nem található a kosárban.');
            }
            foreach ($this->selection->handle($deviceId, $this->input) as $id) {
                if (!$cart->add_to_cart($id, 1, 0, [], [WooProtectionAssociation::PARENT => $parentKey])) {
                    throw new \RuntimeException('A kiegészítő nem került a kosárba. Kérjük, ellenőrizd a választást és próbáld újra.');
                }
            }
        } catch (\Throwable $error) {
            // Restore quantities/items as they were before the whole device + extras request.
            // Woo's add_to_cart catches this exception and returns its normal error response.
            $cart->set_cart_contents($this->before);
            $cart->calculate_totals();
            // Earlier native add hooks may already have saved a partial logged-in cart.
            (new \WC_Cart_Session($cart))->persistent_cart_update();
            throw new \RuntimeException($error->getMessage(), 0, $error);
        } finally {
            $this->appending = false;
            $this->deviceId = 0;
            $this->input = $this->before = [];
        }
    }
}
