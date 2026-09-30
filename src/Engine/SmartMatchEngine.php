<?php
declare(strict_types=1);

namespace HireCraft\Engine;

/**
 * HireCraft SmartMatch Engine — PHP port.
 *
 * This is a line-by-line port of design/engine/engine.js (the tested
 * JavaScript reference implementation used by the clickable prototype).
 * Every formula, constant and branch below has a matching line in that
 * file, and tests/EngineTest.php re-runs the same 25 checks as
 * design/engine/test.js against this class. Data is passed and returned
 * as plain associative arrays, mirroring the JS objects, so the two
 * implementations stay easy to compare side by side.
 *
 * Inputs:
 *   $job    = ['title','description','categoryId','skillIds'=>[...], 'areaId',
 *              'date' (Y-m-d or null), 'urgency' ('low'|'normal'|'high'),
 *              'budgetType' ('range'|'fixed'|'unknown'), 'budgetMin','budgetMax','negotiable']
 *   $ref    = ['skills'=>[id=>[...]], 'areas'=>[id=>['lat','lng','name']], 'categories'=>[id=>['name']]]
 *   $artisans = list of ['id','name','business','category','skills'=>[ids],'baseArea','serviceAreas'=>[ids],
 *               'radiusKm','prices'=>['simple'=>[min,max],'medium'=>[..],'complex'=>[..]],
 *               'weekdays'=>[0..6],'blocked'=>[dates],'emergency'(bool),'years',
 *               'verified'=>['phone','email','identity','skill'](bool), 'profileCompleteness',
 *               'completedJobs','completionRate','responseRate','disputeFreeRate',
 *               'ratingCount','ratingMean','history'=>[skillId=>n],'portfolio'=>[skillId=>n],'status']
 */
final class SmartMatchEngine
{
    public const DEFAULT_CONFIG = [
        'weights' => ['skill' => 30, 'location' => 15, 'budget' => 15, 'availability' => 10, 'experience' => 10, 'trust' => 10, 'rating' => 5, 'portfolio' => 5],
        'urgencyBoost' => 1.5,
        'minMatchScore' => 50,
        'levels' => ['highly' => 85, 'recommended' => 70, 'possible' => 50],
        'experienceCapYears' => 10,
        'similarWorkCap' => 5,
        'ratingPrior' => ['m' => 5, 'mean' => 3.8],
        'reliabilityPrior' => ['k' => 5, 'value' => 0.6],
        'trustParts' => ['verification' => 40, 'profile' => 20, 'reliability' => 40],
        'verificationParts' => ['phone' => 0.2, 'email' => 0.1, 'identity' => 0.4, 'skill' => 0.3],
        'benchmark' => ['minObservations' => 5, 'highConfidence' => 20, 'mediumConfidence' => 10],
        'feasibility' => ['minSuitableForHigh' => 3],
        'locationRadiusHardLimit' => 1.5,
        'capScore' => 69,
        'severeBudgetShortfall' => 0.25,
    ];

    private const LEVEL_LABELS = ['HIGHLY RECOMMENDED', 'RECOMMENDED', 'POSSIBLE MATCH', 'LOW COMPATIBILITY'];
    public const COMPONENTS = ['skill', 'location', 'budget', 'availability', 'experience', 'trust', 'rating', 'portfolio'];

    // ------------------------------------------------------------------ helpers
    public static function clamp(float $x, float $lo = 0.0, float $hi = 1.0): float
    {
        return max($lo, min($hi, $x));
    }

    public static function round(float $x, int $d = 1): float
    {
        return round($x, $d);
    }

    public static function gh(float $n): string
    {
        return 'GH¢' . number_format(round($n));
    }

    public static function haversineKm(array $a, array $b): float
    {
        $R = 6371.0;
        $toRad = fn($d) => $d * M_PI / 180;
        $dLat = $toRad($b['lat'] - $a['lat']);
        $dLng = $toRad($b['lng'] - $a['lng']);
        $s = sin($dLat / 2) ** 2 + cos($toRad($a['lat'])) * cos($toRad($b['lat'])) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(sqrt($s));
    }

    private static function normaliseWeights(array $w): array
    {
        $total = 0.0;
        foreach (self::COMPONENTS as $k) $total += $w[$k] ?? 0;
        if ($total <= 0) $total = 1;
        $out = [];
        foreach (self::COMPONENTS as $k) $out[$k] = ($w[$k] ?? 0) / $total * 100;
        return $out;
    }

