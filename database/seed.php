<?php
declare(strict_types=1);

/**
 * Seeds the HireCraft database with:
 *  - the reference catalogue (areas, trades, skills) from design/engine/data.js
 *  - 13 synthetic artisan accounts (same ones used by the clickable prototype
 *    and the design document's worked examples), fully profiled: skills,
 *    service areas, availability, price ranges, verification, portfolio
 *  - a spread of historical completed+reviewed jobs so profiles, trust
 *    history and "jobs completed" counts are backed by real rows, not just
 *    cached numbers
 *  - price benchmarks and the initial (version 1) matching configuration
 *  - a demo customer account with one already-posted job, ready to view
 *    SmartMatch results for
 *
 * ALL data here is clearly-labelled synthetic sample data (see the design
 * document's caveats) — names, prices and history are invented to
 * demonstrate the system, not findings from the requirements study.
 *
 * Usage: php database/seed.php   (run after importing database/schema.sql)
 */

require __DIR__ . '/../src/bootstrap_cli.php';

use HireCraft\Support\Db;
use HireCraft\Engine\SmartMatchEngine as E;
use HireCraft\Repo\MatchData;
use HireCraft\Controllers\JobController;

Db::init(HC_CONFIG['db']);
$pdo = Db::pdo();

mt_srand(42); // reproducible seed data across reseeds

$D = json_decode(file_get_contents(__DIR__ . '/seed_data.json'), true, flags: JSON_THROW_ON_ERROR);
$DEMO_PASSWORD = 'Passw0rd!';
$hash = password_hash($DEMO_PASSWORD, PASSWORD_DEFAULT);

echo "Clearing existing data...\n";
// Also clear previously-uploaded job/portfolio photos on disk so a reseed
// doesn't accumulate orphaned files with no matching DB row.
foreach (['jobs', 'portfolio', 'verifications'] as $sub) {
    $dir = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . $sub;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'admin_actions', 'user_reports', 'dispute_evidence', 'disputes', 'job_milestones',
    'messages', 'notifications', 'price_benchmarks', 'matching_config',
    'recommendation_feedback', 'favorites', 'reviews', 'job_updates', 'jobs', 'quotations',
    'artisan_job_applications', 'job_feasibility_results', 'budget_analysis_results', 'artisan_job_matches',
    'job_required_skills', 'job_images', 'job_requests',
    'trust_score_history', 'portfolio_skills', 'portfolio_images', 'portfolios', 'verifications',
    'artisan_price_ranges', 'artisan_time_off', 'artisan_availability', 'artisan_service_areas', 'artisan_skills',
    'artisan_profiles', 'customer_profiles', 'users', 'skills', 'service_categories', 'areas',
] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ------------------------------------------------------------------ reference catalogue
echo "Reference catalogue...\n";
$categoryId = []; // code => id
foreach ($D['categories'] as $code => $cat) {
    $categoryId[$code] = Db::insert(
        'INSERT INTO service_categories (name, slug, description, is_active) VALUES (?,?,?,1)',
        [$cat['name'], $code, $cat['name'] . ' services']
    );
}
$skillId = []; // code => id
$skillComplexity = []; // code => 1/2/3
foreach ($D['skills'] as $code => $sk) {
    $skillId[$code] = Db::insert(
        'INSERT INTO skills (category_id, name, complexity_weight, is_active) VALUES (?,?,?,1)',
        [$categoryId[$sk['category']], $sk['name'], $sk['complexity']]
    );
    $skillComplexity[$code] = $sk['complexity'];
}
$areaId = []; // code => id
foreach ($D['areas'] as $code => $ar) {
    $areaId[$code] = Db::insert(
        'INSERT INTO areas (name, district, latitude, longitude) VALUES (?,?,?,?)',
        [$ar['name'], $ar['district'] ?? null, $ar['lat'], $ar['lng']]
    );
}
$complexityLabel = [1 => 'simple', 2 => 'medium', 3 => 'complex'];

