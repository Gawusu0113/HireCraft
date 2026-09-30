<?php
/** @var array $job */
/** @var array $rows */
$title = 'Compare artisans';
$levelInfo = [
    'highly_recommended' => ['label' => 'HIGHLY RECOMMENDED', 'class' => 'ok'],
    'recommended' => ['label' => 'RECOMMENDED', 'class' => 'gold'],
    'possible_match' => ['label' => 'POSSIBLE MATCH', 'class' => 'mute'],
    'low_compatibility' => ['label' => 'LOW COMPATIBILITY', 'class' => 'bad'],
];
$breakdown = [
    ['key' => 'skill_match_score', 'label' => 'Skill match'],
    ['key' => 'location_match_score', 'label' => 'Location'],
    ['key' => 'budget_match_score', 'label' => 'Budget'],
    ['key' => 'availability_score', 'label' => 'Availability'],
    ['key' => 'experience_score', 'label' => 'Experience'],
    ['key' => 'trust_score', 'label' => 'Trust'],
    ['key' => 'rating_score', 'label' => 'Rating'],
    ['key' => 'portfolio_relevance_score', 'label' => 'Portfolio'],
];
?>
<div class="stack">
  <div class="row between">
    <div>
      <span class="eyebrow"><?= e($job['category_name']) ?> &middot; <?= e($job['area_name']) ?></span>
      <h2 style="margin-top:4px">Compare artisans &mdash; <?= e($job['title']) ?></h2>
      <p class="muted small" style="margin-top:6px">Side-by-side view of the <?= count($rows) ?> artisans you picked from your SmartMatch results, including the same per-component scoring behind each recommendation.</p>
    </div>
    <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/results')) ?>">Back to results</a>
  </div>

  <div class="tw">
    <table>
      <thead>
        <tr>
          <th style="min-width:160px">&nbsp;</th>
          <?php foreach ($rows as $r): ?>
            <th style="min-width:200px">
              <a href="<?= e(url('/artisans/' . (int)$r['artisan_id'])) ?>" style="color:inherit"><?= e($r['business_name'] ?: $r['full_name']) ?></a>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="muted small">Overall score</td>
          <?php foreach ($rows as $r): $lv = $levelInfo[$r['recommendation_level']] ?? ['label' => $r['recommendation_level'], 'class' => 'mute']; ?>
            <td><b style="font-size:18px"><?= (int)round((float)$r['overall_match_score']) ?></b> <span class="pill <?= e($lv['class']) ?>" style="margin-left:6px"><?= e($lv['label']) ?></span></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Location &amp; experience</td>
          <?php foreach ($rows as $r): ?>
            <td><?= e($r['artisan_area']) ?> &middot; <?= (int)$r['years_experience'] ?> yrs &middot; <?= (int)$r['travel_radius_km'] ?>km radius<?= $r['accepts_emergency'] ? ' &middot; takes emergencies' : '' ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Trust score</td>
          <?php foreach ($rows as $r): ?>
            <td><b><?= (int)$r['artisan_trust_score'] ?></b>/100</td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Rating</td>
          <?php foreach ($rows as $r): ?>
            <td><?= $r['rating_count'] > 0 ? number_format((float)$r['rating_mean'], 1) . ' ★ (' . (int)$r['rating_count'] . ' review' . ((int)$r['rating_count'] === 1 ? '' : 's') . ')' : 'No reviews yet' ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Jobs completed</td>
          <?php foreach ($rows as $r): ?>
            <td><?= (int)$r['completed_jobs'] ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Typical price range<br><span class="xs">(for this job's complexity)</span></td>
          <?php foreach ($rows as $r): ?>
            <td><?= $r['price_range'] ? e(gh((float)$r['price_range']['min_price'])) . ' – ' . e(gh((float)$r['price_range']['max_price'])) : '<span class="muted">Not listed</span>' ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="muted small">Weekly availability</td>
          <?php foreach ($rows as $r): ?>
            <td>
              <?php if ($r['availability']): ?>
                <?php foreach ($r['availability'] as $slot): ?><span class="chip static" style="margin:2px 4px 2px 0;padding:4px 10px;font-size:12px"><?= e($slot) ?></span><?php endforeach; ?>
              <?php else: ?>
                <span class="muted">Not listed</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php foreach ($breakdown as $b): ?>
          <tr>
            <td class="muted small"><?= e($b['label']) ?> points</td>
            <?php foreach ($rows as $r): $weights = json_decode($r['weights_snapshot'], true) ?: []; $wkey = str_replace(['_match_score', '_score', '_relevance'], '', $b['key']); $max = (float)($weights[$wkey] ?? 0); ?>
              <td><?= number_format((float)$r[$b['key']], 1) ?><?= $max > 0 ? '/' . number_format($max, 0) : '' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td class="muted small">&nbsp;</td>
          <?php foreach ($rows as $r): ?>
            <td>
              <?php if (!in_array($job['status'], ['hired', 'in_progress', 'completed', 'reviewed'], true)): ?>
                <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/request/' . (int)$r['artisan_id'])) ?>">
                  <?= csrf() ?>
                  <button class="btn primary sm" type="submit">Request quotation</button>
                </form>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Why each is a match</h3>
    <div class="grid g<?= min(count($rows), 3) ?>" style="margin-top:12px">
      <?php foreach ($rows as $r): ?>
        <div>
          <b class="small"><?= e($r['business_name'] ?: $r['full_name']) ?></b>
          <ul class="exl" style="margin-top:8px">
            <?php foreach ($r['explanation'] as $line): ?>
              <li class="<?= e($line['status']) ?>"><b class="dot"></b><span><?= e($line['text']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
