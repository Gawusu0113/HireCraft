<?php
/** @var array $stats */
/** @var array $jobStatusRows */
/** @var array $jobsByCategory */
/** @var array $signupsByWeek */
/** @var array $trustBuckets */
$title = 'Admin';

$statusLabels = [
    'draft' => 'Draft', 'posted' => 'Posted', 'matched' => 'Matched', 'quoted' => 'Quoted',
    'hired' => 'Hired', 'in_progress' => 'In progress', 'completed' => 'Completed', 'reviewed' => 'Reviewed',
    'cancelled' => 'Cancelled', 'expired' => 'Expired', 'disputed' => 'Disputed',
];
$jobStatusLabels = [];
$jobStatusCounts = [];
foreach ($jobStatusRows as $r) {
    $jobStatusLabels[] = $statusLabels[$r['status']] ?? ucfirst($r['status']);
    $jobStatusCounts[] = (int)$r['n'];
}

$categoryLabels = array_column($jobsByCategory, 'name');
$categoryCounts = array_map('intval', array_column($jobsByCategory, 'n'));

// Pivot signups-by-week rows (wk, role, n) into aligned week/customer/artisan series for Chart.js.
$weeks = [];
$byWeek = [];
foreach ($signupsByWeek as $r) {
    if (!in_array($r['wk'], $weeks, true)) $weeks[] = $r['wk'];
    $byWeek[$r['wk']][$r['role']] = (int)$r['n'];
}
sort($weeks);
$customerSeries = [];
$artisanSeries = [];
foreach ($weeks as $wk) {
    $customerSeries[] = $byWeek[$wk]['customer'] ?? 0;
    $artisanSeries[] = $byWeek[$wk]['artisan'] ?? 0;
}

$bucketOrder = ['0-39', '40-59', '60-74', '75-89', '90-100'];
$bucketByLabel = [];
foreach ($trustBuckets as $r) {
    $bucketByLabel[$r['bucket']] = (int)$r['n'];
}
$trustLabels = $bucketOrder;
$trustCounts = array_map(fn($b) => $bucketByLabel[$b] ?? 0, $bucketOrder);
?>
<span class="eyebrow">Admin dashboard</span>
<h2 style="margin-top:4px">Platform overview</h2>

<div class="grid g4" style="margin-top:22px">
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['total_users'] ?></b><span>Total users</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['customers'] ?></b><span>Customers</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['artisans'] ?></b><span>Artisans</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['verified_artisans'] ?></b><span>Verified artisans</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['pending_approval'] ?></b><span>Pending verification</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['active_jobs'] ?></b><span>Active jobs</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['completed_jobs'] ?></b><span>Completed jobs</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['open_disputes'] ?></b><span>Open disputes</span></div></div>
  <div class="card" style="text-align:center"><div class="kpi" style="align-items:center"><b><?= $stats['open_reports'] ?></b><span>Open reports</span></div></div>
</div>

<div class="grid g3" style="margin-top:22px">
  <a class="card" style="text-decoration:none;color:inherit" href="<?= e(url('/find-artisan')) ?>">
    <h3>All artisans</h3>
    <p class="muted small" style="margin-top:6px">Browse every approved artisan &mdash; <?= $stats['artisans'] ?> total, <?= $stats['verified_artisans'] ?> verified</p>
  </a>
  <a class="card" style="text-decoration:none;color:inherit" href="<?= e(url('/admin/jobs')) ?>">
    <h3>Jobs</h3>
    <p class="muted small" style="margin-top:6px"><?= $stats['open_jobs'] ?> open, <?= $stats['active_jobs'] ?> active, <?= $stats['completed_jobs'] ?> completed</p>
  </a>
  <a class="card" style="text-decoration:none;color:inherit" href="<?= e(url('/admin/artisans?status=pending')) ?>">
    <h3>Verification queue</h3>
    <p class="muted small" style="margin-top:6px"><?= $stats['pending_approval'] ?> artisan<?= $stats['pending_approval'] === 1 ? '' : 's' ?> awaiting review</p>
  </a>
  <a class="card" style="text-decoration:none;color:inherit" href="<?= e(url('/admin/weights')) ?>">
    <h3>Matching weights</h3>
    <p class="muted small" style="margin-top:6px">Adjust and preview SmartMatch's scoring weights</p>
  </a>
  <a class="card" style="text-decoration:none;color:inherit" href="<?= e(url('/admin/benchmarks')) ?>">
    <h3>Price benchmarks</h3>
    <p class="muted small" style="margin-top:6px">Typical price ranges behind the budget check</p>
  </a>