    public static function effectiveWeights(array $config, bool $urgent): array
    {
        $w = $config['weights'];
        if ($urgent) {
            $w['location'] *= $config['urgencyBoost'];
            $w['availability'] *= $config['urgencyBoost'];
        }
        return self::normaliseWeights($w);
    }

    // ------------------------------------------------------------------ job analysis (rule-based)
    public static function analyzeJob(array $job, array $ref): array
    {
        $skills = [];
        foreach ($job['skillIds'] as $id) {
            if (isset($ref['skills'][$id])) $skills[] = $ref['skills'][$id];
        }
        $cats = array_values(array_unique(array_map(fn($s) => $s['category'], $skills)));
        $primary = (!empty($job['categoryId']) && in_array($job['categoryId'], $cats, true))
            ? $job['categoryId'] : ($cats[0] ?? ($job['categoryId'] ?? null));
        $maxW = 0;
        foreach ($skills as $s) $maxW = max($maxW, $s['complexity']);
        $index = $maxW + (int)floor(max(count($skills) - 1, 0) / 2) + max(count($cats) - 1, 0);
        $complexity = $index <= 1 ? 'simple' : ($index === 2 ? 'medium' : 'complex');
        $secondary = array_values(array_filter($cats, fn($c) => $c !== $primary));
        $urgent = ($job['urgency'] ?? '') === 'high' || ($job['urgency'] ?? '') === 'emergency';
        $missing = [];
        if (empty($job['skillIds'])) $missing[] = 'required skills';
        if (empty($job['areaId'])) $missing[] = 'location';
        if (empty($job['description']) || mb_strlen(trim($job['description'])) < 20) $missing[] = 'a job description of at least 20 characters';
        if (($job['budgetType'] ?? '') !== 'unknown' && !(($job['budgetMax'] ?? 0) > 0)) $missing[] = 'a budget or "I don\'t know the price"';
        return [
            'primary' => $primary, 'secondary' => $secondary, 'categories' => $cats, 'skills' => $skills,
            'complexity' => $complexity, 'complexityIndex' => $index, 'multiTrade' => count($cats) > 1,
            'urgent' => $urgent, 'missing' => $missing,
        ];
    }

    // ------------------------------------------------------------------ budget
    private static function priceRangeFor(array $a, string $complexity): ?array
    {
        return $a['prices'][$complexity] ?? $a['prices']['medium'] ?? $a['prices']['simple'] ?? null;
    }

    /** Artisan-level budget compatibility score in [0,1], plus a label. */
    public static function budgetScore(array $job, ?array $range): array
    {
        if ($job['budgetType'] === 'unknown' || !$range) {
            return ['score' => 0.5, 'label' => 'quotation needed', 'kind' => 'unknown'];
        }
        $bmin = $job['budgetType'] === 'fixed' ? $job['budgetMax'] : ($job['budgetMin'] ?? 0);
        $bmax = $job['budgetMax'];
        [$pmin, $pmax] = $range;
        $overlap = min($bmax, $pmax) - max($bmin, $pmin);
        if ($overlap >= 0 && $bmax >= $pmin && $bmin <= $pmax) {
            $width = max($bmax - $bmin, 1);
            $coverage = self::clamp($overlap / $width);
            $score = 0.7 + 0.3 * $coverage;
            $kind = 'within';
        } elseif ($bmin > $pmax) {
            $score = 0.9;
            $kind = 'above';
        } else {
            $gap = $pmin - $bmax;
            $score = self::clamp(1 - 2 * $gap / $pmin);
            $kind = 'below';
        }
        if ($kind === 'below' && !empty($job['negotiable'])) $score = min(1, $score + 0.15);
        return ['score' => $score, 'kind' => $kind, 'range' => $range, 'bmin' => $bmin, 'bmax' => $bmax];
    }

