<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;

/** Milestones for large/multi-trade jobs (FR: section 32) — added by either party, tracked to completion. */
final class MilestoneController
{
    public function add(array $args): void
    {
        $jobId = (int)$args['id'];
        $work = $this->participantWork($jobId);

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $cost = $_POST['estimated_cost'] ?? '';
        $date = trim((string)($_POST['estimated_date'] ?? ''));

        if ($title === '' || mb_strlen($title) > 150) {
            Auth::flash('error', 'Please give the milestone a short title.');
            Response::redirect('/jobs/' . $jobId);
        }
        $estimatedCost = $cost !== '' ? max(0, (float)$cost) : null;
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';

        $nextOrder = (int)Db::scalar('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM job_milestones WHERE job_id = ?', [$work['id']]);
        Db::run(
            'INSERT INTO job_milestones (job_id, title, description, estimated_cost, estimated_date, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$work['id'], $title, $description !== '' ? $description : null, $estimatedCost, $date !== '' ? $date : null, $nextOrder]
        );

        $other = (int)Auth::id() === (int)$work['customer_id'] ? (int)$work['artisan_id'] : (int)$work['customer_id'];
        Notifier::send($other, 'status', 'A milestone was added to your job', $title, '/jobs/' . $jobId);

        Auth::flash('success', 'Milestone added.');
        Response::redirect('/jobs/' . $jobId);
    }

    public function updateStatus(array $args): void
    {
        $milestoneId = (int)$args['id'];
        $milestone = Db::one('SELECT * FROM job_milestones WHERE id = ?', [$milestoneId]);
        if (!$milestone) Response::notFound();

        $work = Db::one('SELECT * FROM jobs WHERE id = ?', [$milestone['job_id']]);
        $me = (int)Auth::id();
        if (!$work || ($me !== (int)$work['customer_id'] && $me !== (int)$work['artisan_id'])) {
            Response::notFound();
        }

        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            Response::notFound();
        }
        Db::run('UPDATE job_milestones SET status = ? WHERE id = ?', [$status, $milestoneId]);

        $other = $me === (int)$work['customer_id'] ? (int)$work['artisan_id'] : (int)$work['customer_id'];
        Notifier::send($other, 'status', 'A milestone was updated', $milestone['title'] . ' — ' . ucwords(str_replace('_', ' ', $status)), '/jobs/' . $work['job_request_id']);

        Auth::flash('success', 'Milestone updated.');
        Response::redirect('/jobs/' . $work['job_request_id']);
    }

    private function participantWork(int $jobId): array
    {
        Auth::requireLogin();
        $work = Db::one('SELECT * FROM jobs WHERE job_request_id = ?', [$jobId]);
        if (!$work) Response::notFound();
        $me = (int)Auth::id();
        if ($me !== (int)$work['customer_id'] && $me !== (int)$work['artisan_id']) {
            Response::notFound();
        }
        return $work;
    }
}
