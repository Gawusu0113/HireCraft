<?php
/** @var array $dispute */
/** @var array $evidence */
/** @var int $jobId */
/** @var int $me */
$title = 'Dispute';
$issueLabels = [
    'poor_workmanship' => 'Poor workmanship', 'job_not_completed' => 'Job not completed',
    'artisan_no_show' => 'Artisan did not appear', 'pricing_dispute' => 'Pricing dispute',
    'suspicious_behavior' => 'Suspicious behavior', 'other' => 'Other',
];
$statusPill = [
    'open' => 'bad', 'under_review' => 'gold', 'waiting_for_response' => 'gold',
    'resolved' => 'ok', 'closed' => 'mute',
];
$statusLabel = [
    'open' => 'Open', 'under_review' => 'Under review', 'waiting_for_response' => 'Waiting for response',
    'resolved' => 'Resolved', 'closed' => 'Closed',
];
?>
<div class="stack" style="max-width:640px;margin:0 auto">
  <div class="row between">
    <div>
      <span class="eyebrow">Job #<?= (int)$jobId ?></span>
      <h2 style="margin-top:4px"><?= e($issueLabels[$dispute['issue_type']] ?? ucfirst($dispute['issue_type'])) ?></h2>
    </div>
    <span class="pill <?= e($statusPill[$dispute['status']] ?? 'mute') ?>"><?= e($statusLabel[$dispute['status']] ?? $dispute['status']) ?></span>
  </div>

  <div class="card">
    <p class="small"><?= nl2br(e($dispute['description'])) ?></p>
    <p class="muted small" style="margin-top:8px">Opened <?= e(fmtDateTime($dispute['created_at'])) ?></p>
  </div>

  <?php if ($dispute['status'] === 'resolved' && $dispute['resolution']): ?>
    <div class="card tint">
      <h3>Resolution</h3>
      <p class="small" style="margin-top:8px"><?= nl2br(e($dispute['resolution'])) ?></p>
      <p class="muted small" style="margin-top:8px">Resolved <?= e(fmtDateTime($dispute['resolved_at'])) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($evidence): ?>
    <div class="card">
      <h3>Discussion</h3>
      <div class="stack s10" style="margin-top:10px">
        <?php foreach ($evidence as $ev): ?>
          <div>
            <b style="font-size:13.5px"><?= e($ev['full_name']) ?></b>
            <span class="muted small"> &middot; <?= e(fmtDateTime($ev['created_at'])) ?></span>
            <p class="small" style="margin-top:2px"><?= nl2br(e($ev['note'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!in_array($dispute['status'], ['resolved', 'closed'], true)): ?>
    <form method="post" action="<?= e(url('/jobs/' . (int)$jobId . '/dispute/respond')) ?>" class="row" style="gap:10px;align-items:flex-end">
      <?= csrf() ?>
      <div class="field" style="flex:1;margin:0">
        <textarea name="note" maxlength="1000" required placeholder="Add more detail or respond…" style="min-height:56px"></textarea>
      </div>
      <button class="btn primary" type="submit">Send</button>
    </form>
  <?php endif; ?>
</div>
