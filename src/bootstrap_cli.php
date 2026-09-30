<?php
declare(strict_types=1);

// Lightweight bootstrap for CLI scripts (tests, seeding): autoloading and
// config only, no session/DB-required web request context.
spl_autoload_register(function (string $class) {
    $prefix = 'HireCraft\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

define('HC_ROOT', dirname(__DIR__));
define('HC_CONFIG', require HC_ROOT . '/config/config.php');
