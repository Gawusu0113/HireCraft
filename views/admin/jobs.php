<?php
/** @var array $jobs */
/** @var string $filter */
/** @var array $counts */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
$title = 'Jobs';
$statusLabels = [
    'draft' => 'Draft', 'posted' => 'Posted', 'matched' => 'Matched', 'quoted' => 'Quoted',
    'hired' => 'Hired', 'in_progress' => 'In progress', 'completed' => 'Completed', 'reviewed' => 'Reviewed',
    'cancelled' => 'Cancelled', 'expired' => 'Expired', 'disputed' => 'Disputed',
];
$statusPill = [
    'draft' => 'mute', 'posted' => 'gold', 'matched' => 'gold', 'quoted' => 'gold',
    'hired' => 'ok', 'in_progress' => 'ok', 'completed' => 'ok', 'reviewed' => 'ok',
    'cancelled' => 'bad', 'expired' => 'bad', 'disputed' => 'bad',
];
$urgencyLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'emergency' => '🚨 Emergency'];
$tabs = [
    'open' => 'Open', 'active' => 'Active/hired', 'completed' => 'Completed',
    'disputed' => 'Disputed', 'cancelled' => 'Cancelled/expired', 'all' => 'All',
];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin</span>
    <h2 style="margin-top:4px">Jobs</h2>
    <p class="muted small" style="margin-top:6px">Every job request posted on the platform, by lifecycle stage. Click a job to see its full detail, matches, quotations and tracking history.</p>
  </div>

  <div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="<?= e(url('/admin/jobs?status=' . $key)) ?>" class="<?= $filter === $key ? 'current' : '' ?>"><?= e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </div>

  <?php if (!$jobs): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No jobs here</h3>
      <p class="muted small" style="margin-top:6px">Nothing in this stage right now.</p>
    </div>
  <?php else: ?>
    <?php foreach ($jobs as $j): ?>
      <a class="card" style="display:block;text-decoration:none;color:inherit" href="<?= e(url('/jobs/' . (int)$j['id'])) ?>">
        <div class="row between">
          <div>
            <h3 style="font-size:16px"><?= e($j['title']) ?></h3>
            <p class="muted small" style="margin-top:4px"><?= e($j['category_name']) ?> &middot; <?= e($j['area_name']) ?> &middot; Posted by <?= e($j['customer_name']) ?> &middot; <?= e(fmtDate($j['created_at'])) ?></p>
          </div>
          <div style="text-align:right">
            <span class="pill <?= e($statusPill[$j['status']] ?? 'mute') ?>"><?= e($statusLabels[$j['status']] ?? ucfirst($j['status'])) ?></span>
            <?php if ($j['urgency'] !== 'normal'): ?><p class="small muted" style="margin-top:4px"><?= e($urgencyLabels[$j['urgency']] ?? ucfirst($j['urgency'])) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="row" style="margin-top:10px;gap:18px;flex-wrap:wrap">
          <span class="small muted">Budget <b style="color:var(--ink)"><?= $j['budget_type'] === 'unknown' ? 'Not specified' : (e(gh((float)$j['budget_min'])) . ($j['budget_max'] ? ' &ndash; ' . e(gh((float)$j['budget_max'])) : '')) ?></b></span>
          <?php if ($j['complexity']): ?><span class="small muted">Complexity <b style="color:var(--ink)"><?= e(ucfirst($j['complexity'])) ?></b></span><?php endif; ?>
          <?php if ($j['is_multi_trade']): ?><span class="small muted">Multi-trade</span><?php endif; ?>
          <span class="small muted"><?= (int)$j['match_count'] ?> matched artisan<?= (int)$j['match_count'] === 1 ? '' : 's' ?></span>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if ($pages > 1): ?>
      <div class="row between" style="margin-top:4px;align-items:center">
        <span class="muted small">Page <?= $page ?> of <?= $pages ?> &middot; <?= $total ?> job<?= $total === 1 ? '' : 's' ?> total</span>
        <div class="row" style="gap:8px">
          <?php if ($page > 1): ?>
            <a class="btn ghost sm" href="<?= e(url('/admin/jobs?status=' . $filter . '&page=' . ($page - 1))) ?>">&larr; Previous</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="btn ghost sm" href="<?= e(url('/admin/jobs?status=' . $filter . '&page=' . ($page + 1))) ?>">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
