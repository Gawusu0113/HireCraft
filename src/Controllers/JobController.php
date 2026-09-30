<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Engine\SmartMatchEngine as E;
use HireCraft\Repo\Lookups;
use HireCraft\Repo\MatchData;
use HireCraft\Repo\Users;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\Uploads;
use HireCraft\Support\View;

/** Job posting and the SmartMatch results page (FR: matching, budget check, feasibility). */
final class JobController
{
    public function showNew(): void
    {
        Auth::requireRole('customer');
        if (!Users::hasCustomerProfile((int)Auth::id())) {
            Response::redirect('/profile/setup');
        }
        View::render('jobs/new', [
            'categories' => Lookups::categories(),
            'skillsByCategory' => Lookups::skillsByCategory(),
            'areas' => Lookups::areas(),
        ]);
    }

    /**
     * A job can only be edited while it's still at the pure SmartMatch-shortlist
     * stage: no artisan has been asked for a quotation, applied, or been hired
     * yet. The moment an artisan_job_applications row exists, artisans are
     * already actively engaging with the job as posted, so letting the
     * customer silently change the details out from under them would be
     * confusing (and could invalidate a quotation already on its way).
     */
    public static function isEditable(array $job): bool
    {
        $jobId = (int)$job['id'];
        $hasApplications = Db::scalar('SELECT COUNT(*) FROM artisan_job_applications WHERE job_request_id = ?', [$jobId]);
        $hasWork = Db::scalar('SELECT COUNT(*) FROM jobs WHERE job_request_id = ?', [$jobId]);
        return !$hasApplications && !$hasWork && in_array($job['status'], ['posted', 'matched'], true);
    }

    private static function ownedEditableJob(int $jobId, int $userId): array
    {
        $job = Db::one('SELECT * FROM job_requests WHERE id = ?', [$jobId]);
        if (!$job || (int)$job['customer_id'] !== $userId) {
            Response::notFound();
        }
        if (!self::isEditable($job)) {
            Auth::flash('error', 'This job can no longer be edited — an artisan has already engaged with it (or it\'s no longer open). You can message them from the job page instead.');
            Response::redirect('/jobs/' . $jobId);
        }
        return $job;
    }

    public function showEdit(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $job = self::ownedEditableJob($jobId, (int)Auth::id());

        $skillIds = array_map('intval', array_column(
            Db::all('SELECT skill_id FROM job_required_skills WHERE job_request_id = ?', [$jobId]), 'skill_id'
        ));
        $photos = Db::all('SELECT id, image_path, caption FROM job_images WHERE job_request_id = ? ORDER BY id', [$jobId]);

        View::render('jobs/edit', [
            'job' => $job,
            'categories' => Lookups::categories(),
            'skillsByCategory' => Lookups::skillsByCategory(),
            'areas' => Lookups::areas(),
            'skillIds' => $skillIds,
            'photos' => $photos,
        ]);
    }

