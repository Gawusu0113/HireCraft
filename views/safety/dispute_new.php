<?php
/** @var array $job */
/** @var int $jobId */
$title = 'Open a dispute';
$issueLabels = [
    'poor_workmanship' => 'Poor workmanship', 'job_not_completed' => 'Job not completed',
    'artisan_no_show' => 'Artisan did not appear', 'pricing_dispute' => 'Pricing dispute',
    'suspicious_behavior' => 'Suspicious behavior', 'other' => 'Other',
];
?>
<div class="card" style="max-width:560px;margin:0 auto">
  <span class="eyebrow">Job #<?= (int)$jobId ?></span>
  <h2 style="margin-top:4px">Open a dispute</h2>
  <p class="muted small" style="margin-top:6px">Tell us what went wrong. An administrator will review this and help resolve it &mdash; the job is flagged as disputed until then.</p>

  <form method="post" action="<?= e(url('/jobs/' . (int)$jobId . '/dispute')) ?>" class="stack" style="margin-top:20px">
    <?= csrf() ?>
    <div class="field">
      <label for="issue_type">What's the issue?</label>
      <select id="issue_type" name="issue_type" required>
        <option value="">— Choose an issue —</option>
        <?php foreach ($issueLabels as $val => $label): ?>
          <option value="<?= e($val) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="description">Describe what happened</label>
      <textarea id="description" name="description" minlength="10" maxlength="2000" required placeholder="Include dates, what was agreed, and what actually happened."></textarea>
    </div>
    <button class="btn primary lg" type="submit">Open dispute</button>
  </form>
</div>
