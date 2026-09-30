<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Engine\SmartMatchEngine as E;
use HireCraft\Repo\Lookups;
use HireCraft\Repo\Users;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Response;
use HireCraft\Support\Uploads;
use HireCraft\Support\View;

/**
 * Customer and artisan profile setup wizard. Runs once, right after
 * registration (or on login, if a profile row is still missing).
 */
final class ProfileController
{
    private const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function showSetup(): void
    {
        Auth::requireRole('customer', 'artisan');
        $userId = (int)Auth::id();

        if (Auth::role() === 'customer') {
            if (Users::hasCustomerProfile($userId)) {
                Response::redirect('/dashboard');
            }
            View::render('profile/customer_setup', ['areas' => Lookups::areas()]);
            return;
        }

        if (Users::hasArtisanProfile($userId)) {
            Response::redirect('/dashboard');
        }
        View::render('profile/artisan_setup', [
            'categories' => Lookups::categories(),
            'skillsByCategory' => Lookups::skillsByCategory(),
            'areas' => Lookups::areas(),
            'weekdays' => self::WEEKDAYS,
        ]);
    }

    public function saveSetup(): void
    {
        Auth::requireRole('customer', 'artisan');
        if (Auth::role() === 'customer') {
            $this->saveCustomerSetup();
        } else {
            $this->saveArtisanSetup();
        }
    }

    private function saveCustomerSetup(): void
    {
        $userId = (int)Auth::id();
        if (Users::hasCustomerProfile($userId)) {
            Response::redirect('/dashboard');
        }

        $areaId = (int)($_POST['area_id'] ?? 0) ?: null;
        $addressNote = trim((string)($_POST['address_note'] ?? ''));
        if ($addressNote !== '' && mb_strlen($addressNote) > 255) {
            $addressNote = mb_substr($addressNote, 0, 255);
        }

        if ($areaId !== null && !Db::scalar('SELECT COUNT(*) FROM areas WHERE id = ?', [$areaId])) {
            Auth::flash('error', 'Please choose a valid area.');
            Response::redirect('/profile/setup');
        }

        Db::run(
            'INSERT INTO customer_profiles (user_id, area_id, address_note) VALUES (?, ?, ?)',
            [$userId, $areaId, $addressNote !== '' ? $addressNote : null]
        );

        Auth::flash('success', 'Your profile is set up. You can post a job whenever you\'re ready.');
        Response::redirect('/dashboard');
    }

