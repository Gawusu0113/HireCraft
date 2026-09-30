<?php
/** @var array|null $reportedUser */
$title = 'Report a concern';
$reasons = [
    'fraud' => 'Fraud', 'fake_profile' => 'Fake profile', 'harassment' => 'Harassment',
    'suspicious_behavior' => 'Suspicious behavior', 'inappropriate_messages' => 'Inappropriate messages',
    'fake_review' => 'Fake review',
];
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <span class="eyebrow">Safety</span>
  <h2 style="margin-top:4px">Report a concern</h2>
  <?php if ($reportedUser): ?>
    <p class="muted small" style="margin-top:6px">About: <b style="color:var(--ink)"><?= e($reportedUser['full_name']) ?></b></p>
  <?php endif; ?>
  <p class="muted small" style="margin-top:6px">Our team reviews every report. Thank you for helping keep HireCraft safe.</p>

  <form method="post" action="<?= e(url('/reports')) ?>" class="stack" style="margin-top:20px">
    <?= csrf() ?>
    <?php if ($reportedUser): ?><input type="hidden" name="reported_user_id" value="<?= (int)$reportedUser['id'] ?>"><?php endif; ?>
    <div class="field">
      <label for="reason">Reason</label>
      <select id="reason" name="reason" required>
        <option value="">— Choose a reason —</option>
        <?php foreach ($reasons as $val => $label): ?>
          <option value="<?= e($val) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="details">What happened? <span class="muted" style="font-weight:400">(optional)</span></label>
      <textarea id="details" name="details" maxlength="1000" placeholder="Any details that would help us look into this"></textarea>
    </div>
    <button class="btn primary lg" type="submit">Submit report</button>
  </form>
</div>
