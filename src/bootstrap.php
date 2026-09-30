<?php
declare(strict_types=1);

// Minimal PSR-4-ish autoloader for the HireCraft\ namespace -> src/
// No Composer is required to run this app (some shared hosts / offline
// classrooms have no `composer install` available), which keeps deployment
// to "copy files, import schema.sql, edit config".
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
require __DIR__ . '/helpers.php';

date_default_timezone_set('Africa/Accra');
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_name(HC_CONFIG['app']['session_name']);
    session_start();
}

if (HC_CONFIG['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

use HireCraft\Support\Db;
use HireCraft\Support\Auth;

Db::init(HC_CONFIG['db']);
Auth::bootFlash();
