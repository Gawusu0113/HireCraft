<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Repo\Lookups;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\View;

/**
 * A general artisan directory a customer can browse and filter directly —
 * independent of any specific job (FR: section 23, "Artisan discovery").
 * SmartMatch's job-specific ranking lives on the results/compare pages;
 * this is the open "look for someone" search.
 */
final class ArtisanSearchController
{
    private const SORTS = ['best', 'trusted', 'rated', 'experienced', 'nearest', 'budget', 'available'];

    public function index(): void
    {
        Auth::requireLogin();

        $categoryId = (int)($_GET['category'] ?? 0) ?: null;
        $skillIds = array_values(array_filter(array_map('intval', (array)($_GET['skills'] ?? []))));
        $areaId = (int)($_GET['area'] ?? 0) ?: null;
        $minTrust = (int)($_GET['min_trust'] ?? 0);
        $minRating = (float)($_GET['min_rating'] ?? 0);
        $verification = (string)($_GET['verification'] ?? '');
        $minExperience = (int)($_GET['min_experience'] ?? 0);
        $emergencyOnly = !empty($_GET['emergency']);
        $availableToday = !empty($_GET['available_today']);
        $budgetMin = $_GET['budget_min'] ?? '';
        $budgetMax = $_GET['budget_max'] ?? '';
        $sortIn = (string)($_GET['sort'] ?? 'best');
        $sort = in_array($sortIn, self::SORTS, true) ? $sortIn : 'best';

        $where = ["ap.approval_status = 'approved'"];
        $params = [];
        if ($categoryId) {
            $where[] = 'ap.category_id = ?';
            $params[] = $categoryId;
        }
        if ($areaId) {
            $where[] = '(ap.base_area_id = ? OR EXISTS (SELECT 1 FROM artisan_service_areas x WHERE x.artisan_id = ap.user_id AND x.area_id = ?))';
            $params[] = $areaId;
            $params[] = $areaId;
        }
        if ($minTrust > 0) {
            $where[] = 'ap.trust_score >= ?';
            $params[] = $minTrust;
        }
        if ($minRating > 0) {
            $where[] = 'ap.rating_mean >= ? AND ap.rating_count > 0';
            $params[] = $minRating;
        }
        if ($verification !== '' && in_array($verification, ['basic', 'phone', 'email', 'identity', 'skill', 'full'], true)) {
            $where[] = 'ap.verification_level = ?';
            $params[] = $verification;
        }
        if ($minExperience > 0) {
            $where[] = 'ap.years_experience >= ?';
            $params[] = $minExperience;
        }
        if ($emergencyOnly) {
            $where[] = 'ap.accepts_emergency = 1';
        }
        if ($skillIds) {
            $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
            $where[] = "ap.user_id IN (SELECT artisan_id FROM artisan_skills WHERE skill_id IN ($placeholders) GROUP BY artisan_id HAVING COUNT(DISTINCT skill_id) = " . count($skillIds) . ')';
            array_push($params, ...$skillIds);
        }
        if ($availableToday) {
            $todayWeekday = (int)date('w'); // 0=Sun..6=Sat, matching artisan_availability.weekday (see SmartMatchEngine::dayIndex)
            $where[] = 'EXISTS (SELECT 1 FROM artisan_availability av WHERE av.artisan_id = ap.user_id AND av.weekday = ?)';
            $params[] = $todayWeekday;
        }

        $sql = "SELECT ap.*, u.full_name, sc.name AS category_name, sc.slug AS category_slug, a.name AS area_name
                FROM artisan_profiles ap
                JOIN users u ON u.id = ap.user_id
                JOIN service_categories sc ON sc.id = ap.category_id
                JOIN areas a ON a.id = ap.base_area_id
                WHERE " . implode(' AND ', $where);
        $artisans = Db::all($sql, $params);

        // Budget-compatible sort/annotation: closest fit to the customer's stated
        // range against this artisan's medium-complexity price band.
        if ($budgetMin !== '' || $budgetMax !== '') {
            $bMin = $budgetMin !== '' ? (float)$budgetMin : null;
            $bMax = $budgetMax !== '' ? (float)$budgetMax : null;
            $mid = $bMin !== null && $bMax !== null ? ($bMin + $bMax) / 2 : ($bMin ?? $bMax);
            $artisanIds = array_column($artisans, 'user_id');
            $prices = [];
            if ($artisanIds) {
                $placeholders = implode(',', array_fill(0, count($artisanIds), '?'));
                foreach (Db::all("SELECT artisan_id, min_price, max_price FROM artisan_price_ranges WHERE complexity = 'medium' AND artisan_id IN ($placeholders)", $artisanIds) as $p) {
                    $prices[(int)$p['artisan_id']] = $p;
                }
            }
            foreach ($artisans as &$art) {
                $p = $prices[(int)$art['user_id']] ?? null;
                if (!$p) {
                    $art['budget_fit'] = null;
                    continue;
                }
                $artMid = ((float)$p['min_price'] + (float)$p['max_price']) / 2;
                $art['budget_fit'] = $mid !== null ? abs($artMid - $mid) : null;
                $art['price_range'] = [$p['min_price'], $p['max_price']];
            }
            unset($art);
        }

        usort($artisans, function ($a, $b) use ($sort) {
            return match ($sort) {
                'trusted' => $b['trust_score'] <=> $a['trust_score'],
                'rated' => $b['rating_mean'] <=> $a['rating_mean'],
                'experienced' => $b['years_experience'] <=> $a['years_experience'],
                'nearest' => 0, // area filter already narrows this; stable order otherwise
                'budget' => ($a['budget_fit'] ?? PHP_FLOAT_MAX) <=> ($b['budget_fit'] ?? PHP_FLOAT_MAX),
                'available' => 0,
                default => ($b['trust_score'] * 0.4 + $b['rating_mean'] * 20 * 0.35 + min($b['years_experience'], 15) / 15 * 100 * 0.25)
                       <=> ($a['trust_score'] * 0.4 + $a['rating_mean'] * 20 * 0.35 + min($a['years_experience'], 15) / 15 * 100 * 0.25),
            };
        });

        View::render('customer/find_artisan', [
            'artisans' => $artisans, 'categories' => Lookups::categories(), 'areas' => Lookups::areas(),
            'skillsByCategory' => Lookups::skillsByCategory(),
            'filters' => compact('categoryId', 'skillIds', 'areaId', 'minTrust', 'minRating', 'verification', 'minExperience', 'emergencyOnly', 'availableToday', 'budgetMin', 'budgetMax', 'sort'),
        ]);
    }
}
