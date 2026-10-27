<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces\Frontend;

use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCatalog;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCart;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionAssociation;

final class ProductProtection
{
    public function __construct(private readonly WooProtectionCatalog $catalog, private readonly WooProtectionCart $cart, private readonly WooProtectionAssociation $association) {}

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 20, 3);
        add_action('woocommerce_add_to_cart', [$this, 'added'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('woocommerce_get_item_data', [$this->association, 'cartItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this->association, 'copyToOrder'], 20, 4);
        add_action('woocommerce_cart_item_removed', [$this->association, 'afterRemovalOrRestore'], 5, 2);
        add_action('woocommerce_cart_item_restored', [$this->association, 'afterRemovalOrRestore'], 5, 2);
        add_action('woocommerce_cart_loaded_from_session', [$this->association, 'pruneOrphans'], 5);
    }

    public function assets(): void
    {
        if (!is_product()) return;
        $path = dirname(__DIR__, 3) . '/assets/product-protection.css';
        wp_enqueue_style('appleklinika-product-protection', plugins_url('assets/product-protection.css', dirname(__DIR__, 3) . '/appleklinika-inventory.php'), [], (string) filemtime($path));
    }

    public function validate(bool $valid, int $id, $quantity): bool
    {
        if (!$valid || !isset($_POST['ak_protection'])) return $valid;
        try {
            if (!is_array($_POST['ak_protection']) || !isset($_POST['ak_protection_nonce']) || !is_string($_POST['ak_protection_nonce'])
                || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ak_protection_nonce'])), 'ak_protection_' . $id)) {
                throw new \InvalidArgumentException('A választás lejárt. Kérjük, frissítsd a termékoldalt.');
            }
            $this->cart->prepare(WC()->cart, $id, wp_unslash($_POST['ak_protection']));
            return true;
        } catch (\InvalidArgumentException $error) {
            wc_add_notice($error->getMessage(), 'error');
            return false;
        }
    }

    public function added(string $key, int $id): void
    {
        if (WC()->cart instanceof \WC_Cart) $this->cart->append(WC()->cart, $id, $key);
    }

    public function render(): void
    {
        global $product;
        if (!$product instanceof \WC_Product) return;
        $options = $this->catalog->forDevice($product->get_id());
        if ($options === []) return;
        echo '<section class="ak-protection" aria-label="Extra védelem"><h2>Extra védelem</h2><p class="ak-protection__intro">Tedd teljessé a készüléked.</p>';
        wp_nonce_field('ak_protection_' . $product->get_id(), 'ak_protection_nonce', false);
        foreach (['glass' => 'Üvegfólia', 'case' => 'Tok'] as $group => $title) {
            $items = array_filter($options, static fn(array $item): bool => $item['group'] === $group);
            if ($items === []) continue;
            echo '<fieldset><legend>' . esc_html($title) . '</legend><div class="ak-protection__choices ak-protection__choices--radio">';
            echo '<label class="ak-protection__option is-none"><input type="radio" name="ak_protection[' . esc_attr($group) . ']" value="0" checked><span class="ak-protection__indicator" aria-hidden="true"></span><span class="ak-protection__content"><span class="ak-protection__name">Nincs</span></span></label>';
            foreach ($items as $item) $this->option($item, 'radio');
            echo '</div></fieldset>';
        }
        $independent = array_filter($options, static fn(array $item): bool => in_array($item['group'], ['camera', 'adapter'], true));
        if ($independent !== []) {
            echo '<fieldset><legend>További kiegészítők</legend><div class="ak-protection__choices ak-protection__choices--independent">';
            foreach ($independent as $item) $this->option($item, 'checkbox');
            echo '</div></fieldset>';
        }
        echo '</section>';
    }

    private function option(array $item, string $type): void
    {
        echo '<label class="ak-protection__option' . (!$item['available'] ? ' is-unavailable' : '') . '"><input type="' . esc_attr($type) . '" name="ak_protection[' . esc_attr($item['group']) . ']" value="' . esc_attr((string) $item['id']) . '"' . disabled(!$item['available'], true, false) . '><span class="ak-protection__indicator" aria-hidden="true"></span><span class="ak-protection__content"><span class="ak-protection__name">' . esc_html($item['name']) . '</span><small class="ak-protection__price">' . ($item['available'] ? '+ ' . wp_kses_post($item['price_html']) : 'Jelenleg nem elérhető') . '</small></span></label>';
    }
}