    public function update(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $userId = (int)Auth::id();
        self::ownedEditableJob($jobId, $userId);

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $areaId = (int)($_POST['area_id'] ?? 0);
        $addressNote = trim((string)($_POST['address_note'] ?? ''));
        $preferredDate = trim((string)($_POST['preferred_date'] ?? ''));
        $preferredTime = (string)($_POST['preferred_time'] ?? 'any');
        $urgency = (string)($_POST['urgency'] ?? 'normal');
        $budgetType = (string)($_POST['budget_type'] ?? 'range');
        $budgetMin = $_POST['budget_min'] ?? '';
        $budgetMax = $_POST['budget_max'] ?? '';
        $fixedPrice = $_POST['fixed_price'] ?? '';
        $isNegotiable = !empty($_POST['is_negotiable']) ? 1 : 0;
        $visibility = !empty($_POST['discoverable']) ? 'public' : 'matching_only';
        $specialRequirements = trim((string)($_POST['special_requirements'] ?? ''));
        $skillIds = array_values(array_unique(array_map('intval', (array)($_POST['skills'] ?? []))));

        $errors = [];
        if ($title === '' || mb_strlen($title) > 150) $errors[] = 'Please give your job a short title.';
        if (mb_strlen($description) < 20) $errors[] = 'Please describe the job in at least 20 characters.';
        $validAreaIds = array_column(Lookups::areas(), 'id');
        if (!$areaId || !in_array($areaId, $validAreaIds, true)) $errors[] = 'Please choose the job location.';
        if (!$skillIds) $errors[] = 'Please select at least one skill this job needs.';
        if (!in_array($preferredTime, ['morning', 'afternoon', 'evening', 'any'], true)) $preferredTime = 'any';
        if (!in_array($urgency, ['low', 'normal', 'high', 'emergency'], true)) $urgency = 'normal';
        if (!in_array($budgetType, ['range', 'fixed', 'unknown'], true)) $budgetType = 'unknown';
        if ($preferredDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) $preferredDate = '';

        $jobBudgetMin = null;
        $jobBudgetMax = null;
        if ($budgetType === 'range') {
            $min = (float)$budgetMin;
            $max = (float)$budgetMax;
            if ($budgetMin === '' || $budgetMax === '' || $max < $min || $min < 0) {
                $errors[] = 'Please enter a valid budget range (maximum at least the minimum).';
            } else {
                $jobBudgetMin = $min;
                $jobBudgetMax = $max;
            }
        } elseif ($budgetType === 'fixed') {
            $price = (float)$fixedPrice;
            if ($fixedPrice === '' || $price <= 0) {
                $errors[] = 'Please enter your fixed budget.';
            } else {
                $jobBudgetMax = $price;
            }
        }

        if ($errors) {
            foreach ($errors as $e) Auth::flash('error', $e);
            Response::redirect('/jobs/' . $jobId . '/edit');
        }

        $ref = MatchData::ref();
        $engineJob = [
            'title' => $title,
            'description' => $description,
            'categoryId' => null,
            'skillIds' => $skillIds,
            'areaId' => $areaId,
            'date' => $preferredDate !== '' ? $preferredDate : null,
            'urgency' => $urgency,
            'budgetType' => $budgetType,
            'budgetMin' => $jobBudgetMin,
            'budgetMax' => $jobBudgetMax,
            'negotiable' => (bool)$isNegotiable,
        ];
        $analysis = E::analyzeJob($engineJob, $ref);
        if (!empty($analysis['missing'])) {
            Auth::flash('error', 'Please add: ' . implode(', ', $analysis['missing']) . '.');
            Response::redirect('/jobs/' . $jobId . '/edit');
        }

        $artisans = MatchData::artisans();
        $benchmarks = MatchData::benchmarks();
        $activeConfig = MatchData::activeConfig();
        $result = E::match($engineJob, $ref, $artisans, ['config' => $activeConfig['config'], 'benchmarks' => $benchmarks]);
        $primaryCategoryId = MatchData::categoryIdsBySlug()[$analysis['primary']] ?? null;

        // Photo removals the customer checked, plus any newly attached photos —
        // capped at 6 total, same limit as posting a new job.
        $removeIds = array_map('intval', (array)($_POST['remove_photos'] ?? []));
        $existingPhotos = Db::all('SELECT id, image_path FROM job_images WHERE job_request_id = ?', [$jobId]);
        $toDelete = array_filter($existingPhotos, fn($p) => in_array((int)$p['id'], $removeIds, true));
        $remainingCount = count($existingPhotos) - count($toDelete);

        $uploaded = Uploads::saveMany($_FILES['photos'] ?? null, 'jobs/' . $jobId);
        foreach ($uploaded['errors'] as $upErr) {
            Auth::flash('error', $upErr);
        }
        $newPaths = $uploaded['paths'];
        if ($remainingCount + count($newPaths) > 6) {
            $allowed = max(0, 6 - $remainingCount);
            $extra = array_slice($newPaths, $allowed);
            $newPaths = array_slice($newPaths, 0, $allowed);
            foreach ($extra as $p) {
                $full = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $p;
                if (is_file($full)) @unlink($full);
            }
            Auth::flash('error', 'A job can have at most 6 photos — remove some existing ones to add more.');
        }

        Db::transaction(function () use (
            $jobId, $title, $description, $areaId, $addressNote, $preferredDate, $preferredTime, $urgency,
            $budgetType, $jobBudgetMin, $jobBudgetMax, $isNegotiable, $visibility, $specialRequirements, $skillIds,
            $analysis, $result, $activeConfig, $primaryCategoryId, $toDelete, $newPaths
        ) {
            Db::run(
                'UPDATE job_requests SET
                    title = ?, category_id = ?, description = ?, area_id = ?, address_note = ?, preferred_date = ?,
                    preferred_time = ?, urgency = ?, budget_type = ?, budget_min = ?, budget_max = ?, is_negotiable = ?,
                    visibility = ?, special_requirements = ?, complexity = ?, is_multi_trade = ?, status = "matched"
                 WHERE id = ?',
                [
                    $title, $primaryCategoryId, $description, $areaId, $addressNote !== '' ? $addressNote : null,
                    $preferredDate !== '' ? $preferredDate : null, $preferredTime, $urgency, $budgetType,
                    $jobBudgetMin, $jobBudgetMax, $isNegotiable, $visibility, $specialRequirements !== '' ? $specialRequirements : null,
                    $analysis['complexity'], $analysis['multiTrade'] ? 1 : 0, $jobId,
                ]
            );

            Db::run('DELETE FROM job_required_skills WHERE job_request_id = ?', [$jobId]);
            foreach ($skillIds as $skillId) {
                Db::run('INSERT INTO job_required_skills (job_request_id, skill_id) VALUES (?, ?)', [$jobId, $skillId]);
            }

            foreach ($toDelete as $p) {
                Db::run('DELETE FROM job_images WHERE id = ?', [$p['id']]);
            }
            foreach ($newPaths as $path) {
                Db::run('INSERT INTO job_images (job_request_id, image_path) VALUES (?, ?)', [$jobId, $path]);
            }

            Db::run('DELETE FROM budget_analysis_results WHERE job_request_id = ?', [$jobId]);
            Db::run('DELETE FROM job_feasibility_results WHERE job_request_id = ?', [$jobId]);
            Db::run('DELETE FROM artisan_job_matches WHERE job_request_id = ?', [$jobId]);

            self::persistBudget($jobId, $jobBudgetMin, $jobBudgetMax, $result['budget']);
            self::persistFeasibility($jobId, $result['feasibility']);
            self::persistMatches($jobId, $result, $activeConfig['version']);
        });

        foreach ($toDelete as $p) {
            $full = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $p['image_path'];
            if (is_file($full)) @unlink($full);
        }

        Auth::flash('success', 'Your job has been updated — here are the refreshed SmartMatch results.');
        Response::redirect('/jobs/' . $jobId . '/results');
    }

