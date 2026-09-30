<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Engine\SmartMatchEngine as E;
use HireCraft\Repo\MatchData;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/**
 * Everything after the SmartMatch results page: requesting a quotation,
 * an artisan quoting or declining, the customer accepting and hiring,
 * job tracking through to completion, and the review that recomputes
 * rating and trust (FR: quotations, hiring, tracking, reviews).
 */
final class HireController
{
    // ------------------------------------------------------------------ customer: request a quotation
    public function requestQuotation(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $artisanId = (int)$args['artisan'];
        $job = self::ownedJob($jobId);

        $exists = Db::scalar(
            'SELECT COUNT(*) FROM artisan_job_applications WHERE job_request_id = ? AND artisan_id = ?',
            [$jobId, $artisanId]
        );
        if (!$exists) {
            $match = Db::one(
                'SELECT overall_match_score FROM artisan_job_matches WHERE job_request_id = ? AND artisan_id = ? ORDER BY id DESC LIMIT 1',
                [$jobId, $artisanId]
            );
            Db::run(
                'INSERT INTO artisan_job_applications (job_request_id, artisan_id, origin, status, match_score_at_time) VALUES (?, ?, "requested", "submitted", ?)',
                [$jobId, $artisanId, $match['overall_match_score'] ?? null]
            );
            Notifier::send($artisanId, 'application', 'You received a service request', $job['title'] ?? null, '/artisan/requests');
            Auth::flash('success', 'Quotation requested. You\'ll see it here once the artisan responds.');
        } else {
            Auth::flash('info', 'You already requested a quotation from this artisan.');
        }
        Response::redirect('/jobs/' . $jobId . '/results');
    }

    // ------------------------------------------------------------------ artisan: incoming requests
    public function myRequests(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $requests = Db::all(
            'SELECT aja.*, jr.customer_id, jr.title, jr.description, jr.urgency, jr.budget_type, jr.budget_min, jr.budget_max, jr.complexity,
                    a.name AS area_name, u.full_name AS customer_name
             FROM artisan_job_applications aja
             JOIN job_requests jr ON jr.id = aja.job_request_id
             JOIN areas a ON a.id = jr.area_id
             JOIN users u ON u.id = jr.customer_id
             WHERE aja.artisan_id = ? AND aja.status = "submitted"
             ORDER BY aja.created_at DESC',
            [$artisanId]
        );
        if ($requests) {
            $jobIds = array_column($requests, 'job_request_id');
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $photosByJob = [];
            foreach (Db::all("SELECT job_request_id, image_path, caption FROM job_images WHERE job_request_id IN ($placeholders) ORDER BY id", $jobIds) as $img) {
                $photosByJob[(int)$img['job_request_id']][] = $img;
            }
            foreach ($requests as &$r) {
                $r['photos'] = $photosByJob[(int)$r['job_request_id']] ?? [];
            }
            unset($r);
        }
        $quoted = Db::all(
            'SELECT aja.id AS application_id, jr.id AS job_id, jr.title, q.total_cost, q.status AS quote_status, q.estimated_days
             FROM artisan_job_applications aja
             JOIN job_requests jr ON jr.id = aja.job_request_id
             JOIN quotations q ON q.application_id = aja.id
             WHERE aja.artisan_id = ?
             ORDER BY q.created_at DESC',
            [$artisanId]
        );
        View::render('artisan/requests', ['requests' => $requests, 'quoted' => $quoted]);
    }

