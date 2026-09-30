<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/**
 * Secure, job-specific messaging between a customer and an artisan (FR:
 * section 37). A "thread" is scoped to (job_request_id, the other party),
 * so a job with several interested artisans gets one thread per artisan
 * rather than one mixed conversation. Only the two participants — verified
 * against the job, and against an application/quotation/hire for that
 * artisan on that job — may read or post into a thread.
 */
final class MessageController
{
    public function thread(array $args): void
    {
        Auth::requireLogin();
        $jobId = (int)$args['id'];
        $otherId = (int)$args['other'];
        $me = (int)Auth::id();

        $job = Db::one(
            'SELECT jr.*, a.name AS area_name FROM job_requests jr JOIN areas a ON a.id = jr.area_id WHERE jr.id = ?',
            [$jobId]
        );
        if (!$job) Response::notFound();

        [$ok, $customerId, $artisanId] = $this->authorize($job, $me, $otherId);
        if (!$ok) Response::notFound();

        $other = Db::one('SELECT id, full_name, role FROM users WHERE id = ?', [$otherId]);
        if (!$other) Response::notFound();

        $messages = Db::all(
            'SELECT * FROM messages WHERE job_request_id = ?
             AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
             ORDER BY created_at ASC',
            [$jobId, $me, $otherId, $otherId, $me]
        );

        Db::run(
            'UPDATE messages SET read_at = NOW() WHERE job_request_id = ? AND sender_id = ? AND recipient_id = ? AND read_at IS NULL',
            [$jobId, $otherId, $me]
        );

        View::render('messages/thread', [
            'job' => $job, 'other' => $other, 'messages' => $messages, 'me' => $me,
        ]);
    }

    public function send(array $args): void
    {
        Auth::requireLogin();
        $jobId = (int)$args['id'];
        $otherId = (int)$args['other'];
        $me = (int)Auth::id();

        $job = Db::one('SELECT * FROM job_requests WHERE id = ?', [$jobId]);
        if (!$job) Response::notFound();

        [$ok] = $this->authorize($job, $me, $otherId);
        if (!$ok) Response::notFound();

        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            Auth::flash('error', 'Message cannot be empty.');
            Response::redirect('/jobs/' . $jobId . '/messages/' . $otherId);
        }
        if (mb_strlen($body) > 1000) {
            $body = mb_substr($body, 0, 1000);
        }

        Db::run(
            'INSERT INTO messages (job_request_id, sender_id, recipient_id, body) VALUES (?, ?, ?, ?)',
            [$jobId, $me, $otherId, $body]
        );

        $sender = Db::one('SELECT full_name FROM users WHERE id = ?', [$me]);
        Notifier::send(
            $otherId, 'message', 'New message from ' . ($sender['full_name'] ?? 'a user'),
            mb_strimwidth($body, 0, 100, '…'), '/jobs/' . $jobId . '/messages/' . $me
        );

        Response::redirect('/jobs/' . $jobId . '/messages/' . $otherId);
    }

    /**
     * Confirms $me and $otherId are the two legitimate participants of a
     * conversation about $job: one must be the customer who posted it, the
     * other an artisan who has an application, quotation or hire on it.
     * @return array{0: bool, 1: int|null, 2: int|null}
     */
    private function authorize(array $job, int $me, int $otherId): array
    {
        $jobId = (int)$job['id'];
        $customerId = (int)$job['customer_id'];

        if ($me === $customerId) {
            $artisanId = $otherId;
        } elseif ($otherId === $customerId) {
            $artisanId = $me;
        } else {
            return [false, null, null];
        }
        if ($artisanId === $customerId) {
            return [false, null, null];
        }
        if (Auth::role() === 'admin') {
            return [true, $customerId, $artisanId];
        }
        if ($me !== $customerId && $me !== $artisanId) {
            return [false, null, null];
        }

        $hasRelationship = (bool)Db::scalar(
            'SELECT 1 FROM artisan_job_applications WHERE job_request_id = ? AND artisan_id = ? LIMIT 1',
            [$jobId, $artisanId]
        );
        if (!$hasRelationship) {
            $hasRelationship = (bool)Db::scalar(
                'SELECT 1 FROM jobs WHERE job_request_id = ? AND artisan_id = ? LIMIT 1',
                [$jobId, $artisanId]
            );
        }
        if (!$hasRelationship) {
            return [false, null, null];
        }

        return [true, $customerId, $artisanId];
    }
}
