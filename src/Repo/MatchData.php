<?php
declare(strict_types=1);

namespace HireCraft\Repo;

use HireCraft\Support\Db;

/**
 * Builds the plain-array inputs SmartMatchEngine::match() expects (ref,
 * artisans, benchmarks) from the live database, so the engine itself never
 * has to know about SQL. Keeping this in one place means Task #15's job
 * results page and Task #17's admin what-if re-ranking build the exact
 * same inputs.
 */
final class MatchData
{
    /**
     * The engine (a faithful port of design/engine/engine.js) identifies a
     * trade by its short slug ("plumbing"), not by the numeric DB id — some
     * of its explanation text interpolates the category identifier directly
     * (e.g. "based on 18 similar medium plumbing jobs"), so using the slug
     * here is what keeps that text readable. The numeric category_id is the
     * DB's own FK and is only used at the DB boundary (see categoryIdBySlug()).
     *
     * @return array{skills: array, areas: array, categories: array}
     */
    public static function ref(): array
    {
        $slugById = self::categorySlugsById();
        $skills = [];
        foreach (Db::all('SELECT id, category_id, name, complexity_weight FROM skills WHERE is_active = 1') as $s) {
            $skills[(int)$s['id']] = [
                'id' => (int)$s['id'], 'name' => $s['name'],
                'category' => $slugById[(int)$s['category_id']], 'complexity' => (int)$s['complexity_weight'],
            ];
        }
        $areas = [];
        foreach (Db::all('SELECT id, name, latitude, longitude FROM areas') as $a) {
            $areas[(int)$a['id']] = ['id' => (int)$a['id'], 'name' => $a['name'], 'lat' => (float)$a['latitude'], 'lng' => (float)$a['longitude']];
        }
        $categories = [];
        foreach (Db::all('SELECT id, name, slug FROM service_categories') as $c) {
            $categories[$c['slug']] = ['id' => (int)$c['id'], 'name' => $c['name'], 'slug' => $c['slug']];
        }
        return ['skills' => $skills, 'areas' => $areas, 'categories' => $categories];
    }

    /** @return array<string, array{n:int, p25:float, median:float, p75:float}> Keyed "{categorySlug}:{complexity}". */
    public static function benchmarks(): array
    {
        $slugById = self::categorySlugsById();
        $out = [];
        foreach (Db::all('SELECT category_id, complexity, observations, p25, median, p75 FROM price_benchmarks') as $b) {
            $out[$slugById[(int)$b['category_id']] . ':' . $b['complexity']] = [
                'n' => (int)$b['observations'], 'p25' => (float)$b['p25'], 'median' => (float)$b['median'], 'p75' => (float)$b['p75'],
            ];
        }
        return $out;
    }

