<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\ProductionReadiness;

final class ProductionReadinessCommand
{
    /** Read-only. --fingerprints / --launch-evidence print private configuration material, never credentials. */
    public function __invoke(array $args, array $flags): void
    {
        try {
            if (isset($flags['fingerprints'])) {
                if (!ProductionReadiness::production()) { throw new \RuntimeException('Fingerprint export requires the production environment; TEST identities are not exported.'); }
                $out = [];
                foreach (ProductionReadiness::identities() as $name=>$values) { $out[$name] = ProductionReadiness::fingerprint($name,$values); }
            } elseif (isset($flags['public-opening-evidence'])) { $out = ProductionReadiness::publicOpeningEvidence(); }
            elseif (isset($flags['public-opening'])) { $out = ProductionReadiness::publicReport(); }
            elseif (isset($flags['launch-evidence']) || isset($flags['provisioning-evidence'])) { $out = ProductionReadiness::launchEvidence(); }
            else { $out = isset($flags['provisioning']) ? ProductionReadiness::report(true) : ProductionReadiness::publicReport(); }
        } catch (\Throwable) { \WP_CLI::error('Production readiness could not be verified. Check private configuration/schema and required modules. No activation performed.'); return; }
        \WP_CLI::line((string)json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        if (isset($out['ready']) && !$out['ready']) { \WP_CLI::halt(2); }
    }
}