// ------------------------------------------------------------------ admin + legacy customer accounts
echo "Admin and demo customer accounts...\n";
$adminId = Db::insert(
    "INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at, phone_verified_at, status) VALUES ('admin','Ama Konadu','admin@hirecraft.test','0240000000',?,NOW(),NOW(),'active')",
    [$hash]
);

$legacyCustomerIds = [];
$legacyNames = ['Yaa Asantewaa', 'Kwabena Owusu', 'Abena Fosu', 'Kwadwo Nkrumah'];
foreach ($legacyNames as $i => $name) {
    $areaCode = array_rand($areaId);
    $uid = Db::insert(
        "INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at, phone_verified_at, status) VALUES ('customer',?,?,?,?,NOW(),NOW(),'active')",
        [$name, 'legacy' . ($i + 1) . '@hirecraft.test', '02411100' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT), $hash]
    );
    Db::run('INSERT INTO customer_profiles (user_id, area_id) VALUES (?,?)', [$uid, $areaId[$areaCode]]);
    $legacyCustomerIds[] = $uid;
}

$demoCustomerId = Db::insert(
    "INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at, phone_verified_at, status) VALUES ('customer','Adjoa Ofori','customer@hirecraft.test','0240000001',?,NOW(),NOW(),'active')",
    [$hash]
);
Db::run('INSERT INTO customer_profiles (user_id, area_id) VALUES (?,?)', [$demoCustomerId, $areaId['adum']]);

// ------------------------------------------------------------------ artisans
echo "Artisan accounts and profiles...\n";
$slugEmail = function (string $name, string $id): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name));
    return trim($slug, '.') . '@hirecraft.test';
};
$artisanUserId = []; // code => users.id
$historyJobCounts = []; // code => total completed jobs actually generated (for reporting)

// $D['artisans'] is the 13-artisan reference set that tests/EngineTest.php's
// fixtures are pinned to — it reads seed_data.json independently, so this
// seeder and the engine test suite always agree on exactly the same roster.
$allArtisans = $D['artisans'];

