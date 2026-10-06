<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Domain\CustomerNotification;
use Appleklinika\BackOffice\Infrastructure\OrderDocuments;
use Appleklinika\BackOffice\Infrastructure\LifecycleEmailPresentation;

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

    /** A plain-text alternative by default; preserve any explicitly saved Woo format. */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->form_fields['email_type']['default'] = 'multipart';
    }

    public function get_content_html()
    {
        return $this->renderView('lifecycle.php');
    }

    public function get_content_plain()
    {
        return $this->renderView('plain/lifecycle.php');
    }

    private function renderView(string $template): string
    {
        if (! $this->object instanceof \WC_Order) {
            return '';
        }
        $view = (new LifecycleEmailPresentation())->forOrder($this->object, $this->event);
        $heading = $this->get_heading();
        ob_start();
        include dirname(__DIR__, 2) . '/templates/emails/' . $template;
        return (string) ob_get_clean();
    }
}
