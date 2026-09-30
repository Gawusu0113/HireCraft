<?php
use HireCraft\Support\Auth;
/** @var array $profile */
/** @var array $skills */
/** @var array $serviceAreas */
/** @var array $availability */
/** @var array $prices */
$title = 'My profile';
$weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$approvalPill = match ($profile['approval_status']) {
    'approved' => ['label' => 'Approved', 'class' => 'ok'],
    'pending' => ['label' => 'Pending review', 'class' => 'gold'],
    'rejected' => ['label' => 'Rejected', 'class' => 'bad'],
    'suspended' => ['label' => 'Suspended', 'class' => 'bad'],
    default => ['label' => ucfirst($profile['approval_status']), 'class' => 'mute'],
};
?>
<div class="row between">
  <div>
    <span class="eyebrow">Artisan dashboard</span>
    <h2 style="margin-top:4px"><?= e($profile['business_name'] ?: Auth::name()) ?></h2>
    <p class="muted small" style="margin-top:4px"><?= e($profile['category_name']) ?> · <?= e($profile['area_name']) ?> · <?= (int)$profile['years_experience'] ?> yrs experience</p>
  </div>
  <span class="pill <?= e($approvalPill['class']) ?>"><?= e($approvalPill['label']) ?></span>
</div>

<?php if ($profile['approval_status'] === 'pending'): ?>
  <div class="note" style="margin-top:16px">Your profile is awaiting admin approval and won't appear in customer matches yet.</div>
<?php endif; ?>

<div class="grid g3" style="margin-top:22px">
  <div class="card" style="text-align:center">
    <div class="kpi" style="align-items:center"><b><?= (int)$profile['trust_score'] ?></b><span>Trust score</span></div>
  </div>
  <div class="card" style="text-align:center">
    <div class="kpi" style="align-items:center"><b><?= number_format((float)$profile['profile_completeness'] * 100, 0) ?>%</b><span>Profile completeness</span></div>
  </div>
  <div class="card" style="text-align:center">
    <div class="kpi" style="align-items:center"><b><?= ucfirst($profile['verification_level']) ?></b><span>Verification level</span></div>
  </div>
</div>

<div class="grid g2" style="margin-top:22px">
  <div class="card">
    <h3>Skills</h3>
    <div class="row" style="margin-top:10px;gap:8px">
      <?php foreach ($skills as $s): ?><span class="chip static">✓ <?= e($s['name']) ?></span><?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h3>Service areas</h3>
    <div class="row" style="margin-top:10px;gap:8px">
      <?php foreach ($serviceAreas as $a): ?><span class="chip static"><?= e($a['name']) ?></span><?php endforeach; ?>
    </div>
    <p class="muted small" style="margin-top:10px">Travel radius: <?= (int)$profile['travel_radius_km'] ?> km · Emergency jobs: <?= $profile['accepts_emergency'] ? 'Yes' : 'No' ?></p>
  </div>
  <div class="card">
    <h3>Availability</h3>
    <div class="row" style="margin-top:10px;gap:8px">
      <?php foreach ($availability as $av): ?>
        <span class="chip static"><?= e($weekdayNames[(int)$av['weekday']]) ?> <?= e(substr($av['start_time'], 0, 5)) ?>–<?= e(substr($av['end_time'], 0, 5)) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h3>Typical pricing</h3>
    <div class="tw" style="margin-top:10px">
      <table>
        <thead><tr><th>Complexity</th><th>Range</th></tr></thead>
        <tbody>
        <?php foreach ($prices as $p): ?>
          <tr><td><?= e(ucfirst($p['complexity'])) ?></td><td><?= e(gh((float)$p['min_price'])) ?> – <?= e(gh((float)$p['max_price'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($profile['bio']): ?>
  <div class="card" style="margin-top:22px">
    <h3>Bio</h3>
    <p class="small" style="margin-top:8px"><?= nl2br(e($profile['bio'])) ?></p>
  </div>
<?php endif; ?>

<div class="note" style="margin-top:22px">See <a href="<?= e(url('/artisan/requests')) ?>">job requests</a> for customers asking you for a quotation, or manage your <a href="<?= e(url('/profile/portfolio')) ?>">portfolio of past work</a>.</div>
