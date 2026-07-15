<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Cli;

use AppleKlinika\Buyback\Application\Legacy\LegacyReport;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportExitPolicy;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportService;

final class LegacyReportCommand
{
    public function __construct(
        private readonly LegacyReportService $reports,
        private readonly LegacyReportExitPolicy $exitPolicy
    ) {
    }

    /**
     * Produce a read-only report for legacy buyback user-meta records.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Supported values: table, json. Default: table.
     *
     * [--user-id=<id>]
     * : Limit the report to one positive WordPress user ID.
     *
     * [--strict]
     * : Exit non-zero when invalid or manual-mapping records exist.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $format = strtolower((string) ($assocArgs['format'] ?? 'table'));

        if (! in_array($format, ['table', 'json'], true)) {
            \WP_CLI::error('Unsupported format. Use table or json.');
            return;
        }

        $userId = null;

        if (isset($assocArgs['user-id'])) {
            $userId = filter_var($assocArgs['user-id'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if (! is_int($userId)) {
                \WP_CLI::error('The --user-id value must be a positive integer.');
                return;
            }
        }

        $report = $this->reports->report($userId);

        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));
        } else {
            $this->table($report);
        }

        $exitCode = $this->exitPolicy->exitCode($report, array_key_exists('strict', $assocArgs));

        if ($exitCode !== 0) {
            \WP_CLI::halt($exitCode);
        }
    }

    private function table(LegacyReport $report): void
    {
        $summary = $report->toArray()['summary'];

        \WP_CLI::line(sprintf(
            'Users: %d | Records: %d | Importable: %d | Manual: %d | Invalid: %d | Existing: %d',
            $summary['users_scanned'],
            $summary['legacy_records_found'],
            $summary['importable_count'],
            $summary['needs_manual_mapping_count'],
            $summary['invalid_count'],
            $summary['already_present_count']
        ));
        \WP_CLI::line('USER\tRECORD\tMARKER\tCLASSIFICATION\tISSUES');

        foreach ($report->items as $item) {
            \WP_CLI::line(implode("\t", [
                (string) $item->ownerUserId,
                (string) $item->legacyRecordId,
                (string) $item->marker,
                $item->classification,
                implode(',', $item->issueCodes),
            ]));
        }

        \WP_CLI::line('Writes: 0 | Source modifications: 0');
    }
}