foreach ($allArtisans as $a) {
    $email = $slugEmail($a['name'], $a['id']);
    $phone = '024' . str_pad((string)(1000000 + crc32($a['id']) % 8999999), 7, '0', STR_PAD_LEFT);
    $uid = Db::insert(
        "INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at, phone_verified_at, status) VALUES ('artisan',?,?,?,?,?,?,'active')",
        [$a['name'], $email, $phone, $hash, $a['verified']['email'] ? date('Y-m-d H:i:s') : null, $a['verified']['phone'] ? date('Y-m-d H:i:s') : null]
    );
    $artisanUserId[$a['id']] = $uid;

    $verLevel = 'basic';
    if ($a['verified']['phone']) $verLevel = 'phone';
    if ($a['verified']['email']) $verLevel = 'email';
    if ($a['verified']['identity']) $verLevel = 'identity';
    if ($a['verified']['skill']) $verLevel = 'skill';
    if ($a['verified']['identity'] && $a['verified']['skill'] && $a['verified']['phone'] && $a['verified']['email']) $verLevel = 'full';

    // Trust score computed with the same engine that will run at match time,
    // so the cached figure on the profile always agrees with the formula.
    $trust = E::trustScore([
        'verified' => $a['verified'], 'profileCompleteness' => $a['profileCompleteness'],
        'completedJobs' => $a['completedJobs'], 'completionRate' => $a['completionRate'],
        'responseRate' => $a['responseRate'], 'disputeFreeRate' => $a['disputeFreeRate'],
    ], E::DEFAULT_CONFIG);

    Db::run(
        'INSERT INTO artisan_profiles (user_id, business_name, category_id, bio, years_experience, base_area_id, travel_radius_km,
            accepts_emergency, profile_completeness, verification_level, trust_score, approval_status,
            rating_mean, rating_count, completed_jobs, completion_rate, response_rate, dispute_free_rate)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $uid, $a['business'], $categoryId[$a['category']], null, $a['years'], $areaId[$a['baseArea']], $a['radiusKm'],
            $a['emergency'] ? 1 : 0, $a['profileCompleteness'], $verLevel, $trust['total'], 'approved',
            $a['ratingMean'], $a['ratingCount'], $a['completedJobs'], $a['completionRate'], $a['responseRate'], $a['disputeFreeRate'],
        ]
    );

    foreach ($a['skills'] as $sc) {
        Db::run('INSERT INTO artisan_skills (artisan_id, skill_id) VALUES (?,?)', [$uid, $skillId[$sc]]);
    }
    foreach ($a['serviceAreas'] as $ac) {
        Db::run('INSERT IGNORE INTO artisan_service_areas (artisan_id, area_id) VALUES (?,?)', [$uid, $areaId[$ac]]);
    }
    foreach ($a['weekdays'] as $wd) {
        Db::run('INSERT INTO artisan_availability (artisan_id, weekday) VALUES (?,?)', [$uid, $wd]);
    }
    foreach ($a['blocked'] ?? [] as $off) {
        Db::run('INSERT INTO artisan_time_off (artisan_id, off_date) VALUES (?,?)', [$uid, $off]);
    }
    foreach ($a['prices'] as $complexity => $range) {
        Db::run('INSERT INTO artisan_price_ranges (artisan_id, complexity, min_price, max_price) VALUES (?,?,?,?)', [$uid, $complexity, $range[0], $range[1]]);
    }
    if ($a['verified']['identity']) {
        Db::run("INSERT INTO verifications (artisan_id, type, document_type, status, reviewed_by, reviewed_at) VALUES (?,'identity','Ghana Card','approved',?,NOW())", [$uid, $adminId]);
    }
    if ($a['verified']['skill']) {
        Db::run("INSERT INTO verifications (artisan_id, type, document_type, status, reviewed_by, reviewed_at) VALUES (?,'skill','Trade certificate','approved',?,NOW())", [$uid, $adminId]);
    }
    Db::run(
        'INSERT INTO trust_score_history (artisan_id, score, verification_part, profile_part, reliability_part, reason) VALUES (?,?,?,?,?,?)',
        [$uid, $trust['total'], $trust['parts']['verification'], $trust['parts']['profile'], $trust['parts']['reliability'], 'Seed data import']
    );

    // Portfolio: real portfolio_skills rows back the "portfolio items" the engine counts.
    // Each item also gets one placeholder photo copied in from
    // database/seed_assets/portfolio/, so the portfolio-photo feature has
    // something to show out of the box instead of an empty gallery.
    $assetDir = HC_ROOT . '/database/seed_assets/portfolio';
    $tradeAssets = glob($assetDir . '/' . $a['category'] . '_*.jpg') ?: [];
    sort($tradeAssets);
    $uploadDir = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/portfolio/' . $uid;
    foreach ($a['portfolio'] as $sc => $n) {
        for ($i = 0; $i < $n; $i++) {
            $pid = Db::insert(
                'INSERT INTO portfolios (artisan_id, title, description, category_id, completed_on) VALUES (?,?,?,?,?)',
                [$uid, $D['skills'][$sc]['name'] . ' project ' . ($i + 1), 'Sample portfolio project shown for design demonstration.', $categoryId[$a['category']], null]
            );
            Db::run('INSERT INTO portfolio_skills (portfolio_id, skill_id) VALUES (?,?)', [$pid, $skillId[$sc]]);

            if ($tradeAssets) {
                $src = $tradeAssets[($pid + $i) % count($tradeAssets)];
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destName = bin2hex(random_bytes(8)) . '.jpg';
                if (copy($src, $uploadDir . '/' . $destName)) {
                    Db::run(
                        'INSERT INTO portfolio_images (portfolio_id, image_path, kind, sort_order) VALUES (?,?,?,?)',
                        [$pid, 'portfolio/' . $uid . '/' . $destName, 'other', 0]
                    );
                }
            }
        }
    }

    // Historical completed+confirmed jobs: one per unit of "similar work" in
    // data.js's history{skill: count}, so portfolio/history scoring is a
    // real join over real rows rather than a hard-coded number.
    $jobIdsForReview = [];
    foreach ($a['history'] as $sc => $n) {
        $complexity = $complexityLabel[$skillComplexity[$sc]];
        $range = $a['prices'][$complexity] ?? $a['prices']['medium'];
        for ($i = 0; $i < $n; $i++) {
            $customerId = $legacyCustomerIds[array_rand($legacyCustomerIds)];
            $areaCode = $a['serviceAreas'][array_rand($a['serviceAreas'])];
            $daysAgo = 30 + mt_rand(0, 540);
            $postedAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
            $price = mt_rand((int)$range[0], (int)$range[1]);
            $jrId = Db::insert(
                "INSERT INTO job_requests (customer_id, title, category_id, description, area_id, preferred_date, urgency, budget_type,
                    budget_min, budget_max, complexity, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,?, 'normal', 'fixed', ?, ?, ?, 'completed', ?, ?)",
                [$customerId, $D['skills'][$sc]['name'] . ' (completed)', $categoryId[$a['category']],
                    'Completed sample job used to seed the artisan\'s history for ' . $D['skills'][$sc]['name'] . '.',
                    $areaId[$areaCode], date('Y-m-d', strtotime($postedAt)), $price, $price, $complexity, $postedAt, $postedAt]
            );
            Db::run('INSERT INTO job_required_skills (job_request_id, skill_id) VALUES (?,?)', [$jrId, $skillId[$sc]]);
            $jobId = Db::insert(
                "INSERT INTO jobs (job_request_id, customer_id, artisan_id, agreed_price, scheduled_date, started_at, completed_at, confirmed_at, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?, 'confirmed', ?)",
                [$jrId, $customerId, $uid, $price, date('Y-m-d', strtotime($postedAt)), $postedAt, $postedAt, $postedAt, $postedAt]
            );
            $jobIdsForReview[] = ['job_id' => $jobId, 'jr_id' => $jrId, 'customer_id' => $customerId, 'at' => $postedAt];
        }
    }
    $historyJobCounts[$a['id']] = count($jobIdsForReview);

    // Reviews: a subset of the historical jobs, sized to the artisan's cached rating count.
    shuffle($jobIdsForReview);
    $reviewN = min($a['ratingCount'], count($jobIdsForReview));
    $comments = [
        'Good work, arrived on time.', 'Very professional and explained everything clearly.',
        'Satisfied with the job. Would hire again.', 'Solid work, a little late starting but finished well.',
        'Reliable and fair price.', 'Clean, tidy work.',
    ];
    for ($i = 0; $i < $reviewN; $i++) {
        $j = $jobIdsForReview[$i];
        $overall = max(1, min(5, (int)round($a['ratingMean'] + (mt_rand(-10, 10) / 10))));
        Db::run(
            "INSERT INTO reviews (job_id, customer_id, artisan_id, overall, quality, professionalism, communication, punctuality, comment, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$j['job_id'], $j['customer_id'], $uid, $overall, $overall, $overall, $overall, max(1, min(5, $overall + mt_rand(-1, 1))), $comments[array_rand($comments)], $j['at']]
        );
        Db::run("UPDATE job_requests SET status='reviewed' WHERE id=?", [$j['jr_id']]);
    }
}