    /** Job-level budget assessment against platform benchmarks for (category, complexity). */
    public static function assessBudget(array $job, array $analysis, array $benchmarks, int $matchesCount, array $config): array
    {
        $cfg = $config['benchmark'];
        if ($analysis['multiTrade']) {
            return ['klass' => 'INSUFFICIENT DATA', 'confidence' => 'none',
                'reason' => 'This job needs more than one trade, so a single price benchmark would be misleading.',
                'action' => 'Request quotations from one suitable artisan for each trade.'];
        }
        if ($job['budgetType'] === 'unknown') {
            return ['klass' => 'INSUFFICIENT DATA', 'confidence' => 'none',
                'reason' => 'You did not enter a budget, so no comparison can be made.',
                'action' => 'Request quotations from several suitable artisans.'];
        }
        $b = $benchmarks[$analysis['primary'] . ':' . $analysis['complexity']] ?? null;
        if (!$b || $b['n'] < $cfg['minObservations']) {
            return ['klass' => 'INSUFFICIENT DATA', 'confidence' => 'none',
                'reason' => 'There is currently insufficient historical job data to provide a reliable budget assessment.',
                'action' => 'Request quotations from multiple suitable artisans.'];
        }
        $bmin = $job['budgetType'] === 'fixed' ? $job['budgetMax'] : ($job['budgetMin'] ?? 0);
        $bmax = $job['budgetMax'];
        $confidence = $b['n'] >= $cfg['highConfidence'] ? 'high' : ($b['n'] >= $cfg['mediumConfidence'] ? 'medium' : 'low');
        $typical = self::gh($b['p25']) . ' to ' . self::gh($b['p75']) . ' (median ' . self::gh($b['median']) . ')';
        $basis = "based on {$b['n']} similar {$analysis['complexity']} {$analysis['primary']} jobs";
        if ($bmin >= $b['p75']) {
            $klass = 'HIGH BUDGET';
            $reason = "Your budget is at or above the upper end of what similar jobs usually cost, $typical, $basis.";
            $action = 'You are likely to attract many offers; compare quotations to avoid overpaying.';
        } elseif ($bmax >= $b['median'] && $bmin >= $b['p25']) {
            $many = $matchesCount >= 5;
            $klass = $many ? 'HIGHLY COMPETITIVE' : 'LIKELY SUFFICIENT';
            $reason = "Your budget covers the typical price for this kind of job, $typical, $basis." . ($many ? ' Several suitable artisans work in this range.' : '');
            $action = 'Proceed and compare the recommended artisans.';
        } elseif ($bmax >= $b['p25']) {
            $klass = 'NEGOTIATION RECOMMENDED';
            $reason = "Your budget is in the lower part of the usual range, $typical, $basis.";
            $action = !empty($job['negotiable']) ? 'Because your budget is negotiable, ask suitable artisans for quotations.' : 'Mark the budget as negotiable or ask for quotations.';
        } else {
            $klass = 'POSSIBLY INSUFFICIENT';
            $reason = "Your budget is below the usual range for this kind of job, $typical, $basis.";
            $action = 'Request quotations from multiple artisans or adjust your budget.';
        }
        return ['klass' => $klass, 'confidence' => $confidence, 'reason' => $reason, 'action' => $action, 'benchmark' => $b];
    }

    // ------------------------------------------------------------------ trust
    public static function trustScore(array $a, array $config): array
    {
        $vp = $config['verificationParts'];
        $v = $a['verified'];
        $V = self::clamp((!empty($v['phone']) ? $vp['phone'] : 0) + (!empty($v['email']) ? $vp['email'] : 0)
            + (!empty($v['identity']) ? $vp['identity'] : 0) + (!empty($v['skill']) ? $vp['skill'] : 0));
        $P = self::clamp((float)$a['profileCompleteness']);
        $n = $a['completedJobs'];
        $observed = ($a['completionRate'] + $a['responseRate'] + $a['disputeFreeRate']) / 3;
        $k = $config['reliabilityPrior']['k'];
        $prior = $config['reliabilityPrior']['value'];
        $R = $n > 0 ? ($n * $observed + $k * $prior) / ($n + $k) : $prior;
        $tp = $config['trustParts'];
        $total = $tp['verification'] * $V + $tp['profile'] * $P + $tp['reliability'] * $R;
        $shown = self::round($total, 0);
        $label = $shown >= 90 ? 'EXCELLENT TRUST' : ($shown >= 75 ? 'HIGH TRUST' : ($shown >= 50 ? 'MODERATE TRUST' : 'LOW TRUST'));
        return [
            'total' => $shown, 'V' => $V, 'P' => $P, 'R' => $R, 'label' => $label,
            'parts' => [
                'verification' => self::round($tp['verification'] * $V, 1),
                'profile' => self::round($tp['profile'] * $P, 1),
                'reliability' => self::round($tp['reliability'] * $R, 1),
            ],
        ];
    }

