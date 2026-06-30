<?php
/**
 * Apple Klinika account orders.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined('ABSPATH') || exit;

$wp_button_class = isset($wp_button_class) ? (string) $wp_button_class : '';

do_action('woocommerce_before_account_orders', $has_orders);
?>

<section class="ak-account-orders">
    <header class="ak-account-orders__header">
        <div>
            <p class="ak-account-section-kicker">Rendelések</p>
            <h2>Rendeléseim</h2>
        </div>
    </header>

    <?php if ($has_orders) : ?>
        <div class="ak-account-orders__list">
            <?php foreach ($customer_orders->orders as $customer_order) : ?>
                <?php
                $order = wc_get_order($customer_order);

                if (! $order instanceof WC_Order) {
                    continue;
                }

                $items = array_values($order->get_items());
                $first_item = $items[0] ?? null;
                $product = $first_item instanceof WC_Order_Item_Product ? $first_item->get_product() : null;
                $item_count = max(0, $order->get_item_count() - $order->get_item_count_refunded());
                $extra_count = max(0, $item_count - 1);
                $product_name = $first_item instanceof WC_Order_Item_Product
                    ? $first_item->get_name()
                    : sprintf('Rendelés #%s', $order->get_order_number());
                $status = $order->get_status();
                $status_label = wc_get_order_status_name($status);
                $status_class = in_array($status, ['completed'], true)
                    ? 'is-complete'
                    : (in_array($status, ['processing', 'pending', 'on-hold'], true) ? 'is-active' : 'is-muted');
                $actions = wc_get_account_orders_actions($order);
                ?>
                <article class="ak-account-order-card ak-account-order-card--<?php echo esc_attr($status); ?>">
                    <a class="ak-account-order-card__thumb" href="<?php echo esc_url($order->get_view_order_url()); ?>" aria-label="<?php echo esc_attr(sprintf('Rendelés #%s megtekintése', $order->get_order_number())); ?>">
                        <?php
                        if ($product instanceof WC_Product) {
                            echo wp_kses_post($product->get_image('woocommerce_thumbnail'));
                        } else {
                            echo wp_kses_post(wc_placeholder_img('woocommerce_thumbnail'));
                        }
                        ?>
                    </a>

                    <div class="ak-account-order-card__body">
                        <div class="ak-account-order-card__meta">
                            <span class="ak-account-order-card__status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                            <span class="ak-account-order-card__number">#<?php echo esc_html($order->get_order_number()); ?></span>
                        </div>

                        <h3>
                            <a href="<?php echo esc_url($order->get_view_order_url()); ?>"><?php echo esc_html($product_name); ?></a>
                        </h3>

                        <div class="ak-account-order-card__details">
                            <?php if ($order->get_date_created()) : ?>
                                <span><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></span>
                            <?php endif; ?>
                            <?php if ($extra_count > 0) : ?>
                                <span>+<?php echo esc_html((string) $extra_count); ?> termék</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ak-account-order-card__summary">
                        <div class="ak-account-order-card__total"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></div>

                        <?php if (! empty($actions)) : ?>
                            <div class="ak-account-order-card__actions">
                                <?php foreach ($actions as $key => $action) : ?>
                                    <?php
                                    $label = $key === 'view' ? 'Megtekintés' : $action['name'];
                                    $aria_label = ! empty($action['aria-label'])
                                        ? $action['aria-label']
                                        : sprintf('%s rendelés #%s', $label, $order->get_order_number());
                                    ?>
                                    <a
                                        href="<?php echo esc_url($action['url']); ?>"
                                        class="woocommerce-button<?php echo esc_attr($wp_button_class); ?> button <?php echo esc_attr(sanitize_html_class($key)); ?>"
                                        aria-label="<?php echo esc_attr($aria_label); ?>"
                                    >
                                        <?php echo esc_html($label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php do_action('woocommerce_before_account_orders_pagination'); ?>

        <?php if (1 < $customer_orders->max_num_pages) : ?>
            <div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination ak-account-pagination">
                <?php if (1 !== $current_page) : ?>
                    <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button<?php echo esc_attr($wp_button_class); ?>" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page - 1)); ?>">Előző</a>
                <?php endif; ?>

                <?php if ((int) $customer_orders->max_num_pages !== $current_page) : ?>
                    <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button<?php echo esc_attr($wp_button_class); ?>" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page + 1)); ?>">Következő</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="ak-account-empty">
            <h3>Még nincs rendelésed.</h3>
            <p>Ha találsz egy megfelelő készüléket, itt tudod majd követni a rendeléseidet.</p>
            <a class="ak-account-empty__button" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">Termékek megtekintése</a>
        </div>
    <?php endif; ?>
</section>

<?php do_action('woocommerce_after_account_orders', $has_orders); ?>
