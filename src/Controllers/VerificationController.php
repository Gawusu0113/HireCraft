<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Support\Auth;
use HireCraft\Support\Db;
use HireCraft\Support\Notifier;
use HireCraft\Support\Response;
use HireCraft\Support\Uploads;
use HireCraft\Support\View;

/**
 * Lets an artisan submit identity, skill, and reference verification
 * documents (certificates, ID, reference letters) beyond the basic
 * phone/email checks done at signup, and see their submission history.
 * Admin review lives in AdminController::verifications()/reviewVerification().
 */
final class VerificationController
{
    private const TYPES = ['identity', 'skill', 'reference'];
    private const TYPE_LABELS = [
        'identity' => 'Identity (Ghana Card, passport, driver\'s licence)',
        'skill' => 'Skill certificate (trade test, apprenticeship, NVTI, etc.)',
        'reference' => 'Reference letter (a past client or employer)',
    ];

    public function index(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $profile = Db::one('SELECT verification_level, trust_score FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        if (!$profile) {
            Response::redirect('/profile/setup');
        }
        $submissions = Db::all(
            'SELECT * FROM verifications WHERE artisan_id = ? ORDER BY submitted_at DESC',
            [$artisanId]
        );
        // One row per type showing its latest approved/pending state, for a quick status summary.
        $latestByType = [];
        foreach ($submissions as $s) {
            if (!isset($latestByType[$s['type']])) {
                $latestByType[$s['type']] = $s;
            }
        }
        View::render('artisan/verifications', [
            'profile' => $profile,
            'submissions' => $submissions,
            'latestByType' => $latestByType,
            'types' => self::TYPES,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function submit(): void
    {
        Auth::requireRole('artisan');
        $artisanId = (int)Auth::id();
        $profile = Db::one('SELECT user_id FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        if (!$profile) {
            Response::redirect('/profile/setup');
        }

        $type = (string)($_POST['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            Auth::flash('error', 'Please choose a valid verification type.');
            Response::redirect('/verifications');
        }
        // Don't allow piling up duplicate pending submissions of the same type.
        $alreadyPending = (bool)Db::scalar(
            "SELECT COUNT(*) FROM verifications WHERE artisan_id = ? AND type = ? AND status = 'pending'",
            [$artisanId, $type]
        );
        if ($alreadyPending) {
            Auth::flash('info', 'You already have a ' . $type . ' verification pending review.');
            Response::redirect('/verifications');
        }

        $documentType = trim((string)($_POST['document_type'] ?? ''));
        if ($documentType === '' || mb_strlen($documentType) > 60) {
            Auth::flash('error', 'Please describe the document you are submitting (e.g. "Ghana Card", "NVTI certificate").');
            Response::redirect('/verifications');
        }

        $upload = Uploads::saveOne($_FILES['document'] ?? null, 'verifications/' . $artisanId);
        if ($upload['error'] !== null) {
            Auth::flash('error', $upload['error']);
            Response::redirect('/verifications');
        }

        Db::run(
            'INSERT INTO verifications (artisan_id, type, document_type, document_path, status) VALUES (?, ?, ?, ?, "pending")',
            [$artisanId, $type, $documentType, $upload['path']]
        );
        Auth::flash('success', 'Submitted for review. We\'ll notify you once an admin checks it.');
        Response::redirect('/verifications');
    }
}
