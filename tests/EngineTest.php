<?php
declare(strict_types=1);

/**
 * PHP port of design/engine/test.js — the same 25 checks, run against the
 * PHP SmartMatchEngine instead of the JavaScript reference, using the same
 * synthetic fixtures (database/seed_data.json, exported straight from
 * design/engine/data.js so the two implementations are tested on
 * byte-identical input). No PHPUnit dependency: this environment has no
 * internet access for `composer require`, so this is a small standalone
 * runner in the same spirit as test.js's own hand-rolled `t()` helper.
 *
 * Run: php tests/EngineTest.php
 */

require __DIR__ . '/../src/bootstrap_cli.php';

use HireCraft\Engine\SmartMatchEngine as E;

$D = json_decode(file_get_contents(__DIR__ . '/../database/seed_data.json'), true, flags: JSON_THROW_ON_ERROR);
$ref = ['skills' => $D['skills'], 'areas' => $D['areas'], 'categories' => $D['categories']];
$artisans = $D['artisans'];
$benchmarks = $D['benchmarks'];
$scenarios = $D['scenarios'];

function scenarioById(array $scenarios, string $id): array
{
    foreach ($scenarios as $s) if ($s['id'] === $id) return $s;
    throw new RuntimeException("no scenario $id");
}

function run(string $id, array $scenarios, array $ref, array $artisans, array $benchmarks, array $cfg = []): array
{
    $job = scenarioById($scenarios, $id);
    $opts = ['benchmarks' => $benchmarks];
    if ($cfg) $opts['config'] = $cfg;
    return E::match($job, $ref, $artisans, $opts);
}

$fails = 0;
$pass = 0;
function t(string $name, callable $fn): void
{
    global $fails, $pass;
    try {
        $fn();
        $pass++;
        echo "  ok   $name\n";
    } catch (\Throwable $e) {
        $fails++;
        echo "  FAIL $name :: " . $e->getMessage() . "\n";
    }
}

function assertTrue($cond, string $msg = 'assertion failed'): void
{
    if (!$cond) throw new RuntimeException($msg);
}
function assertEq($a, $b, string $msg = ''): void
{
    if ($a !== $b) throw new RuntimeException(($msg ?: 'not equal') . ' (' . var_export($a, true) . ' !== ' . var_export($b, true) . ')');
}

foreach (['S1', 'S2', 'S3', 'S4', 'S5', 'S6'] as $id) {
    $r = run($id, $scenarios, $ref, $artisans, $benchmarks);
    $s = scenarioById($scenarios, $id);
    echo "\n=== $id {$s['title']} | complexity={$r['analysis']['complexity']} multiTrade=" . ($r['analysis']['multiTrade'] ? 'true' : 'false') . ' urgent=' . ($r['analysis']['urgent'] ? 'true' : 'false') . "\n";
    echo "   budget: {$r['budget']['klass']} ({$r['budget']['confidence']})  feasibility: {$r['feasibility']['level']} :: " . implode(' | ', $r['feasibility']['reasons']) . "\n";
    foreach (array_slice($r['matches'], 0, 5) as $i => $m) {
        $bd = [];
        foreach ($m['breakdown'] as $k => $v) $bd[] = substr($k, 0, 3) . ':' . $v['points'];
        printf("   %d. %-16s %5s %-19s trust=%s %s\n", $i + 1, $m['artisan']['name'], (string)$m['score'], $m['level'], (string)$m['trust']['total'], implode(' ', $bd));
    }
}

echo "\nAssertions\n";