    public static function ratingScore(array $a, array $config): array
    {
        $m = $config['ratingPrior']['m'];
        $mean = $config['ratingPrior']['mean'];
        $ba = ($a['ratingCount'] * $a['ratingMean'] + $m * $mean) / ($a['ratingCount'] + $m);
        return ['bayes' => $ba, 'score' => self::clamp(($ba - 1) / 4)];
    }

    // ------------------------------------------------------------------ component scores
    private static function skillScore(array $analysis, array $a): array
    {
        $req = array_map(fn($s) => $s['id'], $analysis['skills']);
        $have = array_values(array_filter($req, fn($id) => in_array($id, $a['skills'], true)));
        $missing = array_values(array_filter($req, fn($id) => !in_array($id, $a['skills'], true)));
        return ['score' => count($req) ? count($have) / count($req) : 0, 'have' => $have, 'missing' => $missing];
    }

    private static function locationScore(array $job, array $ref, array $a): array
    {
        $jobArea = $ref['areas'][$job['areaId']] ?? null;
        $home = $ref['areas'][$a['baseArea']] ?? null;
        if (!$jobArea || !$home) return ['score' => 0, 'km' => null, 'R' => $a['radiusKm'] ?? null, 'inArea' => false];
        $km = self::haversineKm($jobArea, $home);
        $R = $a['radiusKm'];
        if (in_array($job['areaId'], $a['serviceAreas'], true) || $km <= 0.5 * $R) $score = 1.0;
        elseif ($km <= $R) $score = 1 - ($km - 0.5 * $R) / (0.5 * $R);
        else $score = 0.0;
        return ['score' => self::clamp($score), 'km' => $km, 'R' => $R, 'inArea' => in_array($job['areaId'], $a['serviceAreas'], true)];
    }

    private static function dayIndex(string $iso): int
    {
        // 0 = Sunday .. 6 = Saturday, matching JS getUTCDay().
        return (int)(new \DateTimeImmutable($iso . 'T12:00:00Z'))->format('w');
    }

    private static function addDays(string $iso, int $n): string
    {
        return (new \DateTimeImmutable($iso . 'T12:00:00Z'))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
    }

    private static function isFree(array $a, string $iso): bool
    {
        return in_array(self::dayIndex($iso), $a['weekdays'], true) && !in_array($iso, $a['blocked'] ?? [], true);
    }

    private static function availabilityScore(array $job, array $a, bool $urgent): array
    {
        if (empty($job['date'])) return ['score' => 0.5, 'note' => 'no date given'];
        if (self::isFree($a, $job['date'])) {
            $score = 1.0;
            $note = 'free on the requested date';
        } else {
            $near = null;
            for ($d = 1; $d <= 3 && $near === null; $d++) {
                if (self::isFree($a, self::addDays($job['date'], $d))) $near = $d;
                elseif (self::isFree($a, self::addDays($job['date'], -$d))) $near = -$d;
            }
            if ($near !== null) {
                $score = 0.5;
                $abs = abs($near);
                $note = "free $abs day" . ($abs > 1 ? 's' : '') . ' ' . ($near > 0 ? 'after' : 'before') . ' the requested date';
            } else {
                $score = 0.0;
                $note = 'not free within 3 days of the requested date';
            }
        }
        if ($urgent && empty($a['emergency'])) {
            $score *= 0.7;
            $note .= '; does not take emergency jobs';
        }
        return ['score' => $score, 'note' => $note];
    }

    private static function experienceScore(array $a, array $config): array
    {
        return ['score' => self::clamp($a['years'] / $config['experienceCapYears'])];
    }

    private static function portfolioScore(array $analysis, array $a, array $config): array
    {
        $sims = 0.0;
        $done = 0;
        $items = 0;
        foreach ($analysis['skills'] as $s) {
            $h = $a['history'][$s['id']] ?? 0;
            $p = $a['portfolio'][$s['id']] ?? 0;
            $sims += $h + 0.5 * $p;
            $done += $h;
            $items += $p;
        }
        return ['score' => self::clamp($sims / $config['similarWorkCap']), 'similarJobs' => $done, 'portfolioItems' => $items];
    }

