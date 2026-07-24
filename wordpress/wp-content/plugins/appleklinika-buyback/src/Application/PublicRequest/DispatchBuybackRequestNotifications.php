<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\PublicRequest;

use AppleKlinika\Buyback\Application\Port\BuybackRequestMailer;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PublicRequestMailEventStore;

/** Sends notification attempts once and records their non-fatal outcome. */
final class DispatchBuybackRequestNotifications
{
    public function __construct(
        private readonly BuybackRequestMailer $mailer,
        private readonly PublicRequestMailEventStore $store,
        private readonly Clock $clock
    ) {
    }

    /** @param array<string,mixed> $input */
    public function dispatch(PublicBuybackSubmissionResult $result, array $input): void
    {
        if ($result->alreadySubmitted) {
            return;
        }

        $token = (string) ($input['idempotency_token'] ?? '');
        $detail = $this->store->findBySubmissionToken(hash('sha256', $token));
        if ($detail === null) {
            return;
        }

        try {
            $customerSent = $this->mailer->sendCustomer($result, $input);
        } catch (\Throwable) {
            $customerSent = false;
        }
        try {
            $adminSent = $this->mailer->sendAdmin($result, $input);
        } catch (\Throwable) {
            $adminSent = false;
        }

        $timestamp = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $requestId = (int) $detail['id'];
        $this->store->recordOperationalEvent($requestId, 'mail_customer_' . ($customerSent ? 'sent' : 'failed'), 'Ügyfélértesítés feldolgozva.', ['delivered' => $customerSent], hash('sha256', 'customer-mail:' . $token), $timestamp);
        $this->store->recordOperationalEvent($requestId, 'mail_admin_' . ($adminSent ? 'sent' : 'failed'), 'Admin értesítés feldolgozva.', ['delivered' => $adminSent], hash('sha256', 'admin-mail:' . $token), $timestamp);
    }
}
