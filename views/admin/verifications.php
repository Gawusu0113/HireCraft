<?php
/** @var array $verifications */
/** @var string $filter */
/** @var string $typeFilter */
$title = 'Verifications';
$typeLabels = ['identity' => 'Identity', 'skill' => 'Skill', 'reference' => 'Reference'];
$statusPill = ['pending' => 'gold', 'approved' => 'ok', 'rejected' => 'bad'];
$tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin · Trust &amp; verification</span>
    <h2 style="margin-top:4px">Verification queue</h2>
    <p class="muted small" style="margin-top:6px">Identity documents, skill certificates and reference letters artisans have submitted. Approving updates their verification level and trust score immediately.</p>
  </div>

  <div class="tabs">
    <?php foreach ($tabs as $val => $label): ?>
      <a href="<?= e(url('/admin/verifications?status=' . $val . ($typeFilter !== '' ? '&type=' . $typeFilter : ''))) ?>" class="<?= $filter === $val ? 'current' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="row" style="gap:8px">
    <a class="btn ghost sm <?= $typeFilter === '' ? 'primary' : '' ?>" href="<?= e(url('/admin/verifications?status=' . $filter)) ?>">All types</a>
    <?php foreach ($typeLabels as $val => $label): ?>
      <a class="btn ghost sm" href="<?= e(url('/admin/verifications?status=' . $filter . '&type=' . $val)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$verifications): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>Nothing here</h3>
      <p class="muted small" style="margin-top:6px">No submissions in this queue right now.</p>
    </div>
  <?php else: ?>
    <?php foreach ($verifications as $v): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3 style="font-size:16px"><?= e($typeLabels[$v['type']] ?? ucfirst($v['type'])) ?> &mdash; <a href="<?= e(url('/artisans/' . (int)$v['artisan_id'])) ?>" style="color:inherit"><?= e($v['business_name'] ?: $v['artisan_name']) ?></a></h3>
            <p class="muted small" style="margin-top:4px"><?= e($v['document_type'] ?? '') ?> &middot; Submitted <?= e(fmtDateTime($v['submitted_at'])) ?></p>
          </div>
          <span class="pill <?= e($statusPill[$v['status']] ?? 'mute') ?>"><?= e(ucfirst($v['status'])) ?></span>
        </div>
        <?php if ($v['document_path']): ?>
          <a class="btn ghost sm" style="margin-top:10px" href="<?= e(uploadUrl($v['document_path'])) ?>" target="_blank" rel="noopener">View document</a>
        <?php endif; ?>
        <?php if ($v['status'] === 'rejected' && $v['reject_reason']): ?>
          <p class="small muted" style="margin-top:8px">Rejected: <?= e($v['reject_reason']) ?></p>
        <?php endif; ?>
        <?php if ($v['status'] === 'pending'): ?>
          <div class="row" style="margin-top:12px;gap:8px;flex-wrap:wrap;align-items:flex-end">
            <form method="post" action="<?= e(url('/admin/verifications/' . (int)$v['id'] . '/approve')) ?>">
              <?= csrf() ?>
              <button class="btn primary sm" type="submit">Approve</button>
            </form>
            <form method="post" action="<?= e(url('/admin/verifications/' . (int)$v['id'] . '/reject')) ?>" class="row" style="gap:8px;align-items:flex-end">
              <?= csrf() ?>
              <div class="field" style="margin:0"><input type="text" name="reject_reason" maxlength="255" placeholder="Reason (optional)"></div>
              <button class="btn ghost sm" type="submit">Reject</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
