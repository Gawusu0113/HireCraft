<?php
// Router script for `php -S` (PHP's built-in dev server). Not used by Apache/XAMPP,
// which rely on .htaccess instead. Serves real files (css, images) as-is, and sends
// everything else to the front controller.
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
