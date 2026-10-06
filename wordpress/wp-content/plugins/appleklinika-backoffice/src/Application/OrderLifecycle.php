<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\CustomerMailer;
use Appleklinika\BackOffice\Application\Port\InvoiceAutomation;
use Appleklinika\BackOffice\Application\Port\OrderLifecycleStore;
use Appleklinika\BackOffice\Application\Port\OrderMutex;
use Appleklinika\BackOffice\Domain\CustomerNotification;

final class OrderLifecycle
{
    public function __construct(
        private readonly OrderLifecycleStore $store,
        private readonly OrderMutex $mutex,
        private readonly InvoiceAutomation $invoices,
        private readonly CustomerMailer $mailer,
    ) {
    }

    public function paymentConfirmed(int $id): void
    {
        $this->mutex->synchronized($id, function () use ($id): void {
            $order = $this->store->order($id);
            if ($order === null || ! $order->managed || ! $order->paid || in_array($order->status, ['cancelled', 'failed', 'refunded'], true)) {
                return;
            }
            if ($order->invoiceNumber !== '') {
                $this->store->invoiceState($id, $order->invoicePath !== '' ? 'ready' : 'document_missing');
                if ($order->invoicePath === '') {
                    $this->store->issue($id, 'invoice_pdf_missing');
                }
                return;
            }
            // A lost response may already have created a provider document. Reconcile
            // with the provider before explicitly clearing this durable attempt record.
            if (in_array($this->store->invoiceRecord($id)['state'] ?? '', ['generating', 'failed', 'uncertain'], true)) {
                $this->store->issue($id, 'invoice_reconciliation_required');
                return;
            }

            if (! $this->invoices->ready()) {
                $this->store->invoiceState($id, 'blocked', 'automation_configuration');
                $this->store->issue($id, 'invoice_automation_not_ready');
                return;
            }
            $this->store->invoiceState($id, 'generating');
            try {
                $this->invoices->run($id);
            } catch (\Throwable) {
                // Never persist a provider exception containing credentials or customer data.
                $this->store->invoiceState($id, 'uncertain', 'provider_exception');
                $this->store->issue($id, 'invoice_generation_failed');
                return;
            }
            $updated = $this->store->order($id);
            if ($updated === null || $updated->invoiceNumber === '' || $updated->invoicePath === '') {
                $this->store->invoiceState($id, 'failed', 'document_not_available');
                $this->store->issue($id, 'invoice_generation_failed');
                return;
            }
            $this->store->invoiceState($id, 'ready');
        });
    }

    /** Returns accepted, failed, ineligible, or skipped; no routine state-change emails. */
    public function notify(int $id, string $event): string
    {
        return $this->mutex->synchronized($id, function () use ($id, $event): string {
            $order = $this->store->order($id);
            if ($order === null || ! CustomerNotification::eligible($event, $order)) {
                return 'ineligible';
            }
            $record = $this->store->notification($id, $event);
            if (! CustomerNotification::mayAttempt($record)) {
                return 'skipped';
            }
            $record = ['state' => 'sending', 'attempts' => (int) ($record['attempts'] ?? 0) + 1];
            $this->store->recordNotification($id, $event, $record);
            try {
                $accepted = $this->mailer->send($id, $event);
            } catch (\Throwable) {
                $record['state'] = 'uncertain';
                $this->store->recordNotification($id, $event, $record);
                $this->store->issue($id, 'email_acceptance_uncertain_' . $event);
                return 'uncertain';
            }
            $record['state'] = $accepted ? 'accepted' : 'failed';
            $this->store->recordNotification($id, $event, $record);
            if (! $accepted) {
                $this->store->issue($id, 'email_transport_failed_' . $event);
            }
            return $record['state'];
        });
    }
}
