<?php
declare(strict_types=1);

namespace HireCraft\Support;

final class View
{
    /**
     * Renders views/{$view}.php with $data in scope, wraps it in the shared
     * layout (nav + footer) unless $layout is null (used for AJAX partials).
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layout/main'): void
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        $data['content'] = $content;
        echo self::capture($layout, $data);
    }

    public static function capture(string $view, array $data = []): string
    {
        $path = HC_ROOT . '/views/' . $view . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: $view");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }
}
