<?php
declare(strict_types=1);

namespace HireCraft\Support;

/** A small front-controller router: no framework dependency required. */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, callable $handler): void { $this->add('POST', $pattern, $handler); }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $paramNames = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'params' => $paramNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $base = rtrim(HC_CONFIG['app']['base_url'], '/');
        // Case-insensitive prefix match: on a case-insensitive filesystem (Windows/XAMPP),
        // Apache's mod_rewrite resolves the rewritten request (anything but a direct hit on
        // an existing file/directory, e.g. every route except "/") against the real on-disk
        // folder name, so SCRIPT_NAME (and therefore base_url) can come back in a different
        // case than the browser's original REQUEST_URI (e.g. "/HireCraft/public" vs the
        // requested "/hirecraft/public"). A case-sensitive str_starts_with() then silently
        // fails to strip the prefix and every non-homepage route 404s. URL paths are not
        // case-sensitive identifiers in this app, so match case-insensitively here.
        if ($base !== '' && stripos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        if ($path === '' ) $path = '/';
        $path = rtrim($path, '/');
        if ($path === '') $path = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (preg_match($route['regex'], $path, $m)) {
                array_shift($m);
                $args = array_combine($route['params'], $m);
                if ($method === 'POST' && !Auth::checkCsrf()) {
                    Auth::flash('error', 'Your session expired. Please try again.');
                    Response::redirect($_SERVER['HTTP_REFERER'] ?? '/', 419);
                }
                call_user_func($route['handler'], $args);
                return;
            }
        }
        Response::notFound();
    }
}
