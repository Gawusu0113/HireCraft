<?php
declare(strict_types=1);

namespace HireCraft\Support;

/**
 * Thin wrapper around the `notifications` table. Fired from controllers on
 * the key events the master prompt lists (section 39): new matches, job
 * applications/requests, quotations, acceptances, status changes, reviews,
 * messages, disputes and reports.
 */
final class Notifier
{
    public static function send(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        Db::run(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $title, $body, $link]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int)Db::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        return Db::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    public static function all(int $userId): array
    {
        return Db::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 200', [$userId]);
    }

    public static function markRead(int $userId, int $id): void
    {
        Db::run('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Db::run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }
}
