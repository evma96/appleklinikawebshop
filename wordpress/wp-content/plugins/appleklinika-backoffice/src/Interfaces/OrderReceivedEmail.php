<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;
use Appleklinika\BackOffice\Domain\CustomerNotification;
final class OrderReceivedEmail extends LifecycleEmail
{
    public function __construct() { parent::__construct(CustomerNotification::RECEIVED); }
}
