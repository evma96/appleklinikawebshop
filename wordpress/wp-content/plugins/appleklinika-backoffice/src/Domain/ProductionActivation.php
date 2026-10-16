<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Pure policy: capability readiness never implies authorization to activate. */
final class ProductionActivation
{
    public const REQUIRED = ['environment', 'canonical_urls', 'modules', 'barion', 'szamlazz', 'gls', 'smtp',
        'customer_email', 'payment_policy', 'mpl_manual', 'operations', 'launch_data'];

    public static function evaluate(array $facts, bool $requested, bool $emergencyOff): array
    {
        $checks = [];
        foreach (self::REQUIRED as $name) {
            $checks[$name] = ($facts[$name] ?? false) === true ? 'READY' : 'NOT READY';
        }
        $checks['mpl_api'] = 'NOT APPLICABLE';
        $ready = !in_array('NOT READY', $checks, true);
        return ['capabilities' => $checks, 'ready' => $ready,
            'activation' => $requested && !$emergencyOff && $ready ? 'ON' : 'OFF'];
    }
}