</div>

<div class="grid g2" style="margin-top:22px">
  <div class="card">
    <h3 style="font-size:15px">Jobs by status</h3>
    <div style="position:relative;height:260px;margin-top:12px"><canvas id="chartJobStatus"></canvas></div>
  </div>
  <div class="card">
    <h3 style="font-size:15px">Jobs by trade</h3>
    <div style="position:relative;height:260px;margin-top:12px"><canvas id="chartCategory"></canvas></div>
  </div>
  <div class="card">
    <h3 style="font-size:15px">Sign-ups, last 8 weeks</h3>
    <div style="position:relative;height:260px;margin-top:12px"><canvas id="chartSignups"></canvas></div>
  </div>
  <div class="card">
    <h3 style="font-size:15px">Approved-artisan trust score spread</h3>
    <div style="position:relative;height:260px;margin-top:12px"><canvas id="chartTrust"></canvas></div>
  </div>
</div>

<script src="<?= e(asset('js/chart.umd.js')) ?>"></script>
<script>
(function () {
  var data = <?= json_encode([
      'jobStatus' => ['labels' => $jobStatusLabels, 'counts' => $jobStatusCounts],
      'category' => ['labels' => $categoryLabels, 'counts' => $categoryCounts],
      'signups' => ['weeks' => $weeks, 'customers' => $customerSeries, 'artisans' => $artisanSeries],
      'trust' => ['labels' => $trustLabels, 'counts' => $trustCounts],
  ], JSON_UNESCAPED_SLASHES) ?>;

  var palette = ['#1f8a5a', '#f2a93b', '#2f6fb3', '#c0432f', '#7a5cc4', '#3fb0a8', '#d97b46', '#5c8a3f', '#a34c8a', '#4c6ea3', '#b38f2e'];

  if (typeof Chart === 'undefined' || !document.getElementById('chartJobStatus')) return;

  new Chart(document.getElementById('chartJobStatus'), {
    type: 'doughnut',
    data: {
      labels: data.jobStatus.labels,
      datasets: [{ data: data.jobStatus.counts, backgroundColor: palette }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, maintainAspectRatio: false }
  });

  new Chart(document.getElementById('chartCategory'), {
    type: 'bar',
    data: {
      labels: data.category.labels,
      datasets: [{ label: 'Jobs posted', data: data.category.counts, backgroundColor: '#1f8a5a' }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      maintainAspectRatio: false
    }
  });

  new Chart(document.getElementById('chartSignups'), {
    type: 'line',
    data: {
      labels: data.signups.weeks,
      datasets: [
        { label: 'Customers', data: data.signups.customers, borderColor: '#2f6fb3', backgroundColor: 'transparent', tension: 0.3 },
        { label: 'Artisans', data: data.signups.artisans, borderColor: '#f2a93b', backgroundColor: 'transparent', tension: 0.3 }
      ]
    },
    options: {
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      maintainAspectRatio: false
    }
  });

  new Chart(document.getElementById('chartTrust'), {
    type: 'bar',
    data: {
      labels: data.trust.labels,
      datasets: [{ label: 'Artisans', data: data.trust.counts, backgroundColor: '#7a5cc4' }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { title: { display: true, text: 'Trust score' } } },
      maintainAspectRatio: false
    }
  });
})();
</script>