    // ------------------------------------------------------------------ artisan: job discovery (two-sided matching)
    /**
     * "Find jobs" page: ranks every publicly-discoverable open job against
     * this artisan's own profile, using the same SmartMatch engine's reverse
     * direction (recommendJobs — already covered by the pinned reference
     * tests), the way an artisan-facing job board would. Only job_requests
     * with visibility='public' show up here; a customer who leaves a job at
     * the default 'matching_only' visibility is only reaching the artisans
     * SmartMatch already shortlisted for them.
     */
    public function browseJobs(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();

        $profile = Db::one('SELECT approval_status FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        if (!$profile) {
            Response::redirect('/profile/setup');
        }
        if ($profile['approval_status'] !== 'approved') {
            View::render('artisan/browse_jobs', ['recommendations' => [], 'notApproved' => true]);
            return;
        }

        $rows = Db::all(
            "SELECT jr.*, a.name AS area_name, u.full_name AS customer_name
             FROM job_requests jr
             JOIN areas a ON a.id = jr.area_id
             JOIN users u ON u.id = jr.customer_id
             WHERE jr.visibility = 'public' AND jr.status IN ('posted', 'matched', 'quoted')
             ORDER BY jr.created_at DESC"
        );
        if (!$rows) {
            View::render('artisan/browse_jobs', ['recommendations' => [], 'notApproved' => false]);
            return;
        }

        $jobIds = array_map(fn($r) => (int)$r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $skillsByJob = [];
        foreach (Db::all("SELECT job_request_id, skill_id FROM job_required_skills WHERE job_request_id IN ($placeholders)", $jobIds) as $s) {
            $skillsByJob[(int)$s['job_request_id']][] = (int)$s['skill_id'];
        }
        $applied = Db::all(
            "SELECT job_request_id FROM artisan_job_applications WHERE artisan_id = ? AND job_request_id IN ($placeholders)",
            array_merge([$artisanId], $jobIds)
        );
        $appliedJobIds = array_map(fn($r) => (int)$r['job_request_id'], $applied);

        $me = MatchData::artisan($artisanId);
        if (!$me) {
            Response::redirect('/dashboard');
        }

        $rowById = [];
        $jobs = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $rowById[$id] = $r;
            $jobs[] = [
                'id' => $id,
                'title' => $r['title'],
                'description' => $r['description'],
                'categoryId' => null,
                'skillIds' => $skillsByJob[$id] ?? [],
                'areaId' => (int)$r['area_id'],
                'date' => $r['preferred_date'],
                'urgency' => $r['urgency'],
                'budgetType' => $r['budget_type'],
                'budgetMin' => $r['budget_min'] !== null ? (float)$r['budget_min'] : null,
                'budgetMax' => $r['budget_max'] !== null ? (float)$r['budget_max'] : null,
                'negotiable' => (bool)$r['is_negotiable'],
            ];
        }

        $ref = MatchData::ref();
        $benchmarks = MatchData::benchmarks();
        $activeConfig = MatchData::activeConfig();
        $recs = E::recommendJobs($jobs, $ref, $me, ['config' => $activeConfig['config'], 'benchmarks' => $benchmarks]);

        foreach ($recs as &$rec) {
            $id = (int)$rec['job']['id'];
            $rec['row'] = $rowById[$id];
            $rec['already_applied'] = in_array($id, $appliedJobIds, true);
            $rec['skill_names'] = array_values(array_filter(array_map(
                fn($sid) => $ref['skills'][$sid]['name'] ?? null,
                $skillsByJob[$id] ?? []
            )));
        }
        unset($rec);

        View::render('artisan/browse_jobs', ['recommendations' => $recs, 'notApproved' => false]);
    }

    public function applyToJob(array $args): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $jobId = (int)$args['id'];

        $job = Db::one(
            "SELECT id, customer_id, title FROM job_requests WHERE id = ? AND visibility = 'public' AND status IN ('posted', 'matched', 'quoted')",
            [$jobId]
        );
        if (!$job) {
            Response::notFound();
        }

