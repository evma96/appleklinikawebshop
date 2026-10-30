<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\ProductionActivation;

/** Read-only configuration/data inspection. Never copies credentials or calls a provider. */
final class ProductionReadiness
{
    public static function production(): bool { return wp_get_environment_type() === 'production'; }

    public static function config(): array
    {
        $path = defined('APPLEKLINIKA_PRODUCTION_CONFIG_FILE') ? APPLEKLINIKA_PRODUCTION_CONFIG_FILE : '';
        if (!is_string($path) || $path === '' || is_link($path) || !is_file($path) || !is_readable($path)) { return []; }
        clearstatcache(true, $path);
        $real = realpath($path);
        $root = realpath(ABSPATH);
        // Private, non-webroot file; group-readable is supported, world access is not.
        if (!$real || !$root || str_starts_with($real, rtrim($root, '/') . '/')
            || (fileperms($path) & 0007) !== 0 || (fileperms($path) & 0022) !== 0 || filesize($path) > 65536) { return []; }
        try { $c = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return []; }
        return is_array($c) && ($c['schema'] ?? null) === 1 && ($c['environment'] ?? '') === 'production' ? $c : [];
    }

    public static function fingerprint(string $capability, array $values): string
    {
        return hash_hmac('sha256', $capability . ':' . json_encode($values, JSON_UNESCAPED_SLASHES), wp_salt('auth'));
    }

    public static function pinned(string $capability, array $values, ?array $config = null): bool
    {
        $c = $config ?? self::config();
        $pin = $c['approved_fingerprints'][$capability] ?? '';
        return is_string($pin) && strlen($pin) === 64 && hash_equals($pin, self::fingerprint($capability, $values));
    }

    /** Raw values stay in memory. Only keyed fingerprints may be exported by the explicit CLI option. */
    public static function identities(): array
    {
        $b = (array) get_option('woocommerce_barion_settings', []);
        $g = class_exists('GLS_Shipping_Account_Helper') ? (array) \GLS_Shipping_Account_Helper::get_active_account() : [];
        $mail = function_exists('fluentMailGetSettings') ? (array) fluentMailGetSettings([], false) : [];
        $connection = $mail['connections'][$mail['misc']['default_connection'] ?? '']['provider_settings'] ?? [];
        return ['barion' => [(string)($b['poskey'] ?? ''), (string)($b['payee'] ?? '')],
            'szamlazz' => [function_exists('WC_Szamlazz') ? (string) \WC_Szamlazz()->get_option('agent_key') : ''],
            'gls' => [(string)($g['client_id'] ?? ''), (string)($g['username'] ?? ''), (string)($g['password'] ?? ''), strtoupper((string)($g['country'] ?? ''))],
            'smtp' => array_map(static fn($key) => (string)($connection[$key] ?? ''), ['host','port','username','password','sender_email'])];
    }

