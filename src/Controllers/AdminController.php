<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Engine\SmartMatchEngine as E;
use HireCraft\Repo\MatchData;
use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/** Admin tools: artisan verification queue, matching-weights editor with what-if, price benchmarks. */
final class AdminController
{
    // ------------------------------------------------------------------ verification queue
    public function artisans(): void
    {
        Auth::requireRole('admin');
        $filter = in_array($_GET['status'] ?? 'pending', ['pending', 'approved', 'rejected', 'suspended', 'all'], true)
            ? ($_GET['status'] ?? 'pending') : 'pending';
        $sql = 'SELECT ap.*, u.full_name, u.email, u.created_at AS joined_at, sc.name AS category_name, a.name AS area_name
                FROM artisan_profiles ap
                JOIN users u ON u.id = ap.user_id
                JOIN service_categories sc ON sc.id = ap.category_id
                JOIN areas a ON a.id = ap.base_area_id';
        $params = [];
        if ($filter !== 'all') {
            $sql .= ' WHERE ap.approval_status = ?';
            $params[] = $filter;
        }
        $sql .= ' ORDER BY ap.created_at DESC';
        $artisans = Db::all($sql, $params);
        View::render('admin/artisans', ['artisans' => $artisans, 'filter' => $filter]);
    }

    // ------------------------------------------------------------------ jobs browser (all job requests, by lifecycle stage)
    private const JOB_TABS = [
        'open' => ['posted', 'matched', 'quoted'],
        'active' => ['hired', 'in_progress'],
        'completed' => ['completed', 'reviewed'],
        'disputed' => ['disputed'],
        'cancelled' => ['cancelled', 'expired'],
        'all' => [],
    ];

    private const JOBS_PER_PAGE = 30;

    public function jobs(): void
    {
        Auth::requireRole('admin');
        $filter = array_key_exists($_GET['status'] ?? '', self::JOB_TABS) ? $_GET['status'] : 'open';
        $statuses = self::JOB_TABS[$filter];

        $where = '';
        $params = [];
        if ($statuses) {
            $where = ' WHERE jr.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            $params = $statuses;
        }

        $total = $statuses
            ? (int)Db::scalar('SELECT COUNT(*) FROM job_requests WHERE status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')', $statuses)
            : (int)Db::scalar('SELECT COUNT(*) FROM job_requests');
        $pages = max(1, (int)ceil($total / self::JOBS_PER_PAGE));
        $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
        $offset = ($page - 1) * self::JOBS_PER_PAGE;

        // LIMIT/OFFSET are validated ints derived above (never raw user input), so
        // it's safe to inline them directly rather than bind them as params.
        $sql = 'SELECT jr.*, sc.name AS category_name, a.name AS area_name, u.full_name AS customer_name,
                       (SELECT COUNT(*) FROM artisan_job_matches m WHERE m.job_request_id = jr.id) AS match_count
                FROM job_requests jr
                JOIN service_categories sc ON sc.id = jr.category_id
                JOIN areas a ON a.id = jr.area_id
                JOIN users u ON u.id = jr.customer_id'
            . $where . ' ORDER BY jr.created_at DESC LIMIT ' . self::JOBS_PER_PAGE . ' OFFSET ' . $offset;
        $jobs = Db::all($sql, $params);

        $counts = [];
        foreach (self::JOB_TABS as $key => $statusList) {
            $counts[$key] = $statusList
                ? (int)Db::scalar('SELECT COUNT(*) FROM job_requests WHERE status IN (' . implode(',', array_fill(0, count($statusList), '?')) . ')', $statusList)
                : (int)Db::scalar('SELECT COUNT(*) FROM job_requests');
        }

        View::render('admin/jobs', [
            'jobs' => $jobs, 'filter' => $filter, 'counts' => $counts,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]);
    }

    public function approveArtisan(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        Db::run('UPDATE artisan_profiles SET approval_status = "approved" WHERE user_id = ?', [$id]);
        self::logAction('approve_artisan', 'artisan_profiles', $id);
        Auth::flash('success', 'Artisan approved. They will now appear in customer matches.');
        Response::redirect('/admin/artisans');
    }

    public function rejectArtisan(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        $reason = trim((string)($_POST['reason'] ?? ''));
        Db::run('UPDATE artisan_profiles SET approval_status = "rejected" WHERE user_id = ?', [$id]);
        self::logAction('reject_artisan', 'artisan_profiles', $id, ['reason' => $reason !== '' ? $reason : null]);
        Auth::flash('info', 'Artisan profile rejected.');
        Response::redirect('/admin/artisans');
    }

