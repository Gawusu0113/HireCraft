<?php
/** @var array $rows */
/** @var array $summary */
$title = 'Evaluation feedback';
$reasonLabels = [
    'best_match' => 'Best overall match', 'lowest_price' => 'Lowest price', 'highest_rating' => 'Highest rating',
    'most_trusted' => 'Most trusted/verified', 'most_experienced' => 'Most experienced',
    'recommended_by_hirecraft' => 'HireCraft recommended them first', 'other' => 'Other',
];
$helpfulPill = ['yes' => 'ok', 'partially' => 'gold', 'no' => 'bad'];
?>
<div class="stack">
  <div class="row between">
    <div>
      <span class="eyebrow">Admin</span>
      <h2 style="margin-top:4px">Evaluation feedback</h2>
      <p class="muted small" style="margin-top:6px">Raw "was this helpful?" responses customers leave on their SmartMatch results &mdash; the data behind Chapter 4's usefulness/understandability evaluation.</p>
    </div>
    <a class="btn ghost sm" href="<?= e(url('/admin/evaluation/export')) ?>">Export CSV</a>
  </div>

  <?php if ($summary['total'] === 0): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No feedback submitted yet</h3>
      <p class="muted small" style="margin-top:6px">Once customers answer "Was this helpful?" on their SmartMatch results page, responses will appear here.</p>
    </div>
  <?php else: ?>
    <div class="grid g3">
      <div class="card" style="text-align:center">
        <div class="kpi" style="align-items:center"><b><?= (int)$summary['total'] ?></b><span>Responses</span></div>
      </div>
      <div class="card" style="text-align:center">
        <div class="kpi" style="align-items:center"><b><?= $summary['helpfulPct'] !== null ? $summary['helpfulPct'] . '%' : '—' ?></b><span>Found it helpful (yes/partially)</span></div>
      </div>
      <div class="card" style="text-align:center">
        <div class="kpi" style="align-items:center"><b><?= $summary['avgUnderstood'] !== null ? number_format($summary['avgUnderstood'], 2) . '/5' : '—' ?></b><span>Avg. explanation clarity</span></div>
      </div>
    </div>

    <div class="grid g2">
      <div class="card">
        <h3>Was it helpful?</h3>
        <div class="stack s8" style="margin-top:10px">
          <?php foreach (['yes' => 'Yes', 'partially' => 'Partially', 'no' => 'No'] as $val => $label): $n = $summary['helpfulCounts'][$val] ?? 0; $pct = $summary['total'] > 0 ? round($n / $summary['total'] * 100) : 0; ?>
            <div class="brk">
              <span class="lbl"><?= e($label) ?></span>
              <span class="meter"><i style="width:<?= $pct ?>%"></i></span>
              <span class="pts"><?= $n ?> (<?= $pct ?>%)</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card">
        <h3>Did they pick our top pick?</h3>
        <p class="muted small" style="margin-top:6px">Among responses naming who they'd hire.</p>
        <div class="grid g2" style="margin-top:10px">
          <div class="kpi"><b><?= $summary['topPickChosenPct'] !== null ? $summary['topPickChosenPct'] . '%' : '—' ?></b><span>Chose our #1 recommendation</span></div>
          <div class="kpi"><b><?= $summary['avgChosenRank'] !== null ? number_format($summary['avgChosenRank'], 2) : '—' ?></b><span>Avg. rank of who they chose</span></div>
        </div>
      </div>
    </div>

    <?php if ($summary['reasonCounts']): ?>
      <div class="card">
        <h3>Why they'd choose that artisan</h3>
        <div class="stack s8" style="margin-top:10px">
          <?php arsort($summary['reasonCounts']); foreach ($summary['reasonCounts'] as $val => $n): $pct = round($n / array_sum($summary['reasonCounts']) * 100); ?>
            <div class="brk">
              <span class="lbl"><?= e($reasonLabels[$val] ?? $val) ?></span>
              <span class="meter"><i style="width:<?= $pct ?>%"></i></span>
              <span class="pts"><?= $n ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="tw">
      <table>
        <thead>
          <tr><th>Submitted</th><th>Job</th><th>Customer</th><th>Helpful?</th><th>Clarity</th><th>Would hire</th><th>Rank</th><th>Reason</th><th>Comment</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="small muted"><?= e(fmtDate($r['created_at'])) ?></td>
              <td><?= e($r['job_title']) ?><br><span class="muted small"><?= e($r['category_name']) ?></span></td>
              <td><?= e($r['customer_name']) ?></td>
              <td><span class="pill <?= e($helpfulPill[$r['helpful']] ?? 'mute') ?>"><?= e(ucfirst($r['helpful'])) ?></span></td>
              <td><?= $r['understood_explanation'] !== null ? (int)$r['understood_explanation'] . '/5' : '—' ?></td>
              <td><?= $r['chosen_business_name'] ?: ($r['chosen_artisan_name'] ?: '—') ?></td>
              <td><?= $r['chosen_rank'] !== null ? '#' . (int)$r['chosen_rank'] : '—' ?></td>
              <td class="small"><?= $r['reason_chosen'] ? e($reasonLabels[$r['reason_chosen']] ?? $r['reason_chosen']) : '—' ?></td>
              <td class="small muted" style="max-width:220px"><?= $r['comment'] ? e($r['comment']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
