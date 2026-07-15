<?php

declare(strict_types=1);

define('AK_BUYBACK_DOMAIN_TEST_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $className): void {
    $prefix = 'AppleKlinika\\Buyback\\';

    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = AK_BUYBACK_DOMAIN_TEST_PATH . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});