    private function saveArtisanSetup(): void
    {
        $userId = (int)Auth::id();
        if (Users::hasArtisanProfile($userId)) {
            Response::redirect('/dashboard');
        }

        $businessName = trim((string)($_POST['business_name'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $bio = trim((string)($_POST['bio'] ?? ''));
        $yearsExperience = (int)($_POST['years_experience'] ?? 0);
        $baseAreaId = (int)($_POST['base_area_id'] ?? 0);
        $travelRadiusKm = (int)($_POST['travel_radius_km'] ?? 0);
        $acceptsEmergency = !empty($_POST['accepts_emergency']) ? 1 : 0;
        $skillIds = array_values(array_unique(array_map('intval', (array)($_POST['skills'] ?? []))));
        $serviceAreaIds = array_values(array_unique(array_map('intval', (array)($_POST['service_areas'] ?? []))));
        $workDays = array_values(array_unique(array_map('intval', (array)($_POST['work_days'] ?? []))));
        $startTime = (string)($_POST['start_time'] ?? '08:00');
        $endTime = (string)($_POST['end_time'] ?? '17:00');

        $errors = [];
        if (mb_strlen($businessName) > 120) $errors[] = 'Business name is too long.';
        $category = $categoryId ? Lookups::categoryById($categoryId) : null;
        if (!$category) $errors[] = 'Please choose your trade.';
        if ($yearsExperience < 0 || $yearsExperience > 60) $errors[] = 'Years of experience should be between 0 and 60.';
        $validAreaIds = array_column(Lookups::areas(), 'id');
        if (!$baseAreaId || !in_array($baseAreaId, $validAreaIds, true)) $errors[] = 'Please choose your base area.';
        if ($travelRadiusKm < 1 || $travelRadiusKm > 50) $errors[] = 'Travel radius should be between 1 and 50 km.';

        $validSkillIds = $category ? array_column(Lookups::skillsForCategory($categoryId), 'id') : [];
        $skillIds = array_values(array_intersect($skillIds, $validSkillIds));
        if (!$skillIds) $errors[] = 'Please select at least one skill within your trade.';

        $serviceAreaIds = array_values(array_intersect($serviceAreaIds, $validAreaIds));
        if ($baseAreaId && !in_array($baseAreaId, $serviceAreaIds, true)) {
            $serviceAreaIds[] = $baseAreaId;
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) $startTime = '08:00';
        if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) $endTime = '17:00';
        $workDays = array_values(array_filter($workDays, fn($d) => $d >= 0 && $d <= 6));
        if (!$workDays) $errors[] = 'Please select at least one working day.';
        if ($startTime >= $endTime) $errors[] = 'Your start time must be before your end time.';

        $prices = [];
        foreach (['simple', 'medium', 'complex'] as $complexity) {
            $min = (float)($_POST['price_min_' . $complexity] ?? 0);
            $max = (float)($_POST['price_max_' . $complexity] ?? 0);
            if ($min < 0 || $max < $min) {
                $errors[] = 'Please check your ' . $complexity . '-job price range (maximum must be at least the minimum).';
                continue;
            }
            $prices[$complexity] = [$min, $max];
        }

        if ($errors) {
            foreach ($errors as $e) Auth::flash('error', $e);
            Response::redirect('/profile/setup');
        }

        $verified = ['phone' => false, 'email' => false, 'identity' => false, 'skill' => false];
        $completeness = self::round3((
            ($businessName !== '' ? 1 : 0)
            + ($bio !== '' ? 1 : 0)
            + 1 // skills selected (required)
            + 1 // service areas selected (required)
            + 1 // availability set (required)
            + 1 // price ranges set (required)
        ) / 6);
        $trust = E::trustScore([
            'verified' => $verified,
            'profileCompleteness' => $completeness,
            'completedJobs' => 0,
            'completionRate' => 0,
            'responseRate' => 0,
            'disputeFreeRate' => 1,
        ], E::DEFAULT_CONFIG);

        Db::transaction(function () use (
            $userId, $businessName, $categoryId, $bio, $yearsExperience, $baseAreaId, $travelRadiusKm,
            $acceptsEmergency, $completeness, $trust, $skillIds, $serviceAreaIds, $workDays, $startTime, $endTime, $prices
        ) {
            Db::run(
                'INSERT INTO artisan_profiles
                    (user_id, business_name, category_id, bio, years_experience, base_area_id, travel_radius_km,
                     accepts_emergency, profile_completeness, verification_level, trust_score, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "basic", ?, "pending")',
                [$userId, $businessName !== '' ? $businessName : null, $categoryId, $bio !== '' ? $bio : null,
                 $yearsExperience, $baseAreaId, $travelRadiusKm, $acceptsEmergency, $completeness, $trust['total']]
            );

            foreach ($skillIds as $skillId) {
                Db::run('INSERT INTO artisan_skills (artisan_id, skill_id) VALUES (?, ?)', [$userId, $skillId]);
            }
            foreach ($serviceAreaIds as $areaId) {
                Db::run('INSERT IGNORE INTO artisan_service_areas (artisan_id, area_id) VALUES (?, ?)', [$userId, $areaId]);
            }
            foreach ($workDays as $weekday) {
                Db::run(
                    'INSERT INTO artisan_availability (artisan_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)',
                    [$userId, $weekday, $startTime . ':00', $endTime . ':00']
                );
            }
            foreach ($prices as $complexity => [$min, $max]) {
                Db::run(
                    'INSERT INTO artisan_price_ranges (artisan_id, complexity, min_price, max_price) VALUES (?, ?, ?, ?)',
                    [$userId, $complexity, $min, $max]
                );
            }
            Db::run(
                'INSERT INTO trust_score_history (artisan_id, score, verification_part, profile_part, reliability_part, reason)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $trust['total'], $trust['parts']['verification'], $trust['parts']['profile'],
                 $trust['parts']['reliability'], 'Initial score at profile setup']
            );
        });

        Auth::flash('success', 'Your artisan profile has been created and is pending admin approval. You\'ll start appearing in matches once approved.');
        Response::redirect('/dashboard');
    }

    // ------------------------------------------------------------------ artisan portfolio (work samples)

    public function showPortfolio(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $profile = Db::one('SELECT category_id FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        if (!$profile) {
            Response::redirect('/profile/setup');
        }

        $items = Db::all('SELECT * FROM portfolios WHERE artisan_id = ? ORDER BY created_at DESC', [$artisanId]);
        foreach ($items as &$item) {
            $item['images'] = Db::all('SELECT id, image_path FROM portfolio_images WHERE portfolio_id = ? ORDER BY sort_order, id', [$item['id']]);
            $item['skills'] = Db::all(
                'SELECT s.name FROM portfolio_skills ps JOIN skills s ON s.id = ps.skill_id WHERE ps.portfolio_id = ?',
                [$item['id']]
            );
        }
        unset($item);

        View::render('profile/portfolio', [
            'items' => $items,
            'skills' => Lookups::skillsForCategory((int)$profile['category_id']),
        ]);
    }

    public function addPortfolioItem(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $profile = Db::one('SELECT category_id FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        if (!$profile) {
            Response::redirect('/profile/setup');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $completedOn = trim((string)($_POST['completed_on'] ?? ''));
        $skillIds = array_values(array_unique(array_map('intval', (array)($_POST['skills'] ?? []))));

        $errors = [];
        if ($title === '' || mb_strlen($title) > 120) {
            $errors[] = 'Please give the project a short title.';
        }
        if ($completedOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedOn)) {
            $completedOn = '';
        }
        $validSkillIds = array_column(Lookups::skillsForCategory((int)$profile['category_id']), 'id');
        $skillIds = array_values(array_intersect($skillIds, $validSkillIds));

        if ($errors) {
            foreach ($errors as $e) Auth::flash('error', $e);
            Response::redirect('/profile/portfolio');
        }

        // Save photos only once the rest of the item has passed validation,
        // same reasoning as job photos: never leave orphaned files behind.
        $uploaded = Uploads::saveMany($_FILES['photos'] ?? null, 'portfolio/' . $artisanId);
        foreach ($uploaded['errors'] as $upErr) {
            Auth::flash('error', $upErr);
        }
        if (!$uploaded['paths']) {
            Auth::flash('error', 'Please attach at least one photo of the finished work.');
            Response::redirect('/profile/portfolio');
        }

        Db::transaction(function () use ($artisanId, $title, $description, $completedOn, $skillIds, $profile, $uploaded) {
            $portfolioId = Db::insert(
                'INSERT INTO portfolios (artisan_id, title, description, category_id, completed_on) VALUES (?, ?, ?, ?, ?)',
                [$artisanId, $title, $description !== '' ? $description : null, (int)$profile['category_id'], $completedOn !== '' ? $completedOn : null]
            );
            foreach ($skillIds as $skillId) {
                Db::run('INSERT IGNORE INTO portfolio_skills (portfolio_id, skill_id) VALUES (?, ?)', [$portfolioId, $skillId]);
            }
            foreach ($uploaded['paths'] as $i => $path) {
                Db::run(
                    'INSERT INTO portfolio_images (portfolio_id, image_path, kind, sort_order) VALUES (?, ?, ?, ?)',
                    [$portfolioId, $path, 'other', $i]
                );
            }
        });

        // The engine's portfolio/"similar work" score is a live join over
        // these rows (see MatchData::artisans()), so this immediately counts
        // toward future SmartMatch results — not just decoration.
        Auth::flash('success', 'Added to your portfolio. This also strengthens your "similar work" match score going forward.');
        Response::redirect('/profile/portfolio');
    }

    public function deletePortfolioItem(array $args): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $id = (int)$args['id'];
        $item = Db::one('SELECT id FROM portfolios WHERE id = ? AND artisan_id = ?', [$id, $artisanId]);
        if (!$item) {
            Response::notFound();
        }

        $images = Db::all('SELECT image_path FROM portfolio_images WHERE portfolio_id = ?', [$id]);
        Db::run('DELETE FROM portfolios WHERE id = ?', [$id]); // cascades to portfolio_images/portfolio_skills
        foreach ($images as $img) {
            $full = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $img['image_path'];
            if (is_file($full)) {
                @unlink($full);
            }
        }

        Auth::flash('success', 'Removed from your portfolio.');
        Response::redirect('/profile/portfolio');
    }

