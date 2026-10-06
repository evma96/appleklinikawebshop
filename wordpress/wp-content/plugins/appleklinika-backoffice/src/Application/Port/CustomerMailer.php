<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Application\Port;

interface CustomerMailer
{
    /** True means the transport accepted the message, not that an inbox received it. */
    public function send(int $orderId, string $event): bool;
}
