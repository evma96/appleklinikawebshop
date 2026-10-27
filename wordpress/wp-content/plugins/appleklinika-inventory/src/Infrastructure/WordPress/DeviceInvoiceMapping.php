<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;

/** Supported Szamlazz XML hooks only; never invokes invoice transport. */
final class DeviceInvoiceMapping
{
    public function __construct(private readonly DeviceOrderIdentity $identity = new DeviceOrderIdentity())
    {
    }

    public function line(mixed $line, mixed $item, mixed $order = null, mixed $xml = null): mixed
    {
        if (!$item instanceof \WC_Order_Item_Product) {
            return $line;
        }
        $identity = $this->identity->lineNote($item);
        if ($identity === '') {
            return $line;
        }
        $note = (string) $line->megjegyzes;
        foreach (explode("\n", $identity) as $row) {
            if (!in_array($row, explode("\n", $note), true)) {
                $note = trim($note . "\n" . $row);
            }
        }
        $line->megjegyzes = $note;
        return $line;
    }

    public function document(mixed $xml, \WC_Order $order, string $type): mixed
    {
        if ($type !== 'invoice') {
            return $xml;
        }
        foreach ($order->get_items('line_item') as $item) {
            if ($item->get_meta(PhysicalDevice::MARKER) === 'yes') {
                $xml->fejlec->megjegyzes = PhysicalDevice::NOTE;
                break;
            }
        }
        return $xml;
    }
}
