<?php
declare(strict_types=1);

/**
 * Fills in a default trade-icon profile picture for every artisan who
 * doesn't have one yet, since the customer can't reasonably ask 28 separate
 * artisan accounts to each go upload a photo of themselves.
 *
 * The pictures are simple flat icon avatars (wrench, bolt, saw, trowel,
 * paint roller), colored to match each trade's existing color on the site —
 * not photos of real people — pre-generated and shipped under
 * public/uploads/avatars/_default/. This script only points each artisan's
 * avatar_path at one of those three-per-trade shared images; it never
 * touches an artisan who already has their own uploaded avatar_path, so
 * it's safe to leave in place and re-run later (e.g. after new artisans
 * join) — it will only ever fill gaps, never overwrite a real photo.
 *
 * Restricted to logged-in admins.
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
echo "HireCraft default artisan avatars\n==================================\n\n";

$defaultsDir = HC_ROOT . '/public/uploads/avatars/_default';
$variantsPerTrade = 3;
$missing = [];
foreach (Db::all('SELECT slug FROM service_categories') as $row) {
    for ($v = 1; $v <= $variantsPerTrade; $v++) {
        $f = $defaultsDir . '/' . $row['slug'] . '-' . $v . '.png';
        if (!is_file($f)) {
            $missing[] = $row['slug'] . '-' . $v . '.png';
        }
    }
}
if ($missing) {
    echo "ERROR: missing default avatar file(s): " . implode(', ', $missing) . "\n";
    echo "(expected under public/uploads/avatars/_default/)\n";
    exit;
}

$artisans = Db::all(
    'SELECT ap.user_id, sc.slug
     FROM artisan_profiles ap
     JOIN service_categories sc ON sc.id = ap.category_id
     WHERE ap.avatar_path IS NULL
     ORDER BY ap.user_id'
);

if (!$artisans) {
    echo "Every artisan already has a profile picture (their own upload or a default one) — nothing to do.\n";
    exit;
}

$assigned = [];
foreach ($artisans as $a) {
    // Deterministic pick so re-running this script gives the same artisan
    // the same default avatar rather than shuffling on every run.
    $variant = ((int)$a['user_id'] % $variantsPerTrade) + 1;
    $path = 'avatars/_default/' . $a['slug'] . '-' . $variant . '.png';
    Db::run('UPDATE artisan_profiles SET avatar_path = ? WHERE user_id = ?', [$path, $a['user_id']]);
    $assigned[] = 'artisan #' . $a['user_id'] . ' (' . $a['slug'] . ') -> ' . $path;
}

echo "Assigned a default " . implode('/', array_column(Db::all('SELECT slug FROM service_categories'), 'slug')) . " icon avatar to " . count($assigned) . " artisan(s) that had none:\n\n";
echo implode("\n", $assigned) . "\n";
echo "\nDone. Artisans who already had their own uploaded photo were left untouched.\n";