    /** Read-only public-facing artisan profile: business info, skills, trust/rating, and portfolio gallery. */
    public function showArtisanProfile(array $args): void
    {
        Auth::requireLogin();
        $id = (int)$args['id'];
        $artisan = Db::one(
            'SELECT ap.*, u.full_name, sc.name AS category_name, sc.slug AS category_slug, a.name AS area_name
             FROM artisan_profiles ap
             JOIN users u ON u.id = ap.user_id
             JOIN service_categories sc ON sc.id = ap.category_id
             JOIN areas a ON a.id = ap.base_area_id
             WHERE ap.user_id = ? AND ap.approval_status = "approved"',
            [$id]
        );
        if (!$artisan) {
            Response::notFound();
        }

        $skills = Db::all(
            'SELECT s.name FROM artisan_skills askl JOIN skills s ON s.id = askl.skill_id WHERE askl.artisan_id = ? ORDER BY s.name',
            [$id]
        );
        $serviceAreas = Db::all(
            'SELECT a.name FROM artisan_service_areas asa JOIN areas a ON a.id = asa.area_id WHERE asa.artisan_id = ? ORDER BY a.name',
            [$id]
        );
        $items = Db::all('SELECT id, title, description, completed_on FROM portfolios WHERE artisan_id = ? ORDER BY created_at DESC', [$id]);
        foreach ($items as &$item) {
            $item['images'] = Db::all('SELECT image_path FROM portfolio_images WHERE portfolio_id = ? ORDER BY sort_order, id', [$item['id']]);
        }
        unset($item);

        $isFavorite = false;
        if (Auth::role() === 'customer') {
            $isFavorite = (bool)Db::scalar(
                'SELECT 1 FROM favorites WHERE customer_id = ? AND artisan_id = ?',
                [(int)Auth::id(), $id]
            );
        }

        View::render('artisan/profile', [
            'artisan' => $artisan, 'skills' => $skills, 'serviceAreas' => $serviceAreas, 'items' => $items,
            'isFavorite' => $isFavorite,
        ]);
    }

