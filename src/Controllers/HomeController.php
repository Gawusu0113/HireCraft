<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Repo\Lookups;
use HireCraft\Repo\Users;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Response;
use HireCraft\Support\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home', ['categories' => Lookups::categories()]);
    }

    public function dashboard(): void
    {
        Auth::requireLogin();
        $userId = (int)Auth::id();

        switch (Auth::role()) {
            case 'customer':
                if (!Users::hasCustomerProfile($userId)) {
                    Response::redirect('/profile/setup');
                }
                $profile = Db::one(
                    'SELECT cp.*, a.name AS area_name FROM customer_profiles cp
                     LEFT JOIN areas a ON a.id = cp.area_id WHERE cp.user_id = ?',
                    [$userId]
                );
                $jobs = Db::all(
                    'SELECT jr.*, sc.name AS category_name, a.name AS area_name
                     FROM job_requests jr
                     JOIN service_categories sc ON sc.id = jr.category_id
                     JOIN areas a ON a.id = jr.area_id
                     WHERE jr.customer_id = ? ORDER BY jr.created_at DESC',
                    [$userId]
                );
                View::render('dashboard/customer', ['profile' => $profile, 'jobs' => $jobs]);
                return;

            case 'artisan':
                if (!Users::hasArtisanProfile($userId)) {
                    Response::redirect('/profile/setup');
                }
                $profile = Db::one(
                    'SELECT ap.*, sc.name AS category_name, a.name AS area_name
                     FROM artisan_profiles ap
                     JOIN service_categories sc ON sc.id = ap.category_id
                     JOIN areas a ON a.id = ap.base_area_id
                     WHERE ap.user_id = ?',
                    [$userId]
                );
                $skills = Db::all(
                    'SELECT s.name FROM artisan_skills ars JOIN skills s ON s.id = ars.skill_id WHERE ars.artisan_id = ? ORDER BY s.name',
                    [$userId]
                );
                $serviceAreas = Db::all(
                    'SELECT a.name FROM artisan_service_areas asa JOIN areas a ON a.id = asa.area_id WHERE asa.artisan_id = ? ORDER BY a.name',
                    [$userId]
                );
                $availability = Db::all(
                    'SELECT weekday, start_time, end_time FROM artisan_availability WHERE artisan_id = ? ORDER BY weekday',
                    [$userId]
                );
                $prices = Db::all(
                    'SELECT complexity, min_price, max_price FROM artisan_price_ranges WHERE artisan_id = ?
                     ORDER BY FIELD(complexity, "simple", "medium", "complex")',
                    [$userId]
                );
                View::render('dashboard/artisan', [
                    'profile' => $profile, 'skills' => $skills, 'serviceAreas' => $serviceAreas,
                    'availability' => $availability, 'prices' => $prices,
                ]);
                return;

            case 'admin':
                $stats = [
                    'total_users' => (int)Db::scalar('SELECT COUNT(*) FROM users'),
                    'customers' => (int)Db::scalar("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
                    'artisans' => (int)Db::scalar("SELECT COUNT(*) FROM users WHERE role = 'artisan'"),
                    'verified_artisans' => (int)Db::scalar("SELECT COUNT(*) FROM artisan_profiles WHERE verification_level != 'basic'"),
                    'pending_approval' => (int)Db::scalar("SELECT COUNT(*) FROM artisan_profiles WHERE approval_status = 'pending'"),
                    // "Open" = posted and still looking for an artisan (matched/quoted included) — a
                    // job only leaves this count once it's hired, cancelled or expired.
                    'open_jobs' => (int)Db::scalar("SELECT COUNT(*) FROM job_requests WHERE status IN ('posted', 'matched', 'quoted')"),
                    'active_jobs' => (int)Db::scalar("SELECT COUNT(*) FROM jobs WHERE status IN ('scheduled', 'in_progress')"),
                    'completed_jobs' => (int)Db::scalar("SELECT COUNT(*) FROM jobs WHERE status = 'confirmed'"),
                    'open_disputes' => (int)Db::scalar("SELECT COUNT(*) FROM disputes WHERE status IN ('open', 'under_review', 'waiting_for_response')"),
                    'open_reports' => (int)Db::scalar("SELECT COUNT(*) FROM user_reports WHERE status = 'open'"),
                ];

                // Analytics data for the dashboard charts (Chart.js) — kept as small,
                // pre-aggregated arrays so the view stays a thin renderer.
                $jobStatusRows = Db::all(
                    "SELECT status, COUNT(*) AS n FROM job_requests GROUP BY status"
                );
                $jobsByCategory = Db::all(
                    'SELECT sc.name, COUNT(*) AS n FROM job_requests jr JOIN service_categories sc ON sc.id = jr.category_id
                     GROUP BY sc.id, sc.name ORDER BY n DESC LIMIT 8'
                );
                $signupsByWeek = Db::all(
                    "SELECT DATE_FORMAT(created_at, '%x-W%v') AS wk, role, COUNT(*) AS n
                     FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK) AND role IN ('customer','artisan')
                     GROUP BY wk, role ORDER BY wk"
                );
                $trustBuckets = Db::all(
                    "SELECT
                        CASE
                            WHEN trust_score < 40 THEN '0-39'
                            WHEN trust_score < 60 THEN '40-59'
                            WHEN trust_score < 75 THEN '60-74'
                            WHEN trust_score < 90 THEN '75-89'
                            ELSE '90-100'
                        END AS bucket,
                        COUNT(*) AS n
                     FROM artisan_profiles WHERE approval_status = 'approved' GROUP BY bucket"
                );

                View::render('dashboard/admin', [
                    'stats' => $stats,
                    'jobStatusRows' => $jobStatusRows,
                    'jobsByCategory' => $jobsByCategory,
                    'signupsByWeek' => $signupsByWeek,
                    'trustBuckets' => $trustBuckets,
                ]);
                return;

            default:
                Response::redirect('/');
        }
    }
}