    // ------------------------------------------------------------------ explanation
    private static function explain(array $job, array $analysis, array $a, array $c, array $trust, array $rating, array $ref): array
    {
        $lines = [];
        $ok = function (string $t) use (&$lines) { $lines[] = ['status' => 'ok', 'text' => $t]; };
        $warn = function (string $t) use (&$lines) { $lines[] = ['status' => 'warn', 'text' => $t]; };
        $bad = function (string $t) use (&$lines) { $lines[] = ['status' => 'bad', 'text' => $t]; };
        $names = fn(array $ids) => implode(', ', array_map(fn($id) => mb_strtolower($ref['skills'][$id]['name']), $ids));

        if ($c['skill']['score'] == 1) {
            $ok('Has all the skills your job needs (' . $names($c['skill']['have']) . ').');
        } elseif ($c['skill']['score'] > 0) {
            $warn('Has ' . count($c['skill']['have']) . ' of ' . (count($c['skill']['have']) + count($c['skill']['missing'])) . ' required skills. Missing: ' . $names($c['skill']['missing']) . '.');
        }

        if ($c['location']['inArea']) {
            $ok("Works in {$ref['areas'][$job['areaId']]['name']}, your area (" . self::round($c['location']['km'], 1) . ' km from their base).');
        } elseif ($c['location']['score'] >= 0.99) {
            $ok('Based ' . self::round($c['location']['km'], 1) . " km away, well within their {$c['location']['R']} km travel range.");
        } elseif ($c['location']['score'] > 0) {
            $warn('Based ' . self::round($c['location']['km'], 1) . " km away, near the edge of their {$c['location']['R']} km travel range.");
        } else {
            $bad("Outside their {$c['location']['R']} km travel range (" . self::round($c['location']['km'], 1) . ' km away).');
        }

        $b = $c['budget'];
        if ($b['kind'] === 'unknown') {
            $warn('You did not give a budget, so price fit could not be checked. Ask for a quotation.');
        } elseif ($b['kind'] === 'within') {
            $ok("Your budget overlaps this artisan's usual {$analysis['complexity']} " . mb_strtolower($ref['categories'][$analysis['primary']]['name']) . ' range of ' . self::gh($b['range'][0]) . ' to ' . self::gh($b['range'][1]) . '.');
        } elseif ($b['kind'] === 'above') {
            $ok("Your budget is above this artisan's usual range of " . self::gh($b['range'][0]) . ' to ' . self::gh($b['range'][1]) . ', so you may be quoted less.');
        } else {
            $warn("Your budget is below this artisan's usual range of " . self::gh($b['range'][0]) . ' to ' . self::gh($b['range'][1]) . '.' . (!empty($job['negotiable']) ? ' You marked it negotiable.' : ''));
        }

        $av = $c['availability'];
        if ($av['score'] >= 1) $ok('Available on your requested date.');
        elseif ($av['score'] > 0) $warn("Availability: {$av['note']}.");
        else $bad("Not available: {$av['note']}.");

        if ($c['portfolio']['similarJobs'] > 0) {
            $n = $c['portfolio']['similarJobs'];
            $txt = "Has completed $n similar job" . ($n > 1 ? 's' : '') . ' on the platform';
            $txt .= $c['portfolio']['portfolioItems'] ? " and shows {$c['portfolio']['portfolioItems']} related portfolio project" . ($c['portfolio']['portfolioItems'] > 1 ? 's' : '') . '.' : '.';
            $ok($txt);
        } elseif ($c['portfolio']['portfolioItems'] > 0) {
            $n = $c['portfolio']['portfolioItems'];
            $ok("Shows $n related portfolio project" . ($n > 1 ? 's' : '') . ', but has no completed similar jobs here yet.');
        } else {
            $warn('No similar completed jobs or portfolio projects yet.');
        }

        $yrs = $a['years'];
        if ($yrs >= 8) $ok("$yrs years of experience.");
        elseif ($yrs >= 3) $warn("$yrs years of experience.");
        else $warn("$yrs year" . ($yrs === 1 ? '' : 's') . ' of experience (newer artisan).');

        $vs = array_values(array_filter([
            !empty($a['verified']['identity']) ? 'identity' : null,
            !empty($a['verified']['phone']) ? 'phone' : null,
            !empty($a['verified']['email']) ? 'email' : null,
            !empty($a['verified']['skill']) ? 'skills' : null,
        ]));
        if ($trust['total'] >= 75) $ok("Trust score {$trust['total']}/100 (" . implode(', ', $vs) . ' verified).');
        elseif ($trust['total'] >= 50) $warn("Trust score {$trust['total']}/100. Verified: " . ($vs ? implode(', ', $vs) : 'nothing yet') . '.');
        else $bad("Trust score {$trust['total']}/100. Verified: " . ($vs ? implode(', ', $vs) : 'nothing yet') . '.');

        if ($a['ratingCount'] === 0) {
            $warn('No customer ratings yet.');
        } elseif ($a['ratingMean'] >= 4.5 && $a['ratingCount'] >= 5) {
            $ok('Rated ' . number_format($a['ratingMean'], 1) . " from {$a['ratingCount']} customers.");
        } else {
            $warn('Rated ' . number_format($a['ratingMean'], 1) . " from only {$a['ratingCount']} customer" . ($a['ratingCount'] > 1 ? 's' : '') . '; the score gives this limited weight.');
        }
        return $lines;
    }