t('weights normalise to 100', function () {
    $w = E::effectiveWeights(E::DEFAULT_CONFIG, false);
    assertTrue(abs(array_sum($w) - 100) < 1e-9);
});
t('urgent raises location+availability weights', function () {
    $a = E::effectiveWeights(E::DEFAULT_CONFIG, false);
    $b = E::effectiveWeights(E::DEFAULT_CONFIG, true);
    assertTrue($b['availability'] > $a['availability'] && $b['location'] > $a['location'] && $b['skill'] < $a['skill']);
});
t('S1: top pick is a plumber near Adum and available', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S1', $scenarios, $ref, $artisans, $benchmarks);
    assertEq($r['matches'][0]['artisan']['id'], 'p1');
    assertTrue($r['matches'][0]['score'] >= 85);
});
t('S1: emergency-capable plumber beats non-emergency', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S1', $scenarios, $ref, $artisans, $benchmarks);
    $ids = array_map(fn($m) => $m['artisan']['id'], $r['matches']);
    assertTrue(array_search('p1', $ids, true) < array_search('p2', $ids, true));
});
t('S1: only plumbers are returned', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S1', $scenarios, $ref, $artisans, $benchmarks);
    foreach ($r['matches'] as $m) assertTrue($m['artisan']['category'] === 'plumbing');
});
t('S2: Kojo Owusu (e1) is top and feasibility is HIGH or MEDIUM', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S2', $scenarios, $ref, $artisans, $benchmarks);
    assertEq($r['matches'][0]['artisan']['id'], 'e1');
    assertTrue(in_array($r['feasibility']['level'], ['HIGH', 'MEDIUM'], true));
});
t('S2: complexity complex', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    assertEq(run('S2', $scenarios, $ref, $artisans, $benchmarks)['analysis']['complexity'], 'complex');
});
t('S3: carpenters ranked; new artisan c3 is not excluded', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S3', $scenarios, $ref, $artisans, $benchmarks);
    assertTrue((bool)array_filter($r['matches'], fn($m) => $m['artisan']['id'] === 'c3'));
    assertEq($r['matches'][0]['artisan']['id'], 'c1');
});
t('S4: severe budget shortfall caps the level below RECOMMENDED', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S4', $scenarios, $ref, $artisans, $benchmarks);
    foreach ($r['matches'] as $m) assertTrue($m['score'] <= 69);
    assertTrue($r['matches'][0]['capped'] !== null);
});
t('S3: artisan outside travel range is capped', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S3', $scenarios, $ref, $artisans, $benchmarks);
    $c3 = null;
    foreach ($r['matches'] as $m) if ($m['artisan']['id'] === 'c3') $c3 = $m;
    assertTrue($c3 !== null && $c3['capped'] !== null && $c3['score'] <= 69);
});
t('S6: multi-trade returns one group per trade', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S6', $scenarios, $ref, $artisans, $benchmarks);
    $keys = array_keys($r['groups']);
    sort($keys);
    assertEq($keys, ['electrical', 'painting', 'plumbing']);
    assertTrue($r['groups']['electrical']['matches'][0]['artisan']['category'] === 'electrical');
});
t('S4: budget assessment is POSSIBLY INSUFFICIENT and recommends quotations', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S4', $scenarios, $ref, $artisans, $benchmarks);
    assertEq($r['budget']['klass'], 'POSSIBLY INSUFFICIENT');
    assertTrue(stripos($r['budget']['action'], 'quotation') !== false);
});
t('S5: masonry complex has too little data -> INSUFFICIENT DATA', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S5', $scenarios, $ref, $artisans, $benchmarks);
    assertEq($r['budget']['klass'], 'INSUFFICIENT DATA');
    assertEq($r['budget']['confidence'], 'none');
});
t('S6: multi-trade job detected and budget unknown handled', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $r = run('S6', $scenarios, $ref, $artisans, $benchmarks);
    assertTrue($r['analysis']['multiTrade']);
    assertEq($r['budget']['klass'], 'INSUFFICIENT DATA');
});
t('missing description -> REQUIRES MORE INFORMATION', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $j = scenarioById($scenarios, 'S1');
    $j['description'] = 'help';
    $r = E::match($j, $ref, $artisans, ['benchmarks' => $benchmarks]);
    assertEq($r['feasibility']['level'], 'REQUIRES MORE INFORMATION');
});
t('suspended artisan is excluded', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $arts = array_map(fn($a) => $a['id'] === 'p1' ? array_merge($a, ['status' => 'suspended']) : $a, $artisans);
    $r = E::match(scenarioById($scenarios, 'S1'), $ref, $arts, ['benchmarks' => $benchmarks]);
    foreach ($r['matches'] as $m) assertTrue($m['artisan']['id'] !== 'p1');
});
t('scores are within 0..100 and sorted descending', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    foreach (['S1', 'S2', 'S3', 'S4', 'S5'] as $id) {
        $r = run($id, $scenarios, $ref, $artisans, $benchmarks);
        foreach ($r['matches'] as $m) assertTrue($m['score'] >= 0 && $m['score'] <= 100);
        for ($i = 1; $i < count($r['matches']); $i++) assertTrue($r['matches'][$i - 1]['score'] >= $r['matches'][$i]['score']);
    }
});
t('points sum to score (within rounding)', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $m = run('S1', $scenarios, $ref, $artisans, $benchmarks)['matches'][0];
    $sum = 0;
    foreach ($m['breakdown'] as $b) $sum += $b['points'];
    assertTrue(abs($sum - $m['score']) < 0.5);
});
t('trust: new artisan not punished to zero (fairness)', function () use ($artisans) {
    $p3 = null; $c3 = null;
    foreach ($artisans as $a) { if ($a['id'] === 'p3') $p3 = $a; if ($a['id'] === 'c3') $c3 = $a; }
    $tr = E::trustScore($p3, E::DEFAULT_CONFIG);
    assertTrue($tr['total'] >= 30);
    $tr2 = E::trustScore($c3, E::DEFAULT_CONFIG);
    assertTrue($tr2['total'] >= 60);
});
t('trust: verification raises trust', function () use ($artisans) {
    $a = null;
    foreach ($artisans as $x) if ($x['id'] === 'e3') $a = $x;
    $hi = array_merge($a, ['verified' => ['phone' => 1, 'email' => 1, 'identity' => 1, 'skill' => 1]]);
    assertTrue(E::trustScore($hi, E::DEFAULT_CONFIG)['total'] > E::trustScore($a, E::DEFAULT_CONFIG)['total']);
});
t('rating: Bayesian average damps tiny samples', function () use ($artisans) {
    $a = null;
    foreach ($artisans as $x) if ($x['id'] === 'c3') $a = $x;
    $r = E::ratingScore($a, E::DEFAULT_CONFIG);
    assertTrue($r['bayes'] < 4.8 && $r['bayes'] > 3.8);
});
t('budget score: within > below', function () {
    $w = E::budgetScore(['budgetType' => 'range', 'budgetMin' => 100, 'budgetMax' => 300, 'negotiable' => false], [100, 300])['score'];
    $b = E::budgetScore(['budgetType' => 'range', 'budgetMin' => 50, 'budgetMax' => 80, 'negotiable' => false], [100, 300])['score'];
    assertTrue($w > $b);
});
t('negotiable lifts a below-range budget', function () {
    $a = E::budgetScore(['budgetType' => 'range', 'budgetMin' => 50, 'budgetMax' => 80, 'negotiable' => false], [100, 300])['score'];
    $b = E::budgetScore(['budgetType' => 'range', 'budgetMin' => 50, 'budgetMax' => 80, 'negotiable' => true], [100, 300])['score'];
    assertTrue($b > $a);
});
t('two-sided: recommendJobs ranks jobs for an artisan', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $p1 = null;
    foreach ($artisans as $a) if ($a['id'] === 'p1') $p1 = $a;
    $rec = E::recommendJobs($scenarios, $ref, $p1, ['benchmarks' => $benchmarks]);
    assertTrue(count($rec) >= 1);
    assertEq($rec[0]['job']['id'], 'S1');
});
t('sensitivity returns stability metrics', function () use ($scenarios, $ref, $artisans, $benchmarks) {
    $s = E::sensitivity($scenarios[0], $ref, $artisans, ['benchmarks' => $benchmarks], 5, 3);
    assertTrue($s['top1Stability'] >= 0 && $s['top1Stability'] <= 1);
    echo '        S1 top-1 stability ' . number_format($s['top1Stability'], 2) . ' mean top-3 overlap ' . number_format($s['meanTopKOverlap'], 2) . "\n";
});

echo $fails ? "\n$fails FAILED\n" : "\nAll passed ($pass)\n";
exit($fails ? 1 : 0);
