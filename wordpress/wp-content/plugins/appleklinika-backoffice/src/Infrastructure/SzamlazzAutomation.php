<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\InvoiceAutomation;

final class SzamlazzAutomation implements InvoiceAutomation
{
    private static ?int $activeOrder = null;

    public function ready(): bool
    {
        if (! LifecycleConfiguration::enabled()
            || ! class_exists('WC_Szamlazz_Pro') || ! \WC_Szamlazz_Pro::is_pro_enabled()
            || ! class_exists('WC_Szamlazz_Automations') || ! function_exists('WC_Szamlazz')
            || \WC_Szamlazz()->get_option('auto_invoice_custom', 'no') !== 'yes') {
            return false;
        }
        if (! self::verifiedAgentKey((string) \WC_Szamlazz()->get_option('agent_key'))) {
            return false;
        }
        // Rollout uses exactly one unconditional paid-invoice rule. Do not silently
        // accept a conflicting order-created / processing / pro-forma automation.
        $rules = get_option('wc_szamlazz_automations', []);
        if (! is_array($rules) || count($rules) !== 1) {
            return false;
        }
        $rule = reset($rules);
        return ($rule['trigger'] ?? '') === 'payment_complete' && ($rule['document'] ?? '') === 'invoice'
            && empty($rule['conditional']) && ($rule['paid'] ?? false) === true;
    }

    public function run(int $id): void
    {
        if (! $this->ready()) {
            throw new \RuntimeException('Licensed TEST invoice automation is not ready.');
        }
        self::$activeOrder = $id;
        try {
            \WC_Szamlazz_Automations::on_payment_complete($id);
        } finally {
            self::$activeOrder = null;
        }
    }

    public static function ownsAttempt(int $id): bool
    {
        return self::$activeOrder === $id;
    }

    public static function verifiedAgentKey(string $key): bool
    {
        $verified = (string) get_option('appleklinika_szamlazz_test_agent_sha256', '');
        return $key !== '' && strlen($verified) === 64 && hash_equals($verified, hash('sha256', $key));
    }
}
