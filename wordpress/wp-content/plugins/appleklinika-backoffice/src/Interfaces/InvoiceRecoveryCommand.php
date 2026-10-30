<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

/** Explicit private operator CLI; WP --user capability is mandatory. No public endpoint. */
final class InvoiceRecoveryCommand
{
    public function __invoke(array $args, array $flags): void
    {
        try {
            $result = (new \Appleklinika\BackOffice\Infrastructure\ExistingInvoiceRecovery())->recover(
                (int)($flags['order'] ?? 0), (string)($flags['invoice'] ?? ''),
                (string)($flags['confirmed-provider-order'] ?? ''), (string)($flags['reason'] ?? ''));
            \WP_CLI::success($result);
        } catch (\Throwable) {
            \WP_CLI::error('Existing invoice recovery blocked or incomplete. Verify operator, order/payment, provider linkage, licence, account and PDF evidence. No new invoice was requested.');
        }
    }
}