    /** @return array<int, string> DB category id -> slug. */
    public static function categorySlugsById(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            foreach (Db::all('SELECT id, slug FROM service_categories') as $c) {
                $cache[(int)$c['id']] = $c['slug'];
            }
        }
        return $cache;
    }

    /** @return array<string, int> Category slug -> DB category id (the reverse of categorySlugsById()). */
    public static function categoryIdsBySlug(): array
    {
        return array_flip(self::categorySlugsById());
    }

    /**
     * All artisans (any approval status — the engine itself excludes
     * non-approved ones, with an explanation) shaped for the engine.
     *
     * @return list<array>
     */
    public static function artisans(): array
    {
        $rows = Db::all(
            'SELECT ap.*, u.full_name, u.email_verified_at, u.phone_verified_at
             FROM artisan_profiles ap JOIN users u ON u.id = ap.user_id'
        );
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r['user_id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $skillsByArtisan = self::groupBy(
            Db::all("SELECT artisan_id, skill_id FROM artisan_skills WHERE artisan_id IN ($placeholders)", $ids),
            'artisan_id', 'skill_id'
        );
        $areasByArtisan = self::groupBy(
            Db::all("SELECT artisan_id, area_id FROM artisan_service_areas WHERE artisan_id IN ($placeholders)", $ids),
            'artisan_id', 'area_id'
        );
        $weekdaysByArtisan = self::groupBy(
            Db::all("SELECT artisan_id, weekday FROM artisan_availability WHERE artisan_id IN ($placeholders)", $ids),
            'artisan_id', 'weekday'
        );
        $blockedByArtisan = self::groupBy(
            Db::all("SELECT artisan_id, off_date FROM artisan_time_off WHERE artisan_id IN ($placeholders)", $ids),
            'artisan_id', 'off_date'
        );
        $pricesByArtisan = [];
        foreach (Db::all("SELECT artisan_id, complexity, min_price, max_price FROM artisan_price_ranges WHERE artisan_id IN ($placeholders)", $ids) as $p) {
            $pricesByArtisan[(int)$p['artisan_id']][$p['complexity']] = [(float)$p['min_price'], (float)$p['max_price']];
        }
        $identityByArtisan = [];
        $skillVerByArtisan = [];
        foreach (Db::all("SELECT artisan_id, type FROM verifications WHERE status = 'approved' AND artisan_id IN ($placeholders)", $ids) as $v) {
            if ($v['type'] === 'identity') $identityByArtisan[(int)$v['artisan_id']] = true;
            if ($v['type'] === 'skill') $skillVerByArtisan[(int)$v['artisan_id']] = true;
        }
        $historyByArtisan = [];
        foreach (Db::all(
            "SELECT j.artisan_id, jrs.skill_id, COUNT(*) AS n
             FROM jobs j JOIN job_requests jr ON jr.id = j.job_request_id
             JOIN job_required_skills jrs ON jrs.job_request_id = jr.id
             WHERE j.status = 'confirmed' AND j.artisan_id IN ($placeholders)
             GROUP BY j.artisan_id, jrs.skill_id", $ids
        ) as $h) {
            $historyByArtisan[(int)$h['artisan_id']][(int)$h['skill_id']] = (int)$h['n'];
        }
        $portfolioByArtisan = [];
        foreach (Db::all(
            "SELECT p.artisan_id, ps.skill_id, COUNT(*) AS n
             FROM portfolio_skills ps JOIN portfolios p ON p.id = ps.portfolio_id
             WHERE p.artisan_id IN ($placeholders) GROUP BY p.artisan_id, ps.skill_id", $ids
        ) as $p) {
            $portfolioByArtisan[(int)$p['artisan_id']][(int)$p['skill_id']] = (int)$p['n'];
        }

        $slugById = self::categorySlugsById();
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['user_id'];
            $out[] = [
                'id' => $id,
                'name' => $r['full_name'],
                'business' => $r['business_name'],
                'category' => $slugById[(int)$r['category_id']],
                'skills' => array_map('intval', $skillsByArtisan[$id] ?? []),
                'baseArea' => (int)$r['base_area_id'],
                'serviceAreas' => array_map('intval', $areasByArtisan[$id] ?? []),
                'radiusKm' => (int)$r['travel_radius_km'],
                'prices' => $pricesByArtisan[$id] ?? [],
                'weekdays' => array_map('intval', $weekdaysByArtisan[$id] ?? []),
                'blocked' => $blockedByArtisan[$id] ?? [],
                'emergency' => (bool)$r['accepts_emergency'],
                'years' => (int)$r['years_experience'],
                'verified' => [
                    'phone' => $r['phone_verified_at'] !== null,
                    'email' => $r['email_verified_at'] !== null,
                    'identity' => $identityByArtisan[$id] ?? false,
                    'skill' => $skillVerByArtisan[$id] ?? false,
                ],
                'profileCompleteness' => (float)$r['profile_completeness'],
                'completedJobs' => (int)$r['completed_jobs'],
                'completionRate' => (float)$r['completion_rate'],
                'responseRate' => (float)$r['response_rate'],
                'disputeFreeRate' => (float)$r['dispute_free_rate'],
                'ratingCount' => (int)$r['rating_count'],
                'ratingMean' => (float)$r['rating_mean'],
                'history' => $historyByArtisan[$id] ?? [],
                'portfolio' => $portfolioByArtisan[$id] ?? [],
                'status' => $r['approval_status'],
            ];
        }
        return $out;
    }

    /**
     * One artisan shaped for the engine (same shape as artisans()), or null
     * if no such artisan exists. Used by the two-sided "recommend jobs for
     * this artisan" flow, where we only need a single artisan's row.
     */
    public static function artisan(int $artisanId): ?array
    {
        foreach (self::artisans() as $a) {
            if ($a['id'] === $artisanId) return $a;
        }
        return null;
    }

    /** The live matching configuration: version number + the config array match() expects as opts.config. */
    public static function activeConfig(): array
    {
        $row = Db::one('SELECT config_version, weights, parameters FROM matching_config WHERE is_active = 1 ORDER BY config_version DESC LIMIT 1');
        if (!$row) {
            return ['version' => 1, 'config' => []];
        }
        $config = json_decode($row['parameters'], true) ?: [];
        $config['weights'] = json_decode($row['weights'], true) ?: [];
        return ['version' => (int)$row['config_version'], 'config' => $config];
    }

    /** @return array<int, array> */
    private static function groupBy(array $rows, string $keyCol, string $valCol): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row[$keyCol]][] = $row[$valCol];
        }
        return $out;
    }
}