    public function create(): void
    {
        Auth::requireRole('customer');
        $userId = (int)Auth::id();
        if (!Users::hasCustomerProfile($userId)) {
            Response::redirect('/profile/setup');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $areaId = (int)($_POST['area_id'] ?? 0);
        $addressNote = trim((string)($_POST['address_note'] ?? ''));
        $preferredDate = trim((string)($_POST['preferred_date'] ?? ''));
        $preferredTime = (string)($_POST['preferred_time'] ?? 'any');
        $urgency = (string)($_POST['urgency'] ?? 'normal');
        $budgetType = (string)($_POST['budget_type'] ?? 'range');
        $budgetMin = $_POST['budget_min'] ?? '';
        $budgetMax = $_POST['budget_max'] ?? '';
        $fixedPrice = $_POST['fixed_price'] ?? '';
        $isNegotiable = !empty($_POST['is_negotiable']) ? 1 : 0;
        $visibility = !empty($_POST['discoverable']) ? 'public' : 'matching_only';
        $specialRequirements = trim((string)($_POST['special_requirements'] ?? ''));
        $skillIds = array_values(array_unique(array_map('intval', (array)($_POST['skills'] ?? []))));

        $errors = [];
        if ($title === '' || mb_strlen($title) > 150) $errors[] = 'Please give your job a short title.';
        if (mb_strlen($description) < 20) $errors[] = 'Please describe the job in at least 20 characters.';
        $validAreaIds = array_column(Lookups::areas(), 'id');
        if (!$areaId || !in_array($areaId, $validAreaIds, true)) $errors[] = 'Please choose the job location.';
        if (!$skillIds) $errors[] = 'Please select at least one skill this job needs.';
        if (!in_array($preferredTime, ['morning', 'afternoon', 'evening', 'any'], true)) $preferredTime = 'any';
        if (!in_array($urgency, ['low', 'normal', 'high', 'emergency'], true)) $urgency = 'normal';
        if (!in_array($budgetType, ['range', 'fixed', 'unknown'], true)) $budgetType = 'unknown';
        if ($preferredDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) $preferredDate = '';

        $jobBudgetMin = null;
        $jobBudgetMax = null;
        if ($budgetType === 'range') {
            $min = (float)$budgetMin;
            $max = (float)$budgetMax;
            if ($budgetMin === '' || $budgetMax === '' || $max < $min || $min < 0) {
                $errors[] = 'Please enter a valid budget range (maximum at least the minimum).';
            } else {
                $jobBudgetMin = $min;
                $jobBudgetMax = $max;
            }
        } elseif ($budgetType === 'fixed') {
            $price = (float)$fixedPrice;
            if ($fixedPrice === '' || $price <= 0) {
                $errors[] = 'Please enter your fixed budget.';
            } else {
                $jobBudgetMax = $price;
            }
        }

        if ($errors) {
            foreach ($errors as $e) Auth::flash('error', $e);
            Response::redirect('/jobs/new');
        }

        $ref = MatchData::ref();
        $job = [
            'title' => $title,
            'description' => $description,
            'categoryId' => null,
            'skillIds' => $skillIds,
            'areaId' => $areaId,
            'date' => $preferredDate !== '' ? $preferredDate : null,
            'urgency' => $urgency,
            'budgetType' => $budgetType,
            'budgetMin' => $jobBudgetMin,
            'budgetMax' => $jobBudgetMax,
            'negotiable' => (bool)$isNegotiable,
        ];
        $analysis = E::analyzeJob($job, $ref);
        if (!empty($analysis['missing'])) {
            Auth::flash('error', 'Please add: ' . implode(', ', $analysis['missing']) . '.');
            Response::redirect('/jobs/new');
        }

        $artisans = MatchData::artisans();
        $benchmarks = MatchData::benchmarks();
        $activeConfig = MatchData::activeConfig();
        $result = E::match($job, $ref, $artisans, ['config' => $activeConfig['config'], 'benchmarks' => $benchmarks]);

        // The engine identifies trades by slug (see MatchData::ref()); the DB's
        // job_requests.category_id is a numeric FK, so translate at this boundary.
        $primaryCategoryId = MatchData::categoryIdsBySlug()[$analysis['primary']] ?? null;

        // Save any attached photos now that we know the job will actually be
        // created (validation above has already passed), so a rejected
        // submission never leaves orphaned files behind. A random per-submission
        // folder means we don't need the job's id, which doesn't exist yet.
        $photoBatch = 'jobs/' . bin2hex(random_bytes(8));
        $uploaded = Uploads::saveMany($_FILES['photos'] ?? null, $photoBatch);
        foreach ($uploaded['errors'] as $upErr) {
            Auth::flash('error', $upErr);
        }
        $photoPaths = $uploaded['paths'];

        $jobId = Db::transaction(function () use (
            $userId, $title, $description, $areaId, $addressNote, $preferredDate, $preferredTime, $urgency,
            $budgetType, $jobBudgetMin, $jobBudgetMax, $isNegotiable, $visibility, $specialRequirements, $skillIds,
            $analysis, $result, $activeConfig, $primaryCategoryId, $photoPaths
        ) {
            $jobId = Db::insert(
                'INSERT INTO job_requests
                    (customer_id, title, category_id, description, area_id, address_note, preferred_date, preferred_time,
                     urgency, budget_type, budget_min, budget_max, is_negotiable, visibility, complexity, is_multi_trade, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "matched")',
                [
                    $userId, $title, $primaryCategoryId, $description, $areaId, $addressNote !== '' ? $addressNote : null,
                    $preferredDate !== '' ? $preferredDate : null, $preferredTime, $urgency, $budgetType,
                    $jobBudgetMin, $jobBudgetMax, $isNegotiable, $visibility, $analysis['complexity'], $analysis['multiTrade'] ? 1 : 0,
                ]
            );
            foreach ($skillIds as $skillId) {
                Db::run('INSERT INTO job_required_skills (job_request_id, skill_id) VALUES (?, ?)', [$jobId, $skillId]);
            }
            foreach ($photoPaths as $path) {
                Db::run('INSERT INTO job_images (job_request_id, image_path) VALUES (?, ?)', [$jobId, $path]);
            }

            self::persistBudget($jobId, $jobBudgetMin, $jobBudgetMax, $result['budget']);
            self::persistFeasibility($jobId, $result['feasibility']);
            self::persistMatches($jobId, $result, $activeConfig['version']);

            return $jobId;
        });

        // Notify the best-fitting artisans that a new matching job is available to
        // discover (FR section 39) — only for publicly discoverable jobs, and only
        // the strong matches, so this doesn't turn into spam for every artisan.
        if ($visibility === 'public') {
            $topArtisanIds = Db::all(
                "SELECT artisan_id FROM artisan_job_matches
                 WHERE job_request_id = ? AND recommendation_level IN ('highly_recommended', 'recommended')
                 ORDER BY overall_match_score DESC LIMIT 5",
                [$jobId]
            );
            foreach ($topArtisanIds as $row) {
                Notifier::send((int)$row['artisan_id'], 'match', 'A matching job was posted', $title, '/artisan/jobs');
            }
        }

        Auth::flash('success', 'Your job is posted. Here is what SmartMatch found.');
        Response::redirect('/jobs/' . $jobId . '/results');
    }

    public function showResults(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $job = Db::one(
            'SELECT jr.*, sc.name AS category_name, a.name AS area_name
             FROM job_requests jr JOIN service_categories sc ON sc.id = jr.category_id JOIN areas a ON a.id = jr.area_id
             WHERE jr.id = ?', [$jobId]
        );
        if (!$job || (int)$job['customer_id'] !== (int)Auth::id()) {
            Response::notFound();
        }

        $requiredSkills = Db::all(
            'SELECT s.id, s.name, s.category_id, sc.name AS category_name FROM job_required_skills jrs
             JOIN skills s ON s.id = jrs.skill_id JOIN service_categories sc ON sc.id = s.category_id
             WHERE jrs.job_request_id = ?', [$jobId]
        );
        $photos = Db::all('SELECT id, image_path, caption FROM job_images WHERE job_request_id = ? ORDER BY id', [$jobId]);
        $budget = Db::one('SELECT * FROM budget_analysis_results WHERE job_request_id = ? ORDER BY id DESC LIMIT 1', [$jobId]);
        $feasibility = Db::one('SELECT * FROM job_feasibility_results WHERE job_request_id = ? ORDER BY id DESC LIMIT 1', [$jobId]);

        $matches = Db::all(
            'SELECT m.*, u.full_name, ap.business_name, ap.category_id, sc.name AS category_name,
                    ap.years_experience, ap.rating_mean, ap.rating_count, ap.trust_score AS artisan_trust_score, a.name AS artisan_area
             FROM artisan_job_matches m
             JOIN artisan_profiles ap ON ap.user_id = m.artisan_id
             JOIN users u ON u.id = m.artisan_id
             JOIN service_categories sc ON sc.id = ap.category_id
             JOIN areas a ON a.id = ap.base_area_id
             WHERE m.job_request_id = ?
             ORDER BY sc.name, m.overall_match_score DESC, m.artisan_id',
            [$jobId]
        );
        foreach ($matches as &$m) {
            $m['explanation'] = json_decode($m['explanation'], true) ?: [];
        }
        unset($m);

        $groups = [];
        foreach ($matches as $m) {
            $groups[$m['category_name']][] = $m;
        }

        // Evaluation feedback (FR: "was this helpful?" capture) — pre-fill the
        // form with whatever this customer already told us about this job, if
        // anything, so revisiting the results page doesn't lose their answer.
        $feedback = Db::one(
            'SELECT * FROM recommendation_feedback WHERE job_request_id = ? AND customer_id = ?',
            [$jobId, (int)Auth::id()]
        );

        View::render('jobs/results', [
            'job' => $job, 'requiredSkills' => $requiredSkills, 'photos' => $photos, 'budget' => $budget,
            'feasibility' => $feasibility, 'groups' => $groups, 'matches' => $matches,
            'isMultiTrade' => (bool)$job['is_multi_trade'], 'feedback' => $feedback,
            'isEditable' => self::isEditable($job),
        ]);
    }

    /**
     * "Was this helpful?" evaluation feedback capture (MUST scope item, and
     * the raw data Chapter 4's user evaluation section needs) — one row per
     * (job, customer), upserted so revisiting the results page updates
     * rather than duplicates their answer.
     */
    public function submitFeedback(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $customerId = (int)Auth::id();
        $owns = Db::scalar('SELECT COUNT(*) FROM job_requests WHERE id = ? AND customer_id = ?', [$jobId, $customerId]);
        if (!$owns) {
            Response::notFound();
        }

        $helpful = (string)($_POST['helpful'] ?? '');
        if (!in_array($helpful, ['yes', 'no', 'partially'], true)) {
            Auth::flash('error', 'Please choose whether the recommendations were helpful.');
            Response::redirect('/jobs/' . $jobId . '/results');
        }

        $validReasons = ['best_match', 'lowest_price', 'highest_rating', 'most_trusted', 'most_experienced', 'recommended_by_hirecraft', 'other'];
        $reasonChosen = (string)($_POST['reason_chosen'] ?? '');
        $reasonChosen = in_array($reasonChosen, $validReasons, true) ? $reasonChosen : null;

        $understood = (int)($_POST['understood_explanation'] ?? 0);
        $understood = ($understood >= 1 && $understood <= 5) ? $understood : null;

        $chosenArtisanId = (int)($_POST['chosen_artisan_id'] ?? 0) ?: null;
        $chosenRank = null;
        if ($chosenArtisanId) {
            $isRealMatch = Db::scalar(
                'SELECT COUNT(*) FROM artisan_job_matches WHERE job_request_id = ? AND artisan_id = ?',
                [$jobId, $chosenArtisanId]
            );
            if ($isRealMatch) {
                $betterCount = Db::scalar(
                    'SELECT COUNT(*) FROM artisan_job_matches
                     WHERE job_request_id = ? AND overall_match_score > (
                         SELECT overall_match_score FROM artisan_job_matches WHERE job_request_id = ? AND artisan_id = ?
                     )',
                    [$jobId, $jobId, $chosenArtisanId]
                );
                $chosenRank = (int)$betterCount + 1;
            } else {
                $chosenArtisanId = null;
            }
        }

        $comment = trim((string)($_POST['comment'] ?? ''));
        $comment = $comment !== '' ? mb_substr($comment, 0, 500) : null;

        $existingId = Db::scalar('SELECT id FROM recommendation_feedback WHERE job_request_id = ? AND customer_id = ?', [$jobId, $customerId]);
        if ($existingId) {
            Db::run(
                'UPDATE recommendation_feedback
                 SET chosen_artisan_id = ?, helpful = ?, reason_chosen = ?, understood_explanation = ?, chosen_rank = ?, comment = ?
                 WHERE id = ?',
                [$chosenArtisanId, $helpful, $reasonChosen, $understood, $chosenRank, $comment, $existingId]
            );
        } else {
            Db::run(
                'INSERT INTO recommendation_feedback
                    (job_request_id, customer_id, chosen_artisan_id, helpful, reason_chosen, understood_explanation, chosen_rank, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$jobId, $customerId, $chosenArtisanId, $helpful, $reasonChosen, $understood, $chosenRank, $comment]
            );
        }

        Auth::flash('success', 'Thanks — your feedback helps evaluate SmartMatch.');
        Response::redirect('/jobs/' . $jobId . '/results');
    }

    /**
     * Side-by-side comparison of 2-3 artisans a customer picked from their
     * SmartMatch results: same per-component score breakdown the results
     * page shows, plus trust, rating, price range (for this job's
     * complexity) and weekly availability, so the customer can weigh
     * finalists against each other rather than one card at a time.
     */
    public function compare(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $job = Db::one(
            'SELECT jr.*, sc.name AS category_name, a.name AS area_name
             FROM job_requests jr JOIN service_categories sc ON sc.id = jr.category_id JOIN areas a ON a.id = jr.area_id
             WHERE jr.id = ?', [$jobId]
        );
        if (!$job || (int)$job['customer_id'] !== (int)Auth::id()) {
            Response::notFound();
        }

        $artisanIds = array_values(array_unique(array_map('intval', (array)($_GET['artisans'] ?? []))));
        $artisanIds = array_slice($artisanIds, 0, 3);
        if (count($artisanIds) < 2) {
            Auth::flash('error', 'Select at least two artisans from your SmartMatch results to compare.');
            Response::redirect('/jobs/' . $jobId . '/results');
        }

        $placeholders = implode(',', array_fill(0, count($artisanIds), '?'));
        $rows = Db::all(
            "SELECT m.*, u.full_name, ap.business_name, ap.category_id, sc.name AS category_name, ap.years_experience,
                    ap.rating_mean, ap.rating_count, ap.trust_score AS artisan_trust_score, ap.completed_jobs,
                    ap.travel_radius_km, ap.accepts_emergency, a.name AS artisan_area
             FROM artisan_job_matches m
             JOIN artisan_profiles ap ON ap.user_id = m.artisan_id
             JOIN users u ON u.id = m.artisan_id
             JOIN service_categories sc ON sc.id = ap.category_id
             JOIN areas a ON a.id = ap.base_area_id
             WHERE m.job_request_id = ? AND m.artisan_id IN ($placeholders)
             ORDER BY m.overall_match_score DESC",
            array_merge([$jobId], $artisanIds)
        );
        if (count($rows) < 2) {
            Auth::flash('error', 'Could not find match data for the selected artisans — try again from your results.');
            Response::redirect('/jobs/' . $jobId . '/results');
        }

        $ids = array_map(fn($r) => (int)$r['artisan_id'], $rows);
        $ph2 = implode(',', array_fill(0, count($ids), '?'));
        $pricesByArtisan = [];
        foreach (Db::all("SELECT artisan_id, complexity, min_price, max_price FROM artisan_price_ranges WHERE artisan_id IN ($ph2)", $ids) as $p) {
            $pricesByArtisan[(int)$p['artisan_id']][$p['complexity']] = $p;
        }
        $weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $availByArtisan = [];
        foreach (Db::all("SELECT artisan_id, weekday, start_time, end_time FROM artisan_availability WHERE artisan_id IN ($ph2) ORDER BY weekday", $ids) as $av) {
            $availByArtisan[(int)$av['artisan_id']][] = $weekdayNames[(int)$av['weekday']] . ' ' . substr($av['start_time'], 0, 5) . "\u{2013}" . substr($av['end_time'], 0, 5);
        }

        foreach ($rows as &$r) {
            $r['explanation'] = json_decode($r['explanation'], true) ?: [];
            $complexity = $job['complexity'] ?: 'medium';
            $r['price_range'] = $pricesByArtisan[(int)$r['artisan_id']][$complexity]
                ?? $pricesByArtisan[(int)$r['artisan_id']]['medium']
                ?? null;
            $r['availability'] = $availByArtisan[(int)$r['artisan_id']] ?? [];
        }
        unset($r);

        View::render('jobs/compare', ['job' => $job, 'rows' => $rows]);
    }

    /**
     * These three persist* helpers are also used by database/seed.php so the
     * one demo job it creates gets real SmartMatch results (budget,
     * feasibility, ranked matches) instead of a bare "posted" row — the
     * exact same pipeline a customer's real job goes through here.
     */
    public static function persistBudget(int $jobId, ?float $budgetMin, ?float $budgetMax, array $b): void
    {
        $klass = strtolower(str_replace(' ', '_', $b['klass']));
        $bench = $b['benchmark'] ?? null;
        Db::run(
            'INSERT INTO budget_analysis_results
                (job_request_id, budget_min, budget_max, assessment, confidence_level, benchmark_n, benchmark_p25, benchmark_median, benchmark_p75, reason, recommended_action)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $jobId, $budgetMin, $budgetMax, $klass, $b['confidence'],
                $bench['n'] ?? 0, $bench['p25'] ?? null, $bench['median'] ?? null, $bench['p75'] ?? null,
                $b['reason'], $b['action'],
            ]
        );
    }

