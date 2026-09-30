<?php
declare(strict_types=1);

namespace HireCraft\Support;

final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        // Pass $status explicitly to header(): PHP silently downgrades a Location
        // redirect to 302 unless the response code is given here (an earlier
        // http_response_code() call alone is not enough).
        header('Location: ' . self::url($path), true, $status);
        exit;
    }

    public static function url(string $path): string
    {
        $base = HC_CONFIG['app']['base_url'];
        return $base . $path;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', []);
        exit;
    }
}