    // ------------------------------------------------------------------ main pipeline
    /**
     * @param array $opts ['config' => partial config override, 'benchmarks' => [...]]
     */
    public static function match(array $job, array $ref, array $artisans, array $opts = []): array
    {
        $config = self::DEFAULT_CONFIG;
        if (!empty($opts['config'])) {
            $config = array_replace_recursive($config, $opts['config']);
            // weights are replaced wholesale per component, not deep-merged beyond that
            $config['weights'] = array_merge(self::DEFAULT_CONFIG['weights'], $opts['config']['weights'] ?? []);
        }
        $benchmarks = $opts['benchmarks'] ?? [];
        $analysis = self::analyzeJob($job, $ref);
        $weights = self::effectiveWeights($config, $analysis['urgent']);
        $results = [];
        $excluded = [];

        if (!empty($analysis['missing'])) {
            $msg = implode(', ', $analysis['missing']);
            return [
                'analysis' => $analysis, 'weights' => $weights, 'matches' => [], 'excluded' => [],
                'budget' => ['klass' => 'INSUFFICIENT DATA', 'confidence' => 'none', 'reason' => 'The job is missing information.', 'action' => "Add $msg."],
                'feasibility' => ['level' => 'REQUIRES MORE INFORMATION', 'reasons' => ["Add $msg to receive recommendations."], 'suitableCount' => 0],
                'config' => $config,
            ];
        }

        if ($analysis['multiTrade']) {
            $groups = [];
            foreach ($analysis['categories'] as $cat) {
                $sub = $job;
                $sub['categoryId'] = $cat;
                $sub['skillIds'] = array_values(array_map(fn($s) => $s['id'], array_filter($analysis['skills'], fn($s) => $s['category'] === $cat)));
                $sub['budgetType'] = 'unknown';
                $sub['budgetMin'] = 0;
                $sub['budgetMax'] = 0;
                $groups[$cat] = self::match($sub, $ref, $artisans, $opts);
            }
            $budget = self::assessBudget($job, $analysis, $benchmarks, 0, $config);
            $counts = array_map(fn($cat) => count(array_filter($groups[$cat]['matches'], fn($r) => $r['score'] >= $config['minMatchScore'])), $analysis['categories']);
            $reasons = [];
            foreach ($analysis['categories'] as $i => $cat) {
                $n = $counts[$i];
                $reasons[] = "{$ref['categories'][$cat]['name']}: $n suitable artisan" . ($n === 1 ? '' : 's') . '.';
            }
            $level = in_array(0, $counts, true) ? 'LOW' : (self::allAtLeast($counts, $config['feasibility']['minSuitableForHigh']) ? 'HIGH' : 'MEDIUM');
            $reasons[] = 'This job needs more than one trade: hire one artisan per trade or split it into milestones.';
            $topPerGroup = [];
            foreach ($analysis['categories'] as $cat) {
                if (!empty($groups[$cat]['matches'])) $topPerGroup[] = $groups[$cat]['matches'][0];
            }
            return [
                'analysis' => $analysis, 'weights' => $weights, 'matches' => $topPerGroup, 'groups' => $groups, 'excluded' => [],
                'budget' => $budget,
                'feasibility' => ['level' => $level, 'reasons' => $reasons, 'suitableCount' => array_sum($counts), 'complexity' => $analysis['complexity']],
                'config' => $config,
            ];
        }

        foreach ($artisans as $a) {
            if (($a['status'] ?? '') !== 'approved') {
                $excluded[] = ['artisan' => $a, 'reason' => 'Profile not approved or suspended'];
                continue;
            }
            $sk = self::skillScore($analysis, $a);
            if ($sk['score'] == 0) {
                $excluded[] = ['artisan' => $a, 'reason' => 'No required skills'];
                continue;
            }
            $loc = self::locationScore($job, $ref, $a);
            if ($loc['km'] !== null && $loc['km'] > $config['locationRadiusHardLimit'] * $a['radiusKm']) {
                $excluded[] = ['artisan' => $a, 'reason' => 'Too far from the job'];
                continue;
            }
            $range = self::priceRangeFor($a, $analysis['complexity']);
            $bud = self::budgetScore($job, $range);
            $av = self::availabilityScore($job, $a, $analysis['urgent']);
            $ex = self::experienceScore($a, $config);
            $trust = self::trustScore($a, $config);
            $rt = self::ratingScore($a, $config);
            $pf = self::portfolioScore($analysis, $a, $config);
            $c = [
                'skill' => $sk, 'location' => $loc, 'budget' => $bud, 'availability' => $av,
                'experience' => $ex, 'trust' => ['score' => $trust['total'] / 100], 'rating' => $rt, 'portfolio' => $pf,
            ];
            $breakdown = [];
            $total = 0.0;
            foreach (self::COMPONENTS as $k) {
                $raw = $c[$k]['score'];
                $pts = $raw * $weights[$k];
                $breakdown[$k] = ['raw' => self::round($raw, 3), 'weight' => self::round($weights[$k], 1), 'points' => self::round($pts, 1)];
                $total += $pts;
            }
            $total = self::round($total, 1);
            $cappedReason = null;
            if ($loc['score'] == 0) $cappedReason = 'the job is outside their travel range';
            elseif ($sk['score'] < 0.5) $cappedReason = 'they offer fewer than half of the required skills';
            elseif ($bud['kind'] === 'below' && $bud['score'] < $config['severeBudgetShortfall']) $cappedReason = 'your budget is far below their usual price';
            $capped = null;
            if ($cappedReason !== null && $total > $config['capScore']) {
                $capped = ['reason' => $cappedReason, 'uncapped' => $total];
                $total = (float)$config['capScore'];
            }
            $level = $total >= $config['levels']['highly'] ? self::LEVEL_LABELS[0]
                : ($total >= $config['levels']['recommended'] ? self::LEVEL_LABELS[1]
                : ($total >= $config['levels']['possible'] ? self::LEVEL_LABELS[2] : self::LEVEL_LABELS[3]));
            $expl = self::explain($job, $analysis, $a, $c, $trust, $rt, $ref);
            if ($capped) array_unshift($expl, ['status' => 'bad', 'text' => "Recommendation capped at {$config['capScore']}: {$capped['reason']}."]);
            $results[] = ['artisan' => $a, 'score' => $total, 'level' => $level, 'breakdown' => $breakdown, 'trust' => $trust, 'rating' => $rt, 'capped' => $capped, 'explanation' => $expl, 'detail' => $c];
        }

        usort($results, function ($x, $y) {
            if ($y['score'] !== $x['score']) return $y['score'] <=> $x['score'];
            if ($y['trust']['total'] !== $x['trust']['total']) return $y['trust']['total'] <=> $x['trust']['total'];
            return strcmp($x['artisan']['id'], $y['artisan']['id']);
        });

        $suitable = array_values(array_filter($results, fn($r) => $r['score'] >= $config['minMatchScore']));
        $budget = self::assessBudget($job, $analysis, $benchmarks, count($suitable), $config);
        $feasibility = self::assessFeasibility($job, $analysis, $suitable, $budget, $results, $config);
        return ['analysis' => $analysis, 'weights' => $weights, 'matches' => $results, 'excluded' => $excluded, 'budget' => $budget, 'feasibility' => $feasibility, 'config' => $config];
    }

