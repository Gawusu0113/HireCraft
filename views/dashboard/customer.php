<?php
/** @var array|null $profile */
/** @var array $jobs */
$title = 'My jobs';
$statusPill = static function (string $status): array {
    return match ($status) {
        'posted', 'matched' => ['label' => ucfirst($status), 'class' => 'gold'],
        'quoted', 'hired', 'in_progress' => ['label' => str_replace('_', ' ', ucfirst($status)), 'class' => 'info'],
        'completed', 'reviewed' => ['label' => ucfirst($status), 'class' => 'ok'],
        'cancelled', 'expired', 'disputed' => ['label' => ucfirst($status), 'class' => 'bad'],
        default => ['label' => ucfirst($status), 'class' => 'mute'],
    };
};
?>
<div class="row between">
  <div>
    <span class="eyebrow">Customer dashboard</span>
    <h2 style="margin-top:4px">Your jobs</h2>
    <?php if ($profile): ?>
      <p class="muted small" style="margin-top:4px">
        <?= $profile['area_name'] ? e($profile['area_name']) : 'No area set' ?><?= $profile['address_note'] ? ' · ' . e($profile['address_note']) : '' ?>
      </p>
    <?php endif; ?>
  </div>
  <a class="btn primary" href="<?= e(url('/jobs/new')) ?>">+ Post a job</a>
</div>

<div class="stack" style="margin-top:22px">
  <?php if (!$jobs): ?>
    <div class="card tint" style="text-align:center;padding:34px">
      <h3>No jobs yet</h3>
      <p class="muted small" style="margin-top:6px">Post a job and SmartMatch will rank suitable artisans for you right away.</p>
    </div>
  <?php else: ?>
    <?php foreach ($jobs as $job): $pill = $statusPill($job['status']); ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3><?= e($job['title']) ?></h3>
            <p class="muted small" style="margin-top:4px"><?= e($job['category_name']) ?> · <?= e($job['area_name']) ?> · posted <?= e(fmtDate($job['created_at'])) ?></p>
          </div>
          <span class="pill <?= e($pill['class']) ?>"><?= e($pill['label']) ?></span>
        </div>
        <?php if (in_array($job['status'], ['matched', 'quoted', 'hired', 'in_progress', 'completed', 'reviewed'], true)): ?>
          <div class="row" style="margin-top:10px;gap:8px">
            <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/results')) ?>">View SmartMatch results</a>
            <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'])) ?>">Quotations &amp; status</a>
          </div>
        <?php endif; ?>
        <div class="row" style="margin-top:10px;gap:16px" class="small muted">
          <span class="small muted">Urgency: <b class="ink" style="color:var(--ink)"><?= e(ucfirst($job['urgency'])) ?></b></span>
          <span class="small muted">Budget:
            <b style="color:var(--ink)">
              <?php if ($job['budget_type'] === 'unknown'): ?>Not specified
              <?php elseif ($job['budget_type'] === 'fixed'): ?><?= e(gh((float)$job['budget_max'])) ?>
              <?php else: ?><?= e(gh((float)$job['budget_min'])) ?> – <?= e(gh((float)$job['budget_max'])) ?><?php endif; ?>
            </b>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
