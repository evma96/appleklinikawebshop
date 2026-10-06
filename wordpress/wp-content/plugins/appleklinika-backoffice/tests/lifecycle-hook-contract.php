<?php
declare(strict_types=1);
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Appleklinika\\BackOffice\\';
        if (str_starts_with($class, $prefix)) { require_once dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
    });
    class WC_Order {
        public function __construct(public bool $managed) {}
        public function get_meta(string $key, bool $single): int { return $this->managed ? 1 : 0; }
    }
    function wc_get_order(int $id): WC_Order|false { return $GLOBALS['fixture_order']; }
    function home_url(string $path): string { return 'http://localhost:8082/'; }
    function wp_parse_url(string $url, int $component): mixed { return parse_url($url, $component); }
    function wp_get_environment_type(): string { return 'local'; }
    function get_option(string $key, mixed $default): mixed { return $key === 'home' ? 'http://localhost:8082' : $default; }
}
namespace Appleklinika\BackOffice\Infrastructure {
    // Only the attempt ownership port is stubbed. The real hook/environment code runs.
    class SzamlazzAutomation {
        public static bool $owns = false;
        public static function ownsAttempt(int $id): bool { return self::$owns; }
    }
}
namespace {
    $hooks = (new ReflectionClass(\Appleklinika\BackOffice\Interfaces\LifecycleHooks::class))->newInstanceWithoutConstructor();
    $n = 0;
    $assert = static function (bool $ok, string $why) use (&$n): void { ++$n; if (!$ok) { throw new RuntimeException($why); } };
    foreach ([false, true, [], ['processing', 'completed'], null] as $value) {
        $GLOBALS['fixture_order'] = new WC_Order(false);
        $assert($hooks->automaticInvoiceAllowed($value, 1) === $value, 'Unmanaged vendor result keeps its original type and value.');
        $GLOBALS['fixture_order'] = new WC_Order(true);
        $assert($hooks->automaticInvoiceAllowed($value, 1) === false, 'Managed calls outside the locked attempt are blocked, including metabox arrays.');
        \Appleklinika\BackOffice\Infrastructure\SzamlazzAutomation::$owns = true;
        $assert($hooks->automaticInvoiceAllowed($value, 1) === $value, 'Owned attempt preserves the vendor decision without converting a status list to bool.');
        \Appleklinika\BackOffice\Infrastructure\SzamlazzAutomation::$owns = false;
    }
    $GLOBALS['fixture_order'] = false;
    $assert($hooks->automaticInvoiceAllowed(['completed'], 0) === ['completed'], 'Unknown order leaves vendor result intact.');
    echo "Lifecycle provider hook contract: $n assertions passed; no provider calls.\n";
}
