<?php
/** @var array $reports */
/** @var string $filter */
$title = 'Reports';
$reasonLabels = [
    'fraud' => 'Fraud', 'fake_profile' => 'Fake profile', 'harassment' => 'Harassment',
    'suspicious_behavior' => 'Suspicious behavior', 'inappropriate_messages' => 'Inappropriate messages',
    'fake_review' => 'Fake review',
];
$statusPill = ['open' => 'bad', 'reviewed' => 'gold', 'dismissed' => 'mute', 'action_taken' => 'ok'];
$tabs = ['open' => 'Open', 'reviewed' => 'Reviewed', 'dismissed' => 'Dismissed', 'action_taken' => 'Action taken', 'all' => 'All'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin · Safety</span>
    <h2 style="margin-top:4px">User reports</h2>
    <p class="muted small" style="margin-top:6px">Fraud, fake profiles, harassment and other safety reports users have submitted.</p>
  </div>

  <div class="tabs">
    <?php foreach ($tabs as $val => $label): ?>
      <a href="<?= e(url('/admin/reports?status=' . $val)) ?>" class="<?= $filter === $val ? 'current' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$reports): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No reports here</h3>
      <p class="muted small" style="margin-top:6px">Nothing in this queue right now.</p>
    </div>
  <?php else: ?>
    <?php foreach ($reports as $r): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3 style="font-size:16px"><?= e($reasonLabels[$r['reason']] ?? ucfirst($r['reason'])) ?></h3>
            <p class="muted small" style="margin-top:4px">
              Reported by <?= e($r['reporter_name']) ?>
              <?php if ($r['reported_name']): ?> about <b style="color:var(--ink)"><?= e($r['reported_name']) ?></b> (<?= e($r['reported_role']) ?>)<?php endif; ?>
              &middot; <?= e(fmtDateTime($r['created_at'])) ?>
            </p>
          </div>
          <span class="pill <?= e($statusPill[$r['status']] ?? 'mute') ?>"><?= e(ucwords(str_replace('_', ' ', $r['status']))) ?></span>
        </div>
        <?php if ($r['details']): ?><p class="small" style="margin-top:10px"><?= nl2br(e($r['details'])) ?></p><?php endif; ?>
        <?php if ($r['admin_note']): ?><p class="small muted" style="margin-top:8px">Admin note: <?= e($r['admin_note']) ?></p><?php endif; ?>
        <?php if ($r['status'] === 'open'): ?>
          <form method="post" action="<?= e(url('/admin/reports/' . (int)$r['id'] . '/act/action')) ?>" class="row" style="margin-top:12px;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <?= csrf() ?>
            <div class="field" style="flex:1;min-width:200px;margin:0">
              <input type="text" name="admin_note" maxlength="500" placeholder="Note (optional)">
            </div>
            <button class="btn primary sm" type="submit">Take action</button>
          </form>
          <div class="row" style="margin-top:8px;gap:8px">
            <form method="post" action="<?= e(url('/admin/reports/' . (int)$r['id'] . '/act/resolve')) ?>"><?= csrf() ?>
              <button class="btn ghost sm" type="submit">Mark reviewed</button>
            </form>
            <form method="post" action="<?= e(url('/admin/reports/' . (int)$r['id'] . '/act/dismiss')) ?>"><?= csrf() ?>
              <button class="btn ghost sm" type="submit">Dismiss</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