// ------------------------------------------------------------------ price benchmarks
echo "Price benchmarks...\n";
foreach ($D['benchmarks'] as $key => $b) {
    [$catCode, $complexity] = explode(':', $key);
    Db::run(
        "INSERT INTO price_benchmarks (category_id, complexity, observations, p25, median, p75, source) VALUES (?,?,?,?,?,?, 'seed')",
        [$categoryId[$catCode], $complexity, $b['n'], $b['p25'], $b['median'], $b['p75']]
    );
}

// ------------------------------------------------------------------ matching configuration v1
echo "Matching configuration v1...\n";
$cfg = E::DEFAULT_CONFIG;
$weights = $cfg['weights'];
unset($cfg['weights']);
Db::run(
    "INSERT INTO matching_config (config_version, weights, parameters, is_active, changed_by, note) VALUES (1,?,?,1,?, 'Initial placeholder weights (to be calibrated from the survey)')",
    [json_encode($weights), json_encode($cfg), $adminId]
);

// ------------------------------------------------------------------ one live demo job for the demo customer
// Run it through the real SmartMatch pipeline (same MatchData/engine calls
// and persist helpers as JobController::create()) so the demo customer's
// one open job has actual budget/feasibility/ranked-match results to view,
// not just a bare "posted" row.
echo "Demo open job...\n";
$s1 = null;
foreach ($D['scenarios'] as $s) if ($s['id'] === 'S1') $s1 = $s;