    public static function facts(array $c): array
    {
        $home = rtrim((string)get_option('home', ''), '/');
        $bo = rtrim((string)get_option('appleklinika_backoffice_origin', ''), '/');
        $urls = self::origin($home) && self::origin($bo) && $home !== $bo
            && $home === ($c['storefront'] ?? '') && $bo === ($c['backoffice'] ?? '')
            && rtrim((string)get_option('siteurl', ''), '/') === $home;
        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost !== '' && !in_array($requestHost, [parse_url($home,PHP_URL_HOST), parse_url($bo,PHP_URL_HOST)], true)) { $urls = false; }
        $ids = self::identities();
        $b = (array)get_option('woocommerce_barion_settings', []);
        $g = class_exists('GLS_Shipping_Account_Helper') ? (array)\GLS_Shipping_Account_Helper::get_active_account() : [];
        $s = function_exists('WC_Szamlazz') ? \WC_Szamlazz() : null;
        $rules = get_option('wc_szamlazz_automations', []);
        $rule = is_array($rules) && count($rules) === 1 ? reset($rules) : [];
        $szamlazz = $s && class_exists('WC_Szamlazz_Pro') && class_exists('WC_Szamlazz_Automations')
            && get_option('_wc_szamlazz_pro_enabled', false) && \WC_Szamlazz_Pro::is_pro_enabled()
            && (string)get_option('_wc_szamlazz_pro_key', '') !== ''
            && $s->get_option('auto_invoice_custom', 'no') === 'yes'
            && ($rule['trigger'] ?? '') === 'payment_complete' && ($rule['document'] ?? '') === 'invoice'
            && empty($rule['conditional']) && ($rule['paid'] ?? false) === true
            && ($c['szamlazz_live_account_and_site_license_verified'] ?? false) === true
            && $ids['szamlazz'][0] !== '' && self::pinned('szamlazz', $ids['szamlazz'], $c)
            && !hash_equals((string)get_option('appleklinika_szamlazz_test_agent_sha256',''), hash('sha256',$ids['szamlazz'][0]));
        $senderReady = false;
        if (class_exists('GLS_Shipping_Sender_Address_Helper') && class_exists('GLS_Shipping_Account_Helper')) {
            $sender = \GLS_Shipping_Sender_Address_Helper::format_for_api_pickup(
                \GLS_Shipping_Sender_Address_Helper::get_default_sender_address(), (string)\GLS_Shipping_Account_Helper::get_account_setting('phone_number'));
            $senderReady = self::filled(array_map(static fn($key) => (string)($sender[$key] ?? ''), ['Name','Street','City','ZipCode','CountryIsoCode','ContactPhone']));
        }
        $mail = function_exists('fluentMailGetSettings') ? (array)fluentMailGetSettings([], false) : [];
        $connections = $mail['connections'] ?? [];
        $m = $connections[$mail['misc']['default_connection'] ?? '']['provider_settings'] ?? [];
        $smtp = count($connections) === 1 && ($m['provider'] ?? '') === 'smtp' && ($m['auth'] ?? '') === 'yes'
            && (($m['encryption'] ?? '') === 'tls' && (int)($m['port'] ?? 0) === 587 || ($m['encryption'] ?? '') === 'ssl' && (int)($m['port'] ?? 0) === 465)
            && ($m['force_from_email'] ?? '') === 'yes' && ($m['force_from_name'] ?? '') === 'yes'
            && ($m['sender_name'] ?? '') === 'Apple Klinika' && filter_var($m['sender_email'] ?? '', FILTER_VALIDATE_EMAIL)
            && self::filled($ids['smtp']) && self::pinned('smtp', $ids['smtp'], $c)
            && ($c['smtp_sender_authorized'] ?? false) === true && empty($mail['misc']['fallback_connection']);
        $emails = true;
        foreach (['order_received','paid_invoice','carrier_handoff'] as $email) {
            $settings = (array)get_option('woocommerce_appleklinika_' . $email . '_settings', []);
            $emails = $emails && ($settings['enabled'] ?? 'yes') === 'yes';
        }
        $cod = (array)get_option('woocommerce_cod_settings', []);
        $pickupOnly = !empty($cod['enable_for_methods']) && count(array_filter((array)$cod['enable_for_methods'],
            static fn($method) => !in_array(explode(':',(string)$method)[0], ['local_pickup','pickup_location'],true))) === 0;
        $ops = (array)($c['operations'] ?? []);
        $operations = ($ops['environment'] ?? '') === 'production' && ($ops['storefront'] ?? '') === $home
            && self::productionLabel($ops['backup_destination'] ?? '') && self::productionLabel($ops['monitor_target'] ?? '')
            && filter_var($ops['monitor_recipient'] ?? '', FILTER_VALIDATE_EMAIL)
            && ($ops['recovery_key_independent'] ?? false) === true && ($ops['cron_verified'] ?? false) === true;
        foreach (['backup_verified_at','restore_verified_at','monitor_verified_at'] as $key) {
            $operations = $operations && is_int($ops[$key] ?? null) && $ops[$key] > 0 && $ops[$key] <= time();
        }
        return ['environment' => self::production() && $c !== [], 'canonical_urls' => $urls,
            'modules' => defined('APPLEKLINIKA_PRODUCTION_SAFETY_LOADED') && APPLEKLINIKA_PRODUCTION_SAFETY_LOADED
                && in_array('appleklinika-backoffice/appleklinika-backoffice.php',(array)get_option('active_plugins',[]),true) && class_exists('WooCommerce') && class_exists('WC_Gateway_Barion') && defined('GLS_SHIPPING_ABSPATH')
                && class_exists('GLS_Shipping_For_Woo') && class_exists('WC_Szamlazz_Xml_Generator'),
            'barion' => ($b['enabled'] ?? '') === 'yes' && ($b['environment'] ?? '') === 'live' && self::filled($ids['barion']) && self::pinned('barion',$ids['barion'],$c),
            'szamlazz' => (bool)$szamlazz,
            'gls' => ($g['mode'] ?? '') === 'production' && \GLS_Shipping_Account_Helper::get_account_setting('mode') === 'production'
                && in_array(strtoupper((string)($g['country'] ?? '')),['HU','HR','CZ','SK','SI','RO','RS'],true) && self::filled($ids['gls']) && $senderReady && self::pinned('gls',$ids['gls'],$c),
            'smtp' => (bool)$smtp, 'customer_email' => $emails,
            'payment_policy' => (get_option('woocommerce_bacs_settings',[])['enabled'] ?? 'no') === 'no'
                && (($cod['enabled'] ?? 'no') === 'no' || $pickupOnly),
            'mpl_manual' => get_option('appleklinika_mpl_enabled','no') === 'yes' && get_option('appleklinika_mpl_api_enabled','no') !== 'yes',
            'operations' => (bool)$operations];
    }

    /** Before launch EVERY product/order/customer/session is forbidden, not just names containing QA. */
    public static function dataCounts(array $c): array
    {
        global $wpdb;
        $count = static function($type) use ($wpdb): int {
            $counts = wp_count_posts($type);
            if (!is_object($counts) || $wpdb->last_error !== '') { throw new \RuntimeException('Catalog inventory unavailable.'); }
            return (int)array_sum((array)$counts);
        };
        $orders = wc_get_orders(['limit'=>1,'paginate'=>true,'status'=>array_unique(array_merge(array_keys(wc_get_order_statuses()),['wc-checkout-draft','trash','auto-draft'])),'type'=>['shop_order','shop_order_refund']]);
        $staff = array_filter((array)($c['approved_staff_ids'] ?? []), static fn($id) => is_int($id) && $id > 0);
        $users = get_users(['fields'=>'all']);
        if ($wpdb->last_error !== '') { throw new \RuntimeException('User inventory unavailable.'); }
        $userIds = []; $customers = 0; $qaUsers = 0;
        foreach ($users as $user) {
            $userIds[] = (int)$user->ID;
            if (array_intersect((array)$user->roles,['customer','subscriber']) !== []) { ++$customers; }
            if (preg_match('/(^|[_-])(qa|fixture|test|teszt)([_-]|$)/i',(string)$user->user_login)) { ++$qaUsers; }
        }
        $pending = 0;
        if (function_exists('as_get_scheduled_actions')) {
            $actions = as_get_scheduled_actions(['status'=>'pending','per_page'=>1001]);
            if (count($actions) > 1000) { $pending = count($actions); }
            else { foreach ($actions as $action) { if (preg_match('/barion|szamlazz|gls|appleklinika_lifecycle|woocommerce.*(?:email|payment|stock)/i',$action->get_hook())) { ++$pending; } } }
        }
        if (!isset($orders->total)) { throw new \RuntimeException('Order inventory unavailable.'); }
        $sessions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions");
        if ($sessions === null || $wpdb->last_error !== '') { throw new \RuntimeException('Session inventory unavailable.'); }
        $carts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE '_woocommerce_persistent_cart_%'");
        if ($carts === null || $wpdb->last_error !== '') { throw new \RuntimeException('Cart inventory unavailable.'); }
        $reserved = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock");
        if ($reserved === null || $wpdb->last_error !== '') { throw new \RuntimeException('Reserved stock inventory unavailable.'); }
        foreach ((array)get_option('cron',[]) as $events) {
            if (!is_array($events)) { continue; }
            foreach ($events as $hook=>$event) {
                if (preg_match('/barion|szamlazz|gls|appleklinika_lifecycle|woocommerce.*(?:email|payment|stock)/i',(string)$hook) && !str_ends_with((string)$hook,'_pro_key_check')) { $pending += count((array)$event); }
            }
        }
        return ['products'=>(int)$count('product'), 'variations'=>(int)$count('product_variation'), 'orders'=>(int)$orders->total,
            'unapproved_users'=>count(array_diff($userIds,$staff)), 'customers'=>$customers, 'qa_users'=>$qaUsers,
            'sessions'=>(int)$sessions,
            'persistent_carts'=>(int)$carts, 'stock_reservations'=>(int)$reserved,
            'provider_jobs'=>$pending];
    }

    public static function report(bool $preflight = false): array
    {
        $c = self::config();
        $facts = self::facts($c);
        $counts = $preflight ? self::dataCounts($c) : null;
        $facts['launch_data'] = $preflight ? $counts !== null && array_sum($counts) === 0 : self::validLaunchEvidence($c);
        $result = ProductionActivation::evaluate($facts, ($c['enabled'] ?? false) === true,
            defined('APPLEKLINIKA_PRODUCTION_EMERGENCY_OFF') && APPLEKLINIKA_PRODUCTION_EMERGENCY_OFF === true);
        // Readiness before launch cannot assert that activation has occurred.
        if ($preflight) { $result['activation'] = self::allowed() ? 'ON' : 'OFF'; $result['counts'] = $counts; }
        return $result;
    }

    public static function allowed(): bool
    {
        if (!self::production()) { return false; }
        try { return self::report()['activation'] === 'ON'; }
        catch (\Throwable) { return false; }
    }

    public static function assertAllowed(string $operation): void
    {
        if (!self::production() || self::allowed()) { return; }
        // Fixed code only: no exception/config/provider payload enters logs.
        if (function_exists('wc_get_logger')) { wc_get_logger()->error('production_activation_blocked:' . preg_replace('/[^a-z_]/','',$operation), ['source'=>'appleklinika-production']); }
        throw new \RuntimeException('Apple Klinika production activation blocked. Run: wp appleklinika production-readiness');
    }

    public static function launchEvidence(): array
    {
        $report = self::report(true);
        if (!$report['ready']) { throw new \RuntimeException('Preflight is NOT READY; no launch evidence issued.'); }
        $evidence = ['home'=>(string)get_option('home'), 'checked_at'=>time(), 'counts'=>$report['counts']];
        $evidence['signature'] = self::fingerprint('empty-launch', $evidence);
        return $evidence;
    }

    private static function validLaunchEvidence(array $c): bool
    {
        $e = $c['launch_evidence'] ?? [];
        $signature = $e['signature'] ?? '';
        unset($e['signature']);
        $start = $c['activated_at'] ?? 0;
        $counts = $e['counts'] ?? [];
        return is_string($signature) && strlen($signature) === 64 && is_int($start) && $start > 0 && $start <= time()
            && ($e['home'] ?? '') === get_option('home') && is_int($e['checked_at'] ?? null)
            && $e['checked_at'] <= $start
            && array_keys($counts) === ['products','variations','orders','unapproved_users','customers','qa_users','sessions','persistent_carts','stock_reservations','provider_jobs']
            && count(array_filter($counts, static fn($n) => $n !== 0)) === 0
            && hash_equals($signature, self::fingerprint('empty-launch',$e));
    }

    /** Public order admission is separate from provider/callback recovery. */
    public static function publicAllowed(): bool
    {
        if (!self::production()) { return true; }
        try {
            $c = self::config(); $e = $c['public_opening_evidence'] ?? [];
            $signature = $e['signature'] ?? ''; unset($e['signature']);
            return self::allowed() && ($c['public_open'] ?? false) === true
                && get_option('woocommerce_coming_soon', 'no') !== 'yes'
                && ($e['home'] ?? '') === get_option('home')
                && ($e['provisioning_signature'] ?? '') === ($c['launch_evidence']['signature'] ?? '')
                && is_int($e['checked_at'] ?? null) && $e['checked_at'] <= time()
                && ($e['ready'] ?? false) === true && is_string($signature)
                && hash_equals(self::fingerprint('public-opening', $e), $signature);
        } catch (\Throwable) { return false; }
    }

    /** Read-only public-opening preflight. No deletion; every existing product needs explicit review. */
    public static function publicReport(): array
    {
        $c = self::config(); $report = self::report(); $counts = self::dataCounts($c);
        $ids = get_posts(['post_type'=>['product','product_variation'], 'post_status'=>array_keys(get_post_stati()),
            'numberposts'=>-1, 'fields'=>'ids']);
        $reviewed = array_values(array_filter((array)($c['reviewed_real_product_ids'] ?? []), static fn($id) => is_int($id) && $id > 0));
        $problems = []; $skus = []; $identifiers = [];
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            if (!$p || !in_array((int)$id, $reviewed, true)) { $problems[$id] = 'not_explicitly_reviewed'; continue; }
            if (!$p->is_type('simple') || preg_match('/(^|[\s_-])(demo|qa|test|teszt|fixture)([\s_-]|$)/iu', $p->get_name().' '.$p->get_sku())) {
                $problems[$id] = 'demo_or_unsupported_product'; continue;
            }
            foreach ($p->get_meta_data() as $meta) {
                $data = $meta->get_data();
                if (preg_match('/(?:^|_)(?:qa|demo|fixture)(?:_|$)/i', $data['key']) && !empty($data['value'])) { $problems[$id] = 'qa_metadata'; }
            }
            $sku = $p->get_sku();
            if ($sku === '' || isset($skus[$sku])) { $problems[$id] = 'missing_or_duplicate_sku'; }
            $skus[$sku] = true;
            if ($p->get_meta('_appleklinika_protection_group') !== '') {
                if ($p->get_tax_status() !== 'taxable' || $p->get_tax_class() !== 'ak-accessories-27') { $problems[$id] = 'accessory_tax_not_approved'; }
                continue;
            }
            if ($p->get_meta('_appleklinika_physical_device') !== 'yes' || !$p->get_manage_stock()
                || !in_array($p->get_stock_quantity(), [0, 1], true) || $p->get_backorders() !== 'no'
                || !$p->is_sold_individually()) { $problems[$id] = 'invalid_physical_stock'; }
            $imei = (string)$p->get_meta('_appleklinika_imei'); $serial = (string)$p->get_meta('_appleklinika_serial_number');
            if ($imei === '' && $serial === '') { $problems[$id] = 'missing_device_identity'; }
            foreach (['imei'=>$imei, 'serial'=>$serial] as $kind=>$value) {
                if ($value === '') { continue; }
                if (isset($identifiers[$kind.':'.$value])) { $problems[$id] = 'duplicate_device_identity'; }
                $identifiers[$kind.':'.$value] = true;
            }
        }
        // Real catalog is permitted. QA/order/customer artifacts are not a launch seed.
        unset($counts['products'], $counts['variations']);
        $clean = array_sum($counts) === 0 && $problems === [];
        $tax = get_option('woocommerce_calc_taxes') === 'yes' && get_option('woocommerce_prices_include_tax') === 'yes'
            && function_exists('WC_Szamlazz') && \WC_Szamlazz()->get_option('afakulcs') === '';
        return ['ready'=>$report['ready'] && $clean && $tax, 'capabilities'=>$report['capabilities'],
            'provisioning_evidence_valid'=>self::validLaunchEvidence($c), 'product_count'=>count($ids),
            'product_problems'=>$problems, 'non_catalog_counts'=>$counts, 'gross_tax_configuration'=>$tax];
    }

    public static function publicOpeningEvidence(): array
    {
        $report = self::publicReport();
        if (!$report['ready']) { throw new \RuntimeException('Public opening is NOT READY; preserve real inventory and resolve reported problems.'); }
        $c = self::config();
        $e = ['home'=>(string)get_option('home'), 'checked_at'=>time(), 'ready'=>true,
            'provisioning_signature'=>$c['launch_evidence']['signature'],
            'reviewed_real_product_ids'=>array_values((array)($c['reviewed_real_product_ids'] ?? []))];
        $e['signature'] = self::fingerprint('public-opening', $e);
        return $e;
    }

    private static function filled(array $values): bool { return $values !== [] && count(array_filter($values, static fn($v) => trim($v) === '')) === 0; }
    private static function productionLabel(mixed $value): bool { return is_string($value) && trim($value) !== '' && !preg_match('/test|teszt|staging|sandbox|localhost/i',$value); }
    private static function origin(string $url): bool
    {
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && isset($p['host']) && str_contains($p['host'],'.')
            && !filter_var($p['host'],FILTER_VALIDATE_IP) && self::productionLabel($p['host']) && !str_ends_with($p['host'],'.local')
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']) && !isset($p['query']) && !isset($p['fragment']) && empty($p['path']);
    }
}