        $exists = Db::scalar(
            'SELECT COUNT(*) FROM artisan_job_applications WHERE job_request_id = ? AND artisan_id = ?',
            [$jobId, $artisanId]
        );
        if (!$exists) {
            $match = Db::one(
                'SELECT overall_match_score FROM artisan_job_matches WHERE job_request_id = ? AND artisan_id = ? ORDER BY id DESC LIMIT 1',
                [$jobId, $artisanId]
            );
            Db::run(
                'INSERT INTO artisan_job_applications (job_request_id, artisan_id, origin, status, match_score_at_time) VALUES (?, ?, "applied", "submitted", ?)',
                [$jobId, $artisanId, $match['overall_match_score'] ?? null]
            );
            $artisanName = (string)Db::scalar('SELECT full_name FROM users WHERE id = ?', [$artisanId]);
            Notifier::send((int)$job['customer_id'], 'application', 'An artisan applied for your job', $artisanName . ' applied to "' . $job['title'] . '"', '/jobs/' . $jobId . '/results');
            Auth::flash('success', 'Applied! The customer will see your interest and can send you a quotation request.');
        } else {
            Auth::flash('info', 'You already applied to this job.');
        }
        Response::redirect('/artisan/jobs');
    }

    public function submitQuotation(array $args): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $applicationId = (int)$args['id'];
        $app = Db::one('SELECT * FROM artisan_job_applications WHERE id = ? AND artisan_id = ?', [$applicationId, $artisanId]);
        if (!$app || $app['status'] !== 'submitted') {
            Response::notFound();
        }

        $labour = (float)($_POST['labour_cost'] ?? 0);
        $material = (float)($_POST['material_cost'] ?? 0);
        $transport = (float)($_POST['transport_cost'] ?? 0);
        $additional = (float)($_POST['additional_charges'] ?? 0);
        $days = (int)($_POST['estimated_days'] ?? 0) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $total = $labour + $material + $transport + $additional;

        if ($total <= 0) {
            Auth::flash('error', 'Please enter at least a labour cost.');
            Response::redirect('/artisan/requests');
        }

        Db::transaction(function () use ($applicationId, $labour, $material, $transport, $additional, $total, $days, $notes) {
            Db::run(
                'INSERT INTO quotations (application_id, labour_cost, material_cost, transport_cost, additional_charges, total_cost, estimated_days, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "sent")',
                [$applicationId, $labour, $material, $transport, $additional, $total, $days, $notes !== '' ? $notes : null]
            );
            $jobId = (int)Db::scalar('SELECT job_request_id FROM artisan_job_applications WHERE id = ?', [$applicationId]);
            Db::run("UPDATE job_requests SET status = 'quoted' WHERE id = ? AND status IN ('matched', 'posted')", [$jobId]);
        });

        $jobRow = Db::one(
            'SELECT jr.id, jr.title, jr.customer_id FROM artisan_job_applications aja JOIN job_requests jr ON jr.id = aja.job_request_id WHERE aja.id = ?',
            [$applicationId]
        );
        if ($jobRow) {
            Notifier::send((int)$jobRow['customer_id'], 'quotation', 'You received a quotation', $jobRow['title'], '/jobs/' . (int)$jobRow['id']);
        }

        Auth::flash('success', 'Your quotation has been sent.');
        Response::redirect('/artisan/requests');
    }

    public function declineRequest(array $args): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $applicationId = (int)$args['id'];
        Db::run(
            'UPDATE artisan_job_applications SET status = "declined" WHERE id = ? AND artisan_id = ? AND status = "submitted"',
            [$applicationId, $artisanId]
        );
        Auth::flash('info', 'Request declined.');
        Response::redirect('/artisan/requests');
    }

    // ------------------------------------------------------------------ job detail hub (customer + hired artisan)
    public function detail(array $args): void
    {
        Auth::requireLogin();
        $jobId = (int)$args['id'];
        $job = Db::one(
            'SELECT jr.*, sc.name AS category_name, a.name AS area_name FROM job_requests jr
             JOIN service_categories sc ON sc.id = jr.category_id JOIN areas a ON a.id = jr.area_id WHERE jr.id = ?',
            [$jobId]
        );
        if (!$job) Response::notFound();

        $work = Db::one(
            'SELECT j.*, u.full_name AS artisan_name, ap.business_name FROM jobs j
             JOIN users u ON u.id = j.artisan_id JOIN artisan_profiles ap ON ap.user_id = j.artisan_id
             WHERE j.job_request_id = ?', [$jobId]
        );
        $isCustomer = Auth::role() === 'customer' && (int)$job['customer_id'] === (int)Auth::id();
        $isArtisan = Auth::role() === 'artisan' && $work && (int)$work['artisan_id'] === (int)Auth::id();
        if (!$isCustomer && !$isArtisan && Auth::role() !== 'admin') {
            Response::notFound();
        }

        $quotations = [];
        if ($isCustomer) {
            $quotations = Db::all(
                'SELECT q.*, aja.artisan_id, u.full_name AS artisan_name, ap.business_name, ap.trust_score, ap.rating_mean, ap.rating_count
                 FROM quotations q
                 JOIN artisan_job_applications aja ON aja.id = q.application_id
                 JOIN users u ON u.id = aja.artisan_id
                 JOIN artisan_profiles ap ON ap.user_id = aja.artisan_id
                 WHERE aja.job_request_id = ? ORDER BY q.total_cost ASC',
                [$jobId]
            );
        }

        $review = Db::one('SELECT * FROM reviews WHERE job_id = ?', [$work['id'] ?? 0]);
        $updates = $work ? Db::all('SELECT * FROM job_updates WHERE job_id = ? ORDER BY created_at', [$work['id']]) : [];
        $photos = Db::all('SELECT id, image_path, caption FROM job_images WHERE job_request_id = ? ORDER BY id', [$jobId]);
        $milestones = $work ? Db::all('SELECT * FROM job_milestones WHERE job_id = ? ORDER BY sort_order, id', [$work['id']]) : [];
        $isEditable = $isCustomer && JobController::isEditable($job);

        View::render('jobs/detail', [
            'job' => $job, 'work' => $work, 'quotations' => $quotations, 'review' => $review,
            'updates' => $updates, 'isCustomer' => $isCustomer, 'isArtisan' => $isArtisan, 'photos' => $photos,
            'milestones' => $milestones, 'isEditable' => $isEditable,
        ]);
    }

    // ------------------------------------------------------------------ customer: accept / decline a quotation
    public function acceptQuotation(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $quotationId = (int)$args['quotation'];
        $job = self::ownedJob($jobId);
        if (in_array($job['status'], ['hired', 'in_progress', 'completed', 'reviewed'], true)) {
            Auth::flash('info', 'This job has already been hired out.');
            Response::redirect('/jobs/' . $jobId);
        }

        $quote = Db::one(
            'SELECT q.*, aja.artisan_id, aja.job_request_id FROM quotations q
             JOIN artisan_job_applications aja ON aja.id = q.application_id
             WHERE q.id = ? AND aja.job_request_id = ? AND q.status = "sent"',
            [$quotationId, $jobId]
        );
        if (!$quote) Response::notFound();

        Db::transaction(function () use ($jobId, $quote, $job) {
            Db::run('UPDATE quotations SET status = "accepted", responded_at = NOW() WHERE id = ?', [$quote['id']]);
            Db::run(
                'UPDATE quotations SET status = "rejected", responded_at = NOW()
                 WHERE application_id IN (SELECT id FROM artisan_job_applications WHERE job_request_id = ?) AND id != ? AND status = "sent"',
                [$jobId, $quote['id']]
            );
            Db::run(
                'UPDATE artisan_job_applications SET status = "accepted" WHERE id = (SELECT application_id FROM quotations WHERE id = ?)',
                [$quote['id']]
            );
            Db::run(
                'UPDATE artisan_job_applications SET status = "declined"
                 WHERE job_request_id = ? AND id != (SELECT application_id FROM quotations WHERE id = ?) AND status IN ("submitted", "shortlisted")',
                [$jobId, $quote['id']]
            );
            Db::run(
                'INSERT INTO jobs (job_request_id, customer_id, artisan_id, quotation_id, agreed_price, scheduled_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, "scheduled")',
                [$jobId, $job['customer_id'], $quote['artisan_id'], $quote['id'], $quote['total_cost'], $job['preferred_date']]
            );
            Db::run("UPDATE job_requests SET status = 'hired' WHERE id = ?", [$jobId]);
        });

        Notifier::send((int)$quote['artisan_id'], 'accepted', 'Your quotation was accepted', $job['title'] ?? null, '/jobs/' . $jobId);
        Auth::flash('success', 'Artisan hired! You can track progress from this page.');
        Response::redirect('/jobs/' . $jobId);
    }

    public function declineQuotation(array $args): void
    {
        Auth::requireRole('customer');
        $jobId = (int)$args['id'];
        $quotationId = (int)$args['quotation'];
        self::ownedJob($jobId);
        Db::run(
            'UPDATE quotations SET status = "rejected", responded_at = NOW()
             WHERE id = ? AND status = "sent" AND application_id IN (SELECT id FROM artisan_job_applications WHERE job_request_id = ?)',
            [$quotationId, $jobId]
        );
        Auth::flash('info', 'Quotation declined.');
        Response::redirect('/jobs/' . $jobId);
    }

    // ------------------------------------------------------------------ tracking
    public function startJob(array $args): void
    {
        $work = self::ownedWork((int)$args['id'], 'artisan');
        if ($work['status'] !== 'scheduled') Response::notFound();
        Db::transaction(function () use ($work, $args) {
            Db::run('UPDATE jobs SET status = "in_progress", started_at = NOW() WHERE id = ?', [$work['id']]);
            Db::run('UPDATE job_requests SET status = "in_progress" WHERE id = ?', [(int)$args['id']]);
            Db::run('INSERT INTO job_updates (job_id, updated_by, status, note) VALUES (?, ?, "in_progress", "Work started")', [$work['id'], Auth::id()]);
        });
        Notifier::send((int)$work['customer_id'], 'status', 'Your job status was updated', 'Work has started', '/jobs/' . $args['id']);
        Auth::flash('success', 'Job marked as started.');
        Response::redirect('/jobs/' . $args['id']);
    }

    public function completeJob(array $args): void
    {
        $work = self::ownedWork((int)$args['id'], 'artisan');
        if ($work['status'] !== 'in_progress') Response::notFound();
        Db::transaction(function () use ($work, $args) {
            Db::run('UPDATE jobs SET status = "completed", completed_at = NOW() WHERE id = ?', [$work['id']]);
            Db::run('UPDATE job_requests SET status = "completed" WHERE id = ?', [(int)$args['id']]);
            Db::run('INSERT INTO job_updates (job_id, updated_by, status, note) VALUES (?, ?, "completed", "Work marked complete, awaiting customer confirmation")', [$work['id'], Auth::id()]);
        });
        Notifier::send((int)$work['customer_id'], 'status', 'Your job status was updated', 'The artisan marked the work complete — please confirm', '/jobs/' . $args['id']);
        Auth::flash('success', 'Job marked as complete. The customer will be asked to confirm.');
        Response::redirect('/jobs/' . $args['id']);
    }

    public function confirmJob(array $args): void
    {
        $work = self::ownedWork((int)$args['id'], 'customer');
        if ($work['status'] !== 'completed') Response::notFound();
        Db::transaction(function () use ($work, $args) {
            Db::run('UPDATE jobs SET status = "confirmed", confirmed_at = NOW() WHERE id = ?', [$work['id']]);
            Db::run('INSERT INTO job_updates (job_id, updated_by, status, note) VALUES (?, ?, "confirmed", "Customer confirmed completion")', [$work['id'], Auth::id()]);
        });
        Notifier::send((int)$work['artisan_id'], 'status', 'Your job status was updated', 'The customer confirmed the work is done', '/jobs/' . $args['id']);
        Auth::flash('success', 'Thanks for confirming! Please leave a review when you\'re ready.');
        Response::redirect('/jobs/' . $args['id']);
    }

    // ------------------------------------------------------------------ review + trust recompute
    public function showReview(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = self::ownedWork($jobId, 'customer');
        if ($work['status'] !== 'confirmed') {
            Auth::flash('info', 'You can leave a review once the job is confirmed complete.');
            Response::redirect('/jobs/' . $jobId);
        }
        if (Db::scalar('SELECT COUNT(*) FROM reviews WHERE job_id = ?', [$work['id']])) {
            Response::redirect('/jobs/' . $jobId);
        }
        View::render('jobs/review', ['job' => Db::one('SELECT * FROM job_requests WHERE id = ?', [$jobId]), 'work' => $work]);
    }

    public function submitReview(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = self::ownedWork($jobId, 'customer');
        if ($work['status'] !== 'confirmed' || Db::scalar('SELECT COUNT(*) FROM reviews WHERE job_id = ?', [$work['id']])) {
            Response::redirect('/jobs/' . $jobId);
        }

        $scores = [];
        foreach (['overall', 'quality', 'professionalism', 'communication', 'punctuality'] as $field) {
            $v = (int)($_POST[$field] ?? 0);
            $scores[$field] = max(1, min(5, $v ?: 5));
        }
        $comment = trim((string)($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 1000) $comment = mb_substr($comment, 0, 1000);

        $artisanId = (int)$work['artisan_id'];
        Db::transaction(function () use ($work, $jobId, $scores, $comment, $artisanId) {
            Db::run(
                'INSERT INTO reviews (job_id, customer_id, artisan_id, overall, quality, professionalism, communication, punctuality, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$work['id'], $work['customer_id'], $artisanId, $scores['overall'], $scores['quality'], $scores['professionalism'], $scores['communication'], $scores['punctuality'], $comment !== '' ? $comment : null]
            );
            Db::run("UPDATE job_requests SET status = 'reviewed' WHERE id = ?", [$jobId]);
            \HireCraft\Support\TrustScorer::recompute($artisanId);
        });

        Notifier::send($artisanId, 'review', 'You received a review', $scores['overall'] . '/5 overall', '/jobs/' . $jobId);
        Auth::flash('success', 'Thanks for your review — it helps other customers and updates this artisan\'s trust score.');
        Response::redirect('/jobs/' . $jobId);
    }

    // Trust-score recompute logic lives in HireCraft\Support\TrustScorer, shared
    // with AdminController's verification review actions (see recomputeTrust call above).

    private static function ownedJob(int $jobId): array
    {
        $job = Db::one('SELECT * FROM job_requests WHERE id = ?', [$jobId]);
        if (!$job || (int)$job['customer_id'] !== (int)Auth::id()) {
            Response::notFound();
        }
        return $job;
    }

    private static function ownedWork(int $jobRequestId, string $role): array
    {
        Auth::requireRole($role);
        $work = Db::one('SELECT * FROM jobs WHERE job_request_id = ?', [$jobRequestId]);
        if (!$work) Response::notFound();
        $ownerId = $role === 'artisan' ? (int)$work['artisan_id'] : (int)$work['customer_id'];
        if ($ownerId !== (int)Auth::id()) Response::notFound();
        return $work;
    }
}
