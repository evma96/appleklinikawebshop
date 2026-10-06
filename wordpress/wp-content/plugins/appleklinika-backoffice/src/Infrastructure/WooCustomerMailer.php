<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\CustomerMailer;
use Appleklinika\BackOffice\Interfaces\LifecycleEmail;

final class WooCustomerMailer implements CustomerMailer
{
    public function send(int $id, string $event): bool
    {
        $emails = WC()->mailer()->get_emails();
        $email = $emails['appleklinika_' . $event] ?? null;
        return $email instanceof LifecycleEmail && $email->deliver($id);
    }
}
