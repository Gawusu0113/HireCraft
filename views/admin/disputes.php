<?php
/** @var array $disputes */
/** @var string $filter */
$title = 'Disputes';
$issueLabels = [
    'poor_workmanship' => 'Poor workmanship', 'job_not_completed' => 'Job not completed',
    'artisan_no_show' => 'Artisan did not appear', 'pricing_dispute' => 'Pricing dispute',
    'suspicious_behavior' => 'Suspicious behavior', 'other' => 'Other',
];
$statusPill = ['open' => 'bad', 'under_review' => 'gold', 'waiting_for_response' => 'gold', 'resolved' => 'ok', 'closed' => 'mute'];
$tabs = ['open' => 'Open', 'under_review' => 'Under review', 'waiting_for_response' => 'Waiting for response', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin · Safety</span>
    <h2 style="margin-top:4px">Disputes</h2>
    <p class="muted small" style="margin-top:6px">Disputes customers and artisans have opened on hired jobs.</p>
  </div>

  <div class="tabs">
    <?php foreach ($tabs as $val => $label): ?>
      <a href="<?= e(url('/admin/disputes?status=' . $val)) ?>" class="<?= $filter === $val ? 'current' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$disputes): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No disputes here</h3>
      <p class="muted small" style="margin-top:6px">Nothing in this queue right now.</p>
    </div>
  <?php else: ?>
    <?php foreach ($disputes as $d): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3 style="font-size:16px"><?= e($issueLabels[$d['issue_type']] ?? ucfirst($d['issue_type'])) ?> &mdash; <?= e($d['job_title']) ?></h3>
            <p class="muted small" style="margin-top:4px">Opened by <?= e($d['opened_by_name']) ?><?= $d['against_name'] ? ' against ' . e($d['against_name']) : '' ?> &middot; <?= e(fmtDateTime($d['created_at'])) ?></p>
          </div>
          <span class="pill <?= e($statusPill[$d['status']] ?? 'mute') ?>"><?= e(ucwords(str_replace('_', ' ', $d['status']))) ?></span>
        </div>
        <p class="small" style="margin-top:10px"><?= nl2br(e($d['description'])) ?></p>
        <?php if ($d['resolution']): ?><p class="small muted" style="margin-top:8px">Resolution: <?= e($d['resolution']) ?></p><?php endif; ?>
        <?php if (!in_array($d['status'], ['resolved', 'closed'], true)): ?>
          <form method="post" action="<?= e(url('/admin/disputes/' . (int)$d['id'] . '/resolve')) ?>" class="stack" style="margin-top:12px">
            <?= csrf() ?>
            <div class="field">
              <textarea name="resolution" maxlength="2000" placeholder="Resolution notes (shown to both parties)"></textarea>
            </div>
            <div class="row" style="gap:8px">
              <button class="btn primary sm" type="submit" name="status" value="resolved">Mark resolved</button>
              <button class="btn ghost sm" type="submit" name="status" value="under_review">Mark under review</button>
              <button class="btn ghost sm" type="submit" name="status" value="closed">Close without resolution</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
