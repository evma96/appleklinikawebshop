<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Interfaces\Cli;

use AppleKlinika\CustomerAddressBook\Application\Handler\LegacyAddressImporter;

final class MigrateLegacyAddressesCommand
{
    public function __construct(private readonly LegacyAddressImporter $importer) {}

    /** @param array<int, string> $args @param array<string, mixed> $assocArgs */
    public function __invoke(array $args, array $assocArgs): void
    {
        $dryRun = (bool) (\WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false));
        $userId = isset($assocArgs['user']) ? absint($assocArgs['user']) : 0;
        $users = $userId > 0 ? [$userId] : get_users(['fields' => 'ids']);
        $totals = ['imported' => 0, 'merged' => 0, 'needs_review' => 0, 'skipped' => 0, 'already_migrated' => 0, 'invalid' => 0];
        foreach ($users as $id) {
            foreach ($this->importer->import((int) $id, $dryRun) as $key => $count) {
                $totals[$key] += $count;
            }
        }
        \WP_CLI::success(wp_json_encode($totals, JSON_UNESCAPED_UNICODE));
    }
}