    private static function round3(float $x): float
    {
        return round($x, 3);
    }

    // ------------------------------------------------------------------ profile picture (customer, artisan, admin)

    public function showAvatar(): void
    {
        Auth::requireLogin();
        $userId = (int)Auth::id();
        $role = (string)Auth::role();
        self::requireProfileFor($role, $userId);

        View::render('profile/avatar', [
            'avatarPath' => Users::avatarPath($userId, $role),
        ]);
    }

    public function saveAvatar(): void
    {
        Auth::requireLogin();
        $userId = (int)Auth::id();
        $role = (string)Auth::role();
        self::requireProfileFor($role, $userId);

        $uploaded = Uploads::saveImage($_FILES['avatar'] ?? null, 'avatars/' . $userId);
        if ($uploaded['error'] !== null) {
            Auth::flash('error', $uploaded['error']);
            Response::redirect('/profile/picture');
        }

        $previousPath = Users::avatarPath($userId, $role);
        Users::setAvatarPath($userId, $role, $uploaded['path']);

        if ($previousPath) {
            $full = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $previousPath;
            if (is_file($full)) {
                @unlink($full);
            }
        }

        Auth::flash('success', 'Your profile picture has been updated.');
        Response::redirect('/profile/picture');
    }

    public function removeAvatar(): void
    {
        Auth::requireLogin();
        $userId = (int)Auth::id();
        $role = (string)Auth::role();
        self::requireProfileFor($role, $userId);

        $previousPath = Users::avatarPath($userId, $role);
        Users::setAvatarPath($userId, $role, null);
        if ($previousPath) {
            $full = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $previousPath;
            if (is_file($full)) {
                @unlink($full);
            }
        }

        Auth::flash('success', 'Your profile picture has been removed.');
        Response::redirect('/profile/picture');
    }

    /** Customers/artisans need their setup wizard done first (that's what creates the row avatar_path lives on); admins have no such prerequisite. */
    private static function requireProfileFor(string $role, int $userId): void
    {
        if ($role === 'customer' && !Users::hasCustomerProfile($userId)) {
            Response::redirect('/profile/setup');
        }
        if ($role === 'artisan' && !Users::hasArtisanProfile($userId)) {
            Response::redirect('/profile/setup');
        }
    }
}
