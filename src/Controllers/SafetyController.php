<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/**
 * User-submitted safety reports (fraud, fake profiles, harassment, suspicious
 * behavior, inappropriate messages, fake reviews) and job disputes (FR:
 * sections 40-41). Both are reviewed by an administrator.
 */
final class SafetyController
{
    private const REPORT_REASONS = ['fraud', 'fake_profile', 'harassment', 'suspicious_behavior', 'inappropriate_messages', 'fake_review'];
    private const DISPUTE_TYPES = ['poor_workmanship', 'job_not_completed', 'artisan_no_show', 'pricing_dispute', 'suspicious_behavior', 'other'];

    // ------------------------------------------------------------------ reports
    public function showReportForm(): void
    {
        Auth::requireLogin();
        $userId = isset($_GET['user']) ? (int)$_GET['user'] : null;
        $reportedUser = $userId ? Db::one('SELECT id, full_name, role FROM users WHERE id = ?', [$userId]) : null;
        if ($userId && !$reportedUser) Response::notFound();
        View::render('safety/report', ['reportedUser' => $reportedUser]);
    }

    public function createReport(): void
    {
        Auth::requireLogin();
        $reportedUserId = !empty($_POST['reported_user_id']) ? (int)$_POST['reported_user_id'] : null;
        $reason = (string)($_POST['reason'] ?? '');
        $details = trim((string)($_POST['details'] ?? ''));

        if (!in_array($reason, self::REPORT_REASONS, true)) {
            Auth::flash('error', 'Please choose a valid reason for your report.');
            Response::redirect('/report' . ($reportedUserId ? '?user=' . $reportedUserId : ''));
        }
        if ($reportedUserId && $reportedUserId === (int)Auth::id()) {
            Auth::flash('error', 'You cannot report yourself.');
            Response::redirect('/');
        }
        if ($reportedUserId && !Db::scalar('SELECT 1 FROM users WHERE id = ?', [$reportedUserId])) {
            Response::notFound();
        }
        if (mb_strlen($details) > 1000) $details = mb_substr($details, 0, 1000);

        Db::run(
            'INSERT INTO user_reports (reporter_id, reported_user_id, reason, details) VALUES (?, ?, ?, ?)',
            [(int)Auth::id(), $reportedUserId, $reason, $details !== '' ? $details : null]
        );
        Auth::flash('success', 'Thanks — our team will review this report.');
        Response::redirect($reportedUserId ? '/artisans/' . $reportedUserId : '/');
    }

    // ------------------------------------------------------------------ disputes
    public function showDisputeForm(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = $this->participantWork($jobId);
        View::render('safety/dispute_new', ['job' => $work, 'jobId' => $jobId]);
    }

    public function openDispute(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = $this->participantWork($jobId);
        $issueType = (string)($_POST['issue_type'] ?? '');
        $description = trim((string)($_POST['description'] ?? ''));

        if (!in_array($issueType, self::DISPUTE_TYPES, true) || mb_strlen($description) < 10) {
            Auth::flash('error', 'Please choose an issue type and describe what happened (at least 10 characters).');
            Response::redirect('/jobs/' . $jobId . '/dispute/new');
        }
        if (mb_strlen($description) > 2000) $description = mb_substr($description, 0, 2000);

        $me = (int)Auth::id();
        $against = $me === (int)$work['customer_id'] ? (int)$work['artisan_id'] : (int)$work['customer_id'];

        Db::transaction(function () use ($jobId, $work, $me, $against, $issueType, $description) {
            Db::run(
                'INSERT INTO disputes (job_id, opened_by, against_user_id, issue_type, description) VALUES (?, ?, ?, ?, ?)',
                [$work['id'], $me, $against, $issueType, $description]
            );
            Db::run("UPDATE job_requests SET status = 'disputed' WHERE id = ?", [$jobId]);
        });

        Notifier::send($against, 'dispute', 'A dispute was opened on your job', ucwords(str_replace('_', ' ', $issueType)), '/jobs/' . $jobId . '/dispute');
        Auth::flash('success', 'Dispute opened. An administrator will review it.');
        Response::redirect('/jobs/' . $jobId . '/dispute');
    }

    public function showDispute(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = $this->participantWork($jobId);
        $dispute = Db::one('SELECT * FROM disputes WHERE job_id = ? ORDER BY id DESC LIMIT 1', [$work['id']]);
        if (!$dispute) {
            Response::redirect('/jobs/' . $jobId . '/dispute/new');
        }
        $evidence = Db::all(
            'SELECT de.*, u.full_name FROM dispute_evidence de JOIN users u ON u.id = de.submitted_by WHERE de.dispute_id = ? ORDER BY de.created_at',
            [$dispute['id']]
        );
        View::render('safety/dispute_detail', ['dispute' => $dispute, 'evidence' => $evidence, 'jobId' => $jobId, 'me' => (int)Auth::id()]);
    }

    public function respondToDispute(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = $this->participantWork($jobId);
        $dispute = Db::one('SELECT * FROM disputes WHERE job_id = ? ORDER BY id DESC LIMIT 1', [$work['id']]);
        if (!$dispute || in_array($dispute['status'], ['resolved', 'closed'], true)) {
            Response::redirect('/jobs/' . $jobId . '/dispute');
        }
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note === '') {
            Auth::flash('error', 'Please write a response.');
            Response::redirect('/jobs/' . $jobId . '/dispute');
        }
        if (mb_strlen($note) > 1000) $note = mb_substr($note, 0, 1000);

        $me = (int)Auth::id();
        Db::transaction(function () use ($dispute, $me, $note) {
            Db::run('INSERT INTO dispute_evidence (dispute_id, submitted_by, note) VALUES (?, ?, ?)', [$dispute['id'], $me, $note]);
            if ($dispute['status'] === 'open') {
                Db::run('UPDATE disputes SET status = "waiting_for_response" WHERE id = ?', [$dispute['id']]);
            }
        });
        $other = $me === (int)$dispute['opened_by'] ? (int)$dispute['against_user_id'] : (int)$dispute['opened_by'];
        if ($other) {
            Notifier::send($other, 'dispute', 'New response on your dispute', mb_strimwidth($note, 0, 100, '…'), '/jobs/' . $jobId . '/dispute');
        }
        Auth::flash('success', 'Your response has been added.');
        Response::redirect('/jobs/' . $jobId . '/dispute');
    }

    /** Confirms the current user is the customer or the hired artisan on a job (or admin), and returns the `jobs` row. */
    private function participantWork(int $jobId): array
    {
        Auth::requireLogin();
        $work = Db::one('SELECT * FROM jobs WHERE job_request_id = ?', [$jobId]);
        if (!$work) Response::notFound();
        $me = (int)Auth::id();
        $isParticipant = $me === (int)$work['customer_id'] || $me === (int)$work['artisan_id'];
        if (!$isParticipant && Auth::role() !== 'admin') Response::notFound();
        return $work;
    }
}
