<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Domain\CustomerNotification;
use Appleklinika\BackOffice\Infrastructure\OrderDocuments;

/** Woo owns rendering, sender configuration and transport. No independent mail client. */
abstract class LifecycleEmail extends \WC_Email
{
    public function __construct(private readonly string $event)
    {
        $this->id = 'appleklinika_' . $event;
        $this->title = $event === CustomerNotification::PAID ? 'Apple Klinika – fizetett rendelés és számla' : 'Apple Klinika – GLS-átadás';
        $this->description = 'Egyszeri értesítés a rendelés naplózott életciklusából. Az elfogadott küldés nem jelent igazolt kézbesítést.';
        $this->customer_email = true;
        $this->heading = $this->subject = $event === CustomerNotification::PAID ? 'Köszönjük, megkaptuk a rendelésed!' : 'Úton van a rendelésed!';
        parent::__construct();
    }

    public function deliver(int $id): bool
    {
        $this->object = wc_get_order($id);
        if (! $this->object instanceof \WC_Order || ! $this->is_enabled()) {
            return false;
        }
        $this->recipient = $this->object->get_billing_email();
        if (! is_email($this->recipient)) {
            return false;
        }
        $attachments = [];
        if ($this->event === CustomerNotification::PAID) {
            $path = (new OrderDocuments())->filePath($this->object, 'invoice');
            if ($path === null) {
                return false;
            }
            $attachments[] = $path;
        }
        $this->setup_locale();
        try {
            return (bool) $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $attachments);
        } finally {
            $this->restore_locale();
        }
    }

    public function get_content_html()
    {
        if (! $this->object instanceof \WC_Order) {
            return '';
        }
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo '<p>Rendelés: <strong>#' . esc_html($this->object->get_order_number()) . '</strong></p>';
        if ($this->event === CustomerNotification::PAID) {
            echo '<p>A fizetésed sikeresen megérkezett. A számlát a levélhez csatoltuk.</p>';
            do_action('woocommerce_email_order_details', $this->object, false, false, $this);
            do_action('woocommerce_email_customer_details', $this->object, false, false, $this);
        } else {
            echo '<p>A csomagodat átadtuk a GLS futárszolgálatnak.</p>';
            foreach ((new OrderDocuments())->trackingLinks($this->object) as $link) {
                echo '<p>GLS csomagszám: <a href="' . esc_url($link['url']) . '">' . esc_html($link['code']) . '</a></p>';
            }
        }
        echo '<p><a href="' . esc_url($this->object->get_view_order_url()) . '">Rendelés megtekintése a fiókomban</a></p>';
        do_action('woocommerce_email_footer', $this);
        return (string) ob_get_clean();
    }

    public function get_content_plain()
    {
        if (! $this->object instanceof \WC_Order) {
            return '';
        }
        ob_start();
        echo $this->get_heading() . "\nRendelés: #" . $this->object->get_order_number() . "\n\n";
        if ($this->event === CustomerNotification::PAID) {
            echo "A fizetésed sikeresen megérkezett. A számlát a levélhez csatoltuk.\n\n";
            do_action('woocommerce_email_order_details', $this->object, false, true, $this);
            do_action('woocommerce_email_customer_details', $this->object, false, true, $this);
        } else {
            echo "A csomagodat átadtuk a GLS futárszolgálatnak.\n";
            foreach ((new OrderDocuments())->trackingLinks($this->object) as $link) {
                echo 'GLS csomagszám: ' . $link['code'] . "\n" . $link['url'] . "\n";
            }
        }
        echo "\nRendelés megtekintése a fiókomban: " . $this->object->get_view_order_url() . "\n";
        return (string) ob_get_clean();
    }
}