$demoSkillIds = array_map(fn($sc) => $skillId[$sc], $s1['skillIds']);
$demoUrgency = $s1['urgency'] === 'high' ? 'high' : 'normal';
$demoAreaId = $areaId[$s1['areaId']];

$ref = MatchData::ref();
$demoJob = [
    'title' => $s1['title'],
    'description' => $s1['description'],
    'categoryId' => null,
    'skillIds' => $demoSkillIds,
    'areaId' => $demoAreaId,
    'date' => $s1['date'],
    'urgency' => $demoUrgency,
    'budgetType' => 'range',
    'budgetMin' => (float)$s1['budgetMin'],
    'budgetMax' => (float)$s1['budgetMax'],
    'negotiable' => false,
];
$demoAnalysis = E::analyzeJob($demoJob, $ref);
$demoPrimaryCategoryId = $categoryId[$s1['categoryId']];

// visibility='public' so this demo job also shows up on the artisan-side
// "Find jobs" browse page (Task #23), not just the customer's own SmartMatch
// results — gives that feature something real to demonstrate out of the box.
$jrId = Db::insert(
    "INSERT INTO job_requests (customer_id, title, category_id, description, area_id, preferred_date, urgency, budget_type,
        budget_min, budget_max, is_negotiable, visibility, complexity, is_multi_trade, status, created_at, updated_at)
     VALUES (?,?,?,?,?,?,?, 'range', ?,?,0,'public',?,?,'matched', NOW(), NOW())",
    [$demoCustomerId, $s1['title'], $demoPrimaryCategoryId, $s1['description'], $demoAreaId,
        $s1['date'], $demoUrgency, $s1['budgetMin'], $s1['budgetMax'], $demoAnalysis['complexity'], $demoAnalysis['multiTrade'] ? 1 : 0]
);
foreach ($demoSkillIds as $sid) {
    Db::run('INSERT INTO job_required_skills (job_request_id, skill_id) VALUES (?,?)', [$jrId, $sid]);
}

$demoArtisans = MatchData::artisans();
$demoBenchmarks = MatchData::benchmarks();
$demoActiveConfig = MatchData::activeConfig();
$demoResult = E::match($demoJob, $ref, $demoArtisans, ['config' => $demoActiveConfig['config'], 'benchmarks' => $demoBenchmarks]);

JobController::persistBudget($jrId, $demoJob['budgetMin'], $demoJob['budgetMax'], $demoResult['budget']);
JobController::persistFeasibility($jrId, $demoResult['feasibility']);
JobController::persistMatches($jrId, $demoResult, $demoActiveConfig['version']);

echo "\nDone.\n";
echo 'Artisans: ' . count($artisanUserId) . ', historical completed jobs: ' . array_sum($historyJobCounts) . "\n";
echo "Demo accounts (password for all: $DEMO_PASSWORD):\n";
echo "  Admin:    admin@hirecraft.test\n";
echo "  Customer: customer@hirecraft.test  (has one open job: \"{$s1['title']}\")\n";
echo "  Artisans: e.g. " . $slugEmail('Kwame Boateng', 'p1') . " — "
    . count($allArtisans) . " artisans across " . count($D['areas']) . " areas in Kumasi; see database/seed_data.json for the full list.\n";
