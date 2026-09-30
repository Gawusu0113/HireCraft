<?php
declare(strict_types=1);

namespace HireCraft\Support;

/**
 * Session-based authentication and small security helpers.
 * NFR-05 (security): password_hash/verify, CSRF tokens on every state-changing
 * form, session regeneration on login, role checks on every protected route.
 */
final class Auth
{
    public static function bootFlash(): void
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function checkCsrf(): bool
    {
        $sent = $_POST['_csrf'] ?? '';
        $known = $_SESSION['csrf'] ?? '';
        // Both sides must be non-empty: hash_equals('', '') is true, which would
        // otherwise let a request with no session and no submitted token pass.
        return is_string($sent) && $sent !== '' && $known !== '' && hash_equals($known, $sent);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['full_name'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            echo '<h1>403</h1><p>You do not have access to this page.</p>';
            exit;
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Reads and clears any flash messages queued by a previous request. */
    public static function pullFlashes(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        $_SESSION['flash'] = [];
        return $messages;
    }
}
