<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Cli;

use AppleKlinika\Buyback\Application\Benchmark\BenchmarkPriceBookSeedService;
use AppleKlinika\Buyback\Application\Benchmark\BenchmarkSeedPlan;
use AppleKlinika\Buyback\Application\Benchmark\BenchmarkSeedResult;
use AppleKlinika\Buyback\Application\Port\BenchmarkManifestLoader;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

final class PriceBookSeedCommand
{
    public function __construct(
        private readonly BenchmarkManifestLoader $loader,
        private readonly BenchmarkPriceBookSeedService $seeds
    ) {
    }

    /**
     * Validate or create one reproducible benchmark draft price book.
     *
     * ## OPTIONS
     *
     * --file=<path>
     * : Manifest path.
     *
     * [--dry-run]
     * : Validate checksums, mappings, evidence and the write plan without writes.
     *
     * [--create-draft]
     * : Create the idempotent draft. Requires --user with price-book capability.
     *
     * [--format=<format>]
     * : table or json. Default: table.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        foreach (['activate', 'replace', 'force', 'delete', 'update-live'] as $forbidden) {
            if (array_key_exists($forbidden, $assocArgs)) {
                \WP_CLI::error("Unsupported destructive option: --{$forbidden}.");
                return;
            }
        }
        $path = trim((string) ($assocArgs['file'] ?? ''));
        if ($path === '') {
            \WP_CLI::error('The --file option is required.');
            return;
        }
        $format = strtolower((string) ($assocArgs['format'] ?? 'table'));
        if (! in_array($format, ['table', 'json'], true)) {
            \WP_CLI::error('Unsupported format. Use table or json.');
            return;
        }
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $create = array_key_exists('create-draft', $assocArgs);
        if ($dryRun === $create) {
            \WP_CLI::error('Choose exactly one action: --dry-run or --create-draft.');
            return;
        }

        try {
            $manifest = $this->loader->load($path);
            if ($dryRun) {
                $this->output($this->seeds->plan($manifest), $format, true);
                return;
            }
            $actorId = get_current_user_id();
            if ($actorId < 1 || ! current_user_can(CapabilityManager::MANAGE_PRICE_BOOKS)) {
                \WP_CLI::error('Draft creation requires an authorized --user context.');
                return;
            }
            $this->output($this->seeds->createDraft($manifest, $actorId), $format, false);
        } catch (\Throwable $exception) {
            \WP_CLI::error($exception->getMessage());
        }
    }

    private function output(BenchmarkSeedPlan|BenchmarkSeedResult $result, string $format, bool $dryRun): void
    {
        $data = $result->toArray();
        $data['dry_run'] = $dryRun;
        $data['writes'] = $dryRun ? 0 : ($result instanceof BenchmarkSeedResult && $result->created ? 1 : 0);
        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        \WP_CLI::line(sprintf(
            'Manifest: %s | Action: %s | Models: %d | Configurations: %d | Rules: %d',
            $data['manifest_version'],
            $data['planned_action'],
            $data['model_count'],
            $data['configuration_count'],
            $data['total_rule_count']
        ));
        \WP_CLI::line(sprintf(
            'Base: %d | Condition: %d | Modes: %d | Manual: %d | Reject: %d | Writes: %d',
            $data['base_price_rule_count'],
            $data['condition_rule_count'],
            $data['mode_adjustment_count'],
            $data['manual_review_count'],
            $data['hard_reject_count'],
            $data['writes']
        ));
        if ($result instanceof BenchmarkSeedResult) {
            \WP_CLI::line(sprintf(
                'Draft ID: %d | Version: %d | Created: %s | Activated: no',
                $result->priceBookId,
                $result->versionNumber,
                $result->created ? 'yes' : 'no'
            ));
        }
    }
}
