<?php
/** @var array $recommendations */
/** @var bool $notApproved */
$title = 'Find jobs';
$levelInfo = [
    'HIGHLY RECOMMENDED' => ['label' => 'HIGHLY RECOMMENDED', 'lvl' => 'lvl-high'],
    'RECOMMENDED' => ['label' => 'RECOMMENDED', 'lvl' => 'lvl-rec'],
    'POSSIBLE MATCH' => ['label' => 'POSSIBLE MATCH', 'lvl' => 'lvl-poss'],
    'LOW COMPATIBILITY' => ['label' => 'LOW COMPATIBILITY', 'lvl' => 'lvl-low'],
];
$breakdownLabels = [
    'skill' => 'Skill match', 'location' => 'Location', 'budget' => 'Budget', 'availability' => 'Availability',
    'experience' => 'Experience', 'trust' => 'Trust', 'rating' => 'Rating', 'portfolio' => 'Portfolio',
];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Artisan</span>
    <h2 style="margin-top:4px">Find jobs</h2>
    <p class="muted small" style="margin-top:6px">Open jobs customers have chosen to list publicly, ranked against your own profile using the same SmartMatch scoring customers see &mdash; highest fit first.</p>
  </div>

  <?php if ($notApproved): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>Awaiting approval</h3>
      <p class="muted small" style="margin-top:6px">Your profile needs admin approval before you can browse and apply to jobs.</p>
    </div>
  <?php elseif (!$recommendations): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No open jobs right now</h3>
      <p class="muted small" style="margin-top:6px">Check back soon, or make sure your skills and service areas are up to date on <a href="<?= e(url('/profile/setup')) ?>">your profile</a>.</p>
    </div>
  <?php else: ?>
    <?php foreach ($recommendations as $rank => $rec): $row = $rec['row']; $lv = $levelInfo[$rec['level']] ?? ['label' => $rec['level'], 'lvl' => 'lvl-poss']; ?>
      <div class="card acard <?= $rank === 0 ? 'top' : '' ?> <?= e($lv['lvl']) ?>">
        <div class="rk"><?= $rank === 0 ? '★' : '#' . ($rank + 1) ?></div>
        <div>
          <h3><?= e($row['title']) ?></h3>
          <p class="muted small" style="margin-top:2px"><?= e($row['customer_name']) ?> &middot; <?= e($row['area_name']) ?><?= $row['preferred_date'] ? ' &middot; ' . e(fmtDate($row['preferred_date'])) : '' ?><?= $row['urgency'] === 'high' ? ' &middot; Urgent' : '' ?><?php if ($row['urgency'] === 'emergency'): ?> &middot; <span style="color:var(--bad);font-weight:700">🚨 Emergency</span><?php endif; ?></p>
          <div class="row" style="margin-top:8px;gap:8px">
            <?php foreach ($rec['skill_names'] as $sn): ?><span class="chip static"><?= e($sn) ?></span><?php endforeach; ?>
          </div>
          <p class="small" style="margin-top:8px"><?= nl2br(e(mb_strimwidth($row['description'], 0, 220, '…'))) ?></p>
          <p class="muted small" style="margin-top:6px">
            <?php if ($row['budget_type'] === 'unknown'): ?>
              Budget: customer doesn't know the price yet
            <?php elseif ($row['budget_type'] === 'fixed'): ?>
              Budget: <?= e(gh((float)$row['budget_max'])) ?> (fixed)
            <?php else: ?>
              Budget: <?= e(gh((float)$row['budget_min'])) ?> &ndash; <?= e(gh((float)$row['budget_max'])) ?><?= $row['is_negotiable'] ? ' (negotiable)' : '' ?>
            <?php endif; ?>
          </p>
          <details class="why" style="margin-top:10px">
            <summary>Why this fits you</summary>
            <ul class="exl" style="margin-top:10px">
              <?php foreach ($rec['explanation'] as $line): ?>
                <li class="<?= e($line['status']) ?>"><b class="dot"></b><span><?= e($line['text']) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <div class="stack s8" style="margin-top:12px">
              <?php foreach ($rec['breakdown'] as $key => $b): $pct = $b['weight'] > 0 ? min(100, ($b['points'] / $b['weight']) * 100) : 0; ?>
                <div class="brk">
                  <span class="lbl"><?= e($breakdownLabels[$key] ?? $key) ?></span>
                  <span class="meter"><i style="width:<?= round($pct, 1) ?>%"></i></span>
                  <span class="pts"><?= number_format($b['points'], 1) ?>/<?= number_format($b['weight'], 0) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        </div>
        <div class="score">
          <div class="ring" style="--p:<?= (float)$rec['score'] ?>"><b><?= (int)round((float)$rec['score']) ?></b></div>
          <div class="pill" style="margin-top:8px;white-space:nowrap"><?= e($lv['label']) ?></div>
          <?php if ($rec['already_applied']): ?>
            <span class="pill mute" style="margin-top:8px">Already applied</span>
          <?php else: ?>
            <form method="post" action="<?= e(url('/artisan/jobs/' . (int)$row['id'] . '/apply')) ?>" style="margin-top:8px">
              <?= csrf() ?>
              <button class="btn primary sm" type="submit">Apply</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