    public function suspendArtisan(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        Db::run('UPDATE artisan_profiles SET approval_status = "suspended" WHERE user_id = ?', [$id]);
        self::logAction('suspend_artisan', 'artisan_profiles', $id);
        Auth::flash('info', 'Artisan suspended and hidden from matches.');
        Response::redirect('/admin/artisans');
    }

    // ------------------------------------------------------------------ matching weights, with live what-if
    public function weights(): void
    {
        Auth::requireRole('admin');
        $active = MatchData::activeConfig();
        $activeWeights = $active['config']['weights'] ?? E::DEFAULT_CONFIG['weights'];

        $candidate = $activeWeights;
        $preview = null;
        if (isset($_GET['preview'])) {
            foreach (E::COMPONENTS as $c) {
                if (isset($_GET['w_' . $c])) $candidate[$c] = max(0, (float)$_GET['w_' . $c]);
            }
            $preview = self::buildPreview($active, $candidate);
        }

        $history = Db::all('SELECT config_version, is_active, note, created_at FROM matching_config ORDER BY config_version DESC LIMIT 10');

        View::render('admin/weights', [
            'activeVersion' => $active['version'], 'activeWeights' => $activeWeights,
            'candidate' => $candidate, 'preview' => $preview, 'history' => $history,
            'components' => E::COMPONENTS,
        ]);
    }

    public function saveWeights(): void
    {
        Auth::requireRole('admin');
        $weights = [];
        foreach (E::COMPONENTS as $c) {
            $weights[$c] = max(0, (float)($_POST['w_' . $c] ?? 0));
        }
        if (array_sum($weights) <= 0) {
            Auth::flash('error', 'At least one weight must be greater than zero.');
            Response::redirect('/admin/weights');
        }
        $note = trim((string)($_POST['note'] ?? ''));

        $nextVersion = (int)(Db::scalar('SELECT MAX(config_version) FROM matching_config') ?? 0) + 1;
        $config = E::DEFAULT_CONFIG;
        unset($config['weights']);

        Db::transaction(function () use ($weights, $config, $nextVersion, $note) {
            Db::run('UPDATE matching_config SET is_active = 0 WHERE is_active = 1');
            Db::run(
                'INSERT INTO matching_config (config_version, weights, parameters, is_active, changed_by, note) VALUES (?, ?, ?, 1, ?, ?)',
                [$nextVersion, json_encode($weights), json_encode($config), Auth::id(), $note !== '' ? $note : 'Weights updated by admin']
            );
        });
        self::logAction('update_weights', 'matching_config', $nextVersion, $weights);

        Auth::flash('success', "Matching configuration v$nextVersion is now live. New job posts and re-matches will use it.");
        Response::redirect('/admin/weights');
    }

    /** Re-ranks one sample job under the active vs candidate weights, without touching the DB. */
    private static function buildPreview(array $active, array $candidateWeights): array
    {
        $job = Db::one(
            "SELECT jr.*, sc.slug AS category_slug FROM job_requests jr JOIN service_categories sc ON sc.id = jr.category_id
             WHERE jr.status IN ('matched', 'quoted', 'hired') AND jr.is_multi_trade = 0 ORDER BY jr.created_at DESC LIMIT 1"
        );
        if (!$job) {
            return ['job' => null];
        }
        $skillIds = array_column(Db::all('SELECT skill_id FROM job_required_skills WHERE job_request_id = ?', [$job['id']]), 'skill_id');
        $engineJob = [
            'title' => $job['title'], 'description' => $job['description'], 'categoryId' => $job['category_slug'],
            'skillIds' => array_map('intval', $skillIds), 'areaId' => (int)$job['area_id'], 'date' => $job['preferred_date'],
            'urgency' => $job['urgency'], 'budgetType' => $job['budget_type'],
            'budgetMin' => $job['budget_min'] !== null ? (float)$job['budget_min'] : null,
            'budgetMax' => $job['budget_max'] !== null ? (float)$job['budget_max'] : null,
            'negotiable' => (bool)$job['is_negotiable'],
        ];
        $ref = MatchData::ref();
        $artisans = MatchData::artisans();
        $benchmarks = MatchData::benchmarks();

        $activeConfig = $active['config'];
        $activeConfig['weights'] = $active['config']['weights'] ?? E::DEFAULT_CONFIG['weights'];
        $candidateConfig = $active['config'];
        $candidateConfig['weights'] = $candidateWeights;

        $before = E::match($engineJob, $ref, $artisans, ['config' => $activeConfig, 'benchmarks' => $benchmarks]);
        $after = E::match($engineJob, $ref, $artisans, ['config' => $candidateConfig, 'benchmarks' => $benchmarks]);

        $top = fn($r) => array_map(fn($m) => ['id' => $m['artisan']['id'], 'name' => $m['artisan']['name'], 'score' => $m['score'], 'level' => $m['level']], array_slice($r['matches'], 0, 5));

        return ['job' => $job, 'before' => $top($before), 'after' => $top($after)];
    }