    public static function persistFeasibility(int $jobId, array $f): void
    {
        $level = strtolower(str_replace(' ', '_', $f['level']));
        Db::run(
            'INSERT INTO job_feasibility_results (job_request_id, level, suitable_artisans, available_on_date, reasons) VALUES (?, ?, ?, ?, ?)',
            [$jobId, $level, $f['suitableCount'] ?? 0, $f['availableOnDate'] ?? 0, json_encode($f['reasons'] ?? [], JSON_UNESCAPED_SLASHES)]
        );
    }

    public static function persistMatches(int $jobId, array $result, int $configVersion): void
    {
        $rows = [];
        if (!empty($result['groups'])) {
            foreach ($result['groups'] as $group) {
                foreach ($group['matches'] as $m) $rows[] = $m;
            }
        } else {
            $rows = $result['matches'];
        }
        $weights = $result['weights'];
        $levelMap = [
            'HIGHLY RECOMMENDED' => 'highly_recommended', 'RECOMMENDED' => 'recommended',
            'POSSIBLE MATCH' => 'possible_match', 'LOW COMPATIBILITY' => 'low_compatibility',
        ];
        foreach ($rows as $m) {
            Db::run(
                'INSERT INTO artisan_job_matches
                    (job_request_id, artisan_id, config_version, overall_match_score, skill_match_score, location_match_score,
                     budget_match_score, availability_score, experience_score, trust_score, rating_score, portfolio_relevance_score,
                     recommendation_level, capped_reason, explanation, weights_snapshot)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $jobId, $m['artisan']['id'], $configVersion, $m['score'],
                    $m['breakdown']['skill']['points'], $m['breakdown']['location']['points'],
                    $m['breakdown']['budget']['points'], $m['breakdown']['availability']['points'],
                    $m['breakdown']['experience']['points'], $m['breakdown']['trust']['points'],
                    $m['breakdown']['rating']['points'], $m['breakdown']['portfolio']['points'],
                    $levelMap[$m['level']] ?? 'low_compatibility',
                    $m['capped']['reason'] ?? null,
                    json_encode($m['explanation'], JSON_UNESCAPED_SLASHES),
                    json_encode($weights, JSON_UNESCAPED_SLASHES),
                ]
            );
        }
    }
}
