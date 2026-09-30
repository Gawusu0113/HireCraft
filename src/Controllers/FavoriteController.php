<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/** Customers saving artisans they'd like to hire again or keep shortlisted (FR: section 38). */
final class FavoriteController
{
    public function index(): void
    {
        Auth::requireRole('customer');
        $customerId = (int)Auth::id();
        $favorites = Db::all(
            'SELECT ap.*, u.full_name, sc.name AS category_name, sc.slug AS category_slug, a.name AS area_name, f.created_at AS saved_at
             FROM favorites f
             JOIN artisan_profiles ap ON ap.user_id = f.artisan_id
             JOIN users u ON u.id = ap.user_id
             JOIN service_categories sc ON sc.id = ap.category_id
             JOIN areas a ON a.id = ap.base_area_id
             WHERE f.customer_id = ?
             ORDER BY f.created_at DESC',
            [$customerId]
        );
        View::render('customer/favorites', ['favorites' => $favorites]);
    }

    public function add(array $args): void
    {
        Auth::requireRole('customer');
        $artisanId = (int)$args['id'];
        $exists = Db::scalar('SELECT 1 FROM artisan_profiles WHERE user_id = ? AND approval_status = "approved"', [$artisanId]);
        if (!$exists) Response::notFound();

        Db::run(
            'INSERT IGNORE INTO favorites (customer_id, artisan_id) VALUES (?, ?)',
            [(int)Auth::id(), $artisanId]
        );
        Auth::flash('success', 'Saved to your favorite artisans.');
        Response::redirect(self::safeBack($artisanId));
    }

    public function remove(array $args): void
    {
        Auth::requireRole('customer');
        $artisanId = (int)$args['id'];
        Db::run('DELETE FROM favorites WHERE customer_id = ? AND artisan_id = ?', [(int)Auth::id(), $artisanId]);
        Auth::flash('info', 'Removed from your favorite artisans.');
        Response::redirect(self::safeBack($artisanId));
    }

    /** Only ever follow a same-site relative path from the form's hidden "back" field. */
    private static function safeBack(int $artisanId): string
    {
        $back = (string)($_POST['back'] ?? '');
        if ($back !== '' && str_starts_with($back, '/') && !str_starts_with($back, '//')) {
            return $back;
        }
        return '/artisans/' . $artisanId;
    }
}