    // ------------------------------------------------------------------ price benchmarks
    public function benchmarks(): void
    {
        Auth::requireRole('admin');
        $rows = Db::all(
            'SELECT pb.*, sc.name AS category_name FROM price_benchmarks pb
             JOIN service_categories sc ON sc.id = pb.category_id
             ORDER BY sc.name, FIELD(pb.complexity, "simple", "medium", "complex")'
        );
        View::render('admin/benchmarks', ['rows' => $rows]);
    }

    public function updateBenchmarks(): void
    {
        Auth::requireRole('admin');
        $ids = array_map('intval', (array)($_POST['id'] ?? []));
        Db::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $n = max(0, (int)($_POST['observations_' . $id] ?? 0));
                $p25 = max(0, (float)($_POST['p25_' . $id] ?? 0));
                $median = max(0, (float)($_POST['median_' . $id] ?? 0));
                $p75 = max(0, (float)($_POST['p75_' . $id] ?? 0));
                if ($p25 > $median) $median = $p25;
                if ($median > $p75) $p75 = $median;
                Db::run(
                    'UPDATE price_benchmarks SET observations = ?, p25 = ?, median = ?, p75 = ?, source = "platform" WHERE id = ?',
                    [$n, $p25, $median, $p75, $id]
                );
            }
        });
        self::logAction('update_benchmarks', 'price_benchmarks', null, ['count' => count($ids)]);
        Auth::flash('success', 'Price benchmarks updated.');
        Response::redirect('/admin/benchmarks');
    }

    // ------------------------------------------------------------------ evaluation feedback (Chapter 4 data)
    /**
     * Summary + raw table of the "was this helpful?" feedback customers
     * leave on the SmartMatch results page (recommendation_feedback) — the
     * raw material for Chapter 4's user-evaluation section (usefulness,
     * explanation understanding).
     */
    public function evaluation(): void
    {
        Auth::requireRole('admin');
        $rows = Db::all(
            'SELECT rf.*, jr.title AS job_title, jr.category_id, sc.name AS category_name, u.full_name AS customer_name,
                    au.full_name AS chosen_artisan_name, ap.business_name AS chosen_business_name
             FROM recommendation_feedback rf
             JOIN job_requests jr ON jr.id = rf.job_request_id
             JOIN service_categories sc ON sc.id = jr.category_id
             JOIN users u ON u.id = rf.customer_id
             LEFT JOIN users au ON au.id = rf.chosen_artisan_id
             LEFT JOIN artisan_profiles ap ON ap.user_id = rf.chosen_artisan_id
             ORDER BY rf.created_at DESC'
        );

        $total = count($rows);
        $helpfulCounts = ['yes' => 0, 'partially' => 0, 'no' => 0];
        $understoodSum = 0;
        $understoodN = 0;
        $reasonCounts = [];
        $rankSum = 0;
        $rankN = 0;
        $topPickChosen = 0;
        foreach ($rows as $r) {
            $helpfulCounts[$r['helpful']] = ($helpfulCounts[$r['helpful']] ?? 0) + 1;
            if ($r['understood_explanation'] !== null) {
                $understoodSum += (int)$r['understood_explanation'];
                $understoodN++;
            }
            if ($r['reason_chosen']) {
                $reasonCounts[$r['reason_chosen']] = ($reasonCounts[$r['reason_chosen']] ?? 0) + 1;
            }
            if ($r['chosen_rank'] !== null) {
                $rankSum += (int)$r['chosen_rank'];
                $rankN++;
                if ((int)$r['chosen_rank'] === 1) $topPickChosen++;
            }
        }

        $summary = [
            'total' => $total,
            'helpfulCounts' => $helpfulCounts,
            'helpfulPct' => $total > 0 ? round((($helpfulCounts['yes'] + $helpfulCounts['partially']) / $total) * 100) : null,
            'avgUnderstood' => $understoodN > 0 ? round($understoodSum / $understoodN, 2) : null,
            'reasonCounts' => $reasonCounts,
            'avgChosenRank' => $rankN > 0 ? round($rankSum / $rankN, 2) : null,
            'topPickChosenPct' => $rankN > 0 ? round(($topPickChosen / $rankN) * 100) : null,
        ];

        View::render('admin/evaluation', ['rows' => $rows, 'summary' => $summary]);
    }

    /** CSV export of every raw feedback row, for Chapter 4's evaluation analysis (e.g. in a spreadsheet or SPSS). */
    public function exportEvaluation(): void
    {
        Auth::requireRole('admin');
        $rows = Db::all(
            'SELECT rf.id, rf.job_request_id, jr.title AS job_title, sc.name AS category_name, rf.customer_id, u.full_name AS customer_name,
                    rf.helpful, rf.understood_explanation, rf.reason_chosen, rf.chosen_artisan_id, au.full_name AS chosen_artisan_name,
                    rf.chosen_rank, rf.comment, rf.created_at
             FROM recommendation_feedback rf
             JOIN job_requests jr ON jr.id = rf.job_request_id
             JOIN service_categories sc ON sc.id = jr.category_id
             JOIN users u ON u.id = rf.customer_id
             LEFT JOIN users au ON au.id = rf.chosen_artisan_id
             ORDER BY rf.created_at'
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hirecraft-evaluation-feedback-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        // PHP 8.4 deprecates the implicit default for fputcsv()'s $escape
        // parameter (would otherwise print a warning into the CSV body
        // itself, corrupting the file), so it's passed explicitly here.
        fputcsv($out, [
            'feedback_id', 'job_request_id', 'job_title', 'trade', 'customer_id', 'customer_name',
            'helpful', 'understood_explanation_1to5', 'reason_chosen', 'chosen_artisan_id', 'chosen_artisan_name',
            'chosen_rank', 'comment', 'submitted_at',
        ], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['job_request_id'], $r['job_title'], $r['category_name'], $r['customer_id'], $r['customer_name'],
                $r['helpful'], $r['understood_explanation'], $r['reason_chosen'], $r['chosen_artisan_id'], $r['chosen_artisan_name'],
                $r['chosen_rank'], $r['comment'], $r['created_at'],
            ], ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------ safety: reports queue
    public function reports(): void
    {
        Auth::requireRole('admin');
        $filter = in_array($_GET['status'] ?? 'open', ['open', 'reviewed', 'dismissed', 'action_taken', 'all'], true)
            ? ($_GET['status'] ?? 'open') : 'open';
        $sql = 'SELECT ur.*, reporter.full_name AS reporter_name, reported.full_name AS reported_name, reported.role AS reported_role
                FROM user_reports ur
                JOIN users reporter ON reporter.id = ur.reporter_id
                LEFT JOIN users reported ON reported.id = ur.reported_user_id';
        $params = [];
        if ($filter !== 'all') {
            $sql .= ' WHERE ur.status = ?';
            $params[] = $filter;
        }
        $sql .= ' ORDER BY ur.created_at DESC';
        $reports = Db::all($sql, $params);
        View::render('admin/reports', ['reports' => $reports, 'filter' => $filter]);
    }

    public function actOnReport(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        $action = (string)($args['action'] ?? '');
        $status = match ($action) {
            'dismiss' => 'dismissed',
            'resolve' => 'reviewed',
            'action' => 'action_taken',
            default => null,
        };
        if (!$status) Response::notFound();
        $note = trim((string)($_POST['admin_note'] ?? ''));
        Db::run(
            'UPDATE user_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_note = ? WHERE id = ?',
            [$status, Auth::id(), $note !== '' ? $note : null, $id]
        );
        self::logAction('report_' . $action, 'user_report', $id);
        Auth::flash('success', 'Report updated.');
        Response::redirect('/admin/reports');
    }

    // ------------------------------------------------------------------ safety: disputes queue
    public function disputes(): void
    {
        Auth::requireRole('admin');
        $filter = in_array($_GET['status'] ?? 'open', ['open', 'under_review', 'waiting_for_response', 'resolved', 'closed', 'all'], true)
            ? ($_GET['status'] ?? 'open') : 'open';
        $sql = 'SELECT d.*, jr.title AS job_title, opener.full_name AS opened_by_name, against.full_name AS against_name
                FROM disputes d
                JOIN jobs j ON j.id = d.job_id
                JOIN job_requests jr ON jr.id = j.job_request_id
                JOIN users opener ON opener.id = d.opened_by
                LEFT JOIN users against ON against.id = d.against_user_id';
        $params = [];
        if ($filter !== 'all') {
            $sql .= ' WHERE d.status = ?';
            $params[] = $filter;
        }
        $sql .= ' ORDER BY d.created_at DESC';
        $disputes = Db::all($sql, $params);
        View::render('admin/disputes', ['disputes' => $disputes, 'filter' => $filter]);
    }

    public function resolveDispute(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        $dispute = Db::one('SELECT * FROM disputes WHERE id = ?', [$id]);
        if (!$dispute) Response::notFound();

        $newStatus = (string)($_POST['status'] ?? '');
        if (!in_array($newStatus, ['under_review', 'resolved', 'closed'], true)) {
            Response::notFound();
        }
        $resolution = trim((string)($_POST['resolution'] ?? ''));
        if (mb_strlen($resolution) > 2000) $resolution = mb_substr($resolution, 0, 2000);

        $jobRequestId = (int)Db::scalar('SELECT job_request_id FROM jobs WHERE id = ?', [$dispute['job_id']]);

        Db::transaction(function () use ($id, $newStatus, $resolution, $jobRequestId) {
            if ($newStatus === 'resolved') {
                Db::run(
                    'UPDATE disputes SET status = ?, resolution = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
                    [$newStatus, $resolution !== '' ? $resolution : null, Auth::id(), $id]
                );
                if ($jobRequestId) {
                    Db::run("UPDATE job_requests SET status = 'in_progress' WHERE id = ? AND status = 'disputed'", [$jobRequestId]);
                }
            } else {
                Db::run('UPDATE disputes SET status = ? WHERE id = ?', [$newStatus, $id]);
            }
        });
        self::logAction('dispute_' . $newStatus, 'dispute', $id);

        foreach (array_filter([(int)$dispute['opened_by'], (int)$dispute['against_user_id']]) as $party) {
            \HireCraft\Support\Notifier::send($party, 'dispute', 'Your dispute status was updated', ucwords(str_replace('_', ' ', $newStatus)), '/jobs/' . $jobRequestId . '/dispute');
        }

        Auth::flash('success', 'Dispute updated.');
        Response::redirect('/admin/disputes');
    }

    // ------------------------------------------------------------------ skill/identity/reference verification queue
    public function verifications(): void
    {
        Auth::requireRole('admin');
        $filter = in_array($_GET['status'] ?? 'pending', ['pending', 'approved', 'rejected', 'all'], true)
            ? ($_GET['status'] ?? 'pending') : 'pending';
        $type = in_array($_GET['type'] ?? '', ['identity', 'skill', 'reference'], true) ? $_GET['type'] : '';
        $sql = 'SELECT v.*, u.full_name AS artisan_name, ap.business_name
                FROM verifications v
                JOIN users u ON u.id = v.artisan_id
                JOIN artisan_profiles ap ON ap.user_id = v.artisan_id
                WHERE 1=1';
        $params = [];
        if ($filter !== 'all') {
            $sql .= ' AND v.status = ?';
            $params[] = $filter;
        }
        if ($type !== '') {
            $sql .= ' AND v.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY v.submitted_at DESC';
        $verifications = Db::all($sql, $params);
        View::render('admin/verifications', ['verifications' => $verifications, 'filter' => $filter, 'typeFilter' => $type]);
    }

    public function reviewVerification(array $args): void
    {
        Auth::requireRole('admin');
        $id = (int)$args['id'];
        $action = (string)($args['action'] ?? '');
        if (!in_array($action, ['approve', 'reject'], true)) Response::notFound();

        $v = Db::one('SELECT * FROM verifications WHERE id = ?', [$id]);
        if (!$v) Response::notFound();

        $reason = trim((string)($_POST['reject_reason'] ?? ''));
        if ($action === 'approve') {
            Db::run(
                'UPDATE verifications SET status = "approved", reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL WHERE id = ?',
                [Auth::id(), $id]
            );
        } else {
            Db::run(
                'UPDATE verifications SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(), reject_reason = ? WHERE id = ?',
                [Auth::id(), $reason !== '' ? $reason : null, $id]
            );
        }
        \HireCraft\Support\TrustScorer::recompute((int)$v['artisan_id']);
        self::logAction('verification_' . $action, 'verification', $id, ['type' => $v['type']]);

        \HireCraft\Support\Notifier::send(
            (int)$v['artisan_id'],
            'verification',
            $action === 'approve' ? ucfirst($v['type']) . ' verification approved' : ucfirst($v['type']) . ' verification rejected',
            $action === 'reject' && $reason !== '' ? $reason : null,
            '/verifications'
        );

        Auth::flash('success', 'Verification ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
        Response::redirect('/admin/verifications');
    }

    private static function logAction(string $action, string $targetType, ?int $targetId, ?array $details = null): void
    {
        Db::run(
            'INSERT INTO admin_actions (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
            [Auth::id(), $action, $targetType, $targetId, $details !== null ? json_encode($details) : null]
        );
    }
}