    private static function allAtLeast(array $counts, int $min): bool
    {
        foreach ($counts as $c) if ($c < $min) return false;
        return true;
    }

    private static function assessFeasibility(array $job, array $analysis, array $suitable, array $budget, array $all, array $config): array
    {
        $reasons = [];
        $n = count($suitable);
        $availableOnDate = count(array_filter($suitable, fn($r) => $r['detail']['availability']['score'] >= 1));
        if ($n === 0) {
            $level = 'LOW';
            $reasons[] = count($all) ? 'No artisan reaches the minimum match score for this job.' : 'No approved artisan offers the required skills in this area.';
        } elseif ($budget['klass'] === 'POSSIBLY INSUFFICIENT' && $n < $config['feasibility']['minSuitableForHigh']) {
            $level = 'LOW';
            $reasons[] = 'Few suitable artisans and the budget looks below the usual range.';
        } elseif ($n >= $config['feasibility']['minSuitableForHigh'] && $budget['klass'] !== 'POSSIBLY INSUFFICIENT' && !($analysis['urgent'] && $availableOnDate === 0)) {
            $level = 'HIGH';
            $reasons[] = "$n suitable artisans found" . ($availableOnDate ? ", $availableOnDate free on your requested date." : '.');
        } else {
            $level = 'MEDIUM';
            if ($n < $config['feasibility']['minSuitableForHigh']) $reasons[] = "Only $n suitable artisan" . ($n > 1 ? 's' : '') . ' found.';
            if ($budget['klass'] === 'POSSIBLY INSUFFICIENT') $reasons[] = 'The budget looks below the usual range.';
            if ($analysis['urgent'] && $availableOnDate === 0) $reasons[] = 'Nobody suitable is free on the requested date.';
        }
        if ($budget['klass'] === 'INSUFFICIENT DATA') $reasons[] = 'Budget could not be checked, so feasibility does not include price.';
        if ($analysis['multiTrade']) $reasons[] = 'This job needs more than one trade (' . implode(', ', $analysis['categories']) . '): hire one artisan per trade or split it into milestones.';
        return ['level' => $level, 'reasons' => $reasons, 'suitableCount' => $n, 'availableOnDate' => $availableOnDate, 'complexity' => $analysis['complexity']];
    }

