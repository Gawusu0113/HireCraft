<?php
// HireCraft configuration. Reads from environment variables with sane local defaults
// so the app runs out of the box on XAMPP/local MySQL, and can be overridden for
// shared hosting by setting real environment variables.

// Auto-detect the app's base URL path from where index.php is actually being
// served (e.g. "/HireCraft/public" when this sits a few folders deep under
// XAMPP's htdocs, or "" when it's the web root / the PHP built-in dev
// server). This is what makes links and the router work without the user
// having to set HC_BASE_URL by hand for the common XAMPP-subfolder case;
// HC_BASE_URL still wins if it's explicitly set (e.g. behind a reverse proxy
// that rewrites paths, where auto-detection would guess wrong).
$autoBaseUrl = '';
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME'])) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $autoBaseUrl = $dir === '/' ? '' : rtrim($dir, '/');
}

return [
    'db' => [
        'host' => getenv('HC_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('HC_DB_PORT') ?: '3307',
        'name' => getenv('HC_DB_NAME') ?: 'hirecraft',
        'user' => getenv('HC_DB_USER') ?: 'root',
        'pass' => getenv('HC_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'HireCraft',
        'base_url' => getenv('HC_BASE_URL') ?: $autoBaseUrl,
        'debug' => (bool)(getenv('HC_DEBUG') ?: true),
        'upload_dir' => __DIR__ . '/../public/uploads',
        'session_name' => 'hc_session',
    ],
];
