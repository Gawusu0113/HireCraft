<?php
declare(strict_types=1);

/**
 * One-time (but safe to re-run) schema sync script.
 *
 * Brings an existing HireCraft database up to date with tables/columns added
 * after the initial import, without touching any existing data:
 *   - re-runs database/schema.sql, which is entirely `CREATE TABLE IF NOT
 *     EXISTS` statements, so tables that already exist are left untouched
 *     and only missing tables (job_milestones, disputes, dispute_evidence,
 *     user_reports) get created;
 *   - widens job_requests.urgency to include 'emergency' if it doesn't
 *     already (a MODIFY COLUMN, safe to run more than once);
 *   - adds users.avatar_path (admin profile pictures) if it's missing.
 *
 * Restricted to logged-in admins. Safe to leave on the server permanently:
 * every operation here is idempotent, so hitting it again later is a no-op.
 */

require __DIR__ . '/../src/bootstrap.php';

use HireCraft\Support\Auth;
use HireCraft\Support\Db;

if (!Auth::check() || Auth::role() !== 'admin') {
    http_response_code(403);
    echo "Forbidden — log in as an admin, then reload this page.\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "HireCraft schema sync\n======================\n\n";

$pdo = Db::pdo();

// 1) Re-run schema.sql. It is pure `CREATE TABLE IF NOT EXISTS`, so this is
// safe against a database that already has data in most of its tables.
$sqlFile = HC_ROOT . '/database/schema.sql';
if (!is_file($sqlFile)) {
    echo "ERROR: database/schema.sql not found at $sqlFile\n";
    exit;
}
$sql = file_get_contents($sqlFile);

// Split on semicolons that end a statement (schema.sql has no stored
// procedures/triggers with embedded semicolons, so a plain split is safe).
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$created = [];
$skipped = [];
foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    // Strip line comments so a comment-only fragment doesn't get executed
    // (each CREATE TABLE statement is itself preceded by a "-- ..." comment
    // line, so this can't be a simple "skip if starts with --" check).
    $clean = trim(preg_replace('/^--.*$/m', '', $stmt));
    if ($clean === '') {
        continue;
    }
    if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $clean, $m)) {
        $table = $m[1];
        $existedBefore = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetch();
        $pdo->exec($clean);
        if ($existedBefore) {
            $skipped[] = $table;
        } else {
            $created[] = $table;
        }
    } else {
        // Any non-CREATE-TABLE statement in schema.sql — run as-is.
        $pdo->exec($clean);
    }
}

echo "Tables newly created: " . (count($created) ? implode(', ', $created) : '(none — all already existed)') . "\n";
echo "Tables already present: " . count($skipped) . "\n\n";

// 2) Widen job_requests.urgency to include 'emergency' if needed.
$col = $pdo->query("SHOW COLUMNS FROM job_requests LIKE 'urgency'")->fetch(PDO::FETCH_ASSOC);
if ($col && !str_contains($col['Type'], "'emergency'")) {
    $pdo->exec("ALTER TABLE job_requests
        MODIFY COLUMN urgency ENUM('low','normal','high','emergency') NOT NULL DEFAULT 'normal'");
    echo "job_requests.urgency: widened to include 'emergency'.\n";
} elseif ($col) {
    echo "job_requests.urgency: already includes 'emergency', no change needed.\n";
} else {
    echo "job_requests.urgency: column not found (unexpected — check job_requests exists).\n";
}

// 3) Add users.avatar_path (used for the admin's own profile picture — customers
// and artisans already had an avatar_path column on their own profile table).
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_path'")->fetch(PDO::FETCH_ASSOC);
if (!$col) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER status");
    echo "users.avatar_path: added.\n";
} else {
    echo "users.avatar_path: already present, no change needed.\n";
}

echo "\nDone. Your database is now in sync with the current codebase.\n";
