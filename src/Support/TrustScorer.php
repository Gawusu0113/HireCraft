<?php
declare(strict_types=1);

namespace HireCraft\Support;

use HireCraft\Engine\SmartMatchEngine as E;

/**
 * Recomputes and persists an artisan's cached rating/completion/trust
 * figures on artisan_profiles. Triggered after events that change any
 * input to the trust score: a published review, or an admin approving/
 * rejecting an identity, skill, or reference verification.
 */
final class TrustScorer
{
    public static function recompute(int $artisanId): void
    {
        $ratingRow = Db::one('SELECT AVG(overall) AS mean, COUNT(*) AS n FROM reviews WHERE artisan_id = ? AND status = "published"', [$artisanId]);
        $ratingMean = round((float)($ratingRow['mean'] ?? 0), 2);
        $ratingCount = (int)($ratingRow['n'] ?? 0);

        $jobStats = Db::one(
            "SELECT COUNT(*) AS total, SUM(status = 'confirmed') AS confirmed FROM jobs WHERE artisan_id = ? AND status != 'cancelled'",
            [$artisanId]
        );
        $total = (int)($jobStats['total'] ?? 0);
        $confirmed = (int)($jobStats['confirmed'] ?? 0);
        $completionRate = $total > 0 ? round($confirmed / $total, 3) : 0.0;

        $profile = Db::one('SELECT * FROM artisan_profiles WHERE user_id = ?', [$artisanId]);
        $user = Db::one('SELECT email_verified_at, phone_verified_at FROM users WHERE id = ?', [$artisanId]);
        $identity = (bool)Db::scalar("SELECT COUNT(*) FROM verifications WHERE artisan_id = ? AND type = 'identity' AND status = 'approved'", [$artisanId]);
        $skillVer = (bool)Db::scalar("SELECT COUNT(*) FROM verifications WHERE artisan_id = ? AND type = 'skill' AND status = 'approved'", [$artisanId]);

        $trust = E::trustScore([
            'verified' => [
                'phone' => $user['phone_verified_at'] !== null, 'email' => $user['email_verified_at'] !== null,
                'identity' => $identity, 'skill' => $skillVer,
            ],
            'profileCompleteness' => (float)$profile['profile_completeness'],
            'completedJobs' => $confirmed,
            'completionRate' => $completionRate,
            'responseRate' => (float)$profile['response_rate'],
            'disputeFreeRate' => (float)$profile['dispute_free_rate'],
        ], E::DEFAULT_CONFIG);

        // verification_level is a human-readable summary of the same inputs,
        // shown on profile/search pages alongside the numeric trust score.
        $level = 'basic';
        if ($identity && $skillVer) {
            $level = 'full';
        } elseif ($identity) {
            $level = 'identity';
        } elseif ($skillVer) {
            $level = 'skill';
        } elseif ($user['phone_verified_at'] !== null) {
            $level = 'phone';
        } elseif ($user['email_verified_at'] !== null) {
            $level = 'email';
        }

        Db::run(
            'UPDATE artisan_profiles SET rating_mean = ?, rating_count = ?, completed_jobs = ?, completion_rate = ?, trust_score = ?, verification_level = ? WHERE user_id = ?',
            [$ratingMean, $ratingCount, $confirmed, $completionRate, $trust['total'], $level, $artisanId]
        );
        Db::run(
            'INSERT INTO trust_score_history (artisan_id, score, verification_part, profile_part, reliability_part, reason) VALUES (?, ?, ?, ?, ?, ?)',
            [$artisanId, $trust['total'], $trust['parts']['verification'], $trust['parts']['profile'], $trust['parts']['reliability'], 'Recomputed after review']
        );
    }
}
