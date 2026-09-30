<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\View;

final class NotificationController
{
    public function index(): void
    {
        Auth::requireLogin();
        $userId = (int)Auth::id();
        $notifications = Notifier::all($userId);
        Notifier::markAllRead($userId);
        View::render('notifications/index', ['notifications' => $notifications]);
    }

    public function open(array $args): void
    {
        Auth::requireLogin();
        $id = (int)$args['id'];
        $userId = (int)Auth::id();
        $n = \HireCraft\Support\Db::one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$n) Response::notFound();
        Notifier::markRead($userId, $id);
        Response::redirect($n['link'] ?: '/notifications');
    }
}
