<?php
/** @var array $artisans */
/** @var string $filter */
$title = 'Artisan verification';
$tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'all' => 'All'];
$statusPill = ['pending' => 'gold', 'approved' => 'ok', 'rejected' => 'bad', 'suspended' => 'bad'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin</span>
    <h2 style="margin-top:4px">Artisan verification</h2>
  </div>

  <div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="<?= e(url('/admin/artisans?status=' . $key)) ?>" class="<?= $filter === $key ? 'current' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$artisans): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>Nothing here</h3>
      <p class="muted small" style="margin-top:6px">No artisans with this status right now.</p>
    </div>
  <?php else: ?>
    <?php foreach ($artisans as $a): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3><?= e($a['business_name'] ?: $a['full_name']) ?></h3>
            <p class="muted small" style="margin-top:4px"><?= e($a['full_name']) ?> · <?= e($a['email']) ?> · <?= e($a['category_name']) ?> · <?= e($a['area_name']) ?></p>
          </div>
          <span class="pill <?= e($statusPill[$a['approval_status']] ?? 'mute') ?>"><?= e(ucfirst($a['approval_status'])) ?></span>
        </div>
        <div class="row" style="margin-top:10px;gap:18px">
          <span class="small muted">Trust <b style="color:var(--ink)"><?= (int)$a['trust_score'] ?>/100</b></span>
          <span class="small muted">Completeness <b style="color:var(--ink)"><?= number_format((float)$a['profile_completeness'] * 100, 0) ?>%</b></span>
          <span class="small muted">Verification <b style="color:var(--ink)"><?= e(ucfirst($a['verification_level'])) ?></b></span>
          <span class="small muted">Joined <b style="color:var(--ink)"><?= e(fmtDate($a['joined_at'])) ?></b></span>
        </div>
        <?php if ($a['bio']): ?><p class="small muted" style="margin-top:8px"><?= nl2br(e($a['bio'])) ?></p><?php endif; ?>
        <div class="row" style="margin-top:12px;gap:8px">
          <?php if ($a['approval_status'] !== 'approved'): ?>
            <form method="post" action="<?= e(url('/admin/artisans/' . (int)$a['user_id'] . '/approve')) ?>"><?= csrf() ?>
              <button class="btn primary sm" type="submit">Approve</button>
            </form>
          <?php endif; ?>
          <?php if ($a['approval_status'] !== 'rejected'): ?>
            <form method="post" action="<?= e(url('/admin/artisans/' . (int)$a['user_id'] . '/reject')) ?>"><?= csrf() ?>
              <button class="btn ghost sm" type="submit">Reject</button>
            </form>
          <?php endif; ?>
          <?php if ($a['approval_status'] === 'approved'): ?>
            <form method="post" action="<?= e(url('/admin/artisans/' . (int)$a['user_id'] . '/suspend')) ?>"><?= csrf() ?>
              <button class="btn danger sm" type="submit">Suspend</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