    // ------------------------------------------------------------------ sensitivity (for evaluation, RQ2)
    public static function sensitivity(array $job, array $ref, array $artisans, array $opts, int $delta = 5, int $k = 3): array
    {
        $base = self::match($job, $ref, $artisans, $opts);
        $baseTop = array_map(fn($r) => $r['artisan']['id'], array_slice($base['matches'], 0, $k));
        $runs = [];
        foreach (self::COMPONENTS as $comp) {
            foreach ([-$delta, $delta] as $d) {
                $cfg = $opts['config'] ?? [];
                $cfg['weights'] = array_merge(self::DEFAULT_CONFIG['weights'], $opts['config']['weights'] ?? []);
                $cfg['weights'][$comp] = max(0, $cfg['weights'][$comp] + $d);
                $r = self::match($job, $ref, $artisans, array_merge($opts, ['config' => $cfg]));
                $top = array_map(fn($x) => $x['artisan']['id'], array_slice($r['matches'], 0, $k));
                $overlapCount = count(array_filter($top, fn($id) => in_array($id, $baseTop, true)));
                $runs[] = ['comp' => $comp, 'delta' => $d, 'top1Same' => ($top[0] ?? null) === ($baseTop[0] ?? null), 'overlap' => $overlapCount / max(count($baseTop), 1)];
            }
        }
        $top1 = count(array_filter($runs, fn($r) => $r['top1Same']));
        $meanOverlap = array_sum(array_column($runs, 'overlap')) / count($runs);
        return ['baseTop' => $baseTop, 'top1Stability' => $top1 / count($runs), 'meanTopKOverlap' => $meanOverlap, 'runs' => $runs];
    }

    /** Reverse direction: rank open jobs for one artisan (two-sided matching). */
    public static function recommendJobs(array $jobs, array $ref, array $artisan, array $opts = []): array
    {
        $out = [];
        foreach ($jobs as $job) {
            $j = $job;
            $an = self::analyzeJob($job, $ref);
            if ($an['multiTrade']) {
                $mine = array_values(array_map(fn($s) => $s['id'], array_filter($an['skills'], fn($s) => $s['category'] === $artisan['category'])));
                if (!$mine) continue;
                $j = $job;
                $j['categoryId'] = $artisan['category'];
                $j['skillIds'] = $mine;
                $j['budgetType'] = 'unknown';
                $j['budgetMin'] = 0;
                $j['budgetMax'] = 0;
            }
            $r = self::match($j, $ref, [$artisan], $opts);
            if ($r['matches']) {
                $m = $r['matches'][0];
                $out[] = ['job' => $job, 'subJob' => $j !== $job ? $j : null, 'score' => $m['score'], 'level' => $m['level'], 'explanation' => $m['explanation'], 'breakdown' => $m['breakdown'], 'budget' => $r['budget']];
            }
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }
}
